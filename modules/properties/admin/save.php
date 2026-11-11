<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/bootstrap.php';

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$pdo = getDB();
$service = properties_service();

$id = (int)($_POST['id'] ?? 0);

// تابع تولید slug یکتا
function generate_unique_slug(string $title, ?string $slug, PDO $pdo, int $excludeId = 0): string {
    $base = $slug ?: $title;
    // تبدیل به slug
    $base = trim(preg_replace('/[^a-z0-9-\x{0600}-\x{06FF}]+/u', '-', str_replace(' ', '-', $base)));
    $base = trim($base, '-');
    if ($base === '') $base = 'property';
    
    $slug = $base;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT COUNT(*) FROM properties WHERE slug = ?";
        $params = [$slug];
        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() == 0) {
            return $slug;
        }
        $slug = $base . '-' . $counter;
        $counter++;
    }
}



// تابع کمکی برای دریافت مقدار امن
function post_val(string $key, $default = null) {
    return isset($_POST[$key]) && $_POST[$key] !== '' ? $_POST[$key] : $default;
}

function post_int(string $key): ?int {
    $val = post_val($key);
    return $val !== null ? (int)$val : null;
}

function post_float(string $key): ?float {
    $val = post_val($key);
    return $val !== null ? (float)$val : null;
}

// جمع‌آوری داده‌ها
$data = [
    'title'         => trim(post_val('title', '')),
    'slug'          => generate_unique_slug(post_val('title', ''), post_val('slug'), $pdo, $id),
    'description'   => post_val('description'),
    'content'       => post_val('content'),
    'property_type' => post_val('property_type', 'apartment'),
    'deal_type'     => post_val('deal_type', 'sale'),
    'area'          => post_int('area'),
    'bedrooms'      => post_int('bedrooms'),
    'bathrooms'     => post_int('bathrooms'),
    'floor'         => post_int('floor'),
    'total_floors'  => post_int('total_floors'),
    'year_built'    => post_int('year_built'),
    'document_type' => post_val('document_type'),
    'price'         => post_float('price'),
    'rent_price'    => post_float('rent_price'),
    'deposit'       => post_float('deposit'),
    'address'       => post_val('address'),
    'city'          => post_val('city'),
    'region'        => post_val('region'),
    'latitude'      => post_float('latitude'),
    'longitude'     => post_float('longitude'),
    'thumbnail_url' => post_val('thumbnail_url'),
    'gallery'       => !empty($_POST['gallery']) ? array_filter(array_map('trim', explode("\n", $_POST['gallery']))) : [],
    'amenities'     => $_POST['amenities'] ?? [],
    'agent_name'    => post_val('agent_name'),
    'agent_phone'   => post_val('agent_phone'),
    'agent_email'   => post_val('agent_email'),
    'status'        => post_val('status', 'pending'),
    'is_featured'   => isset($_POST['is_featured']) ? 1 : 0,
    'sort_order'    => (int)post_val('sort_order', 0),
    'language'      => post_val('language', 'fa-IR'),
];

try {
    if ($id) {
        $service->updateByAdmin($id, $data);
        header('Location: index.php?msg=saved');
    } else {
        $service->createByAdmin($data, true);
        header('Location: index.php?msg=saved');
    }
    exit;
} catch (Exception $e) {
    echo "🔴 خطای اصلی: " . htmlspecialchars($e->getMessage()) . "<br>";
    $prev = $e->getPrevious();
    if ($prev) {
        echo "🔴 خطای PDO: " . htmlspecialchars($prev->getMessage()) . "<br>";
        if ($prev instanceof PDOException) {
            echo "<pre>" . print_r($prev->errorInfo, true) . "</pre>";
        }
    }
    echo '<br><a href="javascript:history.back()">بازگشت</a>';
    exit;
}
