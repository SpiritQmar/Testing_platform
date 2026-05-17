<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $parsed = parse_ini_file($envFile);
    if (is_array($parsed)) {
        foreach ($parsed as $k => $v) {
            $_ENV[$k] = $v;
            putenv($k . '=' . $v);
        }
    }
}
return [
    'db_host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'db_port' => $_ENV['DB_PORT'] ?? '3306',
    'db_name' => $_ENV['DB_NAME'] ?? 'exam_analyzer_3',
    'db_user' => $_ENV['DB_USER'] ?? 'root',
    'db_pass' => $_ENV['DB_PASS'] ?? '',
    'app_name' => 'UAMS Question Analysis',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
];
