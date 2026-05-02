<?php
require_once __DIR__.'/includes/layout.php';
if (user()) { header('Location: dashboard.php'); exit; }
page_header('Главная','');
?>
<div class="landing">
  <div class="hero">
    <h1>Exam Quality Analyzer</h1>
    <p>Единый дипломный проект: база данных, пользовательский интерфейс и интеграция интеллектуальной аналитики для автоматического анализа качества и валидности экзаменационных вопросов.</p>
    <div class="grid2">
      <div class="info"><b>Твоя часть</b><br>Импорт выгрузок, база MySQL, dashboard, вопросы, пользователи.</div>
      <div class="info"><b>AI-модуль</b><br>Силлабус, семантическая проверка, правила классификации и рекомендации.</div>
    </div>
    <a class="btn primary" href="login.php">Войти</a>
    <div class="hint">superadmin / superadmin123</div>
  </div>
</div>
<?php page_footer(); ?>
