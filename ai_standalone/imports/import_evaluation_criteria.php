<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';
require_login();
require_role(['superadmin', 'admin']);

$error = null;
$success = null;
$stats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['criteria_file'])) {
    verify_csrf_or_fail();
    $file = $_FILES['criteria_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $error = 'Supported formats: CSV, XLSX, XLS';
        } else {
            try {
                $data = [];
                if ($extension === 'csv') {
                    $data = readCsvFile($file['tmp_name']);
                } else {
                    $error = 'Please convert Excel to CSV first';
                }

                if (empty($data)) {
                    throw new Exception('File is empty');
                }

                $pdo->beginTransaction();

                $stmtLog = $pdo->prepare("INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type) VALUES (:filename, :fmt, :total, 0, 'evaluation_criteria')");
                $stmtLog->execute([':filename' => $file['name'], ':fmt' => $extension, ':total' => count($data)]);
                $importId = (int)$pdo->lastInsertId();

                $stmtInsert = $pdo->prepare("
                    INSERT INTO evaluation_criteria (criteria_id, name_kazakh, name_russian, name_english, weight_percent)
                    VALUES (:cid, :kz, :ru, :en, :weight)
                    ON DUPLICATE KEY UPDATE
                    name_kazakh = VALUES(name_kazakh),
                    name_russian = VALUES(name_russian),
                    name_english = VALUES(name_english),
                    weight_percent = VALUES(weight_percent)
                ");

                $inserted = 0;
                $errors = 0;

                foreach ($data as $index => $row) {
                    if ($index === 0) continue;

                    try {
                        $criteriaId = isset($row['ID критерия']) && is_numeric($row['ID критерия']) ? (int)$row['ID критерия'] : (isset($row['ID']) && is_numeric($row['ID']) ? (int)$row['ID'] : null);
                        $nameKz = trim($row['Наименование критерия на казахском'] ?? $row['Қазақша'] ?? $row['Kazakh'] ?? $row['name_kazakh'] ?? '');
                        $nameRu = trim($row['Наименование критерия на русском'] ?? $row['Русский'] ?? $row['Russian'] ?? $row['name_russian'] ?? '');
                        $nameEn = trim($row['Наименование критерия на английском'] ?? $row['English'] ?? $row['name_english'] ?? '');
                        $weightVal = $row['Доля от оценки (в %)'] ?? $row['Вес'] ?? $row['Weight'] ?? $row['weight_percent'] ?? '';
                        $weight = ($weightVal !== '' && is_numeric($weightVal)) ? (float)$weightVal : 20.00;

                        if ($criteriaId && $nameKz && $nameRu && $nameEn) {
                            $stmtInsert->execute([
                                ':cid' => $criteriaId,
                                ':kz' => $nameKz,
                                ':ru' => $nameRu,
                                ':en' => $nameEn,
                                ':weight' => $weight
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

                $success = "Imported: {$inserted} criteria" . ($errors > 0 ? ", errors: {$errors}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors
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
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM evaluation_criteria");
    $stats['total_criteria'] = $stmt->fetch()['count'] ?? 0;
} catch (Throwable $e) {
    $stats['total_criteria'] = 0;
}

$lang = get_lang();
?>
<!doctype html>
<html lang="<?= h($lang) ?>">
<head>
  <meta charset="utf-8">
  <title>Import Evaluation Criteria</title>
  <link href="../assets/vendor/css/bootstrap-lite.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:800px;margin:0 auto}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>

<div class="container-fluid py-4">
  <div class="upload-box">
    <h1 class="h3 mb-4">Import Evaluation Criteria</h1>

    <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['total_criteria']) ?></div>
            <div class="small text-muted">Total Criteria</div>
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
            <div class="h2"><?= number_format($stats['total_details'] ?? 0) ?></div>
            <div class="small text-muted">Details Records</div>
          </div>
        </div>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <div class="mb-3">
        <label class="form-label">Criteria File (CSV)</label>
        <input type="file" name="criteria_file" class="form-control" accept=".csv" required>
        <small class="text-muted">Columns: ID, Қазақша, Русский, English, Вес</small>
      </div>
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="view_evaluation_criteria.php" class="btn btn-outline-info">View Criteria</a>
      <a href="index.php?section=import" class="btn btn-outline-secondary">Back</a>
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
