<?php
$db = getDB();
$menuItems = $db->query("SELECT * FROM user_menu_items WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Desktop Sidebar -->
<div class="d-none d-lg-block sidebar-desktop">
    <div class="brand">
        <h5>👤 پنل کاربری</h5>
    </div>
    <nav class="nav flex-column">
        <?php foreach ($menuItems as $item): ?>
            <a class="nav-link <?= $currentPage === $item['url'] ? 'active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['title']) ?>
            </a>
        <?php endforeach; ?>
        <hr class="text-white-50 mx-2">
        <a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> خروج</a>
    </nav>
</div>

<!-- Mobile Offcanvas Sidebar (استفاده از offcanvas-start برای راست‌چین) -->
<div class="offcanvas offcanvas-start text-bg-dark" style="width: 250px;" tabindex="-1" id="userSidebarMobile">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">👤 پنل کاربری</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="nav flex-column">
            <?php foreach ($menuItems as $item): ?>
                <a class="nav-link <?= $currentPage === $item['url'] ? 'active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['title']) ?>
                </a>
            <?php endforeach; ?>
            <hr class="text-white-50 mx-2">
            <a class="nav-link text-danger" href="../logout.php"><i class="bi bi-box-arrow-right"></i> خروج</a>
        </nav>
    </div>
</div>
