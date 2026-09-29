<?php
/**
 * PartoCMS - Ads Admin - Add/Edit v3.0
 * فرم حرفه‌ای افزودن و ویرایش تبلیغ
 *
 * @author Hooman Oliaei
 * @version 3.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/AdManager.php';

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
        ];

        if (empty($data['title'])) {
            throw new Exception('عنوان اجباری است');
        }

        if ($isEdit) {
            $adm->update($id, $data);
            $success = 'تبلیغ با موفقیت به‌روزرسانی شد';
            $ad = $adm->getById($id);
        } else {
            $newId = $adm->create($data);
            header('Location: ad-edit.php?id=' . $newId . '&msg=created');
            exit;
        }
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

// Helper
$v = fn($key, $default = '') => htmlspecialchars($ad[$key] ?? $default);
$vInt = fn($key, $default = 0) => (int) ($ad[$key] ?? $default);

// اندازه‌های IAB
$iabSizes = AdManager::STANDARD_SIZES;
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

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ستون اصلی -->
            <!-- ═══════════════════════════════════════════════════ -->
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

                        <!-- نوع تبلیغ -->
                        <label class="form-label">نوع تبلیغ</label>
                        <div class="type-selector">
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
                                       data-type="<?= $slug ?>">
                                    <input type="radio" name="type" value="<?= $slug ?>"
                                           <?= $currentType === $slug ? 'checked' : '' ?>>
                                    <span class="type-icon"><?= $info['icon'] ?></span>
                                    <span class="type-label"><?= $info['label'] ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- ═══ بخش تصویر (برای image و slider) ═══ -->
                <div class="form-card type-block" data-type-block="image slider">
                    <div class="form-card-header">
                        <span><i class="bi bi-image icon"></i> تصویر تبلیغ</span>
                        <span class="badge bg-info">حداکثر ۵ مگابایت</span>
                    </div>
                    <div class="form-card-body">
                        <!-- فیلد مخفی URL نهایی -->
                        <input type="hidden" name="image_url" id="imageUrlInput" value="<?= $v('image_url') ?>">

                        <!-- Drop Zone / Preview -->
                        <div id="dropZone" class="drop-zone">
                            <!-- حالت خالی -->
                            <div id="dropEmpty">
                                <div class="drop-zone-icon">☁️</div>
                                <div class="drop-zone-title">تصویر را اینجا رها کنید</div>
                                <div class="drop-zone-hint">یا کلیک کنید برای انتخاب فایل</div>
                                <div class="drop-zone-hint mt-2">فرمت‌های مجاز: JPG, PNG, GIF, WebP, SVG</div>
                            </div>

                            <!-- حالت لودینگ -->
                            <div id="dropLoading" style="display:none;">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 text-muted">در حال آپلود...</p>
                            </div>

                            <!-- حالت پیش‌نمایش -->
                            <div id="dropPreview" style="display:none; width:100%;">
                                <img id="previewImg" src="" alt="" style="max-width:100%; max-height:300px; border-radius:8px;">
                                <div class="mt-3 d-flex justify-content-center gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="document.getElementById('fileInput').click();">
                                        <i class="bi bi-arrow-repeat"></i> تغییر
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#mediaPickerModal">
                                        <i class="bi bi-images"></i> از گالری
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeImage();">
                                        <i class="bi bi-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>

                        <input type="file" id="fileInput" accept="image/*" style="display:none;">

                        <!-- لینک دستی -->
                        <div class="mt-3">
                            <label class="form-label small text-muted">
                                <i class="bi bi-link-45deg"></i> یا آدرس تصویر را دستی وارد کنید
                            </label>
                            <input type="text" id="imageUrlManual" class="form-control form-control-sm"
                                   value="<?= $v('image_url') ?>" placeholder="https://example.com/banner.jpg">
                        </div>

                        <div class="mt-3">
                            <label class="form-label">متن جایگزین (alt)</label>
                            <input type="text" name="alt_text" class="form-control" value="<?= $v('alt_text') ?>"
                                   placeholder="توضیح تصویر برای SEO">
                        </div>
                    </div>
                </div>

                <!-- ═══ بخش HTML ═══ -->
                <div class="form-card type-block" data-type-block="html">
                    <div class="form-card-header">
                        <span><i class="bi bi-code-slash icon"></i> کد HTML</span>
                    </div>
                    <div class="form-card-body">
                        <textarea name="html_content" class="form-control" rows="8" 
                                  style="font-family:monospace; direction:ltr; text-align:left;"
                                  placeholder="<div>...</div>"><?= $v('html_content') ?></textarea>
                        <div class="section-hint">کد HTML، JS یا iframe تبلیغ را وارد کنید</div>
                    </div>
                </div>

                <!-- ═══ بخش AdSense ═══ -->
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

                <!-- ═══ بخش اندازه ═══ -->
                <div class="form-card type-block" data-type-block="image slider html text adsense">
                    <div class="form-card-header">
                        <span><i class="bi bi-aspect-ratio icon"></i> اندازه تبلیغ</span>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyPositionSize()">
                            <i class="bi bi-magic"></i> از موقعیت
                        </button>
                    </div>
                    <div class="form-card-body">
                        <!-- اندازه‌های آماده -->
                        <label class="form-label small text-muted">اندازه‌های استاندارد (IAB):</label>
                        <div class="size-presets mb-4">
                            <?php foreach ($iabSizes as $key => $size): ?>
                                <div class="size-preset" data-w="<?= $size['w'] ?>" data-h="<?= $size['h'] ?>">
                                    <div class="size-dims"><?= $size['w'] ?>×<?= $size['h'] ?></div>
                                    <span class="size-label"><?= htmlspecialchars($size['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- ابعاد دستی -->
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

                        <!-- پیش‌نمایش اندازه -->
                        <div class="mt-4">
                            <label class="form-label small text-muted">
                                <i class="bi bi-eye"></i> پیش‌نمایش با اندازه:
                            </label>
                            <div class="ad-preview" id="sizePreviewContainer">
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

                <!-- ═══ بخش موقعیت ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-geo-alt icon"></i> موقعیت نمایش</span>
                    </div>
                    <div class="form-card-body">
                        <label class="form-label small text-muted mb-3">
                            موقعیت را انتخاب کنید:
                        </label>
                        <div class="position-grid">
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
                                       data-position-id="<?= (int)$pos['id'] ?>"
                                       data-position-slug="<?= htmlspecialchars($pos['slug']) ?>"
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

                <!-- ═══ بخش لینک مقصد ═══ -->
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
                            <label class="form-check-label" for="targetBlank">
                                باز شدن در تب جدید
                            </label>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══════════════════════════════════════════════════ -->
            <!-- ستون کناری -->
            <!-- ═══════════════════════════════════════════════════ -->
            <div class="col-lg-4">

                <!-- ═══ وضعیت ═══ -->
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

                <!-- ═══ زمان‌بندی ═══ -->
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

                <!-- ═══ کمپین ═══ -->
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

                <!-- ═══ تنظیمات پیشرفته ═══ -->
                <div class="form-card">
                    <div class="form-card-header">
                        <span><i class="bi bi-sliders icon"></i> تنظیمات پیشرفته</span>
                    </div>
                    <div class="form-card-body">
                        <div class="mb-3">
                            <label class="form-label small">
                                اولویت
                                <i class="bi bi-info-circle" 
                                   data-bs-toggle="tooltip" 
                                   title="عدد بالاتر = نمایش بیشتر"></i>
                            </label>
                            <input type="number" name="priority" class="form-control form-control-sm"
                                   value="<?= $vInt('priority') ?>" min="0" max="1000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">حداکثر نمایش (impression)</label>
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

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- دکمه‌ها -->
        <!-- ═══════════════════════════════════════════════════ -->
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
                <!-- جستجو -->
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="mediaSearch" class="form-control" 
                               placeholder="جستجو بر اساس نام فایل...">
                    </div>
                </div>

                <!-- لیست -->
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
<!-- Scripts -->
<!-- ═══════════════════════════════════════════════════════════ -->
<script>
// ═══════════════════════════════════════════════════════════════
// ثابت‌ها
// ═══════════════════════════════════════════════════════════════
const UPLOAD_URL = '<?= SITE_URL ?>/modules/ads/ajax/upload.php';
const MEDIA_LIST_URL = '<?= SITE_URL ?>/modules/ads/ajax/media-list.php';

// ═══════════════════════════════════════════════════════════════
// نوع تبلیغ — نمایش/پنهان کردن بخش‌ها
// ═══════════════════════════════════════════════════════════════
document.querySelectorAll('.type-option').forEach(option => {
    option.addEventListener('click', function() {
        // حذف active از همه
        document.querySelectorAll('.type-option').forEach(o => o.classList.remove('active'));
        // اضافه کردن به این
        this.classList.add('active');
        // radio رو چک کن
        this.querySelector('input[type="radio"]').checked = true;
        // بروزرسانی بخش‌ها
        updateVisibleBlocks();
    });
});

function updateVisibleBlocks() {
    const selectedType = document.querySelector('input[name="type"]:checked')?.value || 'image';
    document.querySelectorAll('.type-block').forEach(block => {
        const types = block.dataset.typeBlock.split(' ');
        block.style.display = types.includes(selectedType) ? '' : 'none';
    });
}
updateVisibleBlocks();

// ═══════════════════════════════════════════════════════════════
// موقعیت — انتخاب کارت
// ═══════════════════════════════════════════════════════════════
document.querySelectorAll('.position-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.position-card').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        this.querySelector('input[type="radio"]').checked = true;
    });
});

// ═══════════════════════════════════════════════════════════════
// اعمال اندازه از موقعیت
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
    updateSizePreview();
}

// ═══════════════════════════════════════════════════════════════
// اندازه‌های استاندارد
// ═══════════════════════════════════════════════════════════════
document.querySelectorAll('.size-preset').forEach(preset => {
    preset.addEventListener('click', function() {
        const w = this.dataset.w;
        const h = this.dataset.h;
        document.getElementById('adWidth').value = w;
        document.getElementById('adHeight').value = h;
        
        // حذف active از همه
        document.querySelectorAll('.size-preset').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        
        updateSizePreview();
    });
});

// ═══════════════════════════════════════════════════════════════
// پیش‌نمایش اندازه
// ═══════════════════════════════════════════════════════════════
function updateSizePreview() {
    const img = document.getElementById('sizePreviewImg');
    const empty = document.getElementById('sizePreviewEmpty');
    const info = document.getElementById('sizePreviewInfo');
    const url = document.getElementById('imageUrlInput').value;
    const w = parseInt(document.getElementById('adWidth').value) || 0;
    const h = parseInt(document.getElementById('adHeight').value) || 0;
    
    if (url) {
        img.src = url;
        img.style.display = 'inline-block';
        empty.style.display = 'none';
        
        // اعمال اندازه
        img.style.width = w > 0 ? w + 'px' : 'auto';
        img.style.height = h > 0 ? h + 'px' : 'auto';
        img.style.maxWidth = '100%';
        img.style.maxHeight = '400px';
    } else {
        img.style.display = 'none';
        empty.style.display = 'block';
    }
    
    // اطلاعات
    if (w > 0 || h > 0) {
        info.textContent = `اندازه: ${w > 0 ? w : 'خودکار'}×${h > 0 ? h : 'خودکار'} پیکسل`;
    } else {
        info.textContent = 'اندازه: خودکار';
    }
}

// ═══════════════════════════════════════════════════════════════
// آپلود تصویر — Drag & Drop + کلیک
// ═══════════════════════════════════════════════════════════════
(function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const dropEmpty = document.getElementById('dropEmpty');
    const dropLoading = document.getElementById('dropLoading');
    const dropPreview = document.getElementById('dropPreview');
    const previewImg = document.getElementById('previewImg');
    const imageUrlInput = document.getElementById('imageUrlInput');
    const imageUrlManual = document.getElementById('imageUrlManual');
    
    if (!dropZone) return;
    
    // نمایش اولیه
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
    
    // بار اول
    if (imageUrlInput.value) showPreview(imageUrlInput.value);
    
    // کلیک روی dropZone
    dropZone.addEventListener('click', (e) => {
        if (e.target.closest('button')) return;
        if (dropPreview.style.display !== 'none') return; // اگر پیش‌نمایش هست، کلیک نکن
        fileInput.click();
    });
    
    // Drag & Drop
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
        if (files.length > 0) uploadFile(files[0]);
    });
    
    // انتخاب فایل
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) uploadFile(e.target.files[0]);
    });
    
    // آپلود
    function uploadFile(file) {
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
        
        const formData = new FormData();
        formData.append('file', file);
        
        fetch(UPLOAD_URL, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                imageUrlInput.value = data.url;
                imageUrlManual.value = data.url;
                showPreview(data.url);
            } else {
                alert('خطا: ' + (data.error || 'نامشخص'));
                showPreview(imageUrlInput.value);
            }
        })
        .catch(err => {
            alert('خطا در آپلود: ' + err.message);
            showPreview(imageUrlInput.value);
        });
    }
    
    // حذف تصویر
    window.removeImage = function() {
        if (!confirm('تصویر حذف شود؟')) return;
        imageUrlInput.value = '';
        imageUrlManual.value = '';
        previewImg.src = '';
        showPreview('');
    };
    
    // همگام‌سازی با فیلد دستی
    imageUrlManual.addEventListener('input', () => {
        const url = imageUrlManual.value.trim();
        imageUrlInput.value = url;
        showPreview(url);
    });
    
    // دکمه «تغییر»
    window.changeImage = function() {
        fileInput.click();
    };
})();

// ═══════════════════════════════════════════════════════════════
// Media Picker Modal
// ═══════════════════════════════════════════════════════════════
(function() {
    const modal = document.getElementById('mediaPickerModal');
    const listContainer = document.getElementById('mediaListContainer');
    const searchInput = document.getElementById('mediaSearch');
    const countSpan = document.getElementById('mediaCount');
    let loaded = false;
    
    if (!modal) return;
    
    modal.addEventListener('show.bs.modal', () => {
        if (!loaded) {
            loadMedia();
            loaded = true;
        }
    });
    
    // جستجو
    let searchTimeout;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadMedia(searchInput.value), 400);
    });
    
    function loadMedia(search = '') {
        listContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <p class="mt-2 text-muted">در حال بارگذاری...</p>
            </div>
        `;
        
        const url = MEDIA_LIST_URL + '?type=image&limit=60&q=' + encodeURIComponent(search);
        
        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
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
                    html += `
                        <div class="media-item" onclick="selectMedia('${item.url.replace(/'/g, "\\'")}')">
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
            })
            .catch(err => {
                listContainer.innerHTML = `
                    <div class="alert alert-danger">
                        خطا در بارگذاری: ${err.message}
                    </div>
                `;
            });
    }
    
    // انتخاب تصویر
    window.selectMedia = function(url) {
        document.getElementById('imageUrlInput').value = url;
        document.getElementById('imageUrlManual').value = url;
        
        const previewImg = document.getElementById('previewImg');
        previewImg.src = url;
        document.getElementById('dropEmpty').style.display = 'none';
        document.getElementById('dropPreview').style.display = 'block';
        document.getElementById('dropLoading').style.display = 'none';
        
        updateSizePreview();
        
        // بستن modal
        bootstrap.Modal.getInstance(modal).hide();
    };
})();

// ═══════════════════════════════════════════════════════════════
// Tooltip
// ═══════════════════════════════════════════════════════════════
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});

// ═══════════════════════════════════════════════════════════════
// پیش‌نمایش اولیه
// ═══════════════════════════════════════════════════════════════
setTimeout(updateSizePreview, 100);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
