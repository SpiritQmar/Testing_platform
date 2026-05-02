<?php
require_once __DIR__ . '/includes/auth.php';
ensure_superadmin_password();
session_destroy();
echo "<div style='font-family:Arial;padding:30px'>
<h2>Пароль superadmin сброшен</h2>
<p>Теперь вход:</p>
<p><b>Логин:</b> superadmin</p>
<p><b>Пароль:</b> superadmin123</p>
<p><a href='login.php'>Перейти ко входу</a></p>
</div>";
