<!-- Summary Alert -->
<?php if ($allPassed): ?>
    <div class="alert-success-custom mb-4">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-check-circle-fill fs-4"></i>
            <div><strong>همه پیش‌نیازها آماده است!</strong> می‌توانید به مرحله بعد بروید.</div>
        </div>
    </div>
<?php else: ?>
    <div class="alert-danger-custom mb-4">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-x-circle-fill fs-4"></i>
            <div><strong>برخی پیش‌نیازها برقرار نیستند.</strong> لطفاً موارد زیر را برطرف کنید.</div>
        </div>
    </div>
<?php endif; ?>

<?php $total = ($summary['ok'] ?? 0) + ($summary['warn'] ?? 0) + ($summary['fail'] ?? 0); ?>
<div class="row g-3 mb-4 text-center">
    <div class="col-3"><div class="card border-0 shadow-sm py-3"><h4 class="mb-0 fw-bold text-dark"><?= $total ?></h4><small class="text-muted">مجموع</small></div></div>
    <div class="col-3"><div class="card border-0 shadow-sm py-3"><h4 class="mb-0 fw-bold text-success"><?= $summary['ok'] ?? 0 ?></h4><small class="text-muted">موفق</small></div></div>
    <div class="col-3"><div class="card border-0 shadow-sm py-3"><h4 class="mb-0 fw-bold text-warning"><?= $summary['warn'] ?? 0 ?></h4><small class="text-muted">هشدار</small></div></div>
    <div class="col-3"><div class="card border-0 shadow-sm py-3"><h4 class="mb-0 fw-bold text-danger"><?= $summary['fail'] ?? 0 ?></h4><small class="text-muted">خطا</small></div></div>
</div>

<?php foreach ($categories as $catKey => $catInfo): ?>
    <?php if (empty($results[$catKey])) continue; ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold py-3 d-flex align-items-center gap-2">
            <span><?= $catInfo['icon'] ?></span><span><?= htmlspecialchars($catInfo['title']) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <tbody>
                        <?php foreach ($results[$catKey] as $item): ?>
                            <tr>
                                <td style="width: 50px;" class="text-center">
                                    <?php if ($item['status'] === 'ok'): ?>
                                        <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                    <?php elseif ($item['status'] === 'warn'): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                                    <?php else: ?>
                                        <i class="bi bi-x-circle-fill text-danger fs-5"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($item['name']) ?></div>
                                    <?php if (!empty($item['message'])): ?><small class="text-muted"><?= htmlspecialchars($item['message']) ?></small><?php endif; ?>
                                </td>
                                <td class="text-end pe-4" style="width: 150px;">
                                    <?php if (isset($item['current'])): ?>
                                        <span class="badge bg-light text-dark font-monospace" dir="ltr"><?= htmlspecialchars($item['current']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<div class="action-buttons">
    <a href="?refresh=1" class="btn-secondary-custom"><i class="bi bi-arrow-clockwise"></i> بررسی مجدد</a>
    <?php if ($allPassed): ?>
        <a href="database.php" class="btn-primary-custom">مرحله بعد: دیتابیس <i class="bi bi-arrow-left"></i></a>
    <?php else: ?>
        <button class="btn-primary-custom" disabled style="opacity:0.5; cursor:not-allowed;">ابتدا خطاها را برطرف کنید</button>
    <?php endif; ?>
</div>
