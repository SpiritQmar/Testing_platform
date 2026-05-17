<?php

declare(strict_types=1);

require_once __DIR__ . '/EmbeddingsService.php';

final class SearchService
{
    private PDO $pdo;
    private EmbeddingsService $embeddings;

    public function __construct(PDO $pdo, ?EmbeddingsService $embeddings = null)
    {
        $this->pdo = $pdo;
        $this->embeddings = $embeddings ?? new EmbeddingsService();
    }

    public function search(string $query, int $topK = 10, string $type = 'all'): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['mode' => 'empty', 'results' => []];
        }

        $isPureId = preg_match('/^\d+$/', $query) === 1;
        $prefix   = $this->parsePrefix($query);

        if ($prefix !== null) {
            return ['mode' => 'id_lookup', 'results' => $this->lookupById($prefix['type'], $prefix['id'])];
        }

        if ($isPureId) {
            $idVal = (int)$query;
            $merged = array_merge(
                $this->lookupById('question', $idVal),
                $this->lookupById('topic',    $idVal),
                $this->lookupById('student',  $idVal),
                $this->lookupById('import',   $idVal),
                $this->lookupById('exam',     $idVal),
                $this->lookupById('rule',     $idVal),
                $this->lookupById('criteria', $idVal),
                $this->lookupById('answer',   $idVal)
            );
            if (!empty($merged)) {
                return ['mode' => 'id_lookup', 'results' => $merged];
            }
        }

        $sem = $this->semanticSearch($query, $topK, $type);
        if (!empty($sem)) {
            return ['mode' => 'semantic', 'results' => $sem];
        }

        return ['mode' => 'keyword', 'results' => $this->keywordSearch($query, $topK, $type)];
    }

    public function getIndexSize(): int
    {
        try {
            return (int)$this->pdo->query("SELECT COUNT(*) FROM search_embeddings")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    public function rebuildIndex(bool $rebuild = false): array
    {
        if (!$this->embeddings->isEmbeddingsServiceAvailable()) {
            throw new RuntimeException('Python embeddings сервис недоступен');
        }

        if ($rebuild) {
            $this->pdo->exec("DELETE FROM search_embeddings");
        }

        $items = [];

        $qSt = $this->pdo->query(
            "SELECT hq.question_id AS id, hq.question_text AS content,
                    st.discipline_name, st.title AS topic_title
             FROM hier_questions hq
             LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
             WHERE hq.question_text IS NOT NULL AND hq.question_text != ''"
        );
        foreach ($qSt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'type' => 'question', 'id' => (int)$r['id'],
                'content' => (string)$r['content'],
                'meta' => ['discipline' => $r['discipline_name'], 'topic' => $r['topic_title']],
            ];
        }

        $tSt = $this->pdo->query(
            "SELECT syllabus_topic_id AS id, title AS content, discipline_name, keywords
             FROM ai_syllabus_topics WHERE title IS NOT NULL AND title != ''"
        );
        foreach ($tSt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'type' => 'topic', 'id' => (int)$r['id'],
                'content' => (string)$r['content'] . ' ' . (string)($r['keywords'] ?? ''),
                'meta' => ['discipline' => $r['discipline_name']],
            ];
        }

        if (empty($items)) {
            return ['indexed' => 0, 'total' => 0, 'message' => 'Нет данных для индексации'];
        }

        $existingKeys = [];
        if (!$rebuild) {
            $exSt = $this->pdo->query("SELECT entity_type, entity_id FROM search_embeddings");
            while ($r = $exSt->fetch(PDO::FETCH_ASSOC)) {
                $existingKeys[$r['entity_type'] . ':' . $r['entity_id']] = true;
            }
        }

        $todo = array_values(array_filter($items, fn($x) => !isset($existingKeys[$x['type'] . ':' . $x['id']])));
        if (empty($todo)) {
            return ['indexed' => 0, 'total' => count($items), 'message' => 'Индекс актуален'];
        }

        $batchSize = 32;
        $indexed = 0;
        $ins = $this->pdo->prepare(
            "INSERT INTO search_embeddings (entity_type, entity_id, content, meta_json, embedding)
             VALUES (:t, :i, :c, :m, :e)
             ON DUPLICATE KEY UPDATE content = VALUES(content), meta_json = VALUES(meta_json), embedding = VALUES(embedding)"
        );

        for ($i = 0; $i < count($todo); $i += $batchSize) {
            $batch = array_slice($todo, $i, $batchSize);
            $texts = array_map(fn($x) => mb_substr($x['content'], 0, 500, 'UTF-8'), $batch);
            $resp = $this->embeddings->getBatchEmbeddings($texts);
            if (!$resp || empty($resp['embeddings'])) continue;

            foreach ($batch as $idx => $item) {
                if (!isset($resp['embeddings'][$idx])) continue;
                $ins->execute([
                    ':t' => $item['type'],
                    ':i' => $item['id'],
                    ':c' => mb_substr($item['content'], 0, 1000, 'UTF-8'),
                    ':m' => json_encode($item['meta'], JSON_UNESCAPED_UNICODE),
                    ':e' => json_encode($resp['embeddings'][$idx]),
                ]);
                $indexed++;
            }
        }

        return ['indexed' => $indexed, 'total' => count($items), 'mode' => $rebuild ? 'rebuild' : 'incremental'];
    }

    private function parsePrefix(string $query): ?array
    {
        if (!preg_match('/^(q|question|вопрос|t|topic|тема|d|discipline|дисциплина|i|import|импорт|s|student|студент|e|exam|экзамен|r|rule|правило|c|criteria|критерий|a|answer|ответ)\s*[:#]?\s*(\d+)$/iu', $query, $m)) {
            return null;
        }
        $px = mb_strtolower($m[1], 'UTF-8');
        $map = [
            'q' => 'question', 'question' => 'question', 'вопрос' => 'question',
            't' => 'topic', 'topic' => 'topic', 'тема' => 'topic',
            'd' => 'discipline', 'discipline' => 'discipline', 'дисциплина' => 'discipline',
            'i' => 'import', 'import' => 'import', 'импорт' => 'import',
            's' => 'student', 'student' => 'student', 'студент' => 'student',
            'e' => 'exam', 'exam' => 'exam', 'экзамен' => 'exam',
            'r' => 'rule', 'rule' => 'rule', 'правило' => 'rule',
            'c' => 'criteria', 'criteria' => 'criteria', 'критерий' => 'criteria',
            'a' => 'answer', 'answer' => 'answer', 'ответ' => 'answer',
        ];
        if (!isset($map[$px])) return null;
        return ['type' => $map[$px], 'id' => (int)$m[2]];
    }

    private function semanticSearch(string $query, int $topK, string $type): array
    {
        if (!$this->embeddings->isEmbeddingsServiceAvailable()) return [];
        $qEmb = $this->embeddings->getEmbedding($query);
        if (!$qEmb || empty($qEmb['embedding'])) return [];
        $qVec = $qEmb['embedding'];

        $whereType = '';
        if ($type === 'question' || $type === 'topic') {
            $whereType = "WHERE entity_type = " . $this->pdo->quote($type);
        }
        $stmt = $this->pdo->query("SELECT entity_type, entity_id, content, meta_json, embedding FROM search_embeddings $whereType");

        $scored = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vec = json_decode($row['embedding'], true);
            if (!is_array($vec) || count($vec) !== count($qVec)) continue;
            $scored[] = [
                'entity_type' => $row['entity_type'],
                'entity_id'   => (int)$row['entity_id'],
                'content'     => $row['content'],
                'meta'        => $row['meta_json'] ? json_decode($row['meta_json'], true) : null,
                'score'       => self::cosineSim($qVec, $vec),
            ];
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $topK);
    }

    private function keywordSearch(string $query, int $topK, string $type): array
    {
        $like = '%' . $query . '%';
        $rows = [];

        $st = $this->pdo->prepare(
            "SELECT 'question' AS entity_type, hq.question_id AS entity_id,
                    hq.question_text AS content, st.discipline_name, st.title AS topic_title
             FROM hier_questions hq
             LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
             WHERE hq.question_text LIKE :q LIMIT :lim"
        );
        $st->bindValue(':q', $like);
        $st->bindValue(':lim', $topK, PDO::PARAM_INT);
        $st->execute();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'entity_type' => 'question',
                'entity_id'   => (int)$r['entity_id'],
                'content'     => mb_substr((string)$r['content'], 0, 200, 'UTF-8'),
                'meta'        => ['discipline' => $r['discipline_name'], 'topic' => $r['topic_title']],
                'score'       => null,
            ];
        }

        $st = $this->pdo->prepare(
            "SELECT 'topic' AS entity_type, syllabus_topic_id AS entity_id,
                    title AS content, discipline_name
             FROM ai_syllabus_topics
             WHERE title LIKE :q OR keywords LIKE :q LIMIT :lim"
        );
        $st->bindValue(':q', $like);
        $st->bindValue(':lim', $topK, PDO::PARAM_INT);
        $st->execute();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rows[] = [
                'entity_type' => 'topic',
                'entity_id'   => (int)$r['entity_id'],
                'content'     => (string)$r['content'],
                'meta'        => ['discipline' => $r['discipline_name'], 'sections' => ['semantic', 'syllabus-link']],
                'score'       => null,
            ];
        }

        $this->safeAppend($rows, function() use ($like, $topK) {
            $st = $this->pdo->prepare(
                "SELECT DISTINCT discipline_name FROM ai_syllabus_topics WHERE discipline_name LIKE :q LIMIT :lim"
            );
            $st->bindValue(':q', $like); $st->bindValue(':lim', $topK, PDO::PARAM_INT); $st->execute();
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [
                    'entity_type' => 'discipline',
                    'entity_id'   => crc32((string)$r['discipline_name']),
                    'content'     => (string)$r['discipline_name'],
                    'meta'        => ['discipline' => $r['discipline_name']],
                    'score'       => null,
                ];
            }
            return $out;
        });

        $this->safeAppend($rows, function() use ($like, $topK) {
            $st = $this->pdo->prepare(
                "SELECT import_id, source_filename, import_type, rows_imported FROM imports_log
                 WHERE source_filename LIKE :q OR import_type LIKE :q LIMIT :lim"
            );
            $st->bindValue(':q', $like); $st->bindValue(':lim', $topK, PDO::PARAM_INT); $st->execute();
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [
                    'entity_type' => 'import',
                    'entity_id'   => (int)$r['import_id'],
                    'content'     => (string)$r['source_filename'],
                    'meta'        => ['type' => $r['import_type'], 'rows' => (int)$r['rows_imported']],
                    'score'       => null,
                ];
            }
            return $out;
        });

        $this->safeAppend($rows, function() use ($like, $topK) {
            $st = $this->pdo->prepare("SELECT rule_id, rule_name, is_active FROM rule_classifiers WHERE rule_name LIKE :q LIMIT :lim");
            $st->bindValue(':q', $like); $st->bindValue(':lim', $topK, PDO::PARAM_INT); $st->execute();
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [
                    'entity_type' => 'rule',
                    'entity_id'   => (int)$r['rule_id'],
                    'content'     => (string)$r['rule_name'],
                    'meta'        => ['active' => (bool)$r['is_active']],
                    'score'       => null,
                ];
            }
            return $out;
        });

        $this->safeAppend($rows, function() use ($like, $topK) {
            $st = $this->pdo->prepare(
                "SELECT criteria_id, name_russian, name_english, weight_percent FROM evaluation_criteria
                 WHERE name_russian LIKE :q OR name_english LIKE :q OR name_kazakh LIKE :q LIMIT :lim"
            );
            $st->bindValue(':q', $like); $st->bindValue(':lim', $topK, PDO::PARAM_INT); $st->execute();
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [
                    'entity_type' => 'criteria',
                    'entity_id'   => (int)$r['criteria_id'],
                    'content'     => (string)$r['name_russian'],
                    'meta'        => ['name_en' => $r['name_english'], 'weight' => (float)$r['weight_percent']],
                    'score'       => null,
                ];
            }
            return $out;
        });

        $this->safeAppend($rows, function() use ($like, $topK) {
            $st = $this->pdo->prepare(
                "SELECT id, work_id, language, final_score, SUBSTRING(answer_text,1,200) AS preview
                 FROM student_answers
                 WHERE answer_text LIKE :q OR teacher_comment LIKE :q OR work_id LIKE :q LIMIT :lim"
            );
            $st->bindValue(':q', $like); $st->bindValue(':lim', $topK, PDO::PARAM_INT); $st->execute();
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = [
                    'entity_type' => 'answer',
                    'entity_id'   => (int)$r['id'],
                    'content'     => (string)$r['preview'],
                    'meta'        => ['work_id' => $r['work_id'], 'lang' => $r['language'], 'score' => (float)$r['final_score']],
                    'score'       => null,
                ];
            }
            return $out;
        });

        return array_slice($rows, 0, $topK);
    }

    private function safeAppend(array &$rows, callable $fn): void
    {
        try {
            foreach ($fn() as $r) $rows[] = $r;
        } catch (Throwable $e) {}
    }

    public function lookupById(string $entity, int $id): array
    {
        $out = [];
        try {
            switch ($entity) {
                case 'question':  $out = $this->lookupQuestion($id); break;
                case 'topic':     $out = $this->lookupTopic($id); break;
                case 'student':   $out = $this->lookupStudent($id); break;
                case 'import':    $out = $this->lookupImport($id); break;
                case 'exam':      $out = $this->lookupExam($id); break;
                case 'rule':      $out = $this->lookupRule($id); break;
                case 'criteria':  $out = $this->lookupCriteria($id); break;
                case 'answer':    $out = $this->lookupAnswer($id); break;
            }
        } catch (Throwable $e) {}
        return $out;
    }

    private function lookupQuestion(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT hq.question_id, hq.question_text, st.discipline_name, st.title AS topic_title
             FROM hier_questions hq
             LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
             WHERE hq.question_id = :id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];

        $valStatus = $this->scalar("SELECT status FROM question_validation_cache WHERE question_id = :id LIMIT 1", $id);
        $semLevel  = $this->scalar("SELECT alignment_level FROM question_semantic_cache WHERE question_id = :id LIMIT 1", $id);

        $stats = null;
        try {
            $sg = $this->pdo->prepare(
                "SELECT ROUND(AVG(received_score),1) AS avg_s, COUNT(*) AS attempts
                 FROM raw_exam_results WHERE question_id = :id AND received_score IS NOT NULL"
            );
            $sg->execute([':id' => $id]);
            $stats = $sg->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}

        return [[
            'entity_type' => 'question',
            'entity_id'   => (int)$r['question_id'],
            'content'     => mb_substr((string)$r['question_text'], 0, 200, 'UTF-8'),
            'meta'        => [
                'discipline'        => $r['discipline_name'],
                'topic'             => $r['topic_title'],
                'validation_status' => $valStatus,
                'alignment_level'   => $semLevel,
                'avg_score'         => $stats ? (float)$stats['avg_s'] : null,
                'attempts'          => $stats ? (int)$stats['attempts'] : 0,
                'sections'          => ['quality', 'validation', 'correlation', 'semantic'],
            ],
            'score' => null,
        ]];
    }

    private function lookupTopic(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT syllabus_topic_id, title, discipline_name FROM ai_syllabus_topics WHERE syllabus_topic_id = :id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'topic',
            'entity_id'   => (int)$r['syllabus_topic_id'],
            'content'     => (string)$r['title'],
            'meta'        => ['discipline' => $r['discipline_name'], 'sections' => ['semantic', 'syllabus-link']],
            'score'       => null,
        ]];
    }

    private function lookupStudent(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT student_id, ROUND(AVG(received_score),2) AS avg_s,
                    MIN(received_score) AS min_s, MAX(received_score) AS max_s, COUNT(*) AS n_items
             FROM raw_exam_results WHERE student_id = :id AND received_score IS NOT NULL GROUP BY student_id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'student',
            'entity_id'   => (int)$r['student_id'],
            'content'     => 'Студент #' . $r['student_id'],
            'meta'        => [
                'avg' => (float)$r['avg_s'], 'min' => (float)$r['min_s'],
                'max' => (float)$r['max_s'], 'n_items' => (int)$r['n_items'],
            ],
            'score' => null,
        ]];
    }

    private function lookupImport(int $id): array
    {
        $st = $this->pdo->prepare("SELECT import_id, source_filename, import_type, rows_imported FROM imports_log WHERE import_id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'import',
            'entity_id'   => (int)$r['import_id'],
            'content'     => (string)$r['source_filename'],
            'meta'        => ['type' => $r['import_type'], 'rows' => (int)$r['rows_imported']],
            'score'       => null,
        ]];
    }

    private function lookupExam(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT exam_id, COUNT(DISTINCT student_id) AS students, COUNT(*) AS attempts
             FROM raw_exam_results WHERE exam_id = :id GROUP BY exam_id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'exam',
            'entity_id'   => (int)$r['exam_id'],
            'content'     => 'Экзамен #' . $r['exam_id'],
            'meta'        => ['students' => (int)$r['students'], 'attempts' => (int)$r['attempts']],
            'score'       => null,
        ]];
    }

    private function lookupRule(int $id): array
    {
        $st = $this->pdo->prepare("SELECT rule_id, rule_name, is_active FROM rule_classifiers WHERE rule_id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'rule',
            'entity_id'   => (int)$r['rule_id'],
            'content'     => (string)$r['rule_name'],
            'meta'        => ['active' => (bool)$r['is_active']],
            'score'       => null,
        ]];
    }

    private function lookupCriteria(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT criteria_id, name_russian, name_english, weight_percent FROM evaluation_criteria WHERE criteria_id = :id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'criteria',
            'entity_id'   => (int)$r['criteria_id'],
            'content'     => (string)$r['name_russian'],
            'meta'        => ['name_en' => $r['name_english'], 'weight' => (float)$r['weight_percent']],
            'score'       => null,
        ]];
    }

    private function lookupAnswer(int $id): array
    {
        $st = $this->pdo->prepare(
            "SELECT id, work_id, language, final_score, plagiarism_penalty,
                    SUBSTRING(answer_text,1,200) AS preview FROM student_answers WHERE id = :id LIMIT 1"
        );
        $st->execute([':id' => $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if (!$r) return [];
        return [[
            'entity_type' => 'answer',
            'entity_id'   => (int)$r['id'],
            'content'     => (string)$r['preview'],
            'meta'        => [
                'work_id' => $r['work_id'], 'lang' => $r['language'],
                'score'   => (float)$r['final_score'], 'penalty' => (float)$r['plagiarism_penalty'],
            ],
            'score' => null,
        ]];
    }

    private function scalar(string $sql, int $id): ?string
    {
        try {
            $st = $this->pdo->prepare($sql);
            $st->execute([':id' => $id]);
            $v = $st->fetchColumn();
            return $v !== false ? (string)$v : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public static function cosineSim(array $a, array $b): float
    {
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na  += $a[$i] * $a[$i];
            $nb  += $b[$i] * $b[$i];
        }
        if ($na <= 0 || $nb <= 0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
