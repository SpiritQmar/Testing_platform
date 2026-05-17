<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

try {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
        echo json_encode(['error' => 'CSRF mismatch']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $ruleId = (int)($_POST['rule_id'] ?? 0);
    if ($ruleId <= 0) throw new Exception('rule_id обязателен');

    if ($action === 'delete') {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM rule_actions WHERE rule_id = :rid")->execute([':rid' => $ruleId]);
        $pdo->prepare("DELETE FROM rule_conditions WHERE rule_id = :rid")->execute([':rid' => $ruleId]);
        $pdo->prepare("DELETE FROM rule_classifiers WHERE rule_id = :rid")->execute([':rid' => $ruleId]);
        $pdo->commit();
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle') {
        $stmt = $pdo->prepare("UPDATE rule_classifiers SET is_active = 1 - is_active WHERE rule_id = :rid");
        $stmt->execute([':rid' => $ruleId]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Неизвестное действие');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
