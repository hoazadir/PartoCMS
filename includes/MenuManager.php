<?php
/**
 * MenuManager - مدیریت منوهای سایت
 */
class MenuManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * دریافت یک منو با slug
     */
    public function getBySlug($slug) {
        $stmt = $this->pdo->prepare("SELECT * FROM menus WHERE slug = ? AND is_active = 1");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    /**
     * دریافت همه منوها
     */
    public function getAll() {
        return $this->pdo->query("
            SELECT m.*,
                (SELECT COUNT(*) FROM menu_items WHERE menu_id = m.id) as items_count
            FROM menus m
            ORDER BY m.created_at DESC
        ")->fetchAll();
    }

    /**
     * دریافت آیتم‌های یک منو به صورت درختی
     */
    public function getItemsTree($menuId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM menu_items
            WHERE menu_id = ? AND is_active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$menuId]);
        $items = $stmt->fetchAll();
        return $this->buildTree($items);
    }

    /**
     * دریافت آیتم‌های یک منو به صورت تخت (برای ادیتور)
     */
    public function getItemsFlat($menuId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM menu_items
            WHERE menu_id = ?
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$menuId]);
        return $stmt->fetchAll();
    }

    /**
     * ساخت درخت از آیتم‌ها
     */
    private function buildTree($items, $parentId = null) {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * حل URL نهایی آیتم
     */
    public function resolveUrl($item) {
        switch ($item['type']) {
            case 'home':
                return SITE_URL . '/index.php';
            case 'page':
            case 'post':
                if ($item['target_id']) {
                    return SITE_URL . '/post.php?id=' . (int) $item['target_id'];
                }
                return '#';
            case 'category':
                if ($item['target_id']) {
                    $stmt = $this->pdo->prepare("SELECT slug FROM categories WHERE id = ?");
                    $stmt->execute([$item['target_id']]);
                    $slug = $stmt->fetchColumn();
                    return $slug ? SITE_URL . '/category.php?slug=' . urlencode($slug) : '#';
                }
                return '#';
            default:
                $url = $item['url'] ?? '#';
                // اگه URL نسبی بود، SITE_URL اضافه کن
                if ($url !== '#' && !preg_match('#^https?://#', $url) && !preg_match('#^/#', $url)) {
                    return SITE_URL . '/' . $url;
                }
                return $url;
        }
    }

    /**
     * رندر HTML منو
     */
    public function render($slug, $class = 'main-menu', $itemClass = '') {
        $menu = $this->getBySlug($slug);
        if (!$menu) return '';

        $items = $this->getItemsTree($menu['id']);
        return $this->renderItems($items, $class, $itemClass, 0);
    }

    private function renderItems($items, $class, $itemClass, $level) {
        if (empty($items)) return '';

        $html = $level === 0
            ? '<ul class="' . htmlspecialchars($class) . '">'
            : '<ul class="submenu">';

        foreach ($items as $item) {
            $hasChildren = !empty($item['children']);
            $url = $this->resolveUrl($item);
            $target = !empty($item['target_blank']) ? ' target="_blank" rel="noopener"' : '';

            $html .= '<li class="' . htmlspecialchars($itemClass) . ($hasChildren ? ' has-children' : '') . '">';
            $html .= '<a href="' . htmlspecialchars($url) . '"' . $target . '>';

            if (!empty($item['icon'])) {
                $html .= '<span class="menu-icon">' . htmlspecialchars($item['icon']) . '</span> ';
            }
            $html .= htmlspecialchars($item['title']);

            if ($hasChildren) {
                $html .= ' <i class="bi bi-chevron-down"></i>';
            }

            $html .= '</a>';

            if ($hasChildren) {
                $html .= $this->renderItems($item['children'], $class, $itemClass, $level + 1);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        return $html;
    }

    /**
     * ذخیره ترتیب جدید (drag & drop)
     */
    public function updateOrder($items, $menuId, $parentId = null, &$order = 1) {
        foreach ($items as $item) {
            if (empty($item['id'])) continue;

            $stmt = $this->pdo->prepare("
                UPDATE menu_items SET parent_id = ?, sort_order = ?
                WHERE id = ? AND menu_id = ?
            ");
            $stmt->execute([$parentId, $order, (int) $item['id'], $menuId]);
            $order++;

            if (!empty($item['children'])) {
                $this->updateOrder($item['children'], $menuId, (int) $item['id'], $order);
            }
        }
    }
}
