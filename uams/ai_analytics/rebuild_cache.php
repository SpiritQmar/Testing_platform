<?php
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/services/AIAnalyticsService.php';

require_login();

$importId = isset($_GET['import_id']) ? (int)$_GET['import_id'] : null;
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $importId) {
    try {
        $ai = new AIAnalyticsService(db(), $importId);
        $success = $ai->rebuildAnalysisCache();

        if ($success) {
            $message = 'Кэш валидации и семантического анализа успешно пересоздан для импорта #' . $importId;
        } else {
            $error = 'Ошибка при пересоздании кэша';
        }
    } catch (Throwable $e) {
        $error = 'Ошибка: ' . $e->getMessage();
    }
}

$imports = rows("SELECT import_id, source_filename, rows_imported, created_at FROM imports_log ORDER BY import_id DESC LIMIT 20");

page_header('Пересоздание кэша анализа', 'rebuild_cache.php');
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-4">Пересоздание кэша анализа</h1>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= h($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Выберите импорт для пересоздания кэша</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                Эта операция пересоздаст кэш валидации и семантического анализа для выбранного импорта.
                Анализ будет выполнен для всех вопросов, привязанных к силлабусу.
            </p>

            <div class="alert alert-info">
                <strong>Важно:</strong> Убедитесь, что embeddings API запущен на <code>http://127.0.0.1:8000</code>
                <br>Команда для запуска: <code>python -m uvicorn embeddings_api:app --host 127.0.0.1 --port 8000</code>
            </div>

            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Файл</th>
                        <th>Записей</th>
                        <th>Дата</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($imports as $import): ?>
                        <tr>
                            <td><?= h($import['import_id']) ?></td>
                            <td><?= h($import['source_filename']) ?></td>
                            <td><?= h($import['rows_imported']) ?></td>
                            <td><?= h($import['created_at']) ?></td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="import_id" value="<?= h($import['import_id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-primary"
                                            onclick="return confirm('Пересоздать кэш для импорта #<?= h($import['import_id']) ?>? Это может занять несколько минут.')">
                                        Пересоздать кэш
                                    </button>
                                </form>
                                <a href="index.php?import_id=<?= h($import['import_id']) ?>" class="btn btn-sm btn-outline-secondary">
                                    Просмотр
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        <a href="index.php" class="btn btn-secondary">← Назад к аналитике</a>
    </div>
</div>

<?php page_footer(); ?>
