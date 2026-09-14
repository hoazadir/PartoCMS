<?php
require_once __DIR__ . '/config.php';

$slug = $_GET['slug'] ?? '';
if (!$slug) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM forms WHERE slug = ? AND is_active = 1");
$stmt->execute([$slug]);
$form = $stmt->fetch();

if (!$form) {
    http_response_code(404);
    die('فرم یافت نشد');
}

$fields = json_decode($form['fields'] ?? '[]', true) ?: [];
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($form['name']) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .top-bar { background: #2c3e50; color: #fff; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .top-bar .logo { color: #fff; text-decoration: none; font-size: 16px; font-weight: bold; }
        .top-bar a { color: #ecf0f1; text-decoration: none; margin-right: 15px; font-size: 13px; }
        .form-container { max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); }
        .form-container h1 { color: #2c3e50; margin: 0 0 10px; font-size: 26px; }
        .form-container .desc { color: #7f8c8d; margin-bottom: 25px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: #34495e; font-size: 14px; font-weight: bold; }
        .form-group label .required { color: #e74c3c; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%; padding: 12px 15px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-family: Tahoma; font-size: 14px;
            box-sizing: border-box; transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            border-color: #3498db; outline: none;
        }
        .form-group textarea { resize: vertical; min-height: 100px; }
        .checkbox-group label, .radio-group label {
            display: flex; align-items: center; gap: 8px;
            font-weight: normal; padding: 8px; cursor: pointer;
            border-radius: 6px; transition: background 0.2s;
        }
        .checkbox-group label:hover, .radio-group label:hover { background: #f8f9fa; }
        .btn-submit { background: linear-gradient(135deg, #3498db, #2980b9); color: #fff; border: none; padding: 14px 30px; border-radius: 8px; font-size: 15px; font-family: Tahoma; font-weight: bold; cursor: pointer; width: 100%; transition: all 0.2s; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4); }
        .alert-success { background: #d4edda; color: #155724; padding: 20px; border-radius: 10px; text-align: center; font-size: 15px; }
        .alert-error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
    
    /* ==================== DIRECTION SUPPORT ==================== */
    /* RTL پیش‌فرض — Bootstrap RTL خودش کار می‌کند */

    /* LTR — Bootstrap LTR خودش کار می‌کند */

    /* اصلاحات اضافی برای عناصر خاص */
    html[dir="ltr"] body { direction: ltr; text-align: left; }
    html[dir="rtl"] body { direction: rtl; text-align: right; }

    /* منوی اصلی */
    html[dir="ltr"] .main-nav { flex-direction: row; }
    html[dir="rtl"] .main-nav { flex-direction: row-reverse; }

    /* زیرمنو در LTR */
    html[dir="ltr"] .submenu { right: auto; left: 100%; }

    /* pagination */
    html[dir="ltr"] .article .content table th { text-align: left; }
    html[dir="rtl"] .article .content table th { text-align: right; }

    /* blockquote */
    html[dir="ltr"] .article .content blockquote { border-right: none; border-left: 4px solid #3498db; border-radius: 8px 0 0 8px; }

</style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" class="logo">🏠 <?= htmlspecialchars($siteName) ?></a>
    <div>
        <a href="index.php"><?= __t('fe_home', [], 'خانه') ?></a>
        <?php if (isLoggedIn()): ?>
            <a href="user/index.php"><?= __t('fe_user_panel', [], 'پنل کاربری') ?></a>
            <a href="logout.php"><?= __t('fe_logout', [], 'خروج') ?></a>
        <?php else: ?>
            <a href="login.php"><?= __t('fe_login', [], 'ورود') ?></a>
            <a href="register.php"><?= __t('fe_register', [], 'ثبت‌نام') ?></a>
        <?php endif; ?>
    </div>
</div>

<div class="form-container">
    <h1>📋 <?= htmlspecialchars($form['name']) ?></h1>
    <?php if ($form['description']): ?>
        <p class="desc"><?= htmlspecialchars($form['description']) ?></p>
    <?php endif; ?>

    <div id="formMsg"></div>

    <form id="dynamicForm" <?= in_array('file', array_column($fields, 'type')) ? 'enctype="multipart/form-data"' : '' ?>>
        <input type="hidden" name="form_id" value="<?= $form['id'] ?>">

        <?php foreach ($fields as $f):
            $name = htmlspecialchars($f['name']);
            $label = htmlspecialchars($f['label'] ?? $f['name']);
            $req = !empty($f['required']) ? 'required' : '';
            $reqStar = !empty($f['required']) ? '<span class="required">*</span>' : '';
            $ph = htmlspecialchars($f['placeholder'] ?? '');
        ?>
            <div class="form-group">
                <?php if (!in_array($f['type'], ['checkbox', 'radio'])): ?>
                    <label for="<?= $name ?>"><?= $label ?> <?= $reqStar ?></label>
                <?php endif; ?>

                <?php if ($f['type'] === 'text'): ?>
                    <input type="text" name="<?= $name ?>" placeholder="<?= $ph ?>" <?= $req ?>>

                <?php elseif ($f['type'] === 'email'): ?>
                    <input type="email" name="<?= $name ?>" placeholder="<?= $ph ?>" <?= $req ?>>

                <?php elseif ($f['type'] === 'tel'): ?>
                    <input type="tel" name="<?= $name ?>" placeholder="<?= $ph ?>" <?= $req ?>>

                <?php elseif ($f['type'] === 'number'): ?>
                    <input type="number" name="<?= $name ?>" placeholder="<?= $ph ?>" <?= $req ?>>

                <?php elseif ($f['type'] === 'date'): ?>
                    <input type="date" name="<?= $name ?>" <?= $req ?>>

                <?php elseif ($f['type'] === 'textarea'): ?>
                    <textarea name="<?= $name ?>" placeholder="<?= $ph ?>" rows="<?= $f['rows'] ?? 5 ?>" <?= $req ?>></textarea>

                <?php elseif ($f['type'] === 'select'): ?>
                    <select name="<?= $name ?>" <?= $req ?>>
                        <option value="">— انتخاب کنید —</option>
                        <?php foreach ($f['options'] ?? [] as $opt): ?>
                            <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($f['type'] === 'radio'): ?>
                    <label><?= $label ?> <?= $reqStar ?></label>
                    <div class="radio-group">
                        <?php foreach ($f['options'] ?? [] as $i => $opt): ?>
                            <label>
                                <input type="radio" name="<?= $name ?>" value="<?= htmlspecialchars($opt) ?>" <?= $req && $i === 0 ? 'required' : '' ?>>
                                <?= htmlspecialchars($opt) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>

                <?php elseif ($f['type'] === 'checkbox'): ?>
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="<?= $name ?>" value="1" <?= $req ?>>
                            <?= $label ?> <?= $reqStar ?>
                        </label>
                    </div>

                <?php elseif ($f['type'] === 'file'): ?>
                    <input type="file" name="<?= $name ?>" <?= $req ?>>

                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn-submit" id="submitBtn">
            <i class="bi bi-send"></i> <?= htmlspecialchars($form['submit_label'] ?: 'ارسال') ?>
        </button>
    </form>
</div>

<script>
document.getElementById('dynamicForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    const msg = document.getElementById('formMsg');
    const formData = new FormData(this);
    const data = {};

    formData.forEach((value, key) => {
        if (key !== 'form_id') data[key] = value;
    });

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> در حال ارسال...';
    msg.innerHTML = '';

    try {
        const res = await fetch('api/form-submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                form_id: formData.get('form_id'),
                data: data
            })
        });
        const result = await res.json();

        if (result.success) {
            document.getElementById('dynamicForm').innerHTML = 
                '<div class="alert-success">' + result.message + '</div>';
        } else {
            msg.innerHTML = '<div class="alert-error">❌ ' + (result.error || 'خطا در ارسال') + '</div>';
        }
    } catch (err) {
        msg.innerHTML = '<div class="alert-error">❌ خطا در ارتباط با سرور</div>';
    }

    btn.disabled = false;
    btn.innerHTML = '<i class="bi bi-send"></i> <?= htmlspecialchars($form['submit_label'] ?: 'ارسال') ?>';
});
</script>

</body>
</html>
