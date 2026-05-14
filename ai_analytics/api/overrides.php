<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/coefficients.php';

try {
    $resolver = new CoefficientResolver($pdo);
    $action = $_REQUEST['action'] ?? 'list';

    if ($action === 'list') {
        echo json_encode(['success' => true, 'overrides' => $resolver->listOverrides()]);
        exit;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
        echo json_encode(['error' => 'CSRF mismatch']);
        exit;
    }

    if ($action === 'set') {
        $scopeType  = $_POST['scope_type']  ?? '';
        $scopeValue = trim((string)($_POST['scope_value'] ?? ''));
        $category   = $_POST['category']    ?? '';
        $key        = $_POST['coef_key']    ?? '';
        $value      = (float)($_POST['coef_value'] ?? 0);

        if (!in_array($scopeType, ['discipline', 'specialty'], true)) throw new Exception('Неверный scope_type');
        if ($scopeValue === '') throw new Exception('scope_value пуст');
        if ($category === '' || $key === '') throw new Exception('category/key обязательны');

        $resolver->setOverride($scopeType, $scopeValue, $category, $key, $value);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['override_id'] ?? 0);
        if ($id <= 0) throw new Exception('override_id обязателен');
        $resolver->deleteOverride($id);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Неизвестное действие');
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
