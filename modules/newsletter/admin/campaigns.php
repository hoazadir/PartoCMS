<?php
/**
 * PartoCMS - Newsletter Admin
 * لیست کمپین‌ها + آمار
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\CampaignService;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;

/** @var CampaignService $svc */
$svc = $GLOBALS['nl_container']->get(CampaignService::class);

$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));

$statusObj = null;
if ($statusFilter !== 'all' && in_array($statusFilter, CampaignStatus::all(), true)) {
    $statusObj = CampaignStatus::from($statusFilter);
}

$filters = [];
if ($statusObj) $filters['status'] = $statusObj;
if ($search !== '') $filters['search'] = $search;

$list  = $svc->listCampaigns($page, 20, $filters);
$stats = $svc->getGlobalStats();
$flash = nl_flash();
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خبرنامه — کمپین‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #fff; padding: 15px 20px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-right: 4px solid #6366f1; }
        .stat-card .num { font-size: 24px; font-weight: bold; color: #1e293b; }
        .stat-card .lbl { font-size: 12px; color: #64748b; margin-top: 3px; }
        .stat-card.sent { border-right-color: #16a34a; }
        .stat-card.opened { border-right-color: #3b82f6; }
        .stat-card.clicked { border-right-color: #8b5cf6; }
        .stat-card.failed { border-right-color: #dc2626; }
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .filter-tabs a { padding: 8px 16px; background: #fff; border-radius: 8px; text-decoration: none; color: #334155; font-size: 13px; border: 2px solid transparent; transition: 0.2s; }
        .filter-tabs a:hover { border-color: #6366f1; }
        .filter-tabs a.active { border-color: #6366f1; background: #eef2ff; color: #4338ca; font-weight: bold; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .camp-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .camp-table th { background: #f1f5f9; padding: 12px; text-align: right; color: #334155; font-weight: bold; border-bottom: 2px solid #e2e8f0; }
        .camp-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .camp-table tr:hover { background: #f8fafc; }
        .action-btn { padding: 5px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-primary { background: #dbeafe; color: #1e40af; }
        .btn-success { background: #dcfce7; color: #166534; }
        .btn-warning { background: #fef3c7; color: #92400e; }
        .empty { text-align: center; padding: 50px; color: #94a3b8; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .pagination { display: flex; gap: 6px; margin-top: 20px; justify-content: center; }
        .pagination a, .pagination span { padding: 8px 14px; background: #fff; border-radius: 6px; text-decoration: none; color: #334155; font-size: 13px; }
        .pagination .current { background: #6366f1; color: #fff; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; color: #fff; display: inline-block; }
        .metrics { display: flex; gap: 8px; font-size: 11px; color: #64748b; }
        .metrics span { display: inline-flex; align-items: center; gap: 2px; }
        @media (max-width: 900px) { .main { margin-right: 0; padding: 70px 15px 15px; } .camp-table { font-size: 11px; } .camp-table th, .camp-table td { padding: 8px 6px; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h4 style="margin:0;">📧 مدیریت خبرنامه — کمپین‌ها</h4>
        <div>
            <a href="<?= nl_admin_url('campaign-edit.php') ?>" class="action-btn btn-success" style="padding:10px 18px;">
                <i class="bi bi-plus-circle"></i> کمپین جدید
            </a>
            <a href="<?= nl_admin_url() ?>" class="action-btn btn-primary" style="padding:10px 18px;">
                <i class="bi bi-people"></i> مشترکین
            </a>
            <a href="<?= nl_admin_url('settings.php') ?>" class="action-btn btn-primary" style="padding:10px 18px;">
                <i class="bi bi-gear"></i> تنظیمات
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card">
            <div class="num"><?= number_format($stats['total']) ?></div>
            <div class="lbl">کل کمپین‌ها</div>
        </div>
        <div class="stat-card sent">
            <div class="num" style="color:#16a34a;"><?= number_format($stats['sent']) ?></div>
            <div class="lbl">ارسال شده</div>
        </div>
        <div class="stat-card opened">
            <div class="num" style="color:#3b82f6;"><?= number_format($stats['total_opened']) ?></div>
            <div class="lbl">باز شده</div>
        </div>
        <div class="stat-card clicked">
            <div class="num" style="color:#8b5cf6;"><?= number_format($stats['total_clicked']) ?></div>
            <div class="lbl">کلیک شده</div>
        </div>
        <div class="stat-card failed">
            <div class="num" style="color:#dc2626;"><?= number_format($stats['total_failed']) ?></div>
            <div class="lbl">ناموفق</div>
        </div>
    </div>

    <div class="filter-tabs">
        <a href="?status=all" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">همه</a>
        <?php foreach (CampaignStatus::cases() as $st): ?>
            <a href="?status=<?= $st->value ?>" class="<?= $statusFilter === $st->value ? 'active' : '' ?>">
                <?= htmlspecialchars($st->label()) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" style="margin-bottom:15px;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div style="display:flex; gap:10px;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="جستجو در عنوان یا موضوع..."
                   style="flex:1; padding:10px 15px; border:2px solid #e2e8f0; border-radius:8px; font-family:inherit;">
            <button type="submit" class="action-btn btn-primary" style="padding:10px 20px;">
                <i class="bi bi-search"></i> جستجو
            </button>
            <?php if ($search !== ''): ?>
                <a href="?status=<?= htmlspecialchars($statusFilter) ?>" class="action-btn btn-primary" style="padding:10px 20px;">
                    <i class="bi bi-x"></i> پاک کردن
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="card">
        <?php if (empty($list['items'])): ?>
            <div class="empty">
                <i class="bi bi-envelope-open" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                هیچ کمپینی یافت نشد
                <div style="margin-top:20px;">
                    <a href="<?= nl_admin_url('campaign-edit.php') ?>" class="action-btn btn-success" style="padding:10px 20px;">
                        <i class="bi bi-plus-circle"></i> ساخت اولین کمپین
                    </a>
                </div>
            </div>
        <?php else: ?>
            <table class="camp-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>عنوان</th>
                        <th>وضعیت</th>
                        <th>آمار</th>
                        <th>تاریخ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['items'] as $c): ?>
                        <?php
                        $statusClass = 'bg-' . $c->status()->color();
                        $date = $c->completedAt() ?? $c->startedAt() ?? $c->scheduledAt() ?? $c->createdAt();
                        $dateLabel = $c->completedAt() ? 'ارسال'
                                    : ($c->startedAt() ? 'شروع'
                                    : ($c->scheduledAt() ? 'زمان‌بندی' : 'ایجاد'));
                        ?>
                        <tr>
                            <td><?= (int) $c->id() ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c->title()) ?></strong>
                                <div style="font-size:11px; color:#94a3b8; margin-top:3px;">
                                    <?= htmlspecialchars(mb_substr($c->subject(), 0, 50)) ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($c->status()->label()) ?>
                                </span>
                            </td>
                            <td>
                                <div class="metrics">
                                    <span>📤 <?= number_format($c->sentCount()) ?></span>
                                    <?php if ($c->failedCount() > 0): ?>
                                        <span style="color:#dc2626;">❌ <?= number_format($c->failedCount()) ?></span>
                                    <?php endif; ?>
                                    <?php if ($c->openCount() > 0): ?>
                                        <span>👁 <?= number_format($c->openCount()) ?></span>
                                    <?php endif; ?>
                                    <?php if ($c->clickCount() > 0): ?>
                                        <span>🎯 <?= number_format($c->clickCount()) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($c->sentCount() > 0): ?>
                                    <div style="font-size:10px; color:#94a3b8; margin-top:3px;">
                                        <?= $c->openRate() ?>% باز، <?= $c->clickRate() ?>% کلیک
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small style="color:#64748b;"><?= $dateLabel ?>:</small>
                                <br>
                                <small><?= $date->format('Y/m/d H:i') ?></small>
                            </td>
                            <td style="white-space: nowrap;">
                                <?php if ($c->status()->canEdit()): ?>
                                    <a href="<?= nl_admin_url('campaign-edit.php?id=' . $c->id()) ?>" class="action-btn btn-primary" title="ویرایش">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($c->status()->canSend()): ?>
                                    <a href="<?= nl_admin_url('campaign-send.php?id=' . $c->id()) ?>" class="action-btn btn-success" title="ارسال">
                                        <i class="bi bi-send"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if ($c->status()->isSending()): ?>
                                    <a href="<?= nl_admin_url('campaign-send.php?id=' . $c->id()) ?>" class="action-btn btn-warning" title="پیشرفت">
                                        <i class="bi bi-hourglass-split"></i>
                                    </a>
                                <?php endif; ?>

                                <form method="post" action="<?= nl_admin_url('campaign-delete.php') ?>" style="display:inline;">
                                    <input type="hidden" name="id" value="<?= $c->id() ?>">
                                    <button type="submit" class="action-btn btn-danger" title="حذف"
                                            onclick="return confirm('حذف کمپین #<?= $c->id() ?>؟ تمام لاگ‌ها هم حذف می‌شوند.');">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($list['pages'] > 1): ?>
                <div class="pagination">
                    <?php
                    $qs = http_build_query(array_filter([
                        'status' => $statusFilter !== 'all' ? $statusFilter : null,
                        'q'      => $search !== '' ? $search : null,
                    ]));
                    for ($i = 1; $i <= $list['pages']; $i++):
                        $url = '?' . ($qs ? $qs . '&' : '') . 'page=' . $i;
                    ?>
                        <?php if ($i == $list['page']): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= $url ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
