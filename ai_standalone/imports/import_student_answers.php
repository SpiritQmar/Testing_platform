<?php

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';
require_login();
require_role(['superadmin', 'admin']);

$error = null;
$success = null;
$stats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['answers_file'])) {
    verify_csrf_or_fail();
    $file = $_FILES['answers_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv'])) {
            $error = 'Supported format: CSV';
        } else {
            try {
                $data = readCsvFile($file['tmp_name']);

                if (empty($data)) {
                    throw new Exception('File is empty');
                }

                $pdo->beginTransaction();

                $stmtLog = $pdo->prepare("INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type) VALUES (:filename, :fmt, :total, 0, 'student_answers')");
                $stmtLog->execute([':filename' => $file['name'], ':fmt' => $extension, ':total' => count($data)]);
                $importId = (int)$pdo->lastInsertId();

                $stmtInsert = $pdo->prepare("
                    INSERT INTO student_answers (work_id, language, question_text, answer_text, final_score, teacher_id, teacher_comment, plagiarism_penalty)
                    VALUES (:wid, :lang, :qtext, :atext, :score, :tid, :comment, :penalty)
                    ON DUPLICATE KEY UPDATE
                    language = VALUES(language),
                    question_text = VALUES(question_text),
                    answer_text = VALUES(answer_text),
                    final_score = VALUES(final_score),
                    teacher_id = VALUES(teacher_id),
                    teacher_comment = VALUES(teacher_comment),
                    plagiarism_penalty = VALUES(plagiarism_penalty)
                ");

                $stmtCheckWork = $pdo->prepare("SELECT work_id FROM work_mapping WHERE work_id = :wid");

                $inserted = 0;
                $errors = 0;
                $invalidWork = 0;
                $decodeErrors = 0;

                foreach ($data as $index => $row) {
                    if ($index === 0) continue;

                    try {
                        $workId = !empty($row['Код работы'] ?? $row['Work Code'] ?? $row['work_id']) && is_numeric($row['Код работы'] ?? $row['Work Code'] ?? $row['work_id']) ? (int)($row['Код работы'] ?? $row['Work Code'] ?? $row['work_id']) : null;
                        $language = trim($row['Язык'] ?? $row['Language'] ?? $row['language'] ?? '');
                        $questionText = trim($row['Вопрос'] ?? $row['Question'] ?? $row['question_text'] ?? '');
                        $answerBase64 = trim($row['Ответ в BASE64'] ?? $row['Answer in BASE64'] ?? $row['answer_base64'] ?? '');
                        $teacherId = !empty($row['ID проверяющего преподавателя'] ?? $row['Teacher ID'] ?? $row['teacher_id']) && is_numeric($row['ID проверяющего преподавателя'] ?? $row['Teacher ID'] ?? $row['teacher_id']) ? (int)($row['ID проверяющего преподавателя'] ?? $row['Teacher ID'] ?? $row['teacher_id']) : null;
                        $finalScore = !empty($row['Итоговая оценка'] ?? $row['Final Score'] ?? $row['final_score']) && is_numeric($row['Итоговая оценка'] ?? $row['Final Score'] ?? $row['final_score']) ? (float)($row['Итоговая оценка'] ?? $row['Final Score'] ?? $row['final_score']) : null;
                        $teacherComment = trim($row['Комментарий преподавателя к оценке'] ?? $row['Teacher Comment'] ?? $row['teacher_comment'] ?? '');
                        $plagiarismPenalty = !empty($row['Штраф за плагиат'] ?? $row['Plagiarism Penalty'] ?? $row['plagiarism_penalty']) && is_numeric($row['Штраф за плагиат'] ?? $row['Plagiarism Penalty'] ?? $row['plagiarism_penalty']) ? (float)($row['Штраф за плагиат'] ?? $row['Plagiarism Penalty'] ?? $row['plagiarism_penalty']) : 0;

                        $actualWorkId = $workId;

                        $answerText = '';
                        if ($answerBase64) {
                            $decoded = base64_decode($answerBase64, true);
                            if ($decoded === false) {
                                $decodeErrors++;
                                continue;
                            }
                            $answerText = $decoded;
                        }

                        if ($actualWorkId && $language && $questionText) {
                            $stmtInsert->execute([
                                ':wid' => $actualWorkId,
                                ':lang' => $language,
                                ':qtext' => $questionText,
                                ':atext' => $answerText,
                                ':score' => $finalScore,
                                ':tid' => $teacherId,
                                ':comment' => $teacherComment,
                                ':penalty' => $plagiarismPenalty
                            ]);
                            $inserted++;
                        }
                    } catch (Throwable $e) {
                        $errors++;
                    }
                }

                $pdo->commit();

                $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                $stmt->execute([':i' => $inserted, ':id' => $importId]);

                $success = "Imported: {$inserted} answers" . ($errors > 0 ? ", errors: {$errors}" : "") . ($invalidWork > 0 ? ", invalid work_id: {$invalidWork}" : "") . ($decodeErrors > 0 ? ", decode errors: {$decodeErrors}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors,
                    'invalid_work' => $invalidWork,
                    'decode_errors' => $decodeErrors
                ];

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
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM student_answers");
    $stats['total_answers'] = $stmt->fetch()['count'] ?? 0;
} catch (Throwable $e) {
    $stats['total_answers'] = 0;
}

$lang = get_lang();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title>Import Student Answers</title>
  <link href="../assets/vendor/css/bootstrap-lite.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:800px;margin:0 auto}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="container-fluid py-4">
  <div class="upload-box">
    <h1 class="h3 mb-4">Import Student Answers</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['total_answers']) ?></div>
            <div class="small text-muted">Total Answers</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['imported'] ?? 0) ?></div>
            <div class="small text-muted">Imported</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['invalid_work'] ?? 0) ?></div>
            <div class="small text-muted">Invalid Work ID</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['decode_errors'] ?? 0) ?></div>
            <div class="small text-muted">Decode Errors</div>
          </div>
        </div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <div class="mb-3">
        <label class="form-label">Student Answers File (CSV)</label>
        <input type="file" name="answers_file" class="form-control" accept=".csv" required>
        <small class="text-muted">Columns: Код работы, Язык, Вопрос, Ответ в BASE64, ID проверяющего преподавателя, Итоговая оценка, Комментарий преподавателя к оценке, Штраф за плагиат</small>
      </div>
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="import_syllabus.php" class="btn btn-outline-secondary">Back</a>
    </form>
  </div>
</div>
<script src="../assets/vendor/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function readCsvFile(string $filePath, string $delimiter = ','): array {
    $data = [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) throw new Exception('Failed to open CSV');
    $headers = fgetcsv($handle, 0, $delimiter);
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
