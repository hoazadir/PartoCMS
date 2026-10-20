<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/src/Infrastructure/PageRepository.php';

use PartoCMS\PageBuilder\Infrastructure\PageRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$repo = new PageRepository(getDB());
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page = $id ? $repo->getById($id) : ['title' => '', 'slug' => '', 'layout_json' => '[]', 'status' => 'published'];

if (!$page) { die('صفحه یافت نشد.'); }

$enabledModules = [];
if (function_exists('getModuleManager')) {
    $enabledModules = getModuleManager()->getEnabled();
}

$siteName = getSetting('site_name', 'وب‌سایت من');
$activePage = 'page_builder';

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>

<div class="main-content">
    <form method="post" action="save.php" id="builderForm">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="layout_json" id="layoutJsonInput" value='<?= htmlspecialchars($page['layout_json']) ?>'>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>🧩 <?= $id ? 'ویرایش صفحه' : 'صفحه جدید' ?></h2>
            <div>
                <button type="submit" class="btn btn-success px-4">💾 ذخیره صفحه</button>
                <a href="index.php" class="btn btn-secondary">انصراف</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3 shadow-sm border-0 rounded-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">عنوان صفحه</label>
                            <input type="text" name="title" id="titleInput" class="form-control" value="<?= htmlspecialchars($page['title']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">آدرس (Slug)</label>
                            <input type="text" name="slug" id="slugInput" class="form-control" value="<?= htmlspecialchars($page['slug']) ?>" required dir="ltr" oninput="this.value = this.value.replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-')">
                            <small class="text-muted">مثال: about-us</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">وضعیت</label>
                            <select name="status" class="form-select">
                                <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                                <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>پیش‌نویس</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white fw-bold">ماژول‌های موجود</div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <div id="modulesList">
                            <?php foreach ($enabledModules as $mod): ?>
                                <div class="module-item p-2 mb-2 border rounded bg-light" data-slug="<?= htmlspecialchars($mod['slug']) ?>" data-name="<?= htmlspecialchars($mod['name']) ?>" style="cursor: grab;">
                                    <?= htmlspecialchars($mod['name']) ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="module-item p-2 mb-2 border rounded bg-light" data-slug="text" data-name="متن ساده" style="cursor: grab;">متن ساده (تستی)</div>
                            <div class="module-item p-2 mb-2 border rounded bg-light" data-slug="image" data-name="تصویر" style="cursor: grab;">تصویر (تستی)</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card shadow-sm border-0 rounded-4 h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-layout-text-window-reverse"></i> چیدمان صفحه (ماژول‌ها را از سمت راست بکشید و اینجا رها کنید)
                    </div>
                    <div class="card-body bg-light" id="canvasArea" style="min-height: 500px; border: 2px dashed #cbd5e1; border-radius: 12px;">
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
    .builder-block { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px; padding: 15px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .builder-block .drag-handle { cursor: grab; color: #94a3b8; margin-left: 10px; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('canvasArea');
    const layoutInput = document.getElementById('layoutJsonInput');
    const titleInput = document.getElementById('titleInput');
    const slugInput = document.getElementById('slugInput');
    
    titleInput.addEventListener('input', function() {
        if (!slugInput.value) {
            slugInput.value = this.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
        }
    });

    function loadLayout() {
        try {
            let layout = JSON.parse(layoutInput.value);
            if (Array.isArray(layout)) { layout.forEach(item => addBlockToCanvas(item.slug, item.name, false)); }
        } catch(e) { console.error('Invalid JSON'); }
    }

    function addBlockToCanvas(slug, name, save = true) {
        const block = document.createElement('div');
        block.className = 'builder-block';
        block.dataset.slug = slug;
        block.dataset.name = name;
        block.innerHTML = `<span><i class="bi bi-grid"></i> ${name}</span><div><span class="drag-handle"><i class="bi bi-grip-vertical"></i></span><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.builder-block').remove(); updateJson();">❌</button></div>`;
        canvas.appendChild(block);
    }

    function updateJson() {
        const blocks = Array.from(canvas.querySelectorAll('.builder-block')).map(block => ({ slug: block.dataset.slug, name: block.dataset.name }));
        layoutInput.value = JSON.stringify(blocks);
    }

    document.querySelectorAll('.module-item').forEach(item => {
        item.addEventListener('dragstart', function(e) {
            e.dataTransfer.setData('text/plain', JSON.stringify({ slug: this.dataset.slug, name: this.dataset.name }));
        });
    });

    canvas.addEventListener('dragover', e => e.preventDefault());
    canvas.addEventListener('drop', function(e) {
        e.preventDefault();
        const data = e.dataTransfer.getData('text/plain');
        if (data) { const item = JSON.parse(data); addBlockToCanvas(item.slug, item.name); updateJson(); }
    });

    new Sortable(canvas, { animation: 150, handle: '.drag-handle', ghostClass: 'bg-warning', onEnd: function() { updateJson(); } });

    document.getElementById('builderForm').addEventListener('submit', updateJson);
    loadLayout();
});
</script>
</body>
</html>
