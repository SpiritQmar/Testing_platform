<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../services/AIAnalyticsService.php';
require_once __DIR__ . '/../services/RuleClassifierService.php';
require_once __DIR__ . '/../services/TfIdfService.php';
require_once __DIR__ . '/../services/EmbeddingsService.php';

header('Content-Type: application/json');

try {
    require_login();
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

try {
    $importId = $_SESSION['ai_analytics_import_id'] ?? null;

    if ($importId) {
        $check = $pdo->prepare("SELECT import_id FROM imports_log WHERE import_id=? AND import_type='exam_results_upload'");
        $check->execute([$importId]);
        if (!$check->fetchColumn()) $importId = null;
    }

    if (!$importId) {
        $stmt = $pdo->query("SELECT import_id FROM imports_log WHERE import_type='exam_results_upload' AND rows_imported > 0 ORDER BY import_id DESC LIMIT 1");
        $importId = $stmt->fetchColumn();
        if ($importId) {
            $_SESSION['ai_analytics_import_id'] = $importId;
        }
    }

    if (!$importId) {
        echo json_encode(['success' => false, 'error' => 'No import ID found']);
        exit;
    }

    $ai = new AIAnalyticsService($pdo, (int)$importId);
    $embeddingsService = new EmbeddingsService();
    $tfidf = new TfIdfService($pdo, $embeddingsService, (int)$importId);

    
    $metrics = $ai->getQuestionQualityMetrics();
    $hard = 0; $easy = 0; $normal = 0;
    if (!empty($metrics)) {
        foreach ($metrics as $m) {
            $flag = $m['flag'] ?? '';
            if ($flag === 'too_hard') $hard++;
            elseif ($flag === 'too_easy') $easy++;
            else $normal++;
        }
    }

    
    $corr = $ai->getItemTotalCorrelation();
    $corr_weak = 0;
    $corr_good = 0;
    if (!empty($corr)) {
        foreach ($corr as $c) {
            if (($c['flag'] ?? '') === 'low_corr') $corr_weak++;
            else $corr_good++;
        }
    }

    
    $patterns = $ai->getStudentRiskPatterns(1, 10000);
    $st_ok = 0; $st_systemic = 0; $st_spot = 0;
    if (!empty($patterns)) {
        foreach ($patterns as $p) {
            $type = $p['pattern_type'] ?? '';
            if ($type === 'ok') $st_ok++;
            elseif ($type === 'systemic') $st_systemic++;
            elseif ($type === 'spot') $st_spot++;
        }
    }

    
    $validation = $ai->getValidationOverview();
    $val_ok = 0; $val_warning = 0;
    if (!empty($validation)) {
        foreach ($validation as $v) {
            if (isset($v['status']) && $v['status'] === 'ok') $val_ok++;
            else $val_warning++;
        }
    }

    
    $semantic = $ai->getSemanticAnalysis();
    $sem_high = 0; $sem_medium = 0; $sem_low = 0;
    if (!empty($semantic)) {
        foreach ($semantic as $s) {
            $alignment = $s['alignment_level'] ?? '';
            if ($alignment === 'high') $sem_high++;
            elseif ($alignment === 'medium') $sem_medium++;
            else $sem_low++;
        }
    }

    
    $criteria = $ai->getCriteriaPerformanceAnalysis();
    $cr_exc = 0; $cr_good = 0; $cr_sat = 0; $cr_poor = 0;
    if (!empty($criteria)) {
        foreach ($criteria as $c) {
            $lvl = $c['performance_level'] ?? '';
            if ($lvl === 'excellent') $cr_exc++;
            elseif ($lvl === 'good') $cr_good++;
            elseif ($lvl === 'satisfactory') $cr_sat++;
            else $cr_poor++;
        }
    }

    
    $answersAnalysis = $ai->getStudentAnswerAnalysis();
    $plagiarismHigh = 0; $plagiarismLow = 0;
    if (!empty($answersAnalysis)) {
        foreach ($answersAnalysis as $a) {
            if ($a['plagiarism_rate'] > 10) $plagiarismHigh++;
            else $plagiarismLow++;
        }
    }

    $finalData = [
        'success' => true,
        'import_id' => (int)$importId,
        'quality' => [
            'labels' => ['Сложный', 'Легкий', 'Норма'],
            'datasets' => [['data' => [$hard, $easy, $normal], 'backgroundColor' => ['#ef4444', '#f59e0b', '#10b981']]]
        ],
        'correlation' => [
            'labels' => ['Слабая корреляция', 'Хорошая корреляция'],
            'datasets' => [['data' => [$corr_weak, $corr_good], 'backgroundColor' => ['#ef4444', '#10b981']]]
        ],
        'studentRisk' => [
            'labels' => ['Норма', 'Системные проблемы', 'Локальные провалы'],
            'datasets' => [['data' => [$st_ok, $st_systemic, $st_spot], 'backgroundColor' => ['#10b981', '#ef4444', '#f59e0b']]]
        ],
        'validation' => [
            'labels' => ['Корректно', 'Предупреждение'],
            'datasets' => [['data' => [$val_ok, $val_warning], 'backgroundColor' => ['#10b981', '#f59e0b']]]
        ],
        'semantic' => [
            'labels' => ['Высокое сходство', 'Среднее сходство', 'Низкое сходство'],
            'datasets' => [['data' => [$sem_high, $sem_medium, $sem_low], 'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444']]]
        ],
        'criteria' => [
            'labels' => ['Отлично', 'Хорошо', 'Удовлетворительно', 'Плохо'],
            'datasets' => [['data' => [$cr_exc, $cr_good, $cr_sat, $cr_poor], 'backgroundColor' => ['#10b981', '#3b82f6', '#f59e0b', '#ef4444']]]
        ],
        'plagiarism' => [
            'labels' => ['Низкий плагиат', 'Высокий плагиат'],
            'datasets' => [['data' => [$plagiarismLow, $plagiarismHigh], 'backgroundColor' => ['#10b981', '#ef4444']]]
        ]
    ];

    ob_end_clean();
    echo json_encode($finalData);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

