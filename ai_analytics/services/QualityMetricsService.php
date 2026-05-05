<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/logger.php';

final class QualityMetricsService
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

    public function getQuestionQualityMetrics(int $importId, ?string $sortColumn = null, ?string $sortDirection = null): array
    {
        $orderBy = '';
        if ($sortColumn && $sortDirection) {
            $validColumns = ['question_id', 'n', 'mean_score', 'std_score', 'p_correct_proxy'];
            if (in_array($sortColumn, $validColumns)) {
                $direction = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';
                $orderBy = " ORDER BY $sortColumn $direction";
            }
        }

        $sqlQuery = "
            SELECT r.question_id, COUNT(*) AS n, AVG(r.received_score) AS mean_score,
                   STDDEV_SAMP(r.received_score) AS std_score,
                   SUM(CASE WHEN r.received_score >= 60 THEN 1 ELSE 0 END) / COUNT(*) AS p_correct_proxy
            FROM raw_exam_results r
            WHERE r.import_id = :imp AND r.question_id IS NOT NULL AND r.received_score IS NOT NULL
            GROUP BY r.question_id
            $orderBy
        ";

        try {
            $queryStatement = $this->databaseConnection->prepare($sqlQuery);
            $queryStatement->execute([':imp' => $importId]);
            $questionStatistics = $queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $maximumScoreMapping = $this->getQuestionMaxScores($importId);

            $qualityAnalysisResults = [];
            $easyThreshold = $this->analyticsConfig['coefficients']['quality']['easy_threshold'];
            $hardThreshold = $this->analyticsConfig['coefficients']['quality']['hard_threshold'];

            foreach ($questionStatistics as $statRow) {
                $questionIdentifier = (int)$statRow['question_id'];
                $maxPossibleScore = $maximumScoreMapping[$questionIdentifier] ?? 100.0;
                $meanAchievedScore = (float)$statRow['mean_score'];
                $successRatio = $maxPossibleScore > 0 ? $meanAchievedScore / $maxPossibleScore : 0.0;
                $qualityFlag = 'normal';
                $qualityReason = '';

                if ($successRatio >= $easyThreshold) {
                    $qualityFlag = 'too_easy';
                    $qualityReason = 'Очень высокий средний балл — вопрос почти не дифференцирует студентов по уровню знаний';
                } elseif ($successRatio <= $hardThreshold) {
                    $qualityFlag = 'too_hard';
                    $qualityReason = 'Низкий средний балл — проверьте формулировку вопроса и его соответствие учебной программе';
                }

                $qualityAnalysisResults[] = [
                    'question_id' => $questionIdentifier,
                    'n' => (int)$statRow['n'],
                    'mean_score' => round($meanAchievedScore, 2),
                    'std_score' => round((float)($statRow['std_score'] ?? 0), 2),
                    'p_correct_proxy' => round((float)$statRow['p_correct_proxy'], 3),
                    'difficulty_pct' => round(100 * (1 - $successRatio), 1),
                    'flag' => $qualityFlag,
                    'reason' => $qualityReason,
                ];
            }

            if ($sortColumn && $sortDirection && $sortColumn === 'difficulty_pct') {
                $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
                usort($qualityAnalysisResults, function($a, $b) use ($direction) {
                    $valA = $a['difficulty_pct'];
                    $valB = $b['difficulty_pct'];
                    if ($valA == $valB) return 0;
                    return ($valA < $valB ? -1 : 1) * $direction;
                });
            }

            return $qualityAnalysisResults;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get quality metrics', [
                'import_id' => $importId,
                'error' => $exception->getMessage(),
            ]);
            return [];
        }
    }

    public function getTeacherQualityRankings(int $importId, array $discriminationIndices): array
    {
        $qualityMetrics = $this->getQuestionQualityMetrics($importId);
        $discriminationMapping = [];

        foreach ($discriminationIndices as $discriminationRow) {
            $discriminationMapping[(int)$discriminationRow['question_id']] = $discriminationRow;
        }

        $rankingResults = [];
        foreach ($qualityMetrics as $metricRow) {
            $questionId = (int)$metricRow['question_id'];
            $discriminationScore = $discriminationMapping[$questionId]['discrimination'] ?? 0;
            $qualityScore = (float)$metricRow['p_correct_proxy'] * 40 + min(30, max(0, $discriminationScore)) + (100 - (float)$metricRow['difficulty_pct']) * 0.15;

            if ($metricRow['flag'] === 'too_easy' || $metricRow['flag'] === 'too_hard') {
                $qualityScore -= 15;
            }

            $recommendationText = 'Оставить вопрос без изменений.';
            if ($metricRow['flag'] === 'too_easy') {
                $recommendationText = 'Рассмотреть удаление вопроса или его усложнение';
            }
            if ($metricRow['flag'] === 'too_hard') {
                $recommendationText = 'Упростить формулировку вопроса';
            }
            if (($discriminationMapping[$questionId]['label'] ?? '') === 'weak') {
                $recommendationText .= '. Слабая дискриминативная способность вопроса';
            }

            $rankingResults[] = [
                'question_id' => $questionId,
                'quality_score' => round($qualityScore, 1),
                'recommendation' => $recommendationText
            ];
        }

        usort($rankingResults, static fn ($x, $y) => $y['quality_score'] <=> $x['quality_score']);
        return $rankingResults;
    }

    public function suggestQuestionWeights(int $importId): array
    {
        $qualityMetrics = $this->getQuestionQualityMetrics($importId);
        $weightSuggestions = [];
        $difficultyWeights = [];

        foreach ($qualityMetrics as $metricRow) {
            $difficultyWeights[] = max(0.15, min(1.5, (float)$metricRow['difficulty_pct'] / 100));
        }

        $totalWeight = array_sum($difficultyWeights) ?: 1.0;
        $weightIndex = 0;

        foreach ($qualityMetrics as $metricRow) {
            $calculatedWeight = round(100 * $difficultyWeights[$weightIndex] / $totalWeight, 2);
            $weightSuggestions[] = [
                'question_id' => (int)$metricRow['question_id'],
                'weight_pct' => $calculatedWeight,
                'note' => 'Вес пропорционален сложности вопроса'
            ];
            $weightIndex++;
        }

        return $weightSuggestions;
    }

    private function getQuestionMaxScores(int $importIdentifier): array
    {
        $maximumScoreMapping = [];

        try {
            $queryStatement = $this->databaseConnection->prepare("
                SELECT DISTINCT hq.question_id, COALESCE(NULLIF(hq.max_score, 0), 100) AS max_score
                FROM hier_questions hq
                JOIN raw_exam_results r ON r.question_id = hq.question_id
                WHERE r.import_id = :imp
            ");
            $queryStatement->execute([':imp' => $importIdentifier]);

            foreach ($queryStatement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $maximumScoreMapping[(int)$row['question_id']] = (float)$row['max_score'];
            }
        } catch (Throwable $exception) {
            $this->logger->error('Failed to get question max scores', [
                'import_id' => $importIdentifier,
                'error' => $exception->getMessage(),
            ]);
        }

        return $maximumScoreMapping;
    }
}
