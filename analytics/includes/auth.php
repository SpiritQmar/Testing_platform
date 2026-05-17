<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db.php';

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function flash_set(string $type, string $text): void {
    $_SESSION['_flash'] = ['type' => $type, 'text' => $text];
}

function flash_get(): ?array {
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_input(): string {
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf_or_fail(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
        http_response_code(419);
        exit('CSRF token mismatch');
    }
}

function get_lang(): string {
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'kz', 'en'], true)) {
        $_SESSION['lang'] = $_GET['lang'];
    }
    return $_SESSION['lang'] ?? 'ru';
}

function t(string $key): string {
    static $fallback = [
        'ru' => [
            'app_name' => 'Exam Quality Analyzer',
            'app_subtitle' => 'Аналитика качества экзаменационных вопросов',
            'nav_dashboard' => 'Панель управления',
            'nav_questions' => 'Вопросы',
            'nav_import' => 'Импорт данных',
            'nav_users' => 'Пользователи',
            'nav_ai' => 'Аналитика',
        ],
        'kz' => [
            'app_name' => 'Exam Quality Analyzer',
            'app_subtitle' => 'Емтихан сұрақтарының сапа аналитикасы',
            'nav_dashboard' => 'Басқару тақтасы',
            'nav_questions' => 'Сұрақтар',
            'nav_import' => 'Импорт',
            'nav_users' => 'Пайдаланушылар',
            'nav_ai' => 'ИИ талдау',
        ],
        'en' => [
            'app_name' => 'Exam Quality Analyzer',
            'app_subtitle' => 'Exam question quality analytics',
            'nav_dashboard' => 'Dashboard',
            'nav_questions' => 'Questions',
            'nav_import' => 'Import',
            'nav_users' => 'Users',
            'nav_ai' => 'Analytics',
        ],
    ];
    $lang = get_lang();

    global $translations;
    if (isset($translations[$key])) {
        return $translations[$key];
    }

    try {
        global $pdo;
        if ($pdo) {
            $col = $lang === 'kz' ? 'text_kz' : ($lang === 'en' ? 'text_en' : 'text_ru');
            $st = $pdo->prepare("SELECT {$col} AS txt FROM ui_texts WHERE ui_key = :k LIMIT 1");
            $st->execute([':k' => $key]);
            $row = $st->fetch();
            if ($row && !empty($row['txt'])) {
                return (string)$row['txt'];
            }
        }
    } catch (Throwable $e) {
    }
    return $fallback[$lang][$key] ?? $key;
}

function get_help(string $key): ?array {
    global $pdo;
    $lang = get_lang();
    $tcol = $lang === 'kz' ? 'title_kz' : ($lang === 'en' ? 'title_en' : 'title_ru');
    $bcol = $lang === 'kz' ? 'body_kz' : ($lang === 'en' ? 'body_en' : 'body_ru');
    try {
        $stmt = $pdo->prepare("SELECT {$tcol} AS title, {$bcol} AS body FROM ui_help WHERE help_key = :k LIMIT 1");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (Throwable $e) {
        return null;
    }
}
