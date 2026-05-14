<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/SearchService.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['error' => 'POST only']);
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
        echo json_encode(['error' => 'CSRF mismatch']);
        exit;
    }

    $svc = new SearchService($pdo);
    $rebuild = !empty($_POST['rebuild']);
    $r = $svc->rebuildIndex($rebuild);

    echo json_encode(['success' => true] + $r);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
