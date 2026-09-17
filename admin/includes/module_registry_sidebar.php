<?php
/**
 * PartoCMS - Module Registry Sidebar Helper
 * @version 1.1
 */
class ModuleRegistrySidebar {

    private $mr;

    private static $pageIcons = [
        'languages'    => '🌍',
        'translations' => '📚',
        'import'       => '📥',
        'translate'    => '🌐',
        'review'       => '🔍',
        'settings'     => '⚙️',
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
    ];

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
        'index'        => 'صفحه اصلی',
    ];

    private static $groupIcons = [
        'i18n'        => '🌍',
        'translation' => '🔄',
        'security'    => '🛡️',
        'backup_auto' => '💾',
        'tools_pro'   => '🔧',
    ];

    public function __construct($registry) {
        $this->mr = $registry;
    }

    public function canAccess(string $moduleSlug, $moduleManager, bool $isAdmin): bool {
        return $isAdmin;
    }

    public function renderGroups(string $currentFile, string $currentPath, string $baseAdmin, $moduleManager, bool $isAdmin): string {
        $html = '';

        if (!$isAdmin) {
            return $html;
        }

        $groups = $this->mr->getGroups();

        if (empty($groups)) {
            return $html;
        }

        $dynamicGroups = ['i18n', 'translation', 'security', 'backup_auto', 'tools_pro'];
        if (class_exists('ModuleRegistry') && method_exists('ModuleRegistry', 'getDynamicGroups')) {
            $dynamicGroups = ModuleRegistry::getDynamicGroups();
        }

        foreach ($groups as $groupSlug => $groupInfo) {
            if (!in_array($groupSlug, $dynamicGroups, true)) {
                continue;
            }

            if (!$this->canAccess($groupSlug, $moduleManager, $isAdmin)) {
                continue;
            }

            $modules = $groupInfo['modules'] ?? [];
            if (empty($modules)) {
                continue;
            }

            $totalPages = 0;
            foreach ($modules as $m) {
                $totalPages += count($m['pages'] ?? []);
            }
            if ($totalPages === 0) {
                continue;
            }

            $isGroupActive = false;
            foreach ($modules as $m) {
                foreach ($m['pages'] ?? [] as $path) {
                    if (basename($path) === $currentFile) {
                        $isGroupActive = true;
                        break 2;
                    }
                }
            }

            $groupIcon = self::$groupIcons[$groupSlug] ?? '📦';
            $groupTitle = $groupInfo['title'] ?? ('📦 ' . $groupSlug);

            $groupClasses = 'menu-group';
            if ($isGroupActive) {
                $groupClasses .= ' open has-active';
            }

            $html .= "\n" . '    <!-- ' . htmlspecialchars($groupTitle) . ' -->' . "\n";
            $html .= '    <div class="' . $groupClasses . '" data-group="' . htmlspecialchars($groupSlug) . '">' . "\n";
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
                    $url = rtrim($baseAdmin, '/') . '/' . basename($path);

                    $html .= '            <a href="' . htmlspecialchars($url) . '" class="' . ($isActive ? 'active' : '') . '" onclick="closeMobileSidebar()">' . "\n";
                    $html .= '                <span class="item-icon">' . $icon . '</span>' . "\n";
                    $html .= '                <span>' . htmlspecialchars($title) . '</span>' . "\n";
                    $html .= '            </a>' . "\n";
                }
            }

            $html .= '        </div>' . "\n";
            $html .= '    </div>' . "\n";
        }

        return $html;
    }
}
