<?php
require_once __DIR__ . '/../../../ai_analytics/includes/auth.php';
require_once __DIR__ . '/../../../ai_analytics/db.php';

$error = null;
$success = null;
$stats = ['total_records' => 0, 'total_questions' => 0, 'total_students' => 0, 'disciplines' => 0];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_FILES['exam_export'])) {
    verify_csrf_or_fail();
    $file = $_FILES['exam_export'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'ods', 'xlsx'])) {
            $_SESSION['import_flash'] = [
                'type' => 'danger',
                'message' => 'Supported formats: CSV, ODS, XLSX',
            ];
            header('Location: ../import.php?import_tab=ods');
            exit;
        } else {
            try {
                if ($extension === 'ods') {
                    $data = readOdsFile($file['tmp_name']);
                } elseif ($extension === 'xlsx') {
                    $data = readXlsxFile($file['tmp_name']);
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

                foreach ($data as $row) {
                    try {
                        $questionValue = rowValue($row, ['ID вопроса', 'ID тематики', 'ID fråga', 'question_id']);
                        $studentValue = rowValue($row, ['ID студента', 'ID student', 'student_id']);
                        $courseValue = rowValue($row, ['Курс', 'Kurs', 'course_number']);
                        $scoreAfter = rowValue($row, ['Оценка после апелляции', 'Bedömning efter överklagande', 'score_after_appeal']);
                        $scoreBefore = rowValue($row, ['Оценка до апелляции', 'Bedömning före överklagande', 'score_before_appeal']);
                        $disciplineScore = rowValue($row, ['Оценка за дисциплину', 'Bedömning för disciplin', 'discipline_score']);

                        $questionId = numericOrNull($questionValue);
                        $studentId = numericOrNull($studentValue);
                        $disciplineName = trim(rowValue($row, ['Дисциплина', 'Disciplin', 'discipline_name']));
                        $courseNumber = numericOrNull($courseValue);
                        $receivedScore = scoreOrNull($scoreAfter !== '' && $scoreAfter !== '-' ? $scoreAfter : ($scoreBefore !== '' && $scoreBefore !== '-' ? $scoreBefore : $disciplineScore));

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

                
                
                
                
                
                
                
                
                
                
                

                if ($inserted === 0) {
                    throw new Exception('No rows imported. Check column names: question/student/course/discipline/score columns were not recognized.');
                }

                $success = 'Loaded: ' . number_format($inserted) . ' records, disciplines: ' . count($disciplines) . ($errors > 0 ? ', errors: ' . $errors : '');
                $_SESSION['import_flash'] = [
                    'type' => 'success',
                    'message' => $success,
                ];
                $_SESSION['ai_analytics_import_id'] = $importId;
                header('Location: ../import.php?import_tab=ods');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $e) {}
                }
                $_SESSION['import_flash'] = [
                    'type' => 'danger',
                    'message' => 'Error: ' . $e->getMessage(),
                ];
                header('Location: ../import.php?import_tab=ods');
                exit;
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
<?php require_once __DIR__ . '/../../../ai_analytics/includes/layout.php'; ?>

<div class="container-fluid py-4">
  <h1 class="h3 mb-4">Data Import</h1>

  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

  <div class="alert alert-info mb-4">
    <strong>ODS file?</strong> Upload it from the UAMS import page; it is parsed directly.
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
              <input type="file" name="exam_export" class="form-control" accept=".csv,.ods,.xlsx" required>
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
function normalizeHeader(string $header): string {
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
    $header = trim($header);
    return mb_strtolower($header, 'UTF-8');
}

function normalizeNumber(string $value): string {
    return trim(str_replace(',', '.', $value));
}

function numericOrNull(string $value): ?int {
    $value = normalizeNumber($value);
    return $value !== '' && is_numeric($value) ? (int)$value : null;
}

function scoreOrNull(string $value): ?float {
    $value = normalizeNumber($value);
    return $value !== '' && is_numeric($value) ? (float)$value : null;
}

function rowValue(array $row, array $keys): string {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row)) {
            return trim((string)$row[$key]);
        }
    }

    $normalizedRow = [];
    foreach ($row as $header => $value) {
        $normalizedRow[normalizeHeader((string)$header)] = $value;
    }

    foreach ($keys as $key) {
        $normalizedKey = normalizeHeader($key);
        if (array_key_exists($normalizedKey, $normalizedRow)) {
            return trim((string)$normalizedRow[$normalizedKey]);
        }
    }

    return '';
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
        $rowContent = $rowMatch[1];
        preg_match_all('/<table:table-cell([^>]*)>(.*?)<\/table:table-cell>/s', $rowContent, $cellMatches, PREG_SET_ORDER);

        $currentRow = [];
        foreach ($cellMatches as $cellMatch) {
            preg_match('/table:number-columns-repeated="(\d+)"/', $cellMatch[1], $repeatMatch);
            $repeat = isset($repeatMatch[1]) ? max(1, (int)$repeatMatch[1]) : 1;
            preg_match('/<text:p[^>]*>(.*?)<\/text:p>/s', $cellMatch[2], $textMatch);
            $value = isset($textMatch[1]) ? trim(strip_tags(html_entity_decode($textMatch[1], ENT_QUOTES | ENT_XML1, 'UTF-8'))) : '';
            for ($i = 0; $i < $repeat; $i++) {
                $currentRow[] = $value;
            }
        }

        if (empty($currentRow) || count(array_filter($currentRow, static fn ($value) => $value !== '')) === 0) continue;
        if (empty($headers)) {
            $headers = array_map('trim', $currentRow);
        } else {
            $rowData = [];
            foreach ($headers as $index => $header) {
                if ($header === '') continue;
                $rowData[$header] = isset($currentRow[$index]) ? trim($currentRow[$index]) : '';
            }
            $data[] = $rowData;
        }
    }

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
            if ($type === 's') {
                $raw = $sharedStrings[(int)$raw] ?? '';
            } elseif ($type === 'inlineStr') {
                $raw = isset($cell->is->t) ? (string)$cell->is->t : '';
            }
            $sparse[$colIdx] = trim($raw);
        }

        $currentRow = [];
        for ($i = 0; $i <= $maxCol; $i++) { $currentRow[] = $sparse[$i] ?? ''; }

        if (count(array_filter($currentRow, static fn($v) => $v !== '')) === 0) continue;

        if (empty($headers)) {
            $headers = $currentRow;
        } else {
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

function detectCsvDelimiter(string $filePath): string {
    $sample = file_get_contents($filePath, false, null, 0, 4096);
    if ($sample === false) return ';';

    $delimiters = [';' => substr_count($sample, ';'), ',' => substr_count($sample, ','), "\t" => substr_count($sample, "\t")];
    arsort($delimiters);
    $delimiter = array_key_first($delimiters);
    return $delimiters[$delimiter] > 0 ? $delimiter : ';';
}

function readCsvFile(string $filePath): array {
    $data = [];
    $delimiter = detectCsvDelimiter($filePath);
    $handle = fopen($filePath, 'r');
    if ($handle === false) throw new Exception('Failed to open CSV');
    $headers = fgetcsv($handle, 0, $delimiter);
    if ($headers === false) throw new Exception('Failed to read headers');
    $headers = array_map(static fn ($header) => trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$header) ?? (string)$header), $headers);
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        if (empty($row) || count(array_filter($row, static fn ($value) => trim((string)$value) !== '')) === 0) continue;
        $rowData = [];
        foreach ($headers as $index => $header) {
            if ($header === '') continue;
            $rowData[$header] = isset($row[$index]) ? trim((string)$row[$index]) : '';
        }
        $data[] = $rowData;
    }
    fclose($handle);
    return $data;
}
?>
