<?php
/**
 * TemplateRenderer - تبدیل قالب GrapesJS به صفحه داینامیک
 *
 * استفاده از placeholder ها:
 *   {{post.title}}
 *   {{post.content}}
 *   {{post.excerpt}}
 *   {{post.featured_image}}
 *   {{post.author}}
 *   {{post.date}}
 *   {{post.category}}
 *   {{site.name}}
 *   {{site.url}}
 *   {{site.description}}
 *   {{menu:main-menu}}
 *   {{comments}}
 */
class TemplateRenderer {
    private $pdo;
    private $data = [];
    private $placeholders = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->registerDefaultPlaceholders();
    }

    /**
     * رندر قالب با داده‌های مشخص
     */
    public function render($templateId, $postId = null, $context = 'post') {
        $template = $this->loadTemplate($templateId);
        if (!$template) {
            return $this->fallback($postId);
        }

        // بارگذاری محتوا
        if ($postId) {
            $this->loadPost($postId);
        }

        // بارگذاری تنظیمات سایت
        $this->loadSiteSettings();

        // جایگزینی placeholder ها
        $html = $template['content'];
        $css = $template['css'];

        $html = $this->replacePlaceholders($html);
        $css = $this->replacePlaceholders($css);

        // اضافه کردن CSS به head
        $head = "<style>{$css}</style>";

        // اگه تگ <html> داره، CSS رو داخل head تزریق کن
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $head . '</head>', $html);
        } else {
            // اگه قالب کامل نبود، خودمون head اضافه می‌کنیم
            $html = $this->wrapInDocument($html, $css);
        }

        return $html;
    }

    /**
     * بارگذاری قالب از دیتابیس
     */
    private function loadTemplate($templateId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT t.*, 
                       (SELECT GROUP_CONCAT(CONCAT(p.id, ':', p.name) SEPARATOR '|') 
                        FROM pages p WHERE p.template_id = t.id) as pages_data
                FROM templates t
                WHERE t.id = ?
            ");
            $stmt->execute([$templateId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            // اگه ستون‌های جدید نبودن، ساده بگیر
            try {
                $stmt = $this->pdo->prepare("SELECT * FROM templates WHERE id = ?");
                $stmt->execute([$templateId]);
                return $stmt->fetch();
            } catch (Exception $e2) {
                return null;
            }
        }
    }

    /**
     * بارگذاری محتوا
     */
    private function loadPost($postId) {
        $stmt = $this->pdo->prepare("
            SELECT ci.*, 
                   ct.name as type_name, ct.icon as type_icon,
                   u.username as author_name, u.email as author_email,
                   c.name as category_name, c.slug as category_slug, 
                   c.color as category_color, c.icon as category_icon
            FROM content_items ci
            LEFT JOIN content_types ct ON ct.id = ci.type_id
            LEFT JOIN users u ON u.id = ci.author_id
            LEFT JOIN categories c ON c.id = ci.category_id
            WHERE ci.id = ?
        ");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();

        if (!$post) return;

        // محاسبه زمان مطالعه
        $wordCount = str_word_count(strip_tags($post['content'] ?? ''));
        $readTime = max(1, ceil($wordCount / 200));

        // بارگذاری دیدگاه‌ها
        $comments = $this->loadComments($postId);

        // بارگذاری مقادیر
        $this->data = [
            'post.id' => $post['id'],
            'post.title' => $post['title'] ?? '',
            'post.slug' => $post['slug'] ?? '',
            'post.content' => $post['content'] ?? '',
            'post.excerpt' => $post['excerpt'] ?? '',
            'post.featured_image' => $post['featured_image'] ?? '',
            'post.author' => $post['author_name'] ?? 'ناشناس',
            'post.author_email' => $post['author_email'] ?? '',
            'post.date' => isset($post['created_at']) ? date('Y/m/d', strtotime($post['created_at'])) : '',
            'post.date_full' => isset($post['created_at']) ? date('Y/m/d H:i', strtotime($post['created_at'])) : '',
            'post.date_iso' => isset($post['created_at']) ? date('c', strtotime($post['created_at'])) : '',
            'post.views' => $post['views'] ?? 0,
            'post.read_time' => $readTime . ' دقیقه',
            'post.category' => $post['category_name'] ?? '',
            'post.category_slug' => $post['category_slug'] ?? '',
            'post.category_icon' => $post['category_icon'] ?? '',
            'post.category_color' => $post['category_color'] ?? '#3498db',
            'post.type' => $post['type_name'] ?? '',
            'post.type_icon' => $post['type_icon'] ?? '📄',
            'post.url' => SITE_URL . '/post.php?id=' . $post['id'],
            'post.comments' => $comments,
            'post.comments_count' => substr_count($comments, 'comment-item'),
        ];

        // اضافه کردن URL های کوتاه
        $this->data['url'] = $this->data['post.url'];
        $this->data['title'] = $this->data['post.title'];
    }

    /**
     * بارگذاری دیدگاه‌ها به صورت HTML
     */
    private function loadComments($postId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT c.* FROM comments c
                WHERE c.content_id = ? AND c.status = 'approved' AND c.parent_id IS NULL
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$postId]);
            $comments = $stmt->fetchAll();

            if (empty($comments)) {
                return '<p style="text-align:center;color:#95a5a6;padding:20px;">هنوز دیدگاهی ثبت نشده است.</p>';
            }

            $html = '<div class="comments-list">';
            foreach ($comments as $c) {
                $initial = mb_substr($c['author_name'], 0, 1);
                $date = date('Y/m/d H:i', strtotime($c['created_at']));
                $text = nl2br(htmlspecialchars($c['comment']));
                $name = htmlspecialchars($c['author_name']);

                $html .= "<div class='comment-item' style='background:#f8f9fa;border-radius:10px;padding:15px;margin-bottom:10px;border-right:4px solid #3498db;'>
                    <div style='display:flex;gap:12px;'>
                        <div style='width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;'>{$initial}</div>
                        <div style='flex:1;'>
                            <div style='display:flex;justify-content:space-between;margin-bottom:6px;'>
                                <strong style='color:#2c3e50;'>{$name}</strong>
                                <small style='color:#95a5a6;'>{$date}</small>
                            </div>
                            <div style='line-height:1.8;color:#333;'>{$text}</div>
                        </div>
                    </div>
                </div>";
            }
            $html .= '</div>';
            return $html;
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * بارگذاری تنظیمات سایت
     */
    private function loadSiteSettings() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch()) {
                $this->data['site.' . $row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {}

        // مقادیر پیش‌فرض
        $defaults = [
            'site.name' => 'وب‌سایت من',
            'site.description' => '',
            'site.url' => SITE_URL,
            'site.logo' => '',
            'site.year' => date('Y'),
            'site.current_url' => SITE_URL . ($_SERVER['REQUEST_URI'] ?? ''),
        ];

        foreach ($defaults as $key => $val) {
            if (!isset($this->data[$key])) {
                $this->data[$key] = $val;
            }
        }
    }

    /**
     * جایگزینی placeholder ها
     */
    private function replacePlaceholders($text) {
        // جایگزینی ساده {{key}}
        foreach ($this->data as $key => $value) {
            if (is_array($value)) continue;
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }

        // Placeholder های خاص
        $text = preg_replace_callback('/\{\{menu:([a-z0-9_-]+)\}\}/', function($m) {
            return function_exists('renderMenu') ? renderMenu($m[1], 'main-nav') : '';
        }, $text);

        $text = preg_replace_callback('/\{\{form:([a-z0-9_-]+)\}\}/', function($m) {
            return $this->renderFormPlaceholder($m[1]);
        }, $text);

        $text = preg_replace_callback('/\{\{category:([a-z0-9_-]+)\}\}/', function($m) {
            return $this->renderCategoryPlaceholder($m[1]);
        }, $text);

        // پاک کردن placeholder های بی‌استفاده
        $text = preg_replace('/\{\{[a-z0-9._-]+\}\}/i', '', $text);

        return $text;
    }

    /**
     * رندر فرم در قالب
     */
    private function renderFormPlaceholder($slug) {
        try {
            $stmt = $this->pdo->prepare("SELECT id, name, fields FROM forms WHERE slug = ? AND is_active = 1");
            $stmt->execute([$slug]);
            $form = $stmt->fetch();

            if (!$form) return '';

            $fields = json_decode($form['fields'] ?? '[]', true) ?: [];
            $html = '<form method="post" action="' . SITE_URL . '/form.php?slug=' . urlencode($slug) . '">';
            $html .= '<input type="hidden" name="form_id" value="' . $form['id'] . '">';

            foreach ($fields as $f) {
                $name = htmlspecialchars($f['name'] ?? '');
                $label = htmlspecialchars($f['label'] ?? $name);
                $req = !empty($f['required']) ? 'required' : '';
                $reqStar = !empty($f['required']) ? ' *' : '';
                $ph = htmlspecialchars($f['placeholder'] ?? '');

                $html .= '<div style="margin-bottom:15px;">';
                $html .= '<label style="display:block;margin-bottom:6px;font-weight:bold;font-size:13px;color:#34495e;">' . $label . $reqStar . '</label>';

                switch ($f['type']) {
                    case 'textarea':
                        $html .= '<textarea name="' . $name . '" placeholder="' . $ph . '" rows="' . ($f['rows'] ?? 4) . '" ' . $req . ' style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;"></textarea>';
                        break;
                    case 'select':
                        $html .= '<select name="' . $name . '" ' . $req . ' style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;">';
                        $html .= '<option value="">— انتخاب کنید —</option>';
                        foreach ($f['options'] ?? [] as $opt) {
                            $html .= '<option value="' . htmlspecialchars($opt) . '">' . htmlspecialchars($opt) . '</option>';
                        }
                        $html .= '</select>';
                        break;
                    case 'checkbox':
                        $html .= '<label style="display:flex;align-items:center;gap:8px;font-weight:normal;"><input type="checkbox" name="' . $name . '" value="1" ' . $req . '> ' . $label . '</label>';
                        break;
                    default:
                        $type = in_array($f['type'], ['text', 'email', 'tel', 'number', 'date', 'url']) ? $f['type'] : 'text';
                        $html .= '<input type="' . $type . '" name="' . $name . '" placeholder="' . $ph . '" ' . $req . ' style="width:100%;padding:10px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;">';
                }
                $html .= '</div>';
            }

            $html .= '<button type="submit" style="background:linear-gradient(135deg,#3498db,#2980b9);color:#fff;border:none;padding:12px 30px;border-radius:8px;font-family:Tahoma;font-weight:bold;cursor:pointer;">' . htmlspecialchars($form['submit_label'] ?? 'ارسال') . '</button>';
            $html .= '</form>';
            return $html;
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * رندر دسته در قالب
     */
    private function renderCategoryPlaceholder($slug) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM categories WHERE slug = ? AND is_active = 1");
            $stmt->execute([$slug]);
            $cat = $stmt->fetch();
            if (!$cat) return '';

            $stmt = $this->pdo->prepare("
                SELECT id, title, featured_image, excerpt, content, created_at
                FROM content_items
                WHERE category_id = ? AND status = 'published'
                ORDER BY created_at DESC
                LIMIT 6
            ");
            $stmt->execute([$cat['id']]);
            $posts = $stmt->fetchAll();

            $html = '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:15px;">';
            foreach ($posts as $p) {
                $url = SITE_URL . '/post.php?id=' . $p['id'];
                $title = htmlspecialchars($p['title']);
                $excerpt = htmlspecialchars(mb_substr($p['excerpt'] ?: strip_tags($p['content']), 0, 80));
                $img = $p['featured_image']
                    ? '<img src="' . htmlspecialchars($p['featured_image']) . '" style="width:100%;height:150px;object-fit:cover;">'
                    : '<div style="width:100%;height:150px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;color:#fff;font-size:40px;">📄</div>';

                $html .= "<div style='background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06);'>
                    {$img}
                    <div style='padding:15px;'>
                        <h4 style='margin:0 0 8px;font-size:16px;color:#2c3e50;'>{$title}</h4>
                        <p style='color:#7f8c8d;font-size:13px;margin:0 0 10px;'>{$excerpt}...</p>
                        <a href='{$url}' style='color:#3498db;text-decoration:none;font-size:13px;font-weight:bold;'>بیشتر →</a>
                    </div>
                </div>";
            }
            $html .= '</div>';
            return $html;
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * پیچیدن در HTML کامل
     */
    private function wrapInDocument($bodyHtml, $css) {
        $siteName = htmlspecialchars($this->data['site.name'] ?? 'وب‌سایت من');
        return "<!DOCTYPE html>
<html lang=\"fa\" dir=\"rtl\">
<head>
<meta charset=\"UTF-8\">
<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
<title>{$siteName}</title>
<style>{$css}</style>
<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css\">
</head>
<body>
{$bodyHtml}
</body>
</html>";
    }

    /**
     * نمایش پیش‌فرض در صورت نبود قالب
     */
    private function fallback($postId) {
        if (!$postId) {
            return '<div style="padding:50px;text-align:center;font-family:Tahoma;">محتوایی یافت نشد</div>';
        }
        $this->loadPost($postId);
        $this->loadSiteSettings();

        $title = htmlspecialchars($this->data['post.title'] ?? '');
        $content = $this->data['post.content'] ?? '';

        return "<!DOCTYPE html>
<html lang=\"fa\" dir=\"rtl\">
<head>
<meta charset=\"UTF-8\">
<title>{$title}</title>
<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css\">
<style>body{font-family:Tahoma;background:#f8f9fa;padding:20px;}</style>
</head>
<body>
<div style=\"max-width:800px;margin:auto;background:#fff;padding:30px;border-radius:10px;\">
<h1>{$title}</h1>
{$content}
</div>
</body>
</html>";
    }

    /**
     * دریافت همه placeholder های موجود (برای نمایش در ادیتور)
     */
    public static function getAvailablePlaceholders() {
        return [
            'محتوا' => [
                '{{post.title}}' => 'عنوان مقاله',
                '{{post.content}}' => 'محتوای کامل',
                '{{post.excerpt}}' => 'خلاصه',
                '{{post.featured_image}}' => 'تصویر شاخص',
                '{{post.author}}' => 'نویسنده',
                '{{post.date}}' => 'تاریخ (1403/01/15)',
                '{{post.date_full}}' => 'تاریخ و ساعت',
                '{{post.read_time}}' => 'زمان مطالعه',
                '{{post.views}}' => 'تعداد بازدید',
            ],
            'دسته‌بندی' => [
                '{{post.category}}' => 'نام دسته',
                '{{post.category_icon}}' => 'آیکون دسته',
                '{{post.category_color}}' => 'رنگ دسته',
                '{{post.category_slug}}' => 'نامک دسته',
            ],
            'سایت' => [
                '{{site.name}}' => 'نام سایت',
                '{{site.description}}' => 'توضیحات',
                '{{site.url}}' => 'آدرس سایت',
                '{{site.year}}' => 'سال جاری',
            ],
            'کامپوننت‌ها' => [
                '{{post.comments}}' => 'لیست دیدگاه‌ها',
                '{{menu:main-menu}}' => 'منوی اصلی',
                '{{menu:footer-menu}}' => 'منوی فوتر',
                '{{form:contact}}' => 'فرم تماس',
                '{{category:news}}' => 'آخرین مقالات یک دسته',
            ],
        ];
    }
}
