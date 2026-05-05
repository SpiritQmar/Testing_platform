<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/RuleClassifierService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Данный эндпоинт поддерживает только POST-запросы');
}

verify_csrf_or_fail();

$classificationRuleService = new RuleClassifierService($pdo);

try {
    $requestedAction = $_POST['action'] ?? 'create';

    if ($requestedAction === 'create') {
        $ruleDisplayName = trim($_POST['rule_name'] ?? '');
        $evaluationMetric = $_POST['metric_name'] ?? '';
        $comparisonOperator = $_POST['operator'] ?? '=';
        $lowerThresholdValue = (float)($_POST['value_from'] ?? 0);
        $upperThresholdValue = isset($_POST['value_to']) && $_POST['value_to'] !== '' ? (float)$_POST['value_to'] : null;
        $classificationAction = $_POST['action_type'] ?? 'set_category';
        $classificationValue = $_POST['action_value'] ?? '';

        if (empty($ruleDisplayName) || empty($evaluationMetric) || empty($classificationValue)) {
            throw new Exception('Необходимо указать название правила, метрику оценки и значение классификации');
        }

        $newRuleId = $classificationRuleService->createRule($ruleDisplayName);

        $classificationRuleService->addCondition($newRuleId, $evaluationMetric, $comparisonOperator, $lowerThresholdValue, $upperThresholdValue, 0);

        $classificationRuleService->addAction($newRuleId, $classificationAction, $classificationValue);

        flash_set('success', "Правило классификации '{$ruleDisplayName}' успешно создано и добавлено в систему");

    } elseif ($requestedAction === 'delete') {
        $targetRuleId = (int)($_POST['rule_id'] ?? 0);
        if ($targetRuleId > 0) {
            $classificationRuleService->deleteRule($targetRuleId);
            flash_set('success', 'Правило классификации удалено из системы');
        } else {
            throw new Exception('Некорректный идентификатор правила для удаления');
        }
    } elseif ($requestedAction === 'toggle') {
        $targetRuleId = (int)($_POST['rule_id'] ?? 0);
        $newActivationState = (bool)($_POST['is_active'] ?? false);
        if ($targetRuleId > 0) {
            $classificationRuleService->toggleRule($targetRuleId, $newActivationState);
            $statusMessage = $newActivationState ? 'активировано и будет применяться к новым данным' : 'деактивировано и больше не будет использоваться';
            flash_set('success', 'Правило классификации ' . $statusMessage);
        } else {
            throw new Exception('Некорректный идентификатор правила для изменения статуса');
        }
    }

} catch (Throwable $exception) {
    error_log('Ошибка при управлении правилами классификации: ' . $exception->getMessage());
    flash_set('danger', 'Не удалось выполнить операцию с правилом: ' . $exception->getMessage());
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php?section=rules'));
exit;
