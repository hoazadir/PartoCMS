<?php
/**
 * PartoCMS - Newsletter Admin
 * لیست مشترکین + آمار
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\SubscriptionService;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;

/** @var SubscriptionService $svc */
$svc = $GLOBALS['nl_container']->get(SubscriptionService::class);

// ─── فیلترها ───
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['q'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));

$statusObj = null;
if ($statusFilter !== 'all' && in_array($statusFilter, SubscriberStatus::all(), true)) {
    $statusObj = SubscriberStatus::from($statusFilter);
}

$filters = [];
if ($statusObj) $filters['status'] = $statusObj;
if ($search !== '') $filters['search'] = $search;

$list  = $svc->listSubscribers($page, 20, $filters);
$stats = $svc->getStats();
$flash = nl_flash();

// ─── Helper: نمایش badge ───
function nl_status_badge(SubscriberStatus $status): string
{
    return '<span class="badge bg-' . $status->color() . '">'
         . htmlspecialchars($status->label())
         . '</span>';
}
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خبرنامه — مشترکین | <?= htmlspecialchars($siteName) ?></title>
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
        .stat-card.active { border-right-color: #16a34a; }
        .stat-card.pending { border-right-color: #f59e0b; }
        .stat-card.unsubscribed { border-right-color: #64748b; }
        .stat-card.bounced { border-right-color: #dc2626; }
        .filter-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .filter-tabs a { padding: 8px 16px; background: #fff; border-radius: 8px; text-decoration: none; color: #334155; font-size: 13px; border: 2px solid transparent; transition: 0.2s; }
        .filter-tabs a:hover { border-color: #6366f1; }
        .filter-tabs a.active { border-color: #6366f1; background: #eef2ff; color: #4338ca; font-weight: bold; }
        .card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .sub-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sub-table th { background: #f1f5f9; padding: 12px; text-align: right; color: #334155; font-weight: bold; border-bottom: 2px solid #e2e8f0; }
        .sub-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .sub-table tr:hover { background: #f8fafc; }
        .action-btn { padding: 5px 10px; border-radius: 6px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .btn-danger { background: #fee2e2; color: #991b1b; }
        .btn-primary { background: #dbeafe; color: #1e40af; }
        .empty { text-align: center; padding: 50px; color: #94a3b8; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error   { background: #fee2e2; color: #991b1b; }
        .pagination { display: flex; gap: 6px; margin-top: 20px; justify-content: center; }
        .pagination a, .pagination span { padding: 8px 14px; background: #fff; border-radius: 6px; text-decoration: none; color: #334155; font-size: 13px; }
        .pagination .current { background: #6366f1; color: #fff; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <h4 style="margin:0;">📮 مدیریت خبرنامه — مشترکین</h4>
        <div>
            <a href="<?= nl_admin_url('campaigns.php') ?>" class="action-btn btn-primary">
                <i class="bi bi-envelope"></i> کمپین‌ها
            </a>
            <a href="<?= nl_admin_url('settings.php') ?>" class="action-btn btn-primary">
                <i class="bi bi-gear"></i> تنظیمات
            </a>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stat-grid">
        <a href="?status=all" style="text-decoration:none;"><div class="stat-card">
            <div class="num"><?= number_format($stats['total']) ?></div>
            <div class="lbl">کل مشترکین</div>
        </div></a>
        <a href="?status=active" style="text-decoration:none;"><div class="stat-card active">
            <div class="num" style="color:#16a34a;"><?= number_format($stats['active']) ?></div>
            <div class="lbl">فعال</div>
        </div></a>
        <a href="?status=pending" style="text-decoration:none;"><div class="stat-card pending">
            <div class="num" style="color:#f59e0b;"><?= number_format($stats['pending']) ?></div>
            <div class="lbl">در انتظار تأیید</div>
        </div></a>
        <a href="?status=unsubscribed" style="text-decoration:none;"><div class="stat-card unsubscribed">
            <div class="num" style="color:#64748b;"><?= number_format($stats['unsubscribed']) ?></div>
            <div class="lbl">لغو شده</div>
        </div></a>
        <a href="?status=bounced" style="text-decoration:none;"><div class="stat-card bounced">
            <div class="num" style="color:#dc2626;"><?= number_format($stats['bounced']) ?></div>
            <div class="lbl">برگشتی</div>
        </div></a>
    </div>

    <!-- Filters -->
    <div class="filter-tabs">
        <a href="?status=all" class="<?= $statusFilter === 'all' ? 'active' : '' ?>">همه</a>
        <a href="?status=active" class="<?= $statusFilter === 'active' ? 'active' : '' ?>">فعال</a>
        <a href="?status=pending" class="<?= $statusFilter === 'pending' ? 'active' : '' ?>">در انتظار</a>
        <a href="?status=unsubscribed" class="<?= $statusFilter === 'unsubscribed' ? 'active' : '' ?>">لغو شده</a>
        <a href="?status=bounced" class="<?= $statusFilter === 'bounced' ? 'active' : '' ?>">برگشتی</a>
    </div>

    <form method="get" style="margin-bottom:15px;">
        <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
        <div style="display:flex; gap:10px;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="جستجو در ایمیل یا نام..."
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

    <!-- Table -->
    <div class="card">
        <?php if (empty($list['items'])): ?>
            <div class="empty">
                <i class="bi bi-inbox" style="font-size:48px; display:block; margin-bottom:15px;"></i>
                هیچ مشترکی یافت نشد
            </div>
        <?php else: ?>
            <table class="sub-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ایمیل</th>
                        <th>نام</th>
                        <th>وضعیت</th>
                        <th>منبع</th>
                        <th>تاریخ</th>
                        <th>عملیات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['items'] as $s): ?>
                        <tr>
                            <td><?= (int) $s->id() ?></td>
                            <td style="font-family:monospace; direction:ltr; text-align:left;">
                                <?= htmlspecialchars($s->email()->value()) ?>
                            </td>
                            <td><?= htmlspecialchars((string) $s->name()) ?></td>
                            <td><?= nl_status_badge($s->status()) ?></td>
                            <td><small style="color:#94a3b8;"><?= htmlspecialchars((string) $s->source()) ?></small></td>
                            <td><small><?= $s->createdAt()->format('Y/m/d') ?></small></td>
                            <td>
                                <a href="<?= nl_admin_url('subscriber-edit.php?id=' . $s->id()) ?>" class="action-btn btn-primary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
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
