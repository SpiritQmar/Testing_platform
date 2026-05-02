<?php
$config = require __DIR__ . '/../config.php';

function cfg($key = null) {
    global $config;
    return $key ? ($config[$key] ?? null) : $config;
}

function mysql_candidate_dsns(bool $withDb = false): array {
    $c = cfg();
    $db = $c['db_name'] ?? 'exam_analyzer_3';

    $items = [
        ['label' => 'localhost без port', 'dsn' => 'mysql:host=localhost;charset=utf8mb4'],
        ['label' => '127.0.0.1 без port', 'dsn' => 'mysql:host=127.0.0.1;charset=utf8mb4'],
        ['label' => 'localhost:3306', 'dsn' => 'mysql:host=localhost;port=3306;charset=utf8mb4'],
        ['label' => '127.0.0.1:3306', 'dsn' => 'mysql:host=127.0.0.1;port=3306;charset=utf8mb4'],
        ['label' => 'localhost:3307', 'dsn' => 'mysql:host=localhost;port=3307;charset=utf8mb4'],
        ['label' => '127.0.0.1:3307', 'dsn' => 'mysql:host=127.0.0.1;port=3307;charset=utf8mb4'],
        ['label' => 'localhost:3308', 'dsn' => 'mysql:host=localhost;port=3308;charset=utf8mb4'],
        ['label' => '127.0.0.1:3308', 'dsn' => 'mysql:host=127.0.0.1;port=3308;charset=utf8mb4'],
    ];

    $host = $c['db_host'] ?? 'localhost';
    $port = $c['db_port'] ?? '';
    $customDsn = 'mysql:host=' . $host . ($port !== '' ? ';port=' . $port : '') . ';charset=utf8mb4';
    array_unshift($items, ['label' => "config {$host}" . ($port !== '' ? ":{$port}" : " без port"), 'dsn' => $customDsn]);

    if (!$withDb) return $items;

    $with = [];
    foreach ($items as $item) {
        $with[] = [
            'label' => $item['label'] . ' + db',
            'dsn' => str_replace(';charset=utf8mb4', ';dbname=' . $db . ';charset=utf8mb4', $item['dsn'])
        ];
    }
    return $with;
}

function make_pdo(string $dsn): PDO {
    $c = cfg();
    return new PDO($dsn, $c['db_user'], $c['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function server_pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (!extension_loaded('pdo_mysql')) {
        die("<div style='font-family:Arial;padding:30px;line-height:1.5'>
            <h2>В PHP не включён pdo_mysql</h2>
            <p>Open Server запустил PHP, но расширение <b>pdo_mysql</b> недоступно.</p>
            <p>Выбери PHP-модуль, где включён MySQL/PDO MySQL.</p>
        </div>");
    }

    $errors = [];
    foreach (mysql_candidate_dsns(false) as $item) {
        try {
            $pdo = make_pdo($item['dsn']);
            $_SESSION['mysql_working_server_dsn'] = $item['dsn'];
            $_SESSION['mysql_working_label'] = $item['label'];
            return $pdo;
        } catch (PDOException $e) {
            $errors[] = $item['label'] . " — " . $e->getMessage();
        }
    }

    $safeErrors = htmlspecialchars(implode("\n", $errors), ENT_QUOTES, 'UTF-8');
    die("<div style='font-family:Arial;padding:30px;line-height:1.5'>
        <h2>MySQL не подключился из проекта</h2>
        <p>phpMyAdmin работает, но PHP-проект не смог подключиться ни через <b>localhost</b>, ни через <b>127.0.0.1</b>, ни через порты 3306/3307/3308.</p>
        <h3>Попытки подключения:</h3>
        <pre style='white-space:pre-wrap;background:#f3f4f6;padding:12px;border-radius:8px'>{$safeErrors}</pre>
        <p>Открой <code>http://site.loc/mysql_test.php</code> и скинь скрин, если ошибка останется.</p>
    </div>");
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $c = cfg();
    $dbName = $c['db_name'] ?? 'exam_analyzer_3';

    $server = server_pdo();
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");

    $errors = [];
    foreach (mysql_candidate_dsns(true) as $item) {
        try {
            $pdo = make_pdo($item['dsn']);
            $_SESSION['mysql_working_db_dsn'] = $item['dsn'];
            return $pdo;
        } catch (PDOException $e) {
            $errors[] = $item['label'] . " — " . $e->getMessage();
        }
    }

    $safeErrors = htmlspecialchars(implode("\n", $errors), ENT_QUOTES, 'UTF-8');
    die("<div style='font-family:Arial;padding:30px;line-height:1.5'>
        <h2>База создана, но подключение к ней не прошло</h2>
        <pre style='white-space:pre-wrap;background:#f3f4f6;padding:12px;border-radius:8px'>{$safeErrors}</pre>
    </div>");
}

function table_exists($name): bool {
    $st = db()->prepare("SHOW TABLES LIKE ?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
}

function install_if_needed(): void {
    if (table_exists('users') && (int)one("SELECT COUNT(*) FROM users") > 0) return;

    $schema = file_get_contents(__DIR__ . '/../schema.sql');
    db()->exec($schema);

    $seed = file_get_contents(__DIR__ . '/../seed.sql');
    $queries = preg_split('/;\s*\n/', $seed);
    foreach ($queries as $sql) {
        $sql = trim($sql);
        if ($sql !== '') {
            db()->exec($sql);
        }
    }
}

function one($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function rows($sql, $params = []) {
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
