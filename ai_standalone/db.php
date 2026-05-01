<?php

require_once __DIR__ . '/includes/env_loader.php';

$envFilePath = __DIR__ . '/../.env';
if (!file_exists($envFilePath)) {
    $envFilePath = __DIR__ . '/.env';
}
loadEnvironmentVariables($envFilePath);

$config = [];
$configPath = __DIR__ . '/config.php';
$configExamplePath = __DIR__ . '/config.example.php';

if (is_file($configPath)) {
    $loaded = require $configPath;
    if (is_array($loaded)) {
        $config = $loaded;
    }
} elseif (is_file($configExamplePath)) {
    $loaded = require $configExamplePath;
    if (is_array($loaded)) {
        $config = $loaded;
    }
}

$dbConfig = $config['db'] ?? [];
$DB_HOST = $dbConfig['host'] ?? 'localhost';
$DB_PORT = $dbConfig['port'] ?? '3306';
$DB_NAME = $dbConfig['name'] ?? 'exam_analyzer_2';
$DB_USER = $dbConfig['user'] ?? 'root';
$DB_PASS = $dbConfig['pass'] ?? '';
$DB_CHARSET = $dbConfig['charset'] ?? 'utf8mb4';

$pdo = null;
$db_error = null;

try {
    $dsn = "mysql:host={$DB_HOST};port={$DB_PORT};dbname={$DB_NAME};charset={$DB_CHARSET}";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    $db_error = $e->getMessage();
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Check logs or .env configuration. Error: " . htmlspecialchars($e->getMessage()));
}

