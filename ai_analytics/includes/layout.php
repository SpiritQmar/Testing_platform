<?php
require_once __DIR__ . '/auth.php';

function render_header(string $title): void {
    $lang = get_lang();
    $flash = flash_get();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title><?= h($title) ?> - <?= h(t('app_name')) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/app.css" rel="stylesheet">
  <link href="assets/index-custom.css" rel="stylesheet">
  <link href="assets/enhancements.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <style>
    body{background:#f5f5f5}.app-shell{min-height:100vh}.content{flex:1}.topbar{background:#fff;padding:15px 30px;border-bottom:1px solid #dee2e6}.panel{background:#fff;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}.status-pill{display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600}.status-hard{background:#fee2e2;color:#dc2626}.status-normal{background:#fef3c7;color:#d97706}.status-easy{background:#d1fae5;color:#059669}.info-list{display:grid;gap:10px}.info-box{padding:12px;background:#f8f9fa;border-radius:6px}.small-muted{font-size:12px;color:#6c757d}.fw-semibold{font-weight:600}.display-6{font-size:1.5rem}.btn{border-radius:6px}.form-control,.form-select{border-radius:6px}.table th{font-weight:600;font-size:13px;color:#495057}.chart-bars{display:grid;gap:8px}.chart-row{display:grid;grid-template-columns:60px 1fr 50px;gap:10px;align-items:center}.chart-track{background:#e9ecef;border-radius:4px;height:24px;overflow:hidden}.chart-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#10b981)}.metric-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.metric-box{padding:15px;background:#f8f9fa;border-radius:8px;text-align:center}
  </style>
</head>
<body>
<div class="app-shell">
  <main class="content">
    <div class="container-fluid py-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?>"><?= h($flash['text']) ?></div>
      <?php endif; ?>
<?php }

function render_footer(string $page_scripts=''): void {
?>
    </div>
  </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="assets/enhancements.js?v=<?= time() ?>"></script>
<script src="assets/charts.js?v=<?= time() ?>"></script>
<?= $page_scripts ?>
</body>
</html>
<?php } ?>
