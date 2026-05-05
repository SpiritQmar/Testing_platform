<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (parse_ini_file($envFile) as $k => $v) {
        $_ENV[$k] = $v;
        putenv("$k=$v");
    }
}

return [
  'db_host' => $_ENV['DB_HOST'] ?? 'localhost',
  'db_port' => $_ENV['DB_PORT'] ?? '3307',
  'db_name' => $_ENV['DB_NAME'] ?? 'exam_analyzer_3',
  'db_user' => $_ENV['DB_USER'] ?? 'root',
  'db_pass' => $_ENV['DB_PASS'] ?? '',
  'app_name' => 'UAMS Question Analysis',
  'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
];
