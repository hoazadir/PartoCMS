<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'پنل کاربری' ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        
        /* سایدبار دسکتاپ */
        .sidebar-desktop {
            width: 250px; background: #2c3e50; color: #fff; position: fixed; right: 0; top: 0; height: 100vh; overflow-y: auto; z-index: 1000;
        }
        .sidebar-desktop .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar-desktop .nav-link { color: #ecf0f1; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; }
        .sidebar-desktop .nav-link:hover, .sidebar-desktop .nav-link.active { background: #3498db; color: #fff; }
        
        /* محتوای اصلی */
        .main-content { margin-right: 250px; padding: 25px; min-height: 100vh; transition: 0.3s; }
        
        /* تنظیمات موبایل */
        @media (max-width: 991px) {
            .main-content { margin-right: 0; padding: 15px; padding-top: 70px; } /* فاصله برای هدر موبایل */
        }
        
        /* سایدبار موبایل (Offcanvas) */
        .offcanvas { background: #2c3e50; color: #fff; }
        .offcanvas .nav-link { color: #ecf0f1; padding: 14px 20px; border-bottom: 1px solid #34495e; display: flex; align-items: center; gap: 10px; }
        .offcanvas .nav-link:hover, .offcanvas .nav-link.active { background: #3498db; color: #fff; }
        
        /* هدر بالای صفحه در موبایل (همبرگری) */
        .mobile-header { background: #fff; padding: 10px 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: none; align-items: center; justify-content: space-between; position: fixed; top: 0; right: 0; left: 0; z-index: 999; }
        @media (max-width: 991px) { .mobile-header { display: flex; } }
    
        
    
</style>
</head>
<body>

<!-- هدر موبایل (دکمه همبرگری) -->
<div class="mobile-header d-lg-none">
    <button class="btn btn-outline-dark" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebarMobile">
        <i class="bi bi-list fs-4"></i>
    </button>
    <span class="fw-bold text-dark">پنل کاربری</span>
    <div style="width: 40px;"></div> <!-- Spacer -->
</div>

<!-- فراخوانی سایدبار -->
<?php include_once __DIR__ . '/sidebar.php'; ?>

<!-- شروع محتوای اصلی -->
<div class="main-content">
