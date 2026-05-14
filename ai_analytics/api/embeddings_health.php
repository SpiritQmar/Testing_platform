<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!function_exists('curl_init')) {
    echo json_encode(['live' => false, 'ready' => false]);
    exit;
}

function quickGet(string $url): int {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

$live = quickGet('http://localhost:8000/live') === 200;

$ready = $live && quickGet('http://localhost:8000/health') === 200;

echo json_encode(['live' => $live, 'ready' => $ready]);
