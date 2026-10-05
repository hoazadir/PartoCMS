<?php
/**
 * 🆕 PartoCMS Smart Installer — Step 2: Database
 *
 * صفحه دوم: دریافت و تست اطلاعات دیتابیس
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-05
 */

session_start();

// بررسی اینکه مرحله ۱ انجام شده
if (empty($_SESSION['installer_step1_passed'])) {
    // اگر قبلاً چک نشده، به مرحله ۱ برگرد
    // (ولی اجازه می‌دهیم کاربر بدون چک هم وارد شود)
}

// ─── پردازش فرم ───
$errors = [];
$success = '';
$testResult = null;

$defaults = [
    'host'    => $_SESSION['installer_db']['host']    ?? '127.0.0.1',
    'port'    => $_SESSION['installer_db']['port']    ?? '3306',
    'name'    => $_SESSION['installer_db']['name']    ?? 'partocms',
    'user'    => $_SESSION['installer_db']['user']    ?? 'root',
    'pass'    => $_SESSION['installer_db']['pass']    ?? '',
    'charset' => $_SESSION['installer_db']['charset'] ?? 'utf8mb4',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host    = trim($_POST['db_host']    ?? '');
    $port    = trim($_POST['db_port']    ?? '3306');
    $name    = trim($_POST['db_name']    ?? '');
    $user    = trim($_POST['db_user']    ?? '');
    $pass    = $_POST['db_pass']         ?? '';
    $charset = trim($_POST['db_charset'] ?? 'utf8mb4');

    $defaults = compact('host', 'port', 'name', 'user', 'pass', 'charset');

    // ─── اعتبارسنجی ───
    if ($host === '')    $errors[] = 'نام هاست الزامی است';
    if ($name === '')    $errors[] = 'نام دیتابیس الزامی است';
    if ($user === '')    $errors[] = 'نام کاربری الزامی است';
    if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
        $errors[] = 'نام دیتابیس فقط می‌تواند شامل حروف، اعداد، _ و - باشد';
    }
    if ($port !== '' && (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535)) {
        $errors[] = 'شماره پورت نامعتبر است';
    }

    // ─── تست اتصال ───
    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$host};port={$port};charset={$charset}";

            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT            => 5,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // بررسی نسخه MySQL/MariaDB
            $version = $pdo->query('SELECT VERSION()')->fetchColumn();

            // بررسی وجود دیتابیس
            $stmt = $pdo->prepare("
                SELECT SCHEMA_NAME
                FROM INFORMATION_SCHEMA.SCHEMATA
                WHERE SCHEMA_NAME = ?
            ");
            $stmt->execute([$name]);
            $dbExists = (bool) $stmt->fetchColumn();

            // بررسی تعداد جداول اگر DB وجود دارد
            $tableCount = 0;
            if ($dbExists) {
                try {
                    $pdo->exec("USE `{$name}`");
                    $tableCount = count($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
                } catch (Throwable $e) {
                    // ignore
                }
            }

            // ذخیره در session
            $_SESSION['installer_db'] = [
                'host'    => $host,
                'port'    => $port,
                'name'    => $name,
                'user'    => $user,
                'pass'    => $pass,
                'charset' => $charset,
            ];

            $testResult = [
                'version'    => $version,
                'db_exists'  => $dbExists,
                'table_count' => $tableCount,
            ];

            $success = 'اتصال با موفقیت برقرار شد!';

        } catch (PDOException $e) {
            $msg = $e->getMessage();

            // پیام‌های دوستانه‌تر
            if (strpos($msg, 'Access denied') !== false) {
                $errors[] = 'نام کاربری یا رمز عبور اشتباه است';
            } elseif (strpos($msg, 'Unknown database') !== false) {
                $errors[] = 'دیتابیس پیدا نشد (ولی می‌توانیم بسازیمش)';
            } elseif (strpos($msg, 'Connection refused') !== false) {
                $errors[] = 'اتصال به سرور MySQL ممکن نیست — MySQL را چک کنید';
            } elseif (strpos($msg, 'getaddrinfo') !== false || strpos($msg, 'No such host') !== false) {
                $errors[] = 'هاست نامعتبر است';
            } else {
                $errors[] = 'خطا: ' . $msg;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>نصب PartoCMS — مرحله ۲: دیتابیس</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
    body {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        font-family: Tahoma, 'Vazirmatn', sans-serif;
        padding: 20px;
    }
    .installer-container { max-width: 900px; margin: 0 auto; }
    .installer-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        overflow: hidden;
    }
    .installer-header {
        background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
        color: #fff;
        padding: 30px;
        text-align: center;
    }
    .installer-header h1 { margin: 0 0 10px; font-size: 26px; font-weight: bold; }
    .installer-header p { margin: 0; opacity: 0.9; font-size: 14px; }

    .stepper {
        display: flex;
        justify-content: space-between;
        padding: 20px 30px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        overflow-x: auto;
    }
    .step { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 80px; position: relative; opacity: 0.5; }
    .step.completed { opacity: 1; }
    .step.active { opacity: 1; }
    .step .circle {
        width: 36px; height: 36px; border-radius: 50%;
        background: #cbd5e1; color: #fff;
        display: flex; align-items: center; justify-content: center;
        font-weight: bold; margin-bottom: 8px; font-size: 14px;
    }
    .step.completed .circle { background: #10b981; }
    .step.active .circle { background: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2); }
    .step .label { font-size: 11px; color: #64748b; font-weight: bold; text-align: center; }
    .step.active .label { color: #3b82f6; }

    .installer-body { padding: 30px; }

    .alert {
        padding: 12px 18px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 13.5px;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }
    .alert-error {
        background: #fee2e2;
        border-right: 4px solid #dc2626;
        color: #991b1b;
    }
    .alert-success {
        background: #dcfce7;
        border-right: 4px solid #16a34a;
        color: #166534;
    }
    .alert-info {
        background: #dbeafe;
        border-right: 4px solid #3b82f6;
        color: #1e40af;
    }
    .alert ul { margin: 0; padding-right: 20px; }

    .form-group { margin-bottom: 18px; }
    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 6px;
        color: #1e293b;
        font-size: 13.5px;
    }
    .form-group label .required { color: #dc2626; }
    .form-group .hint {
        font-size: 11.5px;
        color: #64748b;
        margin-top: 4px;
        display: block;
    }
    .form-control {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.2s;
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .form-control[dir="ltr"] {
        direction: ltr;
        font-family: monospace;
    }

    .form-row {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 15px;
    }

    .test-result {
        background: #f0fdf4;
        border: 2px solid #86efac;
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    .test-result h4 {
        margin: 0 0 10px;
        color: #166534;
        font-size: 14px;
    }
    .test-result .info-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        border-bottom: 1px dashed #bbf7d0;
        font-size: 13px;
    }
    .test-result .info-row:last-child { border-bottom: none; }
    .test-result .info-row .label { color: #166534; }
    .test-result .info-row .value { font-weight: bold; color: #14532d; font-family: monospace; }

    .actions {
        padding: 20px 30px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .btn-primary-custom {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        color: #fff;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: bold;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
        cursor: pointer;
        font-family: inherit;
    }
    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(59,130,246,0.4);
        color: #fff;
    }
    .btn-primary-custom.disabled {
        background: #cbd5e1;
        cursor: not-allowed;
        pointer-events: none;
    }
    .btn-secondary-custom {
        background: #e2e8f0;
        color: #334155;
        padding: 12px 24px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .btn-secondary-custom:hover { background: #cbd5e1; color: #1e293b; }

    .brand-footer { text-align: center; margin-top: 20px; color: rgba(255,255,255,0.8); font-size: 12px; }

    @media (max-width: 700px) {
        .form-row { grid-template-columns: 1fr; }
        .installer-header h1 { font-size: 20px; }
        .installer-body { padding: 20px; }
    }
</style>
</head>
<body>

<div class="installer-container">
    <div class="installer-card">

        <!-- Header -->
        <div class="installer-header">
            <h1>🚀 نصب PartoCMS</h1>
            <p>مرحله ۲ از ۵: اطلاعات دیتابیس</p>
        </div>

        <!-- Stepper -->
        <div class="stepper">
            <div class="step completed">
                <div class="circle">✓</div>
                <div class="label">پیش‌نیازها</div>
            </div>
            <div class="step active">
                <div class="circle">۲</div>
                <div class="label">دیتابیس</div>
            </div>
            <div class="step">
                <div class="circle">۳</div>
                <div class="label">ادمین</div>
            </div>
            <div class="step">
                <div class="circle">۴</div>
                <div class="label">نوع سایت</div>
            </div>
            <div class="step">
                <div class="circle">۵</div>
                <div class="label">نصب</div>
            </div>
        </div>

        <!-- Body -->
        <div class="installer-body">

            <!-- Errors -->
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <span>❌</span>
                    <div>
                        <strong>خطا در اتصال:</strong>
                        <ul>
                            <?php foreach ($errors as $err): ?>
                                <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success -->
            <?php if ($success && $testResult): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <div><strong><?= htmlspecialchars($success) ?></strong></div>
                </div>

                <div class="test-result">
                    <h4>📊 اطلاعات اتصال</h4>
                    <div class="info-row">
                        <span class="label">نسخه MySQL/MariaDB:</span>
                        <span class="value"><?= htmlspecialchars($testResult['version']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="label">وضعیت دیتابیس:</span>
                        <span class="value">
                            <?= $testResult['db_exists'] ? '✅ موجود' : '📝 ساخته می‌شود' ?>
                        </span>
                    </div>
                    <?php if ($testResult['db_exists']): ?>
                    <div class="info-row">
                        <span class="label">تعداد جداول موجود:</span>
                        <span class="value"><?= $testResult['table_count'] ?> جدول</span>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <form method="post">
                <h3 style="margin-top: 0; font-size: 16px; color: #1e293b;">
                    🔌 اطلاعات اتصال به دیتابیس
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>هاست دیتابیس <span class="required">*</span></label>
                        <input type="text" name="db_host" class="form-control" dir="ltr"
                               value="<?= htmlspecialchars($defaults['host']) ?>"
                               placeholder="127.0.0.1 یا localhost" required>
                        <span class="hint">معمولاً <code>localhost</code> یا <code>127.0.0.1</code></span>
                    </div>
                    <div class="form-group">
                        <label>پورت</label>
                        <input type="text" name="db_port" class="form-control" dir="ltr"
                               value="<?= htmlspecialchars($defaults['port']) ?>"
                               placeholder="3306">
                        <span class="hint">پیش‌فرض: 3306</span>
                    </div>
                </div>

                <div class="form-group">
                    <label>نام دیتابیس <span class="required">*</span></label>
                    <input type="text" name="db_name" class="form-control" dir="ltr"
                           value="<?= htmlspecialchars($defaults['name']) ?>"
                           placeholder="partocms" required>
                    <span class="hint">
                        فقط حروف انگلیسی، اعداد، <code>_</code> و <code>-</code>
                    </span>
                </div>

                <div class="form-group">
                    <label>نام کاربری <span class="required">*</span></label>
                    <input type="text" name="db_user" class="form-control" dir="ltr"
                           value="<?= htmlspecialchars($defaults['user']) ?>"
                           placeholder="root" required>
                </div>

                <div class="form-group">
                    <label>رمز عبور</label>
                    <input type="password" name="db_pass" class="form-control" dir="ltr"
                           value="<?= htmlspecialchars($defaults['pass']) ?>"
                           placeholder="رمز عبور">
                    <span class="hint">روی هاست اشتراکی معمولاً پر است</span>
                </div>

                <div class="form-group">
                    <label>Charset</label>
                    <select name="db_charset" class="form-control">
                        <option value="utf8mb4" <?= $defaults['charset'] === 'utf8mb4' ? 'selected' : '' ?>>
                            utf8mb4 (توصیه شده — پشتیبانی از ایموجی)
                        </option>
                        <option value="utf8" <?= $defaults['charset'] === 'utf8' ? 'selected' : '' ?>>
                            utf8 (قدیمی)
                        </option>
                    </select>
                </div>

                <div style="text-align: center; margin-top: 25px;">
                    <button type="submit" class="btn-primary-custom">
                        <i class="bi bi-plug"></i> تست اتصال
                    </button>
                </div>
            </form>

        </div>

        <!-- Actions -->
        <div class="actions">
            <a href="index.php" class="btn-secondary-custom">
                <i class="bi bi-arrow-right"></i> مرحله قبل
            </a>

            <?php if ($success && $testResult): ?>
                <a href="admin-user.php" class="btn-primary-custom">
                    مرحله بعد: ساخت جداول →
                </a>
            <?php else: ?>
                <span class="btn-primary-custom disabled">
                    ابتدا اتصال را تست کنید
                </span>
            <?php endif; ?>
        </div>

    </div>

    <div class="brand-footer">
        PartoCMS Smart Installer v1.0 — © ۲۰۲۶
    </div>
</div>

</body>
</html>
