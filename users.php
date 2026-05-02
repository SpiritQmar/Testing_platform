<?php
require_once __DIR__.'/includes/layout.php';
require_login();
if($_SERVER['REQUEST_METHOD']==='POST'){
  $hash=password_hash($_POST['password']??'123456', PASSWORD_DEFAULT);
  $st=db()->prepare("INSERT IGNORE INTO users(role_id,login,password_hash,full_name,email,is_active) VALUES(?,?,?,?,?,1)");
  $st->execute([(int)$_POST['role_id'],$_POST['login'],$hash,$_POST['full_name'],$_POST['email']??'']);
}
$users=rows("SELECT u.*,r.role_name FROM users u JOIN roles r ON r.role_id=u.role_id ORDER BY user_id");
$roles=rows("SELECT * FROM roles ORDER BY role_id");
page_header('Пользователи','users.php');
?>
<div class="grid2">
<div class="card"><h3>Новый пользователь</h3><form method="post"><label>Логин</label><input name="login"><label>ФИО</label><input name="full_name"><label>Email</label><input name="email"><label>Пароль</label><input name="password" value="123456"><label>Роль</label><select name="role_id"><?php foreach($roles as $r): ?><option value="<?=$r['role_id']?>"><?=h($r['role_name'])?></option><?php endforeach; ?></select><button class="btn primary wide">Создать</button></form></div>
<div class="card"><h3>Список пользователей</h3><table><thead><tr><th>ID</th><th>Логин</th><th>ФИО</th><th>Роль</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><?=h($u['user_id'])?></td><td><?=h($u['login'])?></td><td><?=h($u['full_name'])?></td><td><?=h($u['role_name'])?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php page_footer(); ?>
