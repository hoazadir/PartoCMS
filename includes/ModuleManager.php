<?php
/**
 * ModuleManager - مدیریت ماژول‌ها و دسترسی‌ها
 */
class ModuleManager {
    private $pdo;
    private $modules = [];
    private $userModulesCache = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadModules();
    }

    /**
     * لود همه ماژول‌های فعال
     */
    private function loadModules() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM modules WHERE is_enabled = 1");
            while ($row = $stmt->fetch()) {
                $this->modules[$row['slug']] = $row;
            }
        } catch (Exception $e) {
            $this->modules = [];
        }
    }

    /**
     * بررسی فعال بودن ماژول
     */
    public function isEnabled($slug) {
        return isset($this->modules[$slug]);
    }

    /**
     * بررسی دسترسی نقش به ماژول
     */
    public function canRoleAccess($roleId, $slug) {
        if (!$this->isEnabled($slug)) return false;
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT can_access FROM role_modules 
                WHERE role_id = ? AND module_slug = ?
            ");
            $stmt->execute([$roleId, $slug]);
            $result = $stmt->fetchColumn();
            
            // اگه رکورد نبود، پیش‌فرض = دسترسی نداره
            if ($result === false) return false;
            
            return (int) $result === 1;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * بررسی دسترسی کاربر فعلی به ماژول
     */
    public function canCurrentUserAccess($slug) {
        if (!function_exists('isLoggedIn') || !isLoggedIn()) return false;
        
        $roleId = $_SESSION['role_id'] ?? 0;
        if (!$roleId) return false;
        
        return $this->canRoleAccess((int) $roleId, $slug);
    }

    /**
     * بررسی دسترسی کاربر به ماژول (با کش)
     */
    public function canUserAccess($userId, $slug) {
        if (!isset($this->userModulesCache[$userId])) {
            try {
                $stmt = $this->pdo->prepare("
                    SELECT u.role_id FROM users u WHERE u.id = ?
                ");
                $stmt->execute([$userId]);
                $roleId = $stmt->fetchColumn();
                
                if (!$roleId) {
                    $this->userModulesCache[$userId] = [];
                } else {
                    $stmt = $this->pdo->prepare("
                        SELECT module_slug FROM role_modules 
                        WHERE role_id = ? AND can_access = 1
                    ");
                    $stmt->execute([$roleId]);
                    $this->userModulesCache[$userId] = $stmt->fetchAll(PDO::FETCH_COLUMN);
                }
            } catch (Exception $e) {
                $this->userModulesCache[$userId] = [];
            }
        }
        
        return in_array($slug, $this->userModulesCache[$userId]);
    }

    /**
     * دریافت همه ماژول‌ها
     */
    public function getAll() {
        try {
            $stmt = $this->pdo->query("
                SELECT m.*, 
                    (SELECT COUNT(*) FROM role_modules WHERE module_slug = m.slug AND can_access = 1) as roles_count
                FROM modules m 
                ORDER BY m.sort_order ASC, m.id ASC
            ");
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * دریافت ماژول‌های فعال
     */
    public function getEnabled() {
        return $this->modules;
    }

    /**
     * دریافت ماژول‌های قابل دسترس کاربر فعلی
     */
    public function getCurrentUserModules() {
        if (!function_exists('isLoggedIn') || !isLoggedIn()) return [];
        
        $roleId = $_SESSION['role_id'] ?? 0;
        if (!$roleId) return [];

        try {
            $stmt = $this->pdo->prepare("
                SELECT m.* FROM modules m
                INNER JOIN role_modules rm ON rm.module_slug = m.slug
                WHERE m.is_enabled = 1 
                  AND rm.role_id = ? 
                  AND rm.can_access = 1
                ORDER BY m.sort_order ASC
            ");
            $stmt->execute([$roleId]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * دریافت ماژول‌های یک نقش
     */
    public function getRoleModules($roleId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT module_slug, can_access 
                FROM role_modules 
                WHERE role_id = ?
            ");
            $stmt->execute([$roleId]);
            $result = [];
            foreach ($stmt->fetchAll() as $row) {
                $result[$row['module_slug']] = (int) $row['can_access'];
            }
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * ذخیره دسترسی‌های نقش
     */
    public function setRoleModules($roleId, $modules) {
        try {
            // حذف همه دسترسی‌های قبلی
            $this->pdo->prepare("DELETE FROM role_modules WHERE role_id = ?")->execute([$roleId]);
            
            // اضافه کردن دسترسی‌های جدید
            $stmt = $this->pdo->prepare("
                INSERT INTO role_modules (role_id, module_slug, can_access) 
                VALUES (?, ?, 1)
            ");
            
            foreach ($modules as $slug) {
                if (is_string($slug) && !empty($slug)) {
                    $stmt->execute([$roleId, $slug]);
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * فعال کردن ماژول
     */
    public function enable($slug) {
        $stmt = $this->pdo->prepare("UPDATE modules SET is_enabled = 1 WHERE slug = ?");
        $result = $stmt->execute([$slug]);
        $this->loadModules();
        return $result;
    }

    /**
     * غیرفعال کردن ماژول
     */
    public function disable($slug) {
        // چک کن ماژول core نباشه
        $stmt = $this->pdo->prepare("SELECT is_core FROM modules WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetchColumn()) {
            return false; // ماژول‌های اصلی قابل غیرفعال شدن نیستند
        }

        $stmt = $this->pdo->prepare("UPDATE modules SET is_enabled = 0 WHERE slug = ?");
        $result = $stmt->execute([$slug]);
        $this->loadModules();
        return $result;
    }

    /**
     * ثبت ماژول جدید
     */
    public function register($slug, $name, $description = '', $version = '1.0.0') {
        $stmt = $this->pdo->prepare("
            INSERT INTO modules (slug, name, description, version)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                description = VALUES(description), 
                version = VALUES(version)
        ");
        return $stmt->execute([$slug, $name, $description, $version]);
    }

    /**
     * دریافت آیتم‌های منو
     */
    public function getMenuItems() {
        $items = [];
        foreach ($this->modules as $slug => $mod) {
            $menuFile = __DIR__ . "/../modules/{$slug}/menu.php";
            if (file_exists($menuFile)) {
                $items = array_merge($items, include $menuFile);
            }
        }
        return $items;
    }

    /**
     * بررسی دسترسی کاربر به صفحه (توسط فایل PHP)
     */
    public function checkPageAccess() {
        // صفحات عمومی که نیازی به چک ندارن
        $publicPages = ['index.php', 'login.php', 'logout.php', 'register.php', 'post.php', 'category.php', 'search.php', 'form.php', 'sitemap.php', 'robots.php'];
        
        $currentFile = basename($_SERVER['PHP_SELF']);
        if (in_array($currentFile, $publicPages)) return true;

        // نگاشت فایل به ماژول
        $fileModuleMap = [
            // admin
            'index.php' => null, // داشبورد - همیشه
            'templates.php' => 'templates',
            'editor.php' => 'editor',
            'users.php' => 'users',
            'roles.php' => 'roles',
            'modules.php' => 'modules',
            'settings.php' => 'settings',
            'categories.php' => 'categories',
            'media.php' => 'media',
            'comments.php' => 'comments',
            'forms.php' => 'forms',
            'form-edit.php' => 'forms',
            'form-submissions.php' => 'forms',
            'menus.php' => 'menus',
            'menu-edit.php' => 'menus',
            'reports.php' => 'reports',
            'seo.php' => 'seo',
            'backups.php' => 'backups',
            // modules
            'admin.php' => 'content', // modules/content/admin.php
        ];

        if (!isset($fileModuleMap[$currentFile])) return true;

        $module = $fileModuleMap[$currentFile];
        if ($module === null) return true; // همیشه قابل دسترس

        return $this->canCurrentUserAccess($module);
    }
}
