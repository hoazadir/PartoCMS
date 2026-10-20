<?php
require_once dirname(__DIR__, 3) . '/config.php';
require_once dirname(__DIR__, 1) . '/src/Infrastructure/PageRepository.php';

use PartoCMS\PageBuilder\Infrastructure\PageRepository;

if (!isLoggedIn()) { header('Location: /admin/login.php'); exit; }

$repo = new PageRepository(getDB());
$pages = $repo->getAll();
$siteName = getSetting('site_name', 'وب‌سایت من');
$activePage = 'page_builder';

require_once dirname(__DIR__, 3) . '/admin/includes/header.php';
require_once dirname(__DIR__, 3) . '/admin/includes/sidebar.php';
?>
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>🧩 صفحه‌ساز (Page Builder)</h2>
        <a href="edit.php" class="btn btn-primary">➕ صفحه جدید</a>
    </div>
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
        <div class="alert alert-success">صفحه با موفقیت ذخیره شد.</div>
    <?php endif; ?>
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4 text-end">عنوان</th>
                        <th>آدرس (Slug)</th>
                        <th>وضعیت</th>
                        <th>تاریخ</th>
                        <th class="text-start pe-4">عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pages)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">هیچ صفحه‌ای ساخته نشده است.</td></tr>
                    <?php else: ?>
                        <?php foreach ($pages as $page): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-end"><?= htmlspecialchars($page['title']) ?></td>
                                <td><a href="/page.php?slug=<?= htmlspecialchars($page['slug']) ?>" target="_blank" class="text-decoration-none">/<?= htmlspecialchars($page['slug']) ?></a></td>
                                <td>
                                    <?php if ($page['status'] === 'published'): ?>
                                        <span class="badge bg-success">منتشر شده</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">پیش‌نویس</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('Y/m/d', strtotime($page['created_at'])) ?></td>
                                <td class="text-start pe-4">
                                    <a href="edit.php?id=<?= $page['id'] ?>" class="btn btn-sm btn-outline-primary">ویرایش</a>
                                    <a href="delete.php?id=<?= $page['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('مطمئن هستید؟')">حذف</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
