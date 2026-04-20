<?php

session_start();


$availableLanguages = ['kz' => 'Қазақша', 'ru' => 'Русский', 'en' => 'English'];
$currentLang = $_SESSION['ai_lang'] ?? 'ru';


if (isset($_GET['lang'])) {
    $newLang = $_GET['lang'];
    if (isset($availableLanguages[$newLang])) {
        $_SESSION['ai_lang'] = $newLang;
        $currentLang = $newLang;
    }
}


if (isset($_POST['set_language'])) {
    $newLang = $_POST['language'] ?? 'ru';
    if (isset($availableLanguages[$newLang])) {
        $_SESSION['ai_lang'] = $newLang;
        $currentLang = $newLang;
    }
}


$translations = [
    'kz' => [
        'title' => 'AI Аналитика',
        'import' => 'Импорт',
        'validation' => 'Валидация',
        'quality' => 'Сапа талдауы',
        'correlation' => 'Корреляция',
        'students' => 'Студенттер',
        'settings' => 'Баптаулар',
        'validation_desc' => 'Сұрақтарды оқу бағдарламасына сәйкестікке тексеру: орташа балл, әрекет саны, тақырыппен байланыс.',
        'quality_desc' => 'Сұрақтар сапасының талдауы: орташа балл, сәтті жауаптар пайызы, қиындық, дискриминация.',
        'correlation_desc' => 'Сұрақ бағасы мен пән бойынша жалпы балл арасындағы корреляция (Пирсон коэффициенті).',
        'students_desc' => 'Студенттер жауаптарының үлгілерінің талдауы: орташа балл, минимум, пән бойынша жалпы, мәселелер түрі.',
        'th_id' => 'ID',
        'th_topic' => 'Тақырып',
        'th_avg_score' => 'Орташа балл',
        'th_attempts' => 'Әрекет саны',
        'th_status' => 'Статус',
        'th_issues' => 'Ескертулер',
        'th_n' => 'N',
        'th_avg' => 'Орташа',
        'th_success_pct' => 'Сәттілік %',
        'th_difficulty_pct' => 'Қиындық %',
        'th_flag' => 'Жалау',
        'th_discrimination' => 'Дискриминация',
        'th_reason' => 'Себеп',
        'th_coefficient' => 'Коэффициент r',
        'th_min' => 'Минимум',
        'th_total' => 'Жалпы',
        'th_type' => 'Түрі',
        'th_notes' => 'Ескертпелер',
        'no_data' => 'Деректер жоқ',
        'select_import' => 'Талдау үшін импортты таңдаңыз',
        'overview' => 'Шолу',
        'validation' => 'Валидация',
        'quality' => 'Сапа талдауы',
        'correlations' => 'Корреляциялар',
        'students' => 'Студенттер',
        'semantic' => 'Семантика',
        'import' => 'Импорт',
        'rules' => 'Ережелер',
        'settings' => 'Баптаулар',
        'containers' => 'Контейнерлер',
        'subtitle' => 'Емтихан сұрақтары сапасын талдауға арналған модульді жүйе',
        'select_data_import' => 'Деректер импортын таңдаңыз:',
        'apply' => 'Қолдану',
        'questions' => 'Сұрақтар',
        'students' => 'Студенттер',
        'attempts' => 'Әрекет саны',
        'avg_score' => 'Орташа балл',
        'available_services' => 'Қол жетімді қызметтер',
        'quick_access' => 'Жылдам қол жетімділік',
        'question_quality_analysis' => 'Сұрақтар сапасын талдау',
        'syllabus_validation' => 'Силлабус валидациясы',
        'student_patterns' => 'Студенттер үлгілері',
        'container_management' => 'Контейнерлер басқарушысы',
        'syllabus_import' => 'Силлабус импорты',
        'ods_exam_results' => 'ODS емтихан нәтижелері',
        'link_syllabus_questions' => 'Сұрақтарды силлабусқа байлау',
        'duplicate_detection' => 'Көшірмелерді анықтау',
        'discipline' => 'Пән',
        'course' => 'Курс',
        'syllabus_file' => 'Силлабус файлы',
        'formats' => 'Форматтар: DOCX, DOC, PDF, TXT, ODT',
        'upload_process' => 'Жүктеу және өңдеу',
        'ods_file' => 'ODS нәтиже файлы',
        'exam_export_file' => 'Емтихан экспорт файлы (.ods)',
        'convert_import' => 'Түрлендіру және импорттау',
        'converter_description' => 'Түрлендіргіш не істейді:',
        'converter_1' => 'Орыс/швед баған атауларын ағылшынға түрлендіреді',
        'converter_2' => 'CSV бөлгішімен құрады ;',
        'converter_3' => 'Деректерді дерекқорға автоматты түрде импорттайды',
        'converter_4' => 'Жаңа пәндер үшін силлабус тақырыптарын құрады',
        'converter_5' => 'Сұрақтарды силлабус тақырыптарына байлайды',
        'discipline_name' => 'Пән атауы',
        'course_number' => 'Курс нөмірі',
        'link_questions_syllabus' => 'Сұрақтарды силлабусқа байлау',
        'similarity_threshold' => 'Ұқсастық шегі',
        'similarity_desc' => 'Бұл шектен жоғары ұқсастығы бар сұрақтар көшірме деп белгіленеді',
        'detection_method' => 'Анықтау әдісі',
        'embeddings' => 'Embeddings (дәлірек)',
        'tfidf' => 'TF-IDF (жылдамырақ)',
        'hybrid' => 'Гибридті (екеуі де)',
        'detect_duplicates' => 'Көшірмелерді анықтау',
        'duplicate_results' => 'Көшірме анықтау нәтижелері',
        'run_detection' => 'Нәтижелерді көру үшін анықтауды іске қосыңыз',
        'import_statistics' => 'Импорт статистикасы',
        'syllabuses' => 'Силлабустар',
        'disciplines' => 'Пәндер',
        'data_imports' => 'Деректер импорттары',
        'recent_syllabuses' => 'Соңғы силлабустар',
        'no_syllabuses' => 'Силлабустар әлі жүктелмеген',
        'q1' => 'С1',
        'q2' => 'С2',
        'similarity' => 'Ұқсастық',
        'method' => 'Әдіс',
        'select_questions' => 'Сұрақтарды таңдаңыз',
        'select_syllabus_topic' => 'Силлабус тақырыбын таңдаңыз',
        'link_selected' => 'Таңдалғандарды байлау',
        'unlinked_questions' => 'Байланбаған сұрақтар',
        'no_unlinked_questions' => 'Байланбаған сұрақтар жоқ',
        'clear_data' => 'Деректерді тазалау',
        'clear_data_confirm' => 'Таңдалған деректерді тазалау керек пе? Бұл әрекетті болдырмау мүмкін емес.',
        'clear_data_success' => 'Деректер сәтті тазаланды',
        'clear_selected' => 'Таңдалғандарды тазалау',
        'cleared_items' => 'Тазаланды',
        'select_items_to_clear' => 'Тазалау үшін элементтерді таңдаңыз',
        'exam_results' => 'Емтихан нәтижелері',
        'syllabuses' => 'Силлабустар',
        'questions' => 'Сұрақтар',
        'students' => 'Студенттер',
        'uploaded_files' => 'Жүктелген файлдар',
    ],
    'ru' => [
        'title' => 'AI Аналитика',
        'import' => 'Импорт',
        'validation' => 'Валидация',
        'quality' => 'Quality Analysis',
        'correlation' => 'Correlation Analysis',
        'students' => 'Student Patterns',
        'settings' => 'Настройки',
        'validation_desc' => 'Проверка вопросов на соответствие силлабусу: средний балл, количество попыток, наличие привязки к теме программы.',
        'quality_desc' => 'Анализ качества вопросов: средний балл, процент успешных ответов, сложность, дискриминативность.',
        'correlation_desc' => 'Корреляция между оценкой за вопрос и итоговым баллом по дисциплине (коэффициент Пирсона).',
        'students_desc' => 'Анализ паттернов ответов студентов: средний балл, минимум, итог по дисциплине, тип проблем.',
        'th_id' => 'ID',
        'th_topic' => 'Тема',
        'th_avg_score' => 'Средний балл',
        'th_attempts' => 'Попыток',
        'th_status' => 'Статус',
        'th_issues' => 'Замечания',
        'th_n' => 'N',
        'th_avg' => 'Средний',
        'th_success_pct' => '% успеха',
        'th_difficulty_pct' => 'Сложность %',
        'th_flag' => 'Флаг',
        'th_discrimination' => 'Дискриминативность',
        'th_reason' => 'Причина',
        'th_coefficient' => 'Коэффициент r',
        'th_min' => 'Мин',
        'th_total' => 'Итог',
        'th_type' => 'Тип',
        'th_notes' => 'Заметки',
        'no_data' => 'Нет данных',
        'select_import' => 'Выберите импорт для анализа',
        'overview' => 'Обзор',
        'validation' => 'Валидация',
        'quality' => 'Quality Analysis',
        'correlations' => 'Корреляции',
        'students' => 'Студенты',
        'semantic' => 'Семантика',
        'import' => 'Импорт',
        'rules' => 'Правила',
        'settings' => 'Настройки',
        'containers' => 'Контейнеры',
        'subtitle' => 'Модульная система для анализа качества экзаменационных вопросов',
        'select_data_import' => 'Выберите импорт данных:',
        'apply' => 'Применить',
        'questions' => 'Вопросы',
        'students' => 'Студенты',
        'attempts' => 'Попытки',
        'avg_score' => 'Средний балл',
        'available_services' => 'Доступные сервисы',
        'quick_access' => 'Быстрый доступ',
        'question_quality_analysis' => 'Анализ качества вопросов',
        'syllabus_validation' => 'Валидация силлабуса',
        'student_patterns' => 'Паттерны студентов',
        'container_management' => 'Управление контейнерами',
        'syllabus_import' => 'Импорт силлабуса',
        'ods_exam_results' => 'Результаты экзамена ODS',
        'link_syllabus_questions' => 'Привязка силлабуса к вопросам',
        'duplicate_detection' => 'Обнаружение дубликатов',
        'discipline' => 'Дисциплина',
        'course' => 'Курс',
        'syllabus_file' => 'Файл силлабуса',
        'formats' => 'Форматы: DOCX, DOC, PDF, TXT, ODT',
        'upload_process' => 'Загрузить и обработать',
        'ods_file' => 'Файл результатов ODS',
        'exam_export_file' => 'Файл экспорта экзамена (.ods)',
        'convert_import' => 'Конвертировать и импортировать',
        'converter_description' => 'Что делает конвертер:',
        'converter_1' => 'Преобразует русские/шведские названия колонок в английские',
        'converter_2' => 'Создает CSV с разделителем ;',
        'converter_3' => 'Автоматически импортирует данные в базу данных',
        'converter_4' => 'Создает темы силлабуса для новых дисциплин',
        'converter_5' => 'Привязывает вопросы к темам силлабуса',
        'discipline_name' => 'Название дисциплины',
        'course_number' => 'Номер курса',
        'link_questions_syllabus' => 'Привязать вопросы к силлабусу',
        'similarity_threshold' => 'Порог сходства',
        'similarity_desc' => 'Вопросы со сходством выше этого порога будут помечены как дубликаты',
        'detection_method' => 'Метод обнаружения',
        'embeddings' => 'Embeddings (точнее)',
        'tfidf' => 'TF-IDF (быстрее)',
        'hybrid' => 'Гибридный (оба)',
        'detect_duplicates' => 'Обнаружить дубликаты',
        'duplicate_results' => 'Результаты обнаружения дубликатов',
        'run_detection' => 'Запустите обнаружение для просмотра результатов',
        'import_statistics' => 'Статистика импорта',
        'syllabuses' => 'Силлабусы',
        'disciplines' => 'Дисциплины',
        'data_imports' => 'Импорты данных',
        'recent_syllabuses' => 'Последние силлабусы',
        'no_syllabuses' => 'Силлабусы еще не загружены',
        'q1' => 'В1',
        'q2' => 'В2',
        'similarity' => 'Сходство',
        'method' => 'Метод',
        'select_questions' => 'Выберите вопросы',
        'select_syllabus_topic' => 'Выберите тему силлабуса',
        'link_selected' => 'Привязать выбранные',
        'unlinked_questions' => 'Непривязанные вопросы',
        'no_unlinked_questions' => 'Нет непривязанных вопросов',
        'clear_data_confirm' => 'Вы уверены, что хотите очистить выбранные данные? Это действие нельзя отменить.',
        'clear_data_success' => 'Данные успешно очищены',
        'clear_selected' => 'Очистить выбранное',
        'cleared_items' => 'Очищено',
        'select_items_to_clear' => 'Выберите элементы для очистки',
        'exam_results' => 'Результаты экзаменов',
        'syllabuses' => 'Силлабусы',
        'questions' => 'Вопросы',
        'students' => 'Студенты',
        'uploaded_files' => 'Загруженные файлы',
    ],
    'en' => [
        'title' => 'AI Analytics',
        'import' => 'Import',
        'validation' => 'Validation',
        'quality' => 'Quality Analysis',
        'correlation' => 'Correlation Analysis',
        'students' => 'Student Patterns',
        'settings' => 'Settings',
        'validation_desc' => 'Check questions against syllabus: average score, attempts, topic linkage.',
        'quality_desc' => 'Question quality analysis: average score, success rate, difficulty, discrimination.',
        'correlation_desc' => 'Correlation between question score and total discipline score (Pearson coefficient).',
        'students_desc' => 'Student response pattern analysis: average score, minimum, total by discipline, issue types.',
        'th_id' => 'ID',
        'th_topic' => 'Topic',
        'th_avg_score' => 'Avg Score',
        'th_attempts' => 'Attempts',
        'th_status' => 'Status',
        'th_issues' => 'Issues',
        'th_n' => 'N',
        'th_avg' => 'Avg',
        'th_success_pct' => 'Success %',
        'th_difficulty_pct' => 'Difficulty %',
        'th_flag' => 'Flag',
        'th_discrimination' => 'Discrimination',
        'th_reason' => 'Reason',
        'th_coefficient' => 'Coefficient r',
        'th_min' => 'Min',
        'th_total' => 'Total',
        'th_type' => 'Type',
        'th_notes' => 'Notes',
        'no_data' => 'No data',
        'select_import' => 'Select import for analysis',
        'overview' => 'Overview',
        'validation' => 'Validation',
        'quality' => 'Quality',
        'correlations' => 'Correlations',
        'students' => 'Students',
        'semantic' => 'Semantic',
        'import' => 'Import',
        'rules' => 'Rules',
        'settings' => 'Settings',
        'containers' => 'Containers',
        'subtitle' => 'Modular system for exam question quality analysis',
        'select_data_import' => 'Select data import:',
        'apply' => 'Apply',
        'questions' => 'Questions',
        'students' => 'Students',
        'attempts' => 'Attempts',
        'avg_score' => 'Avg Score',
        'available_services' => 'Available Services',
        'quick_access' => 'Quick Access',
        'question_quality_analysis' => 'Question Quality Analysis',
        'syllabus_validation' => 'Syllabus Validation',
        'student_patterns' => 'Student Patterns',
        'container_management' => 'Container Management',
        'syllabus_import' => 'Syllabus Import',
        'ods_exam_results' => 'ODS Exam Results',
        'link_syllabus_questions' => 'Link Syllabus to Questions',
        'duplicate_detection' => 'Duplicate Detection',
        'discipline' => 'Discipline',
        'course' => 'Course',
        'syllabus_file' => 'Syllabus File',
        'formats' => 'Formats: DOCX, DOC, PDF, TXT, ODT',
        'upload_process' => 'Upload & Process',
        'ods_file' => 'ODS Exam Results File',
        'exam_export_file' => 'Exam export file (.ods)',
        'convert_import' => 'Convert & Import',
        'converter_description' => 'What the converter does:',
        'converter_1' => 'Converts Russian/Swedish column names to English',
        'converter_2' => 'Creates CSV with ; separator',
        'converter_3' => 'Imports data to database automatically',
        'converter_4' => 'Creates syllabus topics for new disciplines',
        'converter_5' => 'Links questions to syllabus topics',
        'discipline_name' => 'Discipline Name',
        'course_number' => 'Course Number',
        'link_questions_syllabus' => 'Link Questions to Syllabus',
        'similarity_threshold' => 'Similarity Threshold',
        'similarity_desc' => 'Questions with similarity above this threshold will be flagged as duplicates',
        'detection_method' => 'Detection Method',
        'embeddings' => 'Embeddings (more accurate)',
        'tfidf' => 'TF-IDF (faster)',
        'hybrid' => 'Hybrid (both)',
        'detect_duplicates' => 'Detect Duplicates',
        'duplicate_results' => 'Duplicate Detection Results',
        'run_detection' => 'Run detection to see results',
        'import_statistics' => 'Import Statistics',
        'syllabuses' => 'Syllabuses',
        'disciplines' => 'Disciplines',
        'data_imports' => 'Data Imports',
        'recent_syllabuses' => 'Recent Syllabuses',
        'no_syllabuses' => 'No syllabuses uploaded yet',
        'q1' => 'Q1',
        'q2' => 'Q2',
        'similarity' => 'Similarity',
        'method' => 'Method',
        'select_questions' => 'Select questions',
        'select_syllabus_topic' => 'Select syllabus topic',
        'link_selected' => 'Link selected',
        'unlinked_questions' => 'Unlinked questions',
        'no_unlinked_questions' => 'No unlinked questions',
        'clear_data' => 'Clear Data',
        'clear_data_confirm' => 'Are you sure you want to clear selected data? This action cannot be undone.',
        'clear_data_success' => 'Data cleared successfully',
        'clear_selected' => 'Clear Selected',
        'cleared_items' => 'Cleared',
        'select_items_to_clear' => 'Select items to clear',
        'exam_results' => 'Exam Results',
        'syllabuses' => 'Syllabuses',
        'questions' => 'Questions',
        'students' => 'Students',
        'uploaded_files' => 'Uploaded Files',
    ],
];

$t = $translations[$currentLang];

require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/AIAnalyticsService.php';
require_once __DIR__ . '/services/TfIdfService.php';
require_once __DIR__ . '/services/RuleClassifierService.php';
require_once __DIR__ . '/api/router.php';

require_role(['superadmin', 'admin', 'teacher']);
$config = require __DIR__ . '/config.php';


$imports = [];
try {
    $imports = $pdo->query("
        SELECT import_id, source_filename, rows_imported, created_at 
        FROM imports_log 
        WHERE import_type = 'exam_results_upload' AND rows_imported > 0 
        ORDER BY import_id DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$importId = null;
if (isset($_SESSION['ai_analytics_import_id'])) {
    foreach ($imports as $imp) {
        if ((int)$imp['import_id'] === (int)$_SESSION['ai_analytics_import_id']) {
            $importId = (int)$imp['import_id'];
            break;
        }
    }
}
if ($importId === null && !empty($imports)) {
    $importId = (int)$imports[0]['import_id'];
    $_SESSION['ai_analytics_import_id'] = $importId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_id'])) {
    verify_csrf_or_fail();
    $selectedImportId = (int)$_POST['import_id'];
    foreach ($imports as $imp) {
        if ((int)$imp['import_id'] === $selectedImportId) {
            $_SESSION['ai_analytics_import_id'] = $selectedImportId;
            $importId = $selectedImportId;
            break;
        }
    }
}


$ai = new AIAnalyticsService($pdo, $importId);
$tfidf = new TfIdfService($pdo);
$rules = new RuleClassifierService($pdo);
$router = new AnalyticsServiceRouter ($pdo);

$section = $_GET['section'] ?? 'overview';
$sections = [
    'overview' => $t['overview'],
    'validation' => $t['validation'],
    'quality' => $t['quality'],
    'correlation' => $t['correlations'],
    'students' => $t['students'],
    'semantic' => $t['semantic'],
    'import' => $t['import'],
    'rules' => $t['rules'],
    'settings' => $t['settings'],
    'containers' => $t['containers'],
];

render_header($t['title']);
?>

<style>
.ai-container { max-width: 1400px; margin: 0 auto; }
.ai-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 30px; }
.ai-nav { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 30px; }
.ai-nav-btn { padding: 12px 20px; border: none; border-radius: 8px; background: #f1f5f9; cursor: pointer; transition: all 0.2s; font-weight: 500; }
.ai-nav-btn:hover { background: #e2e8f0; transform: translateY(-2px); }
.ai-nav-btn.active { background: #667eea; color: white; }
.ai-section { display: none; animation: fadeIn 0.3s; }
.ai-section.active { display: block; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.stat-value { font-size: 32px; font-weight: 700; color: #1a1d21; }
.stat-label { color: #64748b; font-size: 14px; margin-top: 5px; }
.table-container { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.badge-soft { padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; display: inline-block; }
.badge-soft.success { background: #d1fae5; color: #059669; }
.badge-soft.warning { background: #fef3c7; color: #d97706; }
.badge-soft.danger { background: #fee2e2; color: #dc2626; }
.badge-soft.info { background: #dbeafe; color: #2563eb; }
.container-card { border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; margin-bottom: 15px; }
.container-card.running { border-color: #10b981; background: #f0fdf4; }
.container-card.stopped { border-color: #ef4444; background: #fef2f2; }
.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
.form-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.pagination { display: flex; gap: 5px; margin-top: 20px; justify-content: center; }
.pagination button { padding: 8px 16px; border: 1px solid #e2e8f0; background: white; cursor: pointer; border-radius: 6px; }
.pagination button:hover { background: #f1f5f9; }
.pagination button.active { background: #667eea; color: white; border-color: #667eea; }
.pagination button:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<div class="ai-container">
  <div class="ai-header">
    <h1 class="h2 mb-2"><?= $t['title'] ?></h1>
    <p class="mb-0 opacity-75"><?= $t['subtitle'] ?></p>
  </div>
  
  <?php if ($importId === null && !empty($imports)): ?>
    <div class="alert alert-warning mb-4">
      <strong><?= $t['select_data_import'] ?></strong>
      <form method="post" class="d-flex gap-2 mt-2">
        <?= csrf_input() ?>
        <select name="import_id" class="form-select" style="max-width: 400px;">
          <?php foreach ($imports as $imp): ?>
            <option value="<?= (int)$imp['import_id'] ?>"><?= h($imp['source_filename']) ?> (<?= (int)$imp['rows_imported'] ?>)</option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-warning"><?= $t['apply'] ?></button>
      </form>
    </div>
  <?php endif; ?>
  
  <div class="ai-nav">
    <?php foreach ($sections as $key => $label): ?>
      <button class="ai-nav-btn <?= $section === $key ? 'active' : '' ?>" onclick="showSection('<?= $key ?>')"><?= $label ?></button>
    <?php endforeach; ?>
  </div>
  
  <div id="section-overview" class="ai-section <?= $section === 'overview' ? 'active' : '' ?>">
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value">
          <?php
          if ($importId) {
            try {
              $stmt = $pdo->prepare("SELECT COUNT(DISTINCT question_id) FROM raw_exam_results WHERE import_id = ?");
              $stmt->execute([$importId]);
              echo number_format($stmt->fetchColumn() ?: 0);
            } catch (Throwable $e) { echo '0'; }
          } else { echo '0'; }
          ?>
        </div>
        <div class="stat-label"><?= $t['questions'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-value">
          <?php
          if ($importId) {
            try {
              $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM raw_exam_results WHERE import_id = ?");
              $stmt->execute([$importId]);
              echo number_format($stmt->fetchColumn() ?: 0);
            } catch (Throwable $e) { echo '0'; }
          } else { echo '0'; }
          ?>
        </div>
        <div class="stat-label"><?= $t['students'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-value">
          <?php
          if ($importId) {
            try {
              $stmt = $pdo->prepare("SELECT COUNT(*) FROM raw_exam_results WHERE import_id = ?");
              $stmt->execute([$importId]);
              echo number_format($stmt->fetchColumn() ?: 0);
            } catch (Throwable $e) { echo '0'; }
          } else { echo '0'; }
          ?>
        </div>
        <div class="stat-label"><?= $t['attempts'] ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-value">
          <?php
          if ($importId) {
            try {
              $stmt = $pdo->prepare("SELECT AVG(score_after_appeal) FROM raw_exam_results WHERE import_id = ?");
              $stmt->execute([$importId]);
              echo number_format($stmt->fetchColumn() ?: 0, 1);
            } catch (Throwable $e) { echo '0'; }
          } else { echo '0'; }
          ?>
        </div>
        <div class="stat-label"><?= $t['avg_score'] ?></div>
      </div>
    </div>
    
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header bg-primary text-white"><?= $t['available_services'] ?></div>
          <div class="card-body">
            <ul class="list-group list-group-flush">
              <?php foreach ($router->getActiveAnalysisModules() as $svc): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <?= ucfirst($svc['module_name']) ?>
                  <span class="badge bg-<?= $svc['operational_status'] === 'running' || $svc['operational_status'] === 'active' ? 'success' : 'secondary' ?> rounded-pill">
                    <?= $svc['operational_status'] ?>
                  </span>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card h-100">
          <div class="card-header bg-info text-white"><?= $t['quick_access'] ?></div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <a href="?section=quality" class="btn btn-outline-primary"><?= $t['question_quality_analysis'] ?></a>
              <a href="?section=validation" class="btn btn-outline-success"><?= $t['syllabus_validation'] ?></a>
              <a href="?section=students" class="btn btn-outline-warning"><?= $t['student_patterns'] ?></a>
              <a href="?section=containers" class="btn btn-outline-dark"><?= $t['container_management'] ?></a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div id="section-import" class="ai-section <?= $section === 'import' ? 'active' : '' ?>">
    <?php
    $importError = null;
    $importSuccess = null;
    $importStats = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_or_fail();
        
        if (isset($_FILES['syllabus_file'])) {
            $file = $_FILES['syllabus_file'];
            $disciplineName = trim($_POST['discipline_name'] ?? '');
            $courseNumber = (int)($_POST['course_number'] ?? 3);
            
            if (empty($disciplineName)) {
                $importError = 'Specify discipline name';
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $importError = 'Upload error: ' . $file['error'];
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
                    

                    $pdo->beginTransaction();
                    

                    $checkStmt = $pdo->prepare("
                        SELECT id FROM user_syllabuses 
                        WHERE discipline_name = :disc 
                        AND course_number = :course 
                        AND file_name = :fname 
                        AND uploaded_by = :uid
                    ");
                    $checkStmt->execute([
                        ':disc' => $disciplineName,
                        ':course' => $courseNumber,
                        ':fname' => $file['name'],
                        ':uid' => current_user()['user_id']
                    ]);
                    
                    if ($checkStmt->fetch()) {
                        throw new Exception('This syllabus has already been uploaded');
                    }
                    
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
                        INSERT IGNORE INTO ai_syllabus_topics (discipline_name, course_number, topic_code, title, keywords)
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
                    
                    $pdo->commit();
                    
                    $importSuccess = "Syllabus uploaded! Topics saved: {$savedTopics}";
                    $importStats = [
                        'type' => 'syllabus',
                        'file_name' => $file['name'],
                        'extension' => $extension,
                        'text_length' => strlen($text),
                        'topics_found' => $savedTopics,
                        'keywords' => $keywords,
                    ];
                    
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        try { $pdo->rollBack(); } catch (Throwable $e) {}
                    }
                    $importError = 'Error: ' . $e->getMessage();
                }
            }
        }
        

        if (isset($_FILES['ods_file'])) {
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
                        
                        $questionId = $row['ID тематики'] ?? $row['ID вопроса'] ?? $row['ID fråga'] ?? $row['ID вопроса'] ?? '';
                        $studentId = $row['ID студента'] ?? $row['ID student'] ?? '';
                        $disciplineName = $row['Дисциплина'] ?? $row['Disciplin'] ?? '';
                        $courseNumber = $row['Курс'] ?? $row['Kurs'] ?? '';
                        $scoreAfter = $row['Оценка после апелляции'] ?? $row['Bedömning efter överklagande'] ?? '';
                        $scoreBefore = $row['Оценка до апелляции'] ?? $row['Bedömning före överklagande'] ?? '';
                        $scoreDiscipline = $row['Оценка за дисциплину'] ?? $row['Bedömning för disciplin'] ?? '';
                        

                        $finalScore = $scoreAfter !== '' && $scoreAfter !== '-' ? $scoreAfter : ($scoreBefore !== '' && $scoreBefore !== '-' ? $scoreBefore : $scoreDiscipline);
                        
                        fputcsv($handle, [$questionId, $studentId, $disciplineName, $courseNumber, $finalScore, $scoreBefore, $scoreDiscipline], ';');
                        $converted++;
                    }
                    
                    fclose($handle);
                    

                    $csvData = readCsvFile($csvFilePath);
                    
                    $inserted = 0;
                    $disciplines = [];
                    
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

                        foreach ($csvData as $index => $row) {
                            if ($index === 0) continue;

                            try {
                                $questionId = !empty($row['question_id']) && is_numeric($row['question_id']) ? (int)$row['question_id'] : null;
                                $studentId = !empty($row['student_id']) && is_numeric($row['student_id']) ? (int)$row['student_id'] : null;
                                $disciplineName = trim($row['discipline_name'] ?? '');
                                $courseNumber = !empty($row['course_number']) && is_numeric($row['course_number']) ? (int)$row['course_number'] : null;
                                
                                $scoreAfter = $row['score_after_appeal'] ?? '';
                                $scoreBefore = $row['score_before_appeal'] ?? '';
                                $scoreDiscipline = $row['discipline_score'] ?? '';
                                

                                $receivedScore = null;
                                if ($scoreBefore !== '' && $scoreBefore !== '-' && is_numeric($scoreBefore)) {
                                    $receivedScore = (float)$scoreBefore;
                                } elseif ($scoreAfter !== '' && $scoreAfter !== '-' && is_numeric($scoreAfter)) {
                                    $receivedScore = (float)$scoreAfter;
                                } elseif ($scoreDiscipline !== '' && $scoreDiscipline !== '-' && is_numeric($scoreDiscipline)) {
                                    $receivedScore = (float)$scoreDiscipline;
                                }

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
                            } catch (Throwable $e) {}
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
                    }
                    
                    $importSuccess = "ODS Converted and Imported! Records: {$inserted}, Disciplines: " . count($disciplines);
                    $importStats = [
                        'type' => 'ods',
                        'original_file' => $file['name'],
                        'converted_file' => $csvFileName,
                        'rows' => $converted,
                        'records_imported' => $inserted ?? 0,
                        'disciplines' => count($disciplines),
                        'download_url' => 'uploads/' . $csvFileName,
                    ];
                    
                } catch (Throwable $e) {
                    $importError = 'Error: ' . $e->getMessage();
                }
            }
        }
        

        if (isset($_POST['auto_link_discipline'])) {
            $disciplineName = trim($_POST['auto_link_discipline']);
            $courseNumber = (int)($_POST['auto_link_course'] ?? 3);
            
            try {
                $stmt = $pdo->prepare("
                    UPDATE hier_questions hq
                    INNER JOIN ai_syllabus_topics st ON st.discipline_name = :disc AND st.course_number = :course
                    SET hq.syllabus_topic_id = st.syllabus_topic_id
                    WHERE hq.syllabus_topic_id IS NULL
                ");
                $stmt->execute([':disc' => $disciplineName, ':course' => $courseNumber]);
                $linkedCount = $stmt->rowCount();
                $importSuccess = "Auto-linked {$linkedCount} questions to '{$disciplineName}' syllabus";
            } catch (Throwable $e) {
                $importError = 'Error: ' . $e->getMessage();
            }
        }
        

        if (isset($_POST['link_selected_questions'])) {
            $syllabusTopicId = (int)($_POST['syllabus_topic_id'] ?? 0);
            $questionIds = $_POST['question_ids'] ?? [];
            
            if ($syllabusTopicId > 0 && !empty($questionIds)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE hier_questions 
                        SET syllabus_topic_id = :topic_id 
                        WHERE question_id = :qid
                    ");
                    $linkedCount = 0;
                    foreach ($questionIds as $qid) {
                        $stmt->execute([':topic_id' => $syllabusTopicId, ':qid' => (int)$qid]);
                        $linkedCount += $stmt->rowCount();
                    }
                    $importSuccess = "Linked {$linkedCount} questions to syllabus topic";
                } catch (Throwable $e) {
                    $importError = 'Error: ' . $e->getMessage();
                }
            } else {
                $importError = 'Please select a syllabus topic and at least one question';
            }
        }
        

        if (isset($_POST['clear_data'])) {
            try {
                $pdo->beginTransaction();
                
                $clearOptions = $_POST['clear_options'] ?? [];
                $clearedItems = [];
                
                if (in_array('exam_results', $clearOptions)) {
                    $pdo->exec("DELETE FROM raw_exam_results");
                    $pdo->exec("DELETE FROM imports_log");
                    $clearedItems[] = $t['exam_results'];
                }
                
                if (in_array('syllabuses', $clearOptions)) {
                    $pdo->exec("DELETE FROM ai_syllabus_topics");
                    $pdo->exec("DELETE FROM user_syllabuses");
                    $clearedItems[] = $t['syllabuses'];
                }
                
                if (in_array('questions', $clearOptions)) {
                    $pdo->exec("DELETE FROM hier_questions");
                    $pdo->exec("DELETE FROM questions");
                    $pdo->exec("DELETE FROM ai_question_ai");
                    $clearedItems[] = $t['questions'];
                }
                
                if (in_array('students', $clearOptions)) {
                    $pdo->exec("DELETE FROM students");
                    $clearedItems[] = $t['students'];
                }
                
                if (in_array('uploads', $clearOptions)) {

                    $uploadsDir = __DIR__ . '/uploads';
                    if (is_dir($uploadsDir)) {
                        $files = glob($uploadsDir . '/*');
                        foreach ($files as $file) {
                            if (is_file($file) && basename($file) !== '.gitkeep') {
                                unlink($file);
                            }
                        }

                        $syllabusesDir = $uploadsDir . '/syllabuses';
                        if (is_dir($syllabusesDir)) {
                            $syllabusFiles = glob($syllabusesDir . '/*');
                            foreach ($syllabusFiles as $file) {
                                if (is_file($file)) {
                                    unlink($file);
                                }
                            }
                        }
                    }
                    $clearedItems[] = $t['uploaded_files'];
                }
                
                if (empty($clearedItems)) {
                    $importError = $t['select_items_to_clear'];
                } else {
                    $pdo->commit();
                    $importSuccess = $t['cleared_items'] . ': ' . implode(', ', $clearedItems);
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    try { $pdo->rollBack(); } catch (Throwable $e) {}
                }
                $importError = 'Error: ' . $e->getMessage();
            }
        }
    }
    

    try {
        $stmt = $pdo->query("
            SELECT * FROM user_syllabuses 
            ORDER BY uploaded_at DESC
            LIMIT 10
        ");
        $existingSyllabuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $existingSyllabuses = [];
    }
    ?>
    
    <?php if ($importSuccess): ?>
      <div class="alert alert-success"><?= h($importSuccess) ?></div>
      
      <?php if (!empty($importStats)): ?>
        <div class="stats-grid mb-4">
          <?php if ($importStats['type'] === 'syllabus'): ?>
            <div class="stat-card">
              <div class="stat-value"><?= h($importStats['file_name']) ?></div>
              <div class="stat-label">File</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= strtoupper(h($importStats['extension'])) ?></div>
              <div class="stat-label">Format</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= number_format($importStats['text_length']) ?></div>
              <div class="stat-label">Characters</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['topics_found'] ?></div>
              <div class="stat-label">Topics Found</div>
            </div>
          <?php else: ?>
            <div class="stat-card">
              <div class="stat-value"><?= h($importStats['original_file']) ?></div>
              <div class="stat-label">Original File</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['rows'] ?></div>
              <div class="stat-label">Rows Converted</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['records_imported'] ?></div>
              <div class="stat-label">Records Imported</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $importStats['disciplines'] ?></div>
              <div class="stat-label">Disciplines</div>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php if ($importError): ?>
      <div class="alert alert-danger"><?= h($importError) ?></div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div class="dropdown">

        <div class="dropdown-menu p-3" style="min-width: 250px;">
          <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="clear_data" value="1">
            <div class="mb-2">
              <input type="checkbox" name="clear_options[]" value="exam_results" id="clear_exam_results" class="form-check-input">
              <label for="clear_exam_results" class="form-check-label"><?= $t['exam_results'] ?></label>
            </div>
            <div class="mb-2">
              <input type="checkbox" name="clear_options[]" value="syllabuses" id="clear_syllabuses" class="form-check-input">
              <label for="clear_syllabuses" class="form-check-label"><?= $t['syllabuses'] ?></label>
            </div>
            <div class="mb-2">
              <input type="checkbox" name="clear_options[]" value="questions" id="clear_questions" class="form-check-input">
              <label for="clear_questions" class="form-check-label"><?= $t['questions'] ?></label>
            </div>
            <div class="mb-2">
              <input type="checkbox" name="clear_options[]" value="students" id="clear_students" class="form-check-input">
              <label for="clear_students" class="form-check-label"><?= $t['students'] ?></label>
            </div>
            <div class="mb-2">
              <input type="checkbox" name="clear_options[]" value="uploads" id="clear_uploads" class="form-check-input">
              <label for="clear_uploads" class="form-check-label"><?= $t['uploaded_files'] ?></label>
            </div>
            <button type="submit" class="btn btn-danger btn-sm w-100 mt-2" onsubmit="return confirm('<?= $t['clear_data_confirm'] ?>')"><?= $t['clear_selected'] ?></button>
          </form>
        </div>
      </div>
      <form method="post" class="d-inline">
        <?= csrf_input() ?>
        <input type="hidden" name="set_language" value="1">

      </form>
    </div>
    
    <ul class="nav nav-tabs mb-4" id="importTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="syllabus-tab" data-bs-toggle="tab" data-bs-target="#syllabus" type="button" role="tab"><?= $t['syllabus_import'] ?></button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="ods-tab" data-bs-toggle="tab" data-bs-target="#ods" type="button" role="tab"><?= $t['ods_exam_results'] ?></button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="syllabus-link-tab" data-bs-toggle="tab" data-bs-target="#syllabus-link" type="button" role="tab"><?= $t['link_syllabus_questions'] ?></button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="duplicates-tab" data-bs-toggle="tab" data-bs-target="#duplicates" type="button" role="tab"><?= $t['duplicate_detection'] ?></button>
      </li>
    </ul>
    
    <div class="tab-content" id="importTabsContent">
      <div class="tab-pane fade show active" id="syllabus" role="tabpanel">
        <form method="post" enctype="multipart/form-data" class="mb-4">
          <?= csrf_input() ?>
          
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label fw-semibold"><?= $t['discipline'] ?></label>
              <input type="text" name="discipline_name" class="form-control" required placeholder="e.g.: Pediatrics">
            </div>
            <div class="col-md-4">
              <label class="form-label fw-semibold"><?= $t['course'] ?></label>
              <select name="course_number" class="form-select">
                <option value="1">1st year</option>
                <option value="2">2nd year</option>
                <option value="3" selected>3rd year</option>
                <option value="4">4th year</option>
                <option value="5">5th year</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold"><?= $t['syllabus_file'] ?></label>
              <input type="file" name="syllabus_file" class="form-control" accept=".docx,.doc,.pdf,.txt,.odt" required>
              <small class="text-muted"><?= $t['formats'] ?></small>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><?= $t['upload_process'] ?></button>
            </div>
          </div>
        </form>
      </div>
      
      <div class="tab-pane fade" id="ods" role="tabpanel">
        <form method="post" enctype="multipart/form-data" class="mb-4">
          <?= csrf_input() ?>
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= $t['ods_file'] ?></label>
            <input type="file" name="ods_file" class="form-control" accept=".ods" required>
            <small class="text-muted"><?= $t['exam_export_file'] ?></small>
          </div>
          <button type="submit" class="btn btn-primary"><?= $t['convert_import'] ?></button>
        </form>
        
        <div class="text-muted small">
          <p><strong><?= $t['converter_description'] ?></strong></p>
          <ul class="mb-0">
            <li><?= $t['converter_1'] ?></li>
            <li><?= $t['converter_2'] ?></li>
            <li><?= $t['converter_3'] ?></li>
            <li><?= $t['converter_4'] ?></li>
            <li><?= $t['converter_5'] ?></li>
          </ul>
        </div>
      </div>
      
      <div class="tab-pane fade" id="syllabus-link" role="tabpanel">
        <div class="alert alert-info">
          <strong><?= $t['select_questions'] ?></strong>
          <ul class="mb-0 mt-2">
            <li>Выберите конкретные вопросы для привязки к силлабусу</li>
            <li>Выберите тему силлабуса из выпадающего списка</li>
          </ul>
        </div>
        
        <?php

        $allQuestions = [];
        $totalQuestions = 0;
        $queryError = '';
        try {

            $stmtCount = $pdo->query("SELECT COUNT(*) as count FROM hier_questions");
            $totalQuestions = $stmtCount->fetch()['count'] ?? 0;
            

            $stmt = $pdo->query("
                SELECT hq.question_id, hq.question_text, hq.syllabus_topic_id,
                       st.title as syllabus_title,
                       r.discipline_name
                FROM hier_questions hq
                LEFT JOIN raw_exam_results r ON r.question_id = hq.question_id
                LEFT JOIN ai_syllabus_topics st ON st.syllabus_topic_id = hq.syllabus_topic_id
                GROUP BY hq.question_id
            ");
            $allQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $queryError = $e->getMessage();
        }
        

        $syllabusTopics = [];
        try {
            $stmt = $pdo->query("
                SELECT st.syllabus_topic_id, st.discipline_name, st.course_number, st.title
                FROM ai_syllabus_topics st
                INNER JOIN user_syllabuses us ON 
                    us.discipline_name = st.discipline_name
                ORDER BY st.discipline_name, st.course_number, st.title
                LIMIT 100
            ");
            $syllabusTopics = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {}
        ?>
        
        <form method="post" class="mb-4">
          <?= csrf_input() ?>
          <input type="hidden" name="link_selected_questions" value="1">
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= $t['select_syllabus_topic'] ?> (Доступно тем: <?= count($syllabusTopics) ?>)</label>
            <select name="syllabus_topic_id" class="form-select" required>
              <option value="">-- <?= $t['select_syllabus_topic'] ?> --</option>
              <?php foreach ($syllabusTopics as $topic): ?>
                <option value="<?= (int)$topic['syllabus_topic_id'] ?>">
                  <?= h($topic['discipline_name']) ?> - <?= h($topic['course_number']) ?>: <?= h($topic['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold">Все вопросы (Всего: <?= $totalQuestions ?>, Показано: <?= count($allQuestions) ?>)</label>
            <input type="text" id="questionSearch" class="form-control mb-2" placeholder="Поиск по ID, вопросу или дисциплине..." onkeyup="filterQuestions()">
            <?php if ($queryError): ?>
              <div class="alert alert-danger">Ошибка запроса: <?= h($queryError) ?></div>
            <?php endif; ?>
            <?php if (empty($allQuestions)): ?>
              <p class="text-muted">Нет вопросов в базе данных</p>
            <?php else: ?>
              <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm table-hover" id="questionsTable">
                  <thead>
                    <tr>
                      <th style="width: 50px;"><input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes(this)"></th>
                      <th class="sortable" data-sort="id" style="cursor: pointer;">ID ⬍</th>
                      <th class="sortable" data-sort="question" style="cursor: pointer;">Question ⬍</th>
                      <th class="sortable" data-sort="discipline" style="cursor: pointer;">Discipline ⬍</th>
                      <th class="sortable" data-sort="topic" style="cursor: pointer;">Current Topic ⬍</th>
                    </tr>
                  </thead>
                  <tbody id="questionsTableBody">
                    <?php foreach ($allQuestions as $q): ?>
                      <tr data-id="<?= (int)$q['question_id'] ?>" data-question="<?= h(mb_strtolower($q['question_text'] ?? '')) ?>" data-discipline="<?= h(mb_strtolower($q['discipline_name'] ?? '')) ?>" data-topic="<?= h(mb_strtolower($q['syllabus_title'] ?? '')) ?>">
                        <td style="text-align: center;"><input type="checkbox" name="question_ids[]" value="<?= (int)$q['question_id'] ?>" class="question-checkbox" style="transform: scale(1.2); cursor: pointer;"></td>
                        <td><?= (int)$q['question_id'] ?></td>
                        <td><?= h(mb_substr($q['question_text'] ?? '', 0, 80)) ?></td>
                        <td><?= h($q['discipline_name'] ?? '') ?></td>
                        <td><?= $q['syllabus_title'] ? h($q['syllabus_title']) : '<span class="text-muted">Не привязан</span>' ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          
          <button type="submit" class="btn btn-primary" <?= empty($allQuestions) ? 'disabled' : '' ?>><?= $t['link_selected'] ?></button>
        </form>
      </div>
      
      <div class="tab-pane fade" id="duplicates" role="tabpanel">
        <form method="post" class="mb-4">
          <?= csrf_input() ?>
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= $t['similarity_threshold'] ?></label>
            <input type="number" step="0.01" min="0" max="1" name="dedup_threshold" class="form-control" value="0.85">
            <small class="text-muted"><?= $t['similarity_desc'] ?></small>
          </div>
          
          <div class="mb-3">
            <label class="form-label fw-semibold"><?= $t['detection_method'] ?></label>
            <select name="dedup_method" class="form-select">
              <option value="embeddings"><?= $t['embeddings'] ?></option>
              <option value="tfidf"><?= $t['tfidf'] ?></option>
              <option value="hybrid"><?= $t['hybrid'] ?></option>
            </select>
          </div>
          
          <button type="submit" class="btn btn-primary"><?= $t['detect_duplicates'] ?></button>
        </form>
        
        <?php if ($importId !== null): ?>
          <div class="mt-4">
            <h6 class="fw-semibold mb-3"><?= $t['duplicate_results'] ?></h6>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead><tr><th><?= $t['q1'] ?></th><th><?= $t['q2'] ?></th><th><?= $t['similarity'] ?></th><th><?= $t['method'] ?></th></tr></thead>
                <tbody>
                  <tr><td colspan="4" class="text-muted"><?= $t['run_detection'] ?></td></tr>
                </tbody>
              </table>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <div class="row g-4">
      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-primary text-white"><?= $t['import_statistics'] ?></div>
          <div class="card-body">
            <?php
            try {
              $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_syllabuses");
              $totalSyllabuses = $stmt->fetch()['count'] ?? 0;
              $stmt = $pdo->query("SELECT COUNT(DISTINCT discipline_name) as count FROM ai_syllabus_topics");
              $totalDisciplines = $stmt->fetch()['count'] ?? 0;
              $stmt = $pdo->query("SELECT COUNT(*) as count FROM imports_log WHERE import_type = 'exam_results_upload'");
              $totalImports = $stmt->fetch()['count'] ?? 0;
            } catch (Throwable $e) {
              $totalSyllabuses = 0;
              $totalDisciplines = 0;
              $totalImports = 0;
            }
            ?>
            <div class="stats-grid">
              <div class="stat-item">
                <div class="stat-value"><?= $totalSyllabuses ?></div>
                <div class="stat-label"><?= $t['syllabuses'] ?></div>
              </div>
              <div class="stat-item">
                <div class="stat-value"><?= $totalDisciplines ?></div>
                <div class="stat-label"><?= $t['disciplines'] ?></div>
              </div>
              <div class="stat-item">
                <div class="stat-value"><?= $totalImports ?></div>
                <div class="stat-label"><?= $t['data_imports'] ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-header bg-info text-white"><?= $t['recent_syllabuses'] ?></div>
          <div class="card-body">
            <?php if (empty($existingSyllabuses)): ?>
              <p class="text-muted"><?= $t['no_syllabuses'] ?></p>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($existingSyllabuses as $syl): ?>
                  <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                      <strong><?= h($syl['discipline_name']) ?></strong>
                      <br><small class="text-muted"><?= h($syl['file_name']) ?></small>
                    </div>
                    <span class="badge bg-<?= $syl['status'] === 'processed' ? 'success' : 'warning' ?>"><?= h($syl['status']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  
  <div id="section-validation" class="ai-section <?= $section === 'validation' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= $t['validation'] ?></h3>
      <p class="text-muted small mb-3"><?= $t['validation_desc'] ?></p>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['validation_page']) ? max(1, (int)$_GET['validation_page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $questions = $ai->getQuestionAnalysisList();
        $validationData = [];
        if (!empty($questions)) {
            foreach ($questions as $q):
                $v = $ai->validateQuestionSyllabusAlignment((int)$q['question_id']);
                if ($v):
                    $statusClass = $v['status'] === 'ok' ? 'success' : 'warning';
                    $validationData[] = [
                        'question_id' => (int)$v['question_id'],
                        'syllabus_title' => $v['syllabus_title'],
                        'avg_score' => $v['avg_score'],
                        'attempts' => $v['attempts'],
                        'status_class' => $statusClass,
                        'status' => $v['status'],
                        'issues' => implode(', ', $v['issues'])
                    ];
                endif;
            endforeach;
        }

        $totalItems = count($validationData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($validationData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover sortable">
            <thead><tr><th class="sortable" data-sort="id"><?= $t['th_id'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_topic'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_avg_score'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_attempts'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_status'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_issues'] ?> ⬍</th></tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="6" class="text-muted">' . $t['no_data'] . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= h($row['syllabus_title']) ?></td>
                  <td><?= $row['avg_score'] ?></td>
                  <td><?= $row['attempts'] ?></td>
                  <td><span class="badge badge-soft <?= $row['status_class'] ?>"><?= $row['status'] ?></span></td>
                  <td><?= $row['issues'] ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?section=validation&validation_page=<?= $page - 1 ?>"><button>&laquo;</button></a>
          <?php else: ?>
            <button disabled>&laquo;</button>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
              <button class="active"><?= $i ?></button>
            <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
              <a href="?section=validation&validation_page=<?= $i ?>"><button><?= $i ?></button></a>
            <?php elseif (abs($i - $page) == 3): ?>
              <button disabled>...</button>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?section=validation&validation_page=<?= $page + 1 ?>"><button>&raquo;</button></a>
          <?php else: ?>
            <button disabled>&raquo;</button>
          <?php endif; ?>
        </div>
        <p class="text-center text-muted small">Страница <?= $page ?> из <?= $totalPages ?> (<?= $totalItems ?> записей)</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted"><?= $t['select_import'] ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-quality" class="ai-section <?= $section === 'quality' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= $t['quality'] ?></h3>
      <p class="text-muted small mb-3"><?= $t['quality_desc'] ?></p>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['quality_page']) ? max(1, (int)$_GET['quality_page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $disciplineMap = [];
        try {
            $stmtDisc = $pdo->query("
                SELECT DISTINCT question_id, discipline_name
                FROM raw_exam_results
            ");
            while ($row = $stmtDisc->fetch()) {
                $disciplineMap[$row['question_id']] = $row['discipline_name'];
            }
        } catch (Throwable $e) {}

        $metrics = $ai->getQuestionQualityMetrics();
        $disc = $ai->getDiscriminationIndex();
        $dmap = [];
        foreach ($disc as $d) { $dmap[(int)$d['question_id']] = $d; }

        $qualityData = [];
        if (!empty($metrics)) {
            foreach ($metrics as $m):
                $discData = $dmap[$m['question_id']] ?? null;
                $flagClass = $m['flag'] === 'normal' ? 'success' : ($m['flag'] === 'too_easy' ? 'warning' : 'danger');
                $flagText = $m['flag'] === 'normal' ? 'Норма' : ($m['flag'] === 'too_easy' ? 'Легкий' : 'Сложный');
                $qualityData[] = [
                    'question_id' => $m['question_id'],
                    'discipline' => $disciplineMap[$m['question_id']] ?? '',
                    'n' => $m['n'],
                    'mean_score' => $m['mean_score'],
                    'p_correct_proxy' => $m['p_correct_proxy'],
                    'difficulty_pct' => $m['difficulty_pct'],
                    'flag_class' => $flagClass,
                    'flag_text' => $flagText,
                    'discrimination' => $discData ? $discData['discrimination'] . ' (' . $discData['label'] . ')' : '—',
                    'reason' => $m['reason']
                ];
            endforeach;
        }

        $totalItems = count($qualityData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($qualityData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover sortable">
            <thead><tr><th class="sortable" data-sort="id"><?= $t['th_id'] ?> ⬍</th><th class="sortable" data-sort="text">Discipline ⬍</th><th class="sortable" data-sort="number"><?= $t['th_n'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_avg'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_success_pct'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_difficulty_pct'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_flag'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_discrimination'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_reason'] ?> ⬍</th></tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="9" class="text-muted">' . $t['no_data'] . '</td></tr>';
              else:
                foreach ($pagedData as $row):
                  $discData = $dmap[$row['question_id']] ?? null;
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= h($row['discipline']) ?></td>
                  <td><?= $row['n'] ?></td>
                  <td><?= $row['mean_score'] ?></td>
                  <td><?= $row['p_correct_proxy'] ?></td>
                  <td><?= $row['difficulty_pct'] ?>%</td>
                  <td><span class="badge badge-soft <?= $row['flag_class'] ?>"><?= $row['flag_text'] ?></span></td>
                  <td><?= $row['discrimination'] ?></td>
                  <td><?= h($row['reason']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?section=quality&quality_page=<?= $page - 1 ?>"><button>&laquo;</button></a>
          <?php else: ?>
            <button disabled>&laquo;</button>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
              <button class="active"><?= $i ?></button>
            <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
              <a href="?section=quality&quality_page=<?= $i ?>"><button><?= $i ?></button></a>
            <?php elseif (abs($i - $page) == 3): ?>
              <button disabled>...</button>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?section=quality&quality_page=<?= $page + 1 ?>"><button>&raquo;</button></a>
          <?php else: ?>
            <button disabled>&raquo;</button>
          <?php endif; ?>
        </div>
        <p class="text-center text-muted small">Страница <?= $page ?> из <?= $totalPages ?> (<?= $totalItems ?> записей)</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted"><?= $t['select_import'] ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-correlation" class="ai-section <?= $section === 'correlation' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= $t['correlation'] ?></h3>
      <p class="text-muted small mb-3"><?= $t['correlation_desc'] ?></p>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['correlation_page']) ? max(1, (int)$_GET['correlation_page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $corr = $ai->getItemTotalCorrelation();
        $correlationData = [];
        if (!empty($corr)) {
            foreach ($corr as $c):
                $flagClass = $c['flag'] === 'ok' ? 'success' : 'warning';
                $correlationData[] = [
                    'question_id' => $c['question_id'],
                    'r' => $c['r'],
                    'flag_class' => $flagClass,
                    'flag' => $c['flag']
                ];
            endforeach;
        }

        $totalItems = count($correlationData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($correlationData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover sortable">
            <thead><tr><th class="sortable" data-sort="id"><?= $t['th_id'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_coefficient'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_flag'] ?> ⬍</th></tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="3" class="text-muted">' . $t['no_data'] . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= $row['r'] ?></td>
                  <td><span class="badge badge-soft <?= $row['flag_class'] ?>"><?= $row['flag'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?section=correlation&correlation_page=<?= $page - 1 ?>"><button>&laquo;</button></a>
          <?php else: ?>
            <button disabled>&laquo;</button>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
              <button class="active"><?= $i ?></button>
            <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
              <a href="?section=correlation&correlation_page=<?= $i ?>"><button><?= $i ?></button></a>
            <?php elseif (abs($i - $page) == 3): ?>
              <button disabled>...</button>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?section=correlation&correlation_page=<?= $page + 1 ?>"><button>&raquo;</button></a>
          <?php else: ?>
            <button disabled>&raquo;</button>
          <?php endif; ?>
        </div>
        <p class="text-center text-muted small">Страница <?= $page ?> из <?= $totalPages ?> (<?= $totalItems ?> записей)</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted"><?= $t['select_import'] ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-students" class="ai-section <?= $section === 'students' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3"><?= $t['students'] ?></h3>
      <p class="text-muted small mb-3"><?= $t['students_desc'] ?></p>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['students_page']) ? max(1, (int)$_GET['students_page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $patterns = $ai->getStudentRiskPatterns();
        $studentsData = [];
        if (!empty($patterns)) {
            foreach ($patterns as $p):
                $typeClass = $p['pattern_type'] === 'ok' ? 'success' : ($p['pattern_type'] === 'systemic' ? 'danger' : 'warning');
                $studentsData[] = [
                    'student_id' => $p['student_id'],
                    'avg_item' => $p['avg_item'],
                    'min_item' => $p['min_item'],
                    'discipline_total' => $p['discipline_total'],
                    'type_class' => $typeClass,
                    'pattern_type' => $p['pattern_type'],
                    'notes' => implode(', ', $p['notes'])
                ];
            endforeach;
        }

        $totalItems = count($studentsData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($studentsData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover sortable">
            <thead><tr><th class="sortable" data-sort="id"><?= $t['th_id'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_avg'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_min'] ?> ⬍</th><th class="sortable" data-sort="number"><?= $t['th_total'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_type'] ?> ⬍</th><th class="sortable" data-sort="text"><?= $t['th_notes'] ?> ⬍</th></tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="6" class="text-muted">' . $t['no_data'] . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['student_id'] ?></strong></td>
                  <td><?= $row['avg_item'] ?></td>
                  <td><?= $row['min_item'] ?></td>
                  <td><?= $row['discipline_total'] ?></td>
                  <td><span class="badge badge-soft <?= $row['type_class'] ?>"><?= $row['pattern_type'] ?></span></td>
                  <td><?= $row['notes'] ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?section=students&students_page=<?= $page - 1 ?>"><button>&laquo;</button></a>
          <?php else: ?>
            <button disabled>&laquo;</button>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
              <button class="active"><?= $i ?></button>
            <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
              <a href="?section=students&students_page=<?= $i ?>"><button><?= $i ?></button></a>
            <?php elseif (abs($i - $page) == 3): ?>
              <button disabled>...</button>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?section=students&students_page=<?= $page + 1 ?>"><button>&raquo;</button></a>
          <?php else: ?>
            <button disabled>&raquo;</button>
          <?php endif; ?>
        </div>
        <p class="text-center text-muted small">Страница <?= $page ?> из <?= $totalPages ?> (<?= $totalItems ?> записей)</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted"><?= $t['select_import'] ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-semantic" class="ai-section <?= $section === 'semantic' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3">Semantic Analysis</h3>
      <?php if ($importId !== null): ?>
        <?php
        $page = isset($_GET['semantic_page']) ? max(1, (int)$_GET['semantic_page']) : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $semantic = $tfidf->analyzeQuestionSyllabusAlignment();
        $semanticData = [];
        if (!empty($semantic)) {
            foreach ($semantic as $s):
                $statusClass = $s['alignment_analysis']['alignment_level'] === 'high' ? 'success' : ($s['alignment_analysis']['alignment_level'] === 'medium' ? 'warning' : 'danger');
                $semanticData[] = [
                    'question_id' => $s['question_id'],
                    'syllabus_title' => $s['syllabus_title'],
                    'discipline_name' => $s['discipline_name'],
                    'average_score' => $s['alignment_analysis']['syllabus_context']['average_score'],
                    'status_class' => $statusClass,
                    'alignment_level' => $s['alignment_analysis']['alignment_level']
                ];
            endforeach;
        }

        $totalItems = count($semanticData);
        $totalPages = ceil($totalItems / $perPage);
        $page = min($page, max(1, $totalPages));
        $offset = ($page - 1) * $perPage;
        $pagedData = array_slice($semanticData, $offset, $perPage);
        ?>
        <div class="table-responsive">
          <table class="table table-hover sortable">
            <thead><tr><th class="sortable" data-sort="id">ID ⬍</th><th class="sortable" data-sort="text">Тема ⬍</th><th class="sortable" data-sort="text">Дисциплина ⬍</th><th class="sortable" data-sort="number">Средний балл ⬍</th><th class="sortable" data-sort="text">Статус ⬍</th></tr></thead>
            <tbody>
              <?php if (empty($pagedData)): echo '<tr><td colspan="5" class="text-muted">' . $t['no_data'] . '</td></tr>';
              else:
                foreach ($pagedData as $row):
              ?>
                <tr>
                  <td><strong><?= $row['question_id'] ?></strong></td>
                  <td><?= h($row['syllabus_title']) ?></td>
                  <td><?= h($row['discipline_name']) ?></td>
                  <td><?= $row['average_score'] ?></td>
                  <td><span class="badge badge-soft <?= $row['status_class'] ?>"><?= $row['alignment_level'] ?></span></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php if ($page > 1): ?>
            <a href="?section=semantic&semantic_page=<?= $page - 1 ?>"><button>&laquo;</button></a>
          <?php else: ?>
            <button disabled>&laquo;</button>
          <?php endif; ?>
          
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <?php if ($i == $page): ?>
              <button class="active"><?= $i ?></button>
            <?php elseif (abs($i - $page) <= 2 || $i == 1 || $i == $totalPages): ?>
              <a href="?section=semantic&semantic_page=<?= $i ?>"><button><?= $i ?></button></a>
            <?php elseif (abs($i - $page) == 3): ?>
              <button disabled>...</button>
            <?php endif; ?>
          <?php endfor; ?>
          
          <?php if ($page < $totalPages): ?>
            <a href="?section=semantic&semantic_page=<?= $page + 1 ?>"><button>&raquo;</button></a>
          <?php else: ?>
            <button disabled>&raquo;</button>
          <?php endif; ?>
        </div>
        <p class="text-center text-muted small">Страница <?= $page ?> из <?= $totalPages ?> (<?= $totalItems ?> записей)</p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-muted"><?= $t['select_import'] ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div id="section-rules" class="ai-section <?= $section === 'rules' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3">Конструктор правил</h3>
      
      <div class="row mb-4">
        <div class="col-md-6">
          <h6 class="fw-semibold mb-3">Активные правила</h6>
          <?php
          $activeRules = $rules->getActiveClassificationRules();
          if (empty($activeRules)):
          ?>
            <div class="alert alert-info">Правила не настроены</div>
          <?php else: ?>
            <div class="list-group">
              <?php foreach ($activeRules as $rule): ?>
                <div class="list-group-item">
                  <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1"><?= h($rule['rule_name']) ?></h6>
                    <span class="badge bg-success">Активно</span>
                  </div>
                  <small class="text-muted">Порядок: <?= (int)$rule['rule_order'] ?></small>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="col-md-6">
          <h6 class="fw-semibold mb-3">Создать новое правило</h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label">Название правила</label>
              <input type="text" name="rule_name" class="form-control" required placeholder="Например: Слишком сложные вопросы">
            </div>
            <div class="mb-3">
              <label class="form-label">Метрика</label>
              <select name="metric_name" class="form-select">
                <option value="difficulty_index">Индекс сложности</option>
                <option value="discrimination_index">Индекс дискриминативности</option>
                <option value="avg_score">Средний балл</option>
                <option value="total_attempts">Количество попыток</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Оператор</label>
              <select name="operator" class="form-select">
                <option value=">">Больше (&gt;)</option>
                <option value="<">Меньше (&lt;)</option>
                <option value=">=">Больше или равно (&gt;=)</option>
                <option value="<=">Меньше или равно (&lt;=)</option>
                <option value="between">Между</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Значение от</label>
              <input type="number" step="0.01" name="value_from" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Значение до (для между)</label>
              <input type="number" step="0.01" name="value_to" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Категория качества</label>
              <select name="category" class="form-select">
                <option value="too_difficult">Слишком сложный</option>
                <option value="too_easy">Слишком легкий</option>
                <option value="poor_discrimination">Плохая дискриминативность</option>
                <option value="insufficient_attempts">Недостаточно попыток</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Сообщение для пользователя</label>
              <input type="text" name="action_message" class="form-control" placeholder="Например: Вопрос слишком сложный для студентов">
            </div>
            <div class="mb-3">
              <label class="form-label">Порядок выполнения</label>
              <input type="number" min="1" name="rule_order" class="form-control" value="1">
            </div>
            <button type="submit" class="btn btn-primary">Создать правило</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="section-settings" class="ai-section <?= $section === 'settings' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3">Настройки коэффициентов</h3>
      
      <div class="form-grid">
        <div class="form-card">
          <h6 class="fw-semibold mb-3">Глобальные настройки</h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label">Порог сложности (0-1)</label>
              <input type="number" step="0.01" min="0" max="1" name="difficulty_threshold" class="form-control" value="0.5">
              <small class="text-muted">Вопросы с индексом сложности выше этого порога считаются слишком сложными</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Порог дискриминативности (0-1)</label>
              <input type="number" step="0.01" min="0" max="1" name="discrimination_threshold" class="form-control" value="0.3">
              <small class="text-muted">Вопросы с дискриминативностью ниже этого порога считаются некачественными</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Минимальное количество попыток</label>
              <input type="number" min="1" name="min_attempts" class="form-control" value="5">
              <small class="text-muted">Минимальное количество ответов для анализа качества вопроса</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Порог дедупликации (0-1)</label>
              <input type="number" step="0.01" min="0" max="1" name="dedup_threshold" class="form-control" value="0.85">
              <small class="text-muted">Порог сходства для определения дубликатов вопросов</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Вес TF-IDF (0-1)</label>
              <input type="number" step="0.01" min="0" max="1" name="tfidf_weight" class="form-control" value="0.6">
              <small class="text-muted">Вес TF-IDF в гибридном анализе (остальное - эмбеддинги)</small>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
          </form>
        </div>
        
        <div class="form-card">
          <h6 class="fw-semibold mb-3">Настройки по дисциплине</h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label">Дисциплина</label>
              <select name="discipline_id" class="form-select">
                <option value="">Выберите дисциплину</option>
                <option value="1">Педиатрия</option>
                <option value="2">Хирургия</option>
                <option value="3">Терапия</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Коэффициент сложности</label>
              <input type="number" step="0.01" min="0" max="2" name="discipline_difficulty_coeff" class="form-control" value="1.0">
            </div>
            <div class="mb-3">
              <label class="form-label">Коэффициент важности</label>
              <input type="number" step="0.01" min="0" max="2" name="discipline_importance_coeff" class="form-control" value="1.0">
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
          </form>
        </div>
        
        <div class="form-card">
          <h6 class="fw-semibold mb-3">Настройки по специальности</h6>
          <form method="post" class="mb-3">
            <?= csrf_input() ?>
            <div class="mb-3">
              <label class="form-label">Специальность</label>
              <select name="specialty_id" class="form-select">
                <option value="">Выберите специальность</option>
                <option value="1">Лечебное дело</option>
                <option value="2">Педиатрия</option>
                <option value="3">Стоматология</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Базовый порог сложности</label>
              <input type="number" step="0.01" min="0" max="1" name="specialty_difficulty_threshold" class="form-control" value="0.5">
            </div>
            <div class="mb-3">
              <label class="form-label">Коэффициент строгости</label>
              <input type="number" step="0.01" min="0" max="2" name="specialty_strictness_coeff" class="form-control" value="1.0">
            </div>
            <button type="submit" class="btn btn-primary">Сохранить</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="section-containers" class="ai-section <?= $section === 'containers' ? 'active' : '' ?>">
    <div class="table-container">
      <h3 class="h5 mb-3">Container Management</h3>
      <p class="text-muted">Управление контейнерами</p>
    </div>
  </div>
  
</div>

<script src="assets/vendor/js/bootstrap.bundle.min.js"></script>
<script>
function showSection(sectionId) {
  document.querySelectorAll('.ai-section').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.ai-nav-btn').forEach(el => el.classList.remove('active'));
  document.getElementById('section-' + sectionId).classList.add('active');
  event.target.classList.add('active');
  window.location.hash = sectionId;
}

document.addEventListener('DOMContentLoaded', function() {
  let currentSort = { column: null, direction: 'asc' };
  
  document.querySelectorAll('.sortable').forEach(header => {
    header.style.cursor = 'pointer';
    header.addEventListener('click', function(event) {
      event.stopPropagation();
      event.preventDefault();
      
      const table = this.closest('table');
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      const sortType = this.getAttribute('data-sort');
      const columnIndex = Array.from(this.parentNode.children).indexOf(this);
      

      if (currentSort.column === columnIndex) {
        currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
      } else {
        currentSort.column = columnIndex;
        currentSort.direction = 'asc';
      }
      const isAsc = currentSort.direction === 'asc';
      

      this.parentNode.querySelectorAll('.sortable').forEach((h, index) => {
        const baseText = h.textContent.replace(/[⬍⬎]/g, '').trim();
        if (index === columnIndex) {
          h.textContent = baseText + (isAsc ? ' ⬎' : ' ⬍');
        } else {
          h.textContent = baseText + ' ⬍';
        }
      });
      
      rows.sort((a, b) => {
        let aVal = a.children[columnIndex].textContent.trim();
        let bVal = b.children[columnIndex].textContent.trim();
        
        if (sortType === 'number') {
          aVal = parseFloat(aVal) || 0;
          bVal = parseFloat(bVal) || 0;
        }
        
        if (aVal < bVal) return isAsc ? -1 : 1;
        if (aVal > bVal) return isAsc ? 1 : -1;
        return 0;
      });
      
      rows.forEach(row => tbody.appendChild(row));
    });
  });
});


if (window.location.hash) {
  const section = window.location.hash.substring(1);
  const btn = Array.from(document.querySelectorAll('.ai-nav-btn')).find(b => b.textContent.toLowerCase().includes(section));
  if (btn) btn.click();
}
</script>

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
    
    preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/s', $xmlContent, $matches);
    $text = implode(' ', $matches[1]);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    
    return $text;
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
    
    preg_match_all('/<text:p[^>]*>(.*?)<\/text:p>/s', $xmlContent, $matches);
    $text = implode(' ', $matches[1]);
    $text = strip_tags($text);
    
    return trim($text);
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
    $topics = [];
    
    $patterns = [
        '/(\d+[\.\)]\s*)([A-Za-z][^\n\.]{10,100})/u',
        '/(Topic\s*\d*[:\.\s]*)([A-Za-z][^\n\.]{10,100})/ui',
        '/(Section\s*\d*[:\.\s]*)([A-Za-z][^\n\.]{10,100})/ui',
    ];
    
    $foundTopics = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $title = trim($match[2]);
                if (!in_array($title, $foundTopics) && mb_strlen($title) >= 10) {
                    $foundTopics[] = $title;
                }
            }
        }
    }
    
    $index = 1;
    foreach ($foundTopics as $title) {
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

<script>
function toggleAllCheckboxes(checkbox) {
    const checkboxes = document.querySelectorAll('.question-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

function filterQuestions() {
    const searchValue = document.getElementById('questionSearch').value.toLowerCase();
    const tableBody = document.getElementById('questionsTableBody');
    const rows = tableBody.getElementsByTagName('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = rows[i];
        const id = row.getAttribute('data-id');
        const question = row.getAttribute('data-question');
        const discipline = row.getAttribute('data-discipline');
        const topic = row.getAttribute('data-topic');
        
        if (id.includes(searchValue) || 
            question.includes(searchValue) || 
            discipline.includes(searchValue) ||
            topic.includes(searchValue)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const sortableHeaders = document.querySelectorAll('#questionsTable .sortable');
    let sortDirection = {};
    
    sortableHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const sortType = this.getAttribute('data-sort');
            const tableBody = document.getElementById('questionsTableBody');
            const rows = Array.from(tableBody.getElementsByTagName('tr'));
            
            sortDirection[sortType] = sortDirection[sortType] === 'asc' ? 'desc' : 'asc';
            
            sortableHeaders.forEach(h => h.textContent = h.textContent.replace(' ⬍', '').replace(' ▲', '').replace(' ▼', ''));
            this.textContent = this.textContent + (sortDirection[sortType] === 'asc' ? ' ▲' : ' ▼');
            
            rows.sort((a, b) => {
                let aVal, bVal;
                
                switch(sortType) {
                    case 'id':
                        aVal = parseInt(a.getAttribute('data-id'));
                        bVal = parseInt(b.getAttribute('data-id'));
                        break;
                    case 'question':
                        aVal = a.getAttribute('data-question');
                        bVal = b.getAttribute('data-question');
                        break;
                    case 'discipline':
                        aVal = a.getAttribute('data-discipline');
                        bVal = b.getAttribute('data-discipline');
                        break;
                    case 'topic':
                        aVal = a.getAttribute('data-topic');
                        bVal = b.getAttribute('data-topic');
                        break;
                }
                
                if (sortDirection[sortType] === 'asc') {
                    return aVal > bVal ? 1 : -1;
                } else {
                    return aVal < bVal ? 1 : -1;
                }
            });
            
            rows.forEach(row => tableBody.appendChild(row));
        });
    });
});
</script>

<?php render_footer(); ?>
