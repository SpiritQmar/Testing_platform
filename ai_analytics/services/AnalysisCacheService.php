<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/ValidationService.php';
require_once __DIR__ . '/TfIdfService.php';

final class AnalysisCacheService
{
    private PDO $databaseConnection;
    private Logger $logger;
    private ValidationService $validationService;
    private TfIdfService $tfidfService;

    public function __construct(PDO $databaseConnection, ValidationService $validationService, TfIdfService $tfidfService)
    {
        $this->databaseConnection = $databaseConnection;
        $this->logger = getLogger();
        $this->validationService = $validationService;
        $this->tfidfService = $tfidfService;
    }

    public function isCacheValid(int $importId): bool
    {
        try {
            $stmt = $this->databaseConnection->prepare("
                SELECT COUNT(*) as cached_count
                FROM question_validation_cache
                WHERE import_id = :import_id
            ");
            $stmt->execute([':import_id' => $importId]);
            $cachedCount = (int)$stmt->fetchColumn();

            $stmt = $this->databaseConnection->prepare("
                SELECT COUNT(DISTINCT hq.question_id) as total_count
                FROM hier_questions hq
                INNER JOIN ai_syllabus_topics s ON s.syllabus_topic_id = hq.syllabus_topic_id
                INNER JOIN (
                    SELECT DISTINCT question_id
                    FROM raw_exam_results
                    WHERE import_id = :import_id
                ) source_r ON source_r.question_id = hq.question_id
                WHERE hq.syllabus_topic_id IS NOT NULL
            ");
            $stmt->execute([':import_id' => $importId]);
            $totalCount = (int)$stmt->fetchColumn();

            return $cachedCount === $totalCount;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to check cache validity', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public function buildValidationCache(int $importId): bool
    {
        try {
            $this->logger->info('Building validation cache', ['import_id' => $importId]);

            $this->databaseConnection->prepare("DELETE FROM question_validation_cache WHERE import_id = :import_id")
                ->execute([':import_id' => $importId]);

            $validationResults = $this->validationService->getValidationOverview($importId, true);

            if (empty($validationResults)) {
                $this->logger->warning('No validation results to cache', ['import_id' => $importId]);
                return true;
            }

            $stmt = $this->databaseConnection->prepare("
                INSERT INTO question_validation_cache
                (question_id, import_id, syllabus_title, topic_code, keyword_coverage, avg_score, attempts, issues, status)
                VALUES (:question_id, :import_id, :syllabus_title, :topic_code, :keyword_coverage, :avg_score, :attempts, :issues, :status)
            ");

            foreach ($validationResults as $result) {
                $stmt->execute([
                    ':question_id' => $result['question_id'],
                    ':import_id' => $importId,
                    ':syllabus_title' => $result['syllabus_title'],
                    ':topic_code' => $result['topic_code'],
                    ':keyword_coverage' => $result['keyword_coverage'],
                    ':avg_score' => $result['avg_score'],
                    ':attempts' => $result['attempts'],
                    ':issues' => json_encode($result['issues'], JSON_UNESCAPED_UNICODE),
                    ':status' => $result['status'],
                ]);
            }

            $this->logger->info('Validation cache built successfully', [
                'import_id' => $importId,
                'count' => count($validationResults),
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to build validation cache', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public function buildSemanticCache(int $importId, bool $bypassStatGate = false): bool
    {
        try {
            $this->logger->info('Building semantic cache', ['import_id' => $importId, 'bypass_stat_gate' => $bypassStatGate]);

            $this->databaseConnection->prepare("DELETE FROM question_semantic_cache WHERE import_id = :import_id")
                ->execute([':import_id' => $importId]);

            $semanticResults = $this->tfidfService->analyzeQuestionSyllabusAlignment();

            if (empty($semanticResults)) {
                $this->logger->warning('No semantic results to cache', ['import_id' => $importId]);
                return true;
            }

            if (!$bypassStatGate) {
                $flagged = $this->getStatFlaggedQuestionIds($importId);
                if (!empty($flagged)) {
                    $semanticResults = array_values(array_filter(
                        $semanticResults,
                        fn($r) => isset($flagged[(int)$r['question_id']])
                    ));
                    $this->logger->info('Semantic cache gated by stat analysis', [
                        'import_id' => $importId,
                        'flagged_count' => count($flagged),
                        'cache_count' => count($semanticResults),
                    ]);
                }
                if (empty($semanticResults)) {
                    $this->logger->info('Stat gate filtered all questions — no semantic cache needed', ['import_id' => $importId]);
                    return true;
                }
            }

            $stmt = $this->databaseConnection->prepare("
                INSERT INTO question_semantic_cache
                (question_id, import_id, syllabus_topic_id, syllabus_title, discipline_name,
                 text_similarity, semantic_similarity, combined_score, alignment_level,
                 avg_score, student_attempts)
                VALUES (:question_id, :import_id, :syllabus_topic_id, :syllabus_title, :discipline_name,
                        :text_similarity, :semantic_similarity, :combined_score, :alignment_level,
                        :avg_score, :student_attempts)
            ");

            foreach ($semanticResults as $result) {
                $stmt->execute([
                    ':question_id' => $result['question_id'],
                    ':import_id' => $importId,
                    ':syllabus_topic_id' => $result['syllabus_topic_id'],
                    ':syllabus_title' => $result['syllabus_title'],
                    ':discipline_name' => $result['discipline_name'],
                    ':text_similarity' => $result['alignment_analysis']['text_similarity'],
                    ':semantic_similarity' => $result['alignment_analysis']['semantic_similarity'],
                    ':combined_score' => $result['alignment_analysis']['combined_score'],
                    ':alignment_level' => $result['alignment_analysis']['alignment_level'],
                    ':avg_score' => $result['alignment_analysis']['syllabus_context']['average_score'],
                    ':student_attempts' => $result['alignment_analysis']['syllabus_context']['student_attempts'],
                ]);
            }

            $this->logger->info('Semantic cache built successfully', [
                'import_id' => $importId,
                'count' => count($semanticResults),
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to build semantic cache', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    public function getCachedValidation(int $importId): array
    {
        try {
            $stmt = $this->databaseConnection->prepare("
                SELECT question_id, syllabus_title, topic_code, keyword_coverage,
                       avg_score, attempts, issues, status
                FROM question_validation_cache
                WHERE import_id = :import_id
                ORDER BY question_id
            ");
            $stmt->execute([':import_id' => $importId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as &$result) {
                $result['issues'] = json_decode($result['issues'], true) ?? [];
            }

            return $results;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get cached validation', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getCachedSemantic(int $importId): array
    {
        try {
            $stmt = $this->databaseConnection->prepare("
                SELECT question_id, syllabus_topic_id, syllabus_title, discipline_name,
                       text_similarity, semantic_similarity, combined_score, alignment_level,
                       avg_score, student_attempts
                FROM question_semantic_cache
                WHERE import_id = :import_id
                ORDER BY question_id
            ");
            $stmt->execute([':import_id' => $importId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get cached semantic', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    private function getStatFlaggedQuestionIds(int $importId): array
    {
        $cfg = require __DIR__ . '/../config.php';
        $easyT = (float)($cfg['coefficients']['quality']['easy_threshold'] ?? 0.8);
        $hardT = (float)($cfg['coefficients']['quality']['hard_threshold'] ?? 0.3);

        $stmt = $this->databaseConnection->prepare(
            "SELECT r.question_id,
                    AVG(r.received_score) AS mean_s,
                    COALESCE(MAX(hq.max_score), 100) AS max_s,
                    COUNT(DISTINCT r.student_id) AS attempts
             FROM raw_exam_results r
             LEFT JOIN hier_questions hq ON hq.question_id = r.question_id
             WHERE r.import_id = :imp AND r.received_score IS NOT NULL
             GROUP BY r.question_id"
        );
        $stmt->execute([':imp' => $importId]);

        $flagged = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $qid = (int)$row['question_id'];
            $max = (float)$row['max_s'];
            $mean = (float)$row['mean_s'];
            $att  = (int)$row['attempts'];
            $ratio = $max > 0 ? $mean / $max : 0.0;

            if ($ratio >= $easyT || $ratio <= $hardT || $att < 5) {
                $flagged[$qid] = true;
            }
        }
        return $flagged;
    }

    public function invalidateCache(int $importId): bool
    {
        try {
            $this->databaseConnection->prepare("DELETE FROM question_validation_cache WHERE import_id = :import_id")
                ->execute([':import_id' => $importId]);
            $this->databaseConnection->prepare("DELETE FROM question_semantic_cache WHERE import_id = :import_id")
                ->execute([':import_id' => $importId]);

            $this->logger->info('Cache invalidated', ['import_id' => $importId]);
            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to invalidate cache', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return false;
        }
    }
}
