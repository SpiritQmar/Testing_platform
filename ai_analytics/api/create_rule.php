<?php
error_reporting(0);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['error' => 'POST only']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
    ob_end_clean();
    echo json_encode(['error' => 'CSRF mismatch']);
    exit;
}

require_once __DIR__ . '/../db.php';

try {
    $ruleName = trim($_POST['rule_name'] ?? '');
    $metricName = $_POST['metric_name'] ?? '';
    $operator = $_POST['operator'] ?? '';
    $valueFrom = $_POST['value_from'] ?? '';
    $valueTo = $_POST['value_to'] ?? null;
    $category = $_POST['category'] ?? '';
    $actionMessage = trim($_POST['action_message'] ?? '');
    $ruleOrder = max(1, (int)($_POST['rule_order'] ?? 1));

    $allowed_metrics = ['difficulty_index', 'discrimination_index', 'avg_score', 'total_attempts'];
    $allowed_operators = ['>', '<', '>=', '<=', 'between'];
    $allowed_categories = ['too_difficult', 'too_easy', 'poor_discrimination', 'insufficient_attempts'];

    if (!$ruleName) throw new Exception('Название правила обязательно');
    if (!in_array($metricName, $allowed_metrics)) throw new Exception('Неверная метрика');
    if (!in_array($operator, $allowed_operators)) throw new Exception('Неверный оператор');
    if ($valueFrom === '' || !is_numeric($valueFrom)) throw new Exception('Значение от обязательно');
    if (!in_array($category, $allowed_categories)) throw new Exception('Неверная категория');

    $valueTo = ($operator === 'between' && $valueTo !== '' && is_numeric($valueTo)) ? (float)$valueTo : null;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO rule_classifiers (rule_name, rule_order, is_active) VALUES (:name, :order, 1)");
    $stmt->execute([':name' => $ruleName, ':order' => $ruleOrder]);
    $ruleId = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO rule_conditions (rule_id, metric_name, operator, value_from, value_to, condition_order) VALUES (:rid, :metric, :op, :vfrom, :vto, 1)");
    $stmt->execute([':rid' => $ruleId, ':metric' => $metricName, ':op' => $operator, ':vfrom' => (float)$valueFrom, ':vto' => $valueTo]);

    $stmt = $pdo->prepare("INSERT INTO rule_actions (rule_id, action_type, action_value, action_message) VALUES (:rid, 'set_category', :cat, :msg)");
    $stmt->execute([':rid' => $ruleId, ':cat' => $category, ':msg' => $actionMessage]);

    $pdo->commit();

    ob_end_clean();
    echo json_encode(['success' => true, 'rule_id' => $ruleId, 'rule_name' => $ruleName]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
