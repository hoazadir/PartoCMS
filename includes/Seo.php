<?php
/**
 * Seo - مدیریت متاتگ‌ها، Sitemap و robots
 */
class Seo {
    private $pdo;
    private $meta = [];
    private $settings = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    private function loadSettings() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM seo_settings");
            while ($row = $stmt->fetch()) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {}
    }

    public function set($key, $value) {
        $this->meta[$key] = $value;
    }

    public function setPost($post) {
        $siteName = $this->settings['default_meta_title'] ?? getSetting('site_name', 'وب‌سایت من');
        $siteDesc = $this->settings['default_meta_description'] ?? getSetting('site_description', '');

        $this->meta['title'] = !empty($post['meta_title']) 
            ? $post['meta_title'] 
            : $post['title'] . ' | ' . $siteName;
        
        $this->meta['description'] = !empty($post['meta_description']) 
            ? $post['meta_description'] 
            : mb_substr(strip_tags($post['excerpt'] ?: $post['content']), 0, 160);
        
        $this->meta['keywords'] = $post['meta_keywords'] ?? $this->settings['default_meta_keywords'] ?? '';
        $this->meta['canonical'] = $post['canonical_url'] ?? (SITE_URL . '/post.php?id=' . $post['id']);
        $this->meta['no_index'] = !empty($post['no_index']);
        
        // Open Graph
        $this->meta['og_title'] = $post['meta_title'] ?? $post['title'];
        $this->meta['og_description'] = $this->meta['description'];
        $this->meta['og_image'] = $post['og_image'] ?: ($post['featured_image'] ?? '');
        $this->meta['og_url'] = $this->meta['canonical'];
        $this->meta['og_type'] = 'article';
        $this->meta['og_site_name'] = $this->settings['og_site_name'] ?? $siteName;
        
        // Twitter
        $this->meta['twitter_card'] = 'summary_large_image';
        $this->meta['twitter_handle'] = $this->settings['twitter_handle'] ?? '';
    }

    public function setPage($title, $description = '') {
        $siteName = getSetting('site_name', 'وب‌سایت من');
        $this->meta['title'] = $title . ' | ' . $siteName;
        $this->meta['description'] = $description;
        $this->meta['canonical'] = SITE_URL . ($_SERVER['REQUEST_URI'] ?? '/');
    }

    public function render() {
        if (empty($this->meta['title'])) {
            $this->meta['title'] = $this->settings['default_meta_title'] ?? getSetting('site_name', 'وب‌سایت من');
        }
        if (empty($this->meta['description'])) {
            $this->meta['description'] = $this->settings['default_meta_description'] ?? getSetting('site_description', '');
        }

        $html = "\n<!-- SEO Meta Tags -->\n";

        // Basic
        if (!empty($this->meta['title'])) {
            $html .= '<title>' . htmlspecialchars($this->meta['title']) . "</title>\n";
        }
        if (!empty($this->meta['description'])) {
            $html .= '<meta name="description" content="' . htmlspecialchars($this->meta['description']) . "\">\n";
        }
        if (!empty($this->meta['keywords'])) {
            $html .= '<meta name="keywords" content="' . htmlspecialchars($this->meta['keywords']) . "\">\n";
        }
        if (!empty($this->meta['canonical'])) {
            $html .= '<link rel="canonical" href="' . htmlspecialchars($this->meta['canonical']) . "\">\n";
        }
        if (!empty($this->meta['no_index'])) {
            $html .= "<meta name=\"robots\" content=\"noindex, nofollow\">\n";
        } else {
            $html .= "<meta name=\"robots\" content=\"index, follow\">\n";
        }

        // Open Graph
        if (!empty($this->meta['og_title'])) {
            $html .= '<meta property="og:title" content="' . htmlspecialchars($this->meta['og_title']) . "\">\n";
        }
        if (!empty($this->meta['og_description'])) {
            $html .= '<meta property="og:description" content="' . htmlspecialchars($this->meta['og_description']) . "\">\n";
        }
        if (!empty($this->meta['og_image'])) {
            $html .= '<meta property="og:image" content="' . htmlspecialchars($this->meta['og_image']) . "\">\n";
        }
        if (!empty($this->meta['og_url'])) {
            $html .= '<meta property="og:url" content="' . htmlspecialchars($this->meta['og_url']) . "\">\n";
        }
        if (!empty($this->meta['og_type'])) {
            $html .= '<meta property="og:type" content="' . htmlspecialchars($this->meta['og_type']) . "\">\n";
        }
        if (!empty($this->meta['og_site_name'])) {
            $html .= '<meta property="og:site_name" content="' . htmlspecialchars($this->meta['og_site_name']) . "\">\n";
        }

        // Twitter
        if (!empty($this->meta['twitter_card'])) {
            $html .= '<meta name="twitter:card" content="' . htmlspecialchars($this->meta['twitter_card']) . "\">\n";
        }
        if (!empty($this->meta['twitter_handle'])) {
            $html .= '<meta name="twitter:site" content="' . htmlspecialchars($this->meta['twitter_handle']) . "\">\n";
        }
        if (!empty($this->meta['og_title'])) {
            $html .= '<meta name="twitter:title" content="' . htmlspecialchars($this->meta['og_title']) . "\">\n";
        }
        if (!empty($this->meta['og_description'])) {
            $html .= '<meta name="twitter:description" content="' . htmlspecialchars($this->meta['og_description']) . "\">\n";
        }
        if (!empty($this->meta['og_image'])) {
            $html .= '<meta name="twitter:image" content="' . htmlspecialchars($this->meta['og_image']) . "\">\n";
        }

        // Google Analytics
        if (!empty($this->settings['google_analytics_id'])) {
            $gaId = htmlspecialchars($this->settings['google_analytics_id']);
            $html .= "\n<!-- Google Analytics -->\n";
            $html .= "<script async src=\"https://www.googletagmanager.com/gtag/js?id={$gaId}\"></script>\n";
            $html .= "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{$gaId}');</script>\n";
        }

        return $html;
    }

    // ==================== Sitemap ====================
    
    public function generateSitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // صفحه اصلی
        $xml .= $this->sitemapUrl(SITE_URL . '/', date('Y-m-d'), '1.0', 'daily');

        // مقالات
        $posts = $this->pdo->query("
            SELECT id, updated_at, created_at 
            FROM content_items 
            WHERE status = 'published' AND no_index = 0
            ORDER BY updated_at DESC
        ")->fetchAll();

        foreach ($posts as $p) {
            $xml .= $this->sitemapUrl(
                SITE_URL . '/post.php?id=' . $p['id'],
                date('Y-m-d', strtotime($p['updated_at'] ?? $p['created_at'])),
                '0.8',
                'weekly'
            );
        }

        // دسته‌بندی‌ها
        $cats = $this->pdo->query("SELECT slug FROM categories WHERE is_active = 1")->fetchAll();
        foreach ($cats as $c) {
            $xml .= $this->sitemapUrl(
                SITE_URL . '/category.php?slug=' . urlencode($c['slug']),
                date('Y-m-d'),
                '0.6',
                'weekly'
            );
        }

        $xml .= '</urlset>';
        return $xml;
    }

    private function sitemapUrl($loc, $lastmod, $priority, $changefreq) {
        return "  <url>\n"
            . "    <loc>" . htmlspecialchars($loc) . "</loc>\n"
            . "    <lastmod>{$lastmod}</lastmod>\n"
            . "    <changefreq>{$changefreq}</changefreq>\n"
            . "    <priority>{$priority}</priority>\n"
            . "  </url>\n";
    }

    // ==================== Robots.txt ====================
    
    public function generateRobots() {
        $robots = $this->settings['robots_txt'] ?? "User-agent: *\nAllow: /";
        $robots .= "\n\nSitemap: " . SITE_URL . "/sitemap.php";
        return $robots;
    }
}
