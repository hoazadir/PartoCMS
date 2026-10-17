<?php
/**
 * PartoCMS - FAQ Admin
 * فرم افزودن / ویرایش سوال متداول
 */

require_once __DIR__ . '/_bootstrap.php';

/** @var FaqService $faq_service */
$faq_service = $GLOBALS['faq_service'];

$id    = (int) ($_GET['id'] ?? 0);
$isNew = ($id === 0);
$item  = null;
$error = '';

// اگر ویرایش است، آیتم را واکشی کن
if (!$isNew) {
    try {
        $item = $faq_service->getById($id);
    } catch (Throwable $e) {
        faq_flash($e->getMessage(), 'error');
        header('Location: ' . faq_url('index.php'));
        exit;
    }
}

// پردازش فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question  = trim($_POST['question'] ?? '');
    $answer    = trim($_POST['answer'] ?? '');
    $category  = trim($_POST['category'] ?? '');
    $category  = $category !== '' ? $category : null;
    $sortOrder = (int) ($_POST['sort_order'] ?? 0);
    $isActive  = isset($_POST['is_active']);

    if ($question === '' || $answer === '') {
        $error = 'سوال و پاسخ نمی‌توانند خالی باشند.';
    } else {
        try {
            if ($isNew) {
                $faq_service->create($question, $answer, $category, $sortOrder, $isActive);
                faq_flash('سوال جدید با موفقیت ایجاد شد.');
            } else {
                $faq_service->update($id, $question, $answer, $category, $sortOrder);
                // در صورت نیاز به تغییر وضعیت در فرم ویرایش
                if ($isActive !== $item->isActive()) {
                    $faq_service->toggleStatus($id);
                }
                faq_flash('سوال با موفقیت ویرایش شد.');
            }
            header('Location: ' . faq_url('index.php'));
            exit;
        } catch (Throwable $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    }
}

// مقادیر پیش‌فرض برای فرم
$valQuestion  = $_POST['question'] ?? ($item ? $item->question() : '');
$valAnswer    = $_POST['answer'] ?? ($item ? $item->answer() : '');
$valCategory  = $_POST['category'] ?? ($item ? ($item->category() ?? '') : '');
$valSortOrder = $_POST['sort_order'] ?? ($item ? $item->sortOrder() : 0);
$valIsActive  = isset($_POST['is_active']) || ($item ? $item->isActive() : true);
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isNew ? 'سوال جدید' : 'ویرایش سوال' ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .form-label { font-weight: bold; color: #334155; font-size: 14px; display: block; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box; margin-bottom: 20px; }
        .form-control:focus { border-color: #6366f1; outline: none; }
        textarea.form-control { min-height: 150px; resize: vertical; }
        .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .alert-error { background: #fee2e2; color: #991b1b; padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .row { display: flex; gap: 20px; margin-bottom: 20px; }
        .col { flex: 1; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .row { flex-direction: column; gap: 0; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <div>
            <a href="<?= faq_url('index.php') ?>" style="text-decoration:none; color:#6366f1;">
                <i class="bi bi-arrow-right"></i> بازگشت به لیست
            </a>
            <h4 style="margin:5px 0 0;"><?= $isNew ? '➕ سوال جدید' : '✏️ ویرایش سوال #' . $id ?></h4>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <label class="form-label">متن سوال *</label>
            <input type="text" name="question" class="form-control" value="<?= htmlspecialchars($valQuestion) ?>" required placeholder="مثلاً: چگونه می‌توانم سفارشم را پیگیری کنم؟">

            <label class="form-label">پاسخ کامل *</label>
            <textarea name="answer" id="faq-answer" class="form-control" placeholder="پاسخ کامل خود را اینجا بنویسید..."><?= htmlspecialchars($valAnswer) ?></textarea>

            <div class="row">
                <div class="col">
                    <label class="form-label">دسته‌بندی (اختیاری)</label>
                    <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($valCategory) ?>" placeholder="مثلاً: پشتیبانی، پرداخت، حساب کاربری">
                </div>
                <div class="col">
                    <label class="form-label">ترتیب نمایش (عدد کمتر = بالاتر)</label>
                    <input type="number" name="sort_order" class="form-control" value="<?= $valSortOrder ?>" min="0">
                </div>
            </div>

            <div style="margin-bottom: 25px;">
                <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" <?= $valIsActive ? 'checked' : '' ?> style="width:20px; height:20px;">
                    <span style="font-weight:bold; color:#334155; font-size:14px;">این سوال فعال باشد (در سایت نمایش داده شود)</span>
                </label>
            </div>

            <div style="display:flex; gap:12px;">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle"></i> <?= $isNew ? 'ایجاد سوال' : 'ذخیره تغییرات' ?>
                </button>
                <a href="<?= faq_url('index.php') ?>" class="btn btn-secondary">انصراف</a>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#faq-answer',
    language: 'fa',
    directionality: 'rtl',
    height: 350,
    menubar: false,
    plugins: 'advlist autolink lists link charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime table help wordcount',
    toolbar: 'undo redo | blocks | bold italic underline strikethrough | forecolor backcolor | alignright aligncenter alignleft alignjustify | bullist numlist outdent indent | link | code | removeformat',
    content_style: 'body { font-family: Tahoma, Arial, sans-serif; font-size: 14px; line-height: 1.8; }'
});
</script>
</body>
</html>
