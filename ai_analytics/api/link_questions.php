<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST only']);
    exit;
}

verify_csrf_or_fail();

try {
    $syllabusTopicId = (int)($_POST['syllabus_topic_id'] ?? 0);
    $questionIds     = $_POST['question_ids'] ?? [];

    if ($syllabusTopicId <= 0 || empty($questionIds)) {
        echo json_encode(['error' => 'Выберите тему и хотя бы один вопрос']);
        exit;
    }

    $importId = $_SESSION['ai_analytics_import_id'] ?? null;

    $topicStmt = $pdo->prepare("SELECT title FROM ai_syllabus_topics WHERE syllabus_topic_id = ?");
    $topicStmt->execute([$syllabusTopicId]);
    $topicTitle = $topicStmt->fetchColumn() ?: '';

    $stmt = $pdo->prepare("UPDATE hier_questions SET syllabus_topic_id = :topic_id WHERE question_id = :qid");
    $linkedCount = 0;
    foreach ($questionIds as $qid) {
        $stmt->execute([':topic_id' => $syllabusTopicId, ':qid' => (int)$qid]);
        $linkedCount += $stmt->rowCount();
    }

    if ($importId) {
        try {
            $pdo->exec("DELETE FROM question_validation_cache WHERE import_id = " . (int)$importId);
            $pdo->exec("DELETE FROM question_semantic_cache WHERE import_id = " . (int)$importId);
        } catch (Throwable $ce) {}
    }

    echo json_encode([
        'success'     => true,
        'linked'      => $linkedCount,
        'topic_title' => $topicTitle,
        'topic_id'    => $syllabusTopicId,
    ]);
} catch (Throwable $e) {
    error_log("Link questions error: " . $e->getMessage());
    echo json_encode(['error' => $e->getMessage()]);
}
