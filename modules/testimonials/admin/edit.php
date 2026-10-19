<?php
/**
 * PartoCMS — Testimonials Admin
 * ویرایش نظر موجود
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/** @var \PartoCMS\Testimonials\Domain\Service\TestimonialService $service */
$service = $GLOBALS['testimonials_service'];

// ─────────────────────────────────────────────────────────────
// دریافت ID
// ─────────────────────────────────────────────────────────────
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    testimonials_redirect(testimonials_admin_url('index.php'), 'error', 'شناسه نظر نامعتبر است');
}

// ─────────────────────────────────────────────────────────────
// دریافت Entity
// ─────────────────────────────────────────────────────────────
try {
    $testimonial = $service->getByIdOrFail($id);
} catch (\Throwable $e) {
    testimonials_redirect(testimonials_admin_url('index.php'), 'error', 'نظر یافت نشد: ' . $e->getMessage());
}

// ─────────────────────────────────────────────────────────────
// Pre-fill از Entity
// ─────────────────────────────────────────────────────────────
$form = [
    'name'        => $testimonial->getName(),
    'email'       => $testimonial->getEmail() ?? '',
    'company'     => $testimonial->getCompany() ?? '',
    'position'    => $testimonial->getPosition() ?? '',
    'content'     => $testimonial->getContent(),
    'rating'      => $testimonial->getRating() ?? 5,
    'avatar_url'  => $testimonial->getAvatarUrl() ?? '',
    'language'    => $testimonial->getLanguage(),
    'status'      => $testimonial->getStatus(),
    'is_featured' => $testimonial->isFeatured(),
    'sort_order'  => $testimonial->getSortOrder(),
];
$errors = [];

// ─────────────────────────────────────────────────────────────
// POST: submit
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!testimonials_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.';
    } else {
        $form['name']        = trim((string) ($_POST['name'] ?? ''));
        $form['email']       = trim((string) ($_POST['email'] ?? ''));
        $form['company']     = trim((string) ($_POST['company'] ?? ''));
        $form['position']    = trim((string) ($_POST['position'] ?? ''));
        $form['content']     = trim((string) ($_POST['content'] ?? ''));
        $form['rating']      = (int) ($_POST['rating'] ?? 0) ?: null;
        $form['avatar_url']  = trim((string) ($_POST['avatar_url'] ?? ''));
        $form['language']    = (string) ($_POST['language'] ?? 'fa-IR');
        $form['status']      = (string) ($_POST['status'] ?? 'published');
        $form['is_featured'] = !empty($_POST['is_featured']);
        $form['sort_order']  = (int) ($_POST['sort_order'] ?? 0);

        if ($form['name'] === '')        $errors[] = 'نام الزامی است';
        if ($form['content'] === '')     $errors[] = 'متن نظر الزامی است';
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ایمیل نامعتبر است';
        }
        if ($form['avatar_url'] !== '' && !filter_var($form['avatar_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URL آواتار نامعتبر است';
        }

        if (empty($errors)) {
            try {
                $service->updateByAdmin($id, [
                    'name'        => $form['name'],
                    'email'       => $form['email'] ?: null,
                    'company'     => $form['company'] ?: null,
                    'position'    => $form['position'] ?: null,
                    'content'     => $form['content'],
                    'rating'      => $form['rating'],
                    'avatar_url'  => $form['avatar_url'] ?: null,
                    'language'    => $form['language'],
                    'is_featured' => $form['is_featured'],
                    'sort_order'  => $form['sort_order'],
                    'status'      => $form['status'],
                ]);

                testimonials_redirect(
                    testimonials_admin_url('index.php'),
                    'success',
                    'نظر با موفقیت به‌روزرسانی شد'
                );
            } catch (\Throwable $e) {
                $errors[] = 'خطا در به‌روزرسانی: ' . $e->getMessage();
            }
        }
    }
}

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش نظر #<?= (int) $id ?> | <?= t_esc($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h2 { margin: 0; font-size: 20px; color: #1e293b; }
        .top-bar .meta { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; font-size: 12px; color: #64748b; }
        .top-bar .meta .badge { font-size: 11px; padding: 5px 10px; border-radius: 6px; }
        .card { background: #fff; border-radius: 10px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-grid .full { grid-column: 1 / -1; }
        .field label { display: block; font-size: 13px; color: #334155; font-weight: 600; margin-bottom: 6px; }
        .field label .req { color: #dc2626; }
        .field input[type=text], .field input[type=email], .field input[type=url], .field input[type=number],
        .field select, .field textarea {
            width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1;
            border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color .15s;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1);
        }
        .field textarea { min-height: 160px; resize: vertical; }
        .field small { display: block; margin-top: 4px; color: #64748b; font-size: 11px; }
        .rating-stars { display: flex; gap: 5px; align-items: center; }
        .rating-stars input { display: none; }
        .rating-stars label { font-size: 26px; color: #cbd5e1; cursor: pointer; transition: color .1s; margin: 0; }
        .rating-stars label:hover, .rating-stars label:hover ~ label,
        .rating-stars input:checked ~ label { color: #fbbf24; }
        .rating-stars input:checked + label { color: #fbbf24; }
        .rating-stars.reverse { flex-direction: row-reverse; justify-content: flex-end; }
        .checkbox-field { display: flex; align-items: center; gap: 8px; }
        .checkbox-field input { width: 18px; height: 18px; cursor: pointer; }
        .checkbox-field label { margin: 0; cursor: pointer; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }
        .alert-error ul { margin: 0; padding-right: 20px; }
        .form-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; justify-content: space-between; flex-wrap: wrap; }
        .form-actions .left, .form-actions .right { display: flex; gap: 10px; }
        .btn { padding: 11px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-danger:hover { background: #fecaca; }
        .info-box { background: #f1f5f9; padding: 12px 16px; border-radius: 8px; font-size: 12px; color: #475569; margin-bottom: 20px; border-right: 3px solid #6366f1; }
        .info-box strong { color: #1e293b; }
        @media (max-width: 1100px) {
            .main { margin-right: 0; padding: 70px 15px 15px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <div>
            <h2>✏️ ویرایش نظر #<?= (int) $id ?></h2>
            <div class="meta">
                <span>ایجاد: <?= t_esc(substr($testimonial->getCreatedAt() ?? '', 0, 16)) ?></span>
                <?php if ($testimonial->getUpdatedAt() && $testimonial->getUpdatedAt() !== $testimonial->getCreatedAt()): ?>
                    <span>آخرین ویرایش: <?= t_esc(substr($testimonial->getUpdatedAt(), 0, 16)) ?></span>
                <?php endif; ?>
                <?= testimonials_status_badge($testimonial->getStatus()) ?>
                <?php if ($testimonial->isFeatured()): ?>
                    <span class="badge bg-warning text-dark">⭐ ویژه</span>
                <?php endif; ?>
            </div>
        </div>
        <a href="<?= t_esc(testimonials_admin_url('index.php')) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>خطا:</strong>
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= t_esc($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">

        <?php if ($testimonial->getSubmittedIp()): ?>
            <div class="info-box">
                <strong>IP ثبت‌کننده:</strong> <?= t_esc($testimonial->getSubmittedIp()) ?>
                — <strong>زبان:</strong> <?= t_esc($testimonial->getLanguage()) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="" id="edit-form">
            <?= testimonials_csrf_field() ?>

            <div class="form-grid">

                <!-- نام -->
                <div class="field">
                    <label for="name">نام <span class="req">*</span></label>
                    <input type="text" id="name" name="name" value="<?= t_esc($form['name']) ?>" required maxlength="100">
                </div>

                <!-- ایمیل -->
                <div class="field">
                    <label for="email">ایمیل</label>
                    <input type="email" id="email" name="email" value="<?= t_esc($form['email']) ?>" maxlength="150">
                </div>

                <!-- شرکت -->
                <div class="field">
                    <label for="company">شرکت</label>
                    <input type="text" id="company" name="company" value="<?= t_esc($form['company']) ?>" maxlength="100">
                </div>

                <!-- سمت -->
                <div class="field">
                    <label for="position">سمت</label>
                    <input type="text" id="position" name="position" value="<?= t_esc($form['position']) ?>" maxlength="100" placeholder="مثل: مدیرعامل">
                </div>

                <!-- متن نظر -->
                <div class="field full">
                    <label for="content">متن نظر <span class="req">*</span></label>
                    <textarea id="content" name="content" required maxlength="5000"><?= t_esc($form['content']) ?></textarea>
                    <small><span id="char-count">۰</span> / ۵۰۰۰ کاراکتر</small>
                </div>

                <!-- امتیاز -->
                <div class="field">
                    <label>امتیاز</label>
                    <div class="rating-stars reverse">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" id="star-<?= $i ?>" name="rating" value="<?= $i ?>" <?= (int) $form['rating'] === $i ? 'checked' : '' ?>>
                            <label for="star-<?= $i ?>" title="<?= $i ?> ستاره">★</label>
                        <?php endfor; ?>
                    </div>
                    <small>۱ تا ۵ ستاره</small>
                </div>

                <!-- زبان -->
                <div class="field">
                    <label for="language">زبان</label>
                    <select id="language" name="language">
                        <?php foreach (['fa-IR' => 'فارسی', 'en-US' => 'English', 'ar-SA' => 'العربية'] as $code => $label): ?>
                            <option value="<?= t_esc($code) ?>" <?= $form['language'] === $code ? 'selected' : '' ?>><?= t_esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- آواتار -->
                <div class="field full">
                    <label for="avatar_url">URL آواتار</label>
                    <input type="url" id="avatar_url" name="avatar_url" value="<?= t_esc($form['avatar_url']) ?>" maxlength="255" placeholder="https://example.com/avatar.jpg">
                </div>

                <!-- وضعیت -->
                <div class="field">
                    <label for="status">وضعیت</label>
                    <select id="status" name="status">
                        <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>✅ منتشرشده</option>
                        <option value="pending" <?= $form['status'] === 'pending' ? 'selected' : '' ?>>⏳ در انتظار</option>
                        <option value="rejected" <?= $form['status'] === 'rejected' ? 'selected' : '' ?>>❌ رد شده</option>
                    </select>
                </div>

                <!-- ترتیب -->
                <div class="field">
                    <label for="sort_order">ترتیب نمایش</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int) $form['sort_order'] ?>" min="0">
                </div>

                <!-- ویژه -->
                <div class="field full">
                    <div class="checkbox-field">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?>>
                        <label for="is_featured">⭐ نمایش در بخش ویژه</label>
                    </div>
                </div>

            </div>

            <div class="form-actions">
                <div class="left">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> ذخیره تغییرات
                    </button>
                    <a href="<?= t_esc(testimonials_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
                </div>
                <div class="right">
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="bi bi-trash"></i> حذف
                    </button>
                </div>
            </div>

        </form>

        <!-- فرم مخفی برای حذف -->
        <form method="post" action="<?= t_esc(testimonials_admin_url('index.php')) ?>" id="delete-form" style="display:none;">
            <?= testimonials_csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
        </form>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Char count
const contentField = document.getElementById('content');
const charCount = document.getElementById('char-count');

function updateCount() {
    const len = contentField.value.length;
    charCount.textContent = len.toLocaleString('fa-IR');
    charCount.style.color = len > 4500 ? '#dc2626' : '#64748b';
}

contentField.addEventListener('input', updateCount);
updateCount();

// Delete confirmation
function confirmDelete() {
    if (confirm('آیا از حذف این نظر مطمئن هستید؟ این عملیات قابل بازگشت نیست.')) {
        document.getElementById('delete-form').submit();
    }
}
</script>
</body>
</html>
