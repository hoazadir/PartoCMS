<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }

$pdo = getDB();
$id = (int) ($_GET['id'] ?? 0);

if (!$id) { header('Location: menus.php'); exit; }

// ذخیره تنظیمات کلی
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_menu') {
    $name = trim($_POST['name']);
    $location = $_POST['location'] ?? 'header';
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE menus SET name=?, location=?, is_active=? WHERE id=?");
    $stmt->execute([$name, $location, $isActive, $id]);
    header('Location: menu-edit.php?id=' . $id . '&msg=saved');
    exit;
}

$menu = $pdo->query("SELECT * FROM menus WHERE id = $id")->fetch();
if (!$menu) { header('Location: menus.php'); exit; }

$siteName = getSetting('site_name', 'وب‌سایت من');

// داده‌های مورد نیاز برای Modal
$pages = $pdo->query("SELECT id, title FROM content_items WHERE status='published' AND type_id IN (SELECT id FROM content_types WHERE slug='page') ORDER BY title LIMIT 100")->fetchAll();
$posts = $pdo->query("SELECT id, title FROM content_items WHERE status='published' AND type_id IN (SELECT id FROM content_types WHERE slug='post') ORDER BY created_at DESC LIMIT 50")->fetchAll();
$customPages = $pdo->query("SELECT id, title FROM custom_pages ORDER BY title ASC")->fetchAll();

$systemModules = [
    ['title' => 'فروشگاه', 'url' => '/modules/shop/index.php'],
    ['title' => 'خدمات', 'url' => '/modules/services/index.php'],
    ['title' => 'نمونه‌کارها', 'url' => '/modules/portfolio/index.php'],
    ['title' => 'تیم ما', 'url' => '/modules/team/index.php'],
    ['title' => 'تماس با ما', 'url' => '/form.php?slug=contact'],
];
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش منو: <?= htmlspecialchars($menu['name']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- jsTree CSS -->
    <link rel="stylesheet" href="/libs/jstree/themes/default/style.min.css" />
    
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; margin-bottom: 20px; }
        
        /* تنظیمات ظاهر jsTree */
        .jstree-default .jstree-anchor { font-size: 14px; line-height: 30px; height: 30px; }
        .jstree-default .jstree-icon:empty { width: 24px; height: 24px; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 fw-bold">✏️ ویرایش منو: <?= htmlspecialchars($menu['name']) ?></h5>
            <div>
                <button class="btn btn-primary" onclick="openAddModal()">➕ افزودن آیتم جدید</button>
                <a href="menus.php" class="btn btn-outline-secondary">بازگشت</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    <span>🧱 ساختار منو</span>
                    <small class="text-muted">برای جابجایی، آیتم‌ها را بکشید (Drag & Drop)</small>
                </div>
                <div class="card-body">
                    <!-- کانتینر jsTree -->
                    <div id="jstree"></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-white fw-bold">⚙️ تنظیمات منو</div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="action" value="update_menu">
                        <div class="mb-3">
                            <label class="form-label">نام منو</label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($menu['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">محل نمایش</label>
                            <select name="location" class="form-select">
                                <option value="header" <?= $menu['location'] === 'header' ? 'selected' : '' ?>>هدر</option>
                                <option value="footer" <?= $menu['location'] === 'footer' ? 'selected' : '' ?>>فوتر</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">ذخیره تنظیمات</button>
                    </form>
                </div>
            </div>
            <div class="alert alert-info mt-3">
                <strong>راهنما:</strong><br>
                * برای ساخت زیرمنو، آیتم را روی آیتم دیگر بکشید.<br>
                * برای ویرایش، روی آیتم <strong>دابل کلیک</strong> کنید.
            </div>
        </div>
    </div>
</div>

<!-- Modal برای ویرایش/افزودن آیتم -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">ویرایش/افزودن آیتم</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" name="id" id="itemId" value="0">
                    <input type="hidden" name="menu_id" value="<?= $id ?>">
                    <input type="hidden" name="parent_id" id="itemParentId" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">عنوان</label>
                        <input type="text" name="title" id="itemTitle" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">نوع آیتم</label>
                        <select name="type" id="itemType" class="form-select" onchange="changeType(this.value)">
                            <option value="custom">🔗 لینک دلخواه</option>
                            <option value="page">📄 صفحه</option>
                            <option value="post">📝 مقاله</option>
                            <option value="custom_page">🧩 صفحه‌ساز</option>
                            <option value="module">🧱 ماژول سیستم</option>
                        </select>
                    </div>

                    <div class="mb-3" id="targetSelector" style="display:none;">
                        <label class="form-label">انتخاب مورد</label>
                        <select id="itemTargetId" class="form-select" onchange="updateUrlFromSelector(this)"></select>
                    </div>

                    <div class="mb-3" id="urlField">
                        <label class="form-label">آدرس (URL)</label>
                        <input type="text" name="url" id="itemUrl" class="form-control" placeholder="/modules/shop/index.php">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">آیکون (ایموجی)</label>
                        <input type="text" name="icon" id="itemIcon" class="form-control" placeholder="🏠">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="saveItem()">ذخیره</button>
            </div>
        </div>
    </div>
</div>

<!-- اسکریپت‌ها -->
<script src="/libs/jstree/jquery.min.js"></script>
<script src="/libs/jstree/jstree.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    const menuId = <?= $id ?>;
    const pages = <?= json_encode($pages) ?>;
    const posts = <?= json_encode($posts) ?>;
    const customPages = <?= json_encode($customPages) ?>;
    const systemModules = <?= json_encode($systemModules) ?>;

    $(function() {
        // راه‌اندازی jsTree
        $('#jstree').jstree({
            'core': {
                'data': {
                    'url': 'ajax/get-menu-tree.php?menu_id=' + menuId,
                    'dataType': 'json'
                },
                'check_callback': true,
                'themes': { 'stripes': true, 'dots': false }
            },
            'plugins': ['dnd', 'types', 'state']
        });

        // ذخیره ترتیب پس از هر جابجایی (Drag & Drop)
        $('#jstree').on('move_node.jstree', function(e, data) {
            const treeData = $('#jstree').jstree(true).get_json('#', { flat: true });
            
            fetch('ajax/menu-save-order.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ menu_id: menuId, items: treeData })
            }).then(r => r.json()).then(res => {
                if(!res.success) console.error('خطا در ذخیره ترتیب:', res.message);
            });
        });

        // باز کردن Modal با دابل کلیک
        $('#jstree').on('dblclick.jstree', function (e) {
            const instance = $.jstree.reference(this);
            const node = instance.get_node(e.target.id);
            openEditModal(node.id, node.text.replace(/[^\w\s\u0600-\u06FF]/g, '').trim(), node.data);
        });
    });

    function openAddModal() {
        document.getElementById('modalTitle').innerText = 'افزودن آیتم جدید';
        document.getElementById('itemId').value = '0';
        document.getElementById('itemTitle').value = '';
        document.getElementById('itemUrl').value = '';
        document.getElementById('itemIcon').value = '';
        document.getElementById('itemType').value = 'custom';
        document.getElementById('itemParentId').value = ''; // به صورت پیش‌فرض بدون والد
        changeType('custom');
        
        new bootstrap.Modal(document.getElementById('itemModal')).show();
    }

    function openEditModal(id, title, data) {
        document.getElementById('modalTitle').innerText = 'ویرایش آیتم';
        document.getElementById('itemId').value = id;
        document.getElementById('itemTitle').value = title;
        document.getElementById('itemUrl').value = data.url || '';
        document.getElementById('itemIcon').value = data.icon || '';
        document.getElementById('itemType').value = data.type || 'custom';
        
        changeType(data.type || 'custom');
        
        // انتخاب مقدار در سلکتور
        setTimeout(() => {
            if (data.type === 'page') document.getElementById('itemTargetId').value = data.target_id;
            if (data.type === 'post') document.getElementById('itemTargetId').value = data.target_id;
            if (data.type === 'custom_page') document.getElementById('itemTargetId').value = data.target_id;
            if (data.type === 'module') document.getElementById('itemTargetId').value = data.url;
        }, 100);

        // آیتم والد (از ساختار jsTree خوانده می‌شود، برای سادگی فعلا از آن صرف‌نظر می‌کنیم چون jsTree خودش مدیریت می‌کند)
        document.getElementById('itemParentId').value = '';

        new bootstrap.Modal(document.getElementById('itemModal')).show();
    }

    function changeType(type) {
        const targetSelector = document.getElementById('targetSelector');
        const urlField = document.getElementById('urlField');
        const select = document.getElementById('itemTargetId');
        
        select.innerHTML = '<option value="">— انتخاب کنید —</option>';
        
        if (type === 'custom') {
            targetSelector.style.display = 'none';
            urlField.style.display = 'block';
        } 
        else if (type === 'module') {
            targetSelector.style.display = 'block';
            urlField.style.display = 'none';
            systemModules.forEach(m => select.innerHTML += `<option value="${m.url}">${m.title}</option>`);
        }
        else {
            targetSelector.style.display = 'block';
            urlField.style.display = 'none';
            let list = type === 'page' ? pages : (type === 'post' ? posts : customPages);
            list.forEach(p => select.innerHTML += `<option value="${p.id}">${p.title}</option>`);
        }
    }

    function updateUrlFromSelector(select) {
        // این تابع صرفاً برای آپدیت فیلد url در پس‌زمینه است
    }

    function saveItem() {
        const formData = new FormData(document.getElementById('itemForm'));
        formData.append('action', 'save');
        
        const type = formData.get('type');
        const targetVal = document.getElementById('itemTargetId').value;
        
        if (type === 'module') {
            formData.set('url', targetVal);
            formData.set('target_id', '');
        } else if (type !== 'custom') {
            formData.set('target_id', targetVal);
            formData.set('url', '#');
        } else {
            formData.set('target_id', '');
        }

        fetch('ajax/menu-save-item.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload(); // برای سادگی و اطمینان از رفرش شدن jsTree
                } else {
                    alert(data.message);
                }
            });
    }
</script>
</body>
</html>
