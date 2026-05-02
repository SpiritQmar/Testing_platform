<?php
session_start();
require_once __DIR__ . '/includes/db.php';

echo "<div style='font-family:Arial;padding:30px;line-height:1.6'>";
echo "<h1>Проверка MySQL</h1>";

echo "<h2>PHP</h2>";
echo "<p>PHP version: <b>" . htmlspecialchars(PHP_VERSION) . "</b></p>";
echo "<p>pdo_mysql: <b>" . (extension_loaded('pdo_mysql') ? 'есть' : 'НЕТ') . "</b></p>";

try {
    $pdo = server_pdo();
    echo "<h2>Подключение к серверу MySQL</h2>";
    echo "<p>Успешно: <b>" . htmlspecialchars($_SESSION['mysql_working_label'] ?? 'unknown') . "</b></p>";
    echo "<p>DSN: <code>" . htmlspecialchars($_SESSION['mysql_working_server_dsn'] ?? '') . "</code></p>";

    $db = db();
    echo "<h2>Подключение к базе</h2>";
    echo "<p>Успешно: <code>" . htmlspecialchars($_SESSION['mysql_working_db_dsn'] ?? '') . "</code></p>";

    install_if_needed();
    echo "<h2>Данные</h2>";
    echo "<p>users: " . htmlspecialchars((string)one('SELECT COUNT(*) FROM users')) . "</p>";
    echo "<p>questions: " . htmlspecialchars((string)one('SELECT COUNT(*) FROM questions')) . "</p>";
    echo "<p>raw_exam_results: " . htmlspecialchars((string)one('SELECT COUNT(*) FROM raw_exam_results')) . "</p>";
    echo "<p><a href='index.php'>Открыть сайт</a></p>";
} catch (Throwable $e) {
    echo "<h2>Ошибка</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}
echo "</div>";
