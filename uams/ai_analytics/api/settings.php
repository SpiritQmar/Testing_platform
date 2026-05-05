<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Данный эндпоинт поддерживает только POST-запросы для обновления настроек');
}

verify_csrf_or_fail();

try {
    $applicationConfigPath = __DIR__ . '/../config.php';
    $currentConfiguration = require $applicationConfigPath;

    if (isset($_POST['coefficients']) && is_array($_POST['coefficients'])) {
        foreach ($_POST['coefficients'] as $analysisCategory => $coefficientValues) {
            if (isset($currentConfiguration['coefficients'][$analysisCategory]) && is_array($coefficientValues)) {
                foreach ($coefficientValues as $coefficientKey => $coefficientValue) {
                    if (isset($currentConfiguration['coefficients'][$analysisCategory][$coefficientKey])) {
                        $currentConfiguration['coefficients'][$analysisCategory][$coefficientKey] = (float)$coefficientValue;
                    }
                }
            }
        }
    }

    $updatedConfigContent = "<?php\nreturn " . var_export($currentConfiguration, true) . ";\n";
    if (file_put_contents($applicationConfigPath, $updatedConfigContent) === false) {
        throw new Exception('Не удалось сохранить обновленные коэффициенты в файл конфигурации. Проверьте права доступа к директории.');
    }

    flash_set('success', 'Коэффициенты анализа успешно обновлены и сохранены');

} catch (Throwable $exception) {
    error_log('Ошибка при обновлении настроек приложения: ' . $exception->getMessage());
    flash_set('danger', 'Не удалось сохранить настройки: ' . $exception->getMessage());
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php?section=settings'));
exit;
