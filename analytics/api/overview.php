<?php
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

try {
    $imports = $pdo->query(
        "SELECT import_id FROM imports_log WHERE import_type='exam_results_upload' AND rows_imported>0 ORDER BY import_id DESC LIMIT 2"
    )->fetchAll(PDO::FETCH_COLUMN);

    $currentId = isset($imports[0]) ? (int)$imports[0] : null;
    $prevId    = isset($imports[1]) ? (int)$imports[1] : null;

    $fetchStats = function(?int $importId) use ($pdo): array {
        if (!$importId) return ['questions' => 0, 'students' => 0, 'attempts' => 0, 'avg_score' => 0.0];
        $st = $pdo->prepare(
            "SELECT COUNT(DISTINCT question_id) AS questions,
                    COUNT(DISTINCT student_id) AS students,
                    COUNT(*) AS attempts,
                    ROUND(AVG(received_score), 1) AS avg_score
             FROM raw_exam_results
             WHERE import_id = :imp AND received_score IS NOT NULL"
        );
        $st->execute([':imp' => $importId]);
        $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'questions' => (int)($r['questions'] ?? 0),
            'students'  => (int)($r['students'] ?? 0),
            'attempts'  => (int)($r['attempts'] ?? 0),
            'avg_score' => (float)($r['avg_score'] ?? 0.0),
        ];
    };

    $calcChange = function(float $cur, float $prev): array {
        if ($prev == 0.0) return ['isPositive' => true, 'change' => 0.0];
        return ['isPositive' => $cur >= $prev, 'change' => round(abs(($cur - $prev) / $prev * 100), 1)];
    };

    $current = $fetchStats($currentId);
    $prev    = $fetchStats($prevId);

    $changes = [
        'questions' => $calcChange((float)$current['questions'], (float)$prev['questions']),
        'students'  => $calcChange((float)$current['students'],  (float)$prev['students']),
        'attempts'  => $calcChange((float)$current['attempts'],  (float)$prev['attempts']),
        'avg_score' => $calcChange($current['avg_score'],        $prev['avg_score']),
    ];

    $totalDocs       = (int)$pdo->query("SELECT COUNT(*) FROM imports_log WHERE rows_imported > 0")->fetchColumn();
    $totalRows       = (int)$pdo->query("SELECT COUNT(*) FROM raw_exam_results")->fetchColumn();
    $dataMb          = max(0.1, round($totalRows * 0.0005, 1));
    $activeQuestions = (int)$pdo->query("SELECT COUNT(*) FROM hier_questions WHERE syllabus_topic_id IS NOT NULL")->fetchColumn();

    $teamMembers = 0;
    try {
        $teamMembers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    } catch (Throwable $e) {}

    $zero = ['isPositive' => true, 'change' => 0.0];

    echo json_encode([
        'success' => true,
        'stats'   => $current,
        'changes' => $changes,
        'additionalStats' => [
            'total_documents' => $totalDocs,
            'data_processed'  => $dataMb,
            'active_queries'  => $activeQuestions,
            'team_members'    => $teamMembers,
        ],
        'additionalChanges' => [
            'total_documents' => $zero,
            'data_processed'  => $zero,
            'active_queries'  => $zero,
            'team_members'    => $zero,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
