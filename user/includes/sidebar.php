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
            <?php $isActive = ($currentPage === basename($item['url']) && strpos($item['url'], '/') !== 0); ?>
            <a class="nav-link <?= $isActive ? 'active' : '' ?> <?= strpos($item['url'], 'logout') !== false ? 'text-danger' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['title']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</div>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start text-bg-dark" style="width: 250px;" tabindex="-1" id="userSidebarMobile">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">👤 پنل کاربری</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="nav flex-column">
            <?php foreach ($menuItems as $item): ?>
                <?php $isActive = ($currentPage === basename($item['url']) && strpos($item['url'], '/') !== 0); ?>
                <a class="nav-link <?= $isActive ? 'active' : '' ?> <?= strpos($item['url'], 'logout') !== false ? 'text-danger' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                    <i class="bi <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['title']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
