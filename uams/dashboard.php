<?php
require_once __DIR__.'/includes/db.php';
require_login();

$cssFiles = glob(__DIR__ . '/dist/assets/*.css');
$jsFiles = glob(__DIR__ . '/dist/assets/*.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UAMS - Dashboard</title>
    <?php foreach($cssFiles as $css): ?>
    <link rel="stylesheet" href="dist/assets/<?php echo basename($css); ?>">
    <?php endforeach; ?>
</head>
<body>
    <div id="root"></div>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        window.USER_DATA = <?php echo json_encode(user()); ?>;
    </script>
    <?php foreach($jsFiles as $js): ?>
    <script type="module" src="dist/assets/<?php echo basename($js); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
