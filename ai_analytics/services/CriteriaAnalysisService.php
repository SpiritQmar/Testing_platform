<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';

final class CriteriaAnalysisService
{
    private PDO $databaseConnection;
    private Logger $logger;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
        $this->logger = getLogger();
    }

    public function getCriteriaPerformanceAnalysis(int $importId): array
    {
        try {
            $query = "
                SELECT ec.criteria_id, ec.name_russian, ec.name_kazakh, ec.name_english, ec.weight_percent,
                       AVG(ed.score) as avg_score, COUNT(*) as evaluations,
                       MIN(ed.score) as min_score, MAX(ed.score) as max_score,
                       STDDEV_SAMP(ed.score) as std_score
                FROM evaluation_details ed
                JOIN evaluation_criteria ec ON ec.criteria_id = ed.criteria_id
                JOIN work_mapping wm ON wm.work_id = ed.work_id
                WHERE wm.import_id = :imp
                GROUP BY ec.criteria_id
                ORDER BY ec.criteria_id
            ";
            $results = $this->fetchCriteriaPerformanceRows($query, [':imp' => $importId]);

            if (!$results) {
                $results = $this->fetchCriteriaPerformanceRows("
                    SELECT ec.criteria_id, ec.name_russian, ec.name_kazakh, ec.name_english, ec.weight_percent,
                           AVG(ed.score) as avg_score, COUNT(*) as evaluations,
                           MIN(ed.score) as min_score, MAX(ed.score) as max_score,
                           STDDEV_SAMP(ed.score) as std_score
                    FROM evaluation_details ed
                    JOIN evaluation_criteria ec ON ec.criteria_id = ed.criteria_id
                    GROUP BY ec.criteria_id
                    ORDER BY ec.criteria_id
                ");
            }

            $analysis = [];
            foreach ($results as $row) {
                $avgScore = (float)($row['avg_score'] ?? 0);
                $weight = (float)($row['weight_percent'] ?? 20);

                $performanceLevel = 'normal';
                if ($avgScore >= 85) {
                    $performanceLevel = 'excellent';
                } elseif ($avgScore >= 70) {
                    $performanceLevel = 'good';
                } elseif ($avgScore >= 50) {
                    $performanceLevel = 'satisfactory';
                } else {
                    $performanceLevel = 'poor';
                }

                $analysis[] = [
                    'criteria_id' => (int)$row['criteria_id'],
                    'name_russian' => $row['name_russian'],
                    'name_kazakh' => $row['name_kazakh'],
                    'name_english' => $row['name_english'],
                    'weight_percent' => $weight,
                    'avg_score' => round($avgScore, 2),
                    'evaluations' => (int)$row['evaluations'],
                    'min_score' => round((float)($row['min_score'] ?? 0), 2),
                    'max_score' => round((float)($row['max_score'] ?? 0), 2),
                    'std_score' => round((float)($row['std_score'] ?? 0), 2),
                    'performance_level' => $performanceLevel
                ];
            }

            return $analysis;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get criteria performance analysis', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getStudentAnswerAnalysis(int $importId): array
    {
        try {
            $query = "
                SELECT sa.language, COUNT(*) as answers,
                       AVG(sa.final_score) as avg_score,
                       AVG(sa.plagiarism_penalty) as avg_penalty,
                       COUNT(CASE WHEN sa.plagiarism_penalty > 0 THEN 1 END) as plagiarism_count
                FROM student_answers sa
                JOIN work_mapping wm ON wm.work_id = sa.work_id
                WHERE wm.import_id = :imp
                GROUP BY sa.language
                ORDER BY answers DESC
            ";
            $results = $this->fetchStudentAnswerRows($query, [':imp' => $importId]);

            if (!$results) {
                $results = $this->fetchStudentAnswerRows("
                    SELECT sa.language, COUNT(*) as answers,
                           AVG(sa.final_score) as avg_score,
                           AVG(sa.plagiarism_penalty) as avg_penalty,
                           COUNT(CASE WHEN sa.plagiarism_penalty > 0 THEN 1 END) as plagiarism_count
                    FROM student_answers sa
                    GROUP BY sa.language
                    ORDER BY answers DESC
                ");
            }

            $analysis = [];
            foreach ($results as $row) {
                $totalAnswers = (int)$row['answers'];
                $plagiarismRate = $totalAnswers > 0 ? ((int)$row['plagiarism_count'] / $totalAnswers) * 100 : 0;

                $analysis[] = [
                    'language' => $row['language'],
                    'total_answers' => $totalAnswers,
                    'avg_score' => round((float)($row['avg_score'] ?? 0), 2),
                    'avg_penalty' => round((float)($row['avg_penalty'] ?? 0), 2),
                    'plagiarism_count' => (int)$row['plagiarism_count'],
                    'plagiarism_rate' => round($plagiarismRate, 2)
                ];
            }

            return $analysis;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get student answer analysis', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getCriteriaByQuestionAnalysis(int $importId): array
    {
        try {
            $query = "
                SELECT w.question_id, ec.criteria_id, ec.name_russian,
                       AVG(ed.score) as avg_score, COUNT(*) as evaluations
                FROM evaluation_details ed
                JOIN evaluation_criteria ec ON ec.criteria_id = ed.criteria_id
                JOIN work_mapping w ON w.work_id = ed.work_id
                WHERE w.import_id = :imp
                GROUP BY w.question_id, ec.criteria_id
                ORDER BY w.question_id, ec.criteria_id
            ";
            $results = $this->fetchCriteriaByQuestionRows($query, [':imp' => $importId]);

            if (!$results) {
                $results = $this->fetchCriteriaByQuestionRows("
                    SELECT w.question_id, ec.criteria_id, ec.name_russian,
                           AVG(ed.score) as avg_score, COUNT(*) as evaluations
                    FROM evaluation_details ed
                    JOIN evaluation_criteria ec ON ec.criteria_id = ed.criteria_id
                    JOIN work_mapping w ON w.work_id = ed.work_id
                    GROUP BY w.question_id, ec.criteria_id
                    ORDER BY w.question_id, ec.criteria_id
                ");
            }

            $analysis = [];
            foreach ($results as $row) {
                $questionId = (int)$row['question_id'];
                $criteriaId = (int)$row['criteria_id'];

                if (!isset($analysis[$questionId])) {
                    $analysis[$questionId] = [
                        'question_id' => $questionId,
                        'criteria' => []
                    ];
                }

                $analysis[$questionId]['criteria'][] = [
                    'criteria_id' => $criteriaId,
                    'name_russian' => $row['name_russian'],
                    'avg_score' => round((float)($row['avg_score'] ?? 0), 2),
                    'evaluations' => (int)$row['evaluations']
                ];
            }

            return array_values($analysis);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get criteria by question analysis', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    private function fetchCriteriaPerformanceRows(string $query, array $params = []): array
    {
        $statement = $this->databaseConnection->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchStudentAnswerRows(string $query, array $params = []): array
    {
        $statement = $this->databaseConnection->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function fetchCriteriaByQuestionRows(string $query, array $params = []): array
    {
        $statement = $this->databaseConnection->prepare($query);
        $statement->execute($params);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
