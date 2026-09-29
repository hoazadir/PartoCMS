<?php
/**
 * PartoCMS - Manifest Loader
 * بارگذاری manifest.json ماژول‌ها از منابع مختلف
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 *
 * منابع جستجو (به ترتیب اولویت):
 *   1. modules/{slug}/manifest.json   (ماژول‌های فیزیکی)
 *   2. admin/manifests/{slug}.json    (ماژول‌های core)
 *   3. جدول modules                    (fallback نهایی)
 */

class ManifestLoader
{
    private PDO $pdo;
    private string $rootPath;
    private array $cache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->rootPath = dirname(__DIR__);
    }

    /**
     * بارگذاری manifest یک ماژول
     */
    public function load(string $slug): ?array
    {
        // چک cache
        if (array_key_exists($slug, $this->cache)) {
            return $this->cache[$slug];
        }

        // مسیرهای جستجو
        $paths = [
            $this->rootPath . '/modules/' . $slug . '/manifest.json',
            $this->rootPath . '/admin/manifests/' . $slug . '.json',
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $data = $this->loadJsonFile($path);
                if ($data !== null) {
                    $this->cache[$slug] = $data;
                    return $data;
                }
            }
        }

        // fallback: از جدول modules
        $data = $this->loadFromDatabase($slug);
        $this->cache[$slug] = $data;

        return $data;
    }

    /**
     * بارگذاری همه manifestهای موجود
     */
    public function loadAll(): array
    {
        $result = [];

        // ۱. از جدول modules
        try {
            $modules = $this->pdo->query("
                SELECT slug FROM modules
                WHERE is_enabled = 1
                ORDER BY sort_order ASC, id ASC
            ")->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            return [];
        }

        foreach ($modules as $slug) {
            $manifest = $this->load($slug);
            if ($manifest !== null) {
                $result[$slug] = $manifest;
            }
        }

        return $result;
    }

    /**
     * پاک کردن cache
     */
    public function clearCache(?string $slug = null): void
    {
        if ($slug === null) {
            $this->cache = [];
        } else {
            unset($this->cache[$slug]);
        }
    }

    /**
     * خواندن فایل JSON
     */
    private function loadJsonFile(string $path): ?array
    {
        try {
            $content = @file_get_contents($path);
            if ($content === false) return null;

            $data = json_decode($content, true);
            if (!is_array($data)) return null;

            // normalize
            return $this->normalize($data);

        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * خواندن از جدول modules (fallback)
     */
    private function loadFromDatabase(string $slug): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT slug, name, icon, menu_group, sort_order
                FROM modules
                WHERE slug = ?
                LIMIT 1
            ");
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return null;

            return $this->normalize([
                'slug'       => $row['slug'],
                'name'       => $row['name'],
                'icon'       => $row['icon'] ?: '📄',
                'menu_group' => $row['menu_group'] ?: 'other',
                'sort_order' => (int) $row['sort_order'],
                'menu'       => [
                    'title' => $row['name'],
                    'url'   => '',
                ],
            ]);

        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * یکسان‌سازی ساختار manifest
     */
    private function normalize(array $data): array
    {
        $slug = $data['slug'] ?? '';

        // menu (از دو جا ممکن است بیاید)
        $menu = $data['menu'] ?? [];

        // اگر menu.url نبود، از manifest.url استفاده کن
        if (empty($menu['url']) && !empty($data['url'])) {
            $menu['url'] = $data['url'];
        }

        // اگر menu.title نبود، از name استفاده کن
        if (empty($menu['title']) && !empty($data['name'])) {
            $menu['title'] = $data['name'];
        }

        // آیکون
        $icon = $data['icon'] ?? ($menu['icon'] ?? '📄');

        return [
            'slug'        => $slug,
            'name'        => $data['name'] ?? $slug,
            'version'     => $data['version'] ?? '1.0.0',
            'description' => $data['description'] ?? '',
            'icon'        => $icon,
            'menu_group'  => $data['menu_group'] ?? ($data['parent'] ?? 'other'),
            'sort_order'  => (int) ($data['sort_order'] ?? ($data['order'] ?? 999)),
            'is_core'     => (bool) ($data['is_core'] ?? false),
            'menu'        => [
                'title'   => $menu['title'] ?? $data['name'] ?? $slug,
                'url'     => $menu['url'] ?? '',
                'icon'    => $icon,
                'submenu' => $this->normalizeSubmenu($menu['submenu'] ?? []),
            ],
            'permissions' => $data['permissions'] ?? [],
            'raw'         => $data,
        ];
    }

    /**
     * یکسان‌سازی زیرمنو
     */
    private function normalizeSubmenu(array $submenu): array
    {
        $result = [];
        foreach ($submenu as $item) {
            if (empty($item['title']) || empty($item['url'])) continue;
            $result[] = [
                'title' => $item['title'],
                'url'   => $item['url'],
                'icon'  => $item['icon'] ?? '•',
            ];
        }
        return $result;
    }
}
