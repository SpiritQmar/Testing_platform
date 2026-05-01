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
    $embeddingsService = new EmbeddingsService();
    $tfidf = new TfIdfService($pdo, $embeddingsService, (int)$importId);
    
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
    
    $patterns = $ai->getStudentRiskPatterns(1, 10000);
    $ok = 0;
    $systemic = 0;
    $spot = 0;
    
    $logFile = __DIR__ . '/debug_students.log';
    $logData = "=== " . date('Y-m-d H:i:s') . " ===\n";
    $logData .= "Import ID: $importId\n";
    $logData .= "Patterns count: " . count($patterns) . "\n";
    
    if (!empty($patterns)) {
        foreach ($patterns as $p) {
            $patternType = $p['pattern_type'] ?? '';
            $logData .= "Pattern: " . $patternType . " - " . json_encode($p) . "\n";
            if ($patternType === 'ok') $ok++;
            elseif ($patternType === 'systemic') $systemic++;
            elseif ($patternType === 'spot') $spot++;
        }
    }
    
    $logData .= "Counts - OK: $ok, Systemic: $systemic, Spot: $spot\n";
    $logData .= "========================\n\n";
    
    file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
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

    $criteriaAnalysis = $ai->getCriteriaPerformanceAnalysis();
    $excellent = 0;
    $good = 0;
    $satisfactory = 0;
    $poor = 0;
    if (!empty($criteriaAnalysis)) {
        foreach ($criteriaAnalysis as $c) {
            $level = $c['performance_level'] ?? '';
            if ($level === 'excellent') $excellent++;
            elseif ($level === 'good') $good++;
            elseif ($level === 'satisfactory') $satisfactory++;
            else $poor++;
        }
    }

    $answersAnalysis = $ai->getStudentAnswerAnalysis();
    $languages = [];
    $plagiarismHigh = 0;
    $plagiarismLow = 0;
    if (!empty($answersAnalysis)) {
        foreach ($answersAnalysis as $a) {
            $languages[] = $a['language'];
            if ($a['plagiarism_rate'] > 10) $plagiarismHigh++;
            else $plagiarismLow++;
        }
    }

    $chartData = [
        'questionQuality' => [
            'labels' => ['Сложный', 'Легкий', 'Норма'],
            'data' => [$hard, $easy, $normal],
            'colors' => ['#ef4444', '#f59e0b', '#10b981'],
            'backgroundColors' => ['#fecaca', '#fed7aa', '#a7f3d0']
        ],
        'correlation' => [
            'labels' => ['Слабая корреляция', 'Хорошая корреляция'],
            'data' => [$weak, $strong],
            'colors' => ['#ef4444', '#10b981'],
            'backgroundColors' => ['#fecaca', '#a7f3d0']
        ],
        'studentRisk' => [
            'labels' => ['Норма', 'Системные проблемы', 'Локальные провалы'],
            'data' => [$ok, $systemic, $spot],
            'colors' => ['#10b981', '#ef4444', '#f59e0b'],
            'backgroundColors' => ['#a7f3d0', '#fecaca', '#fed7aa']
        ],
        'validation' => [
            'labels' => ['Корректно', 'Предупреждение'],
            'data' => [$val_ok, $warning],
            'colors' => ['#10b981', '#f59e0b'],
            'backgroundColors' => ['#a7f3d0', '#fed7aa']
        ],
        'semantic' => [
            'labels' => ['Высокое сходство', 'Среднее сходство', 'Низкое сходство'],
            'data' => [$high, $medium, $low],
            'colors' => ['#10b981', '#f59e0b', '#ef4444'],
            'backgroundColors' => ['#a7f3d0', '#fed7aa', '#fecaca']
        ],
        'criteria' => [
            'labels' => ['Отлично', 'Хорошо', 'Удовлетворительно', 'Плохо'],
            'data' => [$excellent, $good, $satisfactory, $poor],
            'colors' => ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
            'backgroundColors' => ['#a7f3d0', '#dbeafe', '#fed7aa', '#fecaca']
        ],
        'plagiarism' => [
            'labels' => ['Низкий плагиат', 'Высокий плагиат'],
            'data' => [$plagiarismLow, $plagiarismHigh],
            'colors' => ['#10b981', '#ef4444'],
            'backgroundColors' => ['#a7f3d0', '#fecaca']
        ]
    ];
    
    ob_end_clean();
    echo json_encode($chartData);
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
