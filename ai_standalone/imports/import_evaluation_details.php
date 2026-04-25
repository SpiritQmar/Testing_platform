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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['details_file'])) {
    verify_csrf_or_fail();
    $file = $_FILES['details_file'];

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

                $stmtLog = $pdo->prepare("INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type) VALUES (:filename, :fmt, :total, 0, 'evaluation_details')");
                $stmtLog->execute([':filename' => $file['name'], ':fmt' => $extension, ':total' => count($data)]);
                $importId = (int)$pdo->lastInsertId();

                $stmtInsert = $pdo->prepare("
                    INSERT INTO evaluation_details (work_id, criteria_id, score)
                    VALUES (:wid, :cid, :score)
                    ON DUPLICATE KEY UPDATE score = VALUES(score)
                ");

                $stmtCheckCriteria = $pdo->prepare("SELECT criteria_id FROM evaluation_criteria WHERE criteria_id = :cid");
                $stmtCheckWork = $pdo->prepare("SELECT work_id FROM work_mapping WHERE work_id = :wid");

                $inserted = 0;
                $errors = 0;
                $invalidCriteria = 0;
                $invalidWorkId = 0;
                $skipped = 0;
                $duplicates = 0;

                foreach ($data as $index => $row) {
                    if ($index === 0) continue;

                    try {
                        $workCode = !empty($row['Код работы'] ?? $row['Work Code'] ?? $row['work_id']) ? (int)($row['Код работы'] ?? $row['Work Code'] ?? $row['work_id']) : null;
                        $criteriaId = !empty($row['ID критерия'] ?? $row['Criterion ID'] ?? $row['criteria_id']) && is_numeric($row['ID критерия'] ?? $row['Criterion ID'] ?? $row['criteria_id']) ? (int)($row['ID критерия'] ?? $row['Criterion ID'] ?? $row['criteria_id']) : null;
                        $score = !empty($row['Оценка по 100 шкале'] ?? $row['Score'] ?? $row['score']) && is_numeric($row['Оценка по 100 шкале'] ?? $row['Score'] ?? $row['score']) ? (float)($row['Оценка по 100 шкале'] ?? $row['Score'] ?? $row['score']) : null;

                        if (!$workCode || !$criteriaId || $score === null) {
                            $skipped++;
                            continue;
                        }

                        if ($criteriaId) {
                            $stmtCheckCriteria->execute([':cid' => $criteriaId]);
                            if (!$stmtCheckCriteria->fetch()) {
                                $invalidCriteria++;
                                continue;
                            }
                        }

                        $workId = $workCode;
                        if ($workId && $criteriaId && $score !== null) {
                            $stmtInsert->execute([
                                ':wid' => $workId,
                                ':cid' => $criteriaId,
                                ':score' => $score
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

                $success = "Imported: {$inserted} records" . ($errors > 0 ? ", errors: {$errors}" : "") . ($invalidCriteria > 0 ? ", invalid criteria: {$invalidCriteria}" : "") . ($skipped > 0 ? ", skipped: {$skipped}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors,
                    'invalid_criteria' => $invalidCriteria,
                    'skipped' => $skipped
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
  <link href="../assets/vendor/css/bootstrap-lite.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:800px;margin:0 auto}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="container-fluid py-4">
  <div class="upload-box">
    <h1 class="h3 mb-4">Import Evaluation Details</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['total_details']) ?></div>
            <div class="small text-muted">Total Details</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['imported'] ?? 0) ?></div>
            <div class="small text-muted">Imported</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['invalid_criteria'] ?? 0) ?></div>
            <div class="small text-muted">Invalid Criteria</div>
          </div>
        </div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
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
