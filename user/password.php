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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current'] ?? '';
    $new = $_POST['new'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($current, $hash)) {
        $error = 'رمز عبور فعلی اشتباه است';
    } elseif (strlen($new) < 6) {
        $error = 'رمز جدید حداقل ۶ کاراکتر';
    } elseif ($new !== $confirm) {
        $error = 'تکرار رمز مطابقت ندارد';
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $userId]);
        $success = 'رمز عبور با موفقیت تغییر کرد';
    }
}
?>


<?php require_once 'includes/header.php'; ?>


    <h4 class="mb-4">🔑 تغییر رمز عبور</h4>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header"><i class="bi bi-key-fill"></i> رمز عبور</div>
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">رمز فعلی *</label>
                    <input type="password" name="current" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">رمز جدید *</label>
                    <input type="password" name="new" class="form-control" required>
                    <small class="text-muted">حداقل ۶ کاراکتر</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">تکرار رمز جدید *</label>
                    <input type="password" name="confirm" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-warning"><i class="bi bi-key"></i> تغییر رمز</button>
            </form>
        </div>
    </div>
</div>



</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
