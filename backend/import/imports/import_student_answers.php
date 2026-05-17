<?php

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../../../analytics/includes/auth.php';
require_once __DIR__ . '/../../../analytics/db.php';

$error = null;
$success = null;
$stats = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_FILES['answers_file'])) {
    verify_csrf_or_fail();
    $file = $_FILES['answers_file'];
    $importId = (int)($_POST['import_id'] ?? 0);

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
                $inserted = 0;
                $errors = 0;
                $invalidWork = 0;
                $decodeErrors = 0;

                $stmtInsert = $pdo->prepare("INSERT INTO student_answers (work_id, language, question_text, answer_text, final_score, teacher_id, teacher_comment, plagiarism_penalty) VALUES (:wid, :lang, :qtext, :atext, :score, :tid, :comment, :penalty) ON DUPLICATE KEY UPDATE question_text = VALUES(question_text), answer_text = VALUES(answer_text), final_score = VALUES(final_score), teacher_id = VALUES(teacher_id), teacher_comment = VALUES(teacher_comment), plagiarism_penalty = VALUES(plagiarism_penalty)");

                foreach ($data as $row) {
                    try {
                        $workId = trim($row['Код работы'] ?? '');
                        $language = trim($row['Язык'] ?? '');
                        $questionText = trim($row['Вопрос'] ?? '');
                        $answerBase64 = trim($row['Ответ в BASE64'] ?? '');
                        $teacherId = trim($row['ID проверяющего преподавателя'] ?? '');
                        $finalScore = floatval($row['Итоговая оценка'] ?? 0);
                        $teacherComment = trim($row['Комментарий преподавателя к оценке'] ?? '');
                        $plagiarismPenalty = floatval($row['Штраф за плагиат'] ?? 0);

                        if ($workId && $language && $questionText && $answerBase64) {
                            
                            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM work_mapping WHERE work_id = :wid");
                            $stmtCheck->execute([':wid' => $workId]);
                            if ($stmtCheck->fetchColumn() == 0) {
                                try {
                                    $stmtCreate = $pdo->prepare("INSERT IGNORE INTO work_mapping (work_id, student_id, question_id, created_at) VALUES (:wid, :sid, 0, NOW())");
                                    $stmtCreate->execute([':wid' => $workId, ':sid' => (int)$workId]);
                                } catch (Throwable $e) {
                                    $invalidWork++;
                                    continue;
                                }
                            }

                            $answerText = '';
                            if ($answerBase64) {
                                $decoded = base64_decode($answerBase64, true);
                                if ($decoded === false) {
                                    $decodeErrors++;
                                    continue;
                                }
                                $answerText = $decoded;
                            }

                            if ($workId && $language && $questionText) {
                                $stmtInsert->execute([
                                    ':wid' => $workId,
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
                        } else {
                            $invalidWork++;
                        }
                    } catch (Throwable $e) {
                        $errors++;
                    }
                }

                $pdo->commit();

                try {
                    $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                    $stmt->execute([':i' => $inserted, ':id' => $importId]);
                } catch (Throwable $e) {
                }

                $success = "Успешно импортировано ответов студентов: {$inserted}" . ($errors > 0 ? ", ошибок: {$errors}" : "") . ($invalidWork > 0 ? ", неверных work_id: {$invalidWork}" : "") . ($decodeErrors > 0 ? ", ошибок декодирования: {$decodeErrors}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors,
                    'invalid_work' => $invalidWork,
                    'decode_errors' => $decodeErrors
                ];
                
                $_SESSION['import_flash'] = [
                    'type' => 'success',
                    'message' => $success
                ];
                header('Location: ../import.php?import_tab=answers');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $e) {}
                }
                $_SESSION['import_flash'] = [
                    'type' => 'danger',
                    'message' => 'Error: ' . $e->getMessage(),
                ];
                header('Location: ../import.php?import_tab=answers');
                exit;
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:800px;margin:0 auto}</style>
</head>
<body>
<?php require_once __DIR__ . '/../../../analytics/includes/layout.php'; ?>
<div class="container py-5">
  <div class="upload-box">
    <h2 class="mb-4">Import Student Answers</h2>
    
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($stats)): ?>
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['total_rows'] ?? 0) ?></div>
            <div class="small text-muted">Total Rows</div>
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
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <div class="mb-3">
        <label class="form-label">Import ID</label>
        <input type="number" name="import_id" class="form-control" required>
        <small class="text-muted">Enter the import ID from your current import session</small>
      </div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
