<?php
// admin/editor.php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$templateId = $_GET['id'] ?? null;
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ویرایشگر حرفه‌ای | <?= htmlspecialchars($siteName) ?></title>

<link rel="stylesheet" href="../libs/grapes.min.css">
<link rel="stylesheet" href="../libs/grapesjs-template-manager.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; height: 100%; font-family: Tahoma, Arial, sans-serif; overflow: hidden; background: #1e1e1e; }

/* ============ WIZARD ============ */
.wizard-overlay {
    position: fixed; inset: 0; background: linear-gradient(135deg, #1a252f, #2c3e50);
    z-index: 9999; display: flex; align-items: center; justify-content: center;
    padding: 20px; overflow-y: auto;
}
.wizard-box {
    background: #2c3e50; border-radius: 16px; padding: 40px;
    max-width: 900px; width: 100%; color: #ecf0f1;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    border: 1px solid #34495e;
}
.wizard-box h1 { margin: 0 0 10px; color: #fff; font-size: 26px; text-align: center; }
.wizard-box .sub { text-align: center; color: #95a5a6; margin-bottom: 25px; }
.wizard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-bottom: 22px; }
.wizard-card {
    background: #34495e; border: 2px solid transparent; border-radius: 12px;
    padding: 22px; text-align: center; cursor: pointer;
    transition: all 0.3s;
}
.wizard-card:hover { border-color: #3498db; transform: translateY(-5px); background: #3d566e; }
.wizard-card.selected { border-color: #27ae60; background: #2d5a4a; }
.wizard-card .ico { font-size: 42px; margin-bottom: 12px; }
.wizard-card h3 { margin: 0 0 8px; color: #fff; font-size: 17px; }
.wizard-card p { margin: 0; color: #95a5a6; font-size: 12px; line-height: 1.6; }
.wizard-actions { display: flex; justify-content: space-between; gap: 10px; margin-top: 18px; }
.wizard-actions button {
    padding: 11px 28px; border: none; border-radius: 8px;
    cursor: pointer; font-family: Tahoma; font-size: 13px; font-weight: bold;
}
.wizard-actions .back { background: #7f8c8d; color: #fff; }
.wizard-actions .next { background: #3498db; color: #fff; }
.wizard-actions .next:disabled { opacity: 0.5; cursor: not-allowed; }

.module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px; }
.module-item {
    background: #34495e; border-radius: 8px; padding: 12px;
    cursor: pointer; transition: all 0.2s; border: 2px solid transparent;
    display: flex; align-items: center; gap: 10px;
}
.module-item:hover { background: #3d566e; }
.module-item.active { border-color: #27ae60; background: #2d5a4a; }
.module-item .chk {
    width: 20px; height: 20px; border-radius: 50%; border: 2px solid #7f8c8d;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
}
.module-item.active .chk { background: #27ae60; border-color: #27ae60; color: #fff; }
.module-item .m-info { flex-grow: 1; }
.module-item .m-title { font-weight: bold; font-size: 12px; margin-bottom: 2px; }
.module-item .m-desc { font-size: 10px; opacity: 0.7; }

/* ============ MAIN EDITOR ============ */
#main-editor { display: none; height: 100vh; }
#gjs { height: 100vh; width: calc(100% - 260px); padding-top: 52px; margin-right: 260px; }

.gjs-one-bg { background-color: #2c3e50; }
.gjs-two-color { color: #ecf0f1; }
.gjs-three-bg { background-color: #34495e; }
.gjs-four-color, .gjs-four-color-h:hover { color: #3498db; }

.custom-toolbar {
    position: fixed; top: 0; left: 0; right: 260px; height: 52px;
    background: linear-gradient(135deg, #2c3e50, #34495e);
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 12px; z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.4); border-bottom: 1px solid #1a252f;
}
.toolbar-title { color: #ecf0f1; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 8px; }
.toolbar-title small { font-weight: normal; opacity: 0.5; font-size: 10px; }
.toolbar-group { display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }
.toolbar-group button {
    background: rgba(255,255,255,0.08); color: #ecf0f1;
    border: 1px solid rgba(255,255,255,0.15); border-radius: 4px;
    padding: 5px 9px; cursor: pointer; font-size: 11px;
    font-family: Tahoma; transition: all 0.2s;
    display: flex; align-items: center; gap: 4px; white-space: nowrap;
}
.toolbar-group button:hover { background: rgba(255,255,255,0.2); }
.toolbar-group button.primary { background: #3498db; border-color: #2980b9; }
.toolbar-group button.success { background: #27ae60; border-color: #229954; }
.toolbar-group button.danger { background: #e74c3c; border-color: #c0392b; }
.toolbar-group button.warning { background: #f39c12; border-color: #e67e22; color: #fff; }
.toolbar-group button.info { background: #9b59b6; border-color: #8e44ad; color: #fff; }
.toolbar-group button.active-panel { background: #f39c12; color: #fff; border-color: #e67e22; }

/* پنل کناری */
.side-panel {
    position: fixed; left: 0; top: 52px; width: 250px;
    height: calc(100vh - 52px); background: #2c3e50;
    border-right: 1px solid #1a252f; z-index: 999;
    overflow-y: auto; padding: 10px; color: #ecf0f1;
    font-family: Tahoma; transition: width 0.2s;
}
.side-panel.collapsed { width: 0; padding: 0; overflow: hidden; }
.side-panel h4 {
    margin: 10px 0 8px; font-size: 11px; color: #95a5a6;
    border-bottom: 1px solid #34495e; padding-bottom: 5px;
}
.side-panel h4:first-child { margin-top: 0; }
.panel-tab-btn {
    display: inline-block; padding: 5px 6px; margin: 1px;
    background: #34495e; color: #ecf0f1; border: none;
    border-radius: 4px; cursor: pointer; font-size: 10px;
    font-family: Tahoma;
}
.panel-tab-btn:hover { background: #3d566e; }
.panel-tab-btn.active { background: #3498db; }
.panel-content { display: none; margin-top: 8px; }
.panel-content.active { display: block; }

/* Visual Layout Grid */
.layout-grid {
    background: #1a252f; border: 2px solid #34495e; border-radius: 8px;
    padding: 8px; margin-bottom: 10px;
}
.layout-row {
    background: #34495e; border-radius: 6px; padding: 7px 9px;
    margin-bottom: 5px; display: flex; align-items: center;
    justify-content: space-between; font-size: 11px;
    border: 1px solid transparent; transition: all 0.2s;
}
.layout-row:hover { border-color: #3498db; background: #3d566e; }
.layout-row.removed { opacity: 0.4; }
.layout-row.active { background: #27ae60; }
.layout-row .row-label { display: flex; align-items: center; gap: 6px; flex-grow: 1; }
.layout-row .row-btn {
    width: 20px; height: 20px; border-radius: 50%; border: none;
    cursor: pointer; font-size: 12px; display: flex;
    align-items: center; justify-content: center; color: #fff;
}
.layout-row .row-btn.del { background: #e74c3c; }
.layout-row .row-btn.add { background: #27ae60; }
.layout-row .row-btn:hover { opacity: 0.8; }

.middle-row {
    display: grid; grid-template-columns: 1fr 1fr 1fr;
    gap: 5px; margin-bottom: 5px;
}
.middle-cell {
    background: #34495e; border-radius: 6px; padding: 8px 6px;
    text-align: center; font-size: 10px; min-height: 60px;
    border: 1px solid transparent; transition: all 0.2s;
    display: flex; flex-direction: column; align-items: center;
    justify-content: space-between;
}
.middle-cell:hover { border-color: #3498db; }
.middle-cell.active { background: #27ae60; }
.middle-cell .cell-btn {
    width: 18px; height: 18px; border-radius: 50%; border: none;
    cursor: pointer; font-size: 11px; color: #fff; margin-top: 4px;
}
.middle-cell .cell-btn.del { background: #e74c3c; }
.middle-cell .cell-btn.add { background: #27ae60; }

.column-buttons { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
.col-btn {
    background: #34495e; color: #ecf0f1; border: 1px solid #455a64;
    border-radius: 4px; padding: 6px 4px; cursor: pointer;
    font-size: 10px; transition: all 0.2s; font-family: Tahoma;
}
.col-btn:hover { background: #3d566e; border-color: #3498db; }
.col-btn.active { background: #3498db; border-color: #2980b9; }

.template-card {
    background: #34495e; border-radius: 6px; padding: 7px 9px;
    margin-bottom: 5px; cursor: pointer; font-size: 11px;
    transition: all 0.2s; border: 1px solid transparent;
}
.template-card:hover { background: #3d566e; border-color: #3498db; }
.template-card .title { font-weight: bold; margin-bottom: 2px; }
.template-card .desc { font-size: 9px; opacity: 0.7; }

.module-card {
    background: #34495e; border-radius: 6px; padding: 8px;
    margin-bottom: 5px; cursor: pointer; font-size: 11px;
    border: 2px solid transparent; transition: all 0.2s;
}
.module-card:hover { background: #3d566e; border-color: #3498db; }
.module-card.active { border-color: #27ae60; background: #2d5a4a; }
.module-card .m-name { font-weight: bold; margin-bottom: 2px; }
.module-card .m-desc { font-size: 9px; opacity: 0.7; }

.language-item {
    display: flex; align-items: center; gap: 5px;
    background: #34495e; padding: 6px 8px; border-radius: 5px;
    margin-bottom: 4px; font-size: 11px;
    border: 2px solid transparent;
}
.language-item.active { border-color: #27ae60; background: #2d5a4a; }
.language-item .flag { font-size: 14px; }
.language-item .name { flex-grow: 1; }
.language-item button {
    background: #3498db; color: #fff; border: none;
    border-radius: 3px; padding: 2px 6px; cursor: pointer; font-size: 10px;
}

.add-form input {
    width: 100%; padding: 5px 8px; margin-bottom: 4px;
    background: #2c3e50; color: #ecf0f1; border: 1px solid #455a64;
    border-radius: 3px; font-size: 11px; font-family: Tahoma;
}
.add-form input:focus { outline: none; border-color: #3498db; }
.add-form button {
    width: 100%; padding: 6px; background: #27ae60; color: #fff;
    border: none; border-radius: 3px; cursor: pointer;
    font-size: 11px; font-family: Tahoma;
}

.notification {
    position: fixed; top: 65px; left: 50%; transform: translateX(-50%);
    background: #2c3e50; color: #fff; padding: 10px 20px;
    border-radius: 6px; z-index: 99999; font-size: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.3); display: none;
    font-family: Tahoma; min-width: 200px; text-align: center;
}
.notification.success { background: #27ae60; }
.notification.error { background: #e74c3c; }
.notification.info { background: #3498db; }

.gjs-block { min-height: 70px; padding: 10px 6px; }
.gjs-block-label { font-size: 10px; margin-top: 4px; line-height: 1.2; }

.gjs-editor-cont { margin-left: 250px !important; transition: margin-left 0.2s; }
body.panel-collapsed .gjs-editor-cont { margin-left: 0 !important; }

@media (max-width: 900px) {
    .custom-toolbar { height: auto; padding: 6px; flex-wrap: wrap; gap: 6px; }
    .side-panel { width: 200px; }
    .toolbar-group button { padding: 4px 7px; font-size: 10px; }
    .gjs-editor-cont { margin-left: 200px !important; }
}

/* Sidebar Override for Editor */
@media (max-width: 900px) {
    #gjs { width: calc(100% - 70px) !important; margin-right: 70px !important; }
    .custom-toolbar { right: 70px !important; }
}
</style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>


<!-- ============ WIZARD ============ -->
<div class="wizard-overlay" id="wizard">
    <div class="wizard-box">
        <!-- مرحله ۱ -->
        <div id="step-1">
            <h1>🎨 به ویرایشگر حرفه‌ای خوش آمدید</h1>
            <p class="sub">مشخص کنید چه بخشی را می‌خواهید طراحی کنید</p>
            <div class="wizard-cards">
                <div class="wizard-card" data-type="admin" onclick="selectProjectType('admin', this)">
                    <div class="ico">🛠</div>
                    <h3>پنل مدیریت</h3>
                    <p>کنترل پنل ادمین با امکانات کامل مدیریتی</p>
                </div>
                <div class="wizard-card" data-type="public" onclick="selectProjectType('public', this)">
                    <div class="ico">🌐</div>
                    <h3>بخش عمومی سایت</h3>
                    <p>صفحات عمومی برای بازدیدکنندگان</p>
                </div>
                <div class="wizard-card" data-type="user" onclick="selectProjectType('user', this)">
                    <div class="ico">👤</div>
                    <h3>داشبورد کاربران</h3>
                    <p>پنل کاربری و داشبورد شخصی</p>
                </div>
            </div>
            <div class="wizard-actions">
                <span></span>
                <button class="next" id="btn-step-1-next" disabled onclick="goToStep2()">مرحله بعد ←</button>
            </div>
        </div>

        <!-- مرحله ۲ -->
        <div id="step-2" style="display:none;">
            <h1>⚙️ انتخاب ماژول‌ها</h1>
            <p class="sub" id="modules-sub">ماژول‌های مورد نیاز خود را انتخاب کنید</p>
            <div class="module-grid" id="modules-grid"></div>
            <div class="wizard-actions">
                <button class="back" onclick="goToStep1()">→ مرحله قبل</button>
                <button class="next" onclick="finishWizard()">شروع طراحی 🚀</button>
            </div>
        </div>
    </div>
</div>

<!-- ============ MAIN EDITOR ============ -->
<div id="main-editor">

    <!-- نوار ابزار -->
    <div class="custom-toolbar">
        <div class="toolbar-title">
            <span id="project-type-icon">🛠</span>
            <span id="project-type-label">پنل مدیریت</span>
            <small><?= htmlspecialchars($siteName) ?></small>
        </div>
        <div class="toolbar-group">
            <button onclick="toggleSidePanel()" title="نمایش/مخفی پنل"><i class="bi bi-layout-sidebar"></i></button>
            <button onclick="saveProject()" class="success"><i class="bi bi-save"></i> ذخیره</button>
            <button onclick="loadProject()"><i class="bi bi-folder2-open"></i> بارگذاری</button>
            <button onclick="exportZIP()" class="primary"><i class="bi bi-file-zip"></i> ZIP</button>
            <button onclick="toggleDirection()" class="info"><i class="bi bi-arrow-left-right"></i> <span id="dir-label">RTL</span></button>
            <button onclick="openGjsPanel('blocks')" id="btn-blocks"><i class="bi bi-grid"></i> بلاک‌ها</button>
            <button onclick="openGjsPanel('layers')" id="btn-layers"><i class="bi bi-layers"></i> لایه‌ها</button>
            <button onclick="openGjsPanel('styles')" id="btn-styles"><i class="bi bi-palette"></i> استایل</button>
            <button onclick="openGjsPanel('settings')" id="btn-settings"><i class="bi bi-gear"></i> تنظیمات</button>
            <button onclick="toggleBorders()"><i class="bi bi-border-all"></i> مرزها</button>
            <button onclick="editor.runCommand('core:undo')"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button onclick="editor.runCommand('core:redo')"><i class="bi bi-arrow-clockwise"></i></button>
            <button onclick="previewSite()"><i class="bi bi-eye"></i></button>
            <button onclick="clearCanvas()" class="danger"><i class="bi bi-trash"></i></button>
            <button onclick="goBack()" class="warning"><i class="bi bi-arrow-right"></i> بازگشت</button>
        </div>
    </div>

    <!-- پنل کناری -->
    <div class="side-panel" id="side-panel">
        <div style="text-align:center; margin-bottom:6px;">
            <button class="panel-tab-btn active" onclick="switchTab('structure', this)"><i class="bi bi-grid-3x3"></i> ساختار</button>
            <button class="panel-tab-btn" onclick="switchTab('modules', this)"><i class="bi bi-puzzle"></i> ماژول</button>
            <button class="panel-tab-btn" onclick="switchTab('language', this)"><i class="bi bi-globe"></i> زبان</button>
        </div>

        <!-- تب ساختار -->
        <div id="tab-structure" class="panel-content active">
            <h4>🧱 بخش‌های صفحه</h4>
            <div class="layout-grid" id="layout-grid"></div>

            <h4>📊 ستون‌های محتوا</h4>
            <div class="column-buttons">
                <button class="col-btn" onclick="setContentColumns(1, this)">۱</button>
                <button class="col-btn" onclick="setContentColumns(2, this)">۲</button>
                <button class="col-btn" onclick="setContentColumns(3, this)">۳</button>
                <button class="col-btn" onclick="setContentColumns(4, this)">۴</button>
                <button class="col-btn" onclick="setContentColumns(6, this)">۶</button>
                <button class="col-btn" onclick="setContentColumns(8, this)">۸</button>
            </div>

            <h4>📐 قالب‌های آماده</h4>
            <div class="template-card" onclick="loadTemplate('admin-panel')">
                <div class="title">🛠 پنل ادمین</div>
                <div class="desc">داشبورد مدیریتی</div>
            </div>
            <div class="template-card" onclick="loadTemplate('user-dashboard')">
                <div class="title">👤 داشبورد کاربر</div>
                <div class="desc">پنل کاربری</div>
            </div>
            <div class="template-card" onclick="loadTemplate('ecommerce')">
                <div class="title">🛒 فروشگاه</div>
                <div class="desc">قالب فروشگاهی</div>
            </div>
            <div class="template-card" onclick="loadTemplate('blog')">
                <div class="title">📝 وبلاگ</div>
                <div class="desc">قالب وبلاگی</div>
            </div>
        </div>

        <!-- تب ماژول -->
        <div id="tab-modules" class="panel-content">
            <h4>🧩 ماژول‌های فعال</h4>
            <div id="active-modules-list"></div>
            <p style="font-size:10px; opacity:0.6; line-height:1.5; margin-top:10px;">
                💡 برای فعال/غیرفعال کردن، روی هر ماژول کلیک کنید
            </p>
        </div>

        <!-- تب زبان -->
        <div id="tab-language" class="panel-content">
            <h4>🧭 جهت صفحه</h4>
            <div style="display:flex; gap:5px; margin-bottom:10px;">
                <button class="col-btn" id="btn-rtl" onclick="setDirection('rtl')" style="flex:1;">RTL</button>
                <button class="col-btn" id="btn-ltr" onclick="setDirection('ltr')" style="flex:1;">LTR</button>
            </div>

            <h4>🌍 زبان‌ها</h4>
            <div id="languages-list"></div>

            <div class="add-form" style="margin-top:8px;">
                <input type="text" id="new-lang-name" placeholder="نام (مثال: آلمانی)">
                <input type="text" id="new-lang-code" placeholder="کد (مثال: de)">
                <input type="text" id="new-lang-flag" placeholder="پرچم (🇩🇪)">
                <button onclick="addNewLanguage()">➕ افزودن زبان</button>
            </div>
        </div>
    </div>

    <div id="gjs"></div>
    <div id="notification" class="notification"></div>

    <button id="open-panel-btn" onclick="toggleSidePanel()" style="position:fixed; top:60px; left:10px; z-index:1001; display:none; background:#3498db; color:#fff; border:none; border-radius:50%; width:36px; height:36px; cursor:pointer; font-size:16px;">
        <i class="bi bi-list"></i>
    </button>
</div>

<script src="../libs/grapes.min.js"></script>
<script src="../libs/grapesjs-plugin-export.min.js"></script>
<script src="../libs/grapesjs-plugin-forms.min.js"></script>
<script src="../libs/grapesjs-template-manager.min.js"></script>

<script>
// ================================================================
// ۱. WIZARD
// ================================================================
let projectType = null;
let selectedModules = [];
let editor = null;

const PROJECT_TYPES = {
    'admin': { icon: '🛠', label: 'پنل مدیریت', modules: [
        { id: 'roles', name: 'مدیریت نقش‌ها', desc: 'تعریف نقش و سطوح دسترسی', icon: '👥' },
        { id: 'users', name: 'مدیریت کاربران', desc: 'افزودن، ویرایش و حذف کاربران', icon: '👤' },
        { id: 'permissions', name: 'تعیین دسترسی', desc: 'کنترل دقیق دسترسی هر نقش', icon: '🔐' },
        { id: 'form-builder', name: 'فرم‌ساز', desc: 'ساخت فرم‌های پویا', icon: '📝' },
        { id: 'table-builder', name: 'جدول‌ساز', desc: 'جدول‌های داده پویا', icon: '📊' },
        { id: 'report-builder', name: 'ریپورت‌ساز', desc: 'گزارش‌های سفارشی', icon: '📈' },
        { id: 'template-mgr', name: 'مدیریت قالب‌ها', desc: 'انتخاب و مدیریت قالب', icon: '🎨' },
        { id: 'content-mgr', name: 'مدیریت محتوا', desc: 'ایجاد و ویرایش محتوا', icon: '📄' },
        { id: 'translate', name: 'مدیریت ترجمه', desc: 'افزودن هوشمند زبان', icon: '🌐' },
        { id: 'settings', name: 'تنظیمات سایت', desc: 'پیکربندی کلی', icon: '⚙️' }
    ]},
    'public': { icon: '🌐', label: 'بخش عمومی سایت', modules: [
        { id: 'navbar', name: 'نوار ناوبری', desc: 'منوی اصلی سایت', icon: '🧭' },
        { id: 'hero', name: 'بخش Hero', desc: 'بنر اصلی', icon: '🎯' },
        { id: 'services', name: 'خدمات', desc: 'معرفی خدمات', icon: '💼' },
        { id: 'portfolio', name: 'نمونه کارها', desc: 'گالری پروژه‌ها', icon: '📷' },
        { id: 'testimonials', name: 'نظرات', desc: 'نظرات مشتریان', icon: '💬' },
        { id: 'pricing', name: 'جدول قیمت', desc: 'پلن‌های قیمت‌گذاری', icon: '💰' },
        { id: 'blog', name: 'وبلاگ', desc: 'مقالات و اخبار', icon: '📰' },
        { id: 'contact', name: 'فرم تماس', desc: 'ارتباط با ما', icon: '📧' },
        { id: 'newsletter', name: 'خبرنامه', desc: 'عضویت در خبرنامه', icon: '📮' },
        { id: 'footer', name: 'فوتر', desc: 'پاورقی سایت', icon: '🔻' }
    ]},
    'user': { icon: '👤', label: 'داشبورد کاربران', modules: [
        { id: 'profile', name: 'پروفایل کاربر', desc: 'اطلاعات شخصی', icon: '👤' },
        { id: 'dashboard', name: 'داشبورد اصلی', desc: 'نمای کلی', icon: '📊' },
        { id: 'orders', name: 'سفارشات', desc: 'لیست سفارشات', icon: '🛒' },
        { id: 'messages', name: 'پیام‌ها', desc: 'پیام‌های کاربر', icon: '✉️' },
        { id: 'notifications', name: 'اعلان‌ها', desc: 'اعلان‌های کاربر', icon: '🔔' },
        { id: 'wallet', name: 'کیف پول', desc: 'موجودی و تراکنش‌ها', icon: '💳' },
        { id: 'tickets', name: 'تیکت‌های پشتیبانی', desc: 'ارتباط با پشتیبانی', icon: '🎫' },
        { id: 'settings', name: 'تنظیمات حساب', desc: 'تنظیمات شخصی', icon: '⚙️' }
    ]}
};

function selectProjectType(type, el) {
    document.querySelectorAll('.wizard-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    projectType = type;
    document.getElementById('btn-step-1-next').disabled = false;
}

function goToStep2() {
    if (!projectType) return;
    document.getElementById('step-1').style.display = 'none';
    document.getElementById('step-2').style.display = 'block';
    const typeInfo = PROJECT_TYPES[projectType];
    document.getElementById('modules-sub').textContent = `ماژول‌های مورد نیاز برای ${typeInfo.label} را انتخاب کنید`;
    const grid = document.getElementById('modules-grid');
    grid.innerHTML = '';
    selectedModules = typeInfo.modules.map(m => m.id);
    typeInfo.modules.forEach(mod => {
        const item = document.createElement('div');
        item.className = 'module-item active';
        item.dataset.moduleId = mod.id;
        item.innerHTML = `
            <div class="chk">✓</div>
            <div class="m-info">
                <div class="m-title">${mod.icon} ${mod.name}</div>
                <div class="m-desc">${mod.desc}</div>
            </div>`;
        item.onclick = () => toggleModuleItem(item, mod.id);
        grid.appendChild(item);
    });
}

function toggleModuleItem(el, id) {
    const isActive = el.classList.contains('active');
    if (isActive) {
        el.classList.remove('active');
        el.querySelector('.chk').textContent = '';
        selectedModules = selectedModules.filter(m => m !== id);
    } else {
        el.classList.add('active');
        el.querySelector('.chk').textContent = '✓';
        selectedModules.push(id);
    }
}

function goToStep1() {
    document.getElementById('step-2').style.display = 'none';
    document.getElementById('step-1').style.display = 'block';
}

function finishWizard() {
    document.getElementById('wizard').style.display = 'none';
    document.getElementById('main-editor').style.display = 'block';
    const typeInfo = PROJECT_TYPES[projectType];
    document.getElementById('project-type-icon').textContent = typeInfo.icon;
    document.getElementById('project-type-label').textContent = typeInfo.label;

    initEditor();
    renderActiveModules();
    renderLayoutGrid();
    renderLanguagesList();

    setTimeout(() => {
        applyProjectTemplate();
        notify(`✅ پروژه "${typeInfo.label}" آماده شد`, 'success', 3000);
    }, 500);
}

// ================================================================
// ۲. ماژول‌ها (پنل کناری)
// ================================================================
function renderActiveModules() {
    const list = document.getElementById('active-modules-list');
    const typeInfo = PROJECT_TYPES[projectType];
    list.innerHTML = '';
    typeInfo.modules.forEach(mod => {
        const isActive = selectedModules.includes(mod.id);
        const item = document.createElement('div');
        item.className = 'module-card' + (isActive ? ' active' : '');
        item.innerHTML = `<div class="m-name">${mod.icon} ${mod.name}</div><div class="m-desc">${mod.desc}</div>`;
        item.onclick = () => {
            if (isActive) {
                selectedModules = selectedModules.filter(m => m !== mod.id);
                notify(`⏸ ماژول "${mod.name}" غیرفعال شد`, 'info', 1500);
            } else {
                selectedModules.push(mod.id);
                notify(`✅ ماژول "${mod.name}" فعال شد`, 'success', 1500);
            }
            renderActiveModules();
            applyProjectTemplate();
        };
        list.appendChild(item);
    });
}

// ================================================================
// ۳. Layout Grid بصری
// ================================================================
const LAYOUT_STATE = {
    header: true, topMenu: true,
    leftSidebar: false, content: true, rightSidebar: false,
    bottomMenu: false, footer: true
};

function renderLayoutGrid() {
    const grid = document.getElementById('layout-grid');
    grid.innerHTML = '';
    grid.appendChild(createLayoutRow('header', '🔝 هدر', LAYOUT_STATE.header));
    grid.appendChild(createLayoutRow('topMenu', '📑 منو تاپ', LAYOUT_STATE.topMenu));

    const middleRow = document.createElement('div');
    middleRow.className = 'middle-row';

    middleRow.appendChild(makeMiddleCell('leftSidebar', '⬅️ چپ', LAYOUT_STATE.leftSidebar));
    middleRow.appendChild(makeMiddleCell('content', '📄 محتوا', LAYOUT_STATE.content));
    middleRow.appendChild(makeMiddleCell('rightSidebar', '➡️ راست', LAYOUT_STATE.rightSidebar));

    grid.appendChild(middleRow);
    grid.appendChild(createLayoutRow('bottomMenu', '📎 منو باتن', LAYOUT_STATE.bottomMenu));
    grid.appendChild(createLayoutRow('footer', '🔻 فوتر', LAYOUT_STATE.footer));
}

function createLayoutRow(key, label, isActive) {
    const row = document.createElement('div');
    row.className = 'layout-row' + (isActive ? ' active' : ' removed');
    row.innerHTML = `
        <div class="row-label"><span>${label}</span></div>
        ${isActive
            ? `<button class="row-btn del" onclick="toggleSection('${key}')" title="حذف">×</button>`
            : `<button class="row-btn add" onclick="toggleSection('${key}')" title="افزودن">+</button>`}`;
    return row;
}

function makeMiddleCell(key, label, isActive) {
    const cell = document.createElement('div');
    cell.className = 'middle-cell' + (isActive ? ' active' : '');
    cell.innerHTML = `
        <span>${label}</span>
        ${isActive
            ? `<button class="cell-btn del" onclick="toggleSection('${key}')">×</button>`
            : `<button class="cell-btn add" onclick="toggleSection('${key}')">+</button>`}`;
    return cell;
}

function toggleSection(key) {
    LAYOUT_STATE[key] = !LAYOUT_STATE[key];
    renderLayoutGrid();
    applyProjectTemplate();
    const names = {
        header: 'هدر', topMenu: 'منو تاپ', leftSidebar: 'سایدبار چپ',
        content: 'محتوا', rightSidebar: 'سایدبار راست',
        bottomMenu: 'منو باتن', footer: 'فوتر'
    };
    notify(`${LAYOUT_STATE[key] ? '✅ افزودن' : '🗑 حذف'} ${names[key]}`, LAYOUT_STATE[key] ? 'success' : 'info', 1500);
}

let contentColumns = 1;

function setContentColumns(n, btn) {
    contentColumns = n;
    document.querySelectorAll('.col-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    applyProjectTemplate();
    notify(`📊 محتوا به ${n} ستون تغییر یافت`, 'info', 1500);
}

// ================================================================
// ۴. تولید HTML
// ================================================================
function applyProjectTemplate() {
    if (!editor || !projectType) return;
    const dir = currentDirection;
    const typeInfo = PROJECT_TYPES[projectType];
    let html = `<div style="display:flex;flex-direction:column;min-height:100vh;font-family:Tahoma;direction:${dir};">`;

    if (LAYOUT_STATE.header) {
        html += `<header style="background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.08);padding:15px 30px;display:flex;justify-content:space-between;align-items:center;">
            <div style="font-size:22px;font-weight:bold;color:#2c3e50;">${typeInfo.icon} لوگو</div>
            <nav style="display:flex;gap:20px;"><a href="#" style="color:#34495e;text-decoration:none;">خانه</a><a href="#" style="color:#34495e;text-decoration:none;">درباره</a><a href="#" style="color:#34495e;text-decoration:none;">تماس</a></nav>
            <div style="display:flex;gap:8px;"><button style="background:#3498db;color:#fff;border:none;padding:6px 12px;border-radius:5px;cursor:pointer;font-size:12px;">ورود</button></div>
        </header>`;
    }

    if (LAYOUT_STATE.topMenu) {
        html += `<nav style="background:#34495e;padding:10px 30px;display:flex;justify-content:center;gap:25px;">
            <a href="#" style="color:#ecf0f1;text-decoration:none;font-size:13px;">صفحه اصلی</a>
            <a href="#" style="color:#ecf0f1;text-decoration:none;font-size:13px;">محصولات</a>
            <a href="#" style="color:#ecf0f1;text-decoration:none;font-size:13px;">وبلاگ</a>
            <a href="#" style="color:#ecf0f1;text-decoration:none;font-size:13px;">تماس</a>
        </nav>`;
    }

    const hasLeft = LAYOUT_STATE.leftSidebar, hasRight = LAYOUT_STATE.rightSidebar, hasContent = LAYOUT_STATE.content;
    if (hasLeft || hasRight || hasContent) {
        html += '<div style="display:flex;flex:1;min-height:400px;">';
        if (hasLeft) {
            html += `<aside style="width:240px;background:#2c3e50;color:#ecf0f1;padding:20px;">
                <h3 style="color:#fff;margin:0 0 15px;padding-bottom:8px;border-bottom:1px solid #34495e;font-size:14px;">منوی کناری</h3>
                <ul style="list-style:none;padding:0;font-size:13px;line-height:2.5;">
                    <li><a href="#" style="color:#ecf0f1;text-decoration:none;">📊 داشبورد</a></li>
                    <li><a href="#" style="color:#ecf0f1;text-decoration:none;">👤 پروفایل</a></li>
                    <li><a href="#" style="color:#ecf0f1;text-decoration:none;">⚙️ تنظیمات</a></li>
                </ul>
            </aside>`;
        }
        if (hasContent) {
            html += `<main style="flex:1;padding:25px;background:#f5f7fa;"><h2 style="color:#2c3e50;margin:0 0 15px;">${typeInfo.label}</h2>`;
            if (contentColumns > 1) {
                html += `<div style="display:grid;grid-template-columns:repeat(${contentColumns},1fr);gap:12px;">`;
                for (let i = 1; i <= contentColumns; i++) {
                    html += `<div style="background:#fff;border-radius:8px;padding:20px;min-height:120px;box-shadow:0 2px 8px rgba(0,0,0,0.05);text-align:center;color:#95a5a6;border:2px dashed #bdc3c7;">ستون ${i}</div>`;
                }
                html += '</div>';
            } else {
                html += `<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">`;
                typeInfo.modules.filter(m => selectedModules.includes(m.id)).forEach(mod => {
                    html += `<div style="background:#fff;border-radius:10px;padding:18px;box-shadow:0 2px 10px rgba(0,0,0,0.06);border-right:4px solid #3498db;">
                        <div style="font-size:24px;margin-bottom:8px;">${mod.icon}</div>
                        <div style="font-weight:bold;color:#2c3e50;font-size:14px;margin-bottom:5px;">${mod.name}</div>
                        <div style="font-size:12px;color:#7f8c8d;line-height:1.5;">${mod.desc}</div>
                    </div>`;
                });
                html += '</div>';
            }
            html += `</main>`;
        }
        if (hasRight) {
            html += `<aside style="width:240px;background:#f8f9fa;border-right:1px solid #e0e0e0;padding:20px;">
                <h4 style="color:#2c3e50;margin:0 0 15px;font-size:13px;">📊 آمار</h4>
                <div style="background:#fff;padding:12px;border-radius:6px;margin-bottom:8px;font-size:12px;">بازدید: ۱,۲۳۴</div>
                <div style="background:#fff;padding:12px;border-radius:6px;font-size:12px;">اعضا: ۵۶۷</div>
            </aside>`;
        }
        html += '</div>';
    }

    if (LAYOUT_STATE.bottomMenu) {
        html += `<nav style="background:#2c3e50;padding:10px 30px;display:flex;justify-content:center;gap:35px;">
            <a href="#" style="color:#bdc3c7;text-decoration:none;font-size:12px;">🏠 خانه</a>
            <a href="#" style="color:#bdc3c7;text-decoration:none;font-size:12px;">🔍 جستجو</a>
            <a href="#" style="color:#bdc3c7;text-decoration:none;font-size:12px;">👤 پروفایل</a>
        </nav>`;
    }
    if (LAYOUT_STATE.footer) {
        html += `<footer style="background:#2c3e50;color:#bdc3c7;padding:25px 30px;text-align:center;font-size:12px;">© ۲۰۲۶ - تمامی حقوق محفوظ است.</footer>`;
    }

    html += '</div>';
    editor.setComponents(html);
}

// ================================================================
// ۵. قالب‌های آماده
// ================================================================
function loadTemplate(type) {
    const dir = currentDirection;
    let html = '';
    switch (type) {
        case 'admin-panel':
            html = `<div style="display:flex;min-height:100vh;font-family:Tahoma;direction:${dir};">
                <aside style="width:250px;background:#2c3e50;color:#ecf0f1;padding:20px;min-height:100vh;">
                    <h3 style="color:#fff;margin:0 0 20px;padding-bottom:10px;border-bottom:1px solid #34495e;">🛠 پنل مدیریت</h3>
                    <ul style="list-style:none;padding:0;line-height:2.5;">
                        <li><a href="#" style="color:#ecf0f1;text-decoration:none;">📊 داشبورد</a></li>
                        <li><a href="#" style="color:#ecf0f1;text-decoration:none;">👥 کاربران</a></li>
                        <li><a href="#" style="color:#ecf0f1;text-decoration:none;">📦 محصولات</a></li>
                        <li><a href="#" style="color:#ecf0f1;text-decoration:none;">⚙️ تنظیمات</a></li>
                    </ul>
                </aside>
                <main style="flex:1;padding:30px;background:#f5f7fa;">
                    <div style="background:#fff;padding:20px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);"><h2 style="margin:0;color:#2c3e50;">📊 داشبورد</h2></div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;">
                        <div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:10px;"><div style="opacity:0.8;">کاربران</div><div style="font-size:28px;font-weight:bold;">۱,۲۳۴</div></div>
                        <div style="background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;padding:20px;border-radius:10px;"><div style="opacity:0.8;">فروش</div><div style="font-size:28px;font-weight:bold;">۵۶۷</div></div>
                        <div style="background:linear-gradient(135deg,#f093fb,#f5576c);color:#fff;padding:20px;border-radius:10px;"><div style="opacity:0.8;">درآمد</div><div style="font-size:28px;font-weight:bold;">۸۹۰</div></div>
                    </div>
                </main>
            </div>`;
            break;
        case 'user-dashboard':
            html = `<div style="font-family:Tahoma;direction:${dir};">
                <header style="background:#3498db;color:#fff;padding:20px 30px;display:flex;justify-content:space-between;align-items:center;">
                    <div style="font-size:20px;font-weight:bold;">👤 پنل کاربری</div>
                    <button style="background:rgba(255,255,255,0.2);color:#fff;border:none;padding:8px 15px;border-radius:6px;cursor:pointer;">خروج</button>
                </header>
                <main style="padding:30px;background:#f5f7fa;min-height:400px;"><h2 style="color:#2c3e50;">پروفایل من</h2><p style="color:#7f8c8d;">اطلاعات حساب کاربری خود را اینجا می‌بینید.</p></main>
            </div>`;
            break;
        case 'ecommerce':
            html = `<div style="font-family:Tahoma;direction:${dir};">
                <header style="background:#2c3e50;padding:15px 30px;display:flex;justify-content:space-between;align-items:center;">
                    <div style="color:#fff;font-size:22px;font-weight:bold;">🛒 فروشگاه</div>
                    <div style="display:flex;gap:20px;"><a href="#" style="color:#ecf0f1;text-decoration:none;">خانه</a><a href="#" style="color:#ecf0f1;text-decoration:none;">محصولات</a></div>
                </header>
                <main style="padding:30px;background:#f5f7fa;min-height:400px;">
                    <h1 style="text-align:center;color:#2c3e50;">محصولات ویژه</h1>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-top:30px;">
                        ${Array(6).fill(`<div style="background:#fff;border-radius:10px;padding:15px;box-shadow:0 2px 10px rgba(0,0,0,0.05);"><div style="height:150px;background:#ecf0f1;border-radius:8px;margin-bottom:10px;"></div><h3 style="margin:0 0 5px;">محصول</h3><p style="color:#3498db;font-weight:bold;margin:0;">۱۵۰,۰۰۰ تومان</p></div>`).join('')}
                    </div>
                </main>
            </div>`;
            break;
        case 'blog':
            html = `<div style="font-family:Tahoma;direction:${dir};">
                <header style="background:#fff;padding:20px 30px;box-shadow:0 2px 10px rgba(0,0,0,0.08);text-align:center;">
                    <h1 style="margin:0;color:#2c3e50;">📝 وبلاگ من</h1>
                    <nav style="margin-top:15px;"><a href="#" style="margin:0 15px;color:#3498db;text-decoration:none;">خانه</a><a href="#" style="margin:0 15px;color:#3498db;text-decoration:none;">درباره</a></nav>
                </header>
                <main style="max-width:900px;margin:0 auto;padding:30px;">
                    <article style="background:#fff;padding:25px;border-radius:10px;margin-bottom:20px;box-shadow:0 2px 10px rgba(0,0,0,0.05);"><h2 style="color:#2c3e50;margin-top:0;">عنوان مقاله اول</h2><p style="color:#7f8c8d;line-height:1.8;">خلاصه‌ای از مقاله...</p></article>
                </main>
            </div>`;
            break;
    }
    editor.setComponents(html);
    notify('✅ قالب بارگذاری شد', 'success', 2000);
}

// ================================================================
// ۶. زبان و جهت
// ================================================================
let currentDirection = localStorage.getItem('gjs_direction') || 'rtl';
let activeLanguage = localStorage.getItem('gjs_active_lang') || 'fa';

const DEFAULT_LANGUAGES = [
    { code: 'fa', name: 'فارسی', flag: '🇮🇷', direction: 'rtl', builtin: true },
    { code: 'ar', name: 'العربية', flag: '🇸🇦', direction: 'rtl', builtin: true },
    { code: 'en', name: 'English', flag: '🇬🇧', direction: 'ltr', builtin: true }
];
let languages = JSON.parse(localStorage.getItem('gjs_languages') || 'null') || [...DEFAULT_LANGUAGES];

function renderLanguagesList() {
    const list = document.getElementById('languages-list');
    list.innerHTML = '';
    languages.forEach(lang => {
        const item = document.createElement('div');
        item.className = 'language-item' + (lang.code === activeLanguage ? ' active' : '');
        item.innerHTML = `
            <span class="flag">${lang.flag}</span>
            <span class="name">${lang.name}</span>
            <button onclick="selectLanguage('${lang.code}')">${lang.code === activeLanguage ? '✓' : 'انتخاب'}</button>
            ${!lang.builtin ? `<button style="background:#e74c3c;" onclick="deleteLanguage('${lang.code}')">×</button>` : ''}`;
        list.appendChild(item);
    });
}

function selectLanguage(code) {
    const lang = languages.find(l => l.code === code);
    if (!lang) return;
    activeLanguage = code;
    localStorage.setItem('gjs_active_lang', code);
    setDirection(lang.direction);
    renderLanguagesList();
    notify(`🌍 زبان "${lang.name}" فعال شد`, 'success', 1500);
}

function addNewLanguage() {
    const name = document.getElementById('new-lang-name').value.trim();
    const code = document.getElementById('new-lang-code').value.trim().toLowerCase();
    const flag = document.getElementById('new-lang-flag').value.trim() || '🏳️';
    if (!name || !code) { notify('⚠️ نام و کد الزامی', 'error', 2000); return; }
    if (!/^[a-z]{2,5}$/.test(code)) { notify('⚠️ کد باید ۲-۵ حرف انگلیسی باشد', 'error', 2000); return; }
    if (languages.find(l => l.code === code)) { notify('⚠️ این کد قبلاً وجود دارد', 'error', 2000); return; }
    const rtlLangs = ['fa', 'ar', 'he', 'ur', 'ps', 'sd', 'yi'];
    languages.push({ code, name, flag, direction: rtlLangs.includes(code) ? 'rtl' : 'ltr', builtin: false });
    localStorage.setItem('gjs_languages', JSON.stringify(languages));
    renderLanguagesList();
    document.getElementById('new-lang-name').value = '';
    document.getElementById('new-lang-code').value = '';
    document.getElementById('new-lang-flag').value = '';
    notify(`✅ زبان "${name}" اضافه شد`, 'success', 2000);
}

function deleteLanguage(code) {
    const lang = languages.find(l => l.code === code);
    if (!lang || lang.builtin) return;
    if (!confirm(`حذف "${lang.name}"؟`)) return;
    languages = languages.filter(l => l.code !== code);
    localStorage.setItem('gjs_languages', JSON.stringify(languages));
    if (activeLanguage === code) selectLanguage('fa');
    renderLanguagesList();
}

function setDirection(dir) {
    currentDirection = dir;
    localStorage.setItem('gjs_direction', dir);
    document.documentElement.dir = dir;
    document.body.dir = dir;
    const rtlBtn = document.getElementById('btn-rtl');
    const ltrBtn = document.getElementById('btn-ltr');
    if (rtlBtn) rtlBtn.classList.toggle('active', dir === 'rtl');
    if (ltrBtn) ltrBtn.classList.toggle('active', dir === 'ltr');
    const dirLabel = document.getElementById('dir-label');
    if (dirLabel) dirLabel.textContent = dir.toUpperCase();
    if (editor) {
        editor.setStyle(`
            body { font-family: Tahoma, Arial, sans-serif !important; background-color: #f5f7fa !important; margin: 0; padding: 0; min-height: 100vh; direction: ${dir}; text-align: ${dir === 'rtl' ? 'right' : 'left'}; }
            * { box-sizing: border-box; }
        `);
        try {
            const frame = editor.Canvas.getFrameEl();
            if (frame && frame.contentDocument) {
                frame.contentDocument.documentElement.dir = dir;
                frame.contentDocument.body.style.direction = dir;
            }
        } catch(e) {}
    }
}

function toggleDirection() {
    setDirection(currentDirection === 'rtl' ? 'ltr' : 'rtl');
    notify(`🧭 جهت: ${currentDirection.toUpperCase()}`, 'info', 1500);
}

// ================================================================
// ۷. کتابخانه بلاک‌ها (کامل)
// ================================================================
const allBlocks = [
    // احراز هویت
    { id: 'auth-login', label: '🔐 ورود', category: '🔑 احراز هویت',
      content: `<div style="max-width:400px;margin:20px auto;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);font-family:Tahoma;"><div style="text-align:center;font-size:48px;margin-bottom:15px;">🔐</div><h3 style="text-align:center;margin-bottom:25px;color:#2c3e50;">ورود به حساب</h3><form method="post"><div style="margin-bottom:20px;"><label style="display:block;margin-bottom:8px;color:#34495e;font-size:14px;">نام کاربری</label><input type="text" name="username" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;"></div><div style="margin-bottom:25px;"><label style="display:block;margin-bottom:8px;color:#34495e;font-size:14px;">رمز عبور</label><input type="password" name="password" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;"></div><button type="submit" style="width:100%;padding:14px;background:linear-gradient(135deg,#3498db,#2980b9);color:white;border:none;border-radius:8px;cursor:pointer;font-size:16px;font-weight:bold;">ورود</button></form></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10,17V14H3V10H10V7L15,12L10,17M10,2H19A2,2 0 0,1 21,4V20A2,2 0 0,1 19,22H10A2,2 0 0,1 8,20V18H10V20H19V4H10V6H8V4A2,2 0 0,1 10,2Z"/></svg>' },
    { id: 'auth-register', label: '📝 ثبت‌نام', category: '🔑 احراز هویت',
      content: `<div style="max-width:400px;margin:20px auto;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);font-family:Tahoma;"><div style="text-align:center;font-size:48px;margin-bottom:15px;">📝</div><h3 style="text-align:center;margin-bottom:25px;color:#2c3e50;">ثبت‌نام</h3><form method="post"><div style="margin-bottom:20px;"><label style="display:block;margin-bottom:8px;color:#34495e;font-size:14px;">نام کامل</label><input type="text" name="name" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;"></div><div style="margin-bottom:20px;"><label style="display:block;margin-bottom:8px;color:#34495e;font-size:14px;">ایمیل</label><input type="email" name="email" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;"></div><div style="margin-bottom:25px;"><label style="display:block;margin-bottom:8px;color:#34495e;font-size:14px;">رمز عبور</label><input type="password" name="password" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;"></div><button type="submit" style="width:100%;padding:14px;background:linear-gradient(135deg,#27ae60,#229954);color:white;border:none;border-radius:8px;cursor:pointer;font-size:16px;font-weight:bold;">ثبت‌نام</button></form></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M15,14C12.33,14 7,15.33 7,18V20H23V18C23,15.33 17.67,14 15,14M6,10V7H4V10H1V12H4V15H6V12H9V10M15,12A4,4 0 0,0 19,8A4,4 0 0,0 15,4A4,4 0 0,0 11,8A4,4 0 0,0 15,12Z"/></svg>' },
    { id: 'auth-forgot', label: '🔓 فراموشی رمز', category: '🔑 احراز هویت',
      content: `<div style="max-width:400px;margin:20px auto;padding:30px;background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.1);font-family:Tahoma;"><div style="text-align:center;font-size:48px;margin-bottom:15px;">🔓</div><h3 style="text-align:center;margin-bottom:10px;color:#2c3e50;">بازیابی رمز</h3><p style="text-align:center;color:#7f8c8d;font-size:13px;margin-bottom:25px;">ایمیل خود را وارد کنید</p><form><input type="email" required style="width:100%;padding:12px 15px;border:2px solid #e0e0e0;border-radius:8px;box-sizing:border-box;font-family:Tahoma;margin-bottom:20px;"><button type="submit" style="width:100%;padding:14px;background:#f39c12;color:white;border:none;border-radius:8px;cursor:pointer;font-size:16px;font-weight:bold;">ارسال لینک</button></form></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12,17A2,2 0 0,0 14,15C14,13.89 13.1,13 12,13A2,2 0 0,0 10,15A2,2 0 0,0 12,17M18,8A2,2 0 0,1 20,10V20A2,2 0 0,1 18,22H6A2,2 0 0,1 4,20V10C4,8.89 4.9,8 6,8H7V6A5,5 0 0,1 12,1A5,5 0 0,1 17,6V8H18M12,3A3,3 0 0,0 9,6V8H15V6A3,3 0 0,0 12,3Z"/></svg>' },
    // منوها
    { id: 'menu-horizontal', label: 'منوی افقی', category: '📋 منوها',
      content: `<nav style="background:#34495e;padding:15px 30px;display:flex;justify-content:center;gap:30px;font-family:Tahoma;border-radius:8px;"><a href="#" style="color:#ecf0f1;text-decoration:none;padding:8px 15px;">خانه</a><a href="#" style="color:#ecf0f1;text-decoration:none;padding:8px 15px;">محصولات</a><a href="#" style="color:#ecf0f1;text-decoration:none;padding:8px 15px;">خدمات</a><a href="#" style="color:#ecf0f1;text-decoration:none;padding:8px 15px;">تماس</a></nav>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3,6H21V8H3V6M3,11H21V13H3V11M3,16H21V18H3V16Z"/></svg>' },
    { id: 'menu-vertical', label: 'منوی عمودی', category: '📋 منوها',
      content: `<nav style="background:#2c3e50;padding:20px;width:220px;font-family:Tahoma;border-radius:8px;"><a href="#" style="display:block;color:#ecf0f1;text-decoration:none;padding:12px 15px;margin-bottom:5px;border-radius:5px;background:rgba(255,255,255,0.05);">📊 داشبورد</a><a href="#" style="display:block;color:#ecf0f1;text-decoration:none;padding:12px 15px;margin-bottom:5px;border-radius:5px;background:rgba(255,255,255,0.05);">👤 پروفایل</a><a href="#" style="display:block;color:#ecf0f1;text-decoration:none;padding:12px 15px;border-radius:5px;background:rgba(255,255,255,0.05);">🚪 خروج</a></nav>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M3,3H9V21H3V3M11,3H21V21H11V3Z"/></svg>' },
    { id: 'menu-dropdown', label: 'دراپ‌داون', category: '📋 منوها',
      content: `<nav style="background:#34495e;padding:15px 30px;display:flex;gap:20px;font-family:Tahoma;"><div style="position:relative;"><a href="#" style="color:#ecf0f1;text-decoration:none;padding:8px 15px;display:block;">محصولات ▾</a><div style="background:#2c3e50;padding:10px;border-radius:6px;position:absolute;top:100%;min-width:180px;"><a href="#" style="display:block;color:#ecf0f1;text-decoration:none;padding:8px 12px;">محصول ۱</a><a href="#" style="display:block;color:#ecf0f1;text-decoration:none;padding:8px 12px;">محصول ۲</a></div></div><a href="#" style="color:#ecf0f1;text-decoration:none;padding:8px 15px;">تماس</a></nav>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M7,10L12,15L17,10H7Z"/></svg>' },
    { id: 'menu-breadcrumb', label: 'بردکرامب', category: '📋 منوها',
      content: `<nav style="padding:15px 20px;background:#f8f9fa;border-radius:8px;font-family:Tahoma;font-size:14px;"><a href="#" style="color:#3498db;text-decoration:none;">خانه</a> › <a href="#" style="color:#3498db;text-decoration:none;">محصولات</a> › <span style="color:#7f8c8d;">دسته</span></nav>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M10,17L15,12L10,7V17Z"/></svg>' },
    // ادیتورها
    { id: 'editor-simple', label: 'ادیتور ساده', category: '✏️ ادیتورها',
      content: `<div style="padding:20px;"><div style="border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#fff;"><div style="background:#f8f9fa;padding:8px 15px;border-bottom:1px solid #ddd;display:flex;gap:10px;"><button type="button" style="background:none;border:none;cursor:pointer;font-weight:bold;">B</button><button type="button" style="background:none;border:none;cursor:pointer;font-style:italic;">I</button><button type="button" style="background:none;border:none;cursor:pointer;text-decoration:underline;">U</button></div><div contenteditable="true" style="padding:15px;min-height:150px;outline:none;">اینجا بنویسید...</div></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    { id: 'editor-advanced', label: 'ادیتور پیشرفته', category: '✏️ ادیتورها',
      content: `<div style="padding:20px;"><div style="border:1px solid #ddd;border-radius:8px;overflow:hidden;background:#fff;"><div style="background:#f8f9fa;padding:8px 15px;border-bottom:1px solid #ddd;display:flex;gap:5px;flex-wrap:wrap;"><button type="button" style="background:none;border:none;cursor:pointer;font-weight:bold;padding:4px 8px;">B</button><button type="button" style="background:none;border:none;cursor:pointer;font-style:italic;padding:4px 8px;">I</button><button type="button" style="background:none;border:none;cursor:pointer;text-decoration:underline;padding:4px 8px;">U</button><button type="button" style="background:none;border:none;cursor:pointer;padding:4px 8px;">🔗</button><button type="button" style="background:none;border:none;cursor:pointer;padding:4px 8px;">📷</button></div><div contenteditable="true" style="padding:15px;min-height:250px;outline:none;">محتوای خود را اینجا بنویسید...</div></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    { id: 'editor-html', label: 'ادیتور HTML', category: '✏️ ادیتورها',
      content: `<div style="padding:20px;"><div style="background:#1e1e1e;border-radius:8px;overflow:hidden;"><div style="background:#2c3e50;padding:8px 15px;color:#ecf0f1;font-size:12px;">&lt;HTML&gt;</div><textarea style="width:100%;min-height:180px;background:#1e1e1e;color:#e67e22;border:none;padding:15px;font-family:monospace;font-size:13px;resize:vertical;outline:none;">&lt;div&gt;...&lt;/div&gt;</textarea></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    { id: 'editor-css', label: 'ادیتور CSS', category: '✏️ ادیتورها',
      content: `<div style="padding:20px;"><div style="background:#1e1e1e;border-radius:8px;overflow:hidden;"><div style="background:#2c3e50;padding:8px 15px;color:#ecf0f1;font-size:12px;">{CSS}</div><textarea style="width:100%;min-height:180px;background:#1e1e1e;color:#3498db;border:none;padding:15px;font-family:monospace;font-size:13px;resize:vertical;outline:none;">body { font-family: Tahoma; }</textarea></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    { id: 'editor-js', label: 'ادیتور JavaScript', category: '✏️ ادیتورها',
      content: `<div style="padding:20px;"><div style="background:#1e1e1e;border-radius:8px;overflow:hidden;"><div style="background:#2c3e50;padding:8px 15px;color:#ecf0f1;font-size:12px;">JS</div><textarea style="width:100%;min-height:180px;background:#1e1e1e;color:#f1c40f;border:none;padding:15px;font-family:monospace;font-size:13px;resize:vertical;outline:none;">console.log('Hello');</textarea></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    // ابزارک‌ها
    { id: 'widget-stats', label: 'کارت آمار', category: '🎁 ابزارک‌ها',
      content: `<div style="padding:20px;"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;"><div style="background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;padding:20px;border-radius:12px;"><div style="opacity:0.8;font-size:13px;">بازدید</div><div style="font-size:28px;font-weight:bold;">۱,۲۳۴</div></div><div style="background:linear-gradient(135deg,#11998e,#38ef7d);color:#fff;padding:20px;border-radius:12px;"><div style="opacity:0.8;font-size:13px;">اعضا</div><div style="font-size:28px;font-weight:bold;">۵۶۷</div></div></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M22,21H2V3H4V19H22V21Z"/></svg>' },
    { id: 'widget-notification', label: 'اعلان‌ها', category: '🎁 ابزارک‌ها',
      content: `<div style="padding:20px;"><div style="background:#d4edda;border-right:4px solid #27ae60;padding:15px;border-radius:6px;margin-bottom:10px;">✅ عملیات موفق</div><div style="background:#fff3cd;border-right:4px solid #f39c12;padding:15px;border-radius:6px;margin-bottom:10px;">⚠️ هشدار</div><div style="background:#f8d7da;border-right:4px solid #e74c3c;padding:15px;border-radius:6px;">❌ خطا</div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M13,17H11V15H13V17M13,13H11V7H13V13Z"/></svg>' },
    { id: 'widget-pricing', label: 'جدول قیمت', category: '🎁 ابزارک‌ها',
      content: `<div style="padding:20px;"><div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;"><div style="background:#fff;border:2px solid #e0e0e0;border-radius:12px;padding:25px;text-align:center;"><h3 style="color:#2c3e50;margin:0 0 15px;">پایه</h3><div style="font-size:32px;color:#3498db;font-weight:bold;">۵۰۰</div><ul style="list-style:none;padding:0;margin:20px 0;font-size:13px;"><li style="padding:8px 0;">۱ گیگابایت</li></ul><button style="width:100%;padding:10px;background:#3498db;color:#fff;border:none;border-radius:6px;cursor:pointer;">انتخاب</button></div><div style="background:#3498db;color:#fff;border-radius:12px;padding:25px;text-align:center;"><h3 style="margin:0 0 15px;">حرفه‌ای</h3><div style="font-size:32px;font-weight:bold;">۱۵۰۰</div><ul style="list-style:none;padding:0;margin:20px 0;font-size:13px;"><li style="padding:8px 0;">۱۰ گیگابایت</li></ul><button style="width:100%;padding:10px;background:#fff;color:#3498db;border:none;border-radius:6px;cursor:pointer;font-weight:bold;">انتخاب</button></div></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M5,4H19A2,2 0 0,1 21,6V18A2,2 0 0,1 19,20H5A2,2 0 0,1 3,18V6A2,2 0 0,1 5,4Z"/></svg>' },
    // محتوا
    { id: 'content-container', label: 'کانتینر', category: '📦 محتوا',
      content: `<div style="padding:30px;border:2px dashed #bdc3c7;margin:15px;min-height:120px;background:#f8f9fa;border-radius:8px;"><p style="margin:0;color:#95a5a6;text-align:center;font-family:Tahoma;">محتوای خود را اینجا بگذارید</p></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3M19,19H5V5H19V19Z"/></svg>' },
    { id: 'content-text', label: 'متن', category: '📦 محتوا',
      content: '<p style="padding:10px;margin:0;font-family:Tahoma;line-height:1.8;color:#333;">متن نمونه شما اینجا قرار می‌گیرد.</p>',
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M14,17H7V15H14M17,13H7V11H17M17,9H7V7H17M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    { id: 'content-heading', label: 'عنوان', category: '📦 محتوا',
      content: '<h2 style="padding:10px;margin:0;font-family:Tahoma;color:#2c3e50;">عنوان جدید</h2>',
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M5,4V7H10.5V19H13.5V7H19V4H5Z"/></svg>' },
    { id: 'content-image', label: 'تصویر', category: '📦 محتوا',
      content: `<img src="https://via.placeholder.com/800x400/3498db/ffffff?text=Image" style="width:100%;max-width:600px;height:auto;margin:20px auto;display:block;border-radius:12px;">`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M8.5,13.5L11,16.5L14.5,12L19,18H5M21,19V5C21,3.89 20.1,3 19,3H5A2,2 0 0,0 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19Z"/></svg>' },
    { id: 'content-video', label: 'ویدیو', category: '📦 محتوا',
      content: `<div style="max-width:640px;margin:20px auto;"><video controls style="width:100%;border-radius:12px;"><source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4"></video></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M17,10.5V7A1,1 0 0,0 16,6H4A1,1 0 0,0 3,7V17A1,1 0 0,0 4,18H16A1,1 0 0,0 17,17V13.5L21,17.5V6.5L17,10.5Z"/></svg>' },
    { id: 'content-button', label: 'دکمه', category: '📦 محتوا',
      content: '<button style="background:#3498db;color:white;border:none;padding:12px 24px;border-radius:6px;cursor:pointer;margin:10px;">کلیک کن</button>',
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M20,8H4V6H20M20,18H4V12H20M20,4H4C2.89,4 2,4.89 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,0 22,18V6C22,4.89 21.1,4 20,4Z"/></svg>' },
    { id: 'content-card', label: 'کارت', category: '📦 محتوا',
      content: `<div style="max-width:350px;margin:15px auto;background:#fff;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.1);overflow:hidden;"><div style="height:180px;background:linear-gradient(135deg,#667eea,#764ba2);display:flex;align-items:center;justify-content:center;font-size:48px;">📷</div><div style="padding:20px;"><h3 style="margin:0 0 10px;color:#2c3e50;font-size:18px;">عنوان کارت</h3><p style="margin:0 0 15px;color:#7f8c8d;font-size:14px;">توضیحات کوتاه.</p><a href="#" style="color:#3498db;text-decoration:none;font-weight:bold;">بیشتر →</a></div></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z"/></svg>' },
    { id: 'content-table', label: 'جدول', category: '📦 محتوا',
      content: `<div style="padding:20px;overflow-x:auto;"><table style="width:100%;border-collapse:collapse;background:#fff;"><thead><tr style="background:#2c3e50;color:#fff;"><th style="padding:12px;text-align:right;">#</th><th style="padding:12px;text-align:right;">نام</th><th style="padding:12px;text-align:right;">وضعیت</th></tr></thead><tbody><tr><td style="padding:10px;border-bottom:1px solid #eee;">۱</td><td style="padding:10px;border-bottom:1px solid #eee;">محصول الف</td><td style="padding:10px;border-bottom:1px solid #eee;"><span style="background:#d4edda;color:#155724;padding:3px 8px;border-radius:10px;font-size:11px;">فعال</span></td></tr></tbody></table></div>`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M5,4H19A2,2 0 0,1 21,6V18A2,2 0 0,1 19,20H5A2,2 0 0,1 3,18V6A2,2 0 0,1 5,4Z"/></svg>' },
    { id: 'content-divider', label: 'جداکننده', category: '📦 محتوا',
      content: `<hr style="border:none;border-top:2px solid #e0e0e0;margin:25px 10px;">`,
      media: '<svg viewBox="0 0 24 24" width="24" height="24"><path fill="currentColor" d="M19,13H5V11H19V13Z"/></svg>' }
];

// ================================================================
// ۸. راه‌اندازی ادیتور
// ================================================================
function initEditor() {
    editor = grapesjs.init({
        container: '#gjs',
        height: '100%',
        width: 'auto',
        fromElement: false,

        storageManager: {
            type: 'remote',
            steps: ['open', 'load', 'store'],
            autosave: true,
            autoload: true,
            urlStore: '../api/store.php',
            urlLoad: '../api/load.php?id=<?= $templateId ?>',
            contentTypeJson: true,
            headers: { 'Content-Type': 'application/json' },
            params: { template_id: '<?= $templateId ?>' },
        },

        pageManager: true,

        plugins: [
            'grapesjs-plugin-export',
            'grapesjs-plugin-forms',
            'grapesjs-template-manager'
        ],

        pluginsOpts: {
            'grapesjs-plugin-export': {
                addExportBtn: false,
                filenamePfx: 'template',
                root: {
                    css: { 'style.css': (ed) => ed.getCss() },
                    'index.html': (ed) => {
                        const css = ed.getCss();
                        const html = ed.getHtml();
                        return `<!DOCTYPE html>\n<html lang="${activeLanguage}" dir="${currentDirection}">\n<head>\n<meta charset="UTF-8">\n<title>قالب</title>\n<link rel="stylesheet" href="css/style.css">\n</head>\n<body>\n${html}\n</body>\n</html>`;
                    }
                }
            },
            'grapesjs-template-manager': {
                dbName: 'gjs_templates',
                objectStoreName: 'templates',
                loadFirst: false,
                mdlTitle: 'مدیریت قالب‌ها'
            }
        },

        canvasCss: `
            body { font-family: Tahoma, Arial, sans-serif !important; background-color: #f5f7fa !important; margin: 0; padding: 0; min-height: 100vh; direction: ${currentDirection}; text-align: ${currentDirection === 'rtl' ? 'right' : 'left'}; }
            * { box-sizing: border-box; }
        `,

        deviceManager: {
            devices: [
                { id: 'desktop', name: '🖥 دسکتاپ', width: '' },
                { id: 'tablet', name: '📱 تبلت', width: '768px', widthMedia: '992px' },
                { id: 'mobile', name: '📱 موبایل', width: '375px', widthMedia: '480px' }
            ]
        },

        blockManager: { blocks: allBlocks },
        selectorManager: { componentFirst: true },

        panels: {
            defaults: [
                {
                    id: 'commands',
                    buttons: [
                        { id: 'undo', className: 'fa fa-undo', command: 'core:undo', attributes: { title: 'بازگردانی' } },
                        { id: 'redo', className: 'fa fa-repeat', command: 'core:redo', attributes: { title: 'جلوبردن' } },
                        { id: 'preview', className: 'fa fa-eye', command: 'preview', attributes: { title: 'پیش‌نمایش' } },
                        { id: 'fullscreen', className: 'fa fa-arrows-alt', command: 'fullscreen', attributes: { title: 'تمام صفحه' } }
                    ]
                },
                {
                    id: 'views',
                    buttons: [
                        { id: 'open-blocks', className: 'fa fa-th-large', command: 'open-blocks', attributes: { title: 'بلاک‌ها' } },
                        { id: 'open-layers', className: 'fa fa-bars', command: 'open-layers', attributes: { title: 'لایه‌ها' } },
                        { id: 'open-sm', className: 'fa fa-paint-brush', command: 'open-sm', attributes: { title: 'استایل‌ها' } },
                        { id: 'open-tm', className: 'fa fa-cog', command: 'open-tm', attributes: { title: 'تنظیمات' } }
                    ]
                }
            ]
        }
    });

    editor.on('load', () => {
        editor.runCommand('open-blocks');
        applyProjectTemplate();
    });
}

// ================================================================
// ۹. دکمه‌های پنل GrapesJS
// ================================================================
function openGjsPanel(panelType) {
    if (!editor) return;
    const commands = { 'blocks': 'open-blocks', 'layers': 'open-layers', 'styles': 'open-sm', 'settings': 'open-tm' };
    const btnIds = { 'blocks': 'btn-blocks', 'layers': 'btn-layers', 'styles': 'btn-styles', 'settings': 'btn-settings' };

    Object.values(btnIds).forEach(id => {
        const btn = document.getElementById(id);
        if (btn) btn.classList.remove('active-panel');
    });
    editor.runCommand(commands[panelType]);
    const btn = document.getElementById(btnIds[panelType]);
    if (btn) btn.classList.add('active-panel');
}

// ================================================================
// ۱۰. توابع کمکی
// ================================================================
function switchTab(tab, btn) {
    document.querySelectorAll('.panel-tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.panel-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
}

function toggleSidePanel() {
    const panel = document.getElementById('side-panel');
    const btn = document.getElementById('open-panel-btn');
    panel.classList.toggle('collapsed');
    document.body.classList.toggle('panel-collapsed');
    btn.style.display = panel.classList.contains('collapsed') ? 'block' : 'none';
}

function notify(msg, type = 'info', duration = 2500) {
    const el = document.getElementById('notification');
    el.textContent = msg;
    el.className = 'notification ' + type;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', duration);
}

function saveProject() { if (editor) { editor.store(); notify('💾 ذخیره...', 'info', 1500); } }
function loadProject() { if (editor) { editor.load(); notify('📂 بارگذاری...', 'info', 1500); } }
function exportZIP() { if (editor) { editor.runCommand('gjs-export-zip'); notify('📦 خروجی ZIP...', 'success'); } }
function toggleBorders() { if (editor) editor.runCommand('sw-visibility'); }
function previewSite() { if (editor) editor.runCommand('preview'); }
function clearCanvas() {
    if (confirm('پاک کردن بوم؟') && editor) editor.runCommand('core:canvas-clear');
}
function goBack() {
    if (confirm('خروج؟ تغییرات ذخیره نشده از بین می‌روند.')) window.location.href = 'templates.php';
}

// ================================================================
// ۱۱. شروع
// ================================================================
document.addEventListener('DOMContentLoaded', () => {
    console.log('✅ GrapesJS Wizard + Premium Editor لود شد');
});

document.addEventListener('keydown', (e) => {
    if (e.ctrlKey && e.key === 's') { e.preventDefault(); saveProject(); }
    if (e.ctrlKey && e.key === 'z') { e.preventDefault(); editor?.runCommand('core:undo'); }
    if (e.ctrlKey && e.key === 'y') { e.preventDefault(); editor?.runCommand('core:redo'); }
});
</script>
</body>
</html>
