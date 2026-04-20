<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    header('Location: index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT u.user_id,u.login,u.password_hash,u.full_name,u.is_active,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id WHERE u.login=:login LIMIT 1");
    $stmt->execute([':login' => $login]);
    $user = $stmt->fetch();

    if ($user && (int)$user['is_active'] === 1) {
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        flash_set('success', 'Вход выполнен.');
        header('Location: index.php');
        exit;
    }
    $error = 'Неверный логин или пароль.';
}

$lang = get_lang();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title>Вход - <?= h(t('app_name')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/vendor/css/bootstrap-lite.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5;min-height:100vh;display:flex;align-items:center}.login-card{max-width:400px;margin:0 auto;border:none;box-shadow:0 4px 12px rgba(0,0,0,0.1)}</style>
</head>
<body>
<div class="container py-5">
  <div class="login-card card">
    <div class="card-body p-4">
      <h1 class="h4 mb-3 text-center">Вход в систему</h1>
      <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
      <form method="post">
        <?= csrf_input() ?>
        <div class="mb-3">
          <label class="form-label">Логин</label>
          <input class="form-control" name="login" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Пароль</label>
          <input class="form-control" type="password" name="password" required>
        </div>
        <button class="btn btn-primary w-100">Войти</button>
      </form>
      <hr>
      <div class="small text-secondary">
        <b>Логин:</b> superadmin<br>
        <b>Пароль:</b> superadmin123
      </div>
    </div>
  </div>
</div>
</body>
</html>
