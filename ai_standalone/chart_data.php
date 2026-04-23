<?php
error_reporting(0);
ini_set('display_errors', 0);
session_start();
ob_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/AIAnalyticsService.php';
require_once __DIR__ . '/services/RuleClassifierService.php';
require_once __DIR__ . '/services/TfIdfService.php';

header('Content-Type: application/json');

try {
    $importId = $_SESSION['ai_analytics_import_id'] ?? null;
    
    if (!$importId) {
        echo json_encode(['error' => 'No import ID']);
        exit;
    }
    
    $ai = new AIAnalyticsService($pdo, $importId);
    $embeddingsService = new EmbeddingsService('http://localhost:8000', true);
    $tfidf = new TfIdfService($pdo, $embeddingsService);
    
    $metrics = $ai->getQuestionQualityMetrics();
    $hard = 0;
    $easy = 0;
    $normal = 0;
    if (!empty($metrics)) {
        foreach ($metrics as $m) {
            $flag = $m['flag'] ?? '';
            if ($flag === 'too_hard') $hard++;
            elseif ($flag === 'too_easy') $easy++;
            else $normal++;
        }
    }
    
    $corr = $ai->getItemTotalCorrelation();
    $weak = 0;
    $medium = 0;
    $strong = 0;
    if (!empty($corr)) {
        foreach ($corr as $c) {
            $flag = $c['flag'] ?? '';
            if ($flag === 'low_corr') $weak++;
            else $strong++;
        }
    }
    
    $patterns = $ai->getStudentRiskPatterns(1, 1000);
    $ok = 0;
    $systemic = 0;
    $spot = 0;
    if (!empty($patterns)) {
        foreach ($patterns as $p) {
            $patternType = $p['pattern_type'] ?? '';
            if ($patternType === 'ok') $ok++;
            elseif ($patternType === 'systemic') $systemic++;
            elseif ($patternType === 'spot') $spot++;
        }
    }
    
    $validation = $ai->getValidationOverview();
    $val_ok = 0;
    $warning = 0;
    if (!empty($validation)) {
        foreach ($validation as $v) {
            if (isset($v['status']) && $v['status'] === 'ok') $val_ok++;
            else $warning++;
        }
    }
    
    $semantic = $tfidf->analyzeQuestionSyllabusAlignment();
    $high = 0;
    $medium = 0;
    $low = 0;
    if (!empty($semantic)) {
        foreach ($semantic as $s) {
            $alignment = $s['alignment_analysis']['alignment_level'] ?? '';
            if ($alignment === 'high') $high++;
            elseif ($alignment === 'medium') $medium++;
            else $low++;
        }
    }

    $data = [
        'quality' => [
            'labels' => ['Сложный', 'Легкий', 'Норма'],
            'data' => [$hard, $easy, $normal],
            'colors' => ['#dc2626', '#d97706', '#059669'],
            'backgroundColors' => ['#fee2e2', '#fef3c7', '#d1fae5']
        ],
        'correlation' => [
            'labels' => ['Слабая корреляция', 'Хорошая корреляция'],
            'data' => [$weak, $strong],
            'colors' => ['#dc2626', '#059669'],
            'backgroundColors' => ['#fee2e2', '#d1fae5']
        ],
        'students' => [
            'labels' => ['OK', 'Системные проблемы', 'Локальные провалы'],
            'data' => [$ok, $systemic, $spot],
            'colors' => ['#059669', '#dc2626', '#d97706'],
            'backgroundColors' => ['#d1fae5', '#fee2e2', '#fef3c7']
        ],
        'validation' => [
            'labels' => ['OK', 'Warning'],
            'data' => [$val_ok, $warning],
            'colors' => ['#059669', '#d97706'],
            'backgroundColors' => ['#d1fae5', '#fef3c7']
        ],
        'semantic' => [
            'labels' => ['Высокое сходство', 'Среднее сходство', 'Низкое сходство'],
            'data' => [$high, $medium, $low],
            'colors' => ['#059669', '#d97706', '#dc2626'],
            'backgroundColors' => ['#d1fae5', '#fef3c7', '#fee2e2']
        ]
    ];
    
    ob_end_clean();
    echo json_encode($data);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
