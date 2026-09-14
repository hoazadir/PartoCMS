<?php
// admin/includes/header.php
if (!isset($pageTitle)) $pageTitle = 'پنل مدیریت';
if (!isset($activePage)) $activePage = '';
$siteName = getSetting('site_name', 'وب‌سایت من');
$username = $_SESSION['username'] ?? 'کاربر';
$userRole = $_SESSION['role'] ?? 'viewer';

$roleLabels = [
    'admin' => 'مدیر',
    'editor' => 'ویرایشگر',
    'viewer' => 'بیننده',
];
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, Arial, sans-serif; background: #f4f6f9; margin: 0; }
        
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
            color: #fff;
            padding: 0;
            position: fixed;
            right: 0;
            top: 0;
            width: 240px;
            z-index: 100;
            overflow-y: auto;
        }
        .sidebar .brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #34495e;
        }
        .sidebar .brand h4 { margin: 0; font-size: 16px; }
        .sidebar .brand small { opacity: 0.7; font-size: 11px; }
        .sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #34495e;
            transition: background 0.2s, padding-right 0.2s;
            font-size: 14px;
        }
        .sidebar a:hover { background: #34495e; padding-right: 25px; }
        .sidebar a.active { background: #3498db; border-right: 4px solid #fff; }
        .sidebar a.text-danger { color: #e74c3c; }
        .sidebar a i { font-size: 16px; }
        
        .main-content {
            margin-right: 240px;
            padding: 25px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .top-bar h5 { margin: 0; color: #2c3e50; font-size: 18px; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 25px;
        }
        .user-info .avatar {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        .user-info .info { font-size: 13px; }
        .user-info .role {
            font-size: 10px;
            background: #3498db;
            color: #fff;
            padding: 2px 8px;
            border-radius: 10px;
            margin-right: 5px;
        }
        
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .card-header {
            background: #fff;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0 !important;
            color: #2c3e50;
        }
        
        .btn { font-family: Tahoma, sans-serif; }
        .table { font-size: 13px; }
        .table th { background: #f8f9fa; font-weight: 600; }
        
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h4, .sidebar .brand small { display: none; }
            .sidebar a span { display: none; }
            .sidebar a { justify-content: center; padding: 14px 5px; }
            .main-content { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand">
        <h4>🚀 پنل مدیریت</h4>
        <small><?= htmlspecialchars($siteName) ?></small>
    </div>
    <a href="index.php" class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> <span>داشبورد</span>
    </a>
    <a href="templates.php" class="<?= $activePage === 'templates' ? 'active' : '' ?>">
        <i class="bi bi-layers"></i> <span>مدیریت قالب‌ها</span>
    </a>
    <a href="editor.php" class="<?= $activePage === 'editor' ? 'active' : '' ?>">
        <i class="bi bi-pencil-square"></i> <span>ویرایشگر</span>
    </a>
    <?php if (isAdmin()): ?>
    <a href="users.php" class="<?= $activePage === 'users' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> <span>مدیریت کاربران</span>
    </a>
    <a href="settings.php" class="<?= $activePage === 'settings' ? 'active' : '' ?>">
        <i class="bi bi-gear"></i> <span>تنظیمات</span>
    </a>
    <?php endif; ?>
    <a href="../index.php" target="_blank">
        <i class="bi bi-globe"></i> <span>مشاهده سایت</span>
    </a>
    <a href="logout.php" class="text-danger">
        <i class="bi bi-box-arrow-right"></i> <span>خروج</span>
    </a>
</div>

<div class="main-content">
    <div class="top-bar">
        <h5><?= htmlspecialchars($pageTitle) ?></h5>
        <div class="user-info">
            <div class="avatar"><?= mb_substr($username, 0, 1) ?></div>
            <div class="info">
                <strong><?= htmlspecialchars($username) ?></strong>
                <span class="role"><?= $roleLabels[$userRole] ?? $userRole ?></span>
            </div>
        </div>
    </div>
