<?php
require_once __DIR__.'/includes/layout.php';
require_login();
page_header('Settings','settings.php');
?>
<h2 class="page-title">Настройки</h2>
<p class="page-sub">Управляйте настройками приложения</p>
<div class="card"><h3>☾ Внешний вид</h3><div class="card-sub">Настройте внешний вид приложения</div><p><b>Темный режим</b><br><span class="hint">Используйте кнопку луны в верхней панели.</span></p></div>
<div class="card"><h3>◉ Язык</h3><div class="card-sub">Выберите предпочтительный язык</div><p><a class="btn ghost small" href="<?=h(lang_url('ru'))?>">Русский</a> <a class="btn ghost small" href="<?=h(lang_url('en'))?>">English</a> <a class="btn ghost small" href="<?=h(lang_url('kk'))?>">Қазақша</a></p></div>
<div class="card"><h3>♧ Уведомления</h3><p><b>Push-уведомления</b> — включено</p><p><b>Email-оповещения</b> — включено</p></div>
<div class="card"><h3>▣ Данные и конфиденциальность</h3><p><b>Автосохранение</b> — включено</p><a class="btn ghost small" href="sql/exam_analyzer_full_install.sql">Экспорт моих данных</a></div>
<?php page_footer(); ?>
