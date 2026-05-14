<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

function httpCode(string $url, int $timeout = 1): int {
    if (!function_exists('curl_init')) return 0;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

if (httpCode('http://localhost:8000/live') === 200) {
    $ready = httpCode('http://localhost:8000/health') === 200;
    echo json_encode(['status' => 'already_running', 'model_ready' => $ready]);
    exit;
}

$python = 'C:\\Python313\\python.exe';
$script = 'C:\\xampp\\htdocs\\uams\\ai_analytics\\python_services\\embeddings_api.py';
$dir    = 'C:\\xampp\\htdocs\\uams\\ai_analytics\\python_services';
$log    = $dir . '\\startup.log';

$cmd = sprintf('cmd /c start /b "" "%s" "%s" > "%s" 2>&1', $python, $script, $log);
$pipes = [];
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
if (is_resource($proc)) {
    foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
    proc_close($proc);
}

echo json_encode(['status' => 'starting', 'log' => $log]);
