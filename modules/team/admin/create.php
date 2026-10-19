<?php
/**
 * PartoCMS — Team Admin
 * ایجاد عضو جدید تیم
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

/** @var \PartoCMS\Team\Domain\Service\TeamService $service */
$service = $GLOBALS['team_service'];

$form = [
    'name'        => '',
    'position'    => '',
    'department'  => '',
    'bio'         => '',
    'photo_url'   => '',
    'email'       => '',
    'phone'       => '',
    'linkedin'    => '',
    'twitter'     => '',
    'instagram'   => '',
    'status'      => 'published',
    'is_featured' => false,
    'sort_order'  => 0,
    'language'    => 'fa-IR',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!team_csrf_verify($_POST['_csrf'] ?? null)) {
        $errors[] = 'توکن امنیتی نامعتبر است';
    } else {
        $form['name']        = trim((string) ($_POST['name'] ?? ''));
        $form['position']    = trim((string) ($_POST['position'] ?? ''));
        $form['department']  = trim((string) ($_POST['department'] ?? ''));
        $form['bio']         = trim((string) ($_POST['bio'] ?? ''));
        $form['photo_url']   = trim((string) ($_POST['photo_url'] ?? ''));
        $form['email']       = trim((string) ($_POST['email'] ?? ''));
        $form['phone']       = trim((string) ($_POST['phone'] ?? ''));
        $form['linkedin']    = trim((string) ($_POST['linkedin'] ?? ''));
        $form['twitter']     = trim((string) ($_POST['twitter'] ?? ''));
        $form['instagram']   = trim((string) ($_POST['instagram'] ?? ''));
        $form['status']      = (string) ($_POST['status'] ?? 'published');
        $form['is_featured'] = !empty($_POST['is_featured']);
        $form['sort_order']  = (int) ($_POST['sort_order'] ?? 0);
        $form['language']    = (string) ($_POST['language'] ?? 'fa-IR');

        if ($form['name'] === '') $errors[] = 'نام الزامی است';
        if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ایمیل نامعتبر است';
        }
        if ($form['photo_url'] !== '' && !filter_var($form['photo_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URL عکس نامعتبر است';
        }

        if (empty($errors)) {
            try {
                $id = $service->createByAdmin([
                    'name'        => $form['name'],
                    'position'    => $form['position'] ?: null,
                    'department'  => $form['department'] ?: null,
                    'bio'         => $form['bio'] ?: null,
                    'photo_url'   => $form['photo_url'] ?: null,
                    'email'       => $form['email'] ?: null,
                    'phone'       => $form['phone'] ?: null,
                    'linkedin'    => $form['linkedin'] ?: null,
                    'twitter'     => $form['twitter'] ?: null,
                    'instagram'   => $form['instagram'] ?: null,
                    'sort_order'  => $form['sort_order'],
                    'language'    => $form['language'],
                    'is_featured' => $form['is_featured'],
                ], $form['status'] === 'published');

                team_redirect(team_admin_url('index.php'), 'success', "عضو تیم ایجاد شد (ID: $id)");
            } catch (\Throwable $e) {
                $errors[] = 'خطا: ' . $e->getMessage();
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
    <title>عضو جدید تیم | <?= t_esc($siteName) ?></title>
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
        .field input, .field select, .field textarea { width: 100%; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; font-family: inherit; transition: border-color .15s; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        .field textarea { min-height: 120px; resize: vertical; }
        .field small { display: block; margin-top: 4px; color: #64748b; font-size: 11px; }
        .checkbox-field { display: flex; align-items: center; gap: 8px; }
        .checkbox-field input { width: 18px; height: 18px; cursor: pointer; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-error { background: #fee2e2; color: #991b1b; border-right: 4px solid #dc2626; }
        .alert-error ul { margin: 0; padding-right: 20px; }
        .form-actions { display: flex; gap: 10px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; }
        .btn { padding: 11px 24px; border-radius: 8px; border: none; cursor: pointer; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; font-family: inherit; }
        .btn-primary { background: #6366f1; color: #fff; }
        .btn-primary:hover { background: #4f46e5; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h2>➕ عضو جدید تیم</h2>
        <a href="<?= t_esc(team_admin_url('index.php')) ?>" class="btn btn-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <strong>خطا:</strong>
            <ul><?php foreach ($errors as $e): ?><li><?= t_esc($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post" action="">
            <?= team_csrf_field() ?>

            <div class="form-grid">
                <div class="field">
                    <label>نام <span class="req">*</span></label>
                    <input type="text" name="name" value="<?= t_esc($form['name']) ?>" required maxlength="100">
                </div>
                <div class="field">
                    <label>سمت</label>
                    <input type="text" name="position" value="<?= t_esc($form['position']) ?>" maxlength="100" placeholder="مثل: مدیرعامل">
                </div>
                <div class="field">
                    <label>دپارتمان</label>
                    <input type="text" name="department" value="<?= t_esc($form['department']) ?>" maxlength="100">
                </div>
                <div class="field">
                    <label>ایمیل</label>
                    <input type="email" name="email" value="<?= t_esc($form['email']) ?>" maxlength="150">
                </div>
                <div class="field">
                    <label>تلفن</label>
                    <input type="text" name="phone" value="<?= t_esc($form['phone']) ?>" maxlength="50">
                </div>
                <div class="field">
                    <label>URL عکس</label>
                    <input type="url" name="photo_url" value="<?= t_esc($form['photo_url']) ?>" maxlength="255" placeholder="https://example.com/photo.jpg">
                </div>
                <div class="field full">
                    <label>بیوگرافی</label>
                    <textarea name="bio" maxlength="2000"><?= t_esc($form['bio']) ?></textarea>
                </div>
                <div class="field">
                    <label>LinkedIn</label>
                    <input type="url" name="linkedin" value="<?= t_esc($form['linkedin']) ?>" maxlength="255">
                </div>
                <div class="field">
                    <label>Twitter</label>
                    <input type="url" name="twitter" value="<?= t_esc($form['twitter']) ?>" maxlength="255">
                </div>
                <div class="field">
                    <label>Instagram</label>
                    <input type="url" name="instagram" value="<?= t_esc($form['instagram']) ?>" maxlength="255">
                </div>
                <div class="field">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="published" <?= $form['status'] === 'published' ? 'selected' : '' ?>>✅ منتشرشده</option>
                        <option value="hidden" <?= $form['status'] === 'hidden' ? 'selected' : '' ?>>🙈 مخفی</option>
                    </select>
                </div>
                <div class="field">
                    <label>ترتیب نمایش</label>
                    <input type="number" name="sort_order" value="<?= (int) $form['sort_order'] ?>" min="0">
                </div>
                <div class="field">
                    <label>زبان</label>
                    <select name="language">
                        <?php foreach (['fa-IR' => 'فارسی', 'en-US' => 'English', 'ar-SA' => 'العربية'] as $code => $label): ?>
                            <option value="<?= t_esc($code) ?>" <?= $form['language'] === $code ? 'selected' : '' ?>><?= t_esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field full">
                    <div class="checkbox-field">
                        <input type="checkbox" id="is_featured" name="is_featured" value="1" <?= $form['is_featured'] ? 'checked' : '' ?>>
                        <label for="is_featured" style="margin:0; cursor:pointer;">⭐ نمایش در بخش ویژه</label>
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> ایجاد</button>
                <a href="<?= t_esc(team_admin_url('index.php')) ?>" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
