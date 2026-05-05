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

function csrf_input() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return '<input type="hidden" name="csrf_token" value="' . h($_SESSION['csrf_token']) . '">';
}

require_once __DIR__ . '/services/RuleClassifierService.php';
$rules = new RuleClassifierService($pdo);

$activeRules = [];
try {
    $activeRules = $rules->getActiveClassificationRules();
} catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}

$minStudentsDiscrimination = $_SESSION['min_students_discrimination'] ?? 10;
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="/diplom_project/UAMS/ai_analytics/assets/app.css" rel="stylesheet">
    <link href="/diplom_project/UAMS/ai_analytics/assets/index-custom.css" rel="stylesheet">
    <link href="/diplom_project/UAMS/ai_analytics/assets/enhancements.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 20px; background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .settings-wrapper { max-width: 1400px; margin: 0 auto; }
        .settings-tabs { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
        .settings-tabs button { padding: 8px 16px; border: none; background: white; border-radius: 6px; cursor: pointer; font-weight: 500; }
        .settings-tabs button.active { background: #667eea; color: white; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
        .form-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="settings-wrapper">
    <h3 class="h4 mb-4">Настройки и правила</h3>

    <div class="settings-tabs">
        <button onclick="showSettingsTab('rules')" id="settings-btn-rules" class="active">Правила</button>
        <button onclick="showSettingsTab('settings')" id="settings-btn-settings">Настройки</button>
    </div>

    <div id="settings-rules" style="display: block;">
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">Активные правила</h6>
                <?php if (empty($activeRules)): ?>
                    <div class="alert alert-info">Правила не настроены</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($activeRules as $rule): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= h($rule['rule_name']) ?></h6>
                                    <span class="badge bg-success">Активно</span>
                                </div>
                                <small class="text-muted">Порядок: <?= (int)$rule['rule_order'] ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <h6 class="fw-semibold mb-3">Создать новое правило</h6>
                <form method="post" class="mb-3">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Название правила</label>
                        <input type="text" name="rule_name" class="form-control" required placeholder="Например: Слишком сложные вопросы">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Метрика</label>
                        <select name="metric_name" class="form-select">
                            <option value="difficulty_index">Индекс сложности</option>
                            <option value="discrimination_index">Индекс дискриминации</option>
                            <option value="avg_score">Средний балл</option>
                            <option value="total_attempts">Всего попыток</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Оператор</label>
                        <select name="operator" class="form-select">
                            <option value=">">Больше (&gt;)</option>
                            <option value="<">Меньше (&lt;)</option>
                            <option value=">=">Больше или равно (&gt;=)</option>
                            <option value="<=">Меньше или равно (&lt;=)</option>
                            <option value="between">Между</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Значение от</label>
                        <input type="number" step="0.01" name="value_from" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Значение до (для "между")</label>
                        <input type="number" step="0.01" name="value_to" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Категория качества</label>
                        <select name="category" class="form-select">
                            <option value="too_difficult">Слишком сложно</option>
                            <option value="too_easy">Слишком легко</option>
                            <option value="poor_discrimination">Плохая дискриминация</option>
                            <option value="insufficient_attempts">Недостаточно попыток</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Сообщение пользователю</label>
                        <input type="text" name="action_message" class="form-control" placeholder="Рекомендация для улучшения">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Порядок выполнения</label>
                        <input type="number" min="1" name="rule_order" class="form-control" value="1">
                    </div>
                    <button type="submit" class="btn btn-primary">Создать правило</button>
                </form>
            </div>
        </div>
    </div>

    <div id="settings-settings" style="display: none;">
        <div class="form-grid">
            <div class="form-card">
                <h6 class="fw-semibold mb-3">Глобальные настройки</h6>
                <form method="post" class="mb-3">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Порог сложности</label>
                        <input type="number" step="0.01" min="0" max="1" name="difficulty_threshold" class="form-control" value="0.5">
                        <small class="text-muted">Значение от 0 до 1</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Порог дискриминации</label>
                        <input type="number" step="0.01" min="0" max="1" name="discrimination_threshold" class="form-control" value="0.3">
                        <small class="text-muted">Минимальное значение для хорошей дискриминации</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Минимум попыток</label>
                        <input type="number" min="1" name="min_attempts" class="form-control" value="5">
                        <small class="text-muted">Минимальное количество попыток для анализа</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Минимум студентов для дискриминации</label>
                        <input type="number" min="2" name="min_students_discrimination" class="form-control" value="<?= $minStudentsDiscrimination ?>">
                        <small class="text-muted">Минимальное количество студентов</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Порог дубликатов</label>
                        <input type="number" step="0.01" min="0" max="1" name="dedup_threshold" class="form-control" value="0.85">
                        <small class="text-muted">Порог схожести для обнаружения дубликатов</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Вес TF-IDF</label>
                        <input type="number" step="0.01" min="0" max="1" name="tfidf_weight" class="form-control" value="0.6">
                        <small class="text-muted">Вес TF-IDF в гибридном методе</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
                </form>
            </div>

            <div class="form-card">
                <h6 class="fw-semibold mb-3">Настройки дисциплины</h6>
                <form method="post" class="mb-3">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Дисциплина</label>
                        <select name="discipline_id" class="form-select">
                            <option value="">Выберите дисциплину</option>
                            <option value="1">Педиатрия</option>
                            <option value="2">Хирургия</option>
                            <option value="3">Терапия</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Коэффициент сложности</label>
                        <input type="number" step="0.01" min="0" max="2" name="discipline_difficulty_coeff" class="form-control" value="1.0">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Коэффициент важности</label>
                        <input type="number" step="0.01" min="0" max="2" name="discipline_importance_coeff" class="form-control" value="1.0">
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
                </form>
            </div>

            <div class="form-card">
                <h6 class="fw-semibold mb-3">Настройки специальности</h6>
                <form method="post" class="mb-3">
                    <?= csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">Специальность</label>
                        <select name="specialty_id" class="form-select">
                            <option value="">Выберите специальность</option>
                            <option value="1">Лечебное дело</option>
                            <option value="2">Педиатрия</option>
                            <option value="3">Стоматология</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Базовый порог сложности</label>
                        <input type="number" step="0.01" min="0" max="1" name="specialty_difficulty_threshold" class="form-control" value="0.5">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Коэффициент строгости</label>
                        <input type="number" step="0.01" min="0" max="2" name="specialty_strictness_coeff" class="form-control" value="1.0">
                    </div>
                    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showSettingsTab(tabType) {
  const tabs = ['rules', 'settings'];
  tabs.forEach(type => {
    const tabDiv = document.getElementById('settings-' + type);
    const btn = document.getElementById('settings-btn-' + type);
    if (tabDiv) {
      tabDiv.style.display = type === tabType ? 'block' : 'none';
    }
    if (btn) {
      if (type === tabType) {
        btn.classList.add('active');
      } else {
        btn.classList.remove('active');
      }
    }
  });
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
