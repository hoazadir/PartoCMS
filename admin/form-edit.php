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
$siteName = getSetting('site_name', 'وب‌سایت من');
$id = $_GET['id'] ?? null;
$edit = null;
$fields = [];
$error = '';
$success = '';

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
    $stmt->execute([$id]);
    $edit = $stmt->fetch();
    if ($edit) {
        $fields = json_decode($edit['fields'] ?? '[]', true) ?: [];
    }
}

// ذخیره
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $successMessage = trim($_POST['success_message'] ?? '');
    $submitLabel = trim($_POST['submit_label'] ?? 'ارسال');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $fieldsJson = $_POST['fields_json'] ?? '[]';

    if (empty($name)) {
        $error = 'نام فرم الزامی است';
    } elseif (empty($slug)) {
        $error = 'نامک (slug) الزامی است';
    } else {
        // چک تکراری
        $sql = "SELECT id FROM forms WHERE slug = ?";
        $params = [$slug];
        if ($id) {
            $sql .= " AND id != ?";
            $params[] = $id;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $error = 'این نامک قبلاً استفاده شده';
        } else {
            try {
                if ($id) {
                    $stmt = $pdo->prepare("
                        UPDATE forms SET name=?, slug=?, description=?, fields=?, success_message=?, submit_label=?, is_active=?
                        WHERE id=?
                    ");
                    $stmt->execute([$name, $slug, $description, $fieldsJson, $successMessage, $submitLabel, $isActive, $id]);
                    $success = 'فرم به‌روزرسانی شد';
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO forms (name, slug, description, fields, success_message, submit_label, is_active, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$name, $slug, $description, $fieldsJson, $successMessage, $submitLabel, $isActive, $_SESSION['user_id']]);
                    $id = $pdo->lastInsertId();
                    header('Location: form-edit.php?id=' . $id . '&msg=created');
                    exit;
                }

                // بارگذاری مجدد
                $stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
                $stmt->execute([$id]);
                $edit = $stmt->fetch();
                $fields = json_decode($edit['fields'] ?? '[]', true) ?: [];
            } catch (PDOException $e) {
                $error = 'خطا: ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'created') {
    $success = 'فرم ساخته شد. حالا فیلدها رو اضافه کن.';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $edit ? 'ویرایش' : 'ساخت' ?> فرم | <?= htmlspecialchars($siteName) ?></title>
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

        /* پالت فیلدها */
        .field-palette { display: flex; gap: 6px; flex-wrap: wrap; }
        .palette-btn { padding: 8px 12px; background: #3498db; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-family: Tahoma; transition: all 0.2s; }
        .palette-btn:hover { background: #2980b9; transform: translateY(-2px); }

        /* لیست فیلدها */
        .field-item { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 10px; border: 2px solid #e0e0e0; cursor: move; transition: all 0.2s; }
        .field-item:hover { border-color: #3498db; }
        .field-item.dragging { opacity: 0.5; }
        .field-item .field-header { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .field-item .field-title { font-weight: bold; color: #2c3e50; font-size: 13px; }
        .field-item .field-type { font-size: 11px; color: #7f8c8d; background: #fff; padding: 3px 8px; border-radius: 10px; }
        .field-item .field-actions { display: flex; gap: 3px; }
        .field-item .field-actions button { padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 11px; }

        .empty-fields { text-align: center; padding: 50px 20px; color: #95a5a6; }
        .empty-fields i { font-size: 50px; display: block; margin-bottom: 15px; }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;"><?= $edit ? '✏️ ویرایش فرم' : '➕ ساخت فرم جدید' ?></h4>
        <a href="forms.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-right"></i> بازگشت</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" id="formMain">
        <input type="hidden" name="fields_json" id="fieldsJson" value='<?= htmlspecialchars(json_encode($fields, JSON_UNESCAPED_UNICODE)) ?>'>

        <!-- تنظیمات فرم -->
        <div class="card">
            <div class="card-header">⚙️ تنظیمات فرم</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نام فرم <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($edit['name'] ?? '') ?>" placeholder="مثلاً: فرم تماس">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">نامک (slug) <span class="text-danger">*</span></label>
                        <input type="text" name="slug" class="form-control" required value="<?= htmlspecialchars($edit['slug'] ?? '') ?>" placeholder="contact" pattern="[a-z0-9_-]+">
                        <small class="text-muted">فقط حروف کوچک انگلیسی، عدد و خط تیره</small>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">توضیحات</label>
                    <input type="text" name="description" class="form-control" value="<?= htmlspecialchars($edit['description'] ?? '') ?>">
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">متن دکمه ارسال</label>
                        <input type="text" name="submit_label" class="form-control" value="<?= htmlspecialchars($edit['submit_label'] ?? 'ارسال') ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">وضعیت</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= !isset($edit) || $edit['is_active'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">فرم فعال باشد</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">پیام موفقیت</label>
                    <textarea name="success_message" class="form-control" rows="2"><?= htmlspecialchars($edit['success_message'] ?? '✅ پیام شما با موفقیت ارسال شد.') ?></textarea>
                </div>
            </div>
        </div>

        <!-- فیلدها -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>📝 فیلدهای فرم</span>
                <div class="field-palette">
                    <button type="button" class="palette-btn" onclick="addField('text')">➕ متن</button>
                    <button type="button" class="palette-btn" onclick="addField('email')">📧 ایمیل</button>
                    <button type="button" class="palette-btn" onclick="addField('tel')">📞 تلفن</button>
                    <button type="button" class="palette-btn" onclick="addField('number')">🔢 عدد</button>
                    <button type="button" class="palette-btn" onclick="addField('textarea')">📄 متن بلند</button>
                    <button type="button" class="palette-btn" onclick="addField('select')">📋 انتخابی</button>
                    <button type="button" class="palette-btn" onclick="addField('radio')">🔘 رادیو</button>
                    <button type="button" class="palette-btn" onclick="addField('checkbox')">☑️ چک‌باکس</button>
                    <button type="button" class="palette-btn" onclick="addField('date')">📅 تاریخ</button>
                    <button type="button" class="palette-btn" onclick="addField('file')">📎 فایل</button>
                </div>
            </div>
            <div class="card-body">
                <div id="fieldsList"></div>
                <div class="empty-fields" id="emptyFields">
                    <i class="bi bi-inbox"></i>
                    <h5>هنوز فیلدی اضافه نشده</h5>
                    <p>از دکمه‌های بالا برای افزودن فیلد استفاده کنید</p>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-save"></i> ذخیره فرم</button>
    </form>
</div>

<script>
let fields = <?= json_encode($fields, JSON_UNESCAPED_UNICODE) ?> || [];

function renderFields() {
    const list = document.getElementById('fieldsList');
    const empty = document.getElementById('emptyFields');
    list.innerHTML = '';

    if (fields.length === 0) {
        empty.style.display = 'block';
    } else {
        empty.style.display = 'none';
    }

    fields.forEach((f, i) => {
        const el = document.createElement('div');
        el.className = 'field-item';
        el.draggable = true;
        el.dataset.index = i;

        const typeLabels = {
            text: '📝 متن', email: '📧 ایمیل', tel: '📞 تلفن', number: '🔢 عدد',
            textarea: '📄 متن بلند', select: '📋 انتخابی', radio: '🔘 رادیو',
            checkbox: '☑️ چک‌باکس', date: '📅 تاریخ', file: '📎 فایل'
        };

        el.innerHTML = `
            <div class="field-header">
                <div style="flex: 1;">
                    <div class="field-title">${escapeHtml(f.label || 'بدون عنوان')}</div>
                    <div style="font-size: 11px; color: #95a5a6; margin-top: 3px;">
                        name: <code>${escapeHtml(f.name || '-')}</code>
                        ${f.required ? '| <span style="color:#e74c3c;">اجباری</span>' : ''}
                    </div>
                </div>
                <span class="field-type">${typeLabels[f.type] || f.type}</span>
                <div class="field-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="editField(${i})">✏️</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeField(${i})">🗑</button>
                </div>
            </div>
        `;
        list.appendChild(el);
    });

    document.getElementById('fieldsJson').value = JSON.stringify(fields);
}

function addField(type) {
    const names = {
        text: 'متن', email: 'ایمیل', tel: 'تلفن', number: 'عدد',
        textarea: 'پیام', select: 'انتخاب', radio: 'گزینه', checkbox: 'موافقت', date: 'تاریخ', file: 'فایل'
    };
    const field = {
        type: type,
        name: 'field_' + Date.now(),
        label: names[type] || 'فیلد جدید',
        placeholder: '',
        required: false,
        rows: type === 'textarea' ? 5 : undefined,
        options: (type === 'select' || type === 'radio' || type === 'checkbox') ? ['گزینه ۱', 'گزینه ۲'] : undefined
    };
    fields.push(field);
    renderFields();
    // بلافاصله ویرایش باز بشه
    setTimeout(() => editField(fields.length - 1), 100);
}

function removeField(i) {
    if (!confirm('حذف این فیلد؟')) return;
    fields.splice(i, 1);
    renderFields();
}

function editField(i) {
    const f = fields[i];
    let html = `
        <div style="text-align: right;">
            <div class="mb-3">
                <label class="form-label">برچسب <span class="text-danger">*</span></label>
                <input type="text" id="ef_label" class="form-control" value="${escapeAttr(f.label || '')}">
            </div>
            <div class="mb-3">
                <label class="form-label">نام (name) <span class="text-danger">*</span></label>
                <input type="text" id="ef_name" class="form-control" value="${escapeAttr(f.name || '')}">
                <small class="text-muted">فقط حروف انگلیسی، عدد و آندرلاین</small>
            </div>
    `;

    if (f.type !== 'checkbox' || f.options) {
        if (['text', 'email', 'tel', 'number', 'textarea'].includes(f.type)) {
            html += `
                <div class="mb-3">
                    <label class="form-label">Placeholder</label>
                    <input type="text" id="ef_placeholder" class="form-control" value="${escapeAttr(f.placeholder || '')}">
                </div>
            `;
        }
    }

    if (f.type === 'textarea') {
        html += `
            <div class="mb-3">
                <label class="form-label">تعداد خطوط</label>
                <input type="number" id="ef_rows" class="form-control" value="${f.rows || 5}" min="2" max="20">
            </div>
        `;
    }

    if (['select', 'radio', 'checkbox'].includes(f.type)) {
        html += `
            <div class="mb-3">
                <label class="form-label">گزینه‌ها (هر خط یک گزینه)</label>
                <textarea id="ef_options" class="form-control" rows="5">${escapeHtml((f.options || []).join('\\n'))}</textarea>
            </div>
        `;
    }

    html += `
            <div class="mb-3">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="ef_required" ${f.required ? 'checked' : ''}>
                    <label class="form-check-label" for="ef_required">فیلد اجباری باشد</label>
                </div>
            </div>
        </div>
    `;

    // مدال
    const modal = document.createElement('div');
    modal.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9999;padding:20px;overflow:auto;display:flex;align-items:center;justify-content:center;';
    modal.innerHTML = `
        <div style="background:#fff;border-radius:12px;padding:25px;max-width:500px;width:100%;">
            <h5 style="margin-top:0;">ویرایش فیلد</h5>
            ${html}
            <div class="mt-3">
                <button type="button" class="btn btn-success" id="ef_save">💾 ذخیره</button>
                <button type="button" class="btn btn-secondary" id="ef_cancel">انصراف</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    modal.querySelector('#ef_save').onclick = () => {
        fields[i].label = modal.querySelector('#ef_label').value.trim();
        fields[i].name = modal.querySelector('#ef_name').value.trim();
        if (modal.querySelector('#ef_placeholder')) fields[i].placeholder = modal.querySelector('#ef_placeholder').value;
        if (modal.querySelector('#ef_rows')) fields[i].rows = parseInt(modal.querySelector('#ef_rows').value) || 5;
        if (modal.querySelector('#ef_options')) fields[i].options = modal.querySelector('#ef_options').value.split('\\n').map(s => s.trim()).filter(s => s);
        fields[i].required = modal.querySelector('#ef_required').checked;
        renderFields();
        document.body.removeChild(modal);
    };
    modal.querySelector('#ef_cancel').onclick = () => document.body.removeChild(modal);
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function escapeAttr(s) {
    return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

// ذخیره فیلدها قبل از submit
document.getElementById('formMain').addEventListener('submit', () => {
    document.getElementById('fieldsJson').value = JSON.stringify(fields);
});

// Drag & Drop
document.addEventListener('DOMContentLoaded', () => {
    renderFields();

    const list = document.getElementById('fieldsList');
    let dragIdx = null;

    list.addEventListener('dragstart', e => {
        const item = e.target.closest('.field-item');
        if (!item) return;
        dragIdx = parseInt(item.dataset.index);
        item.classList.add('dragging');
    });

    list.addEventListener('dragover', e => {
        e.preventDefault();
        const item = e.target.closest('.field-item');
        if (!item || dragIdx === null) return;
        const overIdx = parseInt(item.dataset.index);
        if (dragIdx === overIdx) return;

        const moved = fields.splice(dragIdx, 1)[0];
        fields.splice(overIdx, 0, moved);
        dragIdx = overIdx;
        renderFields();
    });

    list.addEventListener('dragend', () => {
        document.querySelectorAll('.dragging').forEach(el => el.classList.remove('dragging'));
        dragIdx = null;
    });
});

// Auto-slug از نام
document.querySelector('input[name="name"]').addEventListener('blur', function() {
    const slugInput = document.querySelector('input[name="slug"]');
    if (slugInput.value.trim()) return;
    if (!this.value.trim()) return;

    // تبدیل به انگلیسی - فقط حروف مجاز
    let slug = this.value.trim().toLowerCase()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');

    if (!slug) slug = 'form-' + Date.now();
    slugInput.value = slug;
});
</script>
</body>
</html>
