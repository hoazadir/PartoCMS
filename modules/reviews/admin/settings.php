<?php
/**
 * PartoCMS - Reviews Admin - Settings
 * تنظیمات ماژول نظرات
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/../includes/ReviewManager.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$manager = new ReviewManager($pdo);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'enabled'             => !empty($_POST['enabled']),
        'auto_approve'        => !empty($_POST['auto_approve']),
        'allow_guest'         => !empty($_POST['allow_guest']),
        'require_email'       => !empty($_POST['require_email']),
        'max_rating'          => min(10, max(1, (int) ($_POST['max_rating'] ?? 5))),
        'allow_helpful_votes' => !empty($_POST['allow_helpful_votes']),
        'items_per_page'      => min(100, max(1, (int) ($_POST['items_per_page'] ?? 10))),
        // 🆕 Display settings
        'enable_on_post'      => !empty($_POST['enable_on_post']),
        'enable_on_product'   => !empty($_POST['enable_on_product']),
        'enable_on_page'      => !empty($_POST['enable_on_page']),
        'display_position'    => in_array($_POST['display_position'] ?? 'after', ['before', 'after'], true)
                                  ? $_POST['display_position'] : 'after',
        'show_summary'        => !empty($_POST['show_summary']),
        'show_form'           => !empty($_POST['show_form']),
        'show_list'           => !empty($_POST['show_list']),
    ];

    if ($manager->updateSettings('global', $settings)) {
        $message = 'تنظیمات با موفقیت ذخیره شد';
    } else {
        $error = 'خطا در ذخیره تنظیمات';
    }
}

$settings = $manager->getSettings('global');

// ─── آمار ───
$stats = [
    'total'    => (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    'pending'  => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending'")->fetchColumn(),
    'approved' => (int) $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'approved'")->fetchColumn(),
    'entities' => (int) $pdo->query("SELECT COUNT(DISTINCT entity_type) FROM reviews")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات نظرات — PartoCMS</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    * { box-sizing: border-box; }
    body { background: #f1f5f9; font-family: Tahoma, 'Vazirmatn', sans-serif; margin: 0; padding: 25px; color: #1e293b; }
    .wrap { max-width: 850px; margin: 0 auto; }

    /* ─── Header ─── */
    .page-hdr {
        background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
        color: #fff;
        border-radius: 14px;
        padding: 25px 30px;
        margin-bottom: 25px;
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.25);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-hdr h1 { margin: 0 0 8px; font-size: 24px; font-weight: bold; }
    .page-hdr p { margin: 0; opacity: 0.9; font-size: 13.5px; }
    .hdr-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn-hdr {
        background: rgba(255,255,255,0.2);
        color: #fff;
        border: 2px solid rgba(255,255,255,0.4);
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        font-size: 13.5px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s;
    }
    .btn-hdr:hover { background: rgba(255,255,255,0.3); color: #fff; border-color: #fff; }

    /* ─── Stats ─── */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 16px 18px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        display: flex;
        align-items: center;
        gap: 12px;
        border-right: 4px solid #6366f1;
    }
    .stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: #eef2ff;
        color: #6366f1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        flex-shrink: 0;
    }
    .stat-info .num { font-size: 20px; font-weight: bold; color: #1e293b; line-height: 1.2; }
    .stat-info .lbl { font-size: 11.5px; color: #64748b; margin-top: 2px; }

    /* ─── Form Card ─── */
    .form-card {
        background: #fff;
        border-radius: 14px;
        padding: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .section {
        padding: 25px 30px;
        border-bottom: 1px solid #f1f5f9;
    }
    .section:last-child { border-bottom: none; }
    .section-title {
        font-size: 15px;
        font-weight: bold;
        color: #1e293b;
        margin: 0 0 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 12px;
        border-bottom: 2px solid #f1f5f9;
    }
    .section-title .icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #eef2ff;
        color: #6366f1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }

    /* ─── Setting Row ─── */
    .setting-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 0;
        border-bottom: 1px dashed #e2e8f0;
        gap: 20px;
    }
    .setting-row:last-child { border-bottom: none; padding-bottom: 0; }
    .setting-info { flex: 1; }
    .setting-info .label {
        font-weight: bold;
        color: #1e293b;
        font-size: 14px;
        display: block;
        margin-bottom: 4px;
    }
    .setting-info .hint {
        color: #64748b;
        font-size: 12px;
        line-height: 1.5;
    }

    /* ─── Toggle Switch ─── */
    .switch { position: relative; display: inline-block; width: 52px; height: 28px; flex-shrink: 0; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: #cbd5e1;
        border-radius: 28px;
        transition: 0.3s;
    }
    .slider:before {
        content: "";
        position: absolute;
        height: 20px;
        width: 20px;
        left: 4px;
        bottom: 4px;
        background: #fff;
        border-radius: 50%;
        transition: 0.3s;
        box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    input:checked + .slider { background: linear-gradient(135deg, #6366f1, #4f46e5); }
    input:checked + .slider:before { transform: translateX(24px); }

    /* ─── Number Input ─── */
    .input-num {
        width: 110px;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
        text-align: center;
        transition: all 0.2s;
        font-weight: bold;
    }
    .input-num:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    /* ─── Actions Bar ─── */
    .actions-bar {
        padding: 20px 30px;
        background: #f8fafc;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    .btn {
        padding: 12px 30px;
        border-radius: 8px;
        border: none;
        font-weight: bold;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-family: inherit;
        transition: all 0.2s;
        text-decoration: none;
    }
    .btn-primary {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #fff;
    }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4); color: #fff; }
    .btn-secondary {
        background: #e2e8f0;
        color: #334155;
    }
    .btn-secondary:hover { background: #cbd5e1; color: #1e293b; }
    .btn-danger {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .btn-danger:hover { background: #fecaca; color: #7f1d1d; }

    /* ─── Alert ─── */
    .alert {
        padding: 14px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideDown 0.3s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .alert-success { background: #dcfce7; color: #166534; border-right: 4px solid #16a34a; }
    .alert-error   { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }

    @media (max-width: 700px) {
        body { padding: 15px; }
        .page-hdr { padding: 20px; }
        .page-hdr h1 { font-size: 20px; }
        .setting-row { flex-direction: column; align-items: flex-start; gap: 12px; }
        .input-num { width: 100%; }
    }
</style>
</head>
<body>
<div class="wrap">

    <!-- ═══ Header ═══ -->
    <div class="page-hdr">
        <div>
            <h1>⚙️ تنظیمات نظرات</h1>
            <p>پیکربندی رفتار سیستم نظرات و امتیازدهی</p>
        </div>
        <div class="hdr-actions">
            <a href="index.php" class="btn-hdr"><i class="bi bi-list"></i> همه نظرات</a>
            <a href="pending.php" class="btn-hdr"><i class="bi bi-hourglass-split"></i> در انتظار</a>
        </div>
    </div>

    <!-- ═══ Messages ═══ -->
    <?php if ($message): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i>
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- ═══ Stats ═══ -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">💬</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['total']) ?></div>
                <div class="lbl">کل نظرات</div>
            </div>
        </div>
        <div class="stat-card" style="border-right-color: #f59e0b;">
            <div class="stat-icon" style="background: #fef3c7; color: #d97706;">⏳</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['pending']) ?></div>
                <div class="lbl">در انتظار</div>
            </div>
        </div>
        <div class="stat-card" style="border-right-color: #16a34a;">
            <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">✅</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['approved']) ?></div>
                <div class="lbl">تأییدشده</div>
            </div>
        </div>
        <div class="stat-card" style="border-right-color: #8b5cf6;">
            <div class="stat-icon" style="background: #f3e8ff; color: #8b5cf6;">📦</div>
            <div class="stat-info">
                <div class="num"><?= number_format($stats['entities']) ?></div>
                <div class="lbl">نوع محتوا</div>
            </div>
        </div>
    </div>

    <!-- ═══ Form ═══ -->
    <form method="post" class="form-card">

        <!-- بخش ۱: عمومی -->
        <div class="section">
            <h3 class="section-title">
                <span class="icon">🔧</span>
                تنظیمات عمومی
            </h3>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">فعال بودن سیستم نظرات</span>
                    <div class="hint">اگر غیرفعال باشد، هیچ نظری پذیرفته نمی‌شود و فرم نظرات پنهان می‌ماند.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="enabled" value="1" <?= !empty($settings['enabled']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">تأیید خودکار نظرات</span>
                    <div class="hint">نظرات جدید بدون نیاز به تأیید مدیر، فوراً منتشر می‌شوند. (توصیه نمی‌شود)</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="auto_approve" value="1" <?= !empty($settings['auto_approve']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- بخش ۲: کاربران -->
        <div class="section">
            <h3 class="section-title">
                <span class="icon">👥</span>
                دسترسی کاربران
            </h3>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">اجازه به کاربران مهمان</span>
                    <div class="hint">کاربران بدون ثبت‌نام می‌توانند نظر بدهند. اگر غیرفعال باشد، فقط اعضای لاگین‌شده می‌توانند نظر بدهند.</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="allow_guest" value="1" <?= !empty($settings['allow_guest']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">ایمیل الزامی</span>
                    <div class="hint">کاربر باید ایمیل معتبر وارد کند. (برای امنیت توصیه می‌شود)</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="require_email" value="1" <?= !empty($settings['require_email']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- بخش ۳: امتیازدهی -->
        <div class="section">
            <h3 class="section-title">
                <span class="icon">⭐</span>
                امتیازدهی
            </h3>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">حداکثر امتیاز</span>
                    <div class="hint">حداکثر امتیازی که کاربر می‌تواند بدهد (معمولاً ۵ ستاره).</div>
                </div>
                <input type="number" name="max_rating" value="<?= (int) $settings['max_rating'] ?>"
                       min="1" max="10" class="input-num">
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">رأی «مفید / غیرمفید»</span>
                    <div class="hint">کاربران می‌توانند به نظرات دیگران رأی بدهند. (بهترین نظرها بالاتر می‌آیند)</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="allow_helpful_votes" value="1" <?= !empty($settings['allow_helpful_votes']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- بخش ۴: نمایش -->
        <div class="section">
            <h3 class="section-title">
                <span class="icon">📄</span>
                نمایش
            </h3>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">فعال‌سازی خودکار در مقالات</span>
                    <div class="hint">نمایش خودکار بخش نظرات در تمام مقالات سایت</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="enable_on_post" value="1" <?= !empty($settings['enable_on_post']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">فعال‌سازی خودکار در محصولات</span>
                    <div class="hint">نمایش خودکار بخش نظرات در تمام محصولات فروشگاه</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="enable_on_product" value="1" <?= !empty($settings['enable_on_product']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">فعال‌سازی خودکار در صفحات</span>
                    <div class="hint">نمایش خودکار بخش نظرات در تمام صفحات سایت</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="enable_on_page" value="1" <?= !empty($settings['enable_on_page']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">موقعیت نمایش</span>
                    <div class="hint">بخش نظرات قبل یا بعد از محتوا نمایش داده شود</div>
                </div>
                <select name="display_position" class="input-num" style="padding:8px;border-radius:6px;border:1px solid #ddd;">
                    <option value="after"  <?= ($settings['display_position'] ?? 'after') === 'after'  ? 'selected' : '' ?>>بعد از محتوا</option>
                    <option value="before" <?= ($settings['display_position'] ?? 'after') === 'before' ? 'selected' : '' ?>>قبل از محتوا</option>
                </select>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">نمایش خلاصه امتیاز</span>
                    <div class="hint">نمایش میانگین امتیاز و تعداد نظرات</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="show_summary" value="1" <?= !empty($settings['show_summary']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">نمایش فرم ثبت نظر</span>
                    <div class="hint">نمایش فرم ثبت نظر جدید به کاربران</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="show_form" value="1" <?= !empty($settings['show_form']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">نمایش لیست نظرات</span>
                    <div class="hint">نمایش نظرات تأییدشده کاربران</div>
                </div>
                <label class="switch">
                    <input type="checkbox" name="show_list" value="1" <?= !empty($settings['show_list']) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="setting-row">
                <div class="setting-info">
                    <span class="label">تعداد نظرات در هر صفحه</span>
                    <div class="hint">چند نظر در هر صفحه نمایش داده شود؟ (بین ۱ تا ۱۰۰)</div>
                </div>
                <input type="number" name="items_per_page" value="<?= (int) $settings['items_per_page'] ?>"
                       min="1" max="100" class="input-num">
            </div>
        </div>

        <!-- ═══ Actions ═══ -->
        <div class="actions-bar">
            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-right"></i> انصراف
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> ذخیره تنظیمات
            </button>
        </div>

    </form>

</div>
</body>
</html>
