<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// شبیه‌سازی محیط admin/index.php
$_SERVER['PHP_SELF'] = '/admin/index.php';
$_SERVER['SCRIPT_NAME'] = '/admin/index.php';

// رندر sidebar
ob_start();
require __DIR__ . '/includes/sidebar.php';
$sidebarHtml = ob_get_clean();

// چک کن آیا ماژول‌های داینامیک در HTML هستن
$hasI18n = strpos($sidebarHtml, 'languages.php') !== false;
$hasTableBuilder = strpos($sidebarHtml, 'table_builder') !== false;
$hasModuleRegistry = strpos($sidebarHtml, 'menu-group-items') !== false;

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sidebar Render Test</title>
    <style>body { font-family: Tahoma, sans-serif; padding: 20px; }</style>
</head>
<body>
    <h2>🧪 Sidebar Render Test</h2>
    <table border="1" cellpadding="10" style="border-collapse:collapse;margin-bottom:20px">
        <tr>
            <td><strong>طول HTML:</strong></td>
            <td><?= number_format(strlen($sidebarHtml)) ?> بایت</td>
        </tr>
        <tr>
            <td><strong>شامل languages.php:</strong></td>
            <td><?= $hasI18n ? '✅ بله' : '❌ نه' ?></td>
        </tr>
        <tr>
            <td><strong>شامل table_builder:</strong></td>
            <td><?= $hasTableBuilder ? '✅ بله' : '❌ نه' ?></td>
        </tr>
        <tr>
            <td><strong>شامل menu-group-items:</strong></td>
            <td><?= $hasModuleRegistry ? '✅ بله' : '❌ نه' ?></td>
        </tr>
    </table>

    <h3>📋 لیست لینک‌های sidebar:</h3>
    <ul style="direction:rtl;text-align:right">
    <?php
    preg_match_all('/<a href="([^"]+)"[^>]*>.*?<span[^>]*>([^<]+)<\/span>/s', $sidebarHtml, $matches);
    for ($i = 0; $i < min(count($matches[1]), 40); $i++) {
        echo '<li><code>' . htmlspecialchars($matches[1][$i]) . '</code> — ' . htmlspecialchars(trim($matches[2][$i])) . '</li>';
    }
    ?>
    </ul>
</body>
</html>
