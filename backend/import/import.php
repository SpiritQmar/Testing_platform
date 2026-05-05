<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/db.php';

require_login();

define('DEFAULT_COURSE', 3);
define('MAX_SCORE', 100);

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en', 'kz'])) {
    $_SESSION['ai_lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['ai_lang'] ?? 'ru';
$langFile = __DIR__ . '/../../frontend_data/lang/' . $currentLang . '.json';
if (file_exists($langFile)) {
    $translations = json_decode(file_get_contents($langFile), true) ?? [];
} else {
    $translations = [];
}

function t($key) {
    global $translations;
    return $translations[$key] ?? $key;
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string {
        return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf_or_fail')) {
    function verify_csrf_or_fail(): void {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
            http_response_code(419);
            exit('CSRF token mismatch');
        }
    }
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

$importError = null;
$importSuccess = null;
$importFlash = $_SESSION['import_flash'] ?? null;
$questionImportFlash = $_SESSION['question_text_import_flash'] ?? null;
unset($_SESSION['import_flash'], $_SESSION['question_text_import_flash']);
$activeImportTab = preg_replace('/[^a-z-]/', '', (string)($_GET['import_tab'] ?? 'syllabus'));
$questionImportDisciplines = [];
$currentImportName = '';
try {
    foreach ($imports as $importItem) {
        if ((int)$importItem['import_id'] === (int)$importId) {
            $currentImportName = $importItem['source_filename'] ?? '';
            break;
        }
    }

    if ($importId !== null) {
        $stmt = $pdo->prepare("SELECT DISTINCT discipline_name FROM raw_exam_results WHERE import_id = :import_id AND discipline_name IS NOT NULL AND discipline_name != '' ORDER BY discipline_name");
        $stmt->execute([':import_id' => $importId]);
        $questionImportDisciplines = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
} catch (Throwable $e) {
    error_log('Error loading question import disciplines: ' . $e->getMessage());
}

if (isset($_GET['refresh_topics']) && $_GET['refresh_topics'] == '1') {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->query("
            SELECT st.syllabus_topic_id, st.discipline_name, st.course_number, st.title
            FROM ai_syllabus_topics st
            INNER JOIN user_syllabuses us ON
                us.discipline_name = st.discipline_name
            ORDER BY st.discipline_name, st.course_number, st.title
            LIMIT 100
        ");
        $syllabusTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['topics' => $syllabusTopics]);
    } catch (Throwable $e) {
        echo json_encode(['topics' => []]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_selected_questions'])) {
    verify_csrf_or_fail();
    $syllabusTopicId = (int)$_POST['syllabus_topic_id'];
    $questionIds = $_POST['question_ids'] ?? [];

    if ($syllabusTopicId > 0 && !empty($questionIds)) {
        try {
            $stmt = $pdo->prepare("UPDATE hier_questions SET syllabus_topic_id = ? WHERE question_id = ?");
            $linked = 0;
            foreach ($questionIds as $qid) {
                $stmt->execute([$syllabusTopicId, (int)$qid]);
                $linked++;
            }
            $importSuccess = "Привязано вопросов: $linked";
        } catch (Throwable $e) {
            $importError = "Ошибка привязки: " . $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('import') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="/uams/ai_analytics/assets/app.css" rel="stylesheet">
    <link href="/uams/ai_analytics/assets/index-custom.css" rel="stylesheet">
    <link href="/uams/ai_analytics/assets/enhancements.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .import-wrapper { padding: 20px; }
    </style>
</head>
<body>
<div class="import-wrapper">
    <div class="table-container">

    <?php if ($importSuccess): ?>
      <div class="alert alert-success"><?= h($importSuccess) ?></div>
    <?php endif; ?>

    <?php if ($importError): ?>
      <div class="alert alert-danger"><?= h($importError) ?></div>
    <?php endif; ?>

    <?php if ($importFlash): ?>
      <div class="alert alert-<?= h($importFlash['type'] ?? 'info') ?>"><?= h($importFlash['message'] ?? '') ?></div>
    <?php endif; ?>

    <?php if ($questionImportFlash): ?>
      <div class="alert alert-<?= h($questionImportFlash['type'] ?? 'info') ?>"><?= h($questionImportFlash['message'] ?? '') ?></div>
    <?php endif; ?>

    <div class="import-tabs">
      <button onclick="showImportTab('syllabus')" id="import-btn-syllabus" class="chart-button active"><?= t('syllabus_import') ?></button>
      <button onclick="showImportTab('ods')" id="import-btn-ods" class="chart-button"><?= t('ods_exam_results') ?></button>
      <button onclick="showImportTab('questions')" id="import-btn-questions" class="chart-button">Импорт вопросов</button>
      <button onclick="showImportTab('duplicates')" id="import-btn-duplicates" class="chart-button"><?= t('duplicate_detection') ?></button>
      <button onclick="showImportTab('criteria')" id="import-btn-criteria" class="chart-button"><?= t('evaluation_criteria') ?></button>
      <button onclick="showImportTab('details')" id="import-btn-details" class="chart-button"><?= t('evaluation_details') ?></button>
      <button onclick="showImportTab('answers')" id="import-btn-answers" class="chart-button"><?= t('student_answers') ?></button>
    </div>

    <div class="import-layout">
      <div class="import-left">

        <div id="import-syllabus" style="display: block;">
          <form method="post" enctype="multipart/form-data" action="imports/import_syllabus.php">
            <?= csrf_input() ?>
            <div class="import-upload-box" onclick="document.getElementById('syllabusFileInput').click()">
              <div class="import-upload-icon"><i class="bi bi-cloud-arrow-up"></i></div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.DOCX, .PDF, .TXT, .ODT</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="syllabus_file" id="syllabusFileInput" accept=".docx,.doc,.pdf,.txt,.odt" required style="display: none;" onchange="updateUploadBox(this)">
            </div>
            <div class="import-fields">
              <div class="import-field">
                <label class="form-label"><?= t('discipline') ?></label>
                <input type="text" name="discipline_name" class="form-control" required placeholder="e.g.: Pediatrics">
              </div>
              <div class="import-field">
                <label class="form-label"><?= t('course') ?></label>
                <select name="course_number" class="form-select">
                  <option value="1">1 курс</option>
                  <option value="2">2 курс</option>
                  <option value="3" selected>3 курс</option>
                  <option value="4">4 курс</option>
                  <option value="5">5 курс</option>
                </select>
              </div>
            </div>
            <button type="submit" class="import-submit-btn"><?= t('upload_process') ?></button>
          </form>
        </div>

        <div id="import-ods" style="display: none;">
          <form method="post" enctype="multipart/form-data" action="imports/data_import.php">
            <?= csrf_input() ?>
            <div class="import-upload-box" onclick="document.getElementById('odsFileInput').click()">
              <div class="import-upload-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.ODS, .CSV, .XLSX</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="exam_export" id="odsFileInput" accept=".ods,.csv,.xlsx" required style="display: none;" onchange="updateUploadBox(this)">
            </div>
            <button type="submit" class="import-submit-btn"><?= t('convert_import') ?></button>
          </form>
        </div>

        <div id="import-questions" style="display: none;">
          <form method="post" enctype="multipart/form-data" action="imports/import_question_texts.php">
            <?= csrf_input() ?>
            <?php if ($importId === null): ?>
              <div class="alert alert-warning mb-3">Сначала загрузи или выбери выгрузку результатов.</div>
            <?php endif; ?>
            <input type="hidden" name="import_id" value="<?= (int)$importId ?>">
            <div class="import-upload-box" onclick="document.getElementById('questionsFileInput').click()">
              <div class="import-upload-icon"><i class="bi bi-file-earmark-font"></i></div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.TXT с нумерованными вопросами</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="questions_file" id="questionsFileInput" accept=".txt" required style="display: none;" onchange="updateUploadBox(this)">
            </div>
            <div class="import-fields">
              <div class="import-field">
                <label class="form-label">Текущий импорт</label>
                <input type="text" class="form-control" value="<?= h($currentImportName ?: 'Не выбран') ?>" readonly>
              </div>
              <div class="import-field">
                <label class="form-label">Дисциплина</label>
                <select name="discipline_name" class="form-select" required <?= $importId === null ? 'disabled' : '' ?>>
                  <option value="">-- Выбери дисциплину --</option>
                  <?php foreach ($questionImportDisciplines as $disciplineOption): ?>
                    <option value="<?= h($disciplineOption) ?>"><?= h($disciplineOption) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <button type="submit" class="import-submit-btn" <?= $importId === null ? 'disabled' : '' ?>>Импортировать вопросы</button>
          </form>
        </div>

        <div id="import-duplicates" style="display: none;">
          <p class="text-muted">Обнаружение дубликатов</p>
        </div>

        <div id="import-criteria" style="display: none;">
          <form method="post" enctype="multipart/form-data" action="imports/import_evaluation_criteria.php">
            <?= csrf_input() ?>
            <input type="hidden" name="import_id" value="<?= $importId ?? 1 ?>">
            <div class="import-upload-box" onclick="document.getElementById('criteriaFileInput').click()">
              <div class="import-upload-icon"><i class="bi bi-file-earmark-text"></i></div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.CSV, .XLSX, .ODS (ID, Қазақша, Русский, English, Вес)</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="criteria_file" id="criteriaFileInput" accept=".csv,.xlsx,.ods" required style="display: none;" onchange="updateUploadBox(this)">
            </div>
            <button type="submit" class="import-submit-btn"><?= t('import_criteria') ?></button>
          </form>
        </div>

        <div id="import-details" style="display: none;">
          <form method="post" enctype="multipart/form-data" action="imports/import_evaluation_details.php">
            <?= csrf_input() ?>
            <input type="hidden" name="import_id" value="<?= $importId ?? 1 ?>">
            <div class="import-upload-box" onclick="document.getElementById('detailsFileInput').click()">
              <div class="import-upload-icon"><i class="bi bi-file-earmark-bar-graph"></i></div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.CSV, .XLSX, .ODS (Код работы, ID критерия, Оценка)</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="details_file" id="detailsFileInput" accept=".csv,.xlsx,.ods" required style="display: none;" onchange="updateUploadBox(this)">
            </div>
            <button type="submit" class="import-submit-btn"><?= t('import_details') ?></button>
          </form>
        </div>

        <div id="import-answers" style="display: none;">
          <form method="post" enctype="multipart/form-data" action="imports/import_student_answers.php">
            <?= csrf_input() ?>
            <input type="hidden" name="import_id" value="<?= $importId ?? 1 ?>">
            <div class="import-upload-box" onclick="document.getElementById('answersFileInput').click()">
              <div class="import-upload-icon"><i class="bi bi-file-earmark-person"></i></div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.CSV, .XLSX, .ODS (Код работы, Язык, Вопрос, Ответ и др.)</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="answers_file" id="answersFileInput" accept=".csv,.xlsx,.ods" required style="display: none;" onchange="updateUploadBox(this)">
            </div>
            <button type="submit" class="import-submit-btn"><?= t('import_answers') ?></button>
          </form>
        </div>

      </div>
    </div>
    </div>
</div>

<script>
function showImportTab(tabType) {
  const tabs = ['syllabus', 'ods', 'questions', 'duplicates', 'criteria', 'details', 'answers'];
  tabs.forEach(type => {
    const tabDiv = document.getElementById('import-' + type);
    const btn = document.getElementById('import-btn-' + type);
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

document.addEventListener('DOMContentLoaded', () => {
  showImportTab('<?= h($activeImportTab) ?>');
});

function updateUploadBox(input) {
  const box = input.closest('.import-upload-box');
  const text = box ? box.querySelector('.import-upload-text') : null;
  if (text && input.files && input.files.length > 0) {
    text.textContent = input.files[0].name;
  }
}

function toggleAllCheckboxes(source) {
  const checkboxes = document.querySelectorAll('.question-checkbox');
  checkboxes.forEach(checkbox => {
    checkbox.checked = source.checked;
  });
}

function filterQuestions() {
  const searchValue = document.getElementById('questionSearch').value.toLowerCase();
  const rows = document.querySelectorAll('#questionsTableBody tr');

  rows.forEach(row => {
    const id = row.getAttribute('data-id');
    const question = row.getAttribute('data-question');
    const discipline = row.getAttribute('data-discipline');
    const topic = row.getAttribute('data-topic');

    const matches = id.includes(searchValue) ||
                   question.includes(searchValue) ||
                   discipline.includes(searchValue) ||
                   topic.includes(searchValue);

    row.style.display = matches ? '' : 'none';
  });
}

function refreshSyllabusTopics() {
  fetch('?section=import&refresh_topics=1')
    .then(response => response.json())
    .then(data => {
      const select = document.getElementById('syllabusTopicSelect');
      select.innerHTML = '<option value="">-- <?= t("select_syllabus_topic") ?> --</option>';

      data.topics.forEach(topic => {
        const option = document.createElement('option');
        option.value = topic.syllabus_topic_id;
        option.textContent = `${topic.discipline_name} - ${topic.course_number}: ${topic.title}`;
        select.appendChild(option);
      });

      document.getElementById('topicCount').textContent = data.topics.length;
    })
    .catch(error => console.error('Error refreshing topics:', error));
}
</script>

<!-- Loading Overlay -->
<div id="importLoadingOverlay" class="import-loading-overlay">
  <div class="import-loading-card">
    <div class="import-loading-spinner">
      <div class="spinner-ring"></div>
      <div class="spinner-ring inner"></div>
      <i class="bi bi-cloud-arrow-up spinner-icon"></i>
    </div>
    <h3 class="import-loading-title">Импорт данных</h3>
    <p class="import-loading-status" id="loadingStatusText">Загрузка файла...</p>
    <div class="import-loading-progress">
      <div class="import-loading-progress-bar" id="loadingProgressBar"></div>
    </div>
    <p class="import-loading-hint">Не закрывайте страницу</p>
  </div>
</div>

<style>
.import-loading-overlay {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 99999;
  background: rgba(15, 23, 42, 0.75);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  align-items: center;
  justify-content: center;
}
.import-loading-overlay.active {
  display: flex;
  animation: overlayFadeIn 0.3s ease-out;
}
@keyframes overlayFadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}
.import-loading-card {
  background: linear-gradient(145deg, #1e293b, #0f172a);
  border: 1px solid rgba(100, 116, 139, 0.3);
  border-radius: 20px;
  padding: 48px 56px;
  text-align: center;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5), 0 0 40px rgba(102, 126, 234, 0.15);
  animation: cardSlideUp 0.4s ease-out;
  max-width: 420px;
  width: 90%;
}
@keyframes cardSlideUp {
  from { opacity: 0; transform: translateY(30px) scale(0.95); }
  to { opacity: 1; transform: translateY(0) scale(1); }
}
.import-loading-spinner {
  position: relative;
  width: 90px;
  height: 90px;
  margin: 0 auto 28px;
}
.spinner-ring {
  position: absolute;
  inset: 0;
  border: 3px solid transparent;
  border-top-color: #667eea;
  border-right-color: #764ba2;
  border-radius: 50%;
  animation: spinnerRotate 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
}
.spinner-ring.inner {
  inset: 12px;
  border-top-color: #10b981;
  border-right-color: #06b6d4;
  animation-duration: 0.9s;
  animation-direction: reverse;
}
.spinner-icon {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-size: 28px;
  color: #667eea;
  animation: iconPulse 1.5s ease-in-out infinite;
}
@keyframes spinnerRotate {
  to { transform: rotate(360deg); }
}
@keyframes iconPulse {
  0%, 100% { opacity: 0.6; transform: translate(-50%, -50%) scale(1); }
  50% { opacity: 1; transform: translate(-50%, -50%) scale(1.15); }
}
.import-loading-title {
  color: #f1f5f9;
  font-size: 20px;
  font-weight: 700;
  margin-bottom: 8px;
  letter-spacing: -0.02em;
}
.import-loading-status {
  color: #94a3b8;
  font-size: 14px;
  margin-bottom: 24px;
  min-height: 20px;
}
.import-loading-progress {
  width: 100%;
  height: 6px;
  background: rgba(100, 116, 139, 0.2);
  border-radius: 6px;
  overflow: hidden;
  margin-bottom: 20px;
}
.import-loading-progress-bar {
  height: 100%;
  width: 0%;
  background: linear-gradient(90deg, #667eea, #764ba2, #10b981);
  background-size: 200% 100%;
  border-radius: 6px;
  animation: progressGradient 2s ease infinite, progressGrow 8s cubic-bezier(0.1, 0.5, 0.3, 1) forwards;
}
@keyframes progressGradient {
  0% { background-position: 0% 50%; }
  50% { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}
@keyframes progressGrow {
  0% { width: 0%; }
  15% { width: 25%; }
  40% { width: 50%; }
  65% { width: 70%; }
  85% { width: 85%; }
  100% { width: 92%; }
}
.import-loading-hint {
  color: #64748b;
  font-size: 12px;
  margin: 0;
  font-style: italic;
}
</style>

<script>
(function() {
  const overlay = document.getElementById('importLoadingOverlay');
  const statusText = document.getElementById('loadingStatusText');
  const statusMessages = [
    'Загрузка файла...',
    'Чтение данных...',
    'Обработка записей...',
    'Сохранение в базу данных...',
    'Почти готово...'
  ];

  function showLoading() {
    overlay.classList.add('active');
    let msgIndex = 0;
    const interval = setInterval(() => {
      msgIndex++;
      if (msgIndex < statusMessages.length) {
        statusText.textContent = statusMessages[msgIndex];
      } else {
        clearInterval(interval);
      }
    }, 2000);
  }

  // Intercept all import forms (multipart/form-data)
  document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form => {
    form.addEventListener('submit', function(e) {
      const fileInput = form.querySelector('input[type="file"]');
      if (fileInput && fileInput.files.length === 0) return; // don't show if no file
      showLoading();
    });
  });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

