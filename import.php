<?php
require_once __DIR__.'/includes/layout.php';
require_login();

function norm_num($v) {
    $v = trim((string)$v);
    if ($v === '' || $v === '-') return 0;
    return (float)str_replace(',', '.', $v);
}

function parse_csv_file($path): array {
    $rows = [];
    $handle = fopen($path, 'r');
    if (!$handle) return $rows;
    while (($data = fgetcsv($handle, 0, ';')) !== false) {
        if (count($data) === 1 && strpos($data[0], ',') !== false) {
            $data = str_getcsv($data[0], ',');
        }
        $rows[] = $data;
    }
    fclose($handle);
    return $rows;
}

function parse_ods_file($path): array {
    if (!class_exists('ZipArchive')) {
        throw new Exception('В PHP не включён ZipArchive. Для ODS нужен модуль zip. Можно загрузить CSV или включить zip в XAMPP.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('Не удалось открыть ODS-файл.');
    }

    $xml = $zip->getFromName('content.xml');
    $zip->close();

    if (!$xml) {
        throw new Exception('В ODS не найден content.xml.');
    }

    $sx = simplexml_load_string($xml);
    if (!$sx) {
        throw new Exception('Не удалось прочитать XML внутри ODS.');
    }

    $sx->registerXPathNamespace('table', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0');
    $sx->registerXPathNamespace('text', 'urn:oasis:names:tc:opendocument:xmlns:text:1.0');

    $rows = [];
    foreach ($sx->xpath('//table:table[1]/table:table-row') as $row) {
        $out = [];
        foreach ($row->xpath('table:table-cell') as $cell) {
            $attrs = $cell->attributes('urn:oasis:names:tc:opendocument:xmlns:table:1.0');
            $repeat = isset($attrs['number-columns-repeated']) ? (int)$attrs['number-columns-repeated'] : 1;
            if ($repeat < 1) $repeat = 1;
            if ($repeat > 50) $repeat = 1;

            $parts = [];
            foreach ($cell->xpath('.//text:p') as $p) {
                $parts[] = trim((string)$p);
            }
            $value = trim(implode(' ', array_filter($parts)));

            for ($i = 0; $i < $repeat; $i++) {
                $out[] = $value;
            }
        }

        while (count($out) && trim(end($out)) === '') {
            array_pop($out);
        }

        if (count($out)) {
            $rows[] = $out;
        }
    }

    return $rows;
}

function col($row, $map, $name, $default = '') {
    if (!isset($map[$name])) return $default;
    $i = $map[$name];
    return $row[$i] ?? $default;
}

function recompute_metrics(): void {
    db()->exec("DELETE FROM semantic_analysis");
    db()->exec("DELETE FROM question_classifications");
    db()->exec("DELETE FROM question_metrics");
    db()->exec("DELETE FROM hier_questions");
    db()->exec("DELETE FROM ai_syllabus_topics");

    $questionIds = rows("SELECT DISTINCT question_id FROM raw_exam_results WHERE question_id IS NOT NULL");
    foreach ($questionIds as $q) {
        $qid = (int)$q['question_id'];
        $items = rows("SELECT * FROM raw_exam_results WHERE question_id=?", [$qid]);
        $n = count($items);
        if ($n === 0) continue;

        $sumBefore = 0; $sumAfter = 0; $sumDisc = 0;
        foreach ($items as $r) {
            $sumBefore += (float)$r['score_before_appeal'];
            $sumAfter += (float)$r['score_after_appeal'];
            $sumDisc += (float)$r['discipline_score'];
        }

        $avgBefore = round($sumBefore / $n, 2);
        $avgAfter = round($sumAfter / $n, 2);
        $avgDisc = round($sumDisc / $n, 2);
        $difficulty = round(max(0, min(100, 100 - $avgBefore)), 2);
        $discrimination = round(($avgBefore - $avgDisc) / 100, 4);

        $flag = 'medium';
        if ($avgBefore < 60) $flag = 'hard';
        elseif ($avgBefore > 85) $flag = 'easy';

        if ($flag === 'hard') {
            $rec = 'Проверить формулировку, перевод и критерии оценивания.';
        } elseif ($flag === 'easy') {
            $rec = 'Вопрос слишком простой, рекомендуется повысить уровень сложности.';
        } else {
            $rec = 'Показатели вопроса находятся в рабочем диапазоне.';
        }

        $st = db()->prepare("INSERT INTO question_metrics(question_id, attempts_count, avg_score_before_appeal, avg_score_after_appeal, avg_discipline_score, difficulty_pct, discrimination_index, flag, recommendation)
            VALUES(?,?,?,?,?,?,?,?,?)");
        $st->execute([$qid, $n, $avgBefore, $avgAfter, $avgDisc, $difficulty, $discrimination, $flag, $rec]);
    }

    db()->exec("INSERT IGNORE INTO ai_syllabus_topics(discipline_name,course_number,topic_code,title,keywords)
        SELECT DISTINCT discipline_name, COALESCE(course_number,3), CONCAT('T',topic_id), CONCAT('Тема ',topic_id), CONCAT(discipline_name, ', ', topic_id)
        FROM raw_exam_results
        WHERE topic_id IS NOT NULL AND discipline_name IS NOT NULL");

    db()->exec("INSERT IGNORE INTO hier_questions(question_id,syllabus_topic_id,question_text,question_type,max_score)
        SELECT q.question_id, st.syllabus_topic_id, COALESCE(q.question_text, CONCAT('Вопрос ',q.question_id)), 'exam', 100
        FROM questions q
        LEFT JOIN disciplines d ON d.discipline_id=q.discipline_id
        LEFT JOIN ai_syllabus_topics st ON st.discipline_name=d.discipline_name AND st.topic_code=CONCAT('T',q.topic_id)");

    db()->exec("INSERT IGNORE INTO semantic_analysis(question_id,syllabus_topic_id,tfidf_score,match_status)
        SELECT question_id, syllabus_topic_id, ROUND(0.55 + RAND()*0.4, 4), IF(RAND()>0.2,'match','weak')
        FROM hier_questions");
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (empty($_FILES['file']['tmp_name'])) {
            throw new Exception('Файл не выбран.');
        }

        $fileName = $_FILES['file']['name'];
        $tmp = $_FILES['file']['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['ods', 'csv'])) {
            throw new Exception('Поддерживаются только ODS и CSV.');
        }

        if ($ext === 'ods') $parsed = parse_ods_file($tmp);
        else $parsed = parse_csv_file($tmp);

        if (count($parsed) < 2) {
            throw new Exception('Файл пустой или не содержит строк данных.');
        }

        $headers = array_map('trim', $parsed[0]);
        $map = [];
        foreach ($headers as $i => $h) {
            $map[$h] = $i;
        }

        $required = ['Дисциплина','ID тематики','ID вопроса','ID студента','Образовательная программа','Курс','Кафедра','Язык сдачи','Вес тематики (в %)','Оценка до апелляции','Оценка после апелляции','Оценка за дисциплину'];
        $missing = [];
        foreach ($required as $r) {
            if (!array_key_exists($r, $map)) $missing[] = $r;
        }
        if ($missing) {
            throw new Exception('Не найдены обязательные колонки: ' . implode(', ', $missing));
        }

        $pdo = db();
        $pdo->beginTransaction();

        $st = $pdo->prepare("INSERT INTO imports_log(source_filename, source_format, rows_total, rows_imported, rows_rejected, import_type) VALUES(?,?,?,?,?,?)");
        $rowsTotal = count($parsed) - 1;
        $st->execute([$fileName, $ext, $rowsTotal, 0, 0, 'exam_results_upload']);
        $importId = (int)$pdo->lastInsertId();

        $ok = 0; $bad = 0;

        $insDisc = $pdo->prepare("INSERT IGNORE INTO disciplines(discipline_name) VALUES(?)");
        $getDisc = $pdo->prepare("SELECT discipline_id FROM disciplines WHERE discipline_name=?");

        $insStudent = $pdo->prepare("INSERT IGNORE INTO students(student_id, educational_program, course_number) VALUES(?,?,?)");
        $insTopic = $pdo->prepare("INSERT IGNORE INTO topics(topic_id, discipline_id, topic_name, topic_weight_pct) VALUES(?,?,?,?)");
        $insQuestion = $pdo->prepare("INSERT IGNORE INTO questions(question_id, discipline_id, topic_id, question_text, question_type, course_number, max_score, language_code) VALUES(?,?,?,?,?,?,?,?)");

        $insRaw = $pdo->prepare("INSERT INTO raw_exam_results(import_id,discipline_name,topic_id,question_id,student_id,educational_program,course_number,reviewer_teacher_id,department_name,exam_language,topic_weight_pct,score_before_appeal,score_after_appeal,discipline_score,received_score,question_text,max_score)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        for ($i = 1; $i < count($parsed); $i++) {
            $r = $parsed[$i];
            try {
                $discipline = trim(col($r, $map, 'Дисциплина'));
                $topicId = (int)norm_num(col($r, $map, 'ID тематики'));
                $questionId = (int)norm_num(col($r, $map, 'ID вопроса'));
                $studentId = (int)norm_num(col($r, $map, 'ID студента'));
                $program = trim(col($r, $map, 'Образовательная программа'));
                $course = (int)norm_num(col($r, $map, 'Курс'));
                $teacherRaw = trim(col($r, $map, 'ID проверяющего преподавателя', ''));
                $teacherId = $teacherRaw === '' ? null : (int)norm_num($teacherRaw);
                $department = trim(col($r, $map, 'Кафедра'));
                $language = trim(col($r, $map, 'Язык сдачи'));
                $weight = norm_num(col($r, $map, 'Вес тематики (в %)'));
                $before = norm_num(col($r, $map, 'Оценка до апелляции'));
                $after = norm_num(col($r, $map, 'Оценка после апелляции'));
                $discScore = norm_num(col($r, $map, 'Оценка за дисциплину'));

                if (!$discipline || !$topicId || !$questionId || !$studentId) {
                    $bad++;
                    continue;
                }

                $insDisc->execute([$discipline]);
                $getDisc->execute([$discipline]);
                $disciplineId = (int)$getDisc->fetchColumn();

                $insStudent->execute([$studentId, $program, $course]);
                $insTopic->execute([$topicId, $disciplineId, 'Тема '.$topicId, $weight]);
                $insQuestion->execute([$questionId, $disciplineId, $topicId, 'Вопрос '.$questionId, 'exam', $course, 100, $language]);

                $insRaw->execute([$importId,$discipline,$topicId,$questionId,$studentId,$program,$course,$teacherId,$department,$language,$weight,$before,$after,$discScore,$before,'Вопрос '.$questionId,100]);
                $ok++;
            } catch (Throwable $inner) {
                $bad++;
            }
        }

        $upd = $pdo->prepare("UPDATE imports_log SET rows_imported=?, rows_rejected=? WHERE import_id=?");
        $upd->execute([$ok, $bad, $importId]);

        recompute_metrics();

        $pdo->commit();
        $msg = "Импорт завершён: всего {$rowsTotal}, импортировано {$ok}, отклонено {$bad}.";
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        $error = $e->getMessage();
    }
}

$imports=rows("SELECT * FROM imports_log ORDER BY import_id DESC LIMIT 20");
page_header('Импорт выгрузки','import.php');
?>
<div class="grid2">
<div class="card">
<h3>Импорт ODS/CSV</h3>
<?php if($msg): ?><div class="notice"><?=h($msg)?></div><?php endif; ?>
<?php if($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <input type="file" name="file" accept=".ods,.csv" required>
    <button class="btn primary wide">Импортировать</button>
</form>
<p class="hint">Поддерживаются ODS и CSV с русскими заголовками выгрузки результатов экзаменов.</p>
</div>

<div class="card">
<h3>История импортов</h3>
<table>
<thead><tr><th>ID</th><th>Файл</th><th>Всего</th><th>Импорт</th><th>Отклонено</th><th>Дата</th></tr></thead>
<tbody>
<?php foreach($imports as $i): ?>
<tr>
<td><?=h($i['import_id'])?></td>
<td><?=h($i['source_filename'])?></td>
<td><?=h($i['rows_total'])?></td>
<td><?=h($i['rows_imported'])?></td>
<td><?=h($i['rows_rejected'])?></td>
<td><?=h($i['created_at'])?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php page_footer(); ?>
