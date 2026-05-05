<?php
require_once __DIR__.'/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (login_attempt($data['login'] ?? '', $data['password'] ?? '')) {
        echo json_encode(['success' => true, 'user' => user()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid credentials']);
    }
    exit;
}

if (user()) {
    header('Location: dashboard.php');
    exit;
}

$cssFiles = glob(__DIR__ . '/dist/assets/*.css');
$jsFiles = glob(__DIR__ . '/dist/assets/*.js');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UAMS - Login</title>
    <?php foreach($cssFiles as $css): ?>
    <link rel="stylesheet" href="dist/assets/<?php echo basename($css); ?>">
    <?php endforeach; ?>
</head>
<body>
    <div id="root"></div>
    <script>
        window.USER_DATA = null;
    </script>
    <?php foreach($jsFiles as $js): ?>
    <script type="module" src="dist/assets/<?php echo basename($js); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
