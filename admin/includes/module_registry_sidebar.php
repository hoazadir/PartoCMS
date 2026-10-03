<?php
/**
 * PartoCMS - Module Registry Sidebar Helper v2.0
 * ساختار درختی: هر ماژول یک زیرگروه با عنوان معنادار
 *
 * @version 2.0
 * @date 2026-09-19
 */
class ModuleRegistrySidebar {

    private $mr;

    // ═══════════════════════════════════════════════════════════
    // دیکشنری برچسب‌های ماژول‌های شناخته‌شده
    // ═══════════════════════════════════════════════════════════
    private static $moduleLabels = [
        'contacts'  => ['singular' => 'مخاطب',  'plural' => 'مخاطبین',   'icon' => '📇'],
        'products'  => ['singular' => 'محصول',  'plural' => 'محصولات',   'icon' => '🛒'],
        'posts'     => ['singular' => 'مقاله',  'plural' => 'مقالات',    'icon' => '📝'],
        'articles'  => ['singular' => 'مقاله',  'plural' => 'مقالات',    'icon' => '📝'],
        'users'     => ['singular' => 'کاربر',  'plural' => 'کاربران',   'icon' => '👥'],
        'customers' => ['singular' => 'مشتری',  'plural' => 'مشتریان',   'icon' => '🧑‍💼'],
        'orders'    => ['singular' => 'سفارش',  'plural' => 'سفارشات',   'icon' => '💰'],
        'invoices'  => ['singular' => 'فاکتور', 'plural' => 'فاکتورها',  'icon' => '🧾'],
        'tasks'     => ['singular' => 'وظیفه',  'plural' => 'وظایف',     'icon' => '✅'],
        'projects'  => ['singular' => 'پروژه',  'plural' => 'پروژه‌ها',  'icon' => '📊'],
        'categories'=> ['singular' => 'دسته',   'plural' => 'دسته‌بندی‌ها','icon'=> '🏷'],
        'tags'      => ['singular' => 'برچسب',  'plural' => 'برچسب‌ها',  'icon' => '🔖'],
        'comments'  => ['singular' => 'نظر',    'plural' => 'نظرات',     'icon' => '💬'],
        'events'    => ['singular' => 'رویداد', 'plural' => 'رویدادها',  'icon' => '📅'],
        'files'     => ['singular' => 'فایل',   'plural' => 'فایل‌ها',   'icon' => '📎'],
        'media'     => ['singular' => 'رسانه',  'plural' => 'رسانه‌ها',  'icon' => '🖼'],
        'items'     => ['singular' => 'آیتم',   'plural' => 'آیتم‌ها',   'icon' => '📦'],
        'test01'    => ['singular' => 'آیتم',   'plural' => 'آیتم‌ها',   'icon' => '📦'],
        'ai_assistant' => ['singular' => 'دستیار', 'plural' => 'دستیار هوشمند', 'icon' => '🤖'],
    ];

    // ═══════════════════════════════════════════════════════════
    // دیکشنری آیکون و عنوان صفحات
    // ═══════════════════════════════════════════════════════════
    private static $pageIcons = [
        // صفحات عمومی
        'list'      => '📋',
        'view'      => '👁',
        'form'      => '➕',
        'admin'     => '🛠',
        'add'       => '➕',
        'edit'      => '✏️',
        'settings'  => '⚙️',
        // صفحات ماژول‌های خاص
        'languages'    => '🌍',
        'translations' => '📚',
        'import'       => '📥',
        'translate'    => '🌐',
        'review'       => '🔍',
        'queue'        => '📋',
        'cron_setup'   => '⏰',
        'dashboard'    => '📊',
        'audit'        => '🔎',
        'graphs'       => '📈',
        'telegram'     => '🤖',
        'virustotal'   => '🦠',
        '2fa'          => '🔐',
        'manager'      => '📦',
        'index'        => '📊',
        // 🆕 AI Providers (2026-10-03)
        'providers'    => '☁️',
    ];

    // ═══════════════════════════════════════════════════════════
    // دیکشنری عنوان صفحات خاص
    // ═══════════════════════════════════════════════════════════
    private static $pageTitles = [
        'languages'    => 'مدیریت زبان‌ها',
        'translations' => 'مدیریت ترجمه‌ها',
        'import'       => 'ایمپورت ترجمه‌ها',
        'translate'    => 'ترجمه محتوا',
        'review'       => 'بازنگری ترجمه‌ها',
        'settings'     => 'تنظیمات ترجمه',
        'queue'        => 'صف ترجمه',
        'cron_setup'   => 'راه‌اندازی Cron',
        'dashboard'    => 'داشبورد امنیتی',
        'audit'        => 'تست جامع امنیتی',
        'graphs'       => 'نمودارهای امنیتی',
        'telegram'     => 'راه‌اندازی تلگرام',
        'virustotal'   => 'ویروس‌یاب VirusTotal',
        '2fa'          => 'احراز هویت دو مرحله‌ای',
        'manager'      => 'بکاپ خودکار',
        'index'        => 'جدول‌ساز',
        'admin'        => 'تنظیمات',
        'chat'         => 'چت با دستیار',
        // 🆕 AI Providers (2026-10-03)
        'providers'    => 'ارائه‌دهندگان AI',
    ];

    // ═══════════════════════════════════════════════════════════
    // دیکشنری آیکون گروه‌های تخصصی
    // ═══════════════════════════════════════════════════════════
    private static $groupIcons = [
        'i18n'        => '🌍',
        'translation' => '🔄',
        'security'    => '🛡️',
        'backup_auto' => '💾',
        'tools_pro'   => '🔧',
        'generated'   => '📦',
        'ai'          => '🤖',
    ];

    public function __construct($registry) {
        $this->mr = $registry;
    }

    public function canAccess(string $moduleSlug, $moduleManager, bool $isAdmin): bool {
        return $isAdmin;
    }

    // ═══════════════════════════════════════════════════════════
    //  رندر اصلی
    // ═══════════════════════════════════════════════════════════
    public function renderGroups(string $currentFile, string $currentPath, string $baseAdmin, $moduleManager, bool $isAdmin): string {
        if (!$isAdmin) return '';

        $groups = $this->mr->getGroups();
        if (empty($groups)) return '';

        $dynamicGroups = ['i18n', 'translation', 'security', 'backup_auto', 'tools_pro', 'generated'];
        if (class_exists('ModuleRegistry') && method_exists('ModuleRegistry', 'getDynamicGroups')) {
            $dynamicGroups = ModuleRegistry::getDynamicGroups();
        }

        $html = '';

        foreach ($groups as $groupSlug => $groupInfo) {
            if (!in_array($groupSlug, $dynamicGroups, true)) continue;
            if (!$this->canAccess($groupSlug, $moduleManager, $isAdmin)) continue;

            $modules = $groupInfo['modules'] ?? [];
            if (empty($modules)) continue;

            // ═══ گروه `generated` → هر ماژول یک زیرگروه مستقل ═══
            if ($groupSlug === 'generated') {
                $html .= $this->renderGeneratedGroup($modules, $currentFile, $currentPath, $baseAdmin);
                continue;
            }

            // ═══ سایر گروه‌ها → رندر معمولی ═══
            $html .= $this->renderStandardGroup($groupSlug, $groupInfo, $currentFile, $currentPath, $baseAdmin);
        }

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    //  رندر گروه generated: هر ماژول یک زیرگروه
    // ═══════════════════════════════════════════════════════════
    private function renderGeneratedGroup(array $modules, string $currentFile, string $currentPath, string $baseAdmin): string {
        $html = '';

        // هدر گروه اصلی
        $html .= "\n" . '    <!-- 📦 ماژول‌های ساخته شده -->' . "\n";
        $html .= '    <div class="menu-group generated-root" data-group="generated-root">' . "\n";
        $html .= '        <div class="menu-group-header" onclick="toggleMenuGroup(this)">' . "\n";
        $html .= '            <span class="group-icon">📦</span>' . "\n";
        $html .= '            <span class="group-title">ماژول‌های ساخته شده</span>' . "\n";
        $html .= '            <span class="arrow">▼</span>' . "\n";
        $html .= '        </div>' . "\n";
        $html .= '        <div class="menu-group-items generated-items">' . "\n";

        // هر ماژول یک زیرگروه
        foreach ($modules as $slug => $module) {
            $html .= $this->renderModuleSubGroup($slug, $module, $currentFile, $currentPath, $baseAdmin);
        }

        $html .= '        </div>' . "\n";
        $html .= '    </div>' . "\n";

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    //  زیرگروه یک ماژول Generated
    // ═══════════════════════════════════════════════════════════
    private function renderModuleSubGroup(string $slug, array $module, string $currentFile, string $currentPath, string $baseAdmin): string {
        $pages = $module['pages'] ?? [];
        if (empty($pages)) return '';

        // برچسب‌های ماژول
        $labels = $this->getModuleLabels($slug, $module);
        $icon   = $labels['icon'];
        $plural = $labels['plural'];
        $singular = $labels['singular'];

        // بررسی فعال بودن زیرگروه
        $isActive = false;
        foreach ($pages as $path) {
            if (basename($path) === $currentFile) { $isActive = true; break; }
        }

        // استفاده از name یا slug واقعی برای ID
        $realSlug = $module['slug'] ?? $module['name'] ?? $slug;
        $subGroupId = 'sub-' . preg_replace('/[^a-z0-9_\x{0600}-\x{06FF}]/u', '', $realSlug);
        $classes = 'menu-subgroup' . ($isActive ? ' open has-active' : '');

        $html  = '            <div class="' . $classes . '" data-subgroup="' . htmlspecialchars($subGroupId) . '">' . "\n";
        $html .= '                <div class="menu-subgroup-header" onclick="toggleSubMenu(this)">' . "\n";
        $html .= '                    <span class="subgroup-icon">' . $icon . '</span>' . "\n";
        $html .= '                    <span class="subgroup-title">' . htmlspecialchars($plural) . '</span>' . "\n";
        $html .= '                    <span class="subgroup-arrow">▾</span>' . "\n";
        $html .= '                </div>' . "\n";
        $html .= '                <div class="menu-subgroup-items">' . "\n";

        // ترتیب دلخواه صفحات: chat → list → form → admin → view
        $order = ['chat' => 0, 'list' => 1, 'form' => 2, 'admin' => 3, 'view' => 4];
        $sortedPages = $pages;
        uksort($sortedPages, function($a, $b) use ($order) {
            return ($order[$a] ?? 99) <=> ($order[$b] ?? 99);
        });

        foreach ($sortedPages as $key => $path) {
            $pIcon = self::$pageIcons[$key] ?? '📄';
            $pTitle = $this->getPageTitle($key, $plural, $singular);

            // URL
            if (strpos($path, 'modules/') === 0) {
                $url = rtrim(SITE_URL, '/') . '/' . $path;
            } else {
                $url = rtrim($baseAdmin, '/') . '/' . basename($path);
            }

            $isPageActive = (basename($path) === $currentFile);

            $html .= '                    <a href="' . htmlspecialchars($url) . '" class="' . ($isPageActive ? 'active' : '') . '" onclick="closeMobileSidebar()">' . "\n";
            $html .= '                        <span class="item-icon">' . $pIcon . '</span>' . "\n";
            $html .= '                        <span>' . htmlspecialchars($pTitle) . '</span>' . "\n";
            $html .= '                    </a>' . "\n";
        }

        $html .= '                </div>' . "\n";
        $html .= '            </div>' . "\n";

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    //  رندر گروه استاندارد (i18n, security, ...)
    // ═══════════════════════════════════════════════════════════
    private function renderStandardGroup(string $groupSlug, array $groupInfo, string $currentFile, string $currentPath, string $baseAdmin): string {
        $modules = $groupInfo['modules'] ?? [];
        if (empty($modules)) return '';

        // شمارش صفحات
        $totalPages = 0;
        foreach ($modules as $m) $totalPages += count($m['pages'] ?? []);
        if ($totalPages === 0) return '';

        // بررسی فعال بودن
        $isGroupActive = false;
        foreach ($modules as $m) {
            foreach ($m['pages'] ?? [] as $path) {
                if (basename($path) === $currentFile) { $isGroupActive = true; break 2; }
            }
        }

        $groupIcon = self::$groupIcons[$groupSlug] ?? '📦';
        $groupTitle = $groupInfo['title'] ?? $groupSlug;

        $classes = 'menu-group' . ($isGroupActive ? ' open has-active' : '');

        $html  = "\n" . '    <!-- ' . htmlspecialchars($groupTitle) . ' -->' . "\n";
        $html .= '    <div class="' . $classes . '" data-group="' . htmlspecialchars($groupSlug) . '">' . "\n";
        $html .= '        <div class="menu-group-header" onclick="toggleMenuGroup(this)">' . "\n";
        $html .= '            <span class="group-icon">' . $groupIcon . '</span>' . "\n";
        $html .= '            <span class="group-title">' . htmlspecialchars($groupTitle) . '</span>' . "\n";
        $html .= '            <span class="arrow">▼</span>' . "\n";
        $html .= '        </div>' . "\n";
        $html .= '        <div class="menu-group-items">' . "\n";

        foreach ($modules as $slug => $module) {
            foreach ($module['pages'] ?? [] as $key => $path) {
                $icon = self::$pageIcons[$key] ?? '📄';
                $title = self::$pageTitles[$key] ?? ucfirst($key);
                $isActive = (basename($path) === $currentFile);

                if (strpos($path, 'modules/') === 0) {
                    $url = rtrim(SITE_URL, '/') . '/' . $path;
                } else {
                    $url = rtrim($baseAdmin, '/') . '/' . basename($path);
                }

                $html .= '            <a href="' . htmlspecialchars($url) . '" class="' . ($isActive ? 'active' : '') . '" onclick="closeMobileSidebar()">' . "\n";
                $html .= '                <span class="item-icon">' . $icon . '</span>' . "\n";
                $html .= '                <span>' . htmlspecialchars($title) . '</span>' . "\n";
                $html .= '            </a>' . "\n";
            }
        }

        $html .= '        </div>' . "\n";
        $html .= '    </div>' . "\n";

        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    //  برچسب‌های ماژول (icon, singular, plural)
    // ═══════════════════════════════════════════════════════════
    private function getModuleLabels(string $slug, array $module): array {
        // 1. از دیکشنری
        if (isset(self::$moduleLabels[$slug])) {
            return self::$moduleLabels[$slug];
        }

        // 2. از خود ماژول (name, icon)
        $icon = $module['icon'] ?? '📦';
        $name = $module['name'] ?? $slug;

        // تلاش برای حذف «(Generated)»
        $name = preg_replace('/\s*\(Generated\)\s*/i', '', $name);

        return [
            'singular' => $name,
            'plural'   => $name,
            'icon'     => $icon,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  عنوان صفحه (بر اساس برچسب ماژول)
    // ═══════════════════════════════════════════════════════════
    private function getPageTitle(string $pageKey, string $plural, string $singular): string {
        switch ($pageKey) {
            case 'list':  return 'لیست ' . $plural;
            case 'form':  return 'افزودن ' . $singular;
            case 'admin':
                // برای ماژول ai_assistant، عنوان اختصاصی
                if ($plural === 'دستیار هوشمند') return '🤖 تنظیمات دستیار';
                return 'مدیریت ' . $plural;
            case 'view':  return 'نمایش ' . $singular;
            case 'add':   return 'افزودن ' . $singular;
            case 'edit':  return 'ویرایش ' . $singular;
            default:
                return self::$pageTitles[$pageKey] ?? ucfirst($pageKey);
        }
    }
}
