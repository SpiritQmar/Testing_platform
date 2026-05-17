<?php
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/env_loader.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['error' => 'POST only']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string)$token)) {
    ob_end_clean();
    echo json_encode(['error' => 'CSRF mismatch — обновите страницу и попробуйте снова']);
    exit;
}

try {
    $configPath = realpath(__DIR__ . '/../config.php');
    if (!$configPath || !is_readable($configPath)) {
        throw new Exception('config.php не найден: ' . __DIR__ . '/../config.php');
    }
    if (!is_writable($configPath)) {
        throw new Exception('config.php не доступен для записи. Проверьте права доступа к файлу.');
    }

    $cfg = (static function(string $p): array {
        $r = require $p;
        return is_array($r) ? $r : [];
    })($configPath);

    if (!is_array($cfg) || !isset($cfg['coefficients'])) {
        throw new Exception('Неверный формат config.php — нет ключа coefficients');
    }

    $posted = $_POST['coefficients'] ?? [];
    if (!is_array($posted)) {
        throw new Exception('Нет данных для сохранения');
    }

    $saved = [];
    foreach ($posted as $category => $values) {
        if (!is_array($values) || !isset($cfg['coefficients'][$category])) continue;
        foreach ($values as $key => $val) {
            if (!array_key_exists($key, $cfg['coefficients'][$category])) continue;
            $orig = $cfg['coefficients'][$category][$key];
            $isInt = is_int($orig) || (is_float($orig) && $orig == (int)$orig && strpos((string)$val, '.') === false);
            $cfg['coefficients'][$category][$key] = $isInt ? (int)$val : (float)$val;
            $saved[] = "{$category}.{$key} = " . $cfg['coefficients'][$category][$key];
        }
    }

    if (empty($saved)) {
        throw new Exception('Ни одно поле не совпало с конфигурацией. POST: ' . json_encode(array_keys($posted)));
    }

    $content = "<?php\nreturn " . var_export($cfg, true) . ";\n";
    if (file_put_contents($configPath, $content) === false) {
        throw new Exception('file_put_contents вернул false для: ' . $configPath);
    }

    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($configPath, true);
    }

    if (isset($cfg['coefficients']['discrimination']['min_students'])) {
        $_SESSION['min_students_discrimination'] = (int)$cfg['coefficients']['discrimination']['min_students'];
        foreach (array_keys($_SESSION) as $k) {
            if (strpos($k, 'discrimination_index_') === 0) unset($_SESSION[$k]);
        }
    }

    ob_end_clean();
    echo json_encode(['success' => true, 'saved' => $saved, 'coefficients' => $cfg['coefficients']]);

} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
