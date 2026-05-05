<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';

final class StudentAnalysisService
{
    private PDO $databaseConnection;
    private array $analyticsConfig;
    private Logger $logger;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
        $this->analyticsConfig = require __DIR__ . '/../config.php';
        $this->logger = getLogger();
    }

    public function getStudentRiskPatterns(int $importId, int $page = 1, int $perPage = 50, ?string $sortColumn = null, ?string $sortDirection = null): array
    {
        $cacheKey = "student_risk_patterns_{$importId}";
        if (isset($_SESSION[$cacheKey])) {
            $riskAnalysisResults = $_SESSION[$cacheKey];
        } else {
            try {
                $queryStatement = $this->databaseConnection->prepare(
                    "SELECT student_id, AVG(received_score) AS avg_item,
                            MIN(received_score) AS min_item,
                            STDDEV_SAMP(received_score) AS std_item,
                            COUNT(*) AS n_items
                     FROM raw_exam_results
                     WHERE import_id = :imp AND student_id IS NOT NULL AND received_score IS NOT NULL
                     GROUP BY student_id"
                );
                $queryStatement->execute([':imp' => $importId]);

                $studentExamScores = $this->studentExamTotals($importId);
                $scoreMapping = [];
                foreach ($studentExamScores as $studentData) {
                    $scoreMapping[$studentData['student_id']] = $studentData['total'];
                }

                $medianScore = $this->calculateMedian(array_column($studentExamScores, 'total'));
                $systemicAverageThreshold = $this->analyticsConfig['coefficients']['risk']['systemic_avg'];
                $spotMinimumThreshold = $this->analyticsConfig['coefficients']['risk']['spot_min'];

                $riskAnalysisResults = [];
                while ($resultRow = $queryStatement->fetch(PDO::FETCH_ASSOC)) {
                    $studentId = (int)$resultRow['student_id'];
                    $averageItemScore = (float)$resultRow['avg_item'];
                    $minimumItemScore = (float)$resultRow['min_item'];
                    $standardDeviationItem = (float)($resultRow['std_item'] ?? 0);
                    $totalDisciplineScore = $scoreMapping[$studentId] ?? $averageItemScore;
                    $riskPatternType = 'ok';
                    $riskNotes = [];

                    if ($averageItemScore < $systemicAverageThreshold && $totalDisciplineScore < $medianScore) {
                        $riskPatternType = 'systemic';
                        $riskNotes[] = 'Низкие баллы по большинству вопросов - возможны системные проблемы с подготовкой';
                    } elseif ($minimumItemScore < $spotMinimumThreshold && $averageItemScore > max($systemicAverageThreshold + 15, 60)) {
                        $riskPatternType = 'spot';
                        $riskNotes[] = 'Обнаружены значительные провалы по отдельным вопросам';
                    }

                    if ($standardDeviationItem < 4 && (int)$resultRow['n_items'] > 6) {
                        $riskNotes[] = 'Очень малый разброс баллов по вопросам - подозрительный паттерн ответов';
                    }

                    $riskAnalysisResults[] = [
                        'student_id' => $studentId,
                        'avg_item' => round($averageItemScore, 2),
                        'min_item' => round($minimumItemScore, 2),
                        'discipline_total' => round($totalDisciplineScore, 2),
                        'pattern_type' => $riskPatternType,
                        'notes' => $riskNotes,
                    ];
                }

                $_SESSION[$cacheKey] = $riskAnalysisResults;
            } catch (Throwable $exception) {
                $this->logger->error('Failed to get student risk patterns', [
                    'import_id' => $importId,
                    'error' => $exception->getMessage(),
                ]);
                return [];
            }
        }

        if ($sortColumn && $sortDirection) {
            $validColumns = ['student_id', 'avg_item', 'min_item', 'discipline_total', 'pattern_type', 'notes'];
            if (in_array($sortColumn, $validColumns)) {
                $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
                usort($riskAnalysisResults, function($a, $b) use ($sortColumn, $direction) {
                    $valA = $sortColumn === 'notes' ? implode(', ', $a['notes']) : $a[$sortColumn];
                    $valB = $sortColumn === 'notes' ? implode(', ', $b['notes']) : $b[$sortColumn];
                    if ($valA == $valB) return 0;
                    return ($valA < $valB ? -1 : 1) * $direction;
                });
            }
        }

        $offset = ($page - 1) * $perPage;
        return array_slice($riskAnalysisResults, $offset, $perPage);
    }

    public function getStudentRiskPatternsCount(int $importId): int
    {
        try {
            $queryStatement = $this->databaseConnection->prepare(
                "SELECT COUNT(DISTINCT student_id) as total
                 FROM raw_exam_results
                 WHERE import_id = :imp AND student_id IS NOT NULL AND received_score IS NOT NULL"
            );
            $queryStatement->execute([':imp' => $importId]);
            $result = $queryStatement->fetch(PDO::FETCH_ASSOC);
            return (int)($result['total'] ?? 0);
        } catch (Throwable $exception) {
            $this->logger->error('Failed to count student risk patterns', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return 0;
        }
    }

    public function getNationalityPerformanceBreakdown(int $importId): array
    {
        try {
            $sqlQuery = "
                SELECT p.nationality_group, AVG(r.received_score) AS avg_score, COUNT(*) AS n
                FROM raw_exam_results r
                JOIN ai_student_profile p ON p.student_id = r.student_id
                WHERE r.import_id = :imp AND r.received_score IS NOT NULL
                GROUP BY p.nationality_group
            ";
            $queryStatement = $this->databaseConnection->prepare($sqlQuery);
            $queryStatement->execute([':imp' => $importId]);
            return $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get nationality performance breakdown', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getDataIntegrityFlags(int $importId): array
    {
        $integrityWarnings = [];

        try {
            $queryStatement = $this->databaseConnection->prepare(
                "SELECT student_id, COUNT(DISTINCT received_score) AS uvals, COUNT(*) AS n
                 FROM raw_exam_results WHERE import_id = :imp AND received_score IS NOT NULL
                 GROUP BY student_id HAVING n > 5 AND uvals <= 2"
            );
            $queryStatement->execute([':imp' => $importId]);
            while ($resultRow = $queryStatement->fetch(PDO::FETCH_ASSOC)) {
                $integrityWarnings[] = [
                    'type' => 'repeat_pattern',
                    'student_id' => (int)$resultRow['student_id'],
                    'detail' => 'Мало уникальных оценок - подозрительный паттерн ответов'
                ];
            }

            $secondQueryStatement = $this->databaseConnection->prepare(
                "SELECT question_id, COUNT(DISTINCT student_id) AS studs, MIN(received_score) AS mn, MAX(received_score) AS mx
                 FROM raw_exam_results WHERE import_id = :imp AND received_score IS NOT NULL
                 GROUP BY question_id HAVING studs > 10 AND (mx - mn) < 3"
            );
            $secondQueryStatement->execute([':imp' => $importId]);
            while ($resultRow = $secondQueryStatement->fetch(PDO::FETCH_ASSOC)) {
                $integrityWarnings[] = [
                    'type' => 'flat_question',
                    'question_id' => (int)$resultRow['question_id'],
                    'detail' => 'Почти одинаковые ответы у всех студентов - возможная проблема с вопросом'
                ];
            }

            return $integrityWarnings;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get data integrity flags', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    private function studentExamTotals(int $importId): array
    {
        $queryStatement = $this->databaseConnection->prepare(
            "SELECT student_id, SUM(received_score) AS total
             FROM raw_exam_results
             WHERE import_id = :imp AND student_id IS NOT NULL AND received_score IS NOT NULL
             GROUP BY student_id"
        );
        $queryStatement->execute([':imp' => $importId]);
        $studentScoreRows = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $studentScoreData = [];
        foreach ($studentScoreRows as $scoreRow) {
            $studentScoreData[] = [
                'student_id' => (int)$scoreRow['student_id'],
                'total' => (float)$scoreRow['total']
            ];
        }
        return $studentScoreData;
    }

    private function calculateMedian(array $values): float
    {
        if (!$values) return 0.0;
        sort($values);
        $valueCount = count($values);
        $middleIndex = (int)floor(($valueCount - 1) / 2);
        return $valueCount % 2 ? $values[$middleIndex] : ($values[$middleIndex] + $values[$middleIndex + 1]) / 2;
    }
}
