<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/RuleClassifierService.php';

final class DiscriminationService
{
    private PDO $databaseConnection;
    private array $analyticsConfig;
    private Logger $logger;
    private RuleClassifierService $ruleClassifier;
    private int $minStudentsForDiscrimination;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
        $this->analyticsConfig = require __DIR__ . '/../config.php';
        $this->logger = getLogger();
        $this->ruleClassifier = new RuleClassifierService($databaseConnection);
        $this->minStudentsForDiscrimination = (int)$this->analyticsConfig['coefficients']['discrimination']['min_students'];
    }

    public function setMinStudentsForDiscrimination(int $value): void
    {
        $this->minStudentsForDiscrimination = max(2, $value);
    }

    public function getDiscriminationIndex(int $importId): array
    {
        $cacheKey = "discrimination_index_{$importId}";
        if (isset($_SESSION[$cacheKey])) {
            return $_SESSION[$cacheKey];
        }

        try {
            $questionsQuery = $this->databaseConnection->prepare("
                SELECT DISTINCT question_id
                FROM raw_exam_results
                WHERE import_id = :import_id AND question_id IS NOT NULL
            ");
            $questionsQuery->execute([':import_id' => $importId]);
            $questionList = $questionsQuery->fetchAll(PDO::FETCH_COLUMN);

            $discriminationResults = [];
            foreach ($questionList as $questionId) {
                $qualityMetrics = $this->ruleClassifier->calculateQuestionQualityMetrics((int)$questionId);

                if (($qualityMetrics['total_attempts'] ?? 0) < $this->minStudentsForDiscrimination) {
                    continue;
                }

                $correlationValue = $qualityMetrics['discrimination_index'];
                $discriminationLabel = 'weak';

                $strongThreshold = $this->analyticsConfig['coefficients']['quality']['discrimination_strong'];
                if ($correlationValue >= $strongThreshold) {
                    $discriminationLabel = 'strong';
                } elseif ($correlationValue >= 0.2) {
                    $discriminationLabel = 'medium';
                }

                $discriminationResults[] = [
                    'question_id' => (int)$questionId,
                    'discrimination' => $correlationValue,
                    'label' => $discriminationLabel,
                ];
            }

            $_SESSION[$cacheKey] = $discriminationResults;
            return $discriminationResults;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to calculate discrimination index', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getItemTotalCorrelation(int $importId, ?string $sortColumn = null, ?string $sortDirection = null): array
    {
        try {
            $studentExamScores = $this->studentExamTotals($importId);
            $scoreMapping = [];
            foreach ($studentExamScores as $studentData) {
                $scoreMapping[$studentData['student_id']] = $studentData['total'];
            }

            $queryStatement = $this->databaseConnection->prepare(
                "SELECT question_id, student_id, received_score
                 FROM raw_exam_results
                 WHERE import_id = :imp AND question_id IS NOT NULL AND student_id IS NOT NULL AND received_score IS NOT NULL"
            );
            $queryStatement->execute([':imp' => $importId]);

            $questionScorePairs = [];
            while ($scoreRow = $queryStatement->fetch(PDO::FETCH_ASSOC)) {
                $questionId = (int)$scoreRow['question_id'];
                $studentId = (int)$scoreRow['student_id'];
                if (!isset($scoreMapping[$studentId])) continue;
                $questionScorePairs[$questionId][] = [
                    'x' => (float)$scoreRow['received_score'],
                    'y' => $scoreMapping[$studentId]
                ];
            }

            $correlationResults = [];
            foreach ($questionScorePairs as $questionId => $scorePairs) {
                $adjustedPairs = [];
                foreach ($scorePairs as $scorePair) {
                    $adjustedPairs[] = [
                        'x' => $scorePair['x'],
                        'y' => $scorePair['y'] - $scorePair['x'],
                    ];
                }

                $correlationValue = round($this->calculatePearsonCorrelation($adjustedPairs), 4);
                $correlationResults[] = [
                    'question_id' => $questionId,
                    'r' => $correlationValue,
                    'flag' => abs($correlationValue) < 0.12 ? 'low_corr' : 'ok',
                ];
            }

            if ($sortColumn && $sortDirection) {
                $validColumns = ['question_id', 'r', 'flag'];
                if (in_array($sortColumn, $validColumns)) {
                    $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
                    usort($correlationResults, function($a, $b) use ($sortColumn, $direction) {
                        $valA = $a[$sortColumn];
                        $valB = $b[$sortColumn];
                        if ($valA == $valB) return 0;
                        return ($valA < $valB ? -1 : 1) * $direction;
                    });
                }
            }

            return $correlationResults;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to calculate item-total correlation', [
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

    private function calculatePearsonCorrelation(array $pairs): float
    {
        $pairCount = count($pairs);
        if ($pairCount < 4) return 0.0;

        $meanX = $meanY = 0.0;
        foreach ($pairs as $pair) {
            $meanX += $pair['x'];
            $meanY += $pair['y'];
        }
        $meanX /= $pairCount;
        $meanY /= $pairCount;

        $numerator = $deviationX = $deviationY = 0.0;
        foreach ($pairs as $pair) {
            $varianceX = $pair['x'] - $meanX;
            $varianceY = $pair['y'] - $meanY;
            $numerator += $varianceX * $varianceY;
            $deviationX += $varianceX * $varianceX;
            $deviationY += $varianceY * $varianceY;
        }

        $denominator = sqrt($deviationX) * sqrt($deviationY);
        return $denominator > 1e-9 ? $numerator / $denominator : 0.0;
    }
}
