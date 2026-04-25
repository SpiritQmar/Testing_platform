<?php

session_start();

define('PER_PAGE', 50);
define('DEFAULT_COURSE', 3);
define('MAX_SCORE', 100);
define('DEFAULT_DEDUP_THRESHOLD', 0.85);
define('DEFAULT_TFIDF_WEIGHT', 0.6);
define('MIN_STUDENTS_DISCRIMINATION', 2);

$availableLanguages = ['kz' => 'Қазақша', 'ru' => 'Русский', 'en' => 'English'];
$currentLang = $_SESSION['ai_lang'] ?? 'ru';


if (isset($_GET['lang'])) {
    $newLang = $_GET['lang'];
    if (isset($availableLanguages[$newLang])) {
        $_SESSION['ai_lang'] = $newLang;
        $currentLang = $newLang;
    }
}


$langFile = __DIR__ . '/lang/' . $currentLang . '.json';
if (file_exists($langFile)) {
    $jsonContent = file_get_contents($langFile);
    $translations = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $translations = [];
    }
} else {
    $translations = [];
}

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/AIAnalyticsService.php';
require_once __DIR__ . '/services/TfIdfService.php';
require_once __DIR__ . '/services/RuleClassifierService.php';
require_once __DIR__ . '/api/router.php';

require_role(['superadmin', 'admin', 'teacher']);
$config = require __DIR__ . '/config.php';


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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_id'])) {
    verify_csrf_or_fail();
    $selectedImportId = (int)$_POST['import_id'];
    foreach ($imports as $imp) {
        if ((int)$imp['import_id'] === $selectedImportId) {
            $_SESSION['ai_analytics_import_id'] = $selectedImportId;
            $importId = $selectedImportId;
            break;
        }
    }
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'student_risk_patterns_') === 0 || strpos($key, 'discrimination_index_') === 0) {
            unset($_SESSION[$key]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['min_students_discrimination'])) {
    verify_csrf_or_fail();
    $_SESSION['min_students_discrimination'] = max(MIN_STUDENTS_DISCRIMINATION, (int)$_POST['min_students_discrimination']);
    foreach ($_SESSION as $key => $value) {
        if (strpos($key, 'discrimination_index_') === 0) {
            unset($_SESSION[$key]);
        }
    }
}

$minStudentsDiscrimination = $_SESSION['min_students_discrimination'] ?? 10;

$ai = new AIAnalyticsService($pdo, $importId);
$ai->setMinStudentsForDiscrimination($minStudentsDiscrimination);
$embeddingsService = new EmbeddingsService('http://localhost:8000', true);
$tfidf = new TfIdfService($pdo, $embeddingsService);
$rules = new RuleClassifierService($pdo);
$router = new AnalyticsServiceRouter ($pdo);

function renderPagination($section, $pageParam, $currentPage, $totalPages, $sortColumn = '', $sortDirection = '', $totalItems = 0) {
    if ($totalPages <= 1) return '';
    
    $sortParams = '';
    if ($sortColumn) {
        $sortParams = '&' . $section . '_sort=' . $sortColumn . '&' . $section . '_dir=' . $sortDirection;
    }
    
    ob_start();
    ?>
    <div class="pagination">
      <?php if ($currentPage > 1): ?>
        <a href="?section=<?= $section ?>&<?= $pageParam ?>=<?= $currentPage - 1 ?><?= $sortParams ?>"><button>&laquo;</button></a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php if ($i == $currentPage): ?>
          <button class="active"><?= $i ?></button>
        <?php elseif (abs($i - $currentPage) <= 2 || $i == 1 || $i == $totalPages): ?>
          <a href="?section=<?= $section ?>&<?= $pageParam ?>=<?= $i ?><?= $sortParams ?>"><button><?= $i ?></button></a>
        <?php elseif (abs($i - $currentPage) == 3): ?>
          <span>...</span>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($currentPage < $totalPages): ?>
        <a href="?section=<?= $section ?>&<?= $pageParam ?>=<?= $currentPage + 1 ?><?= $sortParams ?>"><button>&raquo;</button></a>
      <?php endif; ?>
    </div>
    <p class="text-center text-muted small"><?= t('page') ?> <?= $currentPage ?> <?= t('of') ?> <?= $totalPages ?> (<?= $totalItems ?> <?= t('records') ?>)</p>
    <?php
    return ob_get_clean();
}

$section = $_GET['section'] ?? 'overview';
$sections = [
    'overview' => t('overview'),
    'validation' => t('validation'),
    'quality' => t('quality'),
    'correlation' => t('correlations'),
    'students' => t('students'),
    'semantic' => t('semantic'),
    'criteria' => t('criteria_analysis'),
    'answers' => t('student_answers'),
    'import' => t('import'),
    'rules' => t('rules'),
    'settings' => t('settings'),
];

render_header(t('title'));
?>

<script>
function showChart(chartType) {
  const charts = ['quality', 'correlation', 'students', 'validation', 'semantic', 'criteria', 'answers'];
  charts.forEach(type => {
    const chartDiv = document.getElementById('chart-' + type);
    const btn = document.getElementById('btn-' + type);
    if (chartDiv) {
      chartDiv.style.display = type === chartType ? 'block' : 'none';
    }
    if (btn) {
      if (type === chartType) {
        btn.style.background = '#667eea';
        btn.style.color = 'white';
        btn.style.borderColor = '#667eea';
      } else {
        btn.style.background = 'white';
        btn.style.color = '#64748b';
        btn.style.borderColor = '#e2e8f0';
      }
    }
  });
}

function showImportTab(tabType) {
  const tabs = ['syllabus', 'ods', 'syllabus-link', 'duplicates', 'criteria', 'details', 'answers'];
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

function showConfirm(message, onConfirm, form) {
  const modal = document.getElementById('confirmModal');
  const modalMessage = document.getElementById('confirmModalMessage');
  const confirmBtn = document.getElementById('confirmModalConfirm');
  const cancelBtn = document.getElementById('confirmModalCancel');

  modalMessage.textContent = message;
  modal.classList.add('show');

  confirmBtn.onclick = function() {
    modal.classList.remove('show');
    onConfirm(form);
  };

  cancelBtn.onclick = function() {
    modal.classList.remove('show');
  };
}

function showClearConfirm() {
  const form = document.getElementById('clearDataFormBottom');
  const checkboxes = form.querySelectorAll('.clear-data-options .clear-data-checkbox:checked');

  if (checkboxes.length === 0) {
    alert('Выберите хотя бы один тип данных для очистки');
    return;
  }

  const selectedItems = [];
  checkboxes.forEach(cb => {
    const label = cb.closest('.clear-data-option').querySelector('.clear-data-option-text').textContent;
    selectedItems.push(label);
  });

  const message = 'Вы уверены, что хотите удалить следующие данные?\n\n' + selectedItems.join('\n');

  const modal = document.getElementById('confirmModal');
  const modalTitle = document.getElementById('confirmModalTitle');
  const modalMessage = document.getElementById('confirmModalMessage');
  const confirmBtn = document.getElementById('confirmModalConfirm');
  const cancelBtn = document.getElementById('confirmModalCancel');

  modalTitle.textContent = '<?= t('delete_confirmation_title') ?>';

  let itemsHtml = '<div class="confirm-modal-items">';
  selectedItems.forEach(item => {
    itemsHtml += '<div class="confirm-modal-item"><i class="bi bi-x-circle"></i> ' + item + '</div>';
  });
  itemsHtml += '</div>';

  modalMessage.innerHTML = '<strong><?= t('will_be_deleted') ?></strong>' + itemsHtml;
  modal.classList.add('show');

  confirmBtn.onclick = function() {
    modal.classList.remove('show');
    form.submit();
  };

  cancelBtn.onclick = function() {
    modal.classList.remove('show');
  };
}

function updateUploadBox(input, type) {
  const box = input.parentElement;
  const text = box.querySelector('.import-upload-text');
  const icon = box.querySelector('.import-upload-icon');

  if (input.files && input.files[0]) {
    box.classList.add('has-file');
    text.textContent = input.files[0].name;
    text.style.color = '#059669';
    icon.innerHTML = '<i class="bi bi-check-circle-fill"></i>';
    icon.style.color = '#10b981';
  }
}

function setupDragAndDrop() {
  const uploadBoxes = document.querySelectorAll('.import-upload-box');

  uploadBoxes.forEach(box => {
    box.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      box.style.borderColor = '#3b82f6';
      box.style.background = 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
    });

    box.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!box.classList.contains('has-file')) {
        box.style.borderColor = '#cbd5e1';
        box.style.background = 'linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%)';
      }
    });

    box.addEventListener('drop', (e) => {
      e.preventDefault();
      e.stopPropagation();

      const input = box.querySelector('input[type="file"]');
      if (input && e.dataTransfer.files && e.dataTransfer.files[0]) {
        input.files = e.dataTransfer.files;
        updateUploadBox(input);
      }
    });
  });
}

function setupSelectAll() {
  const selectAllBtn = document.getElementById('clearDataSelectAll');
  if (!selectAllBtn) return;

  const optionCheckboxes = document.querySelectorAll('.clear-data-options .clear-data-checkbox');

  selectAllBtn.addEventListener('click', function() {
    const allChecked = Array.from(optionCheckboxes).every(c => c.checked);
    optionCheckboxes.forEach(cb => {
      cb.checked = !allChecked;
    });
  });

  optionCheckboxes.forEach(cb => {
    cb.addEventListener('change', function(e) {
      e.stopPropagation();
      const allChecked = Array.from(optionCheckboxes).every(c => c.checked);
      selectAllBtn.innerHTML = allChecked ? '<i class="bi bi-x-lg"></i> <?= t('deselect_all') ?>' : '<i class="bi bi-check-all"></i> <?= t('select_all') ?>';
    });
  });
}

document.addEventListener('DOMContentLoaded', function() {
  const cancelBtn = document.getElementById('confirmModalCancel');
  if (cancelBtn) {
    cancelBtn.onclick = function() {
      document.getElementById('confirmModal').classList.remove('show');
    };
  }

  setupDragAndDrop();
  setupSelectAll();
});

function refreshSyllabusTopics() {
  fetch('?section=import&refresh_topics=1', {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => response.json())
  .then(data => {
    const select = document.getElementById('syllabusTopicSelect');
    const topicCount = document.getElementById('topicCount');
    
    select.innerHTML = '<option value="">-- <?= t('select_syllabus_topic') ?> --</option>';
    
    data.topics.forEach(topic => {
      const option = document.createElement('option');
      option.value = topic.syllabus_topic_id;
      option.textContent = topic.discipline_name + ' - ' + topic.course_number + ': ' + topic.title;
      select.appendChild(option);
    });
    
    topicCount.textContent = data.topics.length;
  })
  .catch(error => {
    console.error('Error refreshing topics:', error);
  });
}
</script>

<style>
.ai-container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
.ai-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px; border-radius: 12px; margin-bottom: 40px; }
.ai-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
.ai-header p { font-size: 16px; opacity: 0.9; }
.ai-nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 40px; justify-content: center; }
.ai-nav-btn { flex: 1; min-width: 100px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; background: #f1f5f9; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-weight: 500; font-size: 13px; text-decoration: none; color: #1a1d21; display: flex; align-items: center; justify-content: center; text-align: center; line-height: 1.3; }
.ai-nav-btn:hover { background: #e2e8f0; transform: translateY(-2px); border-color: #cbd5e1; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); text-decoration: none; color: #1a1d21; }
.ai-nav-btn:active { transform: translateY(0); }
.ai-nav-btn.active { background: #667eea; color: white; border-color: #667eea; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3); text-decoration: none; }
.ai-section { display: none; }
.ai-section.active { display: block; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 40px; }
.stat-card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 16px rgba(0,0,0,0.15); }
.stat-value { font-size: 36px; font-weight: 700; color: #1a1d21; }
.stat-label { color: #64748b; font-size: 15px; margin-top: 8px; font-weight: 500; }
.table-container { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 30px; max-width: 1400px; margin-left: auto; margin-right: auto; overflow-x: auto; }
.badge-soft { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-soft.success { background: #d1fae5; color: #059669; }
.badge-soft.warning { background: #fef3c7; color: #d97706; }
.badge-soft.danger { background: #fee2e2; color: #dc2626; }
.badge-soft.info { background: #dbeafe; color: #2563eb; }

.quick-guide-card { animation: fadeInUp 0.6s ease-out; }
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

.quick-guide-item { 
  animation: slideIn 0.5s ease-out forwards; 
  opacity: 0;
}
.quick-guide-item:nth-child(1) { animation-delay: 0.1s; }
.quick-guide-item:nth-child(2) { animation-delay: 0.2s; }
.quick-guide-item:nth-child(3) { animation-delay: 0.3s; }
.quick-guide-item:nth-child(4) { animation-delay: 0.4s; }
.quick-guide-item:nth-child(5) { animation-delay: 0.5s; }
.quick-guide-item:nth-child(6) { animation-delay: 0.6s; }

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-20px); }
  to { opacity: 1; transform: translateX(0); }
}

.quick-guide-item:hover { 
  transform: translateX(8px) !important; 
  box-shadow: 0 8px 16px rgba(0,0,0,0.12);
  border-color: #667eea;
}

.quick-guide-icon {
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.1); }
}

.container-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 15px; }
.container-card.running { border-color: #10b981; background: #f0fdf4; }
.container-card.stopped { border-color: #ef4444; background: #fef2f2; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.form-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
.pagination { display: flex; gap: 8px; margin-top: 24px; justify-content: center; }
.pagination button { padding: 10px 18px; border: 1px solid #e2e8f0; background: white; cursor: pointer; border-radius: 8px; font-size: 14px; transition: all 0.2s ease; }
.pagination button:hover { background: #f1f5f9; transform: translateY(-1px); }
.pagination button.active { background: #667eea; color: white; border-color: #667eea; box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3); }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
table thead th a { color: inherit; text-decoration: none; }
table thead th a:hover { text-decoration: underline; }
table { border-collapse: separate; border-spacing: 0; width: auto; max-width: 100%; }
table thead th { border-bottom: 2px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 18px 14px; font-weight: 600; font-size: 16px !important; color: #1a1d21; background: #f8fafc; cursor: pointer; transition: background 0.2s; }
table thead th:hover { background: #f1f5f9; }
table thead th:last-child { border-right: none; }
table thead th a { font-size: 16px !important; color: #1a1d21; cursor: pointer; transition: color 0.2s; }
table thead th a:hover { color: #667eea; }
table tbody td { border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0; padding: 16px 14px; font-size: 16px; color: #334155; }
table tbody td:last-child { border-right: none; }
table tbody tr:hover { background: #f1f5f9; }
table tbody tr:last-child td { border-bottom: none; }
.sort-icon { font-size: 18px; margin-left: 6px; opacity: 0.6; }
.sort-icon.active { opacity: 1; color: #667eea; }
.nav-tabs { border-bottom: 2px solid #e2e8f0; }
.nav-tabs .nav-link { border: 2px solid transparent; border-bottom: none; padding: 12px 20px; color: #64748b; font-weight: 500; font-size: 14px; }
.nav-tabs .nav-link:hover { border-color: #e2e8f0; color: #1a1d21; }
.nav-tabs .nav-link.active { border-color: #e2e8f0 #e2e8f0 #fff; color: #667eea; border-bottom: 2px solid #fff; margin-bottom: -2px; }
.tab-pane { padding: 20px 0; }
.form-control { border: 2px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 14px; transition: all 0.2s ease; }
.form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); outline: none; }
.form-control:hover { border-color: #cbd5e1; }
.form-select { border: 2px solid #e2e8f0; border-radius: 8px; padding: 12px; font-size: 14px; transition: all 0.2s ease; }
.form-select:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); outline: none; }
.form-select:hover { border-color: #cbd5e1; }
.btn { padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; border: none; transition: all 0.2s ease; cursor: pointer; }
.btn:hover { transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.btn-primary { background: #667eea; color: white; }
.btn-primary:hover { background: #5a67d8; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3); }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3); }
.btn-warning { background: #f59e0b; color: white; }
.btn-warning:hover { background: #d97706; box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3); }
.alert { border-radius: 12px; padding: 16px; border: 1px solid #e2e8f0; font-size: 14px; }
.alert-success { background: #f0fdf4; border-color: #10b981; color: #059669; }
.alert-danger { background: #fef2f2; border-color: #ef4444; color: #dc2626; }
.alert-warning { background: #fffbeb; border-color: #f59e0b; color: #d97706; }
</style>

<div class="ai-container">
  <div class="ai-header">
    <h1 class="h2 mb-2"><?= t('title') ?></h1>
    <p class="mb-0 opacity-75"><?= t('subtitle') ?></p>
  </div>
  
  <?php if ($importId === null && !empty($imports)): ?>
    <div class="alert alert-warning mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <strong><?= t('select_data_import') ?></strong>
        <div class="btn-group" role="group">
          <a href="?lang=kz" class="btn btn-sm <?= $currentLang === 'kz' ? 'btn-primary' : 'btn-outline-primary' ?>">Қазақша</a>
          <a href="?lang=ru" class="btn btn-sm <?= $currentLang === 'ru' ? 'btn-primary' : 'btn-outline-primary' ?>">Русский</a>
          <a href="?lang=en" class="btn btn-sm <?= $currentLang === 'en' ? 'btn-primary' : 'btn-outline-primary' ?>">English</a>
        </div>
      </div>
      <form method="post" class="d-flex gap-2 mt-2">
        <?= csrf_input() ?>
        <select name="import_id" class="form-select" style="max-width: 400px;">
          <?php foreach ($imports as $imp): ?>
            <option value="<?= (int)$imp['import_id'] ?>"><?= h($imp['source_filename']) ?> (<?= (int)$imp['rows_imported'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-warning"><?= t('apply') ?></button>
      </form>
    </div>
  <?php endif; ?>
  
  <div class="ai-nav">
    <?php foreach ($sections as $key => $label): ?>
      <a href="?section=<?= $key ?>" class="ai-nav-btn <?= $section === $key ? 'active' : '' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
  
  <div id="section-overview" class="ai-section <?= $section === 'overview' ? 'active' : '' ?>">
    <div class="stats-grid">
      <?php
      $stats = ['questions' => 0, 'students' => 0, 'attempts' => 0, 'avg_score' => 0];
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
      ?>
      <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['questions'] ?: 0) ?></div>
        <div class="stat-label"><?= t('questions') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['students'] ?: 0) ?></div>
        <div class="stat-label"><?= t('students') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['attempts'] ?: 0) ?></div>
        <div class="stat-label"><?= t('attempts') ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['avg_score'] ?: 0, 1) ?></div>
        <div class="stat-label"><?= t('avg_score') ?></div>
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
                  <div style="font-weight: 600; color: #1a1d21; font-size: 13px; margin-bottom: 4px;"><?= t('guide_syllabus_import') ?></div>
                  <div style="color: #64748b; font-size: 12px; line-height: 1.4;"><?= t('guide_syllabus_import_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #10b981;"><i class="bi bi-bar-chart"></i></div>
                <div>
                  <div style="font-weight: 600; color: #1a1d21; font-size: 13px; margin-bottom: 4px;"><?= t('guide_exam_import') ?></div>
                  <div style="color: #64748b; font-size: 12px; line-height: 1.4;"><?= t('guide_exam_import_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #f59e0b;"><i class="bi bi-shuffle"></i></div>
                <div>
                  <div style="font-weight: 600; color: #1a1d21; font-size: 13px; margin-bottom: 4px;"><?= t('guide_sorting') ?></div>
                  <div style="color: #64748b; font-size: 12px; line-height: 1.4;"><?= t('guide_sorting_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #ef4444;"><i class="bi bi-graph-up"></i></div>
                <div>
                  <div style="font-weight: 600; color: #1a1d21; font-size: 13px; margin-bottom: 4px;"><?= t('guide_quality') ?></div>
                  <div style="color: #64748b; font-size: 12px; line-height: 1.4;"><?= t('guide_quality_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #8b5cf6;"><i class="bi bi-link"></i></div>
                <div>
                  <div style="font-weight: 600; color: #1a1d21; font-size: 13px; margin-bottom: 4px;"><?= t('guide_correlation') ?></div>
                  <div style="color: #64748b; font-size: 12px; line-height: 1.4;"><?= t('guide_correlation_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #06b6d4;"><i class="bi bi-people"></i></div>
                <div>
                  <div style="font-weight: 600; color: #1a1d21; font-size: 13px; margin-bottom: 4px;"><?= t('guide_students') ?></div>
                  <div style="color: #64748b; font-size: 12px; line-height: 1.4;"><?= t('guide_students_desc') ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4 margin-top-24">
      <div class="col-md-12">
        <div class="card h-100 import-stats-card">
          <div class="card-header"><?= t('import_statistics') ?></div>
          <div class="card-body">
            <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px;">
              <button onclick="showChart('quality')" id="btn-quality" class="chart-button active"><?= t('quality') ?></button>
              <button onclick="showChart('correlation')" id="btn-correlation" class="chart-button"><?= t('correlation') ?></button>
              <button onclick="showChart('students')" id="btn-students" class="chart-button"><?= t('students') ?></button>
              <button onclick="showChart('validation')" id="btn-validation" class="chart-button"><?= t('validation') ?></button>
              <button onclick="showChart('semantic')" id="btn-semantic" class="chart-button"><?= t('semantic') ?></button>
              <button onclick="showChart('criteria')" id="btn-criteria" class="chart-button"><?= t('criteria_analysis') ?></button>
              <button onclick="showChart('answers')" id="btn-answers" class="chart-button"><?= t('student_answers') ?></button>
            </div>
            <div id="chart-quality" style="display: block;">
              <h4 class="chart-title"><?= t('chart_quality_distribution') ?></h4>
              <div class="chart-container">
                <canvas id="qualityChart"></canvas>
              </div>
            </div>
            <div id="chart-correlation" style="display: block;">
              <h4 class="chart-title"><?= t('chart_correlation_distribution') ?></h4>
              <div class="chart-container">
                <canvas id="correlationChart"></canvas>
              </div>
            </div>
            <div id="chart-students" style="display: block;">
              <h4 class="chart-title"><?= t('chart_student_risk_patterns') ?></h4>
              <div class="chart-container">
                <canvas id="studentsChart"></canvas>
              </div>
            </div>
            <div id="chart-validation" style="display: block;">
              <h4 class="chart-title"><?= t('chart_validation_status') ?></h4>
              <div class="chart-container">
                <canvas id="validationChart"></canvas>
              </div>
            </div>
            <div id="chart-semantic" style="display: block;">
              <h4 class="chart-title"><?= t('chart_semantic_analysis') ?></h4>
              <div class="chart-container">
                <canvas id="semanticChart"></canvas>
              </div>
            </div>
            <div id="chart-criteria" style="display: block;">
              <h4 class="chart-title"><?= t('criteria_analysis') ?></h4>
              <div class="chart-container">
                <canvas id="criteriaChart"></canvas>
              </div>
            </div>
            <div id="chart-answers" style="display: block;">
              <h4 class="chart-title"><?= t('student_answers') ?></h4>
              <div class="chart-container">
                <canvas id="answersChart"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    </div>
  </div>
  
  <div id="section-import" class="ai-section <?= $section === 'import' ? 'active' : '' ?>">
    <div class="table-container">
    <?php
    $importError = null;
    $importSuccess = null;
    $importStats = [];
    
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_fail();
        
        if (isset($_FILES['syllabus_file'])) {
            $file = $_FILES['syllabus_file'];
            $disciplineName = trim($_POST['discipline_name'] ?? '');
            $courseNumber = (int)($_POST['course_number'] ?? 3);
            
            if (empty($disciplineName)) {
                $importError = 'Specify discipline name';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $importError = 'Upload error: ' . $file['error'];
            } else {
                try {
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if (!in_array($extension, ['docx', 'doc', 'pdf', 'txt', 'odt'])) {
                        throw new Exception('Supported formats: DOCX, DOC, PDF, TXT, ODT');
                    }
                    

                    $uploadDir = __DIR__ . '/uploads/syllabuses/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    
                    $fileName = 'syllabus_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
                    $filePath = $uploadDir . $fileName;
                    
                    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                        throw new Exception('Failed to save file');
                    }
                    

                    $text = extractDocumentText($filePath, $extension);
                    

                    $keywords = extractKeywords($text, $disciplineName);
                    

                    $topics = parseSyllabusTopics($text, $disciplineName, $courseNumber);
                    $questions = parseSyllabusQuestions($text);
                    
                    foreach ($questions as $q) {
                    }

                    $pdo->beginTransaction();
                    

                    $checkStmt = $pdo->prepare("
                        SELECT id FROM user_syllabuses 
                        WHERE discipline_name = :disc 
                        AND course_number = :course 
                        AND file_name = :fname 
                        AND uploaded_by = :uid
                    ");
                    $checkStmt->execute([
                        ':disc' => $disciplineName,
                        ':course' => $courseNumber,
                        ':fname' => $file['name'],
                        ':uid' => current_user()['user_id']
                    ]);
                    
                    if ($checkStmt->fetch()) {
                        throw new Exception('This syllabus has already been uploaded');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO user_syllabuses (discipline_name, course_number, file_name, file_path, file_type, text_content, uploaded_by, status)
                        VALUES (:disc, :course, :fname, :fpath, :ftype, :text, :uid, 'processed')
                    ");
                    $stmt->execute([
                        ':disc' => $disciplineName,
                        ':course' => $courseNumber,
                        ':fname' => $file['name'],
                        ':fpath' => 'uploads/syllabuses/' . $fileName,
                        ':ftype' => $extension,
                        ':text' => $text,
                        ':uid' => current_user()['user_id']
                    ]);
                    $syllabusId = (int)$pdo->lastInsertId();
                    

                    $stmtTopic = $pdo->prepare("
                        INSERT INTO ai_syllabus_topics (discipline_name, course_number, topic_code, title, keywords)
                        VALUES (:disc, :course, :code, :title, :keywords)
                    ");
                    
                    $checkTopicStmt = $pdo->prepare("
                        SELECT syllabus_topic_id FROM ai_syllabus_topics 
                        WHERE discipline_name = :disc 
                        AND course_number = :course 
                        AND title = :title
                        LIMIT 1
                    ");
                    
                    $savedTopics = 0;
                    foreach ($topics as $topic) {
                        try {
                            $checkTopicStmt->execute([
                                ':disc' => $disciplineName,
                                ':course' => $courseNumber,
                                ':title' => $topic['title']
                            ]);
                            $existing = $checkTopicStmt->fetch();
                            if ($existing) {
                                error_log("Topic already exists: " . $topic['title']);
                                continue;
                            }
                            
                            $stmtTopic->execute([
                                ':disc' => $disciplineName,
                                ':course' => $courseNumber,
                                ':code' => $topic['code'],
                                ':title' => $topic['title'],
                                ':keywords' => $topic['keywords']
                            ]);
                            if ($stmtTopic->rowCount() > 0) {
                                $savedTopics++;
                                error_log("Topic saved: " . $topic['title']);
                            }
                        } catch (Throwable $e) {
    error_log("Error saving topic: " . $e->getMessage());
}
                    }
                    
                    $savedQuestions = 0;
                    if (!empty($questions) && !empty($topics)) {
                        $firstTopicCode = $topics[0]['code'];
                        
                        $stmtTopicId = $pdo->prepare("
                            SELECT syllabus_topic_id FROM ai_syllabus_topics 
                            WHERE discipline_name = :disc 
                            AND course_number = :course 
                            AND topic_code = :code
                            LIMIT 1
                        ");
                        $stmtTopicId->execute([
                            ':disc' => $disciplineName,
                            ':course' => $courseNumber,
                            ':code' => $firstTopicCode
                        ]);
                        $topicId = $stmtTopicId->fetchColumn();
                        
                        if ($topicId) {
                            $stmtUpdateQuestion = $pdo->prepare("
                                UPDATE hier_questions 
                                SET question_text = :text, syllabus_topic_id = :topic_id
                                WHERE question_id = :qid
                            ");
                            
                            $stmtInsertQuestion = $pdo->prepare("
                                INSERT INTO hier_questions (question_text, max_score, question_type)
                                VALUES (:text, 100, 'single_choice')
                            ");
                            
                            foreach ($questions as $question) {
                                try {
                                    $questionId = $question['number'] ?? null;
                                    
                                    if ($questionId) {
                                        $stmtUpdateQuestion->execute([
                                            ':text' => $question['text'],
                                            ':topic_id' => $topicId,
                                            ':qid' => $questionId
                                        ]);
                                        if ($stmtUpdateQuestion->rowCount() > 0) {
                                            $savedQuestions++;
                                        } else {
                                            $stmtInsertQuestion->execute([
                                                ':text' => $question['text'],
                                                ':topic_id' => $topicId,
                                                ':disc' => $disciplineName
                                            ]);
                                            $savedQuestions++;
                                        }
                                    } else {
                                        $stmtInsertQuestion->execute([
                                            ':text' => $question['text'],
                                            ':topic_id' => $topicId,
                                            ':disc' => $disciplineName
                                        ]);
                                        $savedQuestions++;
                                    }
                                } catch (Throwable $e) {
    error_log("Error saving question: " . $e->getMessage());
}
                            }
                        }
                    }
                    
                    $pdo->commit();
                    
                    $importSuccess = "Syllabus uploaded! Topics saved: {$savedTopics}, Questions saved: {$savedQuestions}";
                    $importStats = [
                        'type' => 'syllabus',
                        'file_name' => $file['name'],
                        'extension' => $extension,
                        'text_length' => strlen($text),
                        'topics_found' => $savedTopics,
                        'questions_found' => $savedQuestions,
                        'keywords' => $keywords,
                    ];
                    
                } catch (Throwable $e) {
                    error_log("Syllabus upload error: " . $e->getMessage());
                    if ($pdo->inTransaction()) {
                        try { $pdo->rollBack(); } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}
                    }
                    $importError = 'Error: ' . $e->getMessage();
                }
            }
        }
        

        if (isset($_FILES['ods_file'])) {
            $file = $_FILES['ods_file'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                try {
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if ($extension === 'csv') {
                        $data = readCsvFile($file['tmp_name'], ',');
                    } else {
                        $data = readOdsFile($file['tmp_name']);
                    }
                    
                    if (empty($data)) {
                        throw new Exception('File is empty');
                    }
                    
                    $converted = 0;
                    
                    if ($extension === 'csv') {
                        $csvData = $data;
                        $csvFileName = $file['name'];
                        $converted = count($data) - 1;
                    } else {
                        $uploadDir = __DIR__ . '/uploads/';
                        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                        
                        $csvFileName = 'converted_' . date('Y-m-d_H-i-s') . '.csv';
                        $csvFilePath = $uploadDir . $csvFileName;
                        
                        $handle = fopen($csvFilePath, 'w');
                        if ($handle === false) {
                            throw new Exception('Failed to create file');
                        }
                        

                        fputcsv($handle, ['question_id', 'student_id', 'discipline_name', 'course_number', 'score_after_appeal', 'score_before_appeal', 'discipline_score'], ';');
                        

                        foreach ($data as $index => $row) {
                            if ($index === 0) continue;
                            
                            $questionId = $row['ID тематики'] ?? $row['ID вопроса'] ?? $row['ID fråga'] ?? $row['ID вопроса'] ?? '';
                            $studentId = $row['ID студента'] ?? $row['ID student'] ?? '';
                            $disciplineName = $row['Дисциплина'] ?? $row['Disciplin'] ?? '';
                            $courseNumber = $row['Курс'] ?? $row['Kurs'] ?? '';
                            $scoreAfter = $row['Оценка после апелляции'] ?? $row['Bedömning efter överklagande'] ?? '';
                            $scoreBefore = $row['Оценка до апелляции'] ?? $row['Bedömning före överklagande'] ?? '';
                            $scoreDiscipline = $row['Оценка за дисциплину'] ?? $row['Bedömning för disciplin'] ?? '';
                            

                            $finalScore = $scoreAfter !== '' && $scoreAfter !== '-' ? $scoreAfter : ($scoreBefore !== '' && $scoreBefore !== '-' ? $scoreBefore : $scoreDiscipline);
                            
                            fputcsv($handle, [$questionId, $studentId, $disciplineName, $courseNumber, $finalScore, $scoreBefore, $scoreDiscipline], ';');
                            $converted++;
                        }
                        
                        fclose($handle);
                        $csvData = readCsvFile($csvFilePath);
                    }
                    
                    $inserted = 0;
                    $disciplines = [];
                    
                    if (!empty($csvData)) {
                        $pdo->beginTransaction();

                        $stmtLog = $pdo->prepare("INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type) VALUES (:filename, :fmt, :total, 0, 'exam_results_upload')");
                        $stmtLog->execute([':filename' => $file['name'], ':fmt' => 'ods', ':total' => count($csvData)]);
                        $importId = (int)$pdo->lastInsertId();

                        $stmtInsert = $pdo->prepare("
                            INSERT INTO raw_exam_results (import_id, student_id, question_id, discipline_name, course_number, received_score, score_after_appeal, score_before_appeal, discipline_score)
                            VALUES (:import_id, :student_id, :question_id, :discipline_name, :course_number, :score1, :score2, :score3, :score4)
                        ");

                        $stmtQuestion = $pdo->prepare("INSERT IGNORE INTO questions (question_id, question_text, max_score, question_type) VALUES (:qid, :qtext, :max, 'single')");
                        $stmtStudent = $pdo->prepare("INSERT IGNORE INTO students (student_id, course_number) VALUES (:sid, :course)");
                        $stmtHierQuestion = $pdo->prepare("INSERT IGNORE INTO hier_questions (question_id, question_text, max_score, question_type) VALUES (:qid, :qtext, :max, 'single_choice')");
                        $stmtWorkMapping = $pdo->prepare("
                            INSERT IGNORE INTO work_mapping (student_id, question_id, discipline_name, course_number, import_id)
                            VALUES (:sid, :qid, :disc, :course, :import_id)
                        ");

                        foreach ($csvData as $index => $row) {
                            if ($index === 0) continue;

                            try {
                                $questionId = isset($row['ID вопроса']) && is_numeric($row['ID вопроса']) ? (int)$row['ID вопроса'] : (isset($row['question_id']) && is_numeric($row['question_id']) ? (int)$row['question_id'] : null);
                                $studentId = isset($row['ID студента']) && is_numeric($row['ID студента']) ? (int)$row['ID студента'] : (isset($row['student_id']) && is_numeric($row['student_id']) ? (int)$row['student_id'] : null);
                                $disciplineName = trim($row['Дисциплина'] ?? $row['discipline_name'] ?? '');
                                $courseNumber = isset($row['Курс']) && is_numeric($row['Курс']) ? (int)$row['Курс'] : (isset($row['course_number']) && is_numeric($row['course_number']) ? (int)$row['course_number'] : null);
                                
                                $scoreAfter = $row['Оценка после апелляции'] ?? $row['score_after_appeal'] ?? '';
                                $scoreBefore = $row['Оценка до апелляции'] ?? $row['score_before_appeal'] ?? '';
                                $scoreDiscipline = $row['Оценка за дисциплину'] ?? $row['discipline_score'] ?? '';
                                

                                $receivedScore = null;
                                if ($scoreBefore !== '' && $scoreBefore !== '-' && is_numeric($scoreBefore)) {
                                    $receivedScore = (float)$scoreBefore;
                                } elseif ($scoreAfter !== '' && $scoreAfter !== '-' && is_numeric($scoreAfter)) {
                                    $receivedScore = (float)$scoreAfter;
                                } elseif ($scoreDiscipline !== '' && $scoreDiscipline !== '-' && is_numeric($scoreDiscipline)) {
                                    $receivedScore = (float)$scoreDiscipline;
                                }

                                if (!empty($questionId)) {
                                    $stmtQuestion->execute([':qid' => $questionId, ':qtext' => '', ':max' => MAX_SCORE]);
                                    $stmtHierQuestion->execute([':qid' => $questionId, ':qtext' => '', ':max' => MAX_SCORE]);
                                }

                                if (!empty($studentId)) {
                                    $stmtStudent->execute([
                                        ':sid' => $studentId,
                                        ':course' => $courseNumber ?? DEFAULT_COURSE
                                    ]);
                                }

                                if ($questionId !== null || $studentId !== null) {
                                    $stmtInsert->execute([
                                        ':import_id' => $importId,
                                        ':student_id' => $studentId,
                                        ':question_id' => $questionId,
                                        ':discipline_name' => $disciplineName,
                                        ':course_number' => $courseNumber,
                                        ':score1' => $receivedScore,
                                        ':score2' => $receivedScore,
                                        ':score3' => $receivedScore,
                                        ':score4' => $receivedScore,
                                    ]);
                                    $inserted++;

                                    if ($studentId && $questionId) {
                                        $stmtWorkMapping->execute([
                                            ':sid' => $studentId,
                                            ':qid' => $questionId,
                                            ':disc' => $disciplineName,
                                            ':course' => $courseNumber ?? DEFAULT_COURSE,
                                            ':import_id' => $importId
                                        ]);
                                    }

                                    if ($disciplineName && !isset($disciplines[$disciplineName])) {
                                        $disciplines[$disciplineName] = true;
                                    }
                                }
                            } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}
                        }

                        $pdo->commit();

                        $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                        $stmt->execute([':i' => $inserted, ':id' => $importId]);


                        foreach ($disciplines as $disciplineName => $_) {
                            try {
                                $stmt = $pdo->prepare("INSERT IGNORE INTO ai_syllabus_topics (discipline_name, course_number, topic_code, title, keywords) VALUES (:disc, " . DEFAULT_COURSE . ", :code, :title, :keywords)");
                                $code = 'AUTO_' . md5($disciplineName);
                                $stmt->execute([
                                    ':disc' => $disciplineName,
                                    ':code' => $code,
                                    ':title' => $disciplineName,
                                    ':keywords' => mb_strtolower($disciplineName, 'UTF-8')
                                ]);
                            } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}
                        }
                    }
                    
                    $importSuccess = "ODS Converted and Imported! Records: {$inserted}, Disciplines: " . count($disciplines);
                    $importStats = [
                        'type' => 'ods',
                        'original_file' => $file['name'],
                        'converted_file' => $csvFileName,
                        'rows' => $converted,
                        'records_imported' => $inserted ?? 0,
                        'disciplines' => count($disciplines),
                        'download_url' => 'uploads/' . $csvFileName,
                    ];
                    
                } catch (Throwable $e) {
                    error_log("ODS import error: " . $e->getMessage());
                    $importError = 'Error: ' . $e->getMessage();
                }
            }
        }
        

        if (isset($_POST['auto_link_discipline'])) {
            $disciplineName = trim($_POST['auto_link_discipline']);
            $courseNumber = (int)($_POST['auto_link_course'] ?? 3);
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE hier_questions hq
                    INNER JOIN ai_syllabus_topics st ON st.discipline_name = :disc AND st.course_number = :course
                    SET hq.syllabus_topic_id = st.syllabus_topic_id
                    WHERE hq.syllabus_topic_id IS NULL
                ");
                $stmt->execute([':disc' => $disciplineName, ':course' => $courseNumber]);
                $linkedCount = $stmt->rowCount();
                $importSuccess = "Auto-linked {$linkedCount} questions to '{$disciplineName}' syllabus";
            } catch (Throwable $e) {
                error_log("Auto-link error: " . $e->getMessage());
                $importError = 'Error: ' . $e->getMessage();
            }
        }
        

        if (isset($_POST['link_selected_questions'])) {
            $syllabusTopicId = (int)($_POST['syllabus_topic_id'] ?? 0);
            $questionIds = $_POST['question_ids'] ?? [];
            
            if ($syllabusTopicId > 0 && !empty($questionIds)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE hier_questions 
                        SET syllabus_topic_id = :topic_id 
                        WHERE question_id = :qid
                    ");
                    $linkedCount = 0;
                    foreach ($questionIds as $qid) {
                        $stmt->execute([':topic_id' => $syllabusTopicId, ':qid' => (int)$qid]);
                        $linkedCount += $stmt->rowCount();
                    }
                    $importSuccess = "Linked {$linkedCount} questions to syllabus topic";
                } catch (Throwable $e) {
                    error_log("Link questions error: " . $e->getMessage());
                    $importError = 'Error: ' . $e->getMessage();
                }
            } else {
                $importError = 'Please select a syllabus topic and at least one question';
            }
        }
        

        if (isset($_POST['clear_data'])) {
            try {
                $pdo->beginTransaction();
                
                $clearOptions = $_POST['clear_options'] ?? [];
                $clearedItems = [];
                
                if (in_array('exam_results', $clearOptions)) {
                    $pdo->exec("DELETE FROM raw_exam_results");
                    $pdo->exec("DELETE FROM imports_log");
                    $clearedItems[] = t('exam_results');
                }
                
                if (in_array('syllabuses', $clearOptions)) {
                    $pdo->exec("DELETE FROM ai_syllabus_topics");
                    $pdo->exec("DELETE FROM user_syllabuses");
                    $clearedItems[] = t('syllabuses');
                }
                
                if (in_array('questions', $clearOptions)) {
                    $pdo->exec("DELETE FROM hier_questions");
                    $pdo->exec("DELETE FROM questions");
                    $pdo->exec("DELETE FROM ai_question_ai");
                    $clearedItems[] = t('questions');
                }
                
                if (in_array('students', $clearOptions)) {
                    $pdo->exec("DELETE FROM students");
                    $clearedItems[] = t('students');
                }
                
                if (in_array('evaluation_criteria', $clearOptions)) {
                    $pdo->exec("DELETE FROM evaluation_criteria");
                    $clearedItems[] = 'Evaluation Criteria';
                }
                
                if (in_array('evaluation_details', $clearOptions)) {
                    $pdo->exec("DELETE FROM evaluation_details");
                    $clearedItems[] = 'Evaluation Details';
                }
                
                if (in_array('student_answers', $clearOptions)) {
                    $pdo->exec("DELETE FROM student_answers");
                    $clearedItems[] = 'Student Answers';
                }
                
                if (in_array('work_mapping', $clearOptions)) {
                    $pdo->exec("DELETE FROM work_mapping");
                    $clearedItems[] = 'Work Mapping';
                }
                
                if (in_array('uploads', $clearOptions)) {

                    $uploadsDir = __DIR__ . '/uploads';
                    if (is_dir($uploadsDir)) {
                        $files = glob($uploadsDir . '/*');
                        foreach ($files as $file) {
                            if (is_file($file) && basename($file) !== '.gitkeep' && strpos(realpath($file), realpath($uploadsDir)) === 0) {
                                unlink($file);
                            }
                        }

                        $syllabusesDir = $uploadsDir . '/syllabuses';
                        if (is_dir($syllabusesDir)) {
                            $syllabusFiles = glob($syllabusesDir . '/*');
                            foreach ($syllabusFiles as $file) {
                                if (is_file($file) && strpos(realpath($file), realpath($syllabusesDir)) === 0) {
                                    unlink($file);
                                }
                            }
                        }
                    }
                    $clearedItems[] = t('uploaded_files');
                }
                
                if (empty($clearedItems)) {
                    $importError = t('select_items_to_clear');
                } else {
                    $pdo->commit();
                    $importSuccess = t('cleared_items') . ': ' . implode(', ', $clearedItems);
                    header('Location: ?section=import');
                    exit;
                }
            } catch (Throwable $e) {
                error_log("Clear data error: " . $e->getMessage());
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}
                }
                $importError = 'Error: ' . $e->getMessage();
            }
        }
    }
    

    try {
        $stmt = $pdo->query("
            SELECT * FROM user_syllabuses 
            ORDER BY uploaded_at DESC
            LIMIT 10
        ");
        $existingSyllabuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("Error fetching syllabuses: " . $e->getMessage());
        $existingSyllabuses = [];
    }
    ?>
    
    <?php if ($importSuccess): ?>
      <div class="alert alert-success"><?= h($importSuccess) ?></div>
      
      <?php if (!empty($importStats)): ?>
        <div class="stats-grid mb-4">
          <?php if ($importStats['type'] === 'syllabus'): ?>
            <div class="stat-card">
              <div class="stat-value"><?= h($importStats['file_name']) ?></div>
              <div class="stat-label">File</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= strtoupper(h($importStats['extension'])) ?></div>
              <div class="stat-label">Format</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= number_format($importStats['text_length']) ?></div>
              <div class="stat-label">Characters</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['topics_found'] ?></div>
              <div class="stat-label">Topics Found</div>
            </div>
          <?php else: ?>
            <div class="stat-card">
              <div class="stat-value"><?= h($importStats['original_file']) ?></div>
              <div class="stat-label">Original File</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['rows'] ?></div>
              <div class="stat-label">Rows Converted</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['records_imported'] ?></div>
              <div class="stat-label">Records Imported</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['disciplines'] ?></div>
              <div class="stat-label">Disciplines</div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($importError): ?>
      <div class="alert alert-danger"><?= h($importError) ?></div>
    <?php endif; ?>
    
    <div class="import-tabs">
      <button onclick="showImportTab('syllabus')" id="import-btn-syllabus" class="chart-button active"><?= t('syllabus_import') ?></button>
      <button onclick="showImportTab('ods')" id="import-btn-ods" class="chart-button"><?= t('ods_exam_results') ?></button>
      <button onclick="showImportTab('syllabus-link')" id="import-btn-syllabus-link" class="chart-button"><?= t('link_syllabus_questions') ?></button>
      <button onclick="showImportTab('duplicates')" id="import-btn-duplicates" class="chart-button"><?= t('duplicate_detection') ?></button>
      <button onclick="showImportTab('criteria')" id="import-btn-criteria" class="chart-button"><?= t('evaluation_criteria') ?></button>
      <button onclick="showImportTab('details')" id="import-btn-details" class="chart-button"><?= t('evaluation_details') ?></button>
      <button onclick="showImportTab('answers')" id="import-btn-answers" class="chart-button"><?= t('student_answers') ?></button>
    </div>

    <div class="import-layout">
      <div class="import-left">
        <div id="import-syllabus" style="display: block;">
          <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            
            <div class="import-upload-box" onclick="document.getElementById('syllabusFileInput').click()">
              <div class="import-upload-icon">
                <i class="bi bi-cloud-arrow-up"></i>
              </div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.DOCX, .PDF, .TXT, .ODT</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="syllabus_file" id="syllabusFileInput" accept=".docx,.doc,.pdf,.txt,.odt" required style="display: none;" onchange="updateUploadBox(this, 'syllabus')">
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
          <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            
            <div class="import-upload-box" onclick="document.getElementById('odsFileInput').click()">
              <div class="import-upload-icon">
                <i class="bi bi-file-earmark-spreadsheet"></i>
              </div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.ODS, .CSV</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="ods_file" id="odsFileInput" accept=".ods,.csv" required style="display: none;" onchange="updateUploadBox(this, 'ods')">
            </div>

            <button type="submit" class="import-submit-btn"><?= t('convert_import') ?></button>
          </form>
          
          <div class="text-muted small mt-4">
            <p><strong><?= t('converter_description') ?></strong></p>
            <ul class="mb-0">
              <li><?= t('converter_1') ?></li>
              <li><?= t('converter_2') ?></li>
              <li><?= t('converter_3') ?></li>
            </ul>
          </div>
        </div>

        <div id="import-syllabus-link" style="display: none;">
        <div class="alert alert-info">
          <strong><?= t('select_questions') ?></strong>
          <ul class="mb-0 mt-2">
            <li><?= t('select_questions_for_link') ?></li>
            <li><?= t('select_topic_from_dropdown') ?></li>
          </ul>
        </div>
        
        <?php

        $allQuestions = [];
        $totalQuestions = 0;
        $queryError = '';
        try {

            $stmtCount = $pdo->query("SELECT COUNT(*) as count FROM hier_questions");
            $totalQuestions = $stmtCount->fetch()['count'] ?? 0;
            

            $stmt = $pdo->query("
                SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id,
                       st.title as syllabus_title,
                       r.discipline_name
                FROM hier_questions hq
                LEFT JOIN raw_exam_results r ON r.question_id = hq.question_id
                LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
                GROUP BY hq.question_id
            ");
            $allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log("Error fetching questions: " . $e->getMessage());
            $queryError = $e->getMessage();
        }
        

        $syllabusTopics = [];
        try {
            $stmt = $pdo->query("
                SELECT DISTINCT st.syllabus_topic_id, st.discipline_name, st.course_number, st.title
                FROM ai_syllabus_topics st
                INNER JOIN user_syllabuses us ON 
                    us.discipline_name = st.discipline_name
                ORDER BY st.discipline_name, st.course_number, st.title
                LIMIT 100
            ");
            $syllabusTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}
        ?>
        
        <form method="post" class="mb-4">
          <?= csrf_input() ?>
          <input type="hidden" name="link_selected_questions" value="1">
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('select_syllabus_topic') ?> (<?= t('available_topics') ?>: <span id="topicCount"><?= count($syllabusTopics) ?></span>)</label>
            <div style="display: flex; gap: 8px;">
              <select name="syllabus_topic_id" class="form-select" required id="syllabusTopicSelect">
                <option value="">-- <?= t('select_syllabus_topic') ?> --</option>
                <?php foreach ($syllabusTopics as $topic): ?>
                  <option value="<?= (int)$topic['syllabus_topic_id'] ?>">
                    <?= h($topic['discipline_name']) ?> - <?= h($topic['course_number']) ?>: <?= h($topic['title']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="btn btn-outline-secondary" onclick="refreshSyllabusTopics()" title="<?= t('refresh') ?>">
                <i class="bi bi-arrow-clockwise"></i>
              </button>
            </div>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('all_questions') ?> (<?= t('total') ?>: <?= $totalQuestions ?>, <?= t('shown') ?>: <?= count($allQuestions) ?>)</label>
            <input type="text" id="questionSearch" class="form-control mb-2" placeholder="<?= t('search_placeholder') ?>" onkeyup="filterQuestions()">
            <?php if ($queryError): ?>
              <div class="alert alert-danger"><?= t('query_error') ?> <?= h($queryError) ?></div>
            <?php endif; ?>
            <?php if (empty($allQuestions)): ?>
              <p class="text-muted"><?= t('no_questions_db') ?></p>
            <?php else: ?>
              <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover" id="questionsTable">
                  <thead>
                    <tr>
                      <th style="width: 50px;"><input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)"></th>
                      <th class="sortable" data-sort="id" style="cursor: pointer;">ID ⬍</th>
                      <th class="sortable" data-sort="question" style="cursor: pointer;">Question ⬍</th>
                      <th class="sortable" data-sort="discipline" style="cursor: pointer;">Discipline ⬍</th>
                      <th class="sortable" data-sort="topic" style="cursor: pointer;">Current Topic ⬍</th>
                    </tr>
                  </thead>
                  <tbody id="questionsTableBody">
                    <?php foreach ($allQuestions as $q): ?>
                      <tr data-id="<?= (int)$q['question_id'] ?>" data-question="<?= h(mb_strtolower($q['question_text'] ?? '')) ?>" data-discipline="<?= h(mb_strtolower($q['discipline_name'] ?? '')) ?>" data-topic="<?= h(mb_strtolower($q['syllabus_title'] ?? '')) ?>">
                        <td style="text-align: center;"><input type="checkbox" name="question_ids[]" value="<?= (int)$q['question_id'] ?>" class="question-checkbox" style="transform: scale(1.2); cursor: pointer;"></td>
                        <td><?= (int)$q['question_id'] ?></td>
                        <td><?= h(mb_substr($q['question_text'] ?? '', 0, 80)) ?></td>
                        <td><?= h($q['discipline_name'] ?? '') ?></td>
                        <td><?= $q['syllabus_title'] ? h($q['syllabus_title']) : '<span class="text-muted">' . t('not_linked') . '</span>' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          
          <button type="submit" class="btn btn-primary" <?= empty($allQuestions) ? 'disabled' : '' ?>><?= t('link_selected') ?></button>
        </form>
      </div>
      
      <div class="tab-pane fade" id="duplicates" role="tabpanel">
        <form method="post" class="mb-4">
          <?= csrf_input() ?>
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('similarity_threshold') ?></label>
            <input type="number" step="0.01" min="0" max="1" name="dedup_threshold" class="form-control" value="<?= DEFAULT_DEDUP_THRESHOLD ?>">
            <small class="text-muted"><?= t('similarity_desc') ?></small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= t('detection_method') ?></label>
            <select name="dedup_method" class="form-select">
              <option value="embeddings"><?= t('embeddings') ?></option>
              <option value="tfidf"><?= t('tfidf') ?></option>
              <option value="hybrid"><?= t('hybrid') ?></option>
            </select>
          </div>
          
          <button type="submit" class="btn btn-primary"><?= t('detect_duplicates') ?></button>
        </form>
        
        <?php if ($importId !== null): ?>
          <div class="mt-4">
            <h6 class="fw-semibold mb-3"><?= t('duplicate_results') ?></h6>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th><?= t('q1') ?></th><th><?= t('q2') ?></th><th><?= t('similarity') ?></th><th><?= t('method') ?></th></tr></thead>
                <tbody>
                  <tr><td colspan="4" class="text-muted"><?= t('run_detection') ?></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
    </div>
    
    <div id="import-duplicates" style="display: none;">
        <div class="alert alert-info">
          <strong><?= t('duplicate_detection') ?></strong>
          <p class="mb-0 mt-2"><?= t('run_detection') ?></p>
        </div>
        
        <div class="mb-3">
          <h6 class="fw-semibold mb-3"><?= t('duplicate_results') ?></h6>
          <div class="table-responsive">
            <table class="table table-sm">
              <thead><tr><th><?= t('q1') ?></th><th><?= t('q2') ?></th><th><?= t('similarity') ?></th><th><?= t('method') ?></th></tr></thead>
              <tbody>
                <tr><td colspan="4" class="text-muted"><?= t('run_detection') ?></td></tr>
              </tbody>
            </table>
          </div>
        </div>
    </div>
    
    <div id="import-criteria" style="display: none;">
        <form method="post" enctype="multipart/form-data" action="imports/import_evaluation_criteria.php">
          <?= csrf_input() ?>
          <div class="import-upload-box" onclick="document.getElementById('criteriaFileInput').click()">
            <div class="import-upload-icon">
              <i class="bi bi-file-earmark-text"></i>
            </div>
            <p class="import-upload-text">Drag & Drop files here...</p>
            <p class="import-upload-subtext">.CSV (ID, Қазақша, Русский, English, Вес)</p>
            <button type="button" class="import-browse-btn">Or browse files</button>
            <input type="file" name="criteria_file" id="criteriaFileInput" accept=".csv" required style="display: none;" onchange="updateUploadBox(this, 'criteria')">
          </div>
          <button type="submit" class="import-submit-btn"><?= t('import_criteria') ?></button>
        </form>
    </div>
    
    <div id="import-details" style="display: none;">
        <form method="post" enctype="multipart/form-data" action="imports/import_evaluation_details.php">
          <?= csrf_input() ?>
          <div class="import-upload-box" onclick="document.getElementById('detailsFileInput').click()">
            <div class="import-upload-icon">
              <i class="bi bi-file-earmark-bar-graph"></i>
            </div>
            <p class="import-upload-text">Drag & Drop files here...</p>
            <p class="import-upload-subtext">.CSV (Код работы, ID критерия, Оценка)</p>
            <button type="button" class="import-browse-btn">Or browse files</button>
            <input type="file" name="details_file" id="detailsFileInput" accept=".csv" required style="display: none;" onchange="updateUploadBox(this, 'details')">
          </div>
          <button type="submit" class="import-submit-btn"><?= t('import_details') ?></button>
        </form>
    </div>
    
    <div id="import-answers" style="display: none;">
        <form method="post" enctype="multipart/form-data" action="imports/import_student_answers.php">
          <?= csrf_input() ?>
          <div class="import-upload-box" onclick="document.getElementById('answersFileInput').click()">
            <div class="import-upload-icon">
              <i class="bi bi-file-earmark-person"></i>
            </div>
            <p class="import-upload-text">Drag & Drop files here...</p>
            <p class="import-upload-subtext">.CSV (Код работы, Язык, Вопрос, Ответ и др.)</p>
            <button type="button" class="import-browse-btn">Or browse files</button>
            <input type="file" name="answers_file" id="answersFileInput" accept=".csv" required style="display: none;" onchange="updateUploadBox(this, 'answers')">
          </div>
          <button type="submit" class="import-submit-btn"><?= t('import_answers') ?></button>
        </form>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-md-4">
        <div class="stats-card">
          <div class="stats-card-header">
            <i class="bi bi-bar-chart-line"></i>
            <?= t('import_statistics') ?>
          </div>
          <div class="stats-card-body">
            <?php
            try {
              $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_syllabuses");
              $totalSyllabuses = $stmt->fetch()['count'] ?? 0;
              $stmt = $pdo->query("SELECT COUNT(DISTINCT discipline_name) as count FROM ai_syllabus_topics");
              $totalDisciplines = $stmt->fetch()['count'] ?? 0;
              $stmt = $pdo->query("SELECT COUNT(*) as count FROM imports_log WHERE import_type = 'exam_results_upload'");
              $totalImports = $stmt->fetch()['count'] ?? 0;
            } catch (Throwable $e) {
                error_log("Error fetching stats: " . $e->getMessage());
                $totalSyllabuses = 0;
                $totalDisciplines = 0;
                $totalImports = 0;
            }
            ?>
            <div class="stats-items">
              <div class="stat-item-modern stat-item-syllabuses">
                <div class="stat-item-icon">
                  <i class="bi bi-file-earmark-text"></i>
                </div>
                <div class="stat-item-content">
                  <div class="stat-item-value"><?= $totalSyllabuses ?></div>
                  <div class="stat-item-label"><?= t('syllabuses') ?></div>
                </div>
              </div>
              <div class="stat-item-modern stat-item-disciplines">
                <div class="stat-item-icon">
                  <i class="bi bi-book"></i>
                </div>
                <div class="stat-item-content">
                  <div class="stat-item-value"><?= $totalDisciplines ?></div>
                  <div class="stat-item-label"><?= t('disciplines') ?></div>
                </div>
              </div>
              <div class="stat-item-modern stat-item-imports">
                <div class="stat-item-icon">
                  <i class="bi bi-download"></i>
                </div>
                <div class="stat-item-content">
                  <div class="stat-item-value"><?= $totalImports ?></div>
                  <div class="stat-item-label"><?= t('data_imports') ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="recent-files-card">
          <div class="recent-files-header">
            <i class="bi bi-clock-history"></i>
            <?= t('recent_files') ?>
          </div>
          <div class="recent-files-body">
            <?php if (empty($existingSyllabuses)): ?>
              <div class="recent-files-empty">
                <i class="bi bi-inbox"></i>
                <p><?= t('no_syllabuses') ?></p>
              </div>
            <?php else: ?>
              <div class="recent-files-list">
                <?php foreach ($existingSyllabuses as $syl): ?>
                  <div class="recent-file-item">
                    <div class="recent-file-icon">
                      <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div class="recent-file-info">
                      <div class="recent-file-name"><?= h($syl['discipline_name']) ?></div>
                      <div class="recent-file-filename"><?= h($syl['file_name']) ?></div>
                    </div>
                    <div class="recent-file-status">
                      <span class="status-badge status-<?= $syl['status'] === 'processed' ? 'success' : 'warning' ?>">
                        <i class="bi bi-<?= $syl['status'] === 'processed' ? 'check-circle' : 'clock' ?>"></i>
                        <?= h($syl['status']) ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="clear-data-card">
          <div class="clear-data-header">
            <i class="bi bi-trash3"></i>
            <?= t('clear_data') ?>
          </div>
          <div class="clear-data-body">
            <p class="clear-data-desc"><?= t('clear_data_desc') ?></p>
            <form method="post" id="clearDataFormBottom">
              <?= csrf_input() ?>
              <input type="hidden" name="clear_data" value="1">
              <button type="button" id="clearDataSelectAll" class="clear-data-select-all-btn">
                <i class="bi bi-check-all"></i>
                <?= t('select_all') ?>
              </button>
              <div class="clear-data-options">
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="exam_results" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('exam_results') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="syllabuses" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('syllabuses') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="questions" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('questions') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="students" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('students') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="evaluation_criteria" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('evaluation_criteria') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="evaluation_details" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('evaluation_details') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="student_answers" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('student_answers') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="work_mapping" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('work_mapping') ?></span>
                </label>
                <label class="clear-data-option">
                  <input type="checkbox" name="clear_options[]" value="uploads" class="clear-data-checkbox">
                  <span class="clear-data-option-text"><?= t('uploaded_files') ?></span>
                </label>
              </div>
              <button type="button" class="clear-data-submit" onclick="showClearConfirm(); return false;">
                <i class="bi bi-trash3"></i> <?= t('clear_selected') ?>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
  
  <div id="section-validation" class="ai-section <?= $section === 'validation' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('validation') ?></h3>
      <p class="text-muted small mb-3"><?= t('validation_desc') ?></p>
      <?php if ($importId !== null): ?>
        <div class="mb-4">
            <canvas id="validationChartTab" style="max-height: 300px;"></canvas>
        </div>
      <?php endif; ?>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['validation_page']) ? max(1, (int)$_GET['validation_page']) : 1;
        $perPage = PER_PAGE;

        $sortColumn = $_GET['validation_sort'] ?? null;
        $sortDirection = $_GET['validation_dir'] ?? null;

        $questions = $ai->getQuestionAnalysisList();
        $validationData = [];
        if (!empty($questions)) {
            foreach ($questions as $q):
                $v = $ai->validateQuestionSyllabusAlignment((int)$q['question_id']);
                if ($v):
                    $statusClass = $v['status'] === 'ok' ? 'success' : 'warning';
                    $validationData[] = [
                        'question_id' => (int)$v['question_id'],
                        'syllabus_title' => $v['syllabus_title'],
                        'avg_score' => $v['avg_score'],
                        'attempts' => $v['attempts'],
                        'status_class' => $statusClass,
                        'status' => $v['status'],
                        'issues' => implode(', ', $v['issues'])
                    ];
                endif;
            endforeach;
        }

        if ($sortColumn && $sortDirection) {
            $validColumns = ['question_id', 'syllabus_title', 'avg_score', 'attempts', 'status'];
            if (in_array($sortColumn, $validColumns)) {
                $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
                usort($validationData, function($a, $b) use ($sortColumn, $direction) {
                    $valA = $a[$sortColumn];
                    $valB = $b[$sortColumn];
                    if ($valA == $valB) return 0;
                    return ($valA < $valB ? -1 : 1) * $direction;
                });
            }
        }

        $totalItems = count($validationData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($validationData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr>
              <th><a href="?section=validation&validation_sort=question_id&validation_dir=<?= ($sortColumn === 'question_id' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_id') ?> <span class="sort-icon <?= ($sortColumn === 'question_id') ? 'active' : '' ?>"><?= ($sortColumn === 'question_id') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=validation&validation_sort=syllabus_title&validation_dir=<?= ($sortColumn === 'syllabus_title' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_topic') ?> <span class="sort-icon <?= ($sortColumn === 'syllabus_title') ? 'active' : '' ?>"><?= ($sortColumn === 'syllabus_title') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=validation&validation_sort=avg_score&validation_dir=<?= ($sortColumn === 'avg_score' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_avg_score') ?> <span class="sort-icon <?= ($sortColumn === 'avg_score') ? 'active' : '' ?>"><?= ($sortColumn === 'avg_score') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=validation&validation_sort=attempts&validation_dir=<?= ($sortColumn === 'attempts' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_attempts') ?> <span class="sort-icon <?= ($sortColumn === 'attempts') ? 'active' : '' ?>"><?= ($sortColumn === 'attempts') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=validation&validation_sort=status&validation_dir=<?= ($sortColumn === 'status' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_status') ?> <span class="sort-icon <?= ($sortColumn === 'status') ? 'active' : '' ?>"><?= ($sortColumn === 'status') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><?= t('th_issues') ?></th>
            </tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="6" class="text-muted">' . t('no_data') . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= h($row['syllabus_title']) ?></td>
                  <td><?= $row['avg_score'] ?></td>
                  <td><?= $row['attempts'] ?></td>
                  <td><span class="badge badge-soft <?= $row['status_class'] ?>"><?= $row['status'] ?></span></td>
                  <td><?= $row['issues'] ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination('validation', 'validation_page', $page, $totalPages, $sortColumn, $sortDirection, $totalItems) ?>
      <?php else: ?>
        <p class="text-muted"><?= t('select_import') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-quality" class="ai-section <?= $section === 'quality' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('quality') ?></h3>
      <p class="text-muted small mb-3"><?= t('quality_desc') ?></p>

      <?php if ($importId !== null): ?>
        <div class="mb-4">
            <canvas id="qualityChartTab" style="max-height: 300px;"></canvas>
        </div>
      <?php endif; ?>

      <?php if ($importId !== null): ?>
        <?php
        $reliabilityMetrics = ['cronbach_alpha' => 0, 'avg_inter_item_correlation' => 0, 'question_count' => 0, 'interpretation' => 'Нет данных'];
        try {
            $reliabilityMetrics = $ai->getExamReliabilityMetrics();
        } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}
        ?>
        <?php if ($reliabilityMetrics['question_count'] >= 2): ?>
          <div class="card mb-4 border-primary">
            <div class="card-header bg-primary text-white">
              <strong>Надежность экзамена</strong>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-md-3 text-center">
                  <div class="h2 mb-1"><?= $reliabilityMetrics['cronbach_alpha'] ?></div>
                  <small class="text-muted">Cronbach's α</small>
                </div>
                <div class="col-md-3 text-center">
                  <div class="h2 mb-1"><?= $reliabilityMetrics['avg_inter_item_correlation'] ?></div>
                  <small class="text-muted">Ср. корреляция</small>
                </div>
                <div class="col-md-3 text-center">
                  <div class="h2 mb-1"><?= $reliabilityMetrics['question_count'] ?></div>
                  <small class="text-muted">Вопросов</small>
                </div>
                <div class="col-md-3 text-center">
                  <span class="badge badge-soft <?= $reliabilityMetrics['cronbach_alpha'] >= 0.8 ? 'success' : ($reliabilityMetrics['cronbach_alpha'] >= 0.7 ? 'warning' : 'danger') ?> fs-6">
                    <?= $reliabilityMetrics['interpretation'] ?>
                  </span>
                </div>
              </div>
              <p class="text-muted small mt-3 mb-0">
                Cronbach's α ≥ 0.9 — отлично, ≥ 0.8 — хорошо, ≥ 0.7 — приемлемо, < 0.6 — требуется ревизия вопросов
              </p>
            </div>
          </div>
        <?php endif; ?>
        <?php
        $page = isset($_GET['quality_page']) ? max(1, (int)$_GET['quality_page']) : 1;
        $perPage = PER_PAGE;

        $sortColumn = $_GET['quality_sort'] ?? null;
        $sortDirection = $_GET['quality_dir'] ?? null;

        $disciplineMap = [];
        try {
            $stmtDisc = $pdo->query("
                SELECT DISTINCT question_id, discipline_name
                FROM raw_exam_results
            ");
            while ($row = $stmtDisc->fetch()) {
                $disciplineMap[$row['question_id']] = $row['discipline_name'];
            }
        } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}

        $metrics = $ai->getQuestionQualityMetrics($sortColumn, $sortDirection);

        $disc = [];
        try {
            $disc = $ai->getDiscriminationIndex();
        } catch (Throwable $e) {
    error_log("Error: " . $e->getMessage());
}

        $dmap = [];
        foreach ($disc as $d) { $dmap[(int)$d['question_id']] = $d; }

        $qualityData = [];
        if (!empty($metrics)) {
            foreach ($metrics as $m):
                $discData = $dmap[$m['question_id']] ?? null;
                $flagClass = $m['flag'] === 'normal' ? 'success' : ($m['flag'] === 'too_easy' ? 'warning' : 'danger');
                $flagText = $m['flag'] === 'normal' ? 'Норма' : ($m['flag'] === 'too_easy' ? 'Легкий' : 'Сложный');
                $qualityData[] = [
                    'question_id' => $m['question_id'],
                    'discipline' => $disciplineMap[$m['question_id']] ?? '',
                    'n' => $m['n'],
                    'mean_score' => $m['mean_score'],
                    'p_correct_proxy' => $m['p_correct_proxy'],
                    'difficulty_pct' => $m['difficulty_pct'],
                    'flag_class' => $flagClass,
                    'flag_text' => $flagText,
                    'discrimination' => $discData ? $discData['discrimination'] . ' (' . $discData['label'] . ')' : '—',
                    'reason' => $m['reason']
                ];
            endforeach;
        }

        if ($sortColumn && $sortDirection) {
            $validColumns = ['discipline', 'flag_text', 'discrimination', 'reason'];
            if (in_array($sortColumn, $validColumns)) {
                $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
                usort($qualityData, function($a, $b) use ($sortColumn, $direction) {
                    $valA = $a[$sortColumn];
                    $valB = $b[$sortColumn];

                    if ($sortColumn === 'flag_text') {
                        $flagOrder = ['Сложный' => 3, 'Легкий' => 2, 'Норма' => 1];
                        $orderA = $flagOrder[$valA] ?? 0;
                        $orderB = $flagOrder[$valB] ?? 0;
                        if ($orderA == $orderB) return 0;
                        return ($orderA < $orderB ? -1 : 1) * $direction;
                    }

                    if ($valA == $valB) return 0;
                    return ($valA < $valB ? -1 : 1) * $direction;
                });
            }
        }

        $totalItems = count($qualityData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($qualityData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr>
              <th><a href="?section=quality&quality_sort=question_id&quality_dir=<?= ($sortColumn === 'question_id' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_id') ?> <span class="sort-icon <?= ($sortColumn === 'question_id') ? 'active' : '' ?>"><?= ($sortColumn === 'question_id') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=discipline&quality_dir=<?= ($sortColumn === 'discipline' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>">Discipline <span class="sort-icon <?= ($sortColumn === 'discipline') ? 'active' : '' ?>"><?= ($sortColumn === 'discipline') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=n&quality_dir=<?= ($sortColumn === 'n' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_n') ?> <span class="sort-icon <?= ($sortColumn === 'n') ? 'active' : '' ?>"><?= ($sortColumn === 'n') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=mean_score&quality_dir=<?= ($sortColumn === 'mean_score' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_avg') ?> <span class="sort-icon <?= ($sortColumn === 'mean_score') ? 'active' : '' ?>"><?= ($sortColumn === 'mean_score') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=p_correct_proxy&quality_dir=<?= ($sortColumn === 'p_correct_proxy' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_success_pct') ?> <span class="sort-icon <?= ($sortColumn === 'p_correct_proxy') ? 'active' : '' ?>"><?= ($sortColumn === 'p_correct_proxy') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=difficulty_pct&quality_dir=<?= ($sortColumn === 'difficulty_pct' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_difficulty_pct') ?> <span class="sort-icon <?= ($sortColumn === 'difficulty_pct') ? 'active' : '' ?>"><?= ($sortColumn === 'difficulty_pct') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=flag_text&quality_dir=<?= ($sortColumn === 'flag_text' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_flag') ?> <span class="sort-icon <?= ($sortColumn === 'flag_text') ? 'active' : '' ?>"><?= ($sortColumn === 'flag_text') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=discrimination&quality_dir=<?= ($sortColumn === 'discrimination' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_discrimination') ?> <span class="sort-icon <?= ($sortColumn === 'discrimination') ? 'active' : '' ?>"><?= ($sortColumn === 'discrimination') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=quality&quality_sort=reason&quality_dir=<?= ($sortColumn === 'reason' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_reason') ?> <span class="sort-icon <?= ($sortColumn === 'reason') ? 'active' : '' ?>"><?= ($sortColumn === 'reason') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
            </tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="9" class="text-muted">' . t('no_data') . '</td></tr>';
              else:
                foreach ($pagedData as $row):
                  $discData = $dmap[$row['question_id']] ?? null;
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= h($row['discipline']) ?></td>
                  <td><?= $row['n'] ?></td>
                  <td><?= $row['mean_score'] ?></td>
                  <td><?= $row['p_correct_proxy'] ?></td>
                  <td><?= $row['difficulty_pct'] ?>%</td>
                  <td><span class="badge badge-soft <?= $row['flag_class'] ?>"><?= $row['flag_text'] ?></span></td>
                  <td><?= $row['discrimination'] ?></td>
                  <td><?= h($row['reason']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination('quality', 'quality_page', $page, $totalPages, $sortColumn, $sortDirection, $totalItems) ?>
      <?php else: ?>
        <p class="text-muted"><?= t('select_import') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-correlation" class="ai-section <?= $section === 'correlation' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('correlation') ?></h3>
      <p class="text-muted small mb-3"><?= t('correlation_desc') ?></p>
      <?php if ($importId !== null): ?>
        <div class="mb-4">
            <canvas id="correlationChartTab" style="max-height: 300px;"></canvas>
        </div>
      <?php endif; ?>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['correlation_page']) ? max(1, (int)$_GET['correlation_page']) : 1;
        $perPage = PER_PAGE;
        $offset = ($page - 1) * $perPage;

        $sortColumn = $_GET['correlation_sort'] ?? null;
        $sortDirection = $_GET['correlation_dir'] ?? null;

        $corr = $ai->getItemTotalCorrelation($sortColumn, $sortDirection);
        $correlationData = [];
        if (!empty($corr)) {
            foreach ($corr as $c):
                $flagClass = $c['flag'] === 'ok' ? 'success' : 'warning';
                $correlationData[] = [
                    'question_id' => $c['question_id'],
                    'r' => $c['r'],
                    'flag_class' => $flagClass,
                    'flag' => $c['flag']
                ];
            endforeach;
        }

        if ($sortColumn && $sortDirection && $sortColumn === 'flag') {
            $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
            usort($correlationData, function($a, $b) use ($direction) {
                $valA = $a['flag'];
                $valB = $b['flag'];
                if ($valA == $valB) return 0;
                return ($valA < $valB ? -1 : 1) * $direction;
            });
        }

        $totalItems = count($correlationData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($correlationData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr>
              <th><a href="?section=correlation&correlation_sort=question_id&correlation_dir=<?= ($sortColumn === 'question_id' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_id') ?> <span class="sort-icon <?= ($sortColumn === 'question_id') ? 'active' : '' ?>"><?= ($sortColumn === 'question_id') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=correlation&correlation_sort=r&correlation_dir=<?= ($sortColumn === 'r' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_coefficient') ?> <span class="sort-icon <?= ($sortColumn === 'r') ? 'active' : '' ?>"><?= ($sortColumn === 'r') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=correlation&correlation_sort=flag&correlation_dir=<?= ($sortColumn === 'flag' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_flag') ?> <span class="sort-icon <?= ($sortColumn === 'flag') ? 'active' : '' ?>"><?= ($sortColumn === 'flag') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
            </tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="3" class="text-muted">' . t('no_data') . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= $row['r'] ?></td>
                  <td><span class="badge badge-soft <?= $row['flag_class'] ?>"><?= $row['flag'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination('correlation', 'correlation_page', $page, $totalPages, $sortColumn, $sortDirection, $totalItems) ?>
      <?php else: ?>
        <p class="text-muted"><?= t('select_import') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-students" class="ai-section <?= $section === 'students' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('students') ?></h3>
      <p class="text-muted small mb-3"><?= t('students_desc') ?></p>
      <?php if ($importId !== null): ?>
        <div class="mb-4">
            <canvas id="studentsChartTab" style="max-height: 300px;"></canvas>
        </div>
      <?php endif; ?>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['students_page']) ? max(1, (int)$_GET['students_page']) : 1;
        $perPage = PER_PAGE;

        $sortColumn = $_GET['students_sort'] ?? null;
        $sortDirection = $_GET['students_dir'] ?? null;

        $totalItems = $ai->getStudentRiskPatternsCount();
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));

        $patterns = $ai->getStudentRiskPatterns($page, $perPage, $sortColumn, $sortDirection);
        $studentsData = [];
        if (!empty($patterns)) {
            foreach ($patterns as $p):
                $typeClass = $p['pattern_type'] === 'ok' ? 'success' : ($p['pattern_type'] === 'systemic' ? 'danger' : 'warning');
                $studentsData[] = [
                    'student_id' => $p['student_id'],
                    'avg_item' => $p['avg_item'],
                    'min_item' => $p['min_item'],
                    'discipline_total' => $p['discipline_total'],
                    'type_class' => $typeClass,
                    'pattern_type' => $p['pattern_type'],
                    'notes' => implode(', ', $p['notes'])
                ];
            endforeach;
        }
        ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr>
              <th><a href="?section=students&students_sort=student_id&students_dir=<?= ($sortColumn === 'student_id' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_id') ?> <span class="sort-icon <?= ($sortColumn === 'student_id') ? 'active' : '' ?>"><?= ($sortColumn === 'student_id') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=students&students_sort=avg_item&students_dir=<?= ($sortColumn === 'avg_item' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_avg') ?> <span class="sort-icon <?= ($sortColumn === 'avg_item') ? 'active' : '' ?>"><?= ($sortColumn === 'avg_item') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=students&students_sort=min_item&students_dir=<?= ($sortColumn === 'min_item' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_min') ?> <span class="sort-icon <?= ($sortColumn === 'min_item') ? 'active' : '' ?>"><?= ($sortColumn === 'min_item') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=students&students_sort=discipline_total&students_dir=<?= ($sortColumn === 'discipline_total' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_total') ?> <span class="sort-icon <?= ($sortColumn === 'discipline_total') ? 'active' : '' ?>"><?= ($sortColumn === 'discipline_total') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=students&students_sort=pattern_type&students_dir=<?= ($sortColumn === 'pattern_type' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_type') ?> <span class="sort-icon <?= ($sortColumn === 'pattern_type') ? 'active' : '' ?>"><?= ($sortColumn === 'pattern_type') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=students&students_sort=notes&students_dir=<?= ($sortColumn === 'notes' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_notes') ?> <span class="sort-icon <?= ($sortColumn === 'notes') ? 'active' : '' ?>"><?= ($sortColumn === 'notes') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
            </tr></thead>
            <tbody>
              <?php if (empty($studentsData)): echo '<tr><td colspan="6" class="text-muted">' . t('no_data') . '</td></tr>';
              else:
                foreach ($studentsData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['student_id'] ?></strong></td>
                  <td><?= $row['avg_item'] ?></td>
                  <td><?= $row['min_item'] ?></td>
                  <td><?= $row['discipline_total'] ?></td>
                  <td><span class="badge badge-soft <?= $row['type_class'] ?>"><?= $row['pattern_type'] ?></span></td>
                  <td><?= $row['notes'] ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination('students', 'students_page', $page, $totalPages, $sortColumn, $sortDirection, $totalItems) ?>
      <?php else: ?>
        <p class="text-muted"><?= t('select_import') ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-semantic" class="ai-section <?= $section === 'semantic' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('semantic_analysis') ?></h3>
      <p class="text-muted small mb-3"><?= t('semantic_desc') ?? 'Semantic analysis of questions using embeddings and vector representations' ?></p>
      <?php if ($importId !== null): ?>
        <div class="mb-4">
            <canvas id="semanticChartTab" style="max-height: 300px;"></canvas>
        </div>
      <?php endif; ?>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['semantic_page']) ? max(1, (int)$_GET['semantic_page']) : 1;
        $perPage = PER_PAGE;
        $offset = ($page - 1) * $perPage;

        $sortColumn = $_GET['semantic_sort'] ?? null;
        $sortDirection = $_GET['semantic_dir'] ?? null;

        $semantic = $tfidf->analyzeQuestionSyllabusAlignment();
        $semanticData = [];
        if (!empty($semantic)) {
            foreach ($semantic as $s):
                $statusClass = $s['alignment_analysis']['alignment_level'] === 'high' ? 'success' : ($s['alignment_analysis']['alignment_level'] === 'medium' ? 'warning' : 'danger');
                $semanticData[] = [
                    'question_id' => $s['question_id'],
                    'syllabus_title' => $s['syllabus_title'],
                    'discipline_name' => $s['discipline_name'],
                    'average_score' => $s['alignment_analysis']['syllabus_context']['average_score'],
                    'status_class' => $statusClass,
                    'alignment_level' => $s['alignment_analysis']['alignment_level']
                ];
            endforeach;
        }

        if ($sortColumn && $sortDirection) {
            $validColumns = ['question_id', 'syllabus_title', 'discipline_name', 'average_score', 'alignment_level'];
            if (in_array($sortColumn, $validColumns)) {
                $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
                usort($semanticData, function($a, $b) use ($sortColumn, $direction) {
                    $valA = $a[$sortColumn];
                    $valB = $b[$sortColumn];
                    if ($valA == $valB) return 0;
                    return ($valA < $valB ? -1 : 1) * $direction;
                });
            }
        }

        $totalItems = count($semanticData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($semanticData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover">
            <thead><tr>
              <th><a href="?section=semantic&semantic_sort=question_id&semantic_dir=<?= ($sortColumn === 'question_id' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>">ID <span class="sort-icon <?= ($sortColumn === 'question_id') ? 'active' : '' ?>"><?= ($sortColumn === 'question_id') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=semantic&semantic_sort=syllabus_title&semantic_dir=<?= ($sortColumn === 'syllabus_title' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>">Тема <span class="sort-icon <?= ($sortColumn === 'syllabus_title') ? 'active' : '' ?>"><?= ($sortColumn === 'syllabus_title') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=semantic&semantic_sort=discipline_name&semantic_dir=<?= ($sortColumn === 'discipline_name' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>">Дисциплина <span class="sort-icon <?= ($sortColumn === 'discipline_name') ? 'active' : '' ?>"><?= ($sortColumn === 'discipline_name') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=semantic&semantic_sort=average_score&semantic_dir=<?= ($sortColumn === 'average_score' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>">Средний балл <span class="sort-icon <?= ($sortColumn === 'average_score') ? 'active' : '' ?>"><?= ($sortColumn === 'average_score') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th><a href="?section=semantic&semantic_sort=alignment_level&semantic_dir=<?= ($sortColumn === 'alignment_level' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>">Статус <span class="sort-icon <?= ($sortColumn === 'alignment_level') ? 'active' : '' ?>"><?= ($sortColumn === 'alignment_level') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
            </tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="5" class="text-muted">' . t('no_data') . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= h($row['syllabus_title']) ?></td>
                  <td><?= h($row['discipline_name']) ?></td>
                  <td><?= $row['average_score'] ?></td>
                  <td><span class="badge badge-soft <?= $row['status_class'] ?>"><?= $row['alignment_level'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?= renderPagination('semantic', 'semantic_page', $page, $totalPages, $sortColumn, $sortDirection, $totalItems) ?>
      <?php else: ?>
        <p class="text-muted"><?= t('select_import') ?></p>

      <?php endif; ?>
    </div>
  </div>

  <div id="section-rules" class="ai-section <?= $section === 'rules' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('rules_constructor') ?></h3>
      
      <div class="row mb-4">
        <div class="col-md-6">
          <h6 class="fw-semibold mb-3"><?= t('active_rules') ?></h6>
          <?php
          $activeRules = $rules->getActiveClassificationRules();
          if (empty($activeRules)):
          ?>
            <div class="alert alert-info"><?= t('no_rules_configured') ?></div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($activeRules as $rule): ?>
                <div class="list-group-item">
                  <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?= h($rule['rule_name']) ?></h6>
                    <span class="badge bg-success"><?= t('active') ?></span>
                  </div>
                  <small class="text-muted"><?= t('order') ?>: <?= (int)$rule['rule_order'] ?></small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="col-md-6">
          <h6 class="fw-semibold mb-3"><?= t('create_new_rule') ?></h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label"><?= t('rule_name') ?></label>
              <input type="text" name="rule_name" class="form-control" required placeholder="<?= t('rule_name_placeholder') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('metric') ?></label>
              <select name="metric_name" class="form-select">
                <option value="difficulty_index"><?= t('difficulty_index') ?></option>
                <option value="discrimination_index"><?= t('discrimination_index') ?></option>
                <option value="avg_score"><?= t('avg_score') ?></option>
                <option value="total_attempts"><?= t('total_attempts') ?></option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('operator') ?></label>
              <select name="operator" class="form-select">
                <option value=">"><?= t('greater_than') ?> (&gt;)</option>
                <option value="<"><?= t('less_than') ?> (&lt;)</option>
                <option value=">="><?= t('greater_or_equal') ?> (&gt;=)</option>
                <option value="<="><?= t('less_or_equal') ?> (&lt;=)</option>
                <option value="between"><?= t('between') ?></option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('value_from') ?></label>
              <input type="number" step="0.01" name="value_from" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('value_to_for_between') ?></label>
              <input type="number" step="0.01" name="value_to" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('quality_category') ?></label>
              <select name="category" class="form-select">
                <option value="too_difficult"><?= t('too_difficult') ?></option>
                <option value="too_easy"><?= t('too_easy') ?></option>
                <option value="poor_discrimination"><?= t('poor_discrimination') ?></option>
                <option value="insufficient_attempts"><?= t('insufficient_attempts') ?></option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('user_message') ?></label>
              <input type="text" name="action_message" class="form-control" placeholder="<?= t('user_message_placeholder') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('execution_order') ?></label>
              <input type="number" min="1" name="rule_order" class="form-control" value="1">
            </div>
            <button type="submit" class="btn btn-primary"><?= t('create_rule') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="section-settings" class="ai-section <?= $section === 'settings' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('settings_coefficients') ?></h3>
      
      <div class="form-grid">
        <div class="form-card">
          <h6 class="fw-semibold mb-3"><?= t('global_settings') ?></h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label"><?= t('difficulty_threshold') ?></label>
              <input type="number" step="0.01" min="0" max="1" name="difficulty_threshold" class="form-control" value="0.5">
              <small class="text-muted"><?= t('difficulty_threshold_desc') ?></small>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('discrimination_threshold') ?></label>
              <input type="number" step="0.01" min="0" max="1" name="discrimination_threshold" class="form-control" value="0.3">
              <small class="text-muted"><?= t('discrimination_threshold_desc') ?></small>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('min_attempts') ?></label>
              <input type="number" min="1" name="min_attempts" class="form-control" value="5">
              <small class="text-muted"><?= t('min_attempts_desc') ?></small>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('min_students_discrimination') ?></label>
              <input type="number" min="2" name="min_students_discrimination" class="form-control" value="<?= $minStudentsDiscrimination ?>">
              <small class="text-muted"><?= t('min_students_discrimination_desc') ?></small>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('dedup_threshold') ?></label>
              <input type="number" step="0.01" min="0" max="1" name="dedup_threshold" class="form-control" value="<?= DEFAULT_DEDUP_THRESHOLD ?>">
              <small class="text-muted"><?= t('dedup_threshold_desc') ?></small>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('tfidf_weight') ?></label>
              <input type="number" step="0.01" min="0" max="1" name="tfidf_weight" class="form-control" value="<?= DEFAULT_TFIDF_WEIGHT ?>">
              <small class="text-muted"><?= t('tfidf_weight_desc') ?></small>
            </div>
            <button type="submit" class="btn btn-primary"><?= t('save_settings') ?></button>
          </form>
        </div>
        
        <div class="form-card">
          <h6 class="fw-semibold mb-3"><?= t('discipline_settings') ?></h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label"><?= t('discipline') ?></label>
              <select name="discipline_id" class="form-select">
                <option value=""><?= t('select_discipline') ?></option>
                <option value="1">Педиатрия</option>
                <option value="2">Хирургия</option>
                <option value="3">Терапия</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('difficulty_coeff') ?></label>
              <input type="number" step="0.01" min="0" max="2" name="discipline_difficulty_coeff" class="form-control" value="1.0">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('importance_coeff') ?></label>
              <input type="number" step="0.01" min="0" max="2" name="discipline_importance_coeff" class="form-control" value="1.0">
            </div>
            <button type="submit" class="btn btn-primary"><?= t('save_settings') ?></button>
          </form>
        </div>
        
        <div class="form-card">
          <h6 class="fw-semibold mb-3"><?= t('specialty_settings') ?></h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label"><?= t('specialty') ?></label>
              <select name="specialty_id" class="form-select">
                <option value=""><?= t('select_specialty') ?></option>
                <option value="1">Лечебное дело</option>
                <option value="2">Педиатрия</option>
                <option value="3">Стоматология</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('base_difficulty_threshold') ?></label>
              <input type="number" step="0.01" min="0" max="1" name="specialty_difficulty_threshold" class="form-control" value="0.5">
            </div>
            <div class="mb-3">
              <label class="form-label"><?= t('strictness_coeff') ?></label>
              <input type="number" step="0.01" min="0" max="2" name="specialty_strictness_coeff" class="form-control" value="1.0">
            </div>
            <button type="submit" class="btn btn-primary"><?= t('save_settings') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="section-criteria" class="ai-section <?= $section === 'criteria' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('criteria_analysis') ?></h3>
      <p class="text-muted small mb-3"><?= t('criteria_analysis_desc') ?? 'Performance analysis by evaluation criteria' ?></p>
      
      <?php
      try {
          $criteriaAnalysis = $ai->getCriteriaPerformanceAnalysis();
      ?>
      
      <?php if (empty($criteriaAnalysis)): ?>
        <div class="alert alert-info">No evaluation criteria data available. Import evaluation criteria and details first.</div>
      <?php else: ?>
        <div class="mb-4">
            <canvas id="criteriaChartTab" style="max-height: 300px;"></canvas>
        </div>
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th>Criteria ID</th>
                <th>Name (Russian)</th>
                <th>Name (Kazakh)</th>
                <th>Weight %</th>
                <th>Avg Score</th>
                <th>Evaluations</th>
                <th>Min/Max</th>
                <th>Std Dev</th>
                <th>Level</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($criteriaAnalysis as $item): ?>
                <tr class="<?= 
                  $item['performance_level'] === 'excellent' ? 'table-success' : 
                  ($item['performance_level'] === 'good' ? 'table-info' : 
                  ($item['performance_level'] === 'satisfactory' ? 'table-warning' : 'table-danger')) 
                ?>">
                  <td><?= h($item['criteria_id']) ?></td>
                  <td><?= h($item['name_russian']) ?></td>
                  <td><?= h($item['name_kazakh']) ?></td>
                  <td><?= h($item['weight_percent']) ?>%</td>
                  <td><strong><?= h($item['avg_score']) ?></strong></td>
                  <td><?= h($item['evaluations']) ?></td>
                  <td><?= h($item['min_score']) ?> / <?= h($item['max_score']) ?></td>
                  <td><?= h($item['std_score']) ?></td>
                  <td>
                    <span class="badge badge-soft <?= 
                      $item['performance_level'] === 'excellent' ? 'success' : 
                      ($item['performance_level'] === 'good' ? 'info' : 
                      ($item['performance_level'] === 'satisfactory' ? 'warning' : 'danger')) 
                    ?>">
                      <?= h(ucfirst($item['performance_level'])) ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      
      <?php
      } catch (Throwable $e) {
          echo '<div class="alert alert-danger">Error: ' . h($e->getMessage()) . '</div>';
      }
      ?>
    </div>
  </div>

  <div id="section-answers" class="ai-section <?= $section === 'answers' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('student_answers') ?></h3>
      <p class="text-muted small mb-3"><?= t('student_answers_desc') ?? 'Analysis of student answers by language and plagiarism detection' ?></p>
      
      <?php
      try {
          $answersAnalysis = $ai->getStudentAnswerAnalysis();
      ?>
      
      <?php if (empty($answersAnalysis)): ?>
        <div class="alert alert-info">No student answers data available. Import student answers first.</div>
      <?php else: ?>
        <div class="mb-4">
            <canvas id="answersChartTab" style="max-height: 300px;"></canvas>
        </div>
        <div class="table-responsive">
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th>Language</th>
                <th>Total Answers</th>
                <th>Avg Score</th>
                <th>Avg Penalty</th>
                <th>Plagiarism Count</th>
                <th>Plagiarism Rate</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($answersAnalysis as $item): ?>
                <tr class="<?= $item['plagiarism_rate'] > 10 ? 'table-danger' : 'table-success' ?>">
                  <td><strong><?= h($item['language']) ?></strong></td>
                  <td><?= h($item['total_answers']) ?></td>
                  <td><strong><?= h($item['avg_score']) ?></strong></td>
                  <td><?= h($item['avg_penalty']) ?></td>
                  <td><?= h($item['plagiarism_count']) ?></td>
                  <td>
                    <span class="badge bg-<?= $item['plagiarism_rate'] > 10 ? 'danger' : 'success' ?>">
                      <?= h($item['plagiarism_rate']) ?>%
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      
      <?php
      } catch (Throwable $e) {
          echo '<div class="alert alert-danger">Error: ' . h($e->getMessage()) . '</div>';
      }
      ?>
    </div>
  </div>

</div>

<script>
let chartData = null;

fetch('chart_data.php')
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            console.error('Error loading chart data:', data.error);
            return;
        }
        chartData = data;
        initializeCharts();
    })
    .catch(error => {
        console.error('Error fetching chart data:', error);
    });

function initializeCharts() {
    if (!chartData) return;
    
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded!');
        return;
    }
    
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    font: { size: 12 },
                    color: '#64748b',
                    padding: 15,
                    usePointStyle: true
                }
            },
            tooltip: {
                enabled: true,
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                titleFont: { size: 13 },
                bodyFont: { size: 12 },
                padding: 10,
                cornerRadius: 6
            }
        }
    };

    const barOptions = {
        ...commonOptions,
        scales: {
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    font: { size: 11 },
                    color: '#64748b'
                }
            },
            y: {
                grid: {
                    color: '#e2e8f0',
                    drawBorder: false
                },
                ticks: {
                    font: { size: 11 },
                    color: '#64748b'
                },
                beginAtZero: true
            }
        }
    };

    const qualityCtx = document.getElementById('qualityChart');
    if (qualityCtx && chartData.quality) {
        try {
            new Chart(qualityCtx, {
                type: 'pie',
                data: {
                    labels: chartData.quality.labels,
                    datasets: [{
                        data: chartData.quality.data,
                        backgroundColor: chartData.quality.backgroundColors,
                        borderColor: chartData.quality.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating quality chart:', error);
        }
    }

    const correlationCtx = document.getElementById('correlationChart');
    if (correlationCtx && chartData.correlation) {
        try {
            new Chart(correlationCtx, {
                type: 'bar',
                data: {
                    labels: chartData.correlation.labels,
                    datasets: [{
                        data: chartData.correlation.data,
                        backgroundColor: chartData.correlation.backgroundColors,
                        borderColor: chartData.correlation.colors,
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: barOptions
            });
        } catch (error) {
            console.error('Error creating correlation chart:', error);
        }
    }

    const studentsCtx = document.getElementById('studentsChart');
    if (studentsCtx && chartData.students) {
        try {
            new Chart(studentsCtx, {
                type: 'bar',
                data: {
                    labels: chartData.students.labels,
                    datasets: [{
                        data: chartData.students.data,
                        backgroundColor: chartData.students.backgroundColors,
                        borderColor: chartData.students.colors,
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: barOptions
            });
        } catch (error) {
            console.error('Error creating students chart:', error);
        }
    }

    const validationCtx = document.getElementById('validationChart');
    if (validationCtx && chartData.validation) {
        try {
            new Chart(validationCtx, {
                type: 'pie',
                data: {
                    labels: chartData.validation.labels,
                    datasets: [{
                        data: chartData.validation.data,
                        backgroundColor: chartData.validation.backgroundColors,
                        borderColor: chartData.validation.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating validation chart:', error);
        }
    }

    const semanticCtx = document.getElementById('semanticChart');
    if (semanticCtx && chartData.semantic) {
        try {
            new Chart(semanticCtx, {
                type: 'bar',
                data: {
                    labels: chartData.semantic.labels,
                    datasets: [{
                        data: chartData.semantic.data,
                        backgroundColor: chartData.semantic.backgroundColors,
                        borderColor: chartData.semantic.colors,
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: barOptions
            });
        } catch (error) {
            console.error('Error creating semantic chart:', error);
        }
    }

    const criteriaCtx = document.getElementById('criteriaChart');
    if (criteriaCtx && chartData.criteria) {
        try {
            new Chart(criteriaCtx, {
                type: 'pie',
                data: {
                    labels: chartData.criteria.labels,
                    datasets: [{
                        data: chartData.criteria.data,
                        backgroundColor: chartData.criteria.backgroundColors,
                        borderColor: chartData.criteria.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating criteria chart:', error);
        }
    }

    const answersCtx = document.getElementById('answersChart');
    if (answersCtx && chartData.answers) {
        try {
            new Chart(answersCtx, {
                type: 'pie',
                data: {
                    labels: chartData.answers.labels,
                    datasets: [{
                        data: chartData.answers.data,
                        backgroundColor: chartData.answers.backgroundColors,
                        borderColor: chartData.answers.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating answers chart:', error);
        }
    }

    const validationCtxTab = document.getElementById('validationChartTab');
    if (validationCtxTab && chartData.validation) {
        try {
            new Chart(validationCtxTab, {
                type: 'pie',
                data: {
                    labels: chartData.validation.labels,
                    datasets: [{
                        data: chartData.validation.data,
                        backgroundColor: chartData.validation.backgroundColors,
                        borderColor: chartData.validation.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating validation tab chart:', error);
        }
    }

    const qualityCtxTab = document.getElementById('qualityChartTab');
    if (qualityCtxTab && chartData.quality) {
        try {
            new Chart(qualityCtxTab, {
                type: 'pie',
                data: {
                    labels: chartData.quality.labels,
                    datasets: [{
                        data: chartData.quality.data,
                        backgroundColor: chartData.quality.backgroundColors,
                        borderColor: chartData.quality.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating quality tab chart:', error);
        }
    }

    const correlationCtxTab = document.getElementById('correlationChartTab');
    if (correlationCtxTab && chartData.correlation) {
        try {
            new Chart(correlationCtxTab, {
                type: 'pie',
                data: {
                    labels: chartData.correlation.labels,
                    datasets: [{
                        data: chartData.correlation.data,
                        backgroundColor: chartData.correlation.backgroundColors,
                        borderColor: chartData.correlation.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating correlation tab chart:', error);
        }
    }

    const studentsCtxTab = document.getElementById('studentsChartTab');
    if (studentsCtxTab && chartData.students) {
        try {
            new Chart(studentsCtxTab, {
                type: 'pie',
                data: {
                    labels: chartData.students.labels,
                    datasets: [{
                        data: chartData.students.data,
                        backgroundColor: chartData.students.backgroundColors,
                        borderColor: chartData.students.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating students tab chart:', error);
        }
    }

    const semanticCtxTab = document.getElementById('semanticChartTab');
    if (semanticCtxTab && chartData.semantic) {
        try {
            new Chart(semanticCtxTab, {
                type: 'bar',
                data: {
                    labels: chartData.semantic.labels,
                    datasets: [{
                        data: chartData.semantic.data,
                        backgroundColor: chartData.semantic.backgroundColors,
                        borderColor: chartData.semantic.colors,
                        borderWidth: 2,
                        borderRadius: 6
                    }]
                },
                options: barOptions
            });
        } catch (error) {
            console.error('Error creating semantic tab chart:', error);
        }
    }

    const criteriaCtxTab = document.getElementById('criteriaChartTab');
    if (criteriaCtxTab && chartData.criteria) {
        try {
            new Chart(criteriaCtxTab, {
                type: 'pie',
                data: {
                    labels: chartData.criteria.labels,
                    datasets: [{
                        data: chartData.criteria.data,
                        backgroundColor: chartData.criteria.backgroundColors,
                        borderColor: chartData.criteria.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating criteria tab chart:', error);
        }
    }

    const answersCtxTab = document.getElementById('answersChartTab');
    if (answersCtxTab && chartData.answers) {
        try {
            new Chart(answersCtxTab, {
                type: 'pie',
                data: {
                    labels: chartData.answers.labels,
                    datasets: [{
                        data: chartData.answers.data,
                        backgroundColor: chartData.answers.backgroundColors,
                        borderColor: chartData.answers.colors,
                        borderWidth: 2
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        legend: {
                            ...commonOptions.plugins.legend,
                            position: 'right'
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating answers tab chart:', error);
        }
    }
}
</script>

<script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  window.showSection = function(sectionId, event) {
    if (event) event.preventDefault();
    document.querySelectorAll('.ai-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.ai-nav-btn').forEach(el => el.classList.remove('active'));
    const section = document.getElementById('section-' + sectionId);
    if (section) {
      section.classList.add('active');
    }
    if (event && event.target) {
      event.target.classList.add('active');
    }
    window.location.hash = sectionId;
  };

  document.querySelectorAll('.table tbody').forEach(tbody => {
    tbody.addEventListener('click', function(event) {
      event.stopPropagation();
    });
  });

  if (window.location.hash) {
    const section = window.location.hash.substring(1);
    const btn = Array.from(document.querySelectorAll('.ai-nav-btn')).find(b => b.textContent.toLowerCase().includes(section));
    if (btn) btn.click();
  }
});
</script>

<?php

function extractDocumentText(string $filePath, string $extension): string
{
    $text = '';
    
    switch ($extension) {
        case 'txt':
            $text = file_get_contents($filePath);
            break;
            
        case 'docx':
            $text = extractDocxText($filePath);
            break;
            
        case 'doc':
            $text = extractDocText($filePath);
            break;
            
        case 'pdf':
            $text = extractPdfText($filePath);
            break;
            
        case 'odt':
            $text = extractOdtText($filePath);
            break;
    }
    
    return trim($text);
}

function extractDocxText(string $filePath): string
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Failed to open DOCX file');
    }
    
    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if (!$xmlContent) {
        throw new Exception('File does not contain document.xml');
    }
    
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlContent, $matches);
    $text = implode(' ', $matches[1]);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    return $text;
}

function extractDocText(string $filePath): string
{
    $text = file_get_contents($filePath);
    $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/u', '', $text);
    return trim($text);
}

function extractPdfText(string $filePath): string
{
    $text = '';
    $handle = fopen($filePath, 'r');
    
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/\((.*?)\)/', $line, $matches)) {
                $text .= $matches[1] . ' ';
            }
        }
        fclose($handle);
    }
    
    return trim($text) ?: 'PDF text not extracted (library required)';
}

function extractOdtText(string $filePath): string
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Failed to open ODT file');
    }
    
    $xmlContent = $zip->getFromName('content.xml');
    $zip->close();
    
    if (!$xmlContent) {
        throw new Exception('File does not contain content.xml');
    }
    
    preg_match_all('/<text:p[^>]*>(.*?)<\/text:p>/s', $xmlContent, $matches);
    $text = implode(' ', $matches[1]);
    $text = strip_tags($text);
    
    return trim($text);
}

function extractKeywords(string $text, string $disciplineName): array
{
    $stopWords = [
        'and','in','on','not','that','he','was','with','for','it','as','his','be','at','i','which','same','we',
        'had','have','from','one','had','but','word','what','were','when','your','said','there','each','use',
        'an','they','how','their','if','will','up','other','about','out','many','then','them','these','so',
        'question','answer','test','exam','student','score'
    ];
    
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    $keywords = [];
    foreach ($words as $word) {
        if (mb_strlen($word, 'UTF-8') >= 4 && !in_array($word, $stopWords)) {
            $keywords[$word] = ($keywords[$word] ?? 0) + 1;
        }
    }
    
    arsort($keywords);
    return array_keys(array_slice($keywords, 0, 50));
}

function parseSyllabusTopics(string $text, string $disciplineName, int $courseNumber): array
{
    $topics = [];
    
    $patterns = [
        '/(\d+[\.\)]\s*)([A-Za-z][^\n\.]{10,100})/u',
        '/(Topic\s*\d*[:\.\s]*)([A-Za-z][^\n\.]{10,100})/ui',
        '/(Section\s*\d*[:\.\s]*)([A-Za-z][^\n\.]{10,100})/ui',
    ];
    
    $foundTopics = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $title = trim($match[2]);
                $normalizedTitle = mb_strtolower(preg_replace('/\s+/', ' ', $title), 'UTF-8');
                if (!in_array($normalizedTitle, $foundTopics) && mb_strlen($title) >= 10) {
                    $foundTopics[$normalizedTitle] = $title;
                }
            }
        }
    }
    
    $index = 1;
    foreach ($foundTopics as $title) {
        $topics[] = [
            'code' => strtoupper(substr(preg_replace('/[^a-z0-9]/ui', '', $disciplineName), 0, 6)) . '_T' . $index,
            'title' => $title,
            'keywords' => implode(',', array_slice(extractKeywords($title, $disciplineName), 0, 10))
        ];
        $index++;
    }
    
    if (empty($topics)) {
        $topics[] = [
            'code' => strtoupper(substr(preg_replace('/[^a-z0-9]/ui', '', $disciplineName), 0, 6)) . '_AUTO',
            'title' => $disciplineName . ' - Main Course',
            'keywords' => implode(',', array_slice(extractKeywords($text, $disciplineName), 0, 10))
        ];
    }
    
    return $topics;
}

function parseSyllabusQuestions(string $text): array
{
    $questions = [];
    
    $sectionText = $text;
    
    if (preg_match('/Контрольно-измерительные средства.*?(?=\n\s*$|\Z)/s', $text, $match)) {
        $sectionText = $match[0];
    }
    elseif (preg_match('/Приложения.*?(?=\n\s*$|\Z)/s', $text, $match)) {
        $sectionText = $match[0];
    }
    
    
    $pattern = '/(\d+)\.\s*(.*?)(?=\n\s*\d+\.\s*|\n\s*$|\Z)/s';
    
    if (preg_match_all($pattern, $sectionText, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $questionText = trim($match[2]);
            
            $questionText = preg_replace('/\s+/', ' ', $questionText);
            
            if (mb_strlen($questionText) > 30) {
                $questions[] = [
                    'text' => $questionText,
                    'type' => 'syllabus_question',
                    'number' => (int)$match[1]
                ];
            }
        }
    } else {
    }
    
    return $questions;
}

function readOdsFile(string $filePath): array {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) throw new Exception('Failed to open ODS');
    
    $xmlContent = $zip->getFromName('content.xml');
    $zip->close();
    if (!$xmlContent) throw new Exception('No content.xml');
    
    $data = [];
    $headers = [];
    
    preg_match_all('/<table:table-row[^>]*>(.*?)<\/table:table-row>/s', $xmlContent, $rowMatches, PREG_SET_ORDER);
    
    foreach ($rowMatches as $rowMatch) {
        $rowContent = $rowMatch[1];
        preg_match_all('/<table:table-cell[^>]*>(.*?)<\/table:table-cell>/s', $rowContent, $cellMatches, PREG_SET_ORDER);
        
        $currentRow = [];
        foreach ($cellMatches as $cellMatch) {
            preg_match('/<text:p[^>]*>(.*?)<\/text:p>/s', $cellMatch[1], $textMatch);
            $currentRow[] = isset($textMatch[1]) ? trim($textMatch[1]) : '';
        }
        
        if (empty($currentRow)) continue;
        
        if (empty($headers)) {
            $headers = $currentRow;
        } else {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $currentRow[$index] ?? '';
            }
            $data[] = $rowData;
        }
    }
    
    return $data;
}

function readCsvFile(string $filePath, string $delimiter = null): array {
    $data = [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) throw new Exception('Failed to open CSV');
    
    if ($delimiter === null) {
        $headers = fgetcsv($handle, 0, ';');
        if ($headers === false) { fseek($handle, 0); $headers = fgetcsv($handle, 0, ','); $delimiter = ','; }
        else { $delimiter = ';'; }
    } else {
        $headers = fgetcsv($handle, 0, $delimiter);
    }
    
    if ($headers === false) throw new Exception('Failed to read headers');
    $headers = array_map('trim', $headers);
    
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (empty($row)) continue;
        $rowData = [];
        foreach ($headers as $index => $header) { $rowData[$header] = isset($row[$index]) ? trim($row[$index]) : ''; }
        $data[] = $rowData;
    }
    fclose($handle);
    return $data;
}
?>

<script>
function toggleAllCheckboxes(checkbox) {
    const checkboxes = document.querySelectorAll('.question-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function filterQuestions() {
    const searchValue = document.getElementById('questionSearch').value.toLowerCase();
    const tableBody = document.getElementById('questionsTableBody');
    const rows = tableBody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const id = row.getAttribute('data-id');
        const question = row.getAttribute('data-question');
        const discipline = row.getAttribute('data-discipline');
        const topic = row.getAttribute('data-topic');
        
        if (id.includes(searchValue) || 
            question.includes(searchValue) || 
            discipline.includes(searchValue) ||
            topic.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const sortableHeaders = document.querySelectorAll('#questionsTable .sortable');
    let sortDirection = {};
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortType = this.getAttribute('data-sort');
            const tableBody = document.getElementById('questionsTableBody');
            const rows = Array.from(tableBody.getElementsByTagName('tr'));
            
            sortDirection[sortType] = sortDirection[sortType] === 'asc' ? 'desc' : 'asc';
            
            sortableHeaders.forEach(h => h.textContent = h.textContent.replace(' ⬍', '').replace(' ▲', '').replace(' ▼', ''));
            this.textContent = this.textContent + (sortDirection[sortType] === 'asc' ? ' ▲' : ' ▼');
            
            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch(sortType) {
                    case 'id':
                        aVal = parseInt(a.getAttribute('data-id'));
                        bVal = parseInt(b.getAttribute('data-id'));
                        break;
                    case 'question':
                        aVal = a.getAttribute('data-question');
                        bVal = b.getAttribute('data-question');
                        break;
                    case 'discipline':
                        aVal = a.getAttribute('data-discipline');
                        bVal = b.getAttribute('data-discipline');
                        break;
                    case 'topic':
                        aVal = a.getAttribute('data-topic');
                        bVal = b.getAttribute('data-topic');
                        break;
                }
                
                if (sortDirection[sortType] === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
            
            rows.forEach(row => tableBody.appendChild(row));
        });
    });
});
</script>

<div id="confirmModal" class="confirm-modal">
  <div class="confirm-modal-content">
    <div class="confirm-modal-title" id="confirmModalTitle"><?= t('confirmation') ?></div>
    <div class="confirm-modal-message" id="confirmModalMessage"></div>
    <div class="confirm-modal-buttons">
      <button id="confirmModalConfirm" class="confirm-modal-btn confirm-modal-btn-confirm"><?= t('confirm') ?></button>
      <button id="confirmModalCancel" class="confirm-modal-btn confirm-modal-btn-cancel"><?= t('cancel') ?></button>
    </div>
  </div>
</div>

<?php render_footer(); ?>
