<?php
class Permissions {
    private $pdo;
    private $cache = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function can($userId, $permission) {
        // اگه کاربر لاگین نیست، دسترسی نداره
        if (empty($userId) || !is_numeric($userId)) {
            return false;
        }

        // لود کش
        if (!isset($this->cache[$userId])) {
            $this->cache[$userId] = $this->loadUserPermissions((int) $userId);
        }

        return in_array($permission, $this->cache[$userId], true);
    }

    private function loadUserPermissions($userId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT p.slug
                FROM users u
                JOIN role_permissions rp ON rp.role_id = u.role_id
                JOIN permissions p ON p.id = rp.permission_id
                WHERE u.id = ? AND u.is_active = 1
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    public function require($permission) {
        // چک لاگین
        if (!isLoggedIn()) {
            header('Location: ' . SITE_URL . '/admin/login.php');
            exit;
        }

        // چک دسترسی
        $userId = $_SESSION['user_id'] ?? 0;
        if (!$this->can($userId, $permission)) {
            http_response_code(403);
            ?>
            <!DOCTYPE html>
            <html <?= __html_attrs() ?>>
            <head>
                <meta charset="UTF-8">
                <title>دسترسی غیرمجاز</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
                <style>body { font-family: Tahoma, sans-serif; background: #f4f6f9; }</style>
            </head>
            <body>
                <div style="text-align:center; padding:80px 20px; min-height:100vh;">
                    <div style="font-size:80px;">🚫</div>
                    <h1 style="color:#e74c3c;">دسترسی غیرمجاز</h1>
                    <p style="color:#7f8c8d;">شما مجوز دسترسی به این بخش را ندارید.</p>
                    <p style="color:#95a5a6; font-size:13px;">دسترسی مورد نیاز: <code><?= htmlspecialchars($permission) ?></code></p>
                    <a href="<?= SITE_URL ?>/admin/index.php" class="btn btn-primary mt-3">بازگشت به داشبورد</a>
                    <a href="<?= SITE_URL ?>/admin/logout.php" class="btn btn-outline-danger mt-3">خروج</a>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
}
