<?php
/**
 * PartoCMS - Ads Admin - Add/Edit v4.0
 * فرم حرفه‌ای افزودن و ویرایش تبلیغ — نسخه پاک‌سازی‌شده
 *
 * @author Hooman Oliaei
 * @version 4.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/AdManager.php';
require_once __DIR__ . '/../includes/AdTargeting.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$adm = new AdManager($pdo);

$id = (int) ($_GET['id'] ?? 0);
$ad = $id > 0 ? $adm->getById($id) : null;
$isEdit = !empty($ad);

$success = '';
$error = '';

// ═══════════════════════════════════════════════════════════
// پردازش فرم
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'title'           => trim($_POST['title'] ?? ''),
            'description'     => trim($_POST['description'] ?? ''),
            'type'            => $_POST['type'] ?? 'image',
            'position_id'     => (int) ($_POST['position_id'] ?? 0),
            'campaign_id'     => (int) ($_POST['campaign_id'] ?? 0),
            'image_url'       => trim($_POST['image_url'] ?? ''),
            'target_url'      => trim($_POST['target_url'] ?? ''),
            'html_content'    => $_POST['html_content'] ?? '',
            'adsense_code'    => $_POST['adsense_code'] ?? '',
            'alt_text'        => trim($_POST['alt_text'] ?? ''),
            'width'           => (int) ($_POST['width'] ?? 0),
            'height'          => (int) ($_POST['height'] ?? 0),
            'target_blank'    => !empty($_POST['target_blank']) ? 1 : 0,
            'start_date'      => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date'        => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'priority'        => (int) ($_POST['priority'] ?? 0),
            'status'          => $_POST['status'] ?? 'active',
            'language'        => $_POST['language'] ?? null,
            'max_impressions' => (int) ($_POST['max_impressions'] ?? 0),
            'max_clicks'      => (int) ($_POST['max_clicks'] ?? 0),
            
            // فیلدهای جدید نمایش
            'display_mode'       => $_POST['display_mode'] ?? 'single',
            'animation_type'     => $_POST['animation_type'] ?? 'fade',
            'animation_duration' => (int) ($_POST['animation_duration'] ?? 600),
            'animation_delay'    => (int) ($_POST['animation_delay'] ?? 0),
            'animation_easing'   => $_POST['animation_easing'] ?? 'ease-in-out',
            'animation_loop'     => !empty($_POST['animation_loop']) ? 1 : 0,
            'autoplay'           => !empty($_POST['autoplay']) ? 1 : 0,
            'autoplay_interval'  => (int) ($_POST['autoplay_interval'] ?? 5000),
            'pause_on_hover'     => !empty($_POST['pause_on_hover']) ? 1 : 0,
            'loop'               => !empty($_POST['loop']) ? 1 : 0,
            'show_nav'           => !empty($_POST['show_nav']) ? 1 : 0,
            'show_dots'          => !empty($_POST['show_dots']) ? 1 : 0,
            'show_progress'      => !empty($_POST['show_progress']) ? 1 : 0,
            'grid_columns'       => (int) ($_POST['grid_columns'] ?? 2),
            'grid_gap'           => (int) ($_POST['grid_gap'] ?? 10),
            'container_type'     => $_POST['container_type'] ?? 'position',
            'container_selector' => trim($_POST['container_selector'] ?? ''),
            'hide_on_mobile'     => !empty($_POST['hide_on_mobile']) ? 1 : 0,
            'hide_on_desktop'    => !empty($_POST['hide_on_desktop']) ? 1 : 0,
            
            // هدف‌گیری محتوا
            'target_mode'        => $_POST['target_mode'] ?? 'all',
            'target_logic'       => $_POST['target_logic'] ?? 'AND',
        ];

        if (empty($data['title'])) {
            throw new Exception('عنوان اجباری است');
        }

        if ($isEdit) {
            $adm->update($id, $data);
            $targetId = $id;
            $success = 'تبلیغ با موفقیت به‌روزرسانی شد';
        } else {
            $targetId = $adm->create($data);
            $success = 'تبلیغ با موفقیت ایجاد شد';
        }

        // ═══ ذخیره بنرها ═══
        $postedBanners = $_POST['banners'] ?? [];
        if (is_array($postedBanners)) {
            $existingBanners = $adm->getBannersByAdId($targetId);
            $postedIds = [];
            foreach ($postedBanners as $b) {
                if (!empty($b['id'])) $postedIds[] = (int) $b['id'];
            }
            foreach ($existingBanners as $eb) {
                if (!in_array((int) $eb['id'], $postedIds, true)) {
                    $adm->deleteBanner((int) $eb['id']);
                }
            }

            foreach ($postedBanners as $i => $b) {
                if (empty($b['image_url'])) continue;

                $bData = [
                    'image_url'  => trim($b['image_url']),
                    'target_url' => trim($b['target_url'] ?? ''),
                    'alt_text'   => trim($b['alt_text'] ?? ''),
                    'title'      => trim($b['title'] ?? ''),
                    'sort_order' => (int) ($b['sort_order'] ?? $i),
                    'is_active'  => !empty($b['is_active']) ? 1 : 0,
                ];

                if (!empty($b['id'])) {
                    $adm->updateBanner((int) $b['id'], $bData);
                } else {
                    $adm->createBanner($targetId, $bData);
                }
            }

            // همگام‌سازی image_url از اولین بنر
            $firstBanner = $adm->getBannersByAdId($targetId, true);
            if (!empty($firstBanner)) {
                $pdo->prepare("UPDATE ads SET image_url = ? WHERE id = ?")
                    ->execute([$firstBanner[0]['image_url'], $targetId]);
            }
        }
        
        // ═══ ذخیره قواعد هدف‌گیری محتوا ═══
        $postedRules = $_POST['target_rules'] ?? [];
        if (is_array($postedRules)) {
            $targeting = $adm->getTargeting();
            $targeting->replaceRules($targetId, $postedRules);
        }

        if (!$isEdit) {
            header('Location: ad-edit.php?id=' . $targetId . '&msg=created');
            exit;
        }

        $ad = $adm->getById($id);
    } catch (Throwable $e) {
        $error = 'خطا: ' . $e->getMessage();
    }
}

if (($_GET['msg'] ?? '') === 'created') {
    $success = 'تبلیغ با موفقیت ایجاد شد';
}

$positions = $adm->getPositions();
$campaigns = $adm->getCampaigns();

$pageTitle = $isEdit ? 'ویرایش تبلیغ' : 'افزودن تبلیغ';
$sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php';
$currentFile = basename($_SERVER['PHP_SELF']);

$v = fn($key, $default = '') => htmlspecialchars($ad[$key] ?? $default);
$vInt = fn($key, $default = 0) => (int) ($ad[$key] ?? $default);

$iabSizes = AdManager::STANDARD_SIZES;

// بنرها برای پیش‌نمایش در فرم
$formBanners = $isEdit ? $adm->getBannersByAdId((int) $ad['id']) : [];
if (empty($formBanners) && !empty($ad['image_url'])) {
    $formBanners = [[
        'id'         => 0,
        'image_url'  => $ad['image_url'],
        'target_url' => $ad['target_url'] ?? '',
        'alt_text'   => $ad['alt_text'] ?? '',
        'title'      => $ad['title'] ?? '',
        'sort_order' => 0,
        'is_active'  => 1,
    ]];
}
$bannerCount = count($formBanners);

// قواعد هدف‌گیری موجود
$formRules = $isEdit ? $adm->getTargeting()->getRulesByAdId((int) $ad['id']) : [];

// دسته‌بندی‌ها، مقالات، برگه‌ها، برچسب‌ها
$allCategories = [];
$allPosts = [];
$allPages = [];
$allTags = [];

try {
    $allCategories = $pdo->query("SELECT id, name FROM categories ORDER BY name LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $allPosts = $pdo->query("SELECT id, title FROM content_items WHERE status = 'published' ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $allTags = $pdo->query("SELECT id, name FROM tags ORDER BY name LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    // نادیده
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | PartoCMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f1f5f9; font-family: Tahoma, sans-serif; margin: 0; }
        .main { margin-right: 260px; padding: 20px; max-width: 1400px; }
        @media (max-width: 900px) { .main { margin-right: 0; padding: 70px 15px 15px; } }
        .form-card { background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.05); margin-bottom: 20px; overflow: hidden; }
        .form-card-header { padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; font-weight: bold; color: #0f172a; }
        .form-card-header .icon { color: #06b6d4; margin-left: 8px; font-size: 18px; }
        .form-card-body { padding: 20px; }
        .type-selector { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        @media (max-width: 768px) { .type-selector { grid-template-columns: repeat(2, 1fr); } }
        .type-option { padding: 15px 10px; border: 2px solid #e2e8f0; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fff; }
        .type-option:hover { border-color: #06b6d4; background: #f0f9ff; }
        .type-option.active { border-color: #06b6d4; background: #e0f2fe; box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .type-option input { display: none; }
        .type-option .type-icon { font-size: 28px; margin-bottom: 5px; display: block; }
        .type-option .type-label { font-size: 12px; color: #475569; font-weight: bold; }

        .drop-zone { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 30px; text-align: center; cursor: pointer; transition: all 0.2s; background: #f8fafc; min-height: 200px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .drop-zone:hover, .drop-zone.dragover { border-color: #06b6d4; background: #f0f9ff; }
        .drop-zone-icon { font-size: 48px; color: #94a3b8; margin-bottom: 10px; }
        .drop-zone-title { font-weight: bold; color: #475569; margin-bottom: 5px; }
        .drop-zone-hint { font-size: 12px; color: #94a3b8; }

        .ad-preview { background: #f1f5f9; border-radius: 10px; padding: 20px; text-align: center; min-height: 200px; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .ad-preview img { max-width: 100%; max-height: 400px; border-radius: 6px; box-shadow: 0 2px 12px rgba(0,0,0,.1); }

        .size-presets { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        @media (max-width: 768px) { .size-presets { grid-template-columns: repeat(2, 1fr); } }
        .size-preset { padding: 10px; border: 2px solid #e2e8f0; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fff; font-size: 12px; }
        .size-preset:hover { border-color: #06b6d4; background: #f0f9ff; }
        .size-preset.active { border-color: #06b6d4; background: #e0f2fe; }
        .size-preset .size-dims { font-family: monospace; font-weight: bold; color: #0891b2; }
        .size-preset .size-label { color: #64748b; display: block; margin-top: 2px; }

        .position-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        @media (max-width: 1200px) { .position-grid { grid-template-columns: repeat(3, 1fr); } }
        @media (max-width: 768px) { .position-grid { grid-template-columns: repeat(2, 1fr); } }
        .position-card { padding: 15px; border: 2px solid #e2e8f0; border-radius: 10px; cursor: pointer; transition: all 0.2s; background: #fff; text-align: center; }
        .position-card:hover { border-color: #06b6d4; background: #f0f9ff; }
        .position-card.active { border-color: #06b6d4; background: #e0f2fe; box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .position-card input { display: none; }
        .position-card .position-icon { font-size: 24px; margin-bottom: 5px; }
        .position-card .position-title { font-weight: bold; font-size: 13px; color: #0f172a; margin-bottom: 3px; }
        .position-card .position-size { font-size: 11px; color: #64748b; font-family: monospace; }

        .form-actions { position: sticky; bottom: 20px; background: #fff; padding: 15px 20px; border-radius: 12px; box-shadow: 0 -4px 20px rgba(0,0,0,.08); display: flex; gap: 10px; align-items: center; justify-content: space-between; z-index: 50; margin-top: 20px; }

        .media-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        @media (max-width: 768px) { .media-grid { grid-template-columns: repeat(2, 1fr); } }
        .media-item { border: 2px solid #e2e8f0; border-radius: 8px; overflow: hidden; cursor: pointer; transition: all 0.2s; background: #f8fafc; }
        .media-item:hover { border-color: #06b6d4; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
        .media-item img { width: 100%; height: 120px; object-fit: cover; display: block; }
        .media-item-name { padding: 5px 8px; font-size: 11px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: #fff; }

        .section-hint { font-size: 12px; color: #94a3b8; margin-top: 3px; }
        .required-mark { color: #ef4444; }

        .display-mode-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
        .display-mode-card { padding: 15px 10px; border: 2px solid #e2e8f0; border-radius: 10px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fff; }
        .display-mode-card:hover { border-color: #06b6d4; background: #f0f9ff; transform: translateY(-2px); }
        .display-mode-card.active { border-color: #06b6d4; background: #e0f2fe; box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .display-mode-card input { display: none; }
        .display-mode-card .mode-icon { font-size: 28px; margin-bottom: 6px; }
        .display-mode-card .mode-label { font-weight: bold; font-size: 13px; color: #0f172a; margin-bottom: 4px; }
        .display-mode-card .mode-desc { font-size: 11px; color: #94a3b8; line-height: 1.4; }

        .animation-groups { display: flex; flex-direction: column; gap: 20px; }
        .animation-group { border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; background: #f8fafc; }
        .animation-group-title { font-weight: bold; font-size: 13px; color: #0891b2; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .animation-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px; }
        .animation-card { padding: 8px; border: 2px solid #e2e8f0; border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.2s; background: #fff; }
        .animation-card:hover { border-color: #06b6d4; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(6,182,212,.15); }
        .animation-card.active { border-color: #06b6d4; background: #e0f2fe; box-shadow: 0 0 0 3px rgba(6,182,212,.15); }
        .animation-card input { display: none; }
        .anim-preview { width: 100%; height: 50px; background: linear-gradient(135deg, #06b6d4, #0891b2); border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-bottom: 6px; overflow: hidden; color: #fff; font-weight: bold; font-size: 14px; }
        .anim-label { font-size: 11px; color: #64748b; line-height: 1.3; }
        .animation-card.active .anim-label { color: #0891b2; font-weight: bold; }

        .banners-list { display: flex; flex-direction: column; gap: 12px; }
        .banner-item { border: 2px solid #e2e8f0; border-radius: 10px; background: #f8fafc; overflow: hidden; }
        .banner-item-header { background: #f1f5f9; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e2e8f0; }
        .banner-number { font-weight: bold; font-size: 13px; color: #0891b2; }
        .banner-actions { display: flex; gap: 4px; }
        .btn-xs { padding: 2px 6px; font-size: 11px; line-height: 1; }
        .banner-item-body { padding: 12px; display: flex; gap: 15px; align-items: flex-start; flex-wrap: wrap; }
        .banner-preview { display: flex; flex-direction: column; align-items: center; gap: 8px; flex-shrink: 0; }
        .banner-image-box { width: 150px; height: 90px; border: 2px dashed #cbd5e1; border-radius: 8px; background: #fff; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .banner-image-box img { width: 100%; height: 100%; object-fit: cover; }
        .banner-placeholder { color: #cbd5e1; font-size: 32px; }
        .banner-controls { display: flex; gap: 4px; flex-wrap: wrap; justify-content: center; }
        .banner-fields { flex: 1; min-width: 250px; }
        @media (max-width: 768px) {
            .banner-item-body { flex-direction: column; }
            .banner-image-box { width: 100%; height: 150px; }
            .banner-fields { width: 100%; }
        }
    </style>
</head>
<body>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- هدر صفحه -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">
            <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'plus-circle' ?>"></i>
            <?= htmlspecialchars($pageTitle) ?>
        </h1>
        <div class="d-flex gap-2">
            <?php if ($isEdit): ?>
                <a href="ads.php?action=duplicate&id=<?= (int) $ad['id'] ?>"
                   class="btn btn-outline-secondary"
                   onclick="return confirm('یک کپی از این تبلیغ ساخته شود؟')">
                    <i class="bi bi-files"></i> تکثیر
                </a>
            <?php endif; ?>
            <a href="ads.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <!-- پیام‌ها -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" id="adForm" enctype="multipart/form-data">
        <div class="row g-3">
            <div class="col-lg-8">

                <!-- ═══ اطلاعات پایه ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-info-circle icon"></i> اطلاعات پایه</span>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label">عنوان <span class="required-mark">*</span></label>
                            <input type="text" name="title" class="form-control form-control-lg" required
                                   value="<?= $v('title') ?>" placeholder="عنوان تبلیغ...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <textarea name="description" class="form-control" rows="2"
                                      placeholder="توضیحات اختیاری..."><?= $v('description') ?></textarea>
                        </div>

                        <label class="form-label">نوع تبلیغ</label>
                        <div class="type-selector" data-card-group="type">
                            <?php
                            $types = [
                                'image'   => ['icon' => '🖼️',  'label' => 'تصویر'],
                                'slider'  => ['icon' => '🎞️',  'label' => 'اسلایدر'],
                                'html'    => ['icon' => '📝',   'label' => 'HTML'],
                                'text'    => ['icon' => '📄',   'label' => 'متن'],
                                'adsense' => ['icon' => '💲',   'label' => 'AdSense'],
                            ];
                            $currentType = $ad['type'] ?? 'image';
                            foreach ($types as $slug => $info):
                            ?>
                                <label class="type-option <?= $currentType === $slug ? 'active' : '' ?>"
                                       data-value="<?= $slug ?>">
                                    <input type="radio" name="type" value="<?= $slug ?>"
                                           <?= $currentType === $slug ? 'checked' : '' ?>>
                                    <span class="type-icon"><?= $info['icon'] ?></span>
                                    <span class="type-label"><?= $info['label'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ حالت نمایش ═══ -->
                <div class="form-card type-block" data-type-block="image slider">
                    <div class="form-card-header">
                        <span><i class="bi bi-grid-3x3-gap icon"></i> حالت نمایش</span>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label small text-muted mb-3">
                            نحوه نمایش بنرها را انتخاب کنید:
                        </label>
                        <div class="display-mode-grid" data-card-group="display_mode">
                            <?php
                            $displayModes = AdEffects::DISPLAY_MODES;
                            $currentMode = $ad['display_mode'] ?? 'single';
                            foreach ($displayModes as $slug => $info):
                            ?>
                                <label class="display-mode-card <?= $currentMode === $slug ? 'active' : '' ?>"
                                       data-value="<?= $slug ?>">
                                    <input type="radio" name="display_mode" value="<?= $slug ?>"
                                           <?= $currentMode === $slug ? 'checked' : '' ?>>
                                    <div class="mode-icon"><?= $info['icon'] ?></div>
                                    <div class="mode-label"><?= htmlspecialchars($info['label']) ?></div>
                                    <div class="mode-desc"><?= htmlspecialchars($info['desc']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ تصویر تبلیغ ═══ -->
                <div class="form-card type-block" data-type-block="image slider">
                    <div class="form-card-header">
                        <span><i class="bi bi-image icon"></i> تصویر اصلی</span>
                        <span class="badge bg-info">حداکثر ۵ مگابایت</span>
                    </div>
                    <div class="form-card-body">
                        <input type="hidden" name="image_url" id="imageUrlInput" value="<?= $v('image_url') ?>">

                        <div id="dropZone" class="drop-zone">
                            <div id="dropEmpty">
                                <div class="drop-zone-icon">☁️</div>
                                <div class="drop-zone-title">تصویر را اینجا رها کنید</div>
                                <div class="drop-zone-hint">یا کلیک کنید برای انتخاب فایل</div>
                                <div class="drop-zone-hint mt-2">JPG, PNG, GIF, WebP, SVG</div>
                            </div>
                            <div id="dropLoading" style="display:none;">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">در حال آپلود...</p>
                            </div>
                            <div id="dropPreview" style="display:none; width:100%;">
                                <img id="previewImg" src="" alt="" style="max-width:100%; max-height:300px; border-radius:8px;">
                                <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('fileInput').click();">
                                        <i class="bi bi-arrow-repeat"></i> تغییر
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#mediaPickerModal">
                                        <i class="bi bi-images"></i> از گالری
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeMainImage();">
                                        <i class="bi bi-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <input type="file" id="fileInput" accept="image/*" style="display:none;">

                        <div class="mt-3">
                            <label class="form-label small text-muted">
                                <i class="bi bi-link-45deg"></i> یا آدرس تصویر را دستی وارد کنید
                            </label>
                            <input type="text" id="imageUrlManual" class="form-control form-control-sm"
                                   value="<?= $v('image_url') ?>" placeholder="https://example.com/banner.jpg" dir="ltr">
                        </div>

                        <div class="mt-3">
                            <label class="form-label">متن جایگزین (alt)</label>
                            <input type="text" name="alt_text" class="form-control" value="<?= $v('alt_text') ?>"
                                   placeholder="توضیح تصویر برای SEO">
                        </div>
                    </div>
                </div>

                <!-- ═══ بنرها (چند بنر) ═══ -->
                <div class="form-card type-block" data-type-block="image slider">
                    <div class="form-card-header">
                        <span><i class="bi bi-collection icon"></i> بنرها</span>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addBanner()">
                            <i class="bi bi-plus-circle"></i> افزودن بنر
                        </button>
                    </div>
                    <div class="form-card-body">
                        <div class="section-hint mb-3">
                            <i class="bi bi-info-circle"></i>
                            برای حالت‌های اسلایدر، چرخشی، شبکه‌ای، عمودی و کاروسل، چند بنر اضافه کنید.
                        </div>

                        <div id="bannersList" class="banners-list">
                            <?php foreach ($formBanners as $idx => $banner): ?>
                                <div class="banner-item" data-banner-index="<?= $idx ?>">
                                    <div class="banner-item-header">
                                        <span class="banner-number">بنر #<?= $idx + 1 ?></span>
                                        <div class="banner-actions">
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="moveBanner(this, -1)" title="بالا"><i class="bi bi-arrow-up"></i></button>
                                            <button type="button" class="btn btn-xs btn-outline-secondary" onclick="moveBanner(this, 1)" title="پایین"><i class="bi bi-arrow-down"></i></button>
                                            <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeBanner(this)" title="حذف"><i class="bi bi-trash"></i></button>
                                        </div>
                                    </div>
                                    <div class="banner-item-body">
                                        <div class="banner-preview">
                                            <input type="hidden" name="banners[<?= $idx ?>][id]" value="<?= (int) ($banner['id'] ?? 0) ?>">
                                            <input type="hidden" name="banners[<?= $idx ?>][image_url]" class="banner-image-url" value="<?= htmlspecialchars($banner['image_url'] ?? '') ?>">
                                            <input type="hidden" name="banners[<?= $idx ?>][is_active]" value="1">
                                            <input type="hidden" name="banners[<?= $idx ?>][sort_order]" class="banner-sort-order" value="<?= $idx ?>">
                                            <div class="banner-image-box">
                                                <?php if (!empty($banner['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($banner['image_url']) ?>" alt="">
                                                <?php else: ?>
                                                    <div class="banner-placeholder"><i class="bi bi-image"></i></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="banner-controls">
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="uploadBannerImage(this)"><i class="bi bi-upload"></i> آپلود</button>
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="pickBannerFromGallery(this)"><i class="bi bi-images"></i> گالری</button>
                                            </div>
                                        </div>
                                        <div class="banner-fields">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <label class="form-label small">عنوان</label>
                                                    <input type="text" name="banners[<?= $idx ?>][title]" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($banner['title'] ?? '') ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small">متن جایگزین (alt)</label>
                                                    <input type="text" name="banners[<?= $idx ?>][alt_text]" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($banner['alt_text'] ?? '') ?>">
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label small">لینک مقصد</label>
                                                    <input type="text" name="banners[<?= $idx ?>][target_url]" class="form-control form-control-sm"
                                                           value="<?= htmlspecialchars($banner['target_url'] ?? '') ?>"
                                                           placeholder="https://example.com" dir="ltr">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div id="bannersEmpty" class="text-center py-4 text-muted" style="<?= $bannerCount > 0 ? 'display:none;' : '' ?>">
                            <i class="bi bi-collection" style="font-size:32px; opacity:.3;"></i>
                            <p class="mt-2 small mb-0">هنوز بنری اضافه نشده</p>
                        </div>
                    </div>
                </div>

                <!-- ═══ HTML ═══ -->
                <div class="form-card type-block" data-type-block="html">
                    <div class="form-card-header">
                        <span><i class="bi bi-code-slash icon"></i> کد HTML</span>
                    </div>
                    <div class="form-card-body">
                        <textarea name="html_content" class="form-control" rows="8"
                                  style="font-family:monospace; direction:ltr; text-align:left;"
                                  placeholder="<div>...</div>"><?= $v('html_content') ?></textarea>
                    </div>
                </div>

                <!-- ═══ AdSense ═══ -->
                <div class="form-card type-block" data-type-block="adsense">
                    <div class="form-card-header">
                        <span><i class="bi bi-google icon"></i> کد AdSense</span>
                    </div>
                    <div class="form-card-body">
                        <textarea name="adsense_code" class="form-control" rows="6"
                                  style="font-family:monospace; direction:ltr; text-align:left;"
                                  placeholder="<script async...></script>"><?= $v('adsense_code') ?></textarea>
                    </div>
                </div>

                <!-- ═══ اندازه ═══ -->
                <div class="form-card type-block" data-type-block="image slider html text adsense">
                    <div class="form-card-header">
                        <span><i class="bi bi-aspect-ratio icon"></i> اندازه تبلیغ</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyPositionSize()">
                            <i class="bi bi-magic"></i> از موقعیت
                        </button>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label small text-muted">اندازه‌های استاندارد:</label>
                        <div class="size-presets mb-4" data-card-group="size">
                            <?php foreach ($iabSizes as $key => $size): ?>
                                <div class="size-preset" data-w="<?= $size['w'] ?>" data-h="<?= $size['h'] ?>">
                                    <div class="size-dims"><?= $size['w'] ?>×<?= $size['h'] ?></div>
                                    <span class="size-label"><?= htmlspecialchars($size['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">عرض (px)</label>
                                <input type="number" name="width" id="adWidth" class="form-control"
                                       value="<?= $vInt('width') ?>" min="0" max="3000"
                                       placeholder="خودکار" onchange="updateSizePreview()">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">ارتفاع (px)</label>
                                <input type="number" name="height" id="adHeight" class="form-control"
                                       value="<?= $vInt('height') ?>" min="0" max="3000"
                                       placeholder="خودکار" onchange="updateSizePreview()">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-outline-secondary w-100" onclick="clearSize()" title="پاک کردن">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="form-label small text-muted"><i class="bi bi-eye"></i> پیش‌نمایش:</label>
                            <div class="ad-preview">
                                <div id="sizePreviewEmpty" class="text-muted">
                                    <i class="bi bi-image" style="font-size:32px; opacity:.3;"></i>
                                    <p class="mt-2 small mb-0">تصویر را انتخاب کنید</p>
                                </div>
                                <img id="sizePreviewImg" src="" alt="" style="display:none;">
                                <div id="sizePreviewInfo" class="mt-2 small text-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ انیمیشن ═══ -->
                <div class="form-card type-block" data-type-block="image slider html text adsense">
                    <div class="form-card-header">
                        <span><i class="bi bi-magic icon"></i> انیمیشن و جلوه‌ها</span>
                        <span class="badge bg-info">۴۵ افکت</span>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label small text-muted mb-2">افکت ورود:</label>
                        <div class="animation-groups">
                            <?php
                            $currentAnim = $ad['animation_type'] ?? 'fade';
                            $animationGroups = AdEffects::getAnimationGroups();
                            foreach ($animationGroups as $groupName => $effects):
                            ?>
                                <div class="animation-group">
                                    <div class="animation-group-title"><?= htmlspecialchars($groupName) ?></div>
                                    <div class="animation-grid" data-card-group="animation_type">
                                        <?php foreach ($effects as $slug => $info): ?>
                                            <label class="animation-card <?= $currentAnim === $slug ? 'active' : '' ?>"
                                                   data-value="<?= $slug ?>"
                                                   title="<?= htmlspecialchars($info['label']) ?>">
                                                <input type="radio" name="animation_type" value="<?= $slug ?>"
                                                       <?= $currentAnim === $slug ? 'checked' : '' ?>>
                                                <div class="anim-preview ad-anim-<?= $slug ?>">
                                                    <span>AD</span>
                                                </div>
                                                <div class="anim-label"><?= htmlspecialchars($info['label']) ?></div>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="row g-3 mt-4">
                            <div class="col-md-4">
                                <label class="form-label small">مدت (ms)</label>
                                <input type="number" name="animation_duration" class="form-control"
                                       value="<?= (int)($ad['animation_duration'] ?? 600) ?>"
                                       min="100" max="10000" step="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">تأخیر (ms)</label>
                                <input type="number" name="animation_delay" class="form-control"
                                       value="<?= (int)($ad['animation_delay'] ?? 0) ?>"
                                       min="0" max="5000" step="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">تکرار</label>
                                <div class="form-check form-switch" style="padding-top: 8px;">
                                    <input class="form-check-input" type="checkbox" name="animation_loop" value="1"
                                           id="animLoop" <?= !empty($ad['animation_loop']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="animLoop">بی‌نهایت</label>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label small">Easing:</label>
                            <select name="animation_easing" class="form-select form-select-sm">
                                <?php
                                $currentEasing = $ad['animation_easing'] ?? 'ease-in-out';
                                foreach (AdEffects::EASING_TYPES as $slug => $label):
                                ?>
                                    <option value="<?= $slug ?>" <?= $currentEasing === $slug ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- ═══ اسلایدر ═══ -->
                <div class="form-card type-block" data-type-block="image slider">
                    <div class="form-card-header">
                        <span><i class="bi bi-sliders icon"></i> تنظیمات اسلایدر</span>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="autoplay" value="1"
                                           id="autoplay" <?= ($ad['autoplay'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="autoplay">پخش خودکار</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="pause_on_hover" value="1"
                                           id="pauseOnHover" <?= ($ad['pause_on_hover'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="pauseOnHover">توقف در hover</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="loop" value="1"
                                           id="loop" <?= ($ad['loop'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="loop">تکرار (Loop)</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_nav" value="1"
                                           id="showNav" <?= ($ad['show_nav'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showNav">دکمه‌های ناوبری</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_dots" value="1"
                                           id="showDots" <?= ($ad['show_dots'] ?? 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showDots">نقطه‌ها</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_progress" value="1"
                                           id="showProgress" <?= ($ad['show_progress'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="showProgress">نوار پیشرفت</label>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-3">
                            <div class="col-md-6">
                                <label class="form-label small">فاصله بین اسلایدها (ms)</label>
                                <input type="number" name="autoplay_interval" class="form-control form-control-sm"
                                       value="<?= (int)($ad['autoplay_interval'] ?? 5000) ?>"
                                       min="1000" max="30000" step="500">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ Grid / Stack ═══ -->
                <div class="form-card type-block" data-type-block="image slider">
                    <div class="form-card-header">
                        <span><i class="bi bi-grid icon"></i> تنظیمات Grid / Stack</span>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">تعداد ستون‌ها (Grid)</label>
                                <select name="grid_columns" class="form-select form-select-sm">
                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                        <option value="<?= $i ?>" <?= (int)($ad['grid_columns'] ?? 2) === $i ? 'selected' : '' ?>>
                                            <?= $i ?> ستون
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small">فاصله بین بنرها (px)</label>
                                <input type="number" name="grid_gap" class="form-control form-control-sm"
                                       value="<?= (int)($ad['grid_gap'] ?? 10) ?>" min="0" max="100">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ موقعیت ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-geo-alt icon"></i> موقعیت نمایش</span>
                    </div>
                    <div class="form-card-body">
                        <div class="position-grid" data-card-group="position_id">
                            <?php
                            $positionIcons = [
                                'header'        => '⬆️',
                                'sidebar'       => '◀️',
                                'footer'        => '⬇️',
                                'in-content'    => '📄',
                                'popup'         => '🔔',
                                'sticky-footer' => '📌',
                                'slider-home'   => '🎞️',
                                'home-top'      => '🏠',
                            ];
                            $currentPosition = $vInt('position_id');
                            foreach ($positions as $pos):
                                $icon = $positionIcons[$pos['slug']] ?? '📍';
                            ?>
                                <label class="position-card <?= $currentPosition === (int)$pos['id'] ? 'active' : '' ?>"
                                       data-value="<?= (int)$pos['id'] ?>"
                                       data-position-w="<?= (int)$pos['width'] ?>"
                                       data-position-h="<?= (int)$pos['height'] ?>">
                                    <input type="radio" name="position_id" value="<?= (int)$pos['id'] ?>"
                                           <?= $currentPosition === (int)$pos['id'] ? 'checked' : '' ?>>
                                    <div class="position-icon"><?= $icon ?></div>
                                    <div class="position-title"><?= htmlspecialchars($pos['title']) ?></div>
                                    <div class="position-size"><?= (int)$pos['width'] ?>×<?= (int)$pos['height'] ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Container ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-code-slash icon"></i> محل نمایش (Container)</span>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small">نحوه هدف‌گیری</label>
                                <select name="container_type" id="containerType" class="form-select form-select-sm" onchange="toggleContainerSelector()">
                                    <option value="position" <?= ($ad['container_type'] ?? 'position') === 'position' ? 'selected' : '' ?>>
                                        از موقعیت‌های پیش‌فرض
                                    </option>
                                    <option value="selector" <?= ($ad['container_type'] ?? '') === 'selector' ? 'selected' : '' ?>>
                                        با CSS Selector
                                    </option>
                                </select>
                            </div>
                            <div class="col-md-6" id="containerSelectorBox" style="<?= ($ad['container_type'] ?? 'position') === 'selector' ? '' : 'display:none;' ?>">
                                <label class="form-label small">CSS Selector</label>
                                <input type="text" name="container_selector" class="form-control form-control-sm"
                                       value="<?= $v('container_selector') ?>"
                                       placeholder="#my-div, .custom-container" dir="ltr">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ هدف‌گیری محتوا ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-bullseye icon"></i> هدف‌گیری محتوا</span>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label small text-muted mb-3">
                            تبلیغ در کدام صفحات نمایش داده شود؟
                        </label>

                        <!-- انتخاب حالت -->
                        <div class="target-mode-grid" data-card-group="target_mode">
                            <?php
                            $currentMode = $ad['target_mode'] ?? 'all';
                            $targetModes = [
                                'all'    => ['icon' => '🌐', 'label' => 'همه صفحات', 'desc' => 'تبلیغ در تمام صفحات سایت'],
                                'manual' => ['icon' => '🎯', 'label' => 'انتخاب دستی', 'desc' => 'انتخاب دسته/مقاله/برگه/برچسب'],
                                'rules'  => ['icon' => '⚙️', 'label' => 'قواعد پیشرفته', 'desc' => 'ترکیب با AND/OR'],
                            ];
                            foreach ($targetModes as $slug => $info):
                            ?>
                                <label class="target-mode-card <?= $currentMode === $slug ? 'active' : '' ?>"
                                       data-value="<?= $slug ?>">
                                    <input type="radio" name="target_mode" value="<?= $slug ?>"
                                           <?= $currentMode === $slug ? 'checked' : '' ?>
                                           onchange="updateTargetMode()">
                                    <div class="mode-icon"><?= $info['icon'] ?></div>
                                    <div class="mode-label"><?= htmlspecialchars($info['label']) ?></div>
                                    <div class="mode-desc"><?= htmlspecialchars($info['desc']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- حالت Manual — انتخاب ساده -->
                        <div id="targetManualBox" class="mt-4" style="<?= $currentMode === 'manual' ? '' : 'display:none;' ?>">
                            <label class="form-label small text-muted">افزودن مورد:</label>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <select id="manualEntityType" class="form-select form-select-sm">
                                        <option value="category">دسته‌بندی</option>
                                        <option value="post">مقاله</option>
                                        <option value="tag">برچسب</option>
                                        <option value="author">نویسنده</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select id="manualEntityId" class="form-select form-select-sm">
                                        <option value="">— انتخاب —</option>
                                        <?php foreach ($allCategories as $cat): ?>
                                            <option value="cat_<?= (int)$cat['id'] ?>">📁 <?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($allPosts as $post): ?>
                                            <option value="post_<?= (int)$post['id'] ?>">📄 <?= htmlspecialchars($post['title']) ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($allTags as $tag): ?>
                                            <option value="tag_<?= (int)$tag['id'] ?>">🏷️ <?= htmlspecialchars($tag['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select id="manualIncludeExclude" class="form-select form-select-sm">
                                        <option value="include">شامل</option>
                                        <option value="exclude">مستثنی</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-primary w-100" onclick="addManualRule()">
                                        <i class="bi bi-plus"></i> افزودن
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- حالت Rules — قواعد پیشرفته -->
                        <div id="targetRulesBox" class="mt-4" style="<?= $currentMode === 'rules' ? '' : 'display:none;' ?>">
                            <label class="form-label small text-muted">منطق بین قواعد:</label>
                            <select name="target_logic" class="form-select form-select-sm mb-3" style="max-width: 200px;">
                                <option value="AND" <?= ($ad['target_logic'] ?? 'AND') === 'AND' ? 'selected' : '' ?>>
                                    AND (همه شروط)
                                </option>
                                <option value="OR" <?= ($ad['target_logic'] ?? '') === 'OR' ? 'selected' : '' ?>>
                                    OR (حداقل یک شرط)
                                </option>
                            </select>

                            <label class="form-label small text-muted">افزودن قاعده:</label>
                            <div class="row g-2 mb-3">
                                <div class="col-md-4">
                                    <select id="ruleEntityType" class="form-select form-select-sm">
                                        <option value="category">دسته‌بندی</option>
                                        <option value="post">مقاله</option>
                                        <option value="tag">برچسب</option>
                                        <option value="author">نویسنده</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <select id="ruleEntityId" class="form-select form-select-sm">
                                        <option value="">— انتخاب —</option>
                                        <?php foreach ($allCategories as $cat): ?>
                                            <option value="cat_<?= (int)$cat['id'] ?>">📁 <?= htmlspecialchars($cat['name']) ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($allPosts as $post): ?>
                                            <option value="post_<?= (int)$post['id'] ?>">📄 <?= htmlspecialchars($post['title']) ?></option>
                                        <?php endforeach; ?>
                                        <?php foreach ($allTags as $tag): ?>
                                            <option value="tag_<?= (int)$tag['id'] ?>">🏷️ <?= htmlspecialchars($tag['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select id="ruleIncludeExclude" class="form-select form-select-sm">
                                        <option value="include">شامل</option>
                                        <option value="exclude">مستثنی</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-sm btn-primary w-100" onclick="addRule()">
                                        <i class="bi bi-plus"></i> افزودن
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- لیست قواعد فعلی -->
                        <div id="targetRulesList" class="mt-3">
                            <?php if (!empty($formRules)): ?>
                                <?php foreach ($formRules as $i => $rule): ?>
                                    <div class="target-rule-item" data-rule-index="<?= $i ?>">
                                        <input type="hidden" name="target_rules[<?= $i ?>][entity_type]" value="<?= htmlspecialchars($rule['entity_type']) ?>">
                                        <input type="hidden" name="target_rules[<?= $i ?>][entity_id]" value="<?= (int) $rule['entity_id'] ?>">
                                        <input type="hidden" name="target_rules[<?= $i ?>][include_exclude]" value="<?= htmlspecialchars($rule['include_exclude']) ?>">
                                        <div class="rule-content">
                                            <span class="rule-badge rule-<?= $rule['include_exclude'] ?>">
                                                <?= $rule['include_exclude'] === 'exclude' ? '❌ مستثنی' : '✅ شامل' ?>
                                            </span>
                                            <span class="rule-type">
                                                <?php
                                                $types = ['category' => '📁 دسته', 'post' => '📄 مقاله', 'page' => '📃 برگه', 'tag' => '🏷️ برچسب', 'author' => '👤 نویسنده'];
                                                echo $types[$rule['entity_type']] ?? $rule['entity_type'];
                                                ?>
                                            </span>
                                            <span class="rule-name"><?= htmlspecialchars($rule['entity_name'] ?? 'ID: ' . $rule['entity_id']) ?></span>
                                        </div>
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeRule(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ Responsive ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-phone icon"></i> نمایش در دستگاه‌ها</span>
                    </div>
                    <div class="form-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="hide_on_mobile" value="1"
                                           id="hideMobile" <?= !empty($ad['hide_on_mobile']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="hideMobile">پنهان در موبایل</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="hide_on_desktop" value="1"
                                           id="hideDesktop" <?= !empty($ad['hide_on_desktop']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="hideDesktop">پنهان در دسکتاپ</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ لینک مقصد ═══ -->
                <div class="form-card type-block" data-type-block="image slider text">
                    <div class="form-card-header">
                        <span><i class="bi bi-link-45deg icon"></i> لینک مقصد</span>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label">آدرس مقصد (URL)</label>
                            <input type="text" name="target_url" class="form-control"
                                   value="<?= $v('target_url') ?>"
                                   placeholder="https://example.com" dir="ltr">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="target_blank"
                                   id="targetBlank" value="1" <?= ($ad['target_blank'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="targetBlank">باز شدن در تب جدید</label>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══ ستون کناری ═══ -->
            <div class="col-lg-4">

                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-toggle-on icon"></i> وضعیت</span>
                    </div>
                    <div class="form-card-body">
                        <select name="status" class="form-select">
                            <option value="active"  <?= ($ad['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>✅ فعال</option>
                            <option value="paused"  <?= ($ad['status'] ?? '') === 'paused' ? 'selected' : '' ?>>⏸️ متوقف</option>
                            <option value="draft"   <?= ($ad['status'] ?? '') === 'draft' ? 'selected' : '' ?>>📝 پیش‌نویس</option>
                            <option value="expired" <?= ($ad['status'] ?? '') === 'expired' ? 'selected' : '' ?>>⏰ منقضی</option>
                        </select>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-calendar icon"></i> زمان‌بندی (اختیاری)</span>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label small">تاریخ شروع</label>
                            <input type="datetime-local" name="start_date" class="form-control form-control-sm"
                                   value="<?= $v('start_date') ?>">
                        </div>
                        <div>
                            <label class="form-label small">تاریخ پایان</label>
                            <input type="datetime-local" name="end_date" class="form-control form-control-sm"
                                   value="<?= $v('end_date') ?>">
                        </div>
                    </div>
                </div>

                <?php if (!empty($campaigns)): ?>
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-diagram-3 icon"></i> کمپین</span>
                    </div>
                    <div class="form-card-body">
                        <select name="campaign_id" class="form-select">
                            <option value="">— بدون کمپین —</option>
                            <?php foreach ($campaigns as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"
                                    <?= $vInt('campaign_id') === (int)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-sliders icon"></i> تنظیمات پیشرفته</span>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label small">اولویت</label>
                            <input type="number" name="priority" class="form-control form-control-sm"
                                   value="<?= $vInt('priority') ?>" min="0" max="1000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">حداکثر نمایش</label>
                            <input type="number" name="max_impressions" class="form-control form-control-sm"
                                   value="<?= $vInt('max_impressions') ?>" min="0" placeholder="بدون محدودیت">
                        </div>
                        <div>
                            <label class="form-label small">حداکثر کلیک</label>
                            <input type="number" name="max_clicks" class="form-control form-control-sm"
                                   value="<?= $vInt('max_clicks') ?>" min="0" placeholder="بدون محدودیت">
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="form-actions">
            <div>
                <?php if ($isEdit): ?>
                    <a href="ads.php?action=delete&id=<?= (int)$ad['id'] ?>"
                       class="btn btn-outline-danger"
                       onclick="return confirm('این تبلیغ و همه آمار آن حذف شود؟')">
                        <i class="bi bi-trash"></i> حذف
                    </a>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <a href="ads.php" class="btn btn-outline-secondary btn-lg">انصراف</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i>
                    <?= $isEdit ? 'ذخیره تغییرات' : 'ایجاد تبلیغ' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- Modal انتخاب از گالری -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="mediaPickerModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-images"></i> انتخاب تصویر از گالری
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="mediaSearch" class="form-control"
                               placeholder="جستجو بر اساس نام فایل...">
                    </div>
                </div>
                <div id="mediaListContainer">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2 text-muted">در حال بارگذاری...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span id="mediaCount" class="text-muted me-auto"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- JavaScript -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
// ═══════════════════════════════════════════════════════════════
// ثابت‌ها
// ═══════════════════════════════════════════════════════════════
const UPLOAD_URL = '<?= SITE_URL ?>/modules/ads/ajax/upload.php';
const MEDIA_LIST_URL = '<?= SITE_URL ?>/modules/ads/ajax/media-list.php';

// ═══════════════════════════════════════════════════════════════
// بخش ۱: انتخاب کارت‌ها (Radio Card Group)
// ═══════════════════════════════════════════════════════════════

/**
 * راه‌اندازی گروه کارت‌های radio
 * با کلیک روی کارت، radio داخل آن چک می‌شود و بقیه کارت‌ها غیرفعال می‌شوند
 */
function initCardGroup(groupSelector) {
    const group = document.querySelector(groupSelector);
    if (!group) return;

    const cards = group.querySelectorAll(':scope > label');
    const radios = group.querySelectorAll('input[type="radio"]');

    if (cards.length === 0 || radios.length === 0) return;

    // کلیک روی کارت
    cards.forEach(card => {
        card.addEventListener('click', function(e) {
            // اگر روی خود radio کلیک شد، اجازه بده
            if (e.target.tagName === 'INPUT') return;

            const radio = this.querySelector('input[type="radio"]');
            if (radio && !radio.checked) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    // تغییر radio — همه غیرفعال، این یکی فعال
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            cards.forEach(card => card.classList.remove('active'));
            const activeCard = this.closest('label');
            if (activeCard) activeCard.classList.add('active');
        });
    });

    // همگام‌سازی اولیه
    const checked = group.querySelector('input[type="radio"]:checked');
    if (checked) {
        cards.forEach(card => card.classList.remove('active'));
        const activeCard = checked.closest('label');
        if (activeCard) activeCard.classList.add('active');
    }
}

// ═══════════════════════════════════════════════════════════════
// بخش ۲: اندازه‌ها — گروه کارت مستقل (بدون radio)
// ═══════════════════════════════════════════════════════════════

function initSizePresets() {
    const container = document.querySelector('[data-card-group="size"]');
    if (!container) return;

    const presets = container.querySelectorAll('.size-preset');
    presets.forEach(preset => {
        preset.addEventListener('click', function() {
            presets.forEach(p => p.classList.remove('active'));
            this.classList.add('active');

            const w = this.dataset.w;
            const h = this.dataset.h;
            document.getElementById('adWidth').value = w || '';
            document.getElementById('adHeight').value = h || '';
            updateSizePreview();
        });
    });
}

// ═══════════════════════════════════════════════════════════════
// بخش ۳: نمایش/پنهان بخش‌ها بر اساس نوع تبلیغ
// ═══════════════════════════════════════════════════════════════

function updateVisibleBlocks() {
    const selectedType = document.querySelector('input[name="type"]:checked')?.value || 'image';
    document.querySelectorAll('.type-block').forEach(block => {
        const types = block.dataset.typeBlock.split(' ');
        block.style.display = types.includes(selectedType) ? '' : 'none';
    });
}

// ═══════════════════════════════════════════════════════════════
// بخش ۴: اندازه و پیش‌نمایش
// ═══════════════════════════════════════════════════════════════

function applyPositionSize() {
    const selected = document.querySelector('.position-card.active');
    if (!selected) {
        alert('ابتدا یک موقعیت انتخاب کنید');
        return;
    }
    const w = selected.dataset.positionW;
    const h = selected.dataset.positionH;
    document.getElementById('adWidth').value = w || '';
    document.getElementById('adHeight').value = h || '';
    updateSizePreview();
}

function clearSize() {
    document.getElementById('adWidth').value = '';
    document.getElementById('adHeight').value = '';
    document.querySelectorAll('.size-preset').forEach(p => p.classList.remove('active'));
    updateSizePreview();
}

function updateSizePreview() {
    const img = document.getElementById('sizePreviewImg');
    const empty = document.getElementById('sizePreviewEmpty');
    const info = document.getElementById('sizePreviewInfo');
    const urlInput = document.getElementById('imageUrlInput');
    const w = parseInt(document.getElementById('adWidth').value) || 0;
    const h = parseInt(document.getElementById('adHeight').value) || 0;

    if (!img || !urlInput) return;

    const url = urlInput.value;

    if (url) {
        img.src = url;
        img.style.display = 'inline-block';
        empty.style.display = 'none';
        img.style.width = w > 0 ? w + 'px' : 'auto';
        img.style.height = h > 0 ? h + 'px' : 'auto';
        img.style.maxWidth = '100%';
        img.style.maxHeight = '400px';
    } else {
        img.style.display = 'none';
        empty.style.display = 'block';
    }

    if (info) {
        if (w > 0 || h > 0) {
            info.textContent = `اندازه: ${w > 0 ? w : 'خودکار'}×${h > 0 ? h : 'خودکار'} پیکسل`;
        } else {
            info.textContent = 'اندازه: خودکار';
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// بخش ۵: آپلود تصویر اصلی (Drop Zone)
// ═══════════════════════════════════════════════════════════════

function initMainImageUpload() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const dropEmpty = document.getElementById('dropEmpty');
    const dropLoading = document.getElementById('dropLoading');
    const dropPreview = document.getElementById('dropPreview');
    const previewImg = document.getElementById('previewImg');
    const imageUrlInput = document.getElementById('imageUrlInput');
    const imageUrlManual = document.getElementById('imageUrlManual');

    if (!dropZone) return;

    function showPreview(url) {
        if (url) {
            previewImg.src = url;
            dropEmpty.style.display = 'none';
            dropPreview.style.display = 'block';
            dropLoading.style.display = 'none';
        } else {
            dropEmpty.style.display = 'block';
            dropPreview.style.display = 'none';
            dropLoading.style.display = 'none';
        }
        updateSizePreview();
    }

    if (imageUrlInput.value) showPreview(imageUrlInput.value);

    dropZone.addEventListener('click', (e) => {
        if (e.target.closest('button')) return;
        if (dropPreview.style.display !== 'none') return;
        fileInput.click();
    });

    ['dragenter', 'dragover'].forEach(evt => {
        dropZone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropZone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) uploadMainFile(files[0]);
    });

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) uploadMainFile(e.target.files[0]);
    });

    async function uploadMainFile(file) {
        if (!file.type.startsWith('image/')) {
            alert('فقط فایل تصویری مجاز است');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            alert('حجم فایل باید کمتر از ۵ مگابایت باشد');
            return;
        }

        dropEmpty.style.display = 'none';
        dropPreview.style.display = 'none';
        dropLoading.style.display = 'block';

        try {
            const formData = new FormData();
            formData.append('file', file);

            const res = await fetch(UPLOAD_URL, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.ok) {
                imageUrlInput.value = data.url;
                imageUrlManual.value = data.url;
                showPreview(data.url);
            } else {
                alert('خطا: ' + (data.error || 'نامشخص'));
                showPreview(imageUrlInput.value);
            }
        } catch (err) {
            alert('خطا در آپلود: ' + err.message);
            showPreview(imageUrlInput.value);
        }
    }

    // همگام‌سازی فیلد دستی
    imageUrlManual.addEventListener('input', () => {
        const url = imageUrlManual.value.trim();
        imageUrlInput.value = url;
        showPreview(url);
    });
}

function removeMainImage() {
    if (!confirm('تصویر حذف شود؟')) return;
    document.getElementById('imageUrlInput').value = '';
    document.getElementById('imageUrlManual').value = '';
    document.getElementById('previewImg').src = '';
    document.getElementById('dropEmpty').style.display = 'block';
    document.getElementById('dropPreview').style.display = 'none';
    updateSizePreview();
}

// ═══════════════════════════════════════════════════════════════
// بخش ۶: مدیریت بنرها
// ═══════════════════════════════════════════════════════════════

let bannerCounter = <?= max(1, $bannerCount) ?>;
let currentBannerItem = null; // برای Media Picker

function addBanner() {
    const list = document.getElementById('bannersList');
    const empty = document.getElementById('bannersEmpty');
    if (!list) return;

    const idx = bannerCounter++;

    const html = `
        <div class="banner-item" data-banner-index="${idx}">
            <div class="banner-item-header">
                <span class="banner-number">بنر جدید</span>
                <div class="banner-actions">
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="moveBanner(this, -1)" title="بالا"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="moveBanner(this, 1)" title="پایین"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeBanner(this)" title="حذف"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="banner-item-body">
                <div class="banner-preview">
                    <input type="hidden" name="banners[${idx}][id]" value="0">
                    <input type="hidden" name="banners[${idx}][image_url]" class="banner-image-url" value="">
                    <input type="hidden" name="banners[${idx}][is_active]" value="1">
                    <input type="hidden" name="banners[${idx}][sort_order]" class="banner-sort-order" value="${idx}">
                    <div class="banner-image-box"><div class="banner-placeholder"><i class="bi bi-image"></i></div></div>
                    <div class="banner-controls">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="uploadBannerImage(this)"><i class="bi bi-upload"></i> آپلود</button>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="pickBannerFromGallery(this)"><i class="bi bi-images"></i> گالری</button>
                    </div>
                </div>
                <div class="banner-fields">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small">عنوان</label><input type="text" name="banners[${idx}][title]" class="form-control form-control-sm"></div>
                        <div class="col-md-6"><label class="form-label small">متن جایگزین</label><input type="text" name="banners[${idx}][alt_text]" class="form-control form-control-sm"></div>
                        <div class="col-12"><label class="form-label small">لینک مقصد</label><input type="text" name="banners[${idx}][target_url]" class="form-control form-control-sm" dir="ltr"></div>
                    </div>
                </div>
            </div>
        </div>
    `;

    list.insertAdjacentHTML('beforeend', html);
    if (empty) empty.style.display = 'none';
    updateBannerNumbers();
}

function removeBanner(btn) {
    if (!confirm('این بنر حذف شود؟')) return;
    const item = btn.closest('.banner-item');
    if (item) item.remove();
    updateBannerNumbers();

    const list = document.getElementById('bannersList');
    const empty = document.getElementById('bannersEmpty');
    if (list && list.children.length === 0 && empty) {
        empty.style.display = '';
    }
}

function moveBanner(btn, direction) {
    const item = btn.closest('.banner-item');
    if (!item) return;

    if (direction < 0) {
        const prev = item.previousElementSibling;
        if (prev) item.parentNode.insertBefore(item, prev);
    } else {
        const next = item.nextElementSibling;
        if (next) item.parentNode.insertBefore(next, item);
    }
    updateBannerNumbers();
}

function updateBannerNumbers() {
    document.querySelectorAll('#bannersList .banner-item').forEach((item, i) => {
        const num = item.querySelector('.banner-number');
        if (num) num.textContent = 'بنر #' + (i + 1);
        const sort = item.querySelector('.banner-sort-order');
        if (sort) sort.value = i;
    });
}

function uploadBannerImage(btn) {
    const item = btn.closest('.banner-item');
    if (!item) return;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        if (e.target.files.length > 0) uploadBannerFile(e.target.files[0], item);
    };
    input.click();
}

async function uploadBannerFile(file, item) {
    if (!file.type.startsWith('image/')) {
        alert('فقط تصویر مجاز است');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        alert('حجم فایل باید کمتر از ۵ مگابایت باشد');
        return;
    }

    const box = item.querySelector('.banner-image-box');
    if (box) box.innerHTML = '<div class="spinner-border text-primary" style="width:24px;height:24px;"></div>';

    try {
        const formData = new FormData();
        formData.append('file', file);

        const res = await fetch(UPLOAD_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        });
        const data = await res.json();

        if (data.ok) {
            setBannerImage(item, data.url);
        } else {
            alert('خطا: ' + (data.error || 'نامشخص'));
            if (box) box.innerHTML = '<div class="banner-placeholder"><i class="bi bi-image"></i></div>';
        }
    } catch (err) {
        alert('خطا: ' + err.message);
        if (box) box.innerHTML = '<div class="banner-placeholder"><i class="bi bi-image"></i></div>';
    }
}

function setBannerImage(item, url) {
    const input = item.querySelector('.banner-image-url');
    if (input) input.value = url;

    const box = item.querySelector('.banner-image-box');
    if (box) {
        box.innerHTML = '<img src="' + url + '" alt="" style="width:100%;height:100%;object-fit:cover;">';
    }
}

function pickBannerFromGallery(btn) {
    const item = btn.closest('.banner-item');
    if (!item) return;

    currentBannerItem = item;
    const modalEl = document.getElementById('mediaPickerModal');
    if (modalEl) {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}

// ═══════════════════════════════════════════════════════════════
// بخش ۷: Media Picker Modal
// ═══════════════════════════════════════════════════════════════

function initMediaPicker() {
    const modal = document.getElementById('mediaPickerModal');
    const listContainer = document.getElementById('mediaListContainer');
    const searchInput = document.getElementById('mediaSearch');
    const countSpan = document.getElementById('mediaCount');

    if (!modal || !listContainer) return;

    let loaded = false;
    let searchTimeout;

    modal.addEventListener('show.bs.modal', () => {
        if (!loaded) {
            loadMedia();
            loaded = true;
        }
    });

    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadMedia(searchInput.value), 400);
    });

    async function loadMedia(search = '') {
        listContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 text-muted">در حال بارگذاری...</p>
            </div>
        `;

        const url = MEDIA_LIST_URL + '?type=image&limit=60&q=' + encodeURIComponent(search);

        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            const data = await res.json();

            if (!data.ok || !data.items || data.items.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox" style="font-size:48px; opacity:.3;"></i>
                        <p class="mt-3">تصویری یافت نشد</p>
                    </div>
                `;
                countSpan.textContent = '';
                return;
            }

            countSpan.textContent = `${data.items.length} از ${data.total} تصویر`;

            let html = '<div class="media-grid">';
            data.items.forEach(item => {
                const safeUrl = item.url.replace(/'/g, "\\'");
                html += `
                    <div class="media-item" data-url="${item.url}">
                        <img src="${item.url}" alt="${item.original_name || item.filename}" loading="lazy">
                        <div class="media-item-name" title="${item.original_name || item.filename}">
                            ${item.width && item.height ? `${item.width}×${item.height}` : ''}
                            ${item.original_name ? ' • ' + item.original_name.substring(0, 30) : ''}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            listContainer.innerHTML = html;

            // رویداد کلیک روی هر media-item
            listContainer.querySelectorAll('.media-item').forEach(el => {
                el.addEventListener('click', () => {
                    const url = el.dataset.url;
                    selectMedia(url);
                });
            });

        } catch (err) {
            listContainer.innerHTML = `
                <div class="alert alert-danger">
                    خطا در بارگذاری: ${err.message}
                </div>
            `;
        }
    }

    // انتخاب تصویر از گالری
    function selectMedia(url) {
        if (currentBannerItem) {
            // برای بنر
            setBannerImage(currentBannerItem, url);
            currentBannerItem = null;
        } else {
            // برای تصویر اصلی
            const imageUrlInput = document.getElementById('imageUrlInput');
            const imageUrlManual = document.getElementById('imageUrlManual');
            const previewImg = document.getElementById('previewImg');

            imageUrlInput.value = url;
            imageUrlManual.value = url;
            previewImg.src = url;
            document.getElementById('dropEmpty').style.display = 'none';
            document.getElementById('dropPreview').style.display = 'block';
            document.getElementById('dropLoading').style.display = 'none';

            updateSizePreview();
        }

        const m = bootstrap.Modal.getInstance(modal);
        if (m) m.hide();
    }
}

// ═══════════════════════════════════════════════════════════════
// بخش ۷-ب: هدف‌گیری محتوا
// ═══════════════════════════════════════════════════════════════

const TARGET_DATA = {
    category: <?= json_encode(array_map(fn($x) => ['id' => (int)$x['id'], 'name' => $x['name']], $allCategories ?? []), JSON_UNESCAPED_UNICODE) ?>,
    post: <?= json_encode(array_map(fn($x) => ['id' => (int)$x['id'], 'name' => $x['title']], $allPosts ?? []), JSON_UNESCAPED_UNICODE) ?>,
    tag: <?= json_encode(array_map(fn($x) => ['id' => (int)$x['id'], 'name' => $x['name']], $allTags ?? []), JSON_UNESCAPED_UNICODE) ?>,
    author: []
};

let ruleCounter = <?= max(1, count($formRules ?? [])) ?>;

function updateTargetMode() {
    const mode = document.querySelector('input[name="target_mode"]:checked')?.value || 'all';
    
    const manualBox = document.getElementById('targetManualBox');
    const rulesBox = document.getElementById('targetRulesBox');
    
    if (manualBox) manualBox.style.display = mode === 'manual' ? '' : 'none';
    if (rulesBox) rulesBox.style.display = mode === 'rules' ? '' : 'none';
}

function fillEntitySelect(selectEl, type) {
    if (!selectEl) return;
    const items = TARGET_DATA[type] || [];
    selectEl.innerHTML = '<option value="">— انتخاب —</option>';
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name;
        selectEl.appendChild(opt);
    });
}

function addManualRule() {
    const selectEl = document.getElementById('manualEntityId');
    const value = selectEl.value;
    const inc = document.getElementById('manualIncludeExclude').value;

    if (!value) {
        alert('یک مورد انتخاب کنید');
        return;
    }

    const parts = value.split('_');
    const type = parts[0] === 'cat' ? 'category' : parts[0];
    const id = parseInt(parts[1]);

    const opt = selectEl.options[selectEl.selectedIndex];
    const name = opt.textContent.trim();

    addRuleToListWithName(type, id, inc, name);
    selectEl.value = '';
}

function addRule() {
    const selectEl = document.getElementById('ruleEntityId');
    const value = selectEl.value;
    const inc = document.getElementById('ruleIncludeExclude').value;

    if (!value) {
        alert('یک مورد انتخاب کنید');
        return;
    }

    const parts = value.split('_');
    const type = parts[0] === 'cat' ? 'category' : parts[0];
    const id = parseInt(parts[1]);

    const opt = selectEl.options[selectEl.selectedIndex];
    const name = opt.textContent.trim();

    addRuleToListWithName(type, id, inc, name);
    selectEl.value = '';
}

function addRuleToListWithName(type, id, inc, name) {
    const list = document.getElementById('targetRulesList');
    if (!list) return;

    const idx = ruleCounter++;
    const typeLabels = {category: '📁 دسته', post: '📄 مقاله', tag: '🏷️ برچسب', author: '👤 نویسنده'};

    const html = `
        <div class="target-rule-item" data-rule-index="${idx}">
            <input type="hidden" name="target_rules[${idx}][entity_type]" value="${type}">
            <input type="hidden" name="target_rules[${idx}][entity_id]" value="${id}">
            <input type="hidden" name="target_rules[${idx}][include_exclude]" value="${inc}">
            <div class="rule-content">
                <span class="rule-badge rule-${inc}">
                    ${inc === 'exclude' ? '❌ مستثنی' : '✅ شامل'}
                </span>
                <span class="rule-type">${typeLabels[type] || type}</span>
                <span class="rule-name">${name}</span>
            </div>
            <button type="button" class="btn btn-xs btn-outline-danger" onclick="removeRule(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;

    list.insertAdjacentHTML('beforeend', html);
}

function removeRule(btn) {
    const item = btn.closest('.target-rule-item');
    if (item) item.remove();
}

function initTargetSelectors() {
    console.log('🔧 initTargetSelectors');
    console.log('📊 TARGET_DATA:', TARGET_DATA);
    console.log('📊 category:', TARGET_DATA.category.length);
    console.log('📊 post:', TARGET_DATA.post.length);
    console.log('📊 tag:', TARGET_DATA.tag.length);
    
    const manualType = document.getElementById('manualEntityType');
    const manualId = document.getElementById('manualEntityId');
    const ruleType = document.getElementById('ruleEntityType');
    const ruleId = document.getElementById('ruleEntityId');
    
    if (manualType && manualId) {
        manualType.addEventListener('change', () => {
            console.log('📌 Manual type changed to:', manualType.value);
            fillEntitySelect(manualId, manualType.value);
        });
        fillEntitySelect(manualId, manualType.value);
        console.log('✅ Manual select initialized');
    } else {
        console.log('❌ Manual select elements not found');
    }
    
    if (ruleType && ruleId) {
        ruleType.addEventListener('change', () => {
            console.log('📌 Rule type changed to:', ruleType.value);
            fillEntitySelect(ruleId, ruleType.value);
        });
        fillEntitySelect(ruleId, ruleType.value);
        console.log('✅ Rule select initialized');
    } else {
        console.log('❌ Rule select elements not found');
    }
}

// ═══════════════════════════════════════════════════════════════
// بخش ۸: Container Type Toggle
// ═══════════════════════════════════════════════════════════════

function toggleContainerSelector() {
    const type = document.getElementById('containerType').value;
    const box = document.getElementById('containerSelectorBox');
    if (box) box.style.display = type === 'selector' ? '' : 'none';
}

// ═══════════════════════════════════════════════════════════════
// بخش ۹: راه‌اندازی اولیه
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function() {
    // ۱. گروه‌های کارت
    initCardGroup('[data-card-group="type"]');
    initCardGroup('[data-card-group="display_mode"]');
    initCardGroup('[data-card-group="animation_type"]');
    initCardGroup('[data-card-group="position_id"]');
    initSizePresets();

    // ۲. رویداد تغییر نوع تبلیغ
    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', updateVisibleBlocks);
    });
    // اجرای اولیه
    updateVisibleBlocks();

    // ۳. آپلود تصویر
    initMainImageUpload();

    // ۴. Media Picker
    initMediaPicker();

    // ۵. Tooltip
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });

    // ۶. پیش‌نمایش اولیه
    setTimeout(() => {
        updateSizePreview();
        updateBannerNumbers();
    }, 100);
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
