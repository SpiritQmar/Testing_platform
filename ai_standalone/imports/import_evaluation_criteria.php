<?php

// Simple test log at the very beginning
file_put_contents(__DIR__ . '/debug_criteria.log', "=== " . date('Y-m-d H:i:s') . " === FILE ACCESSED ===\n", FILE_APPEND | LOCK_EX);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';

// Debug logging right after requires
$logFile = __DIR__ . '/debug_criteria.log';
$logData = "=== " . date('Y-m-d H:i:s') . " === AFTER REQUIRES ===\n";
file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

require_login();
$logData = "=== " . date('Y-m-d H:i:s') . " === AFTER LOGIN ===\n";
file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

require_role(['superadmin', 'admin']);
$logData = "=== " . date('Y-m-d H:i:s') . " === AFTER ROLE CHECK ===\n";
file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

$error = null;
$success = null;
$stats = [];

// Debug logging at the very beginning
$logData = "=== " . date('Y-m-d H:i:s') . " === SCRIPT START ===\n";
$logData .= "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
$logData .= "POST data: " . json_encode($_POST) . "\n";
$logData .= "FILES data: " . json_encode($_FILES) . "\n";
file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

// Debug POST condition
$logData = "=== " . date('Y-m-d H:i:s') . " === POST CONDITION CHECK ===\n";
$logData .= "REQUEST_METHOD: '" . $_SERVER['REQUEST_METHOD'] . "'\n";
$logData .= "Is POST: " . ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'YES' : 'NO') . "\n";
$logData .= "isset(criteria_file): " . (isset($_FILES['criteria_file']) ? 'YES' : 'NO') . "\n";
$logData .= "FILES keys: " . json_encode(array_keys($_FILES)) . "\n";
file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['criteria_file'])) {
    $logData = "=== " . date('Y-m-d H:i:s') . " === ENTERED POST BLOCK ===\n";
    file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
    $logData = "=== " . date('Y-m-d H:i:s') . " === BEFORE CSRF CHECK ===\n";
    file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
    verify_csrf_or_fail();
    
    $logData = "=== " . date('Y-m-d H:i:s') . " === AFTER CSRF CHECK ===\n";
    file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
    
    $file = $_FILES['criteria_file'];
    $importId = (int)($_POST['import_id'] ?? 0);
    
    // Debug logging
    $logData = "=== " . date('Y-m-d H:i:s') . " === PROCESSING ===\n";
    $logData .= "Import ID: $importId\n";
    $logData .= "File: " . $file['name'] . "\n";
    $logData .= "Size: " . $file['size'] . " bytes\n";
    $logData .= "Error: " . $file['error'] . "\n";
    file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);

    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $logData .= "Extension: $extension\n";
        
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $error = 'Supported formats: CSV, XLSX, XLS';
            $logData .= "ERROR: Unsupported format\n";
        } else {
            try {
                $data = [];
                if ($extension === 'csv') {
                    $data = readCsvFile($file['tmp_name']);
                } else {
                    $error = 'Please convert Excel to CSV first';
                    $logData .= "ERROR: Excel not supported\n";
                }

                $logData .= "Data rows: " . count($data) . "\n";
                
                if (empty($data)) {
                    throw new Exception('File is empty');
                }

                // Log first few rows for debugging
                $logData .= "Sample data:\n";
                for ($i = 0; $i < min(3, count($data)); $i++) {
                    $logData .= "Row " . ($i + 1) . ": " . json_encode($data[$i]) . "\n";
                }

                $pdo->beginTransaction();
                $inserted = 0;
                $errors = 0;

                foreach ($data as $index => $row) {
                    try {
                        $id = trim($row['ID'] ?? '');
                        $kaz = trim($row['Қазақша'] ?? '');
                        $rus = trim($row['Русский'] ?? '');
                        $eng = trim($row['English'] ?? '');
                        $weight = floatval($row['Вес'] ?? 0);

                        $logData .= "Row " . ($index + 1) . ": ID='$id', Kaz='$kaz', Rus='$rus', Eng='$eng', Weight=$weight\n";

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
                                $logData .= "  ✓ INSERTED\n";
                            } else {
                                $errors++;
                                $logData .= "  ✗ FAILED\n";
                            }
                        } else {
                            $errors++;
                            $logData .= "  ✗ SKIPPED (missing data)\n";
                        }
                    } catch (Throwable $e) {
                        $errors++;
                        $logData .= "  ✗ ERROR: " . $e->getMessage() . "\n";
                    }
                }

                $pdo->commit();

                // Update imports_log if record exists, otherwise skip silently
                try {
                    $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                    $updateResult = $stmt->execute([':i' => $inserted, ':id' => $importId]);
                    $logData .= "Update imports_log result: " . ($updateResult ? 'SUCCESS' : 'FAILED') . "\n";
                } catch (Throwable $e) {
                    $logData .= "Update imports_log skipped: " . $e->getMessage() . "\n";
                }

                $success = "Imported: {$inserted} criteria" . ($errors > 0 ? ", errors: {$errors}" : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors
                ];
                
                $logData .= "Final result: $success\n";
                
                // Redirect to import page with success message
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
                $logData .= "FATAL ERROR: " . $e->getMessage() . "\n";
            }
        }
    }
    
    $logData .= "========================\n\n";
    file_put_contents($logFile, $logData, FILE_APPEND | LOCK_EX);
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
