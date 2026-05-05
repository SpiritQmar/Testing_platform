<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

$currentLang = $_SESSION['ai_lang'] ?? 'ru';
$langFile = __DIR__ . '/../frontend_data/lang/' . $currentLang . '.json';
if (file_exists($langFile)) {
    $translations = json_decode(file_get_contents($langFile), true) ?? [];
} else {
    $translations = [];
}

function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

$imports = [];
try {
    $imports = $pdo->query("
        SELECT import_id, source_filename, rows_imported, created_at
        FROM imports_log
        WHERE import_type = 'exam_results_upload' AND rows_imported > 0
        ORDER BY import_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Error fetching recent imports: " . $e->getMessage());
}

$importId = null;
if (isset($_SESSION['ai_analytics_import_id'])) {
    foreach ($imports as $imp) {
        if ((int)$imp['import_id'] === (int)$_SESSION['ai_analytics_import_id']) {
            $importId = (int)$imp['import_id'];
            break;
        }
    }
}
if ($importId === null && !empty($imports)) {
    $importId = (int)$imports[0]['import_id'];
    $_SESSION['ai_analytics_import_id'] = $importId;
}

$stats = ['questions' => 0, 'students' => 0, 'attempts' => 0, 'avg_score' => 0];
$previousStats = ['questions' => 0, 'students' => 0, 'attempts' => 0, 'avg_score' => 0];

if ($importId) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COUNT(DISTINCT question_id) as questions,
                COUNT(DISTINCT student_id) as students,
                COUNT(*) as attempts,
                AVG(score_after_appeal) as avg_score
            FROM raw_exam_results
            WHERE import_id = ?
        ");
        $stmt->execute([$importId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Error: " . $e->getMessage());
    }
}

function calculateChange($current, $previous) {
    if ($previous == 0) return ['change' => 0, 'isPositive' => true];
    $change = (($current - $previous) / $previous) * 100;
    return [
        'change' => abs(round($change)),
        'isPositive' => $change >= 0
    ];
}

$questionsChange = calculateChange($stats['questions'], $previousStats['questions']);
$studentsChange = calculateChange($stats['students'], $previousStats['students']);
$attemptsChange = calculateChange($stats['attempts'], $previousStats['attempts']);
$scoreChange = calculateChange($stats['avg_score'], $previousStats['avg_score']);
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Overview</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="/uams/ai_analytics/assets/app.css" rel="stylesheet">
    <link href="/uams/ai_analytics/assets/index-custom.css" rel="stylesheet">
    <link href="/uams/ai_analytics/assets/enhancements.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 20px; background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .overview-wrapper { max-width: 1400px; margin: 0 auto; }
    </style>
</head>
<body>
<div class="overview-wrapper">
    <div class="stats-grid">
      <div class="metric-card">
        <div class="metric-icon blue">
          <i class="bi bi-question-circle"></i>
        </div>
        <div class="metric-value"><?= number_format($stats['questions'] ?: 0) ?></div>
        <div class="metric-label"><?= t('questions') ?></div>
        <div class="metric-change <?= $questionsChange['isPositive'] ? 'positive' : 'negative' ?>">
          <i class="bi bi-arrow-<?= $questionsChange['isPositive'] ? 'up' : 'down' ?>"></i>
          <span><?= $questionsChange['isPositive'] ? '+' : '-' ?><?= $questionsChange['change'] ?>%</span>
        </div>
      </div>
      <div class="metric-card">
        <div class="metric-icon purple">
          <i class="bi bi-graph-up"></i>
        </div>
        <div class="metric-value"><?= number_format($stats['students'] ?: 0) ?></div>
        <div class="metric-label"><?= t('students') ?></div>
        <div class="metric-change <?= $studentsChange['isPositive'] ? 'positive' : 'negative' ?>">
          <i class="bi bi-arrow-<?= $studentsChange['isPositive'] ? 'up' : 'down' ?>"></i>
          <span><?= $studentsChange['isPositive'] ? '+' : '-' ?><?= $studentsChange['change'] ?>%</span>
        </div>
      </div>
      <div class="metric-card">
        <div class="metric-icon green">
          <i class="bi bi-check-circle"></i>
        </div>
        <div class="metric-value"><?= number_format($stats['attempts'] ?: 0) ?></div>
        <div class="metric-label"><?= t('attempts') ?></div>
        <div class="metric-change <?= $attemptsChange['isPositive'] ? 'positive' : 'negative' ?>">
          <i class="bi bi-arrow-<?= $attemptsChange['isPositive'] ? 'up' : 'down' ?>"></i>
          <span><?= $attemptsChange['isPositive'] ? '+' : '-' ?><?= $attemptsChange['change'] ?>%</span>
        </div>
      </div>
      <div class="metric-card">
        <div class="metric-icon red">
          <i class="bi bi-exclamation-triangle"></i>
        </div>
        <div class="metric-value"><?= number_format($stats['avg_score'] ?: 0, 1) ?></div>
        <div class="metric-label"><?= t('avg_score') ?></div>
        <div class="metric-change <?= $scoreChange['isPositive'] ? 'positive' : 'negative' ?>">
          <i class="bi bi-arrow-<?= $scoreChange['isPositive'] ? 'up' : 'down' ?>"></i>
          <span><?= $scoreChange['isPositive'] ? '+' : '-' ?><?= $scoreChange['change'] ?>%</span>
        </div>
      </div>
    </div>

    <div class="row g-4 margin-top-24">
      <div class="col-md-12">
        <div class="card h-100 quick-guide-card">
          <div class="card-header"><?= t('quick_access') ?></div>
          <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px;">
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #667eea;"><i class="bi bi-file-earmark"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">Импорт силлабуса</div>
                  <div style="font-size: 12px; line-height: 1.4;">Загрузите силлабус для анализа соответствия вопросов</div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #10b981;"><i class="bi bi-bar-chart"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">Импорт результатов</div>
                  <div style="font-size: 12px; line-height: 1.4;">Загрузите результаты экзаменов для анализа</div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #f59e0b;"><i class="bi bi-shuffle"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">Сортировка</div>
                  <div style="font-size: 12px; line-height: 1.4;">Используйте сортировку для анализа данных</div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #ef4444;"><i class="bi bi-graph-up"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">Анализ качества</div>
                  <div style="font-size: 12px; line-height: 1.4;">Проверьте качество и сложность вопросов</div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #8b5cf6;"><i class="bi bi-link"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">Корреляция</div>
                  <div style="font-size: 12px; line-height: 1.4;">Анализ корреляции между вопросами</div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #06b6d4;"><i class="bi bi-people"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">Студенты</div>
                  <div style="font-size: 12px; line-height: 1.4;">Анализ успеваемости студентов</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
