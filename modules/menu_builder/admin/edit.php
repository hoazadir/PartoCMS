<?php
require_once dirname(__DIR__, 3) . '/config.php';
if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }
$pdo = getDB();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$menu = $pdo->query("SELECT * FROM menus WHERE id = $id")->fetch();
if (!$menu) { header('Location: index.php'); exit; }

$pages = $pdo->query("SELECT id, title FROM content_items WHERE status='published' AND type_id IN (SELECT id FROM content_types WHERE slug='page') ORDER BY title LIMIT 100")->fetchAll();
$posts = $pdo->query("SELECT id, title FROM content_items WHERE status='published' AND type_id IN (SELECT id FROM content_types WHERE slug='post') ORDER BY created_at DESC LIMIT 50")->fetchAll();
$customPages = $pdo->query("SELECT id, title FROM custom_pages ORDER BY title ASC")->fetchAll();

$systemModules = [
    ['title' => 'فروشگاه', 'url' => '/modules/shop/index.php'],
    ['title' => 'خدمات', 'url' => '/modules/services/index.php'],
    ['title' => 'نمونه‌کارها', 'url' => '/modules/portfolio/index.php'],
    ['title' => 'تیم ما', 'url' => '/modules/team/index.php'],
    ['title' => 'نظرات', 'url' => '/modules/testimonials/index.php'],
    ['title' => 'سوالات متداول', 'url' => '/modules/faq/index.php'],
    ['title' => 'تماس با ما', 'url' => '/form.php?slug=contact'],
];

$siteName = getSetting('site_name', 'وب‌سایت من');
require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    body { font-family: Tahoma, sans-serif; }
    .main-content { margin-right: 250px; padding: 20px; }
    .pool-item { background:#fff; border:1px solid #e2e8f0; padding:8px 12px; border-radius:6px; margin-bottom:6px; cursor:grab; display:flex; justify-content:space-between; align-items:center; font-size:13px; transition:all 0.2s; }
    .pool-item:hover { border-color:#6d28d9; background:#f5f3ff; }
    .pool-item .badge { font-size:9px; }
    
    .menu-builder { background:#f8fafc; border:2px dashed #cbd5e1; border-radius:12px; padding:15px; min-height:300px; }
    .menu-builder:empty::before { content:'آیتم‌ها را از سمت راست اینجا بکشید'; display:block; text-align:center; color:#94a3b8; padding:40px 0; }
    
    .builder-item { list-style:none; margin-bottom:6px; }
    .item-inner { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; display:flex; align-items:center; gap:10px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
    .item-inner:hover { border-color:#6d28d9; }
    .drag-handle { cursor:grab; color:#94a3b8; font-size:16px; }
    .item-title { flex:1; font-weight:600; font-size:13px; }
    .item-type { font-size:10px; padding:2px 8px; border-radius:10px; background:#ede9fe; color:#6d28d9; }
    .sortable-children { margin-right:30px; margin-top:6px; min-height:10px; padding-right:15px; border-right:2px solid #e2e8f0; }
    
    .emoji-trigger { font-size:20px; cursor:pointer; padding:6px 12px; background:#f1f5f9; border:1px solid #cbd5e1; border-radius:6px; }
    emoji-picker { position:absolute; z-index:1060; }
</style>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-0">🎨 ویرایش منو: <?= htmlspecialchars($menu['name']) ?></h3>
        <div>
            <button onclick="saveOrder()" class="btn btn-success">💾 ذخیره ترتیب</button>
            <a href="index.php" class="btn btn-secondary">بازگشت</a>
        </div>
    </div>

    <div class="row g-3">
        <!-- منابع -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white fw-bold">📦 منابع</div>
                <div class="card-body" style="max-height:600px; overflow-y:auto;">
                    <details class="mb-3" open>
                        <summary class="fw-bold small mb-2">🧱 ماژول‌های سیستم</summary>
                        <div class="pool-group" data-type="custom">
                            <?php foreach ($systemModules as $m): ?>
                                <div class="pool-item" data-type="custom" data-url="<?= $m['url'] ?>" data-title="<?= htmlspecialchars($m['title']) ?>">
                                    <span><?= htmlspecialchars($m['title']) ?></span>
                                    <span class="badge bg-secondary">ماژول</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <details class="mb-3">
                        <summary class="fw-bold small mb-2">📄 صفحات</summary>
                        <div class="pool-group" data-type="page">
                            <?php foreach ($pages as $p): ?>
                                <div class="pool-item" data-type="page" data-id="<?= $p['id'] ?>" data-title="<?= htmlspecialchars($p['title']) ?>">
                                    <span><?= htmlspecialchars($p['title']) ?></span>
                                    <span class="badge bg-info">صفحه</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <details class="mb-3">
                        <summary class="fw-bold small mb-2">📝 مقالات</summary>
                        <div class="pool-group" data-type="post">
                            <?php foreach ($posts as $p): ?>
                                <div class="pool-item" data-type="post" data-id="<?= $p['id'] ?>" data-title="<?= htmlspecialchars($p['title']) ?>">
                                    <span><?= htmlspecialchars($p['title']) ?></span>
                                    <span class="badge bg-warning">مقاله</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <details class="mb-3">
                        <summary class="fw-bold small mb-2">🧩 صفحه‌ساز</summary>
                        <div class="pool-group" data-type="custom_page">
                            <?php foreach ($customPages as $cp): ?>
                                <div class="pool-item" data-type="custom_page" data-id="<?= $cp['id'] ?>" data-title="<?= htmlspecialchars($cp['title']) ?>">
                                    <span><?= htmlspecialchars($cp['title']) ?></span>
                                    <span class="badge bg-primary">صفحه‌ساز</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <button class="btn btn-outline-primary w-100 mt-2" onclick="openModal()">➕ آیتم سفارشی</button>
                </div>
            </div>
        </div>

        <!-- بوم -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white fw-bold d-flex justify-content-between">
                    <span>🧱 ساختار منو</span>
                    <small class="text-muted">برای زیرمنو، آیتم را کمی به راست بکشید</small>
                </div>
                <div class="card-body">
                    <ul class="menu-builder" id="menuBuilder"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal ویرایش -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="modalTitle">ویرایش آیتم</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="itemForm">
                    <input type="hidden" name="id" id="itemId" value="0">
                    <input type="hidden" name="menu_id" value="<?= $id ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">عنوان</label>
                            <input type="text" name="title" id="itemTitle" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">آیکون</label>
                            <div class="d-flex gap-2">
                                <input type="text" name="icon" id="itemIcon" class="form-control" placeholder="🏠">
                                <button type="button" class="emoji-trigger" onclick="toggleEmoji()">😀</button>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">نوع</label>
                            <select name="type" id="itemType" class="form-select" onchange="changeType()">
                                <option value="custom">🔗 لینک دلخواه</option>
                                <option value="page">📄 صفحه</option>
                                <option value="post">📝 مقاله</option>
                                <option value="custom_page">🧩 صفحه‌ساز</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3" id="targetField" style="display:none;">
                            <label class="form-label">انتخاب مورد</label>
                            <select id="itemTarget" class="form-select"></select>
                        </div>
                    </div>
                    <div class="mb-3" id="urlField">
                        <label class="form-label">آدرس</label>
                        <input type="text" name="url" id="itemUrl" class="form-control" placeholder="/modules/shop/index.php">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">کلاس CSS</label>
                            <input type="text" name="css_class" id="itemCss" class="form-control" placeholder="مثلاً text-danger">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">باز شدن در تب جدید</label><br>
                            <input type="checkbox" name="target_blank" id="itemBlank" class="form-check-input">
                        </div>
                    </div>
                    <div class="card bg-light mb-3">
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input type="checkbox" name="mega_enabled" id="megaEnabled" class="form-check-input">
                                <label class="form-check-label fw-bold" for="megaEnabled">🧩 فعال‌سازی مگا منو</label>
                            </div>
                            <div id="megaOptions" style="display:none;">
                                <label class="form-label small">تعداد ستون‌ها</label>
                                <input type="number" name="mega_columns" id="megaColumns" class="form-control" value="2" min="2" max="4">
                            </div>
                        </div>
                    </div>
                </form>
                <emoji-picker id="emojiPicker" style="display:none; position:absolute;"></emoji-picker>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                <button class="btn btn-primary" onclick="saveItem()">💾 ذخیره</button>
            </div>
        </div>
    </div>
</div>

<script type="module" src="https://cdn.jsdelivr.net/npm/emoji-picker-element@^1/index.js"></script>
<script>
    const menuId = <?= $id ?>;
    const pages = <?= json_encode($pages) ?>;
    const posts = <?= json_encode($posts) ?>;
    const customPages = <?= json_encode($customPages) ?>;

    let editItemData = null;

    // بارگذاری درخت
    async function loadTree() {
        const res = await fetch('ajax/get-tree.php?menu_id=' + menuId);
        const tree = await res.json();
        const builder = document.getElementById('menuBuilder');
        builder.innerHTML = '';
        tree.forEach(item => builder.appendChild(renderNode(item)));
        initSortable(builder);
    }

    function renderNode(item) {
        const li = document.createElement('li');
        li.className = 'builder-item';
        li.dataset.id = item.id;
        li.dataset.json = JSON.stringify(item);

        const inner = document.createElement('div');
        inner.className = 'item-inner';
        inner.innerHTML = `
            <span class="drag-handle">⋮⋮</span>
            <span style="font-size:18px;">${item.icon || '📄'}</span>
            <span class="item-title">${item.title}</span>
            <span class="item-type">${item.type}</span>
            <button class="btn btn-sm btn-outline-primary" onclick="openModal(${item.id})">✏️</button>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(${item.id})">🗑</button>
        `;
        li.appendChild(inner);

        if (item.children && item.children.length > 0) {
            const ul = document.createElement('ul');
            ul.className = 'sortable-children';
            item.children.forEach(c => ul.appendChild(renderNode(c)));
            li.appendChild(ul);
        }
        return li;
    }

    function initSortable(el) {
        new Sortable(el, {
            group: 'nested',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            handle: '.drag-handle',
            onEnd: () => saveOrder()
        });
        el.querySelectorAll('.sortable-children').forEach(c => initSortable(c));
    }

    // درگ از منابع
    document.querySelectorAll('.pool-group').forEach(group => {
        new Sortable(group, {
            group: { name: 'nested', pull: 'clone', put: false },
            sort: false,
            animation: 150
        });
    });

    // بوم اصلی
    const builder = document.getElementById('menuBuilder');
    new Sortable(builder, {
        group: 'nested',
        animation: 150,
        fallbackOnBody: true,
        swapThreshold: 0.65,
        handle: '.drag-handle',
        onAdd: function(evt) {
            const el = evt.item;
            if (el.classList.contains('pool-item')) {
                const data = {
                    title: el.dataset.title,
                    type: el.dataset.type,
                    url: el.dataset.url || '#',
                    target_id: el.dataset.id || null
                };
                createItem(data, el);
            }
        }
    });

    async function createItem(data, placeholder) {
        const fd = new FormData();
        Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
        fd.append('menu_id', menuId);
        const res = await fetch('ajax/save-item.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            const newNode = {
                id: json.id, title: data.title, type: data.type,
                url: data.url, icon: '', target_id: data.target_id,
                target_blank: 0, meta: null, children: []
            };
            placeholder.replaceWith(renderNode(newNode));
            initSortable(builder);
            saveOrder();
        } else {
            alert('خطا: ' + json.message);
            placeholder.remove();
        }
    }

    function getTree(el) {
        const arr = [];
        el.querySelectorAll(':scope > .builder-item').forEach(li => {
            const item = JSON.parse(li.dataset.json);
            const childrenUl = li.querySelector(':scope > .sortable-children');
            arr.push({
                id: item.id,
                children: childrenUl ? getTree(childrenUl) : []
            });
        });
        return arr;
    }

    async function saveOrder() {
        const tree = getTree(builder);
        await fetch('ajax/save-order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ menu_id: menuId, tree })
        });
    }

    // Modal
    function openModal(id = 0) {
        const form = document.getElementById('itemForm');
        form.reset();
        document.getElementById('itemId').value = 0;
        document.getElementById('itemType').value = 'custom';
        document.getElementById('itemCss').value = '';

        if (id) {
            const li = document.querySelector(`.builder-item[data-id="${id}"]`);
            if (!li) return;
            const item = JSON.parse(li.dataset.json);
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemTitle').value = item.title;
            document.getElementById('itemIcon').value = item.icon || '';
            document.getElementById('itemType').value = item.type;
            document.getElementById('itemUrl').value = item.url || '';
            document.getElementById('itemBlank').checked = item.target_blank == 1;
            if (item.meta && item.meta.mega_menu) {
                document.getElementById('megaEnabled').checked = true;
                document.getElementById('megaColumns').value = item.meta.columns || 2;
                document.getElementById('megaOptions').style.display = 'block';
            }
            if (item.meta && item.meta.css_class) document.getElementById('itemCss').value = item.meta.css_class;
            editItemData = item;
            changeType();
            // انتخاب target
            setTimeout(() => {
                if (item.target_id) document.getElementById('itemTarget').value = item.target_id;
            }, 50);
        } else {
            editItemData = null;
            changeType();
        }
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function changeType() {
        const type = document.getElementById('itemType').value;
        const targetField = document.getElementById('targetField');
        const urlField = document.getElementById('urlField');
        const target = document.getElementById('itemTarget');

        target.innerHTML = '<option value="">— انتخاب کنید —</option>';
        if (type === 'page') {
            pages.forEach(p => target.innerHTML += `<option value="${p.id}">${p.title}</option>`);
            targetField.style.display = 'block';
            urlField.style.display = 'none';
        } else if (type === 'post') {
            posts.forEach(p => target.innerHTML += `<option value="${p.id}">${p.title}</option>`);
            targetField.style.display = 'block';
            urlField.style.display = 'none';
        } else if (type === 'custom_page') {
            customPages.forEach(p => target.innerHTML += `<option value="${p.id}">${p.title}</option>`);
            targetField.style.display = 'block';
            urlField.style.display = 'none';
        } else {
            targetField.style.display = 'none';
            urlField.style.display = 'block';
        }
    }

    function toggleEmoji() {
        const picker = document.getElementById('emojiPicker');
        picker.style.display = picker.style.display === 'none' ? 'block' : 'none';
    }

    document.getElementById('emojiPicker')?.addEventListener('emoji-click', e => {
        document.getElementById('itemIcon').value = e.detail.unicode;
        document.getElementById('emojiPicker').style.display = 'none';
    });

    document.getElementById('megaEnabled')?.addEventListener('change', e => {
        document.getElementById('megaOptions').style.display = e.target.checked ? 'block' : 'none';
    });

    async function saveItem() {
        const form = document.getElementById('itemForm');
        const fd = new FormData(form);
        const res = await fetch('ajax/save-item.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            loadTree();
        } else {
            alert('خطا: ' + json.message);
        }
    }

    async function deleteItem(id) {
        if (!confirm('حذف شود؟')) return;
        const fd = new FormData();
        fd.append('id', id);
        const res = await fetch('ajax/delete-item.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (json.success) { loadTree(); }
    }

    loadTree();
</script>

<?php require_once dirname(__DIR__, 3) . '/admin/includes/footer.php'; ?>
