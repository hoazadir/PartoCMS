<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/modules/properties/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: properties.php'); exit; }

$service  = properties_service();
$property = $service->getById($id);

if (!$property || $property->getStatus() !== 'published') {
    http_response_code(404);
    die('ملک یافت نشد.');
}

$siteName = getSetting('site_name', 'وب‌سایت من');
$gallery  = $property->getGallery();

// قیمت
$priceDisplay = 'تماس بگیرید';
if ($property->getDealType() === 'sale' && $property->getPrice()) {
    $priceDisplay = number_format((float)$property->getPrice()) . ' تومان';
} elseif ($property->getRentPrice() || $property->getDeposit()) {
    $parts = [];
    if ($property->getDeposit()) $parts[] = 'رهن: ' . number_format((float)$property->getDeposit());
    if ($property->getRentPrice()) $parts[] = 'اجاره: ' . number_format((float)$property->getRentPrice());
    $priceDisplay = implode(' | ', $parts);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($property->getTitle()) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/modules/properties/assets/css/properties.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; }
        .gallery-main { width: 100%; height: 450px; object-fit: cover; border-radius: 12px; cursor: pointer; }
        .gallery-thumb { width: 100%; height: 80px; object-fit: cover; border-radius: 6px; cursor: pointer; opacity: 0.6; transition: 0.2s; }
        .gallery-thumb:hover, .gallery-thumb.active { opacity: 1; border: 2px solid #3b82f6; }
        .spec-item { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8fafc; border-radius: 8px; }
        .price-box { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; border-radius: 12px; padding: 20px; text-align: center; }
    </style>
    <style>
        .agent-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            border: 1px solid #eef0f2;
        }
        .agent-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px 20px;
            text-align: center;
            color: #fff;
            position: relative;
        }
        .agent-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #fff;
            object-fit: cover;
            margin: 0 auto 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }
        .agent-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .agent-agency {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .badge-verified {
            background: #10b981;
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .agent-card-body {
            padding: 20px;
        }
        .agent-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eef0f2;
            font-size: 14px;
        }
        .agent-info-row:last-child { border-bottom: none; }
        .agent-info-label { color: #64748b; }
        .agent-info-value { font-weight: 600; color: #1e293b; }
        .agent-contact-btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: #0d6efd;
            color: #fff;
            text-align: center;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            margin-top: 16px;
            transition: 0.2s;
        }
        .agent-contact-btn:hover { background: #0b5ed7; color: #fff; }
        .agent-contact-btn.disabled {
            background: #e2e8f0;
            color: #64748b;
            cursor: not-allowed;
        }
        .locked-overlay {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            margin-top: 16px;
        }
        .locked-overlay p { margin: 0 0 12px; font-size: 13px; color: #64748b; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark py-3 mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">🏠 <?= htmlspecialchars($siteName) ?></a>
        <a href="properties.php" class="btn btn-outline-light btn-sm">لیست املاک</a>
    </div>
</nav>

<div class="container mb-5">
    <div class="mb-4">
        <span class="badge <?= $property->getDealType() === 'sale' ? 'bg-success' : 'bg-info' ?> fs-6">
            <?= htmlspecialchars($property->getDealTypeLabel()) ?>
        </span>
        <span class="badge bg-secondary fs-6"><?= htmlspecialchars($property->getPropertyTypeLabel()) ?></span>
        <h1 class="fw-bold mt-3"><?= htmlspecialchars($property->getTitle()) ?></h1>
        <p class="text-muted">
            <i class="bi bi-geo-alt"></i>
            <?= htmlspecialchars($property->getCity() ?? '—') ?>
            <?php if ($property->getRegion()): ?> - <?= htmlspecialchars($property->getRegion()) ?><?php endif; ?>
        </p>
    </div>

    <div class="row g-4">
        <div class="col-md-8">
            <?php if ($property->getThumbnailUrl()): ?>
                <img src="<?= htmlspecialchars($property->getThumbnailUrl()) ?>" id="mainImage" class="gallery-main mb-3" alt="<?= htmlspecialchars($property->getTitle()) ?>">
            <?php else: ?>
                <div class="gallery-main bg-light d-flex align-items-center justify-content-center mb-3">
                    <span style="font-size:80px;">🏠</span>
                </div>
            <?php endif; ?>

            <?php if (!empty($gallery)): ?>
                <div class="row g-2 mb-4">
                    <?php foreach ($gallery as $i => $img): ?>
                        <div class="col-3">
                            <img src="<?= htmlspecialchars($img) ?>" class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" onclick="changeImage('<?= htmlspecialchars($img) ?>', this)" alt="گالری <?= $i + 1 ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">📝 توضیحات ملک</div>
                <div class="card-body">
                    <?= $property->getDescription() ?: '<p class="text-muted">توضیحاتی ثبت نشده است.</p>' ?>
                </div>
            </div>

            <?php if ($property->getVideoUrl()): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">🎬 ویدیو معرفی</div>
                <div class="card-body">
                    <?php
                        $videoUrl = $property->getVideoUrl();
                        $videoId = '';
                        $embedType = '';
                        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $videoUrl, $m)) {
                            $videoId = $m[1];
                            $embedType = 'youtube';
                        } elseif (preg_match('/aparat\.com\/v\/([a-zA-Z0-9]+)/', $videoUrl, $m)) {
                            $videoId = $m[1];
                            $embedType = 'aparat';
                        }
                    ?>
                    <?php if ($embedType === 'youtube'): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.youtube.com/embed/<?= $videoId ?>" allowfullscreen></iframe>
                        </div>
                    <?php elseif ($embedType === 'aparat'): ?>
                        <div class="ratio ratio-16x9">
                            <iframe src="https://www.aparat.com/video/video/embed/videohash/<?= $videoId ?>/vt/frame" allowfullscreen></iframe>
                        </div>
                    <?php else: ?>
                        <video controls class="w-100" style="border-radius:8px;">
                            <source src="<?= htmlspecialchars($videoUrl) ?>" type="video/mp4">
                            مرورگر شما از ویدیو پشتیبانی نمی‌کند.
                        </video>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($property->getContent()): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">📋 جزئیات بیشتر</div>
                <div class="card-body"><?= $property->getContent() ?></div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white fw-bold">📐 مشخصات ملک</div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php if ($property->getArea()): ?>
                            <div class="col-md-4"><div class="spec-item">📐 <strong><?= $property->getArea() ?></strong> متر</div></div>
                        <?php endif; ?>
                        <?php if ($property->getBedrooms()): ?>
                            <div class="col-md-4"><div class="spec-item">🛏 <strong><?= $property->getBedrooms() ?></strong> خواب</div></div>
                        <?php endif; ?>
                        <?php if ($property->getBathrooms()): ?>
                            <div class="col-md-4"><div class="spec-item">🚿 <strong><?= $property->getBathrooms() ?></strong> سرویس</div></div>
                        <?php endif; ?>
                        <?php if ($property->getFloor() !== null): ?>
                            <div class="col-md-4"><div class="spec-item">🏢 طبقه: <strong><?= $property->getFloor() ?></strong></div></div>
                        <?php endif; ?>
                        <?php if ($property->getTotalFloors()): ?>
                            <div class="col-md-4"><div class="spec-item">🏬 کل طبقات: <strong><?= $property->getTotalFloors() ?></strong></div></div>
                        <?php endif; ?>
                        <?php if ($property->getYearBuilt()): ?>
                            <div class="col-md-4"><div class="spec-item">📅 سال ساخت: <strong><?= $property->getYearBuilt() ?></strong></div></div>
                        <?php endif; ?>
                        <?php if ($property->getDocumentType()): ?>
                            <div class="col-md-4"><div class="spec-item">📄 سند: <strong><?= htmlspecialchars($property->getDocumentType()) ?></strong></div></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($property->getAmenities())): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white fw-bold">✨ امکانات</div>
                <div class="card-body">
                    <?php foreach ($property->getAmenities() as $a): ?>
                        <span class="badge bg-light text-dark border me-1 mb-1"><?= htmlspecialchars($a) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <div class="price-box mb-4">
                <p class="mb-1" style="opacity:0.8;">قیمت</p>
                <h3 class="fw-bold mb-0"><?= htmlspecialchars($priceDisplay) ?></h3>
            </div>

            <?php
            // ─── دریافت اطلاعات مشاور ───
            $agentData = null;
            if ($property->getAgentId()) {
                $pdo = getDB();
                $stmt = $pdo->prepare("
                    SELECT ap.*, u.username, u.email, u.phone AS user_phone
                    FROM agent_profiles ap
                    INNER JOIN users u ON u.id = ap.user_id
                    WHERE ap.user_id = ? AND ap.status = 'approved'
                ");
                $stmt->execute([$property->getAgentId()]);
                $agentData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            ?>
            <?php if ($agentData): ?>
            <div class="agent-card mb-4">
                <div class="agent-card-header">
                    <?php if (!empty($agentData['avatar'])): ?>
                        <img src="<?= htmlspecialchars($agentData['avatar']) ?>" class="agent-avatar" alt="<?= htmlspecialchars($agentData['first_name'] . ' ' . $agentData['last_name']) ?>">
                    <?php else: ?>
                        <div class="agent-avatar bg-light d-flex align-items-center justify-content-center" style="font-size:40px;">👤</div>
                    <?php endif; ?>
                    <div class="agent-name"><?= htmlspecialchars($agentData['first_name'] . ' ' . $agentData['last_name']) ?></div>
                    <div class="agent-agency">🏢 <?= htmlspecialchars($agentData['agency_name']) ?></div>
                    <span class="badge-verified">✅ تأیید شده</span>
                </div>
                <div class="agent-card-body">
                    <div class="agent-info-row">
                        <span class="agent-info-label">شماره پروانه کسب</span>
                        <span class="agent-info-value"><?= htmlspecialchars($agentData['agency_license_no']) ?></span>
                    </div>
                    <div class="agent-info-row">
                        <span class="agent-info-label">مدیر بنگاه</span>
                        <span class="agent-info-value"><?= htmlspecialchars($agentData['agency_manager'] ?? '—') ?></span>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                        <div class="agent-info-row">
                            <span class="agent-info-label">شماره تماس</span>
                            <span class="agent-info-value" dir="ltr"><?= htmlspecialchars($agentData['phone']) ?></span>
                        </div>
                        <a href="tel:<?= htmlspecialchars($agentData['phone']) ?>" class="agent-contact-btn">📞 تماس با مشاور</a>
                    <?php else: ?>
                        <div class="locked-overlay">
                            <p>🔒 برای مشاهده شماره تماس و درخواست بازدید، ابتدا ثبت‌نام کنید.</p>
                            <a href="login.php" class="btn btn-primary btn-sm w-100">ورود / ثبت‌نام</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white fw-bold">📍 موقعیت</div>
                <div class="card-body">
                    <p class="mb-1"><?= htmlspecialchars($property->getAddress() ?? '—') ?></p>
                    <?php if ($property->getLatitude() && $property->getLongitude()): ?>
                        <div class="mt-3" style="border-radius:8px; overflow:hidden;">
                            <iframe width="100%" height="220" frameborder="0" style="border:0"
                                src="https://www.openstreetmap.org/export/embed.html?bbox=<?= ($property->getLongitude() - 0.01) ?>%2C<?= ($property->getLatitude() - 0.01) ?>%2C<?= ($property->getLongitude() + 0.01) ?>%2C<?= ($property->getLatitude() + 0.01) ?>&layer=mapnik&marker=<?= $property->getLatitude() ?>%2C<?= $property->getLongitude() ?>">
                            </iframe>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <a href="properties.php" class="btn btn-outline-secondary w-100">← بازگشت به لیست املاک</a>
        </div>
    </div>
</div>

<footer class="bg-dark text-white text-center py-4 mt-5">
    <p class="mb-0">&copy; <?= date('Y') ?> <?= htmlspecialchars($siteName) ?> - تمامی حقوق محفوظ است.</p>
</footer>

<script>
    function changeImage(src, el) {
        document.getElementById('mainImage').src = src;
        document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
    }
</script>

</body>
</html>
