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
            u.user_id,
            u.login,
            u.full_name,
            u.email,
            u.is_active,
            u.created_at,
            r.role_name
        FROM users u
        LEFT JOIN roles r ON r.role_id = u.role_id
        ORDER BY u.created_at DESC
    ");

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $user['last_login'] = null;
    }

    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
} catch (Throwable $e) {
    error_log("Users API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
