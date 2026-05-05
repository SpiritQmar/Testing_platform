<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {
    require_login();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $stmt = $pdo->query("
        SELECT
            al.log_id,
            al.user_id,
            al.action_type,
            al.action_details,
            al.ip_address,
            al.created_at,
            u.full_name,
            u.login
        FROM audit_logs al
        LEFT JOIN users u ON u.user_id = al.user_id
        ORDER BY al.created_at DESC
        LIMIT 100
    ");

    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalStmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs");
    $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $loginsStmt = $pdo->query("SELECT COUNT(*) as logins FROM audit_logs WHERE action_type = 'Login'");
    $logins = $loginsStmt->fetch(PDO::FETCH_ASSOC)['logins'];

    $updatesStmt = $pdo->query("SELECT COUNT(*) as updates FROM audit_logs WHERE action_type = 'Update'");
    $updates = $updatesStmt->fetch(PDO::FETCH_ASSOC)['updates'];

    $todayStmt = $pdo->query("SELECT COUNT(*) as today FROM audit_logs WHERE DATE(created_at) = CURDATE()");
    $today = $todayStmt->fetch(PDO::FETCH_ASSOC)['today'];

    echo json_encode([
        'success' => true,
        'logs' => $logs,
        'stats' => [
            'total' => $total,
            'logins' => $logins,
            'updates' => $updates,
            'today' => $today
        ]
    ]);
} catch (Throwable $e) {
    error_log("Audit API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
