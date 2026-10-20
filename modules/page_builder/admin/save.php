<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/src/Infrastructure/PageRepository.php';

use PartoCMS\PageBuilder\Infrastructure\PageRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$repo = new PageRepository(getDB());
$data = [
    'id' => (int)($_POST['id'] ?? 0),
    'title' => trim($_POST['title'] ?? ''),
    'slug' => trim($_POST['slug'] ?? ''),
    'layout_json' => $_POST['layout_json'] ?? '[]',
    'status' => in_array($_POST['status'], ['published', 'draft']) ? $_POST['status'] : 'draft'
];

if ($data['title'] && $data['slug']) {
    try {
        $repo->save($data);
        header('Location: index.php?msg=saved');
        exit;
    } catch (Exception $e) {
        echo "<h3>خطا در ذخیره‌سازی:</h3><p style='color:red;'>" . htmlspecialchars($e->getMessage()) . "</p><a href='javascript:history.back()'>بازگشت</a>";
        exit;
    }
}
