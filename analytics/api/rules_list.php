<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

try {
    $rules = $pdo->query(
        "SELECT r.rule_id, r.rule_name, r.rule_order, r.is_active, r.created_at
         FROM rule_classifiers r
         ORDER BY r.rule_order, r.rule_id"
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rules as &$rule) {
        $rid = (int)$rule['rule_id'];

        $cStmt = $pdo->prepare(
            "SELECT condition_id, metric_name, operator, value_from, value_to, condition_order
             FROM rule_conditions WHERE rule_id = :rid ORDER BY condition_order"
        );
        $cStmt->execute([':rid' => $rid]);
        $rule['conditions'] = $cStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $aStmt = $pdo->prepare(
            "SELECT action_id, action_type, action_value, action_message
             FROM rule_actions WHERE rule_id = :rid"
        );
        $aStmt->execute([':rid' => $rid]);
        $rule['actions'] = $aStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    echo json_encode(['success' => true, 'rules' => $rules]);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
