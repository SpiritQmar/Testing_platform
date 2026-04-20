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
        $queryStatement = $this->databaseConnection->prepare("
            SELECT q.max_score, COUNT(a.answer_id) as total_attempts, AVG(a.score_received) as avg_score
            FROM hier_questions q
            LEFT JOIN hier_student_answers a ON a.question_id = q.question_id
            WHERE q.question_id = :id GROUP BY q.question_id, q.max_score
        ");
        $queryStatement->execute([':id' => $questionId]);
        $questionStatistics = $queryStatement->fetch(PDO::FETCH_ASSOC);

        if (!$questionStatistics || $questionStatistics['total_attempts'] == 0) {
            return ['avg_score' => 0, 'difficulty_index' => 0, 'discrimination_index' => 0, 'reliability' => 0];
        }

        $maximumPossibleScore = (float)$questionStatistics['max_score'];
        $averageStudentScore = (float)$questionStatistics['avg_score'];
        $questionDifficultyIndex = 1 - ($averageStudentScore / $maximumPossibleScore);

        return [
            'avg_score' => $averageStudentScore,
            'difficulty_index' => $questionDifficultyIndex,
            'discrimination_index' => 0,
            'reliability' => 1,
            'total_attempts' => (int)$questionStatistics['total_attempts'],
        ];
    }
}
