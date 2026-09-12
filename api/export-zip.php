<?php
// api/export-zip.php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('دسترسی غیرمجاز');
}

$templateId = $_GET['id'] ?? null;
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
$stmt->execute([$templateId]);
$template = $stmt->fetch();

if (!$template) {
    http_response_code(404);
    exit('قالب یافت نشد');
}

$html = $template['content'] ?? '';
$css = $template['css'] ?? '';
$name = preg_replace('/[^a-zA-Z0-9-_]/', '_', $template['name']);

// ایجاد فایل ZIP
$zipName = $name . '_' . date('Ymd_His') . '.zip';
$zipFile = tempnam(sys_get_temp_dir(), 'zip');
$zip = new ZipArchive();

if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('خطا در ایجاد فایل ZIP');
}

// ساختار فایل‌ها
$indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$template['name']}</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
{$html}
<script src="js/script.js"><\/script>
</body>
</html>
HTML;

$scriptJs = <<<JS
// کدهای جاوااسکریپت قالب
// این فایل را می‌توانید ویرایش کنید
console.log('قالب {$template['name']} بارگذاری شد');
JS;

$zip->addFromString('index.html', $indexHtml);
$zip->addFromString('css/style.css', $css);
$zip->addFromString('js/script.js', $scriptJs);
$zip->addFromString('README.txt', "قالب: {$template['name']}\nتاریخ: " . date('Y/m/d H:i:s') . "\nساخته شده با GrapesJS CMS");

$zip->close();

// ارسال فایل
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipFile));
readfile($zipFile);
unlink($zipFile);
exit;
