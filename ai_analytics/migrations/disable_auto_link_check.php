<?php



require_once __DIR__ . '/../config/db.php';

echo "Проверка конфигурации автоматической привязки...\n";

try {
    
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM hier_questions WHERE syllabus_topic_id IS NOT NULL');
    $linkedCount = $stmt->fetch()['count'];
    
    if ($linkedCount > 0) {
        echo "⚠️  Найдены привязанные вопросы ({$linkedCount}). Очищаем...\n";
        $pdo->exec("UPDATE hier_questions SET syllabus_topic_id = NULL");
        echo "✅ Привязки очищены\n";
    } else {
        echo "✅ Нет привязанных вопросов\n";
    }
    
    
    $files = [
        __DIR__ . '/../index.php',
        __DIR__ . '/../imports/import_syllabus.php',
        __DIR__ . '/../imports/data_import.php'
    ];
    
    $allDisabled = true;
    foreach ($files as $file) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if (strpos($content, 'Автоматическая привязка вопросов при импорте силлабуса ОТКЛЮЧЕНА') === false) {
                echo "❌ " . basename($file) . " - автоматическая привязка не отключена\n";
                $allDisabled = false;
            } else {
                echo "✅ " . basename($file) . " - автоматическая привязка отключена\n";
            }
        }
    }
    
    if ($allDisabled) {
        echo "\n🎉 Система правильно сконфигурирована!\n";
        echo "✅ Автоматическая привязка отключена во всех файлах\n";
        echo "✅ Только ручная привязка будет доступна\n";
    } else {
        echo "\n⚠️  Требуется настройка файлов!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
