<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $repo = new PropertyRepository(getDB());
    $repo->delete($id);
}

header('Location: index.php?msg=deleted');
exit;
