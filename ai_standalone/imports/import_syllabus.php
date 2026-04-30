<?php

require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../db.php';

require_login();
require_role(['superadmin', 'admin']);

$error = null;
$success = null;
$stats = [];
$activeTab = $_GET['tab'] ?? 'syllabus';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['syllabus_file']) && $activeTab === 'syllabus') {
    verify_csrf_or_fail();
    
    $file = $_FILES['syllabus_file'];
    $disciplineName = trim($_POST['discipline_name'] ?? '');
    $courseNumber = (int)($_POST['course_number'] ?? 3);
    
    if (empty($disciplineName)) {
        $error = 'Specify discipline name';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error: ' . $file['error'];
    } else {
        try {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, ['docx', 'doc', 'pdf', 'txt', 'odt'])) {
                throw new Exception('Supported formats: DOCX, DOC, PDF, TXT, ODT');
            }

            $uploadDir = __DIR__ . '/uploads/syllabuses/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $fileName = 'syllabus_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save file');
            }

            $text = extractDocumentText($filePath, $extension);

            $keywords = extractKeywords($text, $disciplineName);

            $topics = parseSyllabusTopics($text, $disciplineName, $courseNumber);
            $questions = parseSyllabusQuestions($text);

            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO user_syllabuses (discipline_name, course_number, file_name, file_path, file_type, text_content, uploaded_by, status)
                VALUES (:disc, :course, :fname, :fpath, :ftype, :text, :uid, 'processed')
            ");
            $stmt->execute([
                ':disc' => $disciplineName,
                ':course' => $courseNumber,
                ':fname' => $file['name'],
                ':fpath' => 'uploads/syllabuses/' . $fileName,
                ':ftype' => $extension,
                ':text' => $text,
                ':uid' => current_user()['user_id']
            ]);
            $syllabusId = (int)$pdo->lastInsertId();

            $stmtTopic = $pdo->prepare("
                INSERT INTO ai_syllabus_topics (discipline_name, course_number, topic_code, title, keywords)
                VALUES (:disc, :course, :code, :title, :keywords)
            ");
            
            $savedTopics = 0;
            foreach ($topics as $topic) {
                try {
                    $stmtTopic->execute([
                        ':disc' => $disciplineName,
                        ':course' => $courseNumber,
                        ':code' => $topic['code'],
                        ':title' => $topic['title'],
                        ':keywords' => $topic['keywords']
                    ]);
                    $savedTopics++;
                } catch (Throwable $e) {}
            }

            $savedQuestions = 0;
            if (!empty($questions) && !empty($topics)) {
                $firstTopicCode = $topics[0]['code'];
                
                $stmtTopicId = $pdo->prepare("
                    SELECT syllabus_topic_id FROM ai_syllabus_topics 
                    WHERE discipline_name = :disc 
                    AND course_number = :course 
                    AND topic_code = :code
                    LIMIT 1
                ");
                $stmtTopicId->execute([
                    ':disc' => $disciplineName,
                    ':course' => $courseNumber,
                    ':code' => $firstTopicCode
                ]);
                $topicId = $stmtTopicId->fetchColumn();
                
                if ($topicId) {
                    $stmtUpdateQuestion = $pdo->prepare("
                        UPDATE hier_questions 
                        SET question_text = :text, syllabus_topic_id = :topic_id
                        WHERE question_id = :qid
                    ");
                    
                    $stmtInsertQuestion = $pdo->prepare("
                        INSERT INTO hier_questions (question_text, max_score, question_type)
                        VALUES (:text, 100, 'single_choice')
                    ");
                    
                    foreach ($questions as $question) {
                        try {
                            $questionId = $question['number'] ?? null;
                            
                            if ($questionId) {
                                $stmtUpdateQuestion->execute([
                                    ':text' => $question['text'],
                                    ':topic_id' => $topicId,
                                    ':qid' => $questionId
                                ]);
                                if ($stmtUpdateQuestion->rowCount() > 0) {
                                    $savedQuestions++;
                                } else {
                                    $stmtInsertQuestion->execute([
                                        ':text' => $question['text']
                                    ]);
                                    $savedQuestions++;
                                }
                            } else {
                                $stmtInsertQuestion->execute([
                                    ':text' => $question['text']
                                ]);
                                $savedQuestions++;
                            }
                        } catch (Throwable $e) {
                            error_log("Error saving question: " . $e->getMessage());
                        }
                    }
                }
            }

            $linkedQuestions = 0;
            if ($savedTopics > 0) {
                $stmtLink = $pdo->exec("
                    UPDATE hier_questions hq
                    INNER JOIN raw_exam_results r ON r.question_id = hq.question_id
                    INNER JOIN ai_syllabus_topics ast ON ast.discipline_name = r.discipline_name
                    SET hq.syllabus_topic_id = ast.syllabus_topic_id
                    WHERE hq.syllabus_topic_id IS NULL 
                    AND r.discipline_name = '{$disciplineName}'
                ");
                $linkedQuestions = $stmtLink;
            }
            
            $pdo->commit();
            
            $success = "Syllabus uploaded! Topics saved: {$savedTopics}, questions linked: {$linkedQuestions}";
            
            $stats = [
                'type' => 'syllabus',
                'file_name' => $file['name'],
                'extension' => $extension,
                'text_length' => strlen($text),
                'topics_found' => $savedTopics,
                'keywords' => $keywords,
                'questions_linked' => $linkedQuestions,
            ];
            
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                try { $pdo->rollBack(); } catch (Throwable $e) {}
            }
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['ods_file']) && $activeTab === 'ods') {
    verify_csrf_or_fail();
    $file = $_FILES['ods_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        try {
            $data = readOdsFile($file['tmp_name']);
            
            if (empty($data)) {
                throw new Exception('ODS file is empty');
            }
            
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $csvFileName = 'converted_' . date('Y-m-d_H-i-s') . '.csv';
            $csvFilePath = $uploadDir . $csvFileName;
            
            $handle = fopen($csvFilePath, 'w');
            if ($handle === false) {
                throw new Exception('Failed to create file');
            }

            fputcsv($handle, ['question_id', 'student_id', 'discipline_name', 'course_number', 'score_after_appeal', 'score_before_appeal', 'discipline_score'], ';');

            $converted = 0;
            foreach ($data as $index => $row) {
                if ($index === 0) continue;

                $questionId = $row['ID fråga'] ?? $row['ID fråga'] ?? $row['ID fråga'] ?? '';
                $studentId = $row['ID student'] ?? $row['ID student'] ?? '';
                $disciplineName = $row['Disciplin'] ?? $row['Ämne'] ?? '';
                $courseNumber = $row['Kurs'] ?? $row['Kurs'] ?? '';
                $scoreAfter = $row['Bedömning efter överklagande'] ?? $row['Bedömning efter överklagande'] ?? '';
                $scoreBefore = $row['Bedömning före överklagande'] ?? $row['Bedömning före överklagande'] ?? '';
                $scoreDiscipline = $row['Bedömning för disciplin'] ?? $row['Bedömning för disciplin'] ?? '';

                $questionId = $questionId ?: ($row['ID fråga'] ?? $row['ID fråga'] ?? '');
                $studentId = $studentId ?: ($row['ID student'] ?? $row['ID student'] ?? '');
                $disciplineName = $disciplineName ?: ($row['Disciplin'] ?? $row['Ämne'] ?? '');

                fputcsv($handle, [$questionId, $studentId, $disciplineName, $courseNumber, $scoreAfter, $scoreBefore, $scoreDiscipline], ';');
                $converted++;
            }
            
            fclose($handle);

            $csvData = readCsvFile($csvFilePath);
            
            if (!empty($csvData)) {
                $pdo->beginTransaction();

                $stmtLog = $pdo->prepare("INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type) VALUES (:filename, :fmt, :total, 0, 'exam_results_upload')");
                $stmtLog->execute([':filename' => $file['name'], ':fmt' => 'ods', ':total' => count($csvData)]);
                $importId = (int)$pdo->lastInsertId();

                $stmtInsert = $pdo->prepare("
                    INSERT INTO raw_exam_results (import_id, student_id, question_id, discipline_name, course_number, received_score, score_after_appeal, score_before_appeal, discipline_score)
                    VALUES (:import_id, :student_id, :question_id, :discipline_name, :course_number, :score1, :score2, :score3, :score4)
                ");

                $stmtQuestion = $pdo->prepare("INSERT IGNORE INTO questions (question_id, question_text, max_score, question_type) VALUES (:qid, :qtext, :max, 'single')");
                $stmtStudent = $pdo->prepare("INSERT IGNORE INTO students (student_id, course_number) VALUES (:sid, 3)");
                $stmtHierQuestion = $pdo->prepare("INSERT IGNORE INTO hier_questions (question_id, question_text, max_score, question_type) VALUES (:qid, :qtext, :max, 'single_choice')");

                $inserted = 0;
                $errors = 0;
                $disciplines = [];

                foreach ($csvData as $index => $row) {
                    if ($index === 0) continue;

                    try {
                        $questionId = !empty($row['question_id']) && is_numeric($row['question_id']) ? (int)$row['question_id'] : null;
                        $studentId = !empty($row['student_id']) && is_numeric($row['student_id']) ? (int)$row['student_id'] : null;
                        $disciplineName = trim($row['discipline_name'] ?? '');
                        $courseNumber = !empty($row['course_number']) && is_numeric($row['course_number']) ? (int)$row['course_number'] : null;
                        
                        $scoreAfter = $row['score_after_appeal'] ?? '';
                        $receivedScore = ($scoreAfter !== '' && $scoreAfter !== '-' && is_numeric($scoreAfter)) ? (float)$scoreAfter : null;

                        if (!empty($questionId)) {
                            $stmtQuestion->execute([':qid' => $questionId, ':qtext' => '', ':max' => 100]);
                            $stmtHierQuestion->execute([':qid' => $questionId, ':qtext' => '', ':max' => 100]);
                        }

                        if (!empty($studentId)) {
                            $stmtStudent->execute([':sid' => $studentId]);
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

                            if ($disciplineName && !isset($disciplines[$disciplineName])) {
                                $disciplines[$disciplineName] = true;
                            }
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
            }
            
            $success = "ODS Converted and Imported! Records: {$inserted}, Disciplines: " . count($disciplines);
            
            $stats = [
                'type' => 'ods',
                'original_file' => $file['name'],
                'converted_file' => $csvFileName,
                'rows' => $converted,
                'records_imported' => $inserted ?? 0,
                'disciplines' => count($disciplines),
                'download_url' => 'uploads/' . $csvFileName,
            ];
            
        } catch (Throwable $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->query("
        SELECT * FROM user_syllabuses 
        ORDER BY uploaded_at DESC
        LIMIT 50
    ");
    $existingSyllabuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $existingSyllabuses = [];
}

$pageScripts = <<<HTML
<style>
  .upload-box{background:#fff;border-radius:12px;padding:30px;box-shadow:0 2px 8px rgba(0,0,0,0.1);max-width:900px;margin:0 auto}
  .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-top:20px}
  .stat-item{background:#f8f9fa;padding:15px;border-radius:8px;text-align:center}
  .stat-value{font-size:24px;font-weight:700;color:#1a1d21}
  .stat-label{font-size:12px;color:#64748b;margin-top:5px}
  .preview-box{background:#f8f9fa;padding:15px;border-radius:8px;max-height:300px;overflow-y:auto;font-size:13px;white-space:pre-wrap}
  .nav-tabs .nav-link{color:#64748b;border-color:#dee2e6;border-bottom:none}
  .nav-tabs .nav-link.active{color:#0f172a;background-color:#fff;border-color:#dee2e6;border-bottom-color:#fff}
  .tab-content{border:1px solid #dee2e6;border-top:none;border-radius:0 0 12px 12px;padding:25px}
</style>
HTML;

render_header('Import Syllabus & ODS');
echo $pageScripts;
?>
<div class="container-fluid py-4">
  <div class="upload-box">
    <h1 class="h3 mb-4">Import Data</h1>
    
    <?php if ($success): ?>
      <div class="alert alert-success"><?= h($success) ?></div>
      
      <?php if (!empty($stats)): ?>
        <div class="stats-grid">
          <?php if ($stats['type'] === 'syllabus'): ?>
            <div class="stat-item">
              <div class="stat-value"><?= h($stats['file_name']) ?></div>
              <div class="stat-label">File</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= strtoupper(h($stats['extension'])) ?></div>
              <div class="stat-label">Format</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= number_format($stats['text_length']) ?></div>
              <div class="stat-label">Characters</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= $stats['topics_found'] ?></div>
              <div class="stat-label">Topics Found</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= $stats['questions_linked'] ?></div>
              <div class="stat-label">Questions Linked</div>
            </div>
          <?php else: ?>
            <div class="stat-item">
              <div class="stat-value"><?= h($stats['original_file']) ?></div>
              <div class="stat-label">Original File</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= $stats['rows'] ?></div>
              <div class="stat-label">Rows Converted</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= $stats['records_imported'] ?></div>
              <div class="stat-label">Records Imported</div>
            </div>
            <div class="stat-item">
              <div class="stat-value"><?= $stats['disciplines'] ?></div>
              <div class="stat-label">Disciplines</div>
            </div>
            <div class="stat-item">
              <a href="<?= h($stats['download_url']) ?>" class="btn btn-sm btn-success">Download CSV</a>
            </div>
          <?php endif; ?>
        </div>
        
        <?php if ($stats['type'] === 'syllabus' && !empty($stats['keywords'])): ?>
          <div class="mt-3">
            <h6 class="fw-semibold">Extracted Keywords:</h6>
            <div class="d-flex flex-wrap gap-2 mt-2">
              <?php foreach (array_slice($stats['keywords'], 0, 20) as $kw): ?>
                <span class="badge bg-info"><?= h($kw) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    

    <ul class="nav nav-tabs" id="importTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'syllabus' ? 'active' : '' ?>" 
                id="syllabus-tab" data-bs-toggle="tab" data-bs-target="#syllabus" 
                type="button" role="tab">Syllabus Import</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'ods' ? 'active' : '' ?>" 
                id="ods-tab" data-bs-toggle="tab" data-bs-target="#ods" 
                type="button" role="tab">ODS Exam Results</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'criteria' ? 'active' : '' ?>" 
                id="criteria-tab" data-bs-toggle="tab" data-bs-target="#criteria" 
                type="button" role="tab">Evaluation Criteria</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'details' ? 'active' : '' ?>" 
                id="details-tab" data-bs-toggle="tab" data-bs-target="#details" 
                type="button" role="tab">Evaluation Details</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'answers' ? 'active' : '' ?>" 
                id="answers-tab" data-bs-toggle="tab" data-bs-target="#answers" 
                type="button" role="tab">Student Answers</button>
      </li>
    </ul>
    
    <div class="tab-content" id="importTabsContent">

      <div class="tab-pane fade <?= $activeTab === 'syllabus' ? 'show active' : '' ?>" 
           id="syllabus" role="tabpanel">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_input() ?>
          
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold">Discipline</label>
              <input type="text" name="discipline_name" class="form-control" required placeholder="e.g.: Pediatrics">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold">Course</label>
              <select name="course_number" class="form-select">
                <option value="1">1st year</option>
                <option value="2">2nd year</option>
                <option value="3" selected>3rd year</option>
                <option value="4">4th year</option>
                <option value="5">5th year</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Syllabus File</label>
              <input type="file" name="syllabus_file" class="form-control" accept=".docx,.doc,.pdf,.txt,.odt" required>
              <small class="text-muted">Formats: DOCX, DOC, PDF, TXT, ODT</small>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Upload & Process</button>
              <a href="index.php" class="btn btn-outline-secondary">Back to Analytics</a>
            </div>
          </div>
        </form>
      </div>
      

      <div class="tab-pane fade <?= $activeTab === 'ods' ? 'show active' : '' ?>" 
           id="ods" role="tabpanel">
        <form method="post" enctype="multipart/form-data">
          <?= csrf_input() ?>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">ODS Exam Results File</label>
            <input type="file" name="ods_file" class="form-control" accept=".ods" required>
            <small class="text-muted">Exam export file (.ods)</small>
          </div>
          <button type="submit" class="btn btn-primary">Convert & Import</button>
          <a href="index.php" class="btn btn-outline-secondary">Back to Analytics</a>
        </form>
        
        <hr>
        <div class="text-muted small">
          <p><strong>What the converter does:</strong></p>
          <ul class="mb-0">
            <li>Converts Russian/Swedish column names to English</li>
            <li>Creates CSV with ; separator</li>
            <li>Imports data to database automatically</li>
            <li>Creates syllabus topics for new disciplines</li>
            <li>Links questions to syllabus topics</li>
          </ul>
        </div>
      </div>

      <div class="tab-pane fade <?= $activeTab === 'criteria' ? 'show active' : '' ?>" 
           id="criteria" role="tabpanel">
        <form method="post" enctype="multipart/form-data" action="import_evaluation_criteria.php">
          <?= csrf_input() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Evaluation Criteria File (CSV)</label>
            <input type="file" name="criteria_file" class="form-control" accept=".csv" required>
            <small class="text-muted">Columns: ID, Қазақша, Русский, English, Вес</small>
          </div>
          <button type="submit" class="btn btn-primary">Import Criteria</button>
          <a href="index.php" class="btn btn-outline-secondary">Back to Analytics</a>
        </form>
      </div>

      <div class="tab-pane fade <?= $activeTab === 'details' ? 'show active' : '' ?>" 
           id="details" role="tabpanel">
        <form method="post" enctype="multipart/form-data" action="import_evaluation_details.php">
          <?= csrf_input() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Evaluation Details File (CSV)</label>
            <input type="file" name="details_file" class="form-control" accept=".csv" required>
            <small class="text-muted">Columns: Код работы, ID критерия, Оценка по 100 шкале</small>
          </div>
          <button type="submit" class="btn btn-primary">Import Details</button>
          <a href="index.php" class="btn btn-outline-secondary">Back to Analytics</a>
        </form>
      </div>

      <div class="tab-pane fade <?= $activeTab === 'answers' ? 'show active' : '' ?>" 
           id="answers" role="tabpanel">
        <form method="post" enctype="multipart/form-data" action="import_student_answers.php">
          <?= csrf_input() ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">Student Answers File (CSV)</label>
            <input type="file" name="answers_file" class="form-control" accept=".csv" required>
            <small class="text-muted">Columns: Код работы, Язык, Вопрос, Ответ в BASE64, ID проверяющего преподавателя, Итоговая оценка, Комментарий преподавателя к оценке, Штраф за плагиат</small>
          </div>
          <button type="submit" class="btn btn-primary">Import Answers</button>
          <a href="index.php" class="btn btn-outline-secondary">Back to Analytics</a>
        </form>
      </div>
    </div>
    
    <hr class="my-4">
    
    <h5 class="fw-semibold mb-3">Uploaded Syllabuses</h5>
    
    <?php if (empty($existingSyllabuses)): ?>
      <p class="text-muted">No syllabuses uploaded yet</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Discipline</th>
              <th>Course</th>
              <th>File</th>
              <th>Date</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($existingSyllabuses as $syl): ?>
              <tr>
                <td><strong><?= h($syl['discipline_name']) ?></strong></td>
                <td><?= (int)$syl['course_number'] ?></td>
                <td><?= h($syl['file_name']) ?></td>
                <td><?= h($syl['uploaded_at']) ?></td>
                <td><span class="badge bg-<?= $syl['status'] === 'processed' ? 'success' : 'warning' ?>"><?= h($syl['status']) ?></span></td>
                <td>
                  <a href="?preview=<?= (int)$syl['id'] ?>" class="btn btn-sm btn-outline-info">Preview</a>
                  <a href="<?= h($syl['file_path']) ?>" class="btn btn-sm btn-outline-dark" download>Download</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<script src="../assets/vendor/js/bootstrap.bundle.min.js"></script>
<?php render_footer(); ?>

<?php
function extractDocumentText(string $filePath, string $extension): string
{
    $text = '';
    
    switch ($extension) {
        case 'txt':
            $text = file_get_contents($filePath);
            break;
            
        case 'docx':
            $text = extractDocxText($filePath);
            break;
            
        case 'doc':
            $text = extractDocText($filePath);
            break;
            
        case 'pdf':
            $text = extractPdfText($filePath);
            break;
            
        case 'odt':
            $text = extractOdtText($filePath);
            break;
    }
    
    return trim($text);
}

function extractDocxText(string $filePath): string
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Failed to open DOCX file');
    }
    
    $xmlContent = $zip->getFromName('word/document.xml');
    $zip->close();
    
    if (!$xmlContent) {
        throw new Exception('File does not contain document.xml');
    }

    $xmlContent = preg_replace('/<\/w:p>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/w:tr>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/w:tc>/i', "\t", $xmlContent);
    $xmlContent = preg_replace('/<w:tab[^>]*\/>/i', "\t", $xmlContent);
    $xmlContent = strip_tags($xmlContent);
    $xmlContent = html_entity_decode($xmlContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return normalizeStructuredText($xmlContent);
}

function extractDocText(string $filePath): string
{
    $text = file_get_contents($filePath);
    $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/u', '', $text);
    return trim($text);
}

function extractPdfText(string $filePath): string
{
    $text = '';
    $handle = fopen($filePath, 'r');
    
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (preg_match('/\((.*?)\)/', $line, $matches)) {
                $text .= $matches[1] . ' ';
            }
        }
        fclose($handle);
    }
    
    return trim($text) ?: 'PDF text not extracted (library required)';
}

function extractOdtText(string $filePath): string
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception('Failed to open ODT file');
    }
    
    $xmlContent = $zip->getFromName('content.xml');
    $zip->close();
    
    if (!$xmlContent) {
        throw new Exception('File does not contain content.xml');
    }

    $xmlContent = preg_replace('/<\/text:p>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/table:table-row>/i', "\n", $xmlContent);
    $xmlContent = preg_replace('/<\/table:table-cell>/i', "\t", $xmlContent);
    $text = strip_tags($xmlContent);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return normalizeStructuredText($text);
}

function parseSyllabusQuestions(string $text): array
{
    $lines = splitStructuredLines($text);
    $questions = [];
    $insideQuestionBlock = false;
    $questionMarkers = [
        'контрольно-измерительные средства',
        'контрольно измерительные средства',
        'приложение 2',
        'примеры заданий',
        'экзаменационные вопросы',
        'вопросы',
        'сұрақтар',
        'сұрақ-жауап',
        'сұрақ жауап',
    ];

    foreach ($lines as $line) {
        $normalizedLine = mb_strtolower($line, 'UTF-8');

        foreach ($questionMarkers as $marker) {
            if (mb_strpos($normalizedLine, $marker, 0, 'UTF-8') !== false) {
                $insideQuestionBlock = true;
                continue 2;
            }
        }

        if (!$insideQuestionBlock) {
            continue;
        }

        if (preg_match('/^(приложение\s*[345]|қосымша\s*[345]|тематический план|тақырыптық жоспар)/ui', $line)) {
            break;
        }

        $candidate = cleanQuestionCandidate($line);
        if ($candidate === '') {
            continue;
        }

        if (preg_match('/^(?:\d+|[а-яәіңғүұқөһa-z])[\.\)]\s+/ui', $candidate, $match)) {
            $number = extractLeadingNumber($candidate);
            $textValue = trim(preg_replace('/^(?:\d+|[а-яәіңғүұқөһa-z])[\.\)]\s+/ui', '', $candidate));
            if (mb_strlen($textValue, 'UTF-8') >= 25) {
                $questions[] = [
                    'text' => $textValue,
                    'type' => 'syllabus_question',
                    'number' => $number,
                ];
            }
        }
    }

    if (!$questions) {
        $tailLines = array_slice($lines, (int)max(0, count($lines) * 0.65));
        foreach ($tailLines as $line) {
            $candidate = cleanQuestionCandidate($line);
            if ($candidate === '') {
                continue;
            }

            if (preg_match('/^\d+[\.\)]\s+/u', $candidate)) {
                $number = extractLeadingNumber($candidate);
                $textValue = trim(preg_replace('/^\d+[\.\)]\s+/u', '', $candidate));
                if (mb_strlen($textValue, 'UTF-8') >= 40) {
                    $questions[] = [
                        'text' => $textValue,
                        'type' => 'syllabus_question',
                        'number' => $number,
                    ];
                }
            }
        }
    }

    return deduplicateStructuredItems($questions, 'text');
}

function extractKeywords(string $text, string $disciplineName): array
{
    $stopWords = [
        'and','in','on','not','that','he','was','with','for','it','as','his','be','at','i','which','same','we',
        'had','have','from','one','had','but','word','what','were','when','your','said','there','each','use',
        'an','they','how','their','if','will','up','other','about','out','many','then','them','these','so',
        'question','answer','test','exam','student','score'
    ];
    
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    
    $keywords = [];
    foreach ($words as $word) {
        if (mb_strlen($word, 'UTF-8') >= 4 && !in_array($word, $stopWords)) {
            $keywords[$word] = ($keywords[$word] ?? 0) + 1;
        }
    }
    
    arsort($keywords);
    return array_keys(array_slice($keywords, 0, 50));
}

function parseSyllabusTopics(string $text, string $disciplineName, int $courseNumber): array
{
    $lines = splitStructuredLines($text);
    $collectedTitles = [];
    $insidePlan = false;

    for ($index = 0; $index < count($lines); $index++) {
        $line = $lines[$index];

        if (preg_match('/(тематический план|тақырыптық жоспар|план дисциплины|пәннің тақырыптық жоспары)/ui', $line)) {
            $insidePlan = true;
            continue;
        }

        if ($insidePlan && preg_match('/^(приложение\s*\d+|қосымша\s*\d+|всего[:]?|барлығы[:]?|аралық бақылау|итоговый контроль)/ui', $line)) {
            if (count($collectedTitles) >= 3) {
                break;
            }
        }

        if (!$insidePlan) {
            continue;
        }

        if (!isTopicNumberLine($line)) {
            continue;
        }

        $topicLines = [];
        for ($cursor = $index + 1; $cursor < count($lines); $cursor++) {
            $candidateLine = $lines[$cursor];

            if (isTopicNumberLine($candidateLine) || isTopicBreakLine($candidateLine)) {
                break;
            }

            if (isPureNumericLine($candidateLine) || isTopicTaskLine($candidateLine) || isTopicHeaderLine($candidateLine)) {
                continue;
            }

            $cleanedLine = trim($candidateLine, " \t\n\r\0\x0B-–—.;,");
            if ($cleanedLine === '') {
                continue;
            }

            $topicLines[] = $cleanedLine;

            if (count($topicLines) >= 3) {
                break;
            }
        }

        $topicTitle = buildTopicTitle($topicLines);
        if ($topicTitle !== '') {
            $collectedTitles[] = $topicTitle;
        }
    }

    $collectedTitles = deduplicateTextValues($collectedTitles);

    $topics = [];
    $index = 1;
    foreach ($collectedTitles as $title) {
        $topics[] = [
            'code' => strtoupper(substr(preg_replace('/[^a-z0-9]/ui', '', $disciplineName), 0, 6)) . '_T' . $index,
            'title' => $title,
            'keywords' => implode(',', array_slice(extractKeywords($title, $disciplineName), 0, 10))
        ];
        $index++;
    }

    if (empty($topics)) {
        $topics[] = [
            'code' => strtoupper(substr(preg_replace('/[^a-z0-9]/ui', '', $disciplineName), 0, 6)) . '_AUTO',
            'title' => $disciplineName . ' - Main Course',
            'keywords' => implode(',', array_slice(extractKeywords($text, $disciplineName), 0, 10))
        ];
    }

    return $topics;
}

function normalizeStructuredText(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = str_replace("\u{00A0}", ' ', $text);
    $text = preg_replace("/[ \t]+/u", ' ', $text);
    $text = preg_replace("/\n{2,}/u", "\n", $text);
    $lines = preg_split('/\n/u', $text) ?: [];
    $cleanLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line !== '') {
            $cleanLines[] = $line;
        }
    }

    return implode("\n", $cleanLines);
}

function splitStructuredLines(string $text): array
{
    $normalizedText = normalizeStructuredText($text);
    return preg_split('/\n/u', $normalizedText) ?: [];
}

function cleanTopicCandidate(string $line): string
{
    if (!preg_match('/^\s*\d+[\.\)]/u', $line)) {
        return '';
    }

    $line = preg_replace('/^\s*\d+[\.\)]\s*/u', '', $line);
    $line = preg_replace('/^(?:[A-ZА-ЯЁӘІҢҒҮҰҚӨҺ]{1,8}|[A-ZА-ЯЁӘІҢҒҮҰҚӨҺ]{1,8}\s+[A-ZА-ЯЁӘІҢҒҮҰҚӨҺ]{1,8})\s+/u', '', $line);
    $line = preg_split('/(?:Приложение|Қосымша|Тақырыпты|Тақырып бойынша|Ситуациялық|Ауызша|Жағдайлық|Microsoft Power Point|Электронды тасымалдаудағы|Электронды әдебиетпен)/ui', $line)[0] ?? $line;
    $line = preg_replace('/\s+\d+(?:\s+\d+){0,6}\s*$/u', '', $line);
    $line = trim($line, " \t\n\r\0\x0B-–—.;,");

    return mb_strlen($line, 'UTF-8') >= 12 ? $line : '';
}

function isTopicNumberLine(string $line): bool
{
    return (bool)preg_match('/^\d+\s*[\.\)]\s*$/u', trim($line));
}

function isPureNumericLine(string $line): bool
{
    return (bool)preg_match('/^\d+(?:\s+\d+){0,6}$/u', trim($line));
}

function isTopicTaskLine(string $line): bool
{
    return (bool)preg_match('/(тақырыпты|ситуац|ауызша|жағдайлық|презентац|powerpoint|moodle|алгоритм|электронды|приложение|қосымша|сұрақтарына жауап|оқытушы жетекшілігімен)/ui', $line);
}

function isTopicHeaderLine(string $line): bool
{
    return (bool)preg_match('/^(№|п\/п|бөлім|раздел|тақырып|тема|тапсырмалар|задания|дәріс|лекции|пз|сөож|сро|ср оп|па|всего часов|барылық сағат|жалпы)$/ui', trim($line));
}

function isTopicBreakLine(string $line): bool
{
    return (bool)preg_match('/^(аралық бақылау|итоговый контроль|всего[:]?|барлығы[:]?|приложение\s*\d+|қосымша\s*\d+)/ui', trim($line));
}

function buildTopicTitle(array $lines): string
{
    if (!$lines) {
        return '';
    }

    if (count($lines) >= 2 && isLikelyTopicSectionLabel($lines[0])) {
        array_shift($lines);
    }

    if (!$lines) {
        return '';
    }

    $titleParts = [];
    foreach ($lines as $line) {
        if (isLikelyTopicSectionLabel($line) && $titleParts) {
            continue;
        }

        $titleParts[] = $line;

        if (count($titleParts) >= 2) {
            break;
        }
    }

    $title = trim(implode(' ', $titleParts));
    $title = preg_replace('/\s+/u', ' ', $title);
    $title = trim((string)$title, " \t\n\r\0\x0B-–—.;,");

    return mb_strlen((string)$title, 'UTF-8') >= 12 ? (string)$title : '';
}

function isLikelyTopicSectionLabel(string $line): bool
{
    $line = trim($line);

    if ($line === '') {
        return false;
    }

    if (preg_match('/^(жж|онк|био|пат|кредит\s*\d+)/ui', $line)) {
        return true;
    }

    return (bool)preg_match('/^(патологическая физиология|патологическая анатомия|биологическая химия|жүйке жүйесі|патологиялық физиология|патологиялық анатомия)$/ui', $line);
}

function cleanQuestionCandidate(string $line): string
{
    $line = trim($line);
    $line = preg_replace('/\s+/u', ' ', $line);
    return $line;
}

function deduplicateTextValues(array $values): array
{
    $uniqueValues = [];
    $seen = [];

    foreach ($values as $value) {
        $normalized = mb_strtolower(trim($value), 'UTF-8');
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        $uniqueValues[] = trim($value);
    }

    return $uniqueValues;
}

function deduplicateStructuredItems(array $items, string $field): array
{
    $uniqueItems = [];
    $seen = [];

    foreach ($items as $item) {
        $value = trim((string)($item[$field] ?? ''));
        $normalized = mb_strtolower($value, 'UTF-8');
        if ($normalized === '' || isset($seen[$normalized])) {
            continue;
        }
        $seen[$normalized] = true;
        $uniqueItems[] = $item;
    }

    return $uniqueItems;
}

function extractLeadingNumber(string $line): ?int
{
    if (preg_match('/^(\d+)[\.\)]/u', $line, $match)) {
        return (int)$match[1];
    }

    return null;
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
        preg_match_all('/<table:table-cell[^>]*>(.*?)<\/table:table-cell>/s', $rowContent, $cellMatches, PREG_SET_ORDER);
        
        $currentRow = [];
        foreach ($cellMatches as $cellMatch) {
            preg_match('/<text:p[^>]*>(.*?)<\/text:p>/s', $cellMatch[1], $textMatch);
            $currentRow[] = isset($textMatch[1]) ? trim($textMatch[1]) : '';
        }
        
        if (empty($currentRow)) continue;
        
        if (empty($headers)) {
            $headers = $currentRow;
        } else {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $currentRow[$index] ?? '';
            }
            $data[] = $rowData;
        }
    }
    
    return $data;
}

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
