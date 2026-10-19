<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');

    $errors = [];
    if (empty($username)) $errors[] = 'نام کاربری الزامی است';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'ایمیل معتبر الزامی است';

    // چک تکراری
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $stmt->execute([$username, $email, $userId]);
        if ($stmt->fetch()) {
            $errors[] = 'نام کاربری یا ایمیل قبلاً استفاده شده';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users SET username = ?, email = ?, bio = ?, phone = ?, website = ?
                WHERE id = ?
            ");
            $stmt->execute([$username, $email, $bio ?: null, $phone ?: null, $website ?: null, $userId]);

            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;

            $success = 'پروفایل با موفقیت ذخیره شد';
        } catch (PDOException $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errors);
    }
}

// اطلاعات کاربر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$pageTitle = 'پروفایل من';
?>

<?php require_once 'includes/header.php'; ?>


    <h4 class="mb-4">👤 پروفایل من</h4>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-pencil"></i> اطلاعات شخصی</div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نام کاربری *</label>
                        <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($user['username']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">ایمیل *</label>
                        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= __t('fe_phone', [], 'شماره تماس') ?></label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label"><?= __t('fe_website', [], 'وب‌سایت') ?></label>
                        <input type="url" name="website" class="form-control" value="<?= htmlspecialchars($user['website'] ?? '') ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">درباره من</label>
                    <textarea name="bio" class="form-control" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><i class="bi bi-save"></i> ذخیره</button>
            </form>
        </div>
    </div>
</div>



</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
