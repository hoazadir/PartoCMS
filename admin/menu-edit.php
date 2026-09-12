<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
$perm->require('content.view');

$pdo = getDB();
$mm = getMenuManager();
$siteName = getSetting('site_name', 'وب‌سایت من');
$id = (int) ($_GET['id'] ?? 0);
$success = '';
$error = '';

if (!$id) {
    header('Location: menus.php');
    exit;
}

// دریافت منو
$stmt = $pdo->prepare("SELECT * FROM menus WHERE id = ?");
$stmt->execute([$id]);
$menu = $stmt->fetch();
if (!$menu) {
    header('Location: menus.php');
    exit;
}

// ذخیره تنظیمات منو
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_menu') {
        $name = trim($_POST['name']);
        $location = $_POST['location'] ?? 'header';
        $description = trim($_POST['description'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE menus SET name=?, location=?, description=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $location, $description, $isActive, $id]);
        header('Location: menu-edit.php?id=' . $id . '&msg=saved');
        exit;
    }

    if ($_POST['action'] === 'add_item') {
        $title = trim($_POST['item_title'] ?? '');
        $type = $_POST['item_type'] ?? 'custom';
        $url = trim($_POST['item_url'] ?? '#');
        $targetId = !empty($_POST['target_id']) ? (int) $_POST['target_id'] : null;
        $icon = trim($_POST['item_icon'] ?? '');
        $targetBlank = isset($_POST['target_blank']) ? 1 : 0;
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;

        if ($title) {
            // حداکثر sort_order
            $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM menu_items WHERE menu_id = ?");
            $maxOrder->execute([$id]);
            $nextOrder = $maxOrder->fetchColumn();

            $stmt = $pdo->prepare("
                INSERT INTO menu_items (menu_id, parent_id, title, url, type, target_id, icon, target_blank, sort_order, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([$id, $parentId, $title, $url, $type, $targetId, $icon, $targetBlank, $nextOrder]);
            header('Location: menu-edit.php?id=' . $id . '&msg=added');
            exit;
        }
    }

    if ($_POST['action'] === 'update_order') {
        $tree = json_decode($_POST['tree'] ?? '[]', true);
        if (is_array($tree)) {
            $order = 1;
            $mm->updateOrder($tree, $id, null, $order);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// حذف آیتم
if (isset($_GET['delete_item']) && is_numeric($_GET['delete_item'])) {
    // حذف فرزندان هم به‌خاطر foreign key CASCADE انجام می‌شه
    $pdo->prepare("DELETE FROM menu_items WHERE id = ? AND menu_id = ?")->execute([$_GET['delete_item'], $id]);
    header('Location: menu-edit.php?id=' . $id . '&msg=item_deleted');
    exit;
}

// تغییر وضعیت آیتم
if (isset($_GET['toggle_item']) && is_numeric($_GET['toggle_item'])) {
    $pdo->prepare("UPDATE menu_items SET is_active = NOT is_active WHERE id = ? AND menu_id = ?")->execute([$_GET['toggle_item'], $id]);
    header('Location: menu-edit.php?id=' . $id . '&msg=toggled');
    exit;
}

if (isset($_GET['msg'])) {
    $msgs = ['saved' => 'منو ذخیره شد', 'added' => 'آیتم اضافه شد', 'item_deleted' => 'آیتم حذف شد', 'toggled' => 'وضعیت تغییر کرد'];
    $success = $msgs[$_GET['msg']] ?? '';
}

// آیتم‌های تخت
$items = $mm->getItemsFlat($id);

// ساخت درخت
$tree = [];
$itemsById = [];
foreach ($items as $i) {
    $itemsById[$i['id']] = $i;
    $itemsById[$i['id']]['children'] = [];
}
foreach ($items as $i) {
    if ($i['parent_id'] && isset($itemsById[$i['parent_id']])) {
        $itemsById[$i['parent_id']]['children'][] = &$itemsById[$i['id']];
    } else {
        $tree[] = &$itemsById[$i['id']];
    }
}

// داده‌های کمکی برای انتخاب هدف
$pages = $pdo->query("SELECT id, title FROM content_items WHERE status='published' AND type_id IN (SELECT id FROM content_types WHERE slug IN ('page','post')) ORDER BY title LIMIT 100")->fetchAll();
$categories = $pdo->query("SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name")->fetchAll();

// تابع بازگشتی رندر آیتم‌ها
function renderItemTree($items, $level = 0) {
    foreach ($items as $item) {
        $padding = $level * 25;
        $hasChildren = !empty($item['children']);
        ?>
        <div class="menu-item" data-id="<?= $item['id'] ?>" style="padding-right: <?= $padding ?>px;">
            <div class="item-inner">
                <span class="drag-handle" title="جابجایی">⋮⋮</span>
                <span class="item-icon"><?= htmlspecialchars($item['icon'] ?: '📄') ?></span>
                <span class="item-title">
                    <?= htmlspecialchars($item['title']) ?>
                    <?php if ($item['type'] !== 'custom'): ?>
                        <span class="item-type"><?= $item['type'] ?></span>
                    <?php endif; ?>
                    <?php if (!$item['is_active']): ?>
                        <span class="badge bg-secondary" style="font-size:9px;">غیرفعال</span>
                    <?php endif; ?>
                </span>
                <span class="item-url" title="<?= htmlspecialchars($item['url']) ?>"><?= htmlspecialchars(mb_substr($item['url'], 0, 35)) ?></span>
                <div class="item-actions">
                    <button type="button" class="btn btn-xs btn-outline-primary" onclick="editItem(<?= htmlspecialchars(json_encode($item)) ?>)">✏️</button>
                    <a href="?id=<?= $item['menu_id'] ?>&toggle_item=<?= $item['id'] ?>" class="btn btn-xs btn-outline-warning"><?= $item['is_active'] ? '👁' : '🚫' ?></a>
                    <a href="?id=<?= $item['menu_id'] ?>&delete_item=<?= $item['id'] ?>" class="btn btn-xs btn-outline-danger" onclick="return confirm('حذف این آیتم<?= $hasChildren ? ' و همه زیرآیتم‌هایش' : '' ?>؟')">🗑</a>
                </div>
            </div>
            <?php if ($hasChildren): ?>
                <div class="children">
                    <?php renderItemTree($item['children'], $level + 1); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش منو: <?= htmlspecialchars($menu['name']) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; margin-bottom: 20px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; border-radius: 10px 10px 0 0 !important; padding: 15px 20px; font-weight: bold; }

        /* Menu Builder */
        .menu-builder { background: #f8f9fa; border-radius: 8px; padding: 15px; min-height: 200px; }
        .menu-item { margin-bottom: 8px; }
        .menu-item .item-inner {
            background: #fff; border: 2px solid #e0e0e0; border-radius: 8px;
            padding: 10px 15px; display: flex; align-items: center; gap: 12px;
            transition: all 0.2s; cursor: move;
        }
        .menu-item .item-inner:hover { border-color: #3498db; box-shadow: 0 2px 8px rgba(52,152,219,0.15); }
        .menu-item.dragging .item-inner { opacity: 0.5; }
        .drag-handle { color: #95a5a6; font-size: 16px; cursor: grab; user-select: none; }
        .item-icon { font-size: 18px; }
        .item-title { font-weight: bold; color: #2c3e50; font-size: 14px; flex: 1; }
        .item-type { display: inline-block; background: #e7f1ff; color: #2980b9; padding: 1px 6px; border-radius: 8px; font-size: 10px; margin-right: 5px; }
        .item-url { font-family: monospace; font-size: 11px; color: #95a5a6; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .item-actions { display: flex; gap: 3px; }
        .btn-xs { padding: 3px 7px; font-size: 12px; border-radius: 4px; }
        .children { margin-top: 5px; margin-right: 25px; border-right: 2px dashed #bdc3c7; padding-right: 5px; }
        .empty-state { text-align: center; padding: 40px 20px; color: #95a5a6; }
        .empty-state i { font-size: 50px; display: block; margin-bottom: 15px; }

        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
            .item-url { display: none; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <div>
            <h4 style="margin:0;">✏️ ویرایش منو: <?= htmlspecialchars($menu['name']) ?></h4>
            <small class="text-muted"><code><?= htmlspecialchars($menu['slug']) ?></code></small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" class="btn btn-success btn-sm" onclick="saveOrder()">
                <i class="bi bi-save"></i> ذخیره ترتیب
            </button>
            <a href="menus.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="row">
        <!-- ستون اصلی: آیتم‌های منو -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>🎯 آیتم‌های منو (<?= count($items) ?>)</span>
                    <small class="text-muted">با کشیدن، جابجا کنید</small>
                </div>
                <div class="card-body">
                    <div class="menu-builder" id="menuBuilder">
                        <?php if (empty($tree)): ?>
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h5>هنوز آیتمی اضافه نشده</h5>
                                <p>از فرم کنار برای افزودن آیتم استفاده کنید</p>
                            </div>
                        <?php else: ?>
                            <?php renderItemTree($tree); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ستون کنار: تنظیمات و افزودن -->
        <div class="col-md-4">
            <!-- تنظیمات منو -->
            <div class="card">
                <div class="card-header">⚙️ تنظیمات منو</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="update_menu">
                        <div class="mb-3">
                            <label class="form-label">نام منو</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($menu['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">مکان نمایش</label>
                            <select name="location" class="form-select">
                                <option value="header" <?= $menu['location'] === 'header' ? 'selected' : '' ?>>🔝 هدر</option>
                                <option value="footer" <?= $menu['location'] === 'footer' ? 'selected' : '' ?>>🔻 فوتر</option>
                                <option value="sidebar" <?= $menu['location'] === 'sidebar' ? 'selected' : '' ?>>📑 سایدبار</option>
                                <option value="mobile" <?= $menu['location'] === 'mobile' ? 'selected' : '' ?>>📱 موبایل</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">توضیحات</label>
                            <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($menu['description'] ?? '') ?>">
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" id="menuActive" <?= $menu['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="menuActive">منو فعال باشد</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> ذخیره</button>
                    </form>
                </div>
            </div>

            <!-- افزودن آیتم -->
            <div class="card">
                <div class="card-header">➕ افزودن آیتم جدید</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="add_item">

                        <div class="mb-3">
                            <label class="form-label">عنوان <span class="text-danger">*</span></label>
                            <input type="text" name="item_title" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">آیکون (ایموجی)</label>
                            <input type="text" name="item_icon" class="form-control" placeholder="🏠" maxlength="4">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">نوع</label>
                            <select name="item_type" class="form-select" id="itemType" onchange="toggleTargetField()">
                                <option value="custom">🔗 لینک دلخواه</option>
                                <option value="home">🏠 صفحه اصلی</option>
                                <option value="page">📄 صفحه</option>
                                <option value="post">📝 مقاله</option>
                                <option value="category">🏷 دسته</option>
                            </select>
                        </div>

                        <div class="mb-3" id="urlField">
                            <label class="form-label">آدرس</label>
                            <input type="text" name="item_url" class="form-control" value="#" placeholder="about.php یا https://...">
                        </div>

                        <div class="mb-3" id="pageField" style="display:none;">
                            <label class="form-label">صفحه/مقاله</label>
                            <select name="target_id" class="form-select">
                                <option value="">— انتخاب کنید —</option>
                                <?php foreach ($pages as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3" id="categoryField" style="display:none;">
                            <label class="form-label">دسته</label>
                            <select name="target_id" class="form-select">
                                <option value="">— انتخاب کنید —</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">والد (زیرمنو)</label>
                            <select name="parent_id" class="form-select">
                                <option value="">— بدون والد (آیتم اصلی) —</option>
                                <?php foreach ($items as $it): ?>
                                    <option value="<?= $it['id'] ?>"><?= htmlspecialchars($it['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="target_blank" id="targetBlank">
                            <label class="form-check-label" for="targetBlank">در تب جدید باز شود</label>
                        </div>

                        <button type="submit" class="btn btn-success w-100"><i class="bi bi-plus-lg"></i> افزودن</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// تغییر فیلدها بر اساس نوع
function toggleTargetField() {
    const type = document.getElementById('itemType').value;
    document.getElementById('urlField').style.display = (type === 'custom') ? 'block' : 'none';
    document.getElementById('pageField').style.display = (type === 'page' || type === 'post') ? 'block' : 'none';
    document.getElementById('categoryField').style.display = (type === 'category') ? 'block' : 'none';
}

// Drag & Drop
let dragEl = null;

document.addEventListener('DOMContentLoaded', () => {
    const builder = document.getElementById('menuBuilder');
    if (!builder) return;

    builder.querySelectorAll('.menu-item').forEach(item => {
        const inner = item.querySelector('.item-inner');
        if (!inner) return;
        inner.setAttribute('draggable', 'true');

        inner.addEventListener('dragstart', e => {
            dragEl = item;
            item.classList.add('dragging');
            e.stopPropagation();
        });

        inner.addEventListener('dragend', e => {
            item.classList.remove('dragging');
            dragEl = null;
        });

        item.addEventListener('dragover', e => {
            e.preventDefault();
            if (!dragEl || dragEl === item) return;

            const rect = item.getBoundingClientRect();
            const offset = e.clientY - rect.top;
            const isAfter = offset > rect.height / 2;

            if (isAfter) {
                item.parentNode.insertBefore(dragEl, item.nextSibling);
            } else {
                item.parentNode.insertBefore(dragEl, item);
            }
        });
    });
});

// استخراج ساختار درختی از DOM
function buildTreeFromDOM(parentEl) {
    const tree = [];
    parentEl.childNodes.forEach(node => {
        if (node.nodeType !== 1) return;
        if (!node.classList.contains('menu-item')) return;

        const id = parseInt(node.dataset.id);
        const item = { id: id };

        const childrenContainer = node.querySelector(':scope > .children');
        if (childrenContainer) {
            const kids = buildTreeFromDOM(childrenContainer);
            if (kids.length > 0) {
                item.children = kids;
            }
        }

        tree.push(item);
    });
    return tree;
}

// ذخیره ترتیب
function saveOrder() {
    const builder = document.getElementById('menuBuilder');
    if (!builder) return;

    const tree = buildTreeFromDOM(builder);

    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update_order&tree=' + encodeURIComponent(JSON.stringify(tree))
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('✅ ترتیب ذخیره شد');
        } else {
            alert('❌ خطا در ذخیره');
        }
    })
    .catch(() => alert('❌ خطا در ارتباط'));
}

// ویرایش سریع آیتم
function editItem(item) {
    const newTitle = prompt('عنوان جدید:', item.title);
    if (newTitle === null) return;

    const newIcon = prompt('آیکون (ایموجی):', item.icon || '');
    if (newIcon === null) return;

    const newUrl = prompt('آدرس:', item.url);
    if (newUrl === null) return;

    // ذخیره از طریق fetch
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input name="action" value="edit_item">
        <input name="item_id" value="${item.id}">
        <input name="item_title" value="${newTitle.replace(/"/g, '&quot;')}">
        <input name="item_icon" value="${newIcon.replace(/"/g, '&quot;')}">
        <input name="item_url" value="${newUrl.replace(/"/g, '&quot;')}">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>
