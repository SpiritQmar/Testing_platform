<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';

$error = null;
$success = null;
$stats = ['total_records' => 0, 'total_questions' => 0, 'total_students' => 0, 'disciplines' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['exam_export'])) {
    verify_csrf_or_fail();
    $file = $_FILES['exam_export'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'ods'])) {
            $error = 'Supported formats: CSV, ODS';
        } else {
            try {
                $data = [];
                if ($extension === 'ods') {
                    header('Location: convert_ods.php');
                    exit;
                } else {
                    $data = readCsvFile($file['tmp_name']);
                }

                if (empty($data)) {
                    throw new Exception('File is empty');
                }

                $pdo->beginTransaction();

                $stmtLog = $pdo->prepare("INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type) VALUES (:filename, :fmt, :total, 0, 'exam_results_upload')");
                $stmtLog->execute([':filename' => $file['name'], ':fmt' => $extension, ':total' => count($data)]);
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

                $inserted = 0;
                $errors = 0;
                $disciplines = [];
                $batchSize = 500;
                $batchCount = 0;

                foreach ($data as $index => $row) {
                    if ($index === 0) continue;

                    try {
                        $questionId = !empty($row['ID вопроса'] ?? $row['question_id']) && is_numeric($row['ID вопроса'] ?? $row['question_id']) ? (int)($row['ID вопроса'] ?? $row['question_id']) : null;
                        $studentId = !empty($row['ID студента'] ?? $row['student_id']) && is_numeric($row['ID студента'] ?? $row['student_id']) ? (int)($row['ID студента'] ?? $row['student_id']) : null;
                        $disciplineName = trim($row['Дисциплина'] ?? $row['discipline_name'] ?? '');
                        $courseNumber = !empty($row['Курс'] ?? $row['course_number']) && is_numeric($row['Курс'] ?? $row['course_number']) ? (int)($row['Курс'] ?? $row['course_number']) : null;
                        
                        $scoreAfter = $row['Оценка после апелляции'] ?? $row['score_after_appeal'] ?? '';
                        $receivedScore = ($scoreAfter !== '' && $scoreAfter !== '-' && is_numeric($scoreAfter)) ? (float)$scoreAfter : null;

                        if (!empty($questionId)) {
                            $stmtQuestion->execute([
                                ':qid' => $questionId,
                                ':qtext' => '',
                                ':max' => 100
                            ]);
                            $stmtHierQuestion->execute([
                                ':qid' => $questionId,
                                ':qtext' => '',
                                ':max' => 100
                            ]);
                        }

                        if (!empty($studentId)) {
                            $stmtStudent->execute([
                                ':sid' => $studentId,
                                ':course' => $courseNumber ?? 3
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
                                    ':course' => $courseNumber ?? 3,
                                    ':import_id' => $importId
                                ]);
                            }

                            if ($disciplineName && !isset($disciplines[$disciplineName])) {
                                $disciplines[$disciplineName] = true;
                            }
                        }

                        $batchCount++;
                        if ($batchCount >= $batchSize) {
                            $pdo->exec("COMMIT");
                            $pdo->exec("START TRANSACTION");
                            $batchCount = 0;
                        }

                    } catch (Throwable $e) {
                        $errors++;
                    }
                }

                $pdo->exec("COMMIT");

                $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                $stmt->execute([':i' => $inserted, ':id' => $importId]);

                foreach ($disciplines as $disciplineName => $_) {
                    try {
                        $stmt = $pdo->prepare("INSERT IGNORE INTO ai_syllabus_topics (discipline_name, course_number, topic_code, title, keywords) VALUES (:disc, 3, :code, :title, :keywords)");
                        $code = 'AUTO_' . md5($disciplineName);
                        $stmt->execute([
                            ':disc' => $disciplineName,
                            ':code' => $code,
                            ':title' => $disciplineName,
                            ':keywords' => mb_strtolower($disciplineName, 'UTF-8')
                        ]);
                    } catch (Throwable $e) {}
                }

                try {
                    $pdo->exec("
                        UPDATE hier_questions hq
                        INNER JOIN raw_exam_results r ON r.question_id = hq.question_id
                        INNER JOIN ai_syllabus_topics ast ON ast.discipline_name = r.discipline_name
                        SET hq.syllabus_topic_id = ast.syllabus_topic_id
                        WHERE hq.syllabus_topic_id IS NULL AND r.discipline_name IS NOT NULL
                    ");
                } catch (Throwable $e) {}

                $stats = [
                    'rows_imported' => $inserted,
                    'disciplines' => count($disciplines),
                    'errors' => $errors,
                ];
                $success = 'Loaded: ' . number_format($inserted) . ' records, disciplines: ' . count($disciplines) . ($errors > 0 ? ', errors: ' . $errors : '');

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $e) {}
                }
                $error = 'Error: ' . $e->getMessage();
            }
        }
    }
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM raw_exam_results");
    $stats['total_records'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(DISTINCT question_id) as count FROM raw_exam_results WHERE question_id IS NOT NULL");
    $stats['total_questions'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(DISTINCT student_id) as count FROM raw_exam_results WHERE student_id IS NOT NULL");
    $stats['total_students'] = $stmt->fetch()['count'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(DISTINCT discipline_name) as count FROM raw_exam_results WHERE discipline_name IS NOT NULL");
    $stats['disciplines'] = $stmt->fetch()['count'] ?? 0;
} catch (Throwable $e) {}

$lang = get_lang();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title>Data Import</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.stats-box{background:#fff;border-radius:12px;padding:1.5rem;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.1)}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="container-fluid py-4">
  <h1 class="h3 mb-4">Data Import</h1>

  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <div class="alert alert-info mb-4">
    <strong>ODS file?</strong> First convert it using <a href="../convert_ods.php" class="alert-link">ODS Converter</a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-primary text-white py-3"><h5>Upload CSV</h5></div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label">CSV file</label>
              <input type="file" name="exam_export" class="form-control" accept=".csv" required>
              <small class="text-muted">Format: CSV with ; separator</small>
            </div>
            <button type="submit" class="btn btn-primary w-100">Upload</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-info text-white py-3"><h5>Statistics</h5></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-6"><div class="stats-box"><div class="h2"><?= number_format($stats['total_records']) ?></div><div class="small">Records</div></div></div>
            <div class="col-6"><div class="stats-box"><div class="h2"><?= number_format($stats['total_questions']) ?></div><div class="small">Questions</div></div></div>
            <div class="col-6"><div class="stats-box"><div class="h2"><?= number_format($stats['total_students']) ?></div><div class="small">Students</div></div></div>
            <div class="col-6"><div class="stats-box"><div class="h2"><?= number_format($stats['disciplines']) ?></div><div class="small">Disciplines</div></div></div>
          </div>
          <hr>
          <div class="d-grid gap-2">
            <a href="../index.php" class="btn btn-outline-success">AI Analytics</a>
            <a href="import_syllabus.php" class="btn btn-outline-dark">Import Syllabus</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function readCsvFile(string $filePath): array {
    $data = [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) throw new Exception('Failed to open CSV');
    $headers = fgetcsv($handle, 0, ';');
    if ($headers === false) { fseek($handle, 0); $headers = fgetcsv($handle, 0, ','); }
    if ($headers === false) throw new Exception('Failed to read headers');
    $headers = array_map('trim', $headers);
    while (($row = fgetcsv($handle, 0, ';')) !== false || ($row = fgetcsv($handle, 0, ',')) !== false) {
        if (empty($row)) continue;
        $rowData = [];
        foreach ($headers as $index => $header) { $rowData[$header] = isset($row[$index]) ? trim($row[$index]) : ''; }
        $data[] = $rowData;
    }
    fclose($handle);
    return $data;
}
?>
