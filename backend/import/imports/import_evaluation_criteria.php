<?php

require_once __DIR__ . '/../../../analytics/includes/auth.php';
require_once __DIR__ . '/../../../analytics/db.php';

$error = null;
$success = null;
$stats = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_FILES['criteria_file'])) {
    
    verify_csrf_or_fail();
    
    $file = $_FILES['criteria_file'];
    $importId = (int)($_POST['import_id'] ?? 0);

if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($extension, ['csv', 'xlsx', 'xls', 'ods'])) {
            $error = 'Supported formats: CSV, XLSX, ODS';
        } else {

            try {
                $data = [];
                if ($extension === 'csv') {
                    $data = readCsvFile($file['tmp_name']);
                } elseif ($extension === 'ods') {
                    $data = readOdsFile($file['tmp_name']);
                } else {
                    $data = readXlsxFile($file['tmp_name']);
                }
                
                if (empty($data)) {
                    throw new Exception('File is empty');
                }
                
                $pdo->beginTransaction();
                $inserted = 0;
                $errors = 0;
                $errorDetails = [];

                $usePositional = !empty($data) && !array_key_exists('Наименование критерия на русском', $data[0]) && !array_key_exists('Наименование критерия на английском', $data[0]);

                foreach ($data as $index => $row) {
                    try {
                        if ($usePositional) {
                            $vals = array_values($row);
                            $id     = trim($vals[0] ?? '');
                            $kaz    = trim($vals[1] ?? '');
                            $rus    = trim($vals[2] ?? '') ?: $kaz;
                            $eng    = trim($vals[3] ?? '') ?: $kaz;
                            $raw    = trim((string)($vals[4] ?? $vals[2] ?? 0));
                            $weight = floatval(str_replace([',', '%', ' '], ['.', '', ''], $raw));
                        } else {
                            $id     = trim($row['ID критерия'] ?? '');
                            $kaz    = trim($row['Наименование критерия на казахском'] ?? '');
                            $rus    = trim($row['Наименование критерия на русском'] ?? '') ?: $kaz;
                            $eng    = trim($row['Наименование критерия на английском'] ?? '') ?: $kaz;
                            $raw    = trim((string)($row['Доля от оценки (в %)'] ?? $row['Доля'] ?? 0));
                            $weight = floatval(str_replace([',', '%', ' '], ['.', '', ''], $raw));
                        }

                        if ($id && $kaz && $weight > 0) {
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
                                $errorDetails[] = "row $index: execute failed";
                            }
                        } else {
                            $errors++;
                            $missing = [];
                            if (!$id) $missing[] = 'id=' . json_encode($id);
                            if (!$kaz) $missing[] = 'kaz=' . json_encode($kaz);
                            if (!$rus) $missing[] = 'rus=' . json_encode($rus);
                            if (!$eng) $missing[] = 'eng=' . json_encode($eng);
                            if (!($weight > 0)) $missing[] = 'weight=' . $weight;
                            $errorDetails[] = "row $index: empty fields [" . implode(', ', $missing) . "] keys=" . implode('|', array_keys($row));
                        }
                    } catch (Throwable $e) {
                        $errors++;
                        $errorDetails[] = "row $index exception: " . $e->getMessage();
                    }
                }

                $pdo->commit();

                try {
                    $stmt = $pdo->prepare("UPDATE imports_log SET rows_imported = :i WHERE import_id = :id");
                    $updateResult = $stmt->execute([':i' => $inserted, ':id' => $importId]);
                } catch (Throwable $e) {
                }

                $success = "Успешно импортировано критериев: {$inserted}" . ($errors > 0 ? ", ошибок: {$errors}. Детали: " . implode(' | ', array_slice($errorDetails, 0, 3)) : "");
                $stats = [
                    'total_rows' => count($data),
                    'imported' => $inserted,
                    'errors' => $errors
                ];
                
                $_SESSION['import_flash'] = [
                    'type' => 'success',
                    'message' => $success
                ];
                header('Location: ../import.php?import_tab=criteria');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $e) {}
                }
                $_SESSION['import_flash'] = [
                    'type' => 'danger',
                    'message' => 'Error: ' . $e->getMessage(),
                ];
                header('Location: ../import.php?import_tab=criteria');
                exit;
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
<?php require_once __DIR__ . '/../../../analytics/includes/layout.php'; ?>
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
        <input type="file" name="criteria_file" class="form-control" accept=".csv,.xlsx,.ods" required>
        <small class="text-muted">Columns: ID, Қазақша, Русский, English, Вес</small>
      </div>
      <button type="submit" class="btn btn-primary">Import</button>
      <a href="view_evaluation_criteria.php" class="btn btn-outline-info">View Criteria</a>
      <a href="../import.php?import_tab=criteria" class="btn btn-outline-secondary">Back</a>
    </form>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function detectCsvDelimiter(string $filePath): string {
    $sample = file_get_contents($filePath, false, null, 0, 4096);
    if ($sample === false) return ';';
    $counts = [';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")];
    arsort($counts);
    $d = array_key_first($counts);
    return $counts[$d] > 0 ? $d : ';';
}

function readCsvFile(string $filePath, string $delimiter = ''): array {
    if ($delimiter === '') $delimiter = detectCsvDelimiter($filePath);
    $data = [];
    $handle = fopen($filePath, 'r');
    if ($handle === false) throw new Exception('Failed to open CSV');
    $headers = fgetcsv($handle, 0, $delimiter);
    if ($headers === false) throw new Exception('Failed to read headers');
    $headers = array_map(static fn($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$h) ?? (string)$h), $headers);
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (empty($row)) continue;
        $rowData = [];
        foreach ($headers as $index => $header) { $rowData[$header] = isset($row[$index]) ? trim($row[$index]) : ''; }
        $data[] = $rowData;
    }
    fclose($handle);
    return $data;
}

function xlsxCleanXml(string $xml): string {
    $xml = preg_replace('/\s+xmlns(?::[a-zA-Z0-9_-]+)?="[^"]*"/', '', $xml) ?? $xml;
    $xml = preg_replace('/\s+[a-zA-Z][a-zA-Z0-9]*:[a-zA-Z0-9_-]+=(?:"[^"]*"|\'[^\']*\')/', '', $xml) ?? $xml;
    return $xml;
}

function xlsxColIndex(string $col): int {
    $col = strtoupper(preg_replace('/[0-9]/', '', $col));
    $index = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $index = $index * 26 + (ord($col[$i]) - 64);
    }
    return $index - 1;
}

function readXlsxFile(string $filePath): array {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) throw new Exception('Failed to open XLSX');
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssXml = xlsxCleanXml($ssXml);
        $ss = new SimpleXMLElement($ssXml, LIBXML_NOERROR | LIBXML_NOWARNING);
        foreach ($ss->si as $si) {
            if (isset($si->t)) {
                $sharedStrings[] = trim((string)$si->t);
            } else {
                $text = '';
                foreach ($si->r ?? [] as $r) { $text .= (string)($r->t ?? ''); }
                $sharedStrings[] = trim($text);
            }
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$sheetXml) throw new Exception('No sheet1.xml in XLSX');
    $sheetXml = xlsxCleanXml($sheetXml);
    libxml_use_internal_errors(true);
    $sheet = new SimpleXMLElement($sheetXml, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $data = [];
    $headers = [];
    foreach ($sheet->sheetData->row as $row) {
        $sparse = [];
        $maxCol = 0;
        foreach ($row->c as $cell) {
            $colIdx = xlsxColIndex((string)($cell['r'] ?? 'A'));
            $maxCol = max($maxCol, $colIdx);
            $type = (string)($cell['t'] ?? '');
            $raw = isset($cell->v) ? (string)$cell->v : '';
            if ($type === 's') { $raw = $sharedStrings[(int)$raw] ?? ''; }
            elseif ($type === 'inlineStr') { $raw = isset($cell->is->t) ? (string)$cell->is->t : ''; }
            $sparse[$colIdx] = trim($raw);
        }
        $currentRow = [];
        for ($i = 0; $i <= $maxCol; $i++) { $currentRow[] = $sparse[$i] ?? ''; }
        if (count(array_filter($currentRow, static fn($v) => $v !== '')) === 0) continue;
        if (empty($headers)) { $headers = $currentRow; }
        else {
            $rowData = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') continue;
                $rowData[$header] = $currentRow[$idx] ?? '';
            }
            $data[] = $rowData;
        }
    }
    return $data;
}

function readOdsFile(string $filePath): array {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) throw new Exception('Failed to open ODS');
    $xmlContent = $zip->getFromName('content.xml');
    $zip->close();
    if (!$xmlContent) throw new Exception('No content.xml');
    $data = [];
    $headers = [];
    preg_match_all('/<table:table-row[^>]*>(.*?)<\/table:table-row>/s', $xmlContent, $rowMatches, PREG_SET_ORDER);
    foreach ($rowMatches as $rowMatch) {
        preg_match_all('/<table:table-cell([^>]*)>(.*?)<\/table:table-cell>/s', $rowMatch[1], $cellMatches, PREG_SET_ORDER);
        $currentRow = [];
        foreach ($cellMatches as $cellMatch) {
            preg_match('/table:number-columns-repeated="(\d+)"/', $cellMatch[1], $rep);
            $repeat = isset($rep[1]) ? max(1, (int)$rep[1]) : 1;
            preg_match('/<text:p[^>]*>(.*?)<\/text:p>/s', $cellMatch[2], $textMatch);
            $value = isset($textMatch[1]) ? trim(strip_tags(html_entity_decode($textMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8'))) : '';
            for ($i = 0; $i < $repeat; $i++) { $currentRow[] = $value; }
        }
        if (count(array_filter($currentRow, static fn($v) => $v !== '')) === 0) continue;
        if (empty($headers)) { $headers = array_map('trim', $currentRow); }
        else {
            $rowData = [];
            foreach ($headers as $idx => $header) {
                if ($header === '') continue;
                $rowData[$header] = $currentRow[$idx] ?? '';
            }
            $data[] = $rowData;
        }
    }
    return $data;
}
?>
