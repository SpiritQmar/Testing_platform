<?php
require_once __DIR__ . '/db.php';
session_start();
install_if_needed();

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
