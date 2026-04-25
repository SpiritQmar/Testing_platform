<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';
require_login();
require_role(['superadmin', 'admin', 'teacher']);

$criteria = [];
try {
    $stmt = $pdo->query("SELECT * FROM evaluation_criteria ORDER BY criteria_id");
    $criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = 'Error: ' . $e->getMessage();
}

$detailsCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM evaluation_details");
    $detailsCount = $stmt->fetch()['count'] ?? 0;
} catch (Throwable $e) {
    $detailsCount = 0;
}

$lang = get_lang();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title>View Evaluation Criteria</title>
  <link href="../assets/vendor/css/bootstrap-lite.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.container{max-width:1200px;margin:0 auto;padding:20px}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="container">
  <h1 class="h3 mb-4">Evaluation Criteria</h1>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card">
        <div class="card-body text-center">
          <div class="h2"><?= number_format(count($criteria)) ?></div>
          <div class="small text-muted">Total Criteria</div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card">
        <div class="card-body text-center">
          <div class="h2"><?= number_format($detailsCount) ?></div>
          <div class="small text-muted">Evaluation Details Records</div>
        </div>
      </div>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>

  <?php if (empty($criteria)): ?>
    <div class="alert alert-info">No criteria imported yet</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-striped table-hover">
        <thead>
          <tr>
            <th>ID</th>
            <th>Kazakh</th>
            <th>Russian</th>
            <th>English</th>
            <th>Weight (%)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($criteria as $c): ?>
            <tr>
              <td><?= h($c['criteria_id']) ?></td>
              <td><?= h($c['name_kazakh']) ?></td>
              <td><?= h($c['name_russian']) ?></td>
              <td><?= h($c['name_english']) ?></td>
              <td><?= h($c['weight_percent']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <div class="mt-4">
    <a href="../index.php?section=import" class="btn btn-primary">Back to Import</a>
    <a href="../index.php" class="btn btn-outline-secondary">Back to Analytics</a>
  </div>
</div>
<script src="../assets/vendor/js/bootstrap.bundle.min.js"></script>
</body>
</html>
