<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';

$error = null;
$success = null;
$stats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['criteria_file'])) {
    
    verify_csrf_or_fail();
    
    $file = $_FILES['criteria_file'];
    $importId = (int)($_POST['import_id'] ?? 0);

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
                $inserted = 0;
                $errors = 0;

                foreach ($data as $index => $row) {
                    try {
                        $id = trim($row['ID критерия'] ?? '');
                        $kaz = trim($row['Наименование критерия на казахском'] ?? '');
                        $rus = trim($row['Наименование критерия на русском'] ?? '');
                        $eng = trim($row['Наименование критерия на английском'] ?? '');
                        $weight = floatval($row['Доля от оценки (в %)'] ?? 0);

                        if ($id && $kaz && $rus && $eng && $weight > 0) {
                            $stmt = $pdo->prepare("INSERT INTO evaluation_criteria (criteria_id, name_kazakh, name_russian, name_english, weight_percent) VALUES (:id, :kaz, :rus, :eng, :weight) ON DUPLICATE KEY UPDATE name_kazakh = VALUES(name_kazakh), name_russian = VALUES(name_russian), name_english = VALUES(name_english), weight_percent = VALUES(weight_percent)");
                            $result = $stmt->execute([
                                ':id' => $id,
                                ':kaz' => $kaz,
                                ':rus' => $rus,
                                ':eng' => $eng,
                                ':weight' => $weight
                            ]);
                            
                            if ($result) {
                                $inserted++;
                            } else {
                                $errors++;
                            }
                        } else {
                            $errors++;
                        }
                    } catch (Throwable $e) {
                        $errors++;
                    }
                }

                $pdo->commit();

                try {
                    $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                    $updateResult = $stmt->execute([':i' => $inserted, ':id' => $importId]);
                } catch (Throwable $e) {
                }

                $success = "Успешно импортировано критериев: {$inserted}" . ($errors > 0 ? ", ошибок: {$errors}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors
                ];
                
                $_SESSION['import_flash'] = [
                    'type' => 'success',
                    'message' => $success
                ];
                header('Location: ../index.php?section=import&import_tab=criteria');
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/app.css" rel="stylesheet">
  <style>body{background:#f5f5f5}.upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:800px;margin:0 auto}</style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/layout.php'; ?>
<div class="container py-5">
  <div class="upload-box">
    <h2 class="mb-4">Import Evaluation Criteria</h2>
    
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($stats)): ?>
    <div class="row mb-4">
      <div class="col-md-4">
        <div class="card">
          <div class="card-body text-center">
            <div class="h2"><?= number_format($stats['total_rows'] ?? 0) ?></div>
            <div class="small text-muted">Total Rows</div>
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
            <div class="h2"><?= number_format($stats['total_criteria'] ?? 0) ?></div>
            <div class="small text-muted">Total Criteria</div>
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
        <label class="form-label">Criteria File (CSV)</label>
        <input type="file" name="criteria_file" class="form-control" accept=".csv" required>
        <small class="text-muted">Columns: ID, Қазақша, Русский, English, Вес</small>
      </div>
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="view_evaluation_criteria.php" class="btn btn-outline-info">View Criteria</a>
      <a href="../index.php?section=import" class="btn btn-outline-secondary">Back</a>
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
