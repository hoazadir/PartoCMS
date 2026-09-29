<?php
/**
 * PartoCMS - Menu Builder v2.0
 * ساخت منوی سایدبار به صورت کامل داینامیک از manifest.json
 *
 * @author Hooman Oliaei
 * @version 2.0.0
 *
 * معماری:
 *   - ManifestLoader مسئول بارگذاری manifest است
 *   - MenuBuilder فقط گروه‌بندی و مرتب‌سازی می‌کند
 *   - هیچ URL Map سخت‌کدشده‌ای وجود ندارد
 */

require_once __DIR__ . '/ManifestLoader.php';

class MenuBuilder
{
    private PDO $pdo;
    private ManifestLoader $loader;
    private ModuleManager $mm;

    /**
     * عنوان گروه‌ها (با ایموجی)
     */
    private const GROUP_TITLES = [
        'content'      => ['title' => 'مدیریت محتوا',      'icon' => '📝'],
        'commerce'     => ['title' => 'فروشگاه',            'icon' => '🛒'],
        'marketing'    => ['title' => 'بازاریابی',          'icon' => '📢'],
        'tools'        => ['title' => 'ابزارها',            'icon' => '🔧'],
        'tools_pro'    => ['title' => 'ابزارهای پیشرفته',   'icon' => '⚡'],
        'appearance'   => ['title' => 'ظاهر سایت',          'icon' => '🎨'],
        'reports'      => ['title' => 'گزارش‌ها',           'icon' => '📈'],
        'seo'          => ['title' => 'بهینه‌سازی SEO',     'icon' => '🔍'],
        'i18n'         => ['title' => 'چند زبانی',          'icon' => '🌍'],
        'translation'  => ['title' => 'ترجمه محتوا',        'icon' => '🔄'],
        'security'     => ['title' => 'امنیت',              'icon' => '🛡️'],
        'backup_auto'  => ['title' => 'پشتیبان‌گیری',       'icon' => '💾'],
        'admin'        => ['title' => 'مدیریت سیستم',       'icon' => '⚙️'],
        'ai'           => ['title' => 'هوش مصنوعی',         'icon' => '🤖'],
        'generated'    => ['title' => 'ماژول‌های ساخته‌شده', 'icon' => '🧩'],
        'other'        => ['title' => 'سایر',               'icon' => '📌'],
    ];

    /**
     * ترتیب نمایش گروه‌ها
     */
    private const GROUP_ORDER = [
        'content', 'commerce', 'marketing', 'tools', 'tools_pro',
        'appearance', 'reports', 'seo', 'i18n', 'translation',
        'security', 'backup_auto', 'admin', 'ai', 'generated', 'other',
    ];

    public function __construct(PDO $pdo, ModuleManager $mm)
    {
        $this->pdo = $pdo;
        $this->mm = $mm;
        $this->loader = new ManifestLoader($pdo);
    }

    /**
     * ساخت منوی کامل گروه‌بندی‌شده
     */
    public function build(): array
    {
        // ۱. دریافت همه manifestها
        $manifests = $this->loader->loadAll();

        // ۲. فیلتر بر اساس دسترسی کاربر
        $allowedSlugs = $this->getAllowedSlugs();

        // ۳. گروه‌بندی
        $groups = [];
        foreach ($manifests as $slug => $manifest) {
            // فیلتر دسترسی
            if ($allowedSlugs !== null && !in_array($slug, $allowedSlugs, true)) {
                continue;
            }

            $group = $manifest['menu_group'] ?? 'other';
            $item = $this->buildMenuItem($manifest);

            if ($item === null) continue;

            $groups[$group][] = $item;
        }

        // ۴. مرتب‌سازی هر گروه
        foreach ($groups as &$items) {
            usort($items, fn($a, $b) => $a['_sort'] <=> $b['_sort']);
        }
        unset($items);

        // ۵. ترتیب گروه‌ها
        $ordered = [];
        foreach (self::GROUP_ORDER as $g) {
            if (!empty($groups[$g])) {
                $ordered[$g] = $groups[$g];
            }
        }
        // گروه‌های ناشناخته
        foreach ($groups as $g => $items) {
            if (!isset($ordered[$g])) {
                $ordered[$g] = $items;
            }
        }

        return $ordered;
    }

    /**
     * ساخت یک آیتم منو از manifest
     */
    private function buildMenuItem(array $manifest): ?array
    {
        $url = $manifest['menu']['url'] ?? '';
        if (empty($url)) {
            return null; // ماژول بدون URL نمایش داده نمی‌شود
        }

        // تبدیل URL نسبی به مطلق
        $fullUrl = $this->buildFullUrl($url);

        // زیرمنوها
        $submenu = [];
        foreach ($manifest['menu']['submenu'] ?? [] as $sub) {
            if (empty($sub['url']) || empty($sub['title'])) continue;
            $submenu[] = [
                'title' => $sub['title'],
                'url'   => $this->buildFullUrl($sub['url']),
                'icon'  => $sub['icon'] ?? '•',
            ];
        }

        return [
            'slug'        => $manifest['slug'],
            'title'       => $manifest['menu']['title'] ?? $manifest['name'],
            'icon'        => $manifest['icon'] ?? '📄',
            'url'         => $fullUrl,
            '_sort'       => $manifest['sort_order'] ?? 999,
            'submenu'     => $submenu,
            'has_submenu' => !empty($submenu),
        ];
    }

    /**
     * تبدیل URL نسبی به مطلق
     */
    private function buildFullUrl(string $url): string
    {
        // اگر از قبل مطلق است
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        // اگر با /modules/ شروع می‌شود
        if (strpos($url, '/modules/') === 0) {
            return SITE_URL . $url;
        }

        // اگر با /admin شروع می‌شود
        if (strpos($url, '/admin') === 0) {
            return SITE_URL . $url;
        }

        // پیش‌فرض: با ADMIN_URL
        return ADMIN_URL . '/' . ltrim($url, '/');
    }

    /**
     * دریافت slugهای مجاز برای کاربر فعلی
     * (null = همه مجاز)
     */
    private function getAllowedSlugs(): ?array
    {
        // ادمین → همه
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return null;
        }

        // از ModuleManager
        try {
            $modules = $this->mm->getCurrentUserModules();
            if (empty($modules)) return null;

            return array_column($modules, 'slug');
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * عنوان گروه
     */
    public static function getGroupTitle(string $slug): array
    {
        return self::GROUP_TITLES[$slug] ?? ['title' => $slug, 'icon' => '📁'];
    }

    /**
     * همه عناوین گروه‌ها
     */
    public static function getAllGroupTitles(): array
    {
        return self::GROUP_TITLES;
    }
}
