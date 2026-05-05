<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/db.php';

require_login();

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

function verify_csrf_or_fail() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('CSRF validation failed');
    }
}

require_once __DIR__ . '/services/AIAnalyticsService.php';
require_once __DIR__ . '/services/TfIdfService.php';
require_once __DIR__ . '/services/RuleClassifierService.php';
require_once __DIR__ . '/services/EmbeddingsService.php';

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
}

$minStudentsDiscrimination = $_SESSION['min_students_discrimination'] ?? 10;

$ai = new AIAnalyticsService($pdo, $importId);
$ai->setMinStudentsForDiscrimination($minStudentsDiscrimination);
$embeddingsService = new EmbeddingsService();
$tfidf = new TfIdfService($pdo, $embeddingsService, $importId);
$rules = new RuleClassifierService($pdo);

$section = $_GET['section'] ?? 'overview';

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'section' => $section,
    'importId' => $importId,
    'message' => 'Analytics API ready'
]);
