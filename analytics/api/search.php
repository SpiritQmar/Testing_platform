<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../services/SearchService.php';

try {
    $svc = new SearchService($pdo);
    $query = (string)($_GET['q'] ?? '');
    $topK  = max(1, min(30, (int)($_GET['top_k'] ?? 10)));
    $type  = $_GET['type'] ?? 'all';

    $r = $svc->search($query, $topK, $type);

    echo json_encode([
        'success'    => true,
        'mode'       => $r['mode'],
        'query'      => trim($query),
        'results'    => $r['results'],
        'index_size' => $svc->getIndexSize(),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
