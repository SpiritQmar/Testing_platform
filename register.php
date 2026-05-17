<?php
require_once __DIR__ . '/includes/db.php';
$lang = current_lang();
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full = trim($_POST['full_name'] ?? '');
    $login = trim($_POST['login'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';
    $role = trim($_POST['role'] ?? 'teacher');
    if ($full === '' || $login === '' || $pass === '') {
        $error = tr('fill_required');
    } elseif ($pass !== $pass2) {
        $error = tr('password_mismatch');
    } elseif ((int)one('SELECT COUNT(*) FROM users WHERE login = ?', [$login]) > 0) {
        $error = tr('login_exists');
    } else {
        $roleId = one('SELECT role_id FROM roles WHERE role_name = ?', [$role]);
        if (!$roleId) $roleId = one("SELECT role_id FROM roles WHERE role_name = 'teacher'");
        if (!$roleId) $roleId = 3;
        $st = db()->prepare('INSERT INTO users(role_id, login, password_hash, full_name, email, is_active) VALUES (?, ?, ?, ?, ?, 1)');
        $st->execute([(int)$roleId, $login, password_hash($pass, PASSWORD_DEFAULT), $full, $email]);
        $success = tr('success_register');
    }
}
?><!doctype html><html lang="<?=h($lang)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>UAMS — <?=h(tr('register'))?></title><link rel="stylesheet" href="/uams/assets/app.css"></head><body class="auth-page"><form class="auth-card" method="post"><div class="top-actions" style="justify-content:center;margin-bottom:8px"><?php foreach(['ru'=>'RU','kz'=>'KZ','en'=>'EN'] as $k=>$v):?><a class="lang <?=$lang===$k?'active':''?>" href="<?=h(lang_url($k))?>"><?=$v?></a><?php endforeach;?></div><div class="brand-icon" style="margin:0 auto">U</div><h1><?=h(tr('register'))?></h1><?php if($error):?><div class="notice err"><?=h($error)?></div><?php endif;?><?php if($success):?><div class="notice ok"><?=h($success)?></div><div class="auth-links"><a href="login.php?lang=<?=h($lang)?>"><?=h(tr('go_login'))?></a></div><?php else:?><label><?=h(tr('full_name'))?></label><input class="input" name="full_name" required><label><?=h(tr('login_name'))?></label><input class="input" name="login" required><label><?=h(tr('email'))?></label><input class="input" type="email" name="email"><label><?=h(tr('role'))?></label><select name="role"><option value="teacher">teacher</option><option value="admin">admin</option><option value="student">student</option></select><label><?=h(tr('password'))?></label><input class="input" type="password" name="password" required><label><?=h(tr('confirm_password'))?></label><input class="input" type="password" name="password_confirm" required><button class="btn" type="submit"><?=h(tr('create_account'))?></button><div class="auth-links"><a href="login.php?lang=<?=h($lang)?>"><?=h(tr('already_have'))?></a></div><?php endif;?></form></body></html>
