<?php

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';

$error = null;
$success = null;
$stats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['details_file'])) {
    verify_csrf_or_fail();
    $file = $_FILES['details_file'];
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
                $invalidCriteria = 0;
                $skipped = 0;

                                $stmt = $pdo->query("SELECT criteria_id FROM evaluation_criteria");
                $existingCriteria = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
                $existingCriteria = array_flip($existingCriteria);

$rowCount = 0;
foreach ($data as $row) {
    $rowCount++;
    if ($rowCount % 500 == 0) {
    }
    
    try {
        $workId = trim($row['Код работы'] ?? '');
        $criteriaId = trim($row['ID критерия'] ?? '');
        $score = floatval($row['Оценка по 100 шкале'] ?? 0);

        if ($workId && $criteriaId && $score >= 0 && $score <= 100) {
                            if (!isset($existingCriteria[$criteriaId])) {
                                $invalidCriteria++;
                                continue;
                            }

                            $stmt = $pdo->prepare("INSERT INTO evaluation_details (work_id, criteria_id, score) VALUES (:wid, :cid, :score) ON DUPLICATE KEY UPDATE score = VALUES(score)");
                            $stmt->execute([
                                ':wid' => $workId,
                                ':cid' => $criteriaId,
                                ':score' => $score
                            ]);
                            $inserted++;
                        } else {
                            $skipped++;
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

                $success = "Успешно импортировано деталей оценок: {$inserted}" . ($errors > 0 ? ", ошибок: {$errors}" : "") . ($invalidCriteria > 0 ? ", неверных критериев: {$invalidCriteria}" : "") . ($skipped > 0 ? ", пропущено: {$skipped}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors,
                    'invalid_criteria' => $invalidCriteria,
                    'skipped' => $skipped
                ];
                
                $_SESSION['import_flash'] = [
                    'type' => 'success',
                    'message' => $success
                ];
                header('Location: ../index.php?section=import&import_tab=details');
                exit;

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
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM evaluation_details");
    $stats['total_details'] = $stmt->fetch()['count'] ?? 0;
} catch (Throwable $e) {
    $stats['total_details'] = 0;
}

$lang = get_lang();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title>Import Evaluation Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:800px;margin:0 auto}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>
<div class="container py-5">
  <div class="upload-box">
    <h2 class="mb-4">Import Evaluation Details</h2>
    
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
            <div class="h2"><?= number_format($stats['invalid_criteria'] ?? 0) ?></div>
            <div class="small text-muted">Invalid Criteria</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['total_details'] ?? 0) ?></div>
            <div class="small text-muted">Details Records</div>
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
        <label class="form-label">Evaluation Details File (CSV)</label>
        <input type="file" name="details_file" class="form-control" accept=".csv" required>
        <small class="text-muted">Columns: Код работы, ID критерия, Оценка по 100 шкале</small>
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
