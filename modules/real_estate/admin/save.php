<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 2) . '/src/Domain/Entity/Property.php';
require_once dirname(__DIR__, 2) . '/src/Infrastructure/Persistence/PropertyRepository.php';

use PartoCMS\RealEstate\Domain\Entity\Property;
use PartoCMS\RealEstate\Infrastructure\Persistence\PropertyRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$pdo = getDB();
$repo = new PropertyRepository($pdo);

$id = (int)($_POST['id'] ?? 0);
$property = $id ? $repo->findById($id) : new Property();

if (!$property) {
    die('ملک یافت نشد.');
}

// تنظیم فیلدها
$property->setTitle(trim($_POST['title'] ?? ''));
$property->setSlug(trim($_POST['slug'] ?? '') ?: $this->createSlug($_POST['title'] ?? ''));
$property->setDescription($_POST['description'] ?? null);
$property->setPropertyType($_POST['property_type'] ?? 'apartment');
$property->setListingType($_POST['listing_type'] ?? 'sale');
$property->setPrice(!empty($_POST['price']) ? (float)$_POST['price'] : null);
$property->setRentPrice(!empty($_POST['rent_price']) ? (float)$_POST['rent_price'] : null);
$property->setDepositPrice(!empty($_POST['deposit_price']) ? (float)$_POST['deposit_price'] : null);
$property->setArea(!empty($_POST['area']) ? (int)$_POST['area'] : null);
$property->setRooms(!empty($_POST['rooms']) ? (int)$_POST['rooms'] : null);
$property->setBathrooms(!empty($_POST['bathrooms']) ? (int)$_POST['bathrooms'] : null);
$property->setYearBuilt(!empty($_POST['year_built']) ? (int)$_POST['year_built'] : null);
$property->setAddress(trim($_POST['address'] ?? ''));
$property->setCity(trim($_POST['city'] ?? ''));
$property->setRegion(trim($_POST['region'] ?? ''));
$property->setLatitude(!empty($_POST['latitude']) ? (float)$_POST['latitude'] : null);
$property->setLongitude(!empty($_POST['longitude']) ? (float)$_POST['longitude'] : null);
$property->setFeaturedImage(trim($_POST['featured_image'] ?? ''));
$property->setAgentName(trim($_POST['agent_name'] ?? ''));
$property->setAgentPhone(trim($_POST['agent_phone'] ?? ''));
$property->setAgentEmail(trim($_POST['agent_email'] ?? ''));
$property->setStatus($_POST['status'] ?? 'published');
$property->setIsFeatured(isset($_POST['is_featured']));
$property->setSortOrder((int)($_POST['sort_order'] ?? 0));

// تابع ساخت اسلاگ
function createSlug(string $title): string {
    $slug = strtolower(trim(preg_replace('/[^a-z0-9-]+/', '-', str_replace(' ', '-', $title))));
    return trim($slug, '-') ?: 'property-' . time();
}

if ($repo->save($property)) {
    header('Location: index.php?msg=saved');
} else {
    die('خطا در ذخیره‌سازی.');
}
