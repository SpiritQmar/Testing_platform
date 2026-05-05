<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
require_once __DIR__ . '/services/SystemHealthService.php';
require_once __DIR__ . '/api/router.php';

$config = require __DIR__ . '/config.php';


$systemHealth = new SystemHealthService();

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
$embeddingsService = new EmbeddingsService();
$tfidf = new TfIdfService($pdo, $embeddingsService, $importId);
$rules = new RuleClassifierService($pdo);
$router = new AnalyticsServiceRouter ($pdo);
$questionImportFlash = $_SESSION['question_text_import_flash'] ?? null;
unset($_SESSION['question_text_import_flash']);

$importFlash = $_SESSION['import_flash'] ?? null;
unset($_SESSION['import_flash']);

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

$section = $_GET['section'] ?? 'validation';
$sections = [
    'validation' => t('validation'),
    'quality' => t('quality'),
    'correlation' => t('correlations'),
    'students' => t('students'),
    'semantic' => t('semantic'),
    'criteria' => t('criteria_analysis'),
    'answers' => t('student_answers'),
    'syllabus-link' => t('link_syllabus_questions'),
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
  const params = new URLSearchParams(window.location.search);
  const importTab = params.get('import_tab');
  if (importTab) {
    showImportTab(importTab);
  }
});

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
  setupImportFormLogging();
});

function setupImportFormLogging() {
  // Проверяем что DOM загружен перед поиском форм
  if (document.readyState === 'loading') {
    return;
  }

  const forms = [
    { id: 'criteriaForm', name: 'Criteria', fileInput: 'criteriaFileInput' },
    { id: 'detailsForm', name: 'Details', fileInput: 'detailsFileInput' },
    { id: 'answersForm', name: 'Answers', fileInput: 'answersFileInput' }
  ];

  forms.forEach(formConfig => {
    const form = document.getElementById(formConfig.id);
    if (form) {
      form.addEventListener('submit', function(e) {
        const fileInput = document.getElementById(formConfig.fileInput);

        if (fileInput && fileInput.files.length === 0) {
          console.error('ERROR: No file selected!');
          e.preventDefault();
          alert('Пожалуйста, выберите файл для импорта');
          return;
        }
        
      });
    }
  });
}

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
.ai-header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; color: white; }
.ai-header p { font-size: 16px; opacity: 0.9; color: white; }
.ai-nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 40px; justify-content: center; }
.ai-nav-btn { flex: 1; min-width: 100px; padding: 12px 16px; border: 2px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); font-weight: 500; font-size: 13px; text-decoration: none; color: var(--text-primary); display: flex; align-items: center; justify-content: center; text-align: center; line-height: 1.3; }
.ai-nav-btn:hover { background: var(--bg-tertiary); transform: translateY(-2px); border-color: var(--accent-primary); box-shadow: var(--shadow-md); text-decoration: none; color: var(--text-primary); }
.ai-nav-btn:active { transform: translateY(0); }
.ai-nav-btn.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-color: transparent; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3); text-decoration: none; }
.ai-section { display: none; }
.ai-section.active { display: block; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 24px; margin-bottom: 40px; }
.stat-card { background: var(--bg-primary); padding: 24px; border-radius: 12px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
.stat-value { font-size: 36px; font-weight: 700; color: var(--text-primary); }
.stat-label { color: var(--text-muted); font-size: 15px; margin-top: 8px; font-weight: 500; }
.table-container { background: var(--bg-primary) !important; border-radius: 12px; padding: 24px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); margin-bottom: 30px; max-width: 1400px; margin-left: auto; margin-right: auto; overflow-x: auto; }
.table-container table { background: var(--bg-primary) !important; }
.table-container table thead { background: var(--bg-secondary) !important; }
.table-container table tbody { background: var(--bg-primary) !important; }
.badge-soft { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-soft.success { background: rgba(16, 185, 129, 0.15); color: var(--accent-success); }
.badge-soft.warning { background: rgba(245, 158, 11, 0.15); color: var(--accent-warning); }
.badge-soft.danger { background: rgba(239, 68, 68, 0.15); color: var(--accent-danger); }
.badge-soft.info { background: rgba(37, 99, 235, 0.15); color: var(--accent-primary); }

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

.container-card { border: 2px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 15px; background: var(--bg-primary); }
.container-card.running { border-color: #10b981; background: rgba(16, 185, 129, 0.1); }
.container-card.stopped { border-color: #ef4444; background: rgba(239, 68, 68, 0.1); }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.form-card { background: var(--bg-primary); padding: 20px; border-radius: 12px; box-shadow: var(--shadow-md); border: 1px solid var(--border-color); }
.pagination { display: flex; gap: 8px; margin-top: 24px; justify-content: center; }
.pagination button { padding: 10px 18px; border: 1px solid var(--border-color); background: var(--bg-primary); color: var(--text-primary); cursor: pointer; border-radius: 8px; font-size: 14px; transition: all 0.2s ease; }
.pagination button:hover { background: var(--bg-secondary); transform: translateY(-1px); }
.pagination button.active { background: #667eea; color: white; border-color: #667eea; box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3); }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
table thead th a { color: inherit; text-decoration: none; }
table thead th a:hover { text-decoration: underline; }
table { border-collapse: separate; border-spacing: 0; width: auto; max-width: 100%; background: var(--bg-primary) !important; }
table thead th { border-bottom: 2px solid var(--border-color); border-right: 1px solid var(--border-color); padding: 18px 14px; font-weight: 600; font-size: 16px !important; color: var(--text-primary); background: var(--bg-secondary) !important; cursor: pointer; transition: background 0.2s; }
table thead th:hover { background: var(--bg-tertiary) !important; }
table thead th:last-child { border-right: none; }
table thead th a { font-size: 16px !important; color: var(--text-primary); cursor: pointer; transition: color 0.2s; }
table thead th a:hover { color: #667eea; }
table tbody td { border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); padding: 16px 14px; font-size: 16px; color: var(--text-secondary); background: var(--bg-primary) !important; }
table tbody td:last-child { border-right: none; }
table tbody tr { background: var(--bg-primary) !important; }
table tbody tr:hover { background: var(--bg-secondary) !important; }
table tbody tr:last-child td { border-bottom: none; }
.sort-icon { font-size: 18px; margin-left: 6px; opacity: 0.6; }
.sort-icon.active { opacity: 1; color: #667eea; }
.nav-tabs { border-bottom: 2px solid var(--border-color); }
.nav-tabs .nav-link { border: 2px solid transparent; border-bottom: none; padding: 12px 20px; color: var(--text-muted); background: var(--bg-secondary); font-weight: 500; font-size: 14px; transition: all 0.2s ease; }
.nav-tabs .nav-link:hover { border-color: var(--border-color); color: var(--text-primary); background: var(--bg-tertiary); }
.nav-tabs .nav-link.active { border-color: var(--border-color) var(--border-color) var(--bg-primary); color: #667eea; background: var(--bg-primary); border-bottom: 2px solid var(--bg-primary); margin-bottom: -2px; }
.tab-pane { padding: 20px 0; }
.form-control { border: 2px solid var(--border-color); border-radius: 8px; padding: 12px; font-size: 14px; transition: all 0.2s ease; background: var(--input-bg); color: var(--text-primary); }
.form-control:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); outline: none; }
.form-control:hover { border-color: var(--border-color); }
.form-select { border: 2px solid var(--border-color); border-radius: 8px; padding: 12px; font-size: 14px; transition: all 0.2s ease; background: var(--input-bg); color: var(--text-primary); }
.form-select:focus { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); outline: none; }
.form-select:hover { border-color: var(--border-color); }
.btn { padding: 10px 20px; border-radius: 8px; font-weight: 500; font-size: 14px; border: none; transition: all 0.2s ease; cursor: pointer; }
.btn:hover { transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.btn-primary { background: #667eea; color: white; }
.btn-primary:hover { background: #5a67d8; box-shadow: 0 4px 6px rgba(102, 126, 234, 0.3); }
.btn-danger { background: #ef4444; color: white; }
.btn-danger:hover { background: #dc2626; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3); }
.btn-warning { background: #f59e0b; color: white; }
.btn-warning:hover { background: #d97706; box-shadow: 0 4px 6px rgba(245, 158, 11, 0.3); }
.btn-outline-primary { background: transparent; border: 2px solid var(--border-color); color: var(--text-primary); }
.btn-outline-primary:hover { background: var(--bg-secondary); border-color: #667eea; color: #667eea; }
.alert { border-radius: 12px; padding: 16px; border: 1px solid var(--border-color); font-size: 14px; }
.alert-success { background: rgba(16, 185, 129, 0.1); border-color: #10b981; color: #059669; }
.alert-danger { background: rgba(239, 68, 68, 0.1); border-color: #ef4444; color: #dc2626; }
.alert-warning { background: rgba(245, 158, 11, 0.1); border-color: #f59e0b; color: #d97706; }
</style>

<div class="ai-container">
  <div class="ai-header">
    <div class="ai-header-content">
      <div>
        <h1 class="h2 mb-2"><?= t('title') ?></h1>
        <p class="mb-0 opacity-75"><?= t('subtitle') ?></p>
      </div>
    </div>
  </div>
  
  <?php if ($importId === null && !empty($imports)): ?>
    <div class="alert alert-warning mb-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <strong><?= t('select_data_import') ?></strong>
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
              
              $stmt = $pdo->prepare("
                  SELECT 
                      COUNT(DISTINCT question_id) as questions,
                      COUNT(DISTINCT student_id) as students,
                      COUNT(*) as attempts,
                      AVG(score_after_appeal) as avg_score
                  FROM raw_exam_results 
                  WHERE import_id != ? AND import_id IN (
                      SELECT id FROM imports WHERE created_at < (
                          SELECT created_at FROM imports WHERE id = ?
                      ) ORDER BY created_at DESC LIMIT 1
                  )
              ");
              $stmt->execute([$importId, $importId]);
              $previousStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['questions' => 0, 'students' => 0, 'attempts' => 0, 'avg_score' => 0];
              
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
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?= t('guide_syllabus_import') ?></div>
                  <div style="font-size: 12px; line-height: 1.4;"><?= t('guide_syllabus_import_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #10b981;"><i class="bi bi-bar-chart"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?= t('guide_exam_import') ?></div>
                  <div style="font-size: 12px; line-height: 1.4;"><?= t('guide_exam_import_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #f59e0b;"><i class="bi bi-shuffle"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?= t('guide_sorting') ?></div>
                  <div style="font-size: 12px; line-height: 1.4;"><?= t('guide_sorting_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #ef4444;"><i class="bi bi-graph-up"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?= t('guide_quality') ?></div>
                  <div style="font-size: 12px; line-height: 1.4;"><?= t('guide_quality_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #8b5cf6;"><i class="bi bi-link"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?= t('guide_correlation') ?></div>
                  <div style="font-size: 12px; line-height: 1.4;"><?= t('guide_correlation_desc') ?></div>
                </div>
              </div>
              <div class="quick-guide-item">
                <div class="quick-guide-icon" style="background: #06b6d4;"><i class="bi bi-people"></i></div>
                <div>
                  <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;"><?= t('guide_students') ?></div>
                  <div style="font-size: 12px; line-height: 1.4;"><?= t('guide_students_desc') ?></div>
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
            <div style="display: flex; gap: 8px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; align-items: center; justify-content: space-between;">
              <div style="display: flex; gap: 8px;">
                <button onclick="showChart('quality')" id="btn-quality" class="chart-button active"><?= t('quality') ?></button>
                <button onclick="showChart('correlation')" id="btn-correlation" class="chart-button"><?= t('correlation') ?></button>
                <button onclick="showChart('students')" id="btn-students" class="chart-button"><?= t('students') ?></button>
                <button onclick="showChart('validation')" id="btn-validation" class="chart-button"><?= t('validation') ?></button>
                <button onclick="showChart('semantic')" id="btn-semantic" class="chart-button"><?= t('semantic') ?></button>
                <button onclick="showChart('criteria')" id="btn-criteria" class="chart-button"><?= t('criteria_analysis') ?></button>
                <button onclick="showChart('answers')" id="btn-answers" class="chart-button"><?= t('student_answers') ?></button>
              </div>
              <div style="display: flex; align-items: center;">
                <?= $systemHealth->getHealthStatusBadge() ?>
              </div>
            </div>
            <div id="chart-quality" style="display: block;">
              <h4 class="chart-title"><?= t('chart_quality_distribution') ?></h4>
              <div class="chart-subtitle"><?= t('chart_quality_distribution_desc') ?? 'Question complexity breakdown by difficulty levels' ?></div>
              <div class="chart-container">
                <canvas id="qualityChart"></canvas>
              </div>
            </div>
            <div id="chart-correlation" style="display: none;">
              <h4 class="chart-title"><?= t('chart_correlation_distribution') ?></h4>
              <div class="chart-subtitle"><?= t('chart_correlation_distribution_desc') ?? 'Correlation analysis between question metrics' ?></div>
              <div class="chart-container">
                <canvas id="correlationChart"></canvas>
              </div>
            </div>
            <div id="chart-students" style="display: none;">
              <h4 class="chart-title"><?= t('chart_student_risk_patterns') ?></h4>
              <div class="chart-subtitle"><?= t('chart_student_risk_patterns_desc') ?? 'Student performance patterns and risk identification' ?></div>
              <div class="chart-container">
                <canvas id="studentsChart"></canvas>
              </div>
            </div>
            <div id="chart-validation" style="display: none;">
              <h4 class="chart-title"><?= t('chart_validation_status') ?></h4>
              <div class="chart-subtitle"><?= t('chart_validation_status_desc') ?? 'Question validation results and status overview' ?></div>
              <div class="chart-container">
                <canvas id="validationChart"></canvas>
              </div>
            </div>
            <div id="chart-semantic" style="display: none;">
              <h4 class="chart-title"><?= t('chart_semantic_analysis') ?></h4>
              <div class="chart-subtitle"><?= t('chart_semantic_analysis_desc') ?? 'Semantic analysis using embeddings and vector representations' ?></div>
              <div class="chart-container">
                <canvas id="semanticChart"></canvas>
              </div>
            </div>
            <div id="chart-criteria" style="display: none;">
              <h4 class="chart-title"><?= t('criteria_analysis') ?></h4>
              <div class="chart-subtitle"><?= t('criteria_analysis_desc') ?? 'Evaluation criteria analysis and distribution' ?></div>
              <div class="chart-container">
                <canvas id="criteriaChart"></canvas>
              </div>
            </div>
            <div id="chart-answers" style="display: none;">
              <h4 class="chart-title"><?= t('student_answers') ?></h4>
              <div class="chart-subtitle"><?= t('student_answers_desc') ?? 'Student answer patterns and response analysis' ?></div>
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
                        ':uid' => 1
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
                        ':uid' => 1
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
                    try {
                        $pdo->exec("DELETE FROM question_validation_cache WHERE import_id = " . (int)$importId);
                        $pdo->exec("DELETE FROM question_semantic_cache WHERE import_id = " . (int)$importId);
                    } catch (Throwable $ce) {}
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
    <?php if ($questionImportFlash): ?>
      <div class="alert alert-<?= h($questionImportFlash['type'] ?? 'info') ?>"><?= h($questionImportFlash['message'] ?? '') ?></div>
    <?php endif; ?>
    <?php if ($importFlash): ?>
      <div class="alert alert-<?= h($importFlash['type'] ?? 'info') ?>"><?= h($importFlash['message'] ?? '') ?></div>
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

        <div id="import-questions" style="display: none;">
          <?php
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
                  $stmt = $pdo->prepare("
                      SELECT DISTINCT discipline_name
                      FROM raw_exam_results
                      WHERE import_id = :import_id
                        AND discipline_name IS NOT NULL
                        AND discipline_name != ''
                      ORDER BY discipline_name
                  ");
                  $stmt->execute([':import_id' => $importId]);
                  $questionImportDisciplines = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
              }
          } catch (Throwable $e) {
              error_log('Error loading question import disciplines: ' . $e->getMessage());
          }
          ?>

          <form method="post" enctype="multipart/form-data" action="imports/import_question_texts.php">
            <?= csrf_input() ?>

            <div class="alert alert-info">
              <strong>Импорт текстов вопросов</strong>
              <p class="mb-2 mt-2">Файл `txt` будет применён только к выбранной дисциплине в текущей выгрузке результатов. Вопросы из файла раскладываются по порядку на `question_id` этой дисциплины.</p>
              <p class="mb-0">Если в файле и в выгрузке разное количество вопросов, импорт остановится и покажет ошибку.</p>
            </div>

            <?php if ($importId === null): ?>
              <div class="alert alert-warning mb-3">Сначала выбери или загрузи выгрузку результатов.</div>
            <?php endif; ?>

            <input type="hidden" name="import_id" value="<?= (int)$importId ?>">

            <div class="import-upload-box" onclick="document.getElementById('questionsFileInput').click()">
              <div class="import-upload-icon">
                <i class="bi bi-file-earmark-font"></i>
              </div>
              <p class="import-upload-text">Drag & Drop files here...</p>
              <p class="import-upload-subtext">.TXT с нумерованными вопросами</p>
              <button type="button" class="import-browse-btn">Or browse files</button>
              <input type="file" name="questions_file" id="questionsFileInput" accept=".txt" required style="display: none;" onchange="updateUploadBox(this, 'questions')">
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
        <form id="criteriaForm" method="post" enctype="multipart/form-data" action="imports/import_evaluation_criteria.php">
          <?= csrf_input() ?>
          <input type="hidden" name="import_id" value="<?= $importId ?? 1 ?>">
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
        <form id="detailsForm" method="post" enctype="multipart/form-data" action="imports/import_evaluation_details.php">
          <?= csrf_input() ?>
          <input type="hidden" name="import_id" value="<?= $importId ?? 1 ?>">
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
        <form id="answersForm" method="post" enctype="multipart/form-data" action="imports/import_student_answers.php">
          <?= csrf_input() ?>
          <input type="hidden" name="import_id" value="<?= $importId ?? 1 ?>">
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
      
      <?php
      
      $healthStatus = $systemHealth->checkSystemHealth();
      $healthAlerts = $systemHealth->getHealthAlerts();
      
      if (!empty($healthAlerts)): ?>
        <div class="alert alert-warning mb-4" role="alert">
          <h5 class="alert-heading">
            <i class="bi bi-exclamation-triangle-fill"></i> 
            <?= $healthAlerts[0]['title'] ?>
          </h5>
          <p class="mb-2"><?= $healthAlerts[0]['message'] ?></p>
          <hr>
          <p class="mb-0">
            <small>
              <strong>Что делать:</strong><br>
              1. Откройте командную строку<br>
              2. Перейдите в папку: <code>cd C:\xampp\htdocs\uams\backend\analytics\python_services</code><br>
              3. Установите зависимости: <code>pip install -r requirements.txt</code><br>
              4. Запустите сервис: <code>python embeddings_api.py</code><br>
              5. Проверьте: <code>curl http://localhost:8000/health</code>
            </small>
          </p>
        </div>
      <?php endif; ?>
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

        
        require_once __DIR__ . '/services/ValidationService.php';
        require_once __DIR__ . '/services/EmbeddingsService.php';
        require_once __DIR__ . '/services/TfIdfService.php';
        
        $embeddingsService = new EmbeddingsService();
        $tfIdfService = new TfIdfService($pdo, $embeddingsService, $importId);
        $validationService = new ValidationService($pdo, $embeddingsService, $tfIdfService);
        
        $validationResults = $validationService->getValidationOverview((int)$importId, true);
        
        
        $validationData = [];
        $validationErrors = [];
        
        $validationResultsType = gettype($validationResults);
        $validationResultsCount = is_array($validationResults) ? count($validationResults) : 'not array';
        error_log("Before loop: validationResults type=$validationResultsType, count=$validationResultsCount");
        
        if (!empty($validationResults) && is_array($validationResults)) {
            $iterationCount = 0;
            foreach ($validationResults as $v):
                try {
                    $iterationCount++;
                    error_log("Validation loop iteration $iterationCount, question_id=" . ($v['question_id'] ?? 'missing'));
                    $statusClass = ($v['status'] ?? '') === 'ok' ? 'success' : 'warning';
                    $issues = is_array($v['issues'] ?? null) ? $v['issues'] : [];
                    $validationData[] = [
                        'question_id' => (int)($v['question_id'] ?? 0),
                        'syllabus_title' => $v['syllabus_title'] ?? '—',
                        'avg_score' => $v['avg_score'] ?? 0,
                        'attempts' => $v['attempts'] ?? 0,
                        'status_class' => $statusClass,
                        'status' => $v['status'] ?? 'ok',
                        'issues' => implode(', ', $issues)
                    ];
                    error_log("Validation loop iteration $iterationCount SUCCESS");
                } catch (Throwable $e) {
                    $validationErrors[] = "Iteration $iterationCount error: " . $e->getMessage();
                    error_log("Validation loop iteration $iterationCount ERROR: " . $e->getMessage());
                }
            endforeach;
        }
        
        if (!empty($validationErrors)) {
            echo "<script>console.error('index.php: validation errors:', " . json_encode($validationErrors) . ");</script>";
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
            <canvas id="correlationChartTab" style="max-height: 400px; width: 100%;"></canvas>
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
        <div class="table-responsive" style="max-width: none; width: 100%;">
          <table class="table table-hover" style="width: 100%; min-width: 600px;">
            <thead><tr>
              <th style="width: 25%;"><a href="?section=correlation&correlation_sort=question_id&correlation_dir=<?= ($sortColumn === 'question_id' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_id') ?> <span class="sort-icon <?= ($sortColumn === 'question_id') ? 'active' : '' ?>"><?= ($sortColumn === 'question_id') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th style="width: 35%;"><a href="?section=correlation&correlation_sort=r&correlation_dir=<?= ($sortColumn === 'r' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_coefficient') ?> <span class="sort-icon <?= ($sortColumn === 'r') ? 'active' : '' ?>"><?= ($sortColumn === 'r') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
              <th style="width: 30%;"><a href="?section=correlation&correlation_sort=flag&correlation_dir=<?= ($sortColumn === 'flag' && $sortDirection === 'ASC') ? 'DESC' : 'ASC' ?>"><?= t('th_flag') ?> <span class="sort-icon <?= ($sortColumn === 'flag') ? 'active' : '' ?>"><?= ($sortColumn === 'flag') ? ($sortDirection === 'ASC' ? '▲' : '▼') : '⇅' ?></span></a></th>
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

        $semantic = $ai->getSemanticAnalysis();
        $semanticData = [];
        if (!empty($semantic)) {
            foreach ($semantic as $s):
                $statusClass = $s['alignment_level'] === 'high' ? 'success' : ($s['alignment_level'] === 'medium' ? 'warning' : 'danger');
                $semanticData[] = [
                    'question_id' => $s['question_id'],
                    'syllabus_title' => $s['syllabus_title'],
                    'discipline_name' => $s['discipline_name'],
                    'average_score' => $s['avg_score'],
                    'status_class' => $statusClass,
                    'alignment_level' => $s['alignment_level']
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
                  ($item['performance_level'] === 'good' ? 'table-primary' : 
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
                      ($item['performance_level'] === 'good' ? 'primary' : 
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

  <div id="section-syllabus-link" class="ai-section <?= $section === 'syllabus-link' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= t('link_syllabus_questions') ?></h3>
      <?php if ($section === 'syllabus-link'): ?>
      <?php
      $slQuestions = [];
      $slTotal = 0;
      $slError = '';
      $slTopics = [];
      try {
          if ($importId !== null) {
              $slCountStmt = $pdo->prepare("SELECT COUNT(DISTINCT hq.question_id) FROM hier_questions hq INNER JOIN raw_exam_results r ON r.question_id = hq.question_id AND r.import_id = ? WHERE hq.question_text IS NOT NULL AND hq.question_text != '' AND hq.question_text NOT REGEXP '^Вопрос[[:space:]]+[0-9]+$'");
              $slCountStmt->execute([$importId]);
              $slTotal = (int)$slCountStmt->fetchColumn();
              $slQuestionsStmt = $pdo->prepare("
                  SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id, st.title as syllabus_title,
                         MAX(r.discipline_name) as discipline_name
                  FROM hier_questions hq
                  INNER JOIN raw_exam_results r ON r.question_id = hq.question_id AND r.import_id = ?
                  LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
                  WHERE hq.question_text IS NOT NULL AND hq.question_text != ''
                  AND hq.question_text NOT REGEXP '^Вопрос[[:space:]]+[0-9]+\$'
                  GROUP BY hq.question_id
                  ORDER BY hq.question_id LIMIT 500
              ");
              $slQuestionsStmt->execute([$importId]);
              $slQuestions = $slQuestionsStmt->fetchAll(PDO::FETCH_ASSOC);
          } else {
              $slTotal = (int)$pdo->query("SELECT COUNT(*) FROM hier_questions WHERE question_text IS NOT NULL AND question_text != '' AND question_text NOT REGEXP '^Вопрос[[:space:]]+[0-9]+$'")->fetchColumn();
              $slQuestions = $pdo->query("
                  SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id, st.title as syllabus_title,
                         '' as discipline_name
                  FROM hier_questions hq
                  LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
                  WHERE hq.question_text IS NOT NULL AND hq.question_text != ''
                  AND hq.question_text NOT REGEXP '^Вопрос[[:space:]]+[0-9]+\$'
                  ORDER BY hq.question_id LIMIT 500
              ")->fetchAll(PDO::FETCH_ASSOC);
          }
          $slTopics = $pdo->query("
              SELECT DISTINCT st.syllabus_topic_id, st.discipline_name, st.course_number, st.title
              FROM ai_syllabus_topics st
              INNER JOIN user_syllabuses us ON us.discipline_name = st.discipline_name
              ORDER BY st.discipline_name, st.course_number, st.title LIMIT 100
          ")->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e) {
          $slError = $e->getMessage();
      }
      ?>
      <div class="alert alert-info">
        <strong><?= t('select_questions') ?></strong>
        <ul class="mb-0 mt-2">
          <li><?= t('select_questions_for_link') ?></li>
          <li><?= t('select_topic_from_dropdown') ?></li>
        </ul>
      </div>
      <?php if ($slError): ?>
        <div class="alert alert-danger"><?= h($slError) ?></div>
      <?php endif; ?>
      <form method="post" class="mb-4">
        <?= csrf_input() ?>
        <input type="hidden" name="link_selected_questions" value="1">
        <div class="mb-3">
          <label class="form-label fw-semibold"><?= t('select_syllabus_topic') ?> (<?= t('available_topics') ?>: <span id="slTopicCount"><?= count($slTopics) ?></span>)</label>
          <div style="display: flex; gap: 8px;">
            <select name="syllabus_topic_id" class="form-select" required id="slTopicSelect">
              <option value="">-- <?= t('select_syllabus_topic') ?> --</option>
              <?php foreach ($slTopics as $topic): ?>
                <option value="<?= (int)$topic['syllabus_topic_id'] ?>"><?= h($topic['discipline_name']) ?> - <?= h($topic['course_number']) ?>: <?= h($topic['title']) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary" onclick="refreshSyllabusTopics()" title="<?= t('refresh') ?>"><i class="bi bi-arrow-clockwise"></i></button>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold"><?= t('all_questions') ?> (<?= t('total') ?>: <?= $slTotal ?>, <?= t('shown') ?>: <?= count($slQuestions) ?>)</label>
          <input type="text" id="slSearch" class="form-control mb-2" placeholder="<?= t('search_placeholder') ?>" onkeyup="filterSlQuestions()">
          <?php if (empty($slQuestions)): ?>
            <p class="text-muted"><?= t('no_questions_db') ?></p>
          <?php else: ?>
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
              <table class="table table-sm table-hover" id="slTable">
                <thead>
                  <tr>
                    <th style="width:50px"><input type="checkbox" id="slSelectAll" onchange="document.querySelectorAll('.sl-cb').forEach(c=>c.checked=this.checked)"></th>
                    <th>ID</th><th>Question</th><th>Discipline</th><th>Current Topic</th>
                  </tr>
                </thead>
                <tbody id="slTableBody">
                  <?php foreach ($slQuestions as $q): ?>
                    <tr data-q="<?= h(mb_strtolower($q['question_text'] ?? '')) ?>" data-d="<?= h(mb_strtolower($q['discipline_name'] ?? '')) ?>" data-t="<?= h(mb_strtolower($q['syllabus_title'] ?? '')) ?>">
                      <td><input type="checkbox" name="question_ids[]" value="<?= (int)$q['question_id'] ?>" class="sl-cb" style="transform:scale(1.2);cursor:pointer"></td>
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
        <button type="submit" class="btn btn-primary" <?= empty($slQuestions) ? 'disabled' : '' ?>><?= t('link_selected') ?></button>
      </form>
      <script>
      function filterSlQuestions() {
        const q = document.getElementById('slSearch').value.toLowerCase();
        document.querySelectorAll('#slTableBody tr').forEach(r => {
          r.style.display = (!q || r.dataset.q.includes(q) || r.dataset.d.includes(q) || r.dataset.t.includes(q)) ? '' : 'none';
        });
      }
      </script>
      <?php endif; ?>
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
    if (typeof Chart === 'undefined') return;
    Object.values(Chart.instances).forEach(c => c.destroy());
    
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
    if (qualityCtx && chartData.questionQuality) {
        try {
            new Chart(qualityCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.questionQuality.labels,
                    datasets: [{
                        data: chartData.questionQuality.data,
                        backgroundColor: chartData.questionQuality.backgroundColors,
                        borderColor: chartData.questionQuality.colors,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 13,
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#475569',
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
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
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.correlation.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                beginAtZero: true
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Значение: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating correlation chart:', error);
        }
    }

    const studentsCtx = document.getElementById('studentsChart');
    if (studentsCtx && chartData.studentRisk) {
        try {
            new Chart(studentsCtx, {
                type: 'bar',
                data: {
                    labels: chartData.studentRisk.labels,
                    datasets: [{
                        data: chartData.studentRisk.data,
                        backgroundColor: chartData.studentRisk.backgroundColors,
                        borderColor: chartData.studentRisk.colors,
                        borderWidth: 2,
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.studentRisk.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            type: 'logarithmic',
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                callback: function(value, index, values) {
                                    if (value === 1 || value === 10 || value === 100 || value === 1000) {
                                        return value.toString();
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Студенты: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating students chart:', error);
        }
    }

    const validationCtx = document.getElementById('validationChart');
    if (validationCtx && chartData.validation) {
        try {
            new Chart(validationCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.validation.labels,
                    datasets: [{
                        data: chartData.validation.data,
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(156, 163, 175, 0.8)'
                        ],
                        borderColor: [
                            'rgba(34, 197, 94, 1)',
                            'rgba(239, 68, 68, 1)',
                            'rgba(156, 163, 175, 1)'
                        ],
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 13,
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#475569',
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
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
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.semantic.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                beginAtZero: true
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Сходство: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating semantic chart:', error);
        }
    }

    const criteriaCtx = document.getElementById('criteriaChart');
    if (criteriaCtx && chartData.criteria) {
        try {
            new Chart(criteriaCtx, {
                type: 'doughnut',
                data: {
                    labels: chartData.criteria.labels,
                    datasets: [{
                        data: chartData.criteria.data,
                        backgroundColor: [
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(139, 92, 246, 0.8)'
                        ],
                        borderColor: [
                            'rgba(245, 158, 11, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(16, 185, 129, 1)',
                            'rgba(139, 92, 246, 1)'
                        ],
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 13,
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#475569',
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } catch (error) {
            console.error('Error creating criteria chart:', error);
        }
    }

    const answersCtx = document.getElementById('answersChart');
    if (answersCtx && chartData.plagiarism) {
        try {
            new Chart(answersCtx, {
                type: 'bar',
                data: {
                    labels: chartData.plagiarism.labels,
                    datasets: [{
                        data: chartData.plagiarism.data,
                        backgroundColor: 'rgba(34, 197, 94, 0.8)',
                        borderColor: 'rgba(34, 197, 94, 1)',
                        borderWidth: 2,
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: 'rgba(34, 197, 94, 1)',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                beginAtZero: true
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Ответы: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
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
                type: 'doughnut',
                data: {
                    labels: chartData.validation.labels,
                    datasets: [{
                        data: chartData.validation.data,
                        backgroundColor: chartData.validation.backgroundColors,
                        borderColor: chartData.validation.colors,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 13,
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#475569',
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } catch (error) {
            console.error('Error creating validation tab chart:', error);
        }
    }

    const qualityCtxTab = document.getElementById('qualityChartTab');
    if (qualityCtxTab && chartData.questionQuality) {
        try {
            new Chart(qualityCtxTab, {
                type: 'doughnut',
                data: {
                    labels: chartData.questionQuality.labels,
                    datasets: [{
                        data: chartData.questionQuality.data,
                        backgroundColor: chartData.questionQuality.backgroundColors,
                        borderColor: chartData.questionQuality.colors,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 13,
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#475569',
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
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
                type: 'bar',
                data: {
                    labels: chartData.correlation.labels,
                    datasets: [{
                        data: chartData.correlation.data,
                        backgroundColor: chartData.correlation.backgroundColors,
                        borderColor: chartData.correlation.colors,
                        borderWidth: 2,
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.correlation.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                beginAtZero: true
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Корреляция: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating correlation tab chart:', error);
        }
    }

    const studentsCtxTab = document.getElementById('studentsChartTab');
    if (studentsCtxTab && chartData.studentRisk) {
        try {
            new Chart(studentsCtxTab, {
                type: 'bar',
                data: {
                    labels: chartData.studentRisk.labels,
                    datasets: [{
                        data: chartData.studentRisk.data,
                        backgroundColor: chartData.studentRisk.backgroundColors,
                        borderColor: chartData.studentRisk.colors,
                        borderWidth: 2,
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.studentRisk.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            type: 'logarithmic',
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                callback: function(value, index, values) {
                                    if (value === 1 || value === 10 || value === 100 || value === 1000) {
                                        return value.toString();
                                    }
                                    return null;
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Студенты: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
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
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.semantic.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                beginAtZero: true
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Сходство: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error creating semantic tab chart:', error);
        }
    }

    const criteriaCtxTab = document.getElementById('criteriaChartTab');
    if (criteriaCtxTab && chartData.criteria) {
        try {
            new Chart(criteriaCtxTab, {
                type: 'doughnut',
                data: {
                    labels: chartData.criteria.labels,
                    datasets: [{
                        data: chartData.criteria.data,
                        backgroundColor: chartData.criteria.backgroundColors,
                        borderColor: chartData.criteria.colors,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                font: {
                                    size: 13,
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#475569',
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 800,
                        easing: 'easeInOutQuart'
                    }
                }
            });
        } catch (error) {
            console.error('Error creating criteria tab chart:', error);
        }
    }

    const answersCtxTab = document.getElementById('answersChartTab');
    if (answersCtxTab && chartData.plagiarism) {
        try {
            new Chart(answersCtxTab, {
                type: 'bar',
                data: {
                    labels: chartData.plagiarism.labels,
                    datasets: [{
                        data: chartData.plagiarism.data,
                        backgroundColor: chartData.plagiarism.backgroundColors,
                        borderColor: chartData.plagiarism.colors,
                        borderWidth: 2,
                        borderRadius: 12,
                        borderSkipped: false,
                        hoverBackgroundColor: chartData.plagiarism.colors,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: { 
                                    size: 12, 
                                    family: 'Inter',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10
                            }
                        },
                        y: {
                            grid: {
                                color: 'rgba(226, 232, 240, 0.6)',
                                drawBorder: false,
                                borderDash: [3, 3]
                            },
                            ticks: {
                                font: { 
                                    size: 11, 
                                    family: 'JetBrains Mono',
                                    weight: '500'
                                },
                                color: '#64748b',
                                padding: 10,
                                beginAtZero: true
                            },
                            beginAtZero: true
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(30, 41, 59, 0.9)',
                            titleFont: { size: 14, family: 'Inter', weight: '600' },
                            bodyFont: { size: 12, family: 'Inter' },
                            padding: 12,
                            cornerRadius: 8,
                            displayColors: false,
                            callbacks: {
                                label: function(context) {
                                    return `Ответы: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 800,
                        easing: 'easeInOutQuart',
                        delay: (context) => {
                            let delay = 0;
                            if (context.type === 'data' && context.mode === 'default') {
                                delay = context.dataIndex * 100;
                            }
                            return delay;
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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

    $xmlContent = preg_replace('/<\/w:p>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/w:tr>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/w:tc>/i', "\t", $xmlContent);
    $xmlContent = preg_replace('/<w:tab[^>]*\/>/i', "\t", $xmlContent);
    $xmlContent = strip_tags($xmlContent);
    $xmlContent = html_entity_decode($xmlContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return normalizeStructuredText($xmlContent);
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

    $xmlContent = preg_replace('/<\/text:p>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/table:table-row>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/table:table-cell>/i', "\t", $xmlContent);
    $text = strip_tags($xmlContent);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return normalizeStructuredText($text);
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
    $lines = splitStructuredLines($text);
    $collectedTitles = [];
    $insidePlan = false;

    for ($index = 0; $index < count($lines); $index++) {
        $line = $lines[$index];

        if (preg_match('/(тематический план|тақырыптық жоспар|план дисциплины|пәннің тақырыптық жоспары)/ui', $line)) {
            $insidePlan = true;
            continue;
        }

        if ($insidePlan && preg_match('/^(приложение\s*\d+|қосымша\s*\d+|всего[:]?|барлығы[:]?)/ui', $line)) {
            if (count($collectedTitles) >= 3) {
                break;
            }
        }

        if (!$insidePlan) {
            continue;
        }

        if (!isTopicNumberLine($line)) {
            continue;
        }

        $topicLines = [];
        for ($cursor = $index + 1; $cursor < count($lines); $cursor++) {
            $candidateLine = $lines[$cursor];

            if (isTopicNumberLine($candidateLine) || isTopicBreakLine($candidateLine)) {
                break;
            }

            if (isPureNumericLine($candidateLine) || isTopicTaskLine($candidateLine) || isTopicHeaderLine($candidateLine)) {
                continue;
            }

            $cleanedLine = trim($candidateLine, " \t\n\r\0\x0B-–—.;,");
            if ($cleanedLine === '') {
                continue;
            }

            $topicLines[] = $cleanedLine;

            if (count($topicLines) >= 3) {
                break;
            }
        }

        $topicTitle = buildTopicTitle($topicLines);
        if ($topicTitle !== '') {
            $collectedTitles[] = $topicTitle;
        }
    }

    $collectedTitles = deduplicateTextValues($collectedTitles);

    $topics = [];
    $index = 1;
    foreach ($collectedTitles as $title) {
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
    $lines = splitStructuredLines($text);
    $questions = [];
    $insideQuestionBlock = false;
    $questionMarkers = [
        'контрольно-измерительные средства',
        'контрольно измерительные средства',
        'приложение 2',
        'примеры заданий',
        'экзаменационные вопросы',
        'вопросы',
        'сұрақтар',
        'сұрақ-жауап',
        'сұрақ жауап',
    ];

    foreach ($lines as $line) {
        $normalizedLine = mb_strtolower($line, 'UTF-8');

        foreach ($questionMarkers as $marker) {
            if (mb_strpos($normalizedLine, $marker, 0, 'UTF-8') !== false) {
                $insideQuestionBlock = true;
                continue 2;
            }
        }

        if (!$insideQuestionBlock) {
            continue;
        }

        if (preg_match('/^(приложение\s*[345]|қосымша\s*[345]|тематический план|тақырыптық жоспар)/ui', $line)) {
            break;
        }

        $candidate = cleanQuestionCandidate($line);
        if ($candidate === '') {
            continue;
        }

        if (preg_match('/^(?:\d+|[а-яәіңғүұқөһa-z])[\.\)]\s+/ui', $candidate)) {
            $number = extractLeadingNumber($candidate);
            $textValue = trim(preg_replace('/^(?:\d+|[а-яәіңғүұқөһa-z])[\.\)]\s+/ui', '', $candidate));
            if (mb_strlen($textValue, 'UTF-8') >= 25) {
                $questions[] = [
                    'text' => $textValue,
                    'type' => 'syllabus_question',
                    'number' => $number,
                ];
            }
        }
    }

    if (!$questions) {
        $tailLines = array_slice($lines, (int)max(0, count($lines) * 0.65));
        foreach ($tailLines as $line) {
            $candidate = cleanQuestionCandidate($line);
            if ($candidate === '') {
                continue;
            }

            if (preg_match('/^\d+[\.\)]\s+/u', $candidate)) {
                $number = extractLeadingNumber($candidate);
                $textValue = trim(preg_replace('/^\d+[\.\)]\s+/u', '', $candidate));
                if (mb_strlen($textValue, 'UTF-8') >= 40) {
                    $questions[] = [
                        'text' => $textValue,
                        'type' => 'syllabus_question',
                        'number' => $number,
                    ];
                }
            }
        }
    }

    return deduplicateStructuredItems($questions, 'text');
}

function normalizeStructuredText(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace("\u{00A0}", ' ', $text);
    $text = preg_replace("/[ \t]+/u", ' ', $text);
    $text = preg_replace("/\n{2,}/u", "\n", $text);
    $lines = preg_split('/\n/u', $text) ?: [];
    $cleanLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $cleanLines[] = $line;
        }
    }

    return implode("\n", $cleanLines);
}

function splitStructuredLines(string $text): array
{
    $normalizedText = normalizeStructuredText($text);
    return preg_split('/\n/u', $normalizedText) ?: [];
}

function cleanTopicCandidate(string $line): string
{
    if (!preg_match('/^\s*\d+[\.\)]/u', $line)) {
        return '';
    }

    $line = preg_replace('/^\s*\d+[\.\)]\s*/u', '', $line);
    $line = preg_replace('/^(?:[A-ZА-ЯЁӘІҢҒҮҰҚӨҺ]{1,8}|[A-ZА-ЯЁӘІҢҒҮҰҚӨҺ]{1,8}\s+[A-ZА-ЯЁӘІҢҒҮҰҚӨҺ]{1,8})\s+/u', '', $line);
    $line = preg_split('/(?:Приложение|Қосымша|Тақырыпты|Тақырып бойынша|Ситуациялық|Ауызша|Жағдайлық|Microsoft Power Point|Электронды тасымалдаудағы|Электронды әдебиетпен)/ui', $line)[0] ?? $line;
    $line = preg_replace('/\s+\d+(?:\s+\d+){0,6}\s*$/u', '', $line);
    $line = trim($line, " \t\n\r\0\x0B-–—.;,");

    return mb_strlen($line, 'UTF-8') >= 12 ? $line : '';
}

function isTopicNumberLine(string $line): bool
{
    return (bool)preg_match('/^\d+\s*[\.\)]\s*$/u', trim($line));
}

function isPureNumericLine(string $line): bool
{
    return (bool)preg_match('/^\d+(?:\s+\d+){0,6}$/u', trim($line));
}

function isTopicTaskLine(string $line): bool
{
    return (bool)preg_match('/(тақырыпты|ситуац|ауызша|жағдайлық|презентац|powerpoint|moodle|алгоритм|электронды|приложение|қосымша|сұрақтарына жауап|оқытушы жетекшілігімен)/ui', $line);
}

function isTopicHeaderLine(string $line): bool
{
    return (bool)preg_match('/^(№|п\/п|бөлім|раздел|тақырып|тема|тапсырмалар|задания|дәріс|лекции|пз|сөож|сро|ср оп|па|всего часов|барылық сағат|жалпы)$/ui', trim($line));
}

function isTopicBreakLine(string $line): bool
{
    return (bool)preg_match('/^(аралық бақылау|итоговый контроль|всего[:]?|барлығы[:]?|приложение\s*\d+|қосымша\s*\d+)/ui', trim($line));
}

function buildTopicTitle(array $lines): string
{
    if (!$lines) {
        return '';
    }

    if (count($lines) >= 2 && isLikelyTopicSectionLabel($lines[0])) {
        array_shift($lines);
    }

    if (!$lines) {
        return '';
    }

    $titleParts = [];
    foreach ($lines as $line) {
        if (isLikelyTopicSectionLabel($line) && $titleParts) {
            continue;
        }

        $titleParts[] = $line;

        if (count($titleParts) >= 2) {
            break;
        }
    }

    $title = trim(implode(' ', $titleParts));
    $title = preg_replace('/\s+/u', ' ', $title);
    $title = trim((string)$title, " \t\n\r\0\x0B-–—.;,");

    return mb_strlen((string)$title, 'UTF-8') >= 12 ? (string)$title : '';
}

function isLikelyTopicSectionLabel(string $line): bool
{
    $line = trim($line);

    if ($line === '') {
        return false;
    }

    if (preg_match('/^(жж|онк|био|пат|кредит\s*\d+)/ui', $line)) {
        return true;
    }

    return (bool)preg_match('/^(патологическая физиология|патологическая анатомия|биологическая химия|жүйке жүйесі|патологиялық физиология|патологиялық анатомия)$/ui', $line);
}

function cleanQuestionCandidate(string $line): string
{
    $line = trim($line);
    $line = preg_replace('/\s+/u', ' ', $line);
    return $line;
}

function deduplicateTextValues(array $values): array
{
    $uniqueValues = [];
    $seen = [];

    foreach ($values as $value) {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        $uniqueValues[] = trim($value);
    }

    return $uniqueValues;
}

function deduplicateStructuredItems(array $items, string $field): array
{
    $uniqueItems = [];
    $seen = [];

    foreach ($items as $item) {
        $value = trim((string)($item[$field] ?? ''));
        $normalized = mb_strtolower($value, 'UTF-8');
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        $uniqueItems[] = $item;
    }

    return $uniqueItems;
}

function extractLeadingNumber(string $line): ?int
{
    if (preg_match('/^(\d+)[\.\)]/u', $line, $match)) {
        return (int)$match[1];
    }

    return null;
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
