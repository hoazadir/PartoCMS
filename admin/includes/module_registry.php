<?php
/**
 * PartoCMS - Module Registry
 * نقشه‌ی ماژول‌ها → فایل‌های واقعی
 *
 * @version 1.1
 * @date 2026-09-17
 * استراتژی: بدون انتقال فیزیکی، فقط ثبت نقشه
 */
class ModuleRegistry {

    private $pdo;
    private $registry = null;

    private static $defaultMap = [
        'i18n' => [
            'name' => 'چند زبانی',
            'icon' => '🌍',
            'menu_group' => 'i18n',
            'sort_order' => 100,
            'is_core' => 0,
            'pages' => [
                'languages'    => 'admin/languages.php',
                'translations' => 'admin/translations.php',
                'import'       => 'admin/import_translations.php',
            ],
            'includes' => [
                'i18n'              => 'admin/includes/i18n.php',
                'language_switcher' => 'admin/includes/language_switcher.php',
            ],
        ],
        'translation' => [
            'name' => 'ترجمه محتوا',
            'icon' => '🔄',
            'menu_group' => 'translation',
            'sort_order' => 101,
            'is_core' => 0,
            'pages' => [
                'translate'  => 'admin/content_translate.php',
                'review'     => 'admin/content_review.php',
                'settings'   => 'admin/translator_settings.php',
                'queue'      => 'admin/queue.php',
                'cron_setup' => 'admin/cron_setup.php',
            ],
            'includes' => [
                'multi_translator' => 'admin/includes/multi_translator.php',
                'queue_manager'    => 'admin/includes/queue_manager.php',
                'auto_translator'  => 'admin/includes/auto_translator.php',
                'environment'      => 'admin/includes/environment.php',
            ],
            'cron' => [
                'cron_translation_queue' => 'admin/cron_translation_queue.php',
                'cron_auto_translate'    => 'admin/cron_auto_translate.php',
            ],
        ],
        'security' => [
            'name' => 'امنیت',
            'icon' => '🛡️',
            'menu_group' => 'security',
            'sort_order' => 102,
            'is_core' => 0,
            'pages' => [
                'dashboard'  => 'admin/security_dashboard.php',
                'audit'      => 'admin/security_audit.php',
                'graphs'     => 'admin/security_graphs.php',
                'telegram'   => 'admin/telegram_setup.php',
                'virustotal' => 'admin/virustotal_setup.php',
                '2fa'        => 'admin/2fa_setup.php',
            ],
            'includes' => [
                'security_config'     => 'admin/includes/security_config.php',
                'fim'                 => 'admin/includes/fim.php',
                'rate_limiter'        => 'admin/includes/rate_limiter.php',
                'totp'                => 'admin/includes/totp.php',
                'telegram_notifier'   => 'admin/includes/telegram_notifier.php',
                'virustotal_scanner'  => 'admin/includes/virustotal_scanner.php',
                'virus_scanner'       => 'admin/includes/virus_scanner.php',
            ],
            'cron' => [
                'cron_security_check' => 'admin/cron_security_check.php',
                'cron_weekly_report'  => 'admin/cron_weekly_report.php',
            ],
        ],
        'backup' => [
            'name' => 'پشتیبان‌گیری',
            'icon' => '💾',
            'menu_group' => 'backup_auto',
            'sort_order' => 103,
            'is_core' => 0,
            'pages' => [
                'manager' => 'admin/backup_manager.php',
            ],
            'includes' => [
                'backup_manager' => 'admin/includes/backup_manager.php',
            ],
        ],
        'table_builder' => [
            'name' => 'جدول‌ساز',
            'icon' => '📊',
            'menu_group' => 'tools_pro',
            'sort_order' => 104,
            'is_core' => 0,
            'pages' => [
                'index' => 'admin/table_builder.php',
            ],
            'includes' => [
                'table_builder' => 'admin/includes/table_builder.php',
            ],
        ],
    ];

    private static $dynamicGroups = ['i18n', 'translation', 'security', 'backup_auto', 'tools_pro'];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadRegistry();
    }

    private function loadRegistry() {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'module_files'");
            if (!$stmt->fetchColumn()) {
                $this->registry = self::$defaultMap;
                return;
            }

            $rows = $this->pdo->query("
                SELECT module_slug, file_type, file_key, file_path
                FROM module_files
                ORDER BY module_slug, file_type, file_key
            ")->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                $this->registry = self::$defaultMap;
                return;
            }

            $map = [];
            foreach ($rows as $r) {
                $slug = $r['module_slug'];
                if (!isset(self::$defaultMap[$slug])) continue;

                $type = $r['file_type'];
                $key  = $r['file_key'];

                if (!isset($map[$slug])) {
                    $map[$slug] = self::$defaultMap[$slug];
                    $map[$slug]['pages'] = [];
                    $map[$slug]['includes'] = [];
                    $map[$slug]['cron'] = [];
                }

                $map[$slug][$type][$key] = $r['file_path'];
            }

            foreach ($this->pdo->query("SELECT slug, name, icon, menu_group, sort_order, is_core FROM modules")->fetchAll(PDO::FETCH_ASSOC) as $m) {
                if (isset($map[$m['slug']])) {
                    $map[$m['slug']]['name']       = $m['name'];
                    $map[$m['slug']]['icon']       = $m['icon'] ?: '📦';
                    $map[$m['slug']]['menu_group'] = $m['menu_group'] ?: self::$defaultMap[$m['slug']]['menu_group'];
                    $map[$m['slug']]['sort_order'] = (int)$m['sort_order'];
                    $map[$m['slug']]['is_core']    = (int)$m['is_core'];
                }
            }

            $this->registry = $map;
        } catch (Throwable $e) {
            $this->registry = self::$defaultMap;
        }
    }

    public function getPage(string $slug, string $key): ?string {
        return $this->registry[$slug]['pages'][$key] ?? null;
    }

    public function getInclude(string $slug, string $key): ?string {
        return $this->registry[$slug]['includes'][$key] ?? null;
    }

    public function getCron(string $slug, string $key): ?string {
        return $this->registry[$slug]['cron'][$key] ?? null;
    }

    public function getAll(): array {
        return $this->registry ?? [];
    }

    public function getByGroup(string $group): array {
        return array_filter($this->registry, fn($m) => $m['menu_group'] === $group);
    }

    public function getGroups(): array {
        $groups = [];
        foreach ($this->registry as $m) {
            $g = $m['menu_group'];
            if (!in_array($g, self::$dynamicGroups, true)) continue;

            if (!isset($groups[$g])) {
                $groups[$g] = [
                    'slug'    => $g,
                    'title'   => $this->getGroupTitle($g),
                    'sort'    => $m['sort_order'] ?? 999,
                    'modules' => [],
                ];
            }
            $groups[$g]['modules'][] = $m;
        }
        uasort($groups, fn($a, $b) => $a['sort'] <=> $b['sort']);
        return $groups;
    }

    public function getGroupTitle(string $slug): string {
        return match($slug) {
            'content'      => 'مدیریت محتوا',
            'tools'        => 'ابزارها',
            'appearance'   => 'ظاهر سایت',
            'reports'      => 'گزارش‌گیری',
            'seo'          => 'SEO و بهینه‌سازی',
            'admin'        => 'سیستم',
            'i18n'         => 'چند زبانی',
            'translation'  => 'ترجمه محتوا',
            'security'     => 'امنیت',
            'backup_auto'  => 'پشتیبان‌گیری خودکار',
            'tools_pro'    => 'ابزارهای حرفه‌ای',
            default        => '📦 ' . $slug,
        };
    }

    public function persistToDb(): array {
        $result = ['inserted' => 0, 'errors' => []];
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS module_files (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    module_slug VARCHAR(50) NOT NULL,
                    file_type ENUM('pages','includes','cron') NOT NULL,
                    file_key VARCHAR(50) NOT NULL,
                    file_path VARCHAR(255) NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_mod_file (module_slug, file_type, file_key),
                    INDEX idx_slug (module_slug)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        } catch (Throwable $e) {
            $result['errors'][] = 'CREATE TABLE: ' . $e->getMessage();
            return $result;
        }
        foreach (self::$defaultMap as $slug => $info) {
            foreach (['pages', 'includes', 'cron'] as $type) {
                if (empty($info[$type])) continue;
                foreach ($info[$type] as $key => $path) {
                    try {
                        $stmt = $this->pdo->prepare("
                            INSERT INTO module_files (module_slug, file_type, file_key, file_path)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE file_path = VALUES(file_path)
                        ");
                        $stmt->execute([$slug, $type, $key, $path]);
                        $result['inserted']++;
                    } catch (Throwable $e) {
                        $result['errors'][] = "$slug.$type.$key: " . $e->getMessage();
                    }
                }
            }
        }
        return $result;
    }

    public function registerModulesInDb(): array {
        $result = ['registered' => 0, 'updated' => 0, 'errors' => []];
        foreach (self::$defaultMap as $slug => $info) {
            try {
                $stmt = $this->pdo->prepare("SELECT id FROM modules WHERE slug = ? LIMIT 1");
                $stmt->execute([$slug]);
                $existing = $stmt->fetchColumn();

                if ($existing) {
                    $stmt = $this->pdo->prepare("
                        UPDATE modules SET
                            menu_group = ?, sort_order = ?,
                            icon = COALESCE(NULLIF(icon, ''), ?)
                        WHERE slug = ?
                    ");
                    $stmt->execute([
                        $info['menu_group'] ?? 'tools_pro',
                        $info['sort_order'] ?? 100,
                        $info['icon'] ?? '📦',
                        $slug,
                    ]);
                    $result['updated']++;
                } else {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO modules (slug, name, description, version, is_enabled, is_core, icon, menu_group, sort_order)
                        VALUES (?, ?, ?, '1.0.0', 1, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $slug,
                        $info['name'],
                        $info['name'] . ' — ماژول PartoCMS',
                        $info['is_core'] ?? 0,
                        $info['icon'] ?? '📦',
                        $info['menu_group'] ?? 'tools_pro',
                        $info['sort_order'] ?? 100,
                    ]);
                    $result['registered']++;
                }
            } catch (Throwable $e) {
                $result['errors'][] = "$slug: " . $e->getMessage();
            }
        }
        return $result;
    }

    public function findModuleByPath(string $path): ?array {
        $path = ltrim($path, '/');
        foreach ($this->registry as $slug => $info) {
            foreach (['pages', 'includes', 'cron'] as $type) {
                if (!empty($info[$type])) {
                    foreach ($info[$type] as $key => $p) {
                        if ($p === $path) {
                            return [
                                'module'      => $slug,
                                'module_name' => $info['name'],
                                'type'        => $type,
                                'key'         => $key,
                                'path'        => $p,
                            ];
                        }
                    }
                }
            }
        }
        return null;
    }

    public static function getDynamicGroups(): array {
        return self::$dynamicGroups;
    }
}
