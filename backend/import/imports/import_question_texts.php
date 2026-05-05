<?php

ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
set_time_limit(300);

require_once __DIR__ . '/../../../ai_analytics/includes/auth.php';
require_once __DIR__ . '/../../../ai_analytics/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../import.php');
    exit;
}

verify_csrf_or_fail();

$redirectUrl = '../import.php?import_tab=questions';

try {
    if (!isset($_FILES['questions_file']) || !isset($_POST['import_id']) || !isset($_POST['discipline_name'])) {
        throw new Exception('Не все поля заполнены');
    }

    $file = $_FILES['questions_file'];
    $importId = (int)$_POST['import_id'];
    $disciplineName = trim((string)$_POST['discipline_name']);

    if ($importId <= 0) {
        throw new Exception('Сначала выбери выгрузку результатов');
    }

    if ($disciplineName === '') {
        throw new Exception('Выбери дисциплину');
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new Exception('Не удалось загрузить файл');
    }

    $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if ($extension !== 'txt') {
        throw new Exception('Поддерживается только TXT');
    }

    $stmtImport = $pdo->prepare("
        SELECT import_id
        FROM imports_log
        WHERE import_id = :import_id
          AND import_type = 'exam_results_upload'
        LIMIT 1
    ");
    $stmtImport->execute([':import_id' => $importId]);
    if (!$stmtImport->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Выгрузка результатов не найдена');
    }

    $text = readQuestionTextFile($file['tmp_name']);
    $parsedQuestions = parseNumberedQuestions($text);

    if (empty($parsedQuestions)) {
        throw new Exception('Не удалось выделить вопросы из файла');
    }

    $stmtQuestionIds = $pdo->prepare("
        SELECT DISTINCT question_id
        FROM raw_exam_results
        WHERE import_id = :import_id
          AND discipline_name = :discipline_name
          AND question_id IS NOT NULL
        ORDER BY question_id
    ");
    $stmtQuestionIds->execute([
        ':import_id' => $importId,
        ':discipline_name' => $disciplineName
    ]);
    $questionIds = $stmtQuestionIds->fetchAll(PDO::FETCH_COLUMN) ?: [];

    if (empty($questionIds)) {
        throw new Exception('Для выбранной дисциплины в этой выгрузке не найдено question_id');
    }

    $warning = null;
    if (count($questionIds) !== count($parsedQuestions)) {
        $warning = 'Количество вопросов не совпадает: в TXT ' . count($parsedQuestions) . ', в выгрузке ' . count($questionIds) . '. Будет загружено ' . min(count($questionIds), count($parsedQuestions)) . ' вопросов.';
    }

    $pdo->beginTransaction();

    $stmtLog = $pdo->prepare("
        INSERT INTO imports_log (source_filename, source_format, rows_total, rows_imported, import_type)
        VALUES (:filename, 'txt', :total, 0, 'question_texts')
    ");
    $stmtLog->execute([
        ':filename' => $file['name'],
        ':total' => count($parsedQuestions)
    ]);
    $logImportId = (int)$pdo->lastInsertId();

    $stmtUpdateQuestions = $pdo->prepare("
        UPDATE questions
        SET question_text = :question_text
        WHERE question_id = :question_id
    ");

    $stmtUpdateHierQuestions = $pdo->prepare("
        UPDATE hier_questions
        SET question_text = :question_text
        WHERE question_id = :question_id
    ");

    $updated = 0;
    $count = min(count($questionIds), count($parsedQuestions));

    for ($i = 0; $i < $count; $i++) {
        $questionId = $questionIds[$i];
        $questionText = $parsedQuestions[$i];
        $params = [
            ':question_text' => $questionText,
            ':question_id' => (int)$questionId
        ];

        $stmtUpdateQuestions->execute($params);
        $stmtUpdateHierQuestions->execute($params);
        $updated++;
    }

    $stmtFinishLog = $pdo->prepare("UPDATE imports_log SET rows_imported = :rows_imported WHERE import_id = :import_id");
    $stmtFinishLog->execute([
        ':rows_imported' => $updated,
        ':import_id' => $logImportId
    ]);

    $pdo->commit();

    $message = 'Импортировано вопросов: ' . $updated . '. Дисциплина: ' . $disciplineName;
    if ($warning) {
        $message .= ' ' . $warning;
    }

    $_SESSION['question_text_import_flash'] = [
        'type' => 'success',
        'message' => $message
    ];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Throwable $rollbackError) {
        }
    }

    $_SESSION['question_text_import_flash'] = [
        'type' => 'danger',
        'message' => $e->getMessage()
    ];
}

header('Location: ' . $redirectUrl);
exit;

function readQuestionTextFile(string $filePath): string
{
    $content = file_get_contents($filePath);
    if ($content === false) {
        throw new Exception('Не удалось прочитать TXT файл');
    }

    $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'CP1251', 'ASCII'], true);
    if ($encoding && $encoding !== 'UTF-8') {
        $converted = @mb_convert_encoding($content, 'UTF-8', $encoding);
        if (is_string($converted) && $converted !== '') {
            $content = $converted;
        }
    }

    return str_replace("\r\n", "\n", str_replace("\r", "\n", $content));
}

function parseNumberedQuestions(string $text): array
{
    $lines = preg_split('/\n/u', $text) ?: [];
    $questions = [];
    $currentText = '';
    $expectedNumber = 1;
    $foundFirstQuestion = false;
    $prevLineBlank = true;

    foreach ($lines as $line) {
        $cleanLine = trim($line);

        if ($cleanLine === '') {
            if ($currentText !== '' && $foundFirstQuestion) {
                $currentText .= "\n";
            }
            $prevLineBlank = true;
            continue;
        }

        if ($prevLineBlank && preg_match('/^(\d+)\.\s*(.*)$/u', $cleanLine, $matches)) {
            $number = (int)$matches[1];
            if ($number === $expectedNumber) {
                if ($currentText !== '' && $foundFirstQuestion) {
                    $questions[] = normalizeQuestionText($currentText);
                }
                $currentText = trim($matches[2]);
                $expectedNumber++;
                $foundFirstQuestion = true;
                $prevLineBlank = false;
                continue;
            }
        }

        if ($foundFirstQuestion) {
            $currentText = $currentText === '' ? $cleanLine : $currentText . "\n" . $cleanLine;
        }
        $prevLineBlank = false;
    }

    if ($currentText !== '' && $foundFirstQuestion) {
        $questions[] = normalizeQuestionText($currentText);
    }

    return array_values(array_filter($questions, static fn ($item) => $item !== ''));
}

function normalizeQuestionText(string $text): string
{
    $text = preg_replace("/\n{3,}/u", "\n\n", trim($text)) ?? trim($text);
    return trim($text);
}
