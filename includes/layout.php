<?php
$config = require __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

function install_if_needed(): void {
    static $installed = false;
    if ($installed) return;
    $installed = true;

    $requiredTables = ['users', 'imports_log', 'raw_exam_results', 'evaluation_criteria', 'evaluation_details', 'student_answers', 'work_mapping', 'ai_syllabus_topics', 'hier_questions', 'semantic_analysis'];
    $needsInstall = false;
    foreach ($requiredTables as $table) {
        if (!table_exists($table)) {
            $needsInstall = true;
            break;
        }
    }
    if (!$needsInstall && (int)one("SELECT COUNT(*) FROM users") > 0) return;

    $sql = file_get_contents(__DIR__ . '/../frontend_data/sql/exam_analyzer_full_install.sql');
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\s+IF\s+NOT\s+EXISTS\s+`?exam_analyzer_3`?.*?;\s*/im', '', $sql);
    $sql = preg_replace('/^\s*USE\s+`?exam_analyzer_3`?;\s*/im', '', $sql);

    $queries = preg_split('/;\s*\R/', $sql);
    foreach ($queries as $query) {
        $query = trim($query);
        if ($query !== '') {
            db()->exec($query);
        }
    }
    
    
    try {
        $triggerSql = "
            CREATE TRIGGER IF NOT EXISTS tr_create_work_mapping 
            AFTER INSERT ON raw_exam_results
            FOR EACH ROW
            BEGIN
                INSERT IGNORE INTO work_mapping (work_id, student_id, question_id, discipline_name, course_number, import_id)
                VALUES (CONCAT(NEW.student_id, '_', NEW.question_id), NEW.student_id, NEW.question_id, NEW.discipline_name, NEW.course_number, NEW.import_id);
            END
        ";
        db()->exec($triggerSql);
    } catch (Exception $e) {
        
    }
}

function user() { return $_SESSION['user'] ?? null; }

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function ensure_superadmin_password(): void {
    $hash = password_hash('superadmin123', PASSWORD_DEFAULT);

    $roleId = one("SELECT role_id FROM roles WHERE role_name='superadmin'");
    if (!$roleId) {
        db()->exec("INSERT IGNORE INTO roles(role_id, role_name, role_description) VALUES (1,'superadmin','Полный доступ')");
        $roleId = 1;
    }

    $exists = one("SELECT COUNT(*) FROM users WHERE login='superadmin'");
    if (!$exists) {
        $st = db()->prepare("INSERT INTO users(role_id, login, password_hash, full_name, email, is_active) VALUES(?,?,?,?,?,1)");
        $st->execute([$roleId, 'superadmin', $hash, 'Суперпользователь', 'superadmin@example.local']);
    } else {
        $st = db()->prepare("UPDATE users SET password_hash=?, role_id=?, is_active=1 WHERE login='superadmin'");
        $st->execute([$hash, $roleId]);
    }
}

function login_attempt($login, $pass): bool {
    ensure_superadmin_password();

    $st = db()->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.login=? AND u.is_active=1 LIMIT 1");
    $st->execute([$login]);
    $u = $st->fetch();

    if (!$u) return false;
    if (!password_verify($pass, $u['password_hash'])) return false;

    $_SESSION['user'] = $u;
    return true;
}

function require_login() {
    if (!user()) { header('Location: login.php'); exit; }
}

function tr($key) {
    return $key;
}

function card($title, $value) {
    echo '<div class="card"><h3>' . h($title) . '</h3><div class="stat">' . h($value) . '</div></div>';
}

function lang_url($lang) {
    $params = $_GET;
    $params['lang'] = $lang;
    $query = http_build_query($params);
    return strtok($_SERVER['REQUEST_URI'] ?? '', '?') . ($query !== '' ? '?' . $query : '');
}

function page_header($title, $active) {
    $u = user();
    $currentFile = basename($_SERVER['PHP_SELF'] ?? 'unknown');
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>{$title}</title>";
    echo "<link rel='stylesheet' href='assets/app.css'>";
    echo "<script src='https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'></script>";
    echo "<script defer src='assets/app.js'></script>";
    echo "</head><body>";

    if ($u) {
        echo '<div class="uams-shell">';
        echo '<aside class="uams-sidebar">';
        echo '<div class="uams-brand"><div class="uams-logo">U</div><div><div class="uams-name">UAMS</div><div class="uams-small">Question Analysis</div></div></div>';
        echo '<nav class="uams-nav">';
        echo '<a href="dashboard.php" class="' . ($active === 'dashboard.php' ? 'active' : '') . '"><span class="i grid"></span>Dashboard</a>';
        echo '<a href="questions.php" class="' . ($active === 'questions.php' ? 'active' : '') . '"><span class="i question"></span>Questions</a>';
        echo '<a href="syllabus.php" class="' . ($active === 'syllabus.php' ? 'active' : '') . '"><span class="i book"></span>Syllabus</a>';
        echo '<a href="syllabus_link.php" class="' . ($active === 'syllabus_link.php' ? 'active' : '') . '"><span class="i link"></span>Link Q&A</a>';
        echo '<a href="import.php" class="' . ($active === 'import.php' ? 'active' : '') . '"><span class="i upload"></span>Import</a>';
        echo '<a href="users.php" class="' . ($active === 'users.php' ? 'active' : '') . '"><span class="i settings"></span>Users</a>';
        echo '</nav>';
        echo '<div class="teacher-card"><div class="avatar">' . strtoupper(substr($u['full_name'], 0, 1)) . '</div><div><b>' . h($u['full_name']) . '</b><span>' . h($u['role_name']) . '</span></div></div>';
        echo '</aside>';
        echo '<main class="uams-main"><div class="uams-top"><div class="title-block"><h1>' . h($title) . '</h1></div><div class="uams-actions"><a href="logout.php" class="btn">Logout</a></div></div><div class="uams-content">';
    } else {
        echo '<div class="public">';
    }
}

function page_footer() {
    if (user()) {
        echo '</div></main></div>';
    } else {
        echo '</div>';
    }
    echo "</body></html>";
}
