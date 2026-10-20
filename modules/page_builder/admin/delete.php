<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/src/Infrastructure/PageRepository.php';

use PartoCMS\PageBuilder\Infrastructure\PageRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $repo = new PageRepository(getDB());
    $repo->delete($id);
}
header('Location: index.php');
exit;
