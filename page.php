<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/page_builder/src/Infrastructure/PageRepository.php';
require_once __DIR__ . '/modules/page_builder/src/Widget/PageWidget.php';

use PartoCMS\PageBuilder\Infrastructure\PageRepository;
use PartoCMS\PageBuilder\Widget\PageWidget;

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header('Location: index.php');
    exit;
}

$repo = new PageRepository(getDB());
$page = $repo->getBySlug($slug);

if (!$page) {
    http_response_code(404);
    die('<div style="font-family:tahoma; text-align:center; margin-top:50px;">صفحه مورد نظر یافت نشد.</div>');
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['title']) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f8f9fa; margin: 0; }
        .top-bar { background: #2c3e50; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar a { color: #fff; text-decoration: none; font-size: 14px; }
        .page-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 50px 20px; text-align: center; margin-bottom: 40px; }
        .page-header h1 { margin: 0; font-size: 32px; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" class="fw-bold">🏠 <?= htmlspecialchars($siteName) ?></a>
    <div>
        <a href="index.php">صفحه اصلی</a>
    </div>
</div>

<div class="page-header">
    <h1><?= htmlspecialchars($page['title']) ?></h1>
</div>

<div class="page-content">
    <?= PageWidget::render($page) ?>
</div>

<footer class="text-center py-4 bg-dark text-white mt-5">
    <p class="m-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - تمامی حقوق محفوظ است.</p>
</footer>

</body>
</html>
