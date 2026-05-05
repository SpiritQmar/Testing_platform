<?php

declare(strict_types=1);

final class RuleClassifierService
{
    private PDO $databaseConnection;

    public function __construct(PDO $databaseConnection)
    {
        $this->databaseConnection = $databaseConnection;
    }

    public function getActiveClassificationRules(): array
    {
        $queryStatement = $this->databaseConnection->query("
            SELECT r.*, GROUP_CONCAT(c.condition_id ORDER BY c.condition_order) as condition_ids
            FROM rule_classifiers r
            LEFT JOIN rule_conditions c ON c.rule_id = r.rule_id
            WHERE r.is_active = 1 GROUP BY r.rule_id ORDER BY r.rule_order
        ");
        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function applyClassificationRulesToQuestion(int $questionId, array $qualityMetrics): array
    {
        $classificationResult = ['categories' => [], 'flags' => [], 'messages' => []];
        $activeRules = $this->getActiveClassificationRules();

        foreach ($activeRules as $classificationRule) {
            if ($this->evaluateRuleConditions($classificationRule, $qualityMetrics)) {
                $ruleActions = $this->getRuleActions((int)$classificationRule['rule_id']);
                foreach ($ruleActions as $action) {
                    if ($action['action_type'] === 'set_category') {
                        $classificationResult['categories'][] = $action['action_value'];
                    } elseif ($action['action_type'] === 'set_flag') {
                        $classificationResult['flags'][] = $action['action_value'];
                    }
                    if (!empty($action['action_message'])) {
                        $classificationResult['messages'][] = $action['action_message'];
                    }
                }
            }
        }
        return $classificationResult;
    }

    private function evaluateRuleConditions(array $classificationRule, array $qualityMetrics): bool
    {
        $ruleConditions = $this->getRuleConditions((int)$classificationRule['rule_id']);
        if (empty($ruleConditions)) return false;

        foreach ($ruleConditions as $condition) {
            $metricIdentifier = $condition['metric_name'];
            $comparisonOperator = $condition['operator'];
            $lowerBoundValue = (float)$condition['value_from'];
            $upperBoundValue = $condition['value_to'] !== null ? (float)$condition['value_to'] : null;
            $currentMetricValue = $qualityMetrics[$metricIdentifier] ?? null;

            if ($currentMetricValue === null) return false;
            if (!$this->evaluateMetricCondition($currentMetricValue, $comparisonOperator, $lowerBoundValue, $upperBoundValue)) return false;
        }
        return true;
    }

    private function evaluateMetricCondition(float $metricValue, string $comparisonOperator, float $lowerBoundValue, ?float $upperBoundValue): bool
    {
        return match ($comparisonOperator) {
            '=' => abs($metricValue - $lowerBoundValue) < 0.0001,
            '>' => $metricValue > $lowerBoundValue,
            '<' => $metricValue < $lowerBoundValue,
            '>=' => $metricValue >= $lowerBoundValue,
            '<=' => $metricValue <= $lowerBoundValue,
            'between' => $upperBoundValue !== null && $metricValue >= $lowerBoundValue && $metricValue <= $upperBoundValue,
            default => false,
        };
    }

    private function getRuleConditions(int $ruleId): array
    {
        $queryStatement = $this->databaseConnection->prepare("SELECT * FROM rule_conditions WHERE rule_id = :id ORDER BY condition_order");
        $queryStatement->execute([':id' => $ruleId]);
        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRuleActions(int $ruleId): array
    {
        $queryStatement = $this->databaseConnection->prepare("SELECT * FROM rule_actions WHERE rule_id = :id");
        $queryStatement->execute([':id' => $ruleId]);
        return $queryStatement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function calculateQuestionQualityMetrics(int $questionId): array
    {
        $maxScoreQuery = $this->databaseConnection->prepare("
            SELECT max_score FROM ai_question_ai WHERE question_id = :id LIMIT 1
        ");
        $maxScoreQuery->execute([':id' => $questionId]);
        $maxScoreRow = $maxScoreQuery->fetch(PDO::FETCH_ASSOC);
        $maximumPossibleScore = (float)($maxScoreRow['max_score'] ?? 100);

        $queryStatement = $this->databaseConnection->prepare("
            SELECT COUNT(*) as total_attempts, AVG(received_score) as avg_score
            FROM raw_exam_results
            WHERE question_id = :id
        ");
        $queryStatement->execute([':id' => $questionId]);
        $questionStatistics = $queryStatement->fetch(PDO::FETCH_ASSOC);

        if (!$questionStatistics || $questionStatistics['total_attempts'] == 0) {
            return ['avg_score' => 0, 'difficulty_index' => 0, 'discrimination_index' => 0, 'reliability' => 0, 'total_attempts' => 0];
        }

        $averageStudentScore = (float)$questionStatistics['avg_score'];
        $questionDifficultyIndex = 1 - ($averageStudentScore / $maximumPossibleScore);

        $discriminationIndex = $this->calculateDiscriminationPower($questionId);

        return [
            'avg_score' => $averageStudentScore,
            'difficulty_index' => $questionDifficultyIndex,
            'discrimination_index' => $discriminationIndex,
            'reliability' => 1,
            'total_attempts' => (int)$questionStatistics['total_attempts'],
        ];
    }

    private function calculateDiscriminationPower(int $questionId): float
    {
        $questionScoresQuery = $this->databaseConnection->prepare("
            SELECT student_id, received_score
            FROM raw_exam_results
            WHERE question_id = :question_id
        ");
        $questionScoresQuery->execute([':question_id' => $questionId]);
        $questionData = $questionScoresQuery->fetchAll(PDO::FETCH_ASSOC);

        if (count($questionData) < 2) {
            return 0;
        }

        $studentIds = array_column($questionData, 'student_id');
        $studentIdsString = implode(',', array_map('intval', $studentIds));

        $totalScoresQuery = $this->databaseConnection->query("
            SELECT student_id, AVG(discipline_score) as total_score
            FROM raw_exam_results
            WHERE student_id IN ($studentIdsString)
            GROUP BY student_id
        ");
        $totalScoresData = $totalScoresQuery->fetchAll(PDO::FETCH_ASSOC);

        $totalScoresMap = [];
        foreach ($totalScoresData as $row) {
            $totalScoresMap[$row['student_id']] = (float)$row['total_score'];
        }

        $pairedScores = [];
        foreach ($questionData as $qRow) {
            $studentId = $qRow['student_id'];
            if (isset($totalScoresMap[$studentId])) {
                $pairedScores[] = [
                    'question' => (float)$qRow['received_score'],
                    'total' => $totalScoresMap[$studentId]
                ];
            }
        }

        if (count($pairedScores) < 2) {
            return 0;
        }

        $questionScores = array_column($pairedScores, 'question');
        $totalScores = array_column($pairedScores, 'total');

        return $this->calculatePearsonCorrelation($questionScores, $totalScores);
    }

    private function calculatePearsonCorrelation(array $firstDataset, array $secondDataset): float
    {
        $dataCount = count($firstDataset);

        if ($dataCount !== count($secondDataset) || $dataCount < 2) {
            return 0;
        }

        $firstMean = array_sum($firstDataset) / $dataCount;
        $secondMean = array_sum($secondDataset) / $dataCount;

        $numeratorSum = 0;
        $firstDenominatorSum = 0;
        $secondDenominatorSum = 0;

        for ($index = 0; $index < $dataCount; $index++) {
            $firstDeviation = $firstDataset[$index] - $firstMean;
            $secondDeviation = $secondDataset[$index] - $secondMean;

            $numeratorSum += $firstDeviation * $secondDeviation;
            $firstDenominatorSum += $firstDeviation * $firstDeviation;
            $secondDenominatorSum += $secondDeviation * $secondDeviation;
        }

        $denominatorProduct = sqrt($firstDenominatorSum * $secondDenominatorSum);

        if ($denominatorProduct == 0) {
            return 0;
        }

        return round($numeratorSum / $denominatorProduct, 3);
    }

    public function calculateExamReliability(int $examId): array
    {
        $questionsQuery = $this->databaseConnection->prepare("
            SELECT DISTINCT question_id
            FROM raw_exam_results
            WHERE exam_id = :exam_id
        ");
        $questionsQuery->execute([':exam_id' => $examId]);
        $examQuestions = $questionsQuery->fetchAll(PDO::FETCH_COLUMN);

        $questionCount = count($examQuestions);
        if ($questionCount < 2) {
            return ['cronbach_alpha' => 0, 'avg_inter_item_correlation' => 0, 'question_count' => $questionCount];
        }

        $correlationMatrix = $this->buildInterItemCorrelationMatrix($examId, $examQuestions);
        $avgCorrelation = $this->calculateAverageInterItemCorrelation($correlationMatrix);
        $cronbachAlpha = $this->calculateCronbachAlpha($correlationMatrix, $questionCount);

        return [
            'cronbach_alpha' => round($cronbachAlpha, 3),
            'avg_inter_item_correlation' => round($avgCorrelation, 3),
            'question_count' => $questionCount,
            'correlation_matrix' => $correlationMatrix
        ];
    }

    private function buildInterItemCorrelationMatrix(int $examId, array $questionList): array
    {
        $studentAnswersQuery = $this->databaseConnection->prepare("
            SELECT student_id, question_id, received_score
            FROM raw_exam_results
            WHERE exam_id = :exam_id AND received_score IS NOT NULL
            ORDER BY student_id, question_id
        ");
        $studentAnswersQuery->execute([':exam_id' => $examId]);
        $allAnswers = $studentAnswersQuery->fetchAll(PDO::FETCH_ASSOC);

        $scoresByStudent = [];
        foreach ($allAnswers as $answerRow) {
            $studentKey = $answerRow['student_id'];
            $questionKey = $answerRow['question_id'];
            if (!isset($scoresByStudent[$studentKey])) {
                $scoresByStudent[$studentKey] = [];
            }
            $scoresByStudent[$studentKey][$questionKey] = (float)$answerRow['received_score'];
        }

        $commonStudents = [];
        foreach ($scoresByStudent as $studentKey => $studentScores) {
            if (count(array_intersect_key($studentScores, array_flip($questionList))) === count($questionList)) {
                $commonStudents[$studentKey] = $studentScores;
            }
        }

        if (count($commonStudents) < 10) {
            return [];
        }

        $correlationMatrix = [];
        foreach ($questionList as $firstQuestion) {
            $correlationMatrix[$firstQuestion] = [];
            foreach ($questionList as $secondQuestion) {
                if ($firstQuestion === $secondQuestion) {
                    $correlationMatrix[$firstQuestion][$secondQuestion] = 1.0;
                } else {
                    $firstScores = array_column($commonStudents, $firstQuestion);
                    $secondScores = array_column($commonStudents, $secondQuestion);
                    $correlationMatrix[$firstQuestion][$secondQuestion] = $this->calculatePearsonCorrelation($firstScores, $secondScores);
                }
            }
        }

        return $correlationMatrix;
    }

    private function calculateAverageInterItemCorrelation(array $correlationMatrix): float
    {
        if (empty($correlationMatrix)) {
            return 0;
        }

        $correlationSum = 0;
        $pairCount = 0;
        $questionIds = array_keys($correlationMatrix);

        for ($i = 0; $i < count($questionIds); $i++) {
            for ($j = $i + 1; $j < count($questionIds); $j++) {
                $firstQuestion = $questionIds[$i];
                $secondQuestion = $questionIds[$j];
                $correlationSum += $correlationMatrix[$firstQuestion][$secondQuestion];
                $pairCount++;
            }
        }

        return $pairCount > 0 ? $correlationSum / $pairCount : 0;
    }

    private function calculateCronbachAlpha(array $correlationMatrix, int $itemCount): float
    {
        if (empty($correlationMatrix) || $itemCount < 2) {
            return 0;
        }

        $avgCorrelation = $this->calculateAverageInterItemCorrelation($correlationMatrix);

        $cronbachAlpha = ($itemCount * $avgCorrelation) / (1 + ($itemCount - 1) * $avgCorrelation);

        return max(0, min(1, $cronbachAlpha));
    }
}
