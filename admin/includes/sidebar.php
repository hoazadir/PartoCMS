<?php
/**
 * Sidebar استاندارد - نسخه ریسپانسیو با مسیرهای مطلق (اصلاح نهایی اسکرول)
 */
if (!isset($siteName)) {
    $siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
}

$currentFile = basename($_SERVER['PHP_SELF']);
$currentPath = $_SERVER['PHP_SELF'];

$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = $userRole === 'admin';

// ==================== بررسی دسترسی کاربر به ماژول‌ها ====================
$mm = function_exists('getModuleManager') ? getModuleManager() : null;

if ($mm) {
    $canContent    = $mm->canCurrentUserAccess('content');
    $canCategories = $mm->canCurrentUserAccess('categories');
    $canMedia      = $mm->canCurrentUserAccess('media');
    $canComments   = $mm->canCurrentUserAccess('comments');
    $canForms      = $mm->canCurrentUserAccess('forms');
    $canMenus      = $mm->canCurrentUserAccess('menus');
    $canTemplates  = $mm->canCurrentUserAccess('templates');
    $canEditor     = $mm->canCurrentUserAccess('editor');
    $canReports    = $mm->canCurrentUserAccess('reports');
    $canSeo        = $mm->canCurrentUserAccess('seo');
    $canUsers      = $mm->canCurrentUserAccess('users');
    $canRoles      = $mm->canCurrentUserAccess('roles');
    $canModules    = $mm->canCurrentUserAccess('modules');
    $canSettings   = $mm->canCurrentUserAccess('settings');
    $canBackups    = $mm->canCurrentUserAccess('backups');
} else {
    $canContent = $canCategories = $canMedia = $canComments = true;
    $canForms = $canMenus = $canTemplates = $canEditor = true;
    $canReports = $canSeo = $canUsers = $canRoles = true;
    $canModules = $canSettings = $canBackups = true;
}

// ==================== دسترسی‌های امنیتی: فقط برای ادمین ====================
$canSecurity      = $isAdmin;
$canTelegram      = $isAdmin;
$canVirusTotal    = $isAdmin;
$canSecurityAudit = $isAdmin;
$can2FA           = $isAdmin;
$canBackupMgr     = $isAdmin;

// ==================== بررسی نمایش گروه‌ها ====================
$hasContentGroup    = $canContent || $canCategories || $canMedia || $canComments;
$hasToolsGroup      = $canForms || $canMenus;
$hasAppearanceGroup = $canTemplates || $canEditor;
$hasUsersGroup      = $canUsers || $canRoles;
$hasSettingsGroup   = $canModules || $canSettings || $canBackups;

// ==================== بررسی گروه‌های فعال ====================
$isContentGroup    = in_array($currentFile, ['categories.php', 'media.php', 'comments.php', 'admin.php']) || strpos($currentPath, 'modules/content') !== false;
$isModulesGroup    = in_array($currentFile, ['forms.php', 'form-edit.php', 'form-submissions.php', 'menus.php', 'menu-edit.php']);
$isAppearanceGroup = in_array($currentFile, ['templates.php', 'editor.php']);
$isUsersGroup      = in_array($currentFile, ['users.php', 'roles.php']);
$isSettingsGroup   = in_array($currentFile, ['modules.php', 'settings.php', 'seo.php', 'backups.php']);

// ==================== وضعیت 2FA ====================
$twoFAEnabled = false;
try {
    if (function_exists('getDB') && isset($_SESSION['user_id'])) {
        $db = getDB();
        $stmt = $db->prepare("SELECT enabled FROM user_2fa WHERE user_id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch();
        $twoFAEnabled = $row && (int)$row['enabled'] === 1;
    }
} catch (Throwable $e) { $twoFAEnabled = false; }

// ==================== آدرس‌های مطلق ====================
$baseAdmin = ADMIN_URL;
$baseSite  = SITE_URL;
?>
<style>
    /* ==================== Sidebar Base ==================== */
    .sidebar {
        background: #1e293b;
        color: #fff;
        padding: 0;
        position: fixed;
        right: 0;
        top: 0;
        width: 260px;
        z-index: 1050;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        box-shadow: -2px 0 10px rgba(0,0,0,0.15);
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease;
        box-sizing: border-box;
        overscroll-behavior: contain;
        touch-action: pan-y;
    }
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-thumb { background: #475569; border-radius: 5px; }
    .sidebar::-webkit-scrollbar-track { background: #1e293b; }

    .sidebar .brand {
        padding: 22px 20px;
        text-align: center;
        border-bottom: 1px solid #334155;
        background: linear-gradient(135deg, #1e293b, #334155);
        flex-shrink: 0;
    }
    .sidebar .brand h5 { margin: 0 0 5px; font-size: 16px; font-weight: bold; color: #fff; }
    .sidebar .brand small { opacity: 0.6; font-size: 11px; display: block; color: #cbd5e1; }
    .sidebar .brand .logo-icon { font-size: 28px; display: block; margin-bottom: 8px; }

    .user-info-box {
        padding: 12px 20px;
        background: #0f172a;
        border-bottom: 1px solid #334155;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .user-info-box .avatar {
        width: 36px; height: 36px;
        border-radius: 50%;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-weight: bold; font-size: 15px;
        flex-shrink: 0;
    }
    .user-info-box .info { flex: 1; min-width: 0; }
    .user-info-box .info .name {
        font-size: 13px; font-weight: bold; color: #fff;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .user-info-box .info .role { font-size: 10px; color: #94a3b8; }

    .menu-group { border-bottom: 1px solid #334155; }
    .menu-group-header {
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
        font-weight: bold;
        color: #cbd5e1;
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }
    .menu-group-header:hover { background: #334155; color: #fff; }
    .menu-group-header .group-icon { font-size: 18px; width: 22px; text-align: center; }
    .menu-group-header .group-title { flex: 1; }
    .menu-group-header .arrow {
        font-size: 11px;
        transition: transform 0.3s;
        color: #64748b;
    }
    .menu-group.open .menu-group-header { background: #334155; color: #fff; }
    .menu-group.open .menu-group-header .arrow { transform: rotate(180deg); }
    .menu-group.has-active .menu-group-header { color: #3b82f6; }

    .menu-group-items {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.35s ease;
        background: #0f172a;
    }
    .menu-group.open .menu-group-items { max-height: 800px; }

    .menu-group-items a {
        padding: 11px 20px 11px 45px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #94a3b8;
        text-decoration: none;
        font-size: 13px;
        border-right: 3px solid transparent;
        transition: all 0.2s;
    }
    .menu-group-items a:hover {
        background: #1e293b;
        color: #fff;
        border-right-color: #3b82f6;
    }
    .menu-group-items a.active {
        background: linear-gradient(90deg, transparent, rgba(59,130,246,0.15));
        color: #fff;
        border-right-color: #3b82f6;
        font-weight: bold;
    }
    .menu-group-items a .item-icon { font-size: 14px; width: 18px; text-align: center; }

    .menu-single {
        padding: 14px 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        color: #cbd5e1;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.2s;
        border-bottom: 1px solid #334155;
        border-right: 3px solid transparent;
        position: relative;
    }
    .menu-single:hover { background: #334155; color: #fff; }
    .menu-single.active {
        background: linear-gradient(90deg, transparent, rgba(59,130,246,0.2));
        color: #fff;
        border-right-color: #3b82f6;
        font-weight: bold;
    }
    .menu-single .single-icon { font-size: 18px; width: 22px; text-align: center; }
    .menu-single.danger { color: #f87171; }
    .menu-single.danger:hover { background: #7f1d1d; color: #fff; }

    /* ✅ داشبورد امنیتی */
    .menu-single.security-link { color: #fbbf24; }
    .menu-single.security-link:hover { background: #78350f; color: #fef3c7; }
    .menu-single.security-link.active {
        background: linear-gradient(90deg, transparent, rgba(251,191,36,0.2));
        color: #fef3c7;
        border-right-color: #fbbf24;
        font-weight: bold;
    }

    /* ✅ راه‌اندازی تلگرام */
    .menu-single.telegram-link { color: #38bdf8; }
    .menu-single.telegram-link:hover { background: #0c4a6e; color: #e0f2fe; }
    .menu-single.telegram-link.active {
        background: linear-gradient(90deg, transparent, rgba(56,189,248,0.2));
        color: #e0f2fe;
        border-right-color: #38bdf8;
        font-weight: bold;
    }

    /* ✅ ویروس‌یاب VirusTotal */
    .menu-single.virustotal-link { color: #a78bfa; }
    .menu-single.virustotal-link:hover { background: #4c1d95; color: #ede9fe; }
    .menu-single.virustotal-link.active {
        background: linear-gradient(90deg, transparent, rgba(167,139,250,0.2));
        color: #ede9fe;
        border-right-color: #a78bfa;
        font-weight: bold;
    }

    /* ✅ تست جامع امنیتی */
    .menu-single.audit-link { color: #10b981; }
    .menu-single.audit-link:hover { background: #064e3b; color: #d1fae5; }
    .menu-single.audit-link.active {
        background: linear-gradient(90deg, transparent, rgba(16,185,129,0.2));
        color: #d1fae5;
        border-right-color: #10b981;
        font-weight: bold;
    }

    /* ✅ نمودارهای امنیتی */
    .menu-single.graphs-link { color: #6366f1; }
    .menu-single.graphs-link:hover { background: #312e81; color: #e0e7ff; }
    .menu-single.graphs-link.active {
        background: linear-gradient(90deg, transparent, rgba(99,102,241,0.2));
        color: #e0e7ff;
        border-right-color: #6366f1;
        font-weight: bold;
    }

    /* ✅ بکاپ خودکار */
    .menu-single.backup-mgr-link { color: #14b8a6; }
    .menu-single.backup-mgr-link:hover { background: #134e4a; color: #ccfbf1; }
    .menu-single.backup-mgr-link.active {
        background: linear-gradient(90deg, transparent, rgba(20,184,166,0.2));
        color: #ccfbf1;
        border-right-color: #14b8a6;
        font-weight: bold;
    }

    /* ✅ احراز هویت دو مرحله‌ای (2FA) */
    .menu-single.twofa-link { color: #ec4899; }
    .menu-single.twofa-link:hover { background: #831843; color: #fce7f3; }
    .menu-single.twofa-link.active {
        background: linear-gradient(90deg, transparent, rgba(236,72,153,0.2));
        color: #fce7f3;
        border-right-color: #ec4899;
        font-weight: bold;
    }
    /* نشانگر وضعیت 2FA */
    .menu-single .status-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: auto;
        flex-shrink: 0;
    }
    .status-dot.enabled {
        background: #10b981;
        box-shadow: 0 0 8px rgba(16,185,129,0.8);
        animation: pulse-green 2s infinite;
    }
    .status-dot.disabled {
        background: #ef4444;
    }
    @keyframes pulse-green {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .menu-bottom {
        margin-top: auto;
        border-top: 2px solid #334155;
        padding-top: 5px;
        flex-shrink: 0;
    }

    /* ==================== Mobile Toggle Button ==================== */
    .mobile-toggle {
        display: none;
        position: fixed;
        top: 12px;
        right: 12px;
        z-index: 1100;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        background: linear-gradient(135deg, #1e293b, #334155);
        color: #fff;
        border: 1px solid #475569;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        cursor: pointer;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        transition: transform 0.2s;
    }
    .mobile-toggle:active { transform: scale(0.95); }

    /* ==================== Backdrop Overlay ==================== */
    .sidebar-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 1040;
        backdrop-filter: blur(2px);
        -webkit-backdrop-filter: blur(2px);
    }
    .sidebar-backdrop.active { display: block; }

    /* ==================== Responsive ==================== */
    @media (max-width: 900px) {
        .sidebar {
            width: 280px;
            transform: translateX(100%);
            box-shadow: -5px 0 25px rgba(0,0,0,0.4);
            top: 0;
            bottom: 0;
            height: auto;
            max-height: 100%;
            padding-bottom: 0;
        }
        .sidebar.mobile-open {
            transform: translateX(0);
        }

        .mobile-toggle {
            display: flex;
        }

        .main {
            margin-right: 0 !important;
            padding: 70px 15px 15px !important;
        }

        .menu-group-header { padding: 16px 20px; }
        .menu-single { padding: 16px 20px; }
        .menu-group-items a { padding: 13px 20px 13px 50px; }
    }

    /* ✅ اصلاحات مخصوص لنداسکیپ موبایل */
    @media (max-width: 900px) and (orientation: landscape) {
        .sidebar {
            width: 300px;
            top: 0;
            bottom: 0;
        }
        .sidebar .brand { padding: 12px 20px; }
        .sidebar .brand h5 { font-size: 14px; }
        .sidebar .brand .logo-icon { font-size: 20px; margin-bottom: 4px; }
        .user-info-box { padding: 8px 20px; }
        .menu-group-header { padding: 10px 20px; }
        .menu-single { padding: 10px 20px; }
        .menu-group-items a { padding: 8px 20px 8px 50px; }
        .main { padding-top: 60px !important; }
    }

    @media (max-width: 900px) {
        body .main,
        body .col-md-10,
        body > div[style*="margin-right"] {
            margin-right: 0 !important;
        }
    }
</style>

<!-- Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

<!-- Mobile Toggle Button -->
<button class="mobile-toggle" id="mobileToggle" onclick="toggleMobileSidebar()" aria-label="منو">
    <i class="bi bi-list"></i>
</button>

<div class="sidebar" id="adminSidebar">

    <!-- Brand -->
    <div class="brand">
        <span class="logo-icon">🚀</span>
        <h5>پنل مدیریت</h5>
        <small><?= htmlspecialchars($siteName) ?></small>
    </div>

    <!-- User Info -->
    <?php if (isset($_SESSION['username'])): ?>
    <div class="user-info-box">
        <div class="avatar"><?= htmlspecialchars(mb_substr($_SESSION['username'], 0, 1)) ?></div>
        <div class="info">
            <div class="name"><?= htmlspecialchars($_SESSION['username']) ?></div>
            <div class="role"><?= htmlspecialchars($_SESSION['role_name'] ?? 'کاربر') ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== داشبورد ==================== -->
    <a href="<?= $baseAdmin ?>/index.php" class="menu-single <?= $currentFile === 'index.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">📊</span>
        <span>داشبورد</span>
    </a>

    <!-- ==================== گزارش‌ها ==================== -->
    <?php if ($canReports): ?>
    <a href="<?= $baseAdmin ?>/reports.php" class="menu-single <?= $currentFile === 'reports.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">📈</span>
        <span>گزارش‌ها</span>
    </a>
    <?php endif; ?>

    <!-- ==================== SEO ==================== -->
    <?php if ($canSeo): ?>
    <a href="<?= $baseAdmin ?>/seo.php" class="menu-single <?= $currentFile === 'seo.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">🔍</span>
        <span>تنظیمات SEO</span>
    </a>
    <?php endif; ?>

    <!-- ==================== پشتیبان‌گیری ==================== -->
    <?php if ($canBackups): ?>
    <a href="<?= $baseAdmin ?>/backups.php" class="menu-single <?= $currentFile === 'backups.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">💾</span>
        <span>پشتیبان‌گیری</span>
    </a>
    <?php endif; ?>

    <!-- ==================== بکاپ خودکار ==================== -->
    <?php if ($canBackupMgr): ?>
    <a href="<?= $baseAdmin ?>/backup_manager.php" class="menu-single backup-mgr-link <?= $currentFile === 'backup_manager.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">📦</span>
        <span>بکاپ خودکار</span>
    </a>
    <?php endif; ?>

    <!-- ==================== داشبورد امنیتی ==================== -->
    <?php if ($canSecurity): ?>
    <a href="<?= $baseAdmin ?>/security_dashboard.php" class="menu-single security-link <?= $currentFile === 'security_dashboard.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">🛡️</span>
        <span>داشبورد امنیتی</span>
    </a>
    <?php endif; ?>

    <!-- ==================== راه‌اندازی تلگرام ==================== -->
    <?php if ($canTelegram): ?>
    <a href="<?= $baseAdmin ?>/telegram_setup.php" class="menu-single telegram-link <?= $currentFile === 'telegram_setup.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">🤖</span>
        <span>راه‌اندازی تلگرام</span>
    </a>
    <?php endif; ?>

    <!-- ==================== ویروس‌یاب VirusTotal ==================== -->
    <?php if ($canVirusTotal): ?>
    <a href="<?= $baseAdmin ?>/virustotal_setup.php" class="menu-single virustotal-link <?= $currentFile === 'virustotal_setup.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">🦠</span>
        <span>ویروس‌یاب VirusTotal</span>
    </a>
    <?php endif; ?>

    <!-- ==================== تست جامع امنیتی ==================== -->
    <?php if ($canSecurityAudit): ?>
    <a href="<?= $baseAdmin ?>/security_audit.php" class="menu-single audit-link <?= $currentFile === 'security_audit.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">🔍</span>
        <span>تست جامع امنیتی</span>
    </a>
    <?php endif; ?>

    <!-- ==================== نمودارهای امنیتی ==================== -->
    <?php if ($isAdmin): ?>
    <a href="<?= $baseAdmin ?>/security_graphs.php" class="menu-single graphs-link <?= $currentFile === 'security_graphs.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()">
        <span class="single-icon">📊</span>
        <span>نمودارهای امنیتی</span>
    </a>
    <?php endif; ?>

    <!-- ==================== احراز هویت دو مرحله‌ای (2FA) ==================== -->
    <?php if ($can2FA): ?>
    <a href="<?= $baseAdmin ?>/2fa_setup.php" class="menu-single twofa-link <?= $currentFile === '2fa_setup.php' ? 'active' : '' ?>" onclick="closeMobileSidebar()" title="احراز هویت دو مرحله‌ای — <?= $twoFAEnabled ? 'فعال' : 'غیرفعال' ?>">
        <span class="single-icon">🔐</span>
        <span>احراز هویت دو مرحله‌ای</span>
        <span class="status-dot <?= $twoFAEnabled ? 'enabled' : 'disabled' ?>" title="<?= $twoFAEnabled ? 'فعال' : 'غیرفعال' ?>"></span>
    </a>
    <?php endif; ?>

    <!-- ==================== مدیریت محتوا ==================== -->
    <?php if ($hasContentGroup): ?>
    <div class="menu-group <?= $isContentGroup ? 'open has-active' : '' ?>" data-group="content">
        <div class="menu-group-header" onclick="toggleMenuGroup(this)">
            <span class="group-icon">📝</span>
            <span class="group-title">مدیریت محتوا</span>
            <span class="arrow">▼</span>
        </div>
        <div class="menu-group-items">
            <?php if ($canContent): ?>
            <a href="<?= $baseSite ?>/modules/content/admin.php" class="<?= strpos($currentPath, 'modules/content') !== false ? 'active' : '' ?>">
                <span class="item-icon">📄</span>
                <span>مقالات و صفحات</span>
            </a>
            <?php endif; ?>

            <?php if ($canCategories): ?>
            <a href="<?= $baseAdmin ?>/categories.php" class="<?= $currentFile === 'categories.php' ? 'active' : '' ?>">
                <span class="item-icon">🏷</span>
                <span>دسته‌بندی‌ها</span>
            </a>
            <?php endif; ?>

            <?php if ($canMedia): ?>
            <a href="<?= $baseAdmin ?>/media.php" class="<?= $currentFile === 'media.php' ? 'active' : '' ?>">
                <span class="item-icon">🖼</span>
                <span>رسانه‌ها</span>
            </a>
            <?php endif; ?>

            <?php if ($canComments): ?>
            <a href="<?= $baseAdmin ?>/comments.php" class="<?= $currentFile === 'comments.php' ? 'active' : '' ?>">
                <span class="item-icon">💬</span>
                <span>دیدگاه‌ها</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== ابزارها ==================== -->
    <?php if ($hasToolsGroup): ?>
    <div class="menu-group <?= $isModulesGroup ? 'open has-active' : '' ?>" data-group="modules">
        <div class="menu-group-header" onclick="toggleMenuGroup(this)">
            <span class="group-icon">🧩</span>
            <span class="group-title">ابزارها</span>
            <span class="arrow">▼</span>
        </div>
        <div class="menu-group-items">
            <?php if ($canForms): ?>
            <a href="<?= $baseAdmin ?>/forms.php" class="<?= in_array($currentFile, ['forms.php', 'form-edit.php', 'form-submissions.php']) ? 'active' : '' ?>">
                <span class="item-icon">📋</span>
                <span>فرم‌ساز</span>
            </a>
            <?php endif; ?>

            <?php if ($canMenus): ?>
            <a href="<?= $baseAdmin ?>/menus.php" class="<?= in_array($currentFile, ['menus.php', 'menu-edit.php']) ? 'active' : '' ?>">
                <span class="item-icon">🔗</span>
                <span>منوساز</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== ظاهر سایت ==================== -->
    <?php if ($hasAppearanceGroup): ?>
    <div class="menu-group <?= $isAppearanceGroup ? 'open has-active' : '' ?>" data-group="appearance">
        <div class="menu-group-header" onclick="toggleMenuGroup(this)">
            <span class="group-icon">🎨</span>
            <span class="group-title">ظاهر سایت</span>
            <span class="arrow">▼</span>
        </div>
        <div class="menu-group-items">
            <?php if ($canTemplates): ?>
            <a href="<?= $baseAdmin ?>/templates.php" class="<?= $currentFile === 'templates.php' ? 'active' : '' ?>">
                <span class="item-icon">📦</span>
                <span>مدیریت قالب‌ها</span>
            </a>
            <?php endif; ?>

            <?php if ($canEditor): ?>
            <a href="<?= $baseAdmin ?>/editor.php" class="<?= $currentFile === 'editor.php' ? 'active' : '' ?>">
                <span class="item-icon">✏️</span>
                <span>طراح قالب</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== کاربران ==================== -->
    <?php if ($hasUsersGroup): ?>
    <div class="menu-group <?= $isUsersGroup ? 'open has-active' : '' ?>" data-group="users">
        <div class="menu-group-header" onclick="toggleMenuGroup(this)">
            <span class="group-icon">👥</span>
            <span class="group-title">کاربران</span>
            <span class="arrow">▼</span>
        </div>
        <div class="menu-group-items">
            <?php if ($canUsers): ?>
            <a href="<?= $baseAdmin ?>/users.php" class="<?= $currentFile === 'users.php' ? 'active' : '' ?>">
                <span class="item-icon">👤</span>
                <span>لیست کاربران</span>
            </a>
            <?php endif; ?>

            <?php if ($canRoles): ?>
            <a href="<?= $baseAdmin ?>/roles.php" class="<?= $currentFile === 'roles.php' ? 'active' : '' ?>">
                <span class="item-icon">🛡</span>
                <span>نقش‌ها و دسترسی</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== تنظیمات ==================== -->
    <?php if ($hasSettingsGroup): ?>
    <div class="menu-group <?= $isSettingsGroup ? 'open has-active' : '' ?>" data-group="settings">
        <div class="menu-group-header" onclick="toggleMenuGroup(this)">
            <span class="group-icon">⚙️</span>
            <span class="group-title">تنظیمات</span>
            <span class="arrow">▼</span>
        </div>
        <div class="menu-group-items">
            <?php if ($canModules): ?>
            <a href="<?= $baseAdmin ?>/modules.php" class="<?= $currentFile === 'modules.php' ? 'active' : '' ?>">
                <span class="item-icon">🧩</span>
                <span>ماژول‌ها</span>
            </a>
            <?php endif; ?>

            <?php if ($canSettings): ?>
            <a href="<?= $baseAdmin ?>/settings.php" class="<?= $currentFile === 'settings.php' ? 'active' : '' ?>">
                <span class="item-icon">🛠</span>
                <span>تنظیمات سایت</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==================== Bottom ==================== -->
    <div class="menu-bottom">
        <a href="<?= $baseSite ?>/index.php" target="_blank" class="menu-single">
            <span class="single-icon">🌐</span>
            <span>مشاهده سایت</span>
        </a>
        <a href="<?= $baseAdmin ?>/logout.php" class="menu-single danger">
            <span class="single-icon">🚪</span>
            <span>خروج</span>
        </a>
    </div>

</div>

<script>
// ==================== Mobile Sidebar Toggle ====================
function toggleMobileSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const isOpen = sidebar.classList.contains('mobile-open');

    if (isOpen) {
        closeMobileSidebar();
    } else {
        sidebar.classList.add('mobile-open');
        backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
        document.documentElement.style.overflow = 'hidden';
    }
}

function closeMobileSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.remove('mobile-open');
    backdrop.classList.remove('active');
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
}

// ==================== Menu Group Toggle ====================
function toggleMenuGroup(header) {
    const group = header.parentElement;
    const groupName = group.dataset.group;

    group.classList.toggle('open');

    if (groupName) {
        let openGroups = JSON.parse(localStorage.getItem('sidebar_open_groups') || '[]');
        if (group.classList.contains('open')) {
            if (!openGroups.includes(groupName)) openGroups.push(groupName);
        } else {
            openGroups = openGroups.filter(n => n !== groupName);
        }
        localStorage.setItem('sidebar_open_groups', JSON.stringify(openGroups));
    }
}

// ==================== Restore State on Load ====================
document.addEventListener('DOMContentLoaded', () => {
    const openGroups = JSON.parse(localStorage.getItem('sidebar_open_groups') || '[]');
    document.querySelectorAll('.menu-group').forEach(group => {
        const name = group.dataset.group;
        if (name && openGroups.includes(name)) {
            group.classList.add('open');
        }
        if (group.classList.contains('has-active')) {
            group.classList.add('open');
        }
    });

    document.querySelectorAll('.sidebar a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 900) {
                setTimeout(closeMobileSidebar, 100);
            }
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMobileSidebar();
    });
});

// ==================== Handle Window Resize ====================
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (window.innerWidth > 900) {
            closeMobileSidebar();
        }
    }, 100);
});
</script>
