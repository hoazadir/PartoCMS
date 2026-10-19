<?php
/**
 * PartoCMS — Testimonials Admin
 * ایجاد نظر جدید توسط ادمین
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
// Pre-fill values (برای فرم)
// ─────────────────────────────────────────────────────────────
$form = [
    'name'        => '',
    'email'       => '',
    'company'     => '',
    'position'    => '',
    'content'     => '',
    'rating'      => 5,
    'avatar_url'  => '',
    'language'    => 'fa-IR',
    'status'      => 'published',
    'is_featured' => false,
    'sort_order'  => 0,
];
$errors = [];

// ─────────────────────────────────────────────────────────────
// POST: submit
// ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!testimonials_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.';
    } else {
        // دریافت + sanitize (basic)
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
                $id = $service->createByAdmin([
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
                ], $form['status'] === 'published');

                testimonials_redirect(
                    testimonials_admin_url('index.php'),
                    'success',
                    "نظر با موفقیت ایجاد شد (ID: $id)"
                );
            } catch (\Throwable $e) {
                $errors[] = 'خطا در ایجاد نظر: ' . $e->getMessage();
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
    <title>نظر جدید | <?= t_esc($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h2 { margin: 0; font-size: 20px; color: #1e293b; }
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
        .field textarea { min-height: 140px; resize: vertical; }
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
        .form-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn { padding: 11px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn-secondary:hover { background: #cbd5e1; }
        @media (max-width: 1100px) {
            .main { margin-right: 0; padding: 70px 15px 15px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h2>➕ نظر جدید</h2>
        <a href="<?= t_esc(testimonials_admin_url('index.php')) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i> بازگشت به لیست
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
        <form method="post" action="">
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
                    <small>اختیاری — برای تأیید یا تماس</small>
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
                    <textarea id="content" name="content" required maxlength="5000" placeholder="نظر مشتری..."><?= t_esc($form['content']) ?></textarea>
                    <small>حداقل ۱۰ و حداکثر ۵۰۰۰ کاراکتر</small>
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
                    <small>۱ تا ۵ ستاره (اختیاری)</small>
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
                    <small>اختیاری — لینک تصویر</small>
                </div>

                <!-- وضعیت -->
                <div class="field">
                    <label for="status">وضعیت</label>
                    <select id="status" name="status">
                        <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>✅ منتشرشده</option>
                        <option value="pending" <?= $form['status'] === 'pending' ? 'selected' : '' ?>>⏳ در انتظار</option>
                    </select>
                </div>

                <!-- ترتیب -->
                <div class="field">
                    <label for="sort_order">ترتیب نمایش</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?= (int) $form['sort_order'] ?>" min="0">
                    <small>عدد کوچک‌تر = بالاتر</small>
                </div>

                <!-- ویژه -->
                <div class="field full">
                    <div class="checkbox-field">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?>>
                        <label for="is_featured">⭐ نمایش در بخش ویژه</label>
                    </div>
                </div>

            </div><!-- /.form-grid -->

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> ایجاد نظر
                </button>
                <a href="<?= t_esc(testimonials_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
            </div>

        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
