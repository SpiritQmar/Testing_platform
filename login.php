<?php
require_once __DIR__.'/includes/layout.php';
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (login_attempt($_POST['login'] ?? '', $_POST['password'] ?? '')) { header('Location: dashboard.php'); exit; }
    $error='Неверный логин или пароль';
}
page_header(tr('login.title'),'');
?>
<div class="loginbox">
  <h1><?=h(tr('login.title'))?></h1>
  <p>UAMS Question Analysis</p>
  <?php if($error): ?><div class="err"><?=h($error)?></div><?php endif; ?>
  <form method="post">
    <label>Login</label><input name="login" value="superadmin">
    <label>Password</label><input name="password" type="password" value="superadmin123">
    <button class="btn primary wide">Sign in</button>
  </form>
  <div class="hint">superadmin / superadmin123</div>
</div>
<?php page_footer(); ?>
