<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

exec('for /f "tokens=5" %a in (\'netstat -ano ^| findstr :8000 ^| findstr LISTENING\') do taskkill /F /PID %a 2>NUL', $out, $code);

echo json_encode(['status' => $code === 0 ? 'stopped' : 'not_running']);
