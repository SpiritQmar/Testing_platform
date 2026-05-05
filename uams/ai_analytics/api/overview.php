<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {
    require_login();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

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

try {

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
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = [
                'questions' => (int)$result['questions'],
                'students' => (int)$result['students'],
                'attempts' => (int)$result['attempts'],
                'avg_score' => round((float)$result['avg_score'], 2)
            ];
        }

        $prevImportId = null;
        foreach ($imports as $idx => $imp) {
            if ((int)$imp['import_id'] === $importId && isset($imports[$idx + 1])) {
                $prevImportId = (int)$imports[$idx + 1]['import_id'];
                break;
            }
        }

        if ($prevImportId) {
            $stmt = $pdo->prepare("
                SELECT
                    COUNT(DISTINCT question_id) as questions,
                    COUNT(DISTINCT student_id) as students,
                    COUNT(*) as attempts,
                    AVG(score_after_appeal) as avg_score
                FROM raw_exam_results
                WHERE import_id = ?
            ");
            $stmt->execute([$prevImportId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result) {
                $previousStats = [
                    'questions' => (int)$result['questions'],
                    'students' => (int)$result['students'],
                    'attempts' => (int)$result['attempts'],
                    'avg_score' => round((float)$result['avg_score'], 2)
                ];
            }
        }
    } catch (Throwable $e) {
        error_log("Error fetching stats: " . $e->getMessage());
    }
}

function calculateChange($current, $previous) {
    if ($previous == 0) {
        return ['change' => 0, 'isPositive' => true];
    }
    $change = round((($current - $previous) / $previous) * 100, 1);
    return ['change' => abs($change), 'isPositive' => $change >= 0];
}

$questionsChange = calculateChange($stats['questions'], $previousStats['questions']);
$studentsChange = calculateChange($stats['students'], $previousStats['students']);
$attemptsChange = calculateChange($stats['attempts'], $previousStats['attempts']);
$scoreChange = calculateChange($stats['avg_score'], $previousStats['avg_score']);

$chartData = ['labels' => [], 'avgScores' => [], 'attempts' => []];
if ($importId) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                DATE(created_at) as date,
                AVG(score_after_appeal) as avg_score,
                COUNT(*) as attempts
            FROM raw_exam_results
            WHERE import_id = ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 7
        ");
        $stmt->execute([$importId]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach (array_reverse($results) as $row) {
            $chartData['labels'][] = date('d.m', strtotime($row['date']));
            $chartData['avgScores'][] = round((float)$row['avg_score'], 2);
            $chartData['attempts'][] = (int)$row['attempts'];
        }
    } catch (Throwable $e) {
        error_log("Error fetching chart data: " . $e->getMessage());
    }
}

$additionalStats = [
    'total_documents' => 0,
    'data_processed' => 0,
    'active_queries' => 0,
    'team_members' => 0
];

$previousAdditionalStats = [
    'total_documents' => 0,
    'data_processed' => 0,
    'active_queries' => 0,
    'team_members' => 0
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM imports_log WHERE import_type = 'exam_results_upload'");
    $additionalStats['total_documents'] = (int)$stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(rows_imported) as total FROM imports_log WHERE import_type = 'exam_results_upload'");
    $totalRows = (int)$stmt->fetch()['total'];
    $additionalStats['data_processed'] = round($totalRows / 1000, 1);

    $stmt = $pdo->query("SELECT COUNT(DISTINCT question_id) as total FROM hier_questions");
    $additionalStats['active_queries'] = (int)$stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
    $additionalStats['team_members'] = (int)$stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM imports_log WHERE import_type = 'exam_results_upload' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $previousAdditionalStats['total_documents'] = (int)$stmt->fetch()['total'];

    $stmt = $pdo->query("SELECT SUM(rows_imported) as total FROM imports_log WHERE import_type = 'exam_results_upload' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $prevTotalRows = (int)$stmt->fetch()['total'];
    $previousAdditionalStats['data_processed'] = round($prevTotalRows / 1000, 1);

    $stmt = $pdo->query("SELECT COUNT(DISTINCT hq.question_id) as total FROM hier_questions hq INNER JOIN raw_exam_results r ON r.question_id = hq.question_id INNER JOIN imports_log il ON il.import_id = r.import_id WHERE il.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $previousAdditionalStats['active_queries'] = (int)$stmt->fetch()['total'];

    $previousAdditionalStats['team_members'] = $additionalStats['team_members'];
} catch (Throwable $e) {
    error_log("Error fetching additional stats: " . $e->getMessage());
}

$documentsChange = calculateChange($additionalStats['total_documents'], $previousAdditionalStats['total_documents']);
$dataChange = calculateChange($additionalStats['data_processed'], $previousAdditionalStats['data_processed']);
$queriesChange = calculateChange($additionalStats['active_queries'], $previousAdditionalStats['active_queries']);
$membersChange = calculateChange($additionalStats['team_members'], $previousAdditionalStats['team_members']);

echo json_encode([
    'success' => true,
    'stats' => [
        'questions' => $stats['questions'],
        'students' => $stats['students'],
        'attempts' => $stats['attempts'],
        'avg_score' => $stats['avg_score'],
    ],
    'changes' => [
        'questions' => $questionsChange,
        'students' => $studentsChange,
        'attempts' => $attemptsChange,
        'avg_score' => $scoreChange,
    ],
    'additionalStats' => $additionalStats,
    'additionalChanges' => [
        'total_documents' => $documentsChange,
        'data_processed' => $dataChange,
        'active_queries' => $queriesChange,
        'team_members' => $membersChange,
    ],
    'chartData' => $chartData,
    'importId' => $importId,
    'imports' => $imports
]);
} catch (Throwable $e) {
    error_log("Overview API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
