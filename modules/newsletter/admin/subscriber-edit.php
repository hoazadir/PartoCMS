<?php
/**
 * PartoCMS - Newsletter Admin
 * ویرایش مشترک + مشاهده لاگ‌های ارسال
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\SubscriptionService;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSubscriberRepository;

$container = $GLOBALS['nl_container'];
$svc       = $container->get(SubscriptionService::class);
$subRepo   = $container->get(PdoSubscriberRepository::class);

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    header('Location: ' . nl_admin_url());
    exit;
}

$subscriber = $subRepo->findById($id);
if ($subscriber === null) {
    nl_flash('مشترک یافت نشد', 'error');
    header('Location: ' . nl_admin_url());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'save':
            $name = trim($_POST['name'] ?? '');
            $svc->updateProfile($subscriber->email(), $name !== '' ? $name : null);
            nl_flash('اطلاعات ذخیره شد');
            break;
        case 'unsubscribe':
            $subscriber->unsubscribe();
            $subRepo->save($subscriber);
            nl_flash('مشترک لغو اشتراک شد');
            break;
        case 'reactivate':
            $subscriber->confirm();
            $subRepo->save($subscriber);
            nl_flash('مشترک مجدداً فعال شد');
            break;
        case 'mark_bounced':
            $subscriber->markAsBounced();
            $subRepo->save($subscriber);
            nl_flash('مشترک به عنوان برگشتی علامت خورد');
            break;
        case 'delete':
            $subRepo->delete($subscriber);
            nl_flash('مشترک حذف شد');
            header('Location: ' . nl_admin_url());
            exit;
    }
    header('Location: ' . nl_admin_url('subscriber-edit.php?id=' . $id));
    exit;
}

$flash = nl_flash();

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT sl.*, c.title AS campaign_title
    FROM newsletter_sends sl
    LEFT JOIN newsletter_campaigns c ON c.id = sl.campaign_id
    WHERE sl.subscriber_id = ?
    ORDER BY sl.id DESC
    LIMIT 20
");
$stmt->execute([$id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ویرایش مشترک | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h5 { margin: 0 0 20px; color: #1e293b; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px 30px; }
        .info-row .lbl { font-size: 12px; color: #64748b; font-weight: bold; display: block; margin-bottom: 4px; }
        .info-row .val { font-size: 14px; color: #1e293b; }
        .info-row .val.mono { font-family: monospace; direction: ltr; text-align: left; }
        .form-row { margin-bottom: 15px; }
        .form-row label { display: block; font-size: 13px; font-weight: bold; color: #334155; margin-bottom: 6px; }
        .form-row input { width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0; border-radius: 8px; font-family: inherit; font-size: 14px; }
        .btn { padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-danger { background: #dc2626; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; color: #fff; display: inline-block; }
        .badge-active { background: #16a34a; }
        .badge-pending { background: #f59e0b; }
        .badge-unsubscribed { background: #64748b; }
        .badge-bounced { background: #dc2626; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .log-table th { background: #f1f5f9; padding: 10px; text-align: right; font-weight: bold; color: #334155; }
        .log-table td { padding: 10px; border-bottom: 1px solid #f1f5f9; }
        .empty-logs { text-align: center; padding: 30px; color: #94a3b8; font-size: 14px; }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>
<div class="main">
    <div class="top-bar">
        <div>
            <a href="<?= nl_admin_url() ?>" style="text-decoration:none; color:#6366f1;">
                <i class="bi bi-arrow-right"></i> بازگشت به لیست
            </a>
            <h4 style="margin: 5px 0 0;">✏️ ویرایش مشترک #<?= (int) $subscriber->id() ?></h4>
        </div>
        <div>
            <?php
            $statusClass = match($subscriber->status()) {
                SubscriberStatus::ACTIVE       => 'badge-active',
                SubscriberStatus::PENDING      => 'badge-pending',
                SubscriberStatus::UNSUBSCRIBED => 'badge-unsubscribed',
                SubscriberStatus::BOUNCED      => 'badge-bounced',
            };
            ?>
            <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($subscriber->status()->label()) ?></span>
        </div>
    </div>
    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>
    <div class="card">
        <h5>📋 اطلاعات مشترک</h5>
        <div class="info-grid">
            <div class="info-row"><span class="lbl">ایمیل</span><span class="val mono"><?= htmlspecialchars($subscriber->email()->value()) ?></span></div>
            <div class="info-row"><span class="lbl">وضعیت</span><span class="val"><?= htmlspecialchars($subscriber->status()->label()) ?></span></div>
            <div class="info-row"><span class="lbl">منبع</span><span class="val"><?= htmlspecialchars((string) $subscriber->source() ?: '—') ?></span></div>
            <div class="info-row"><span class="lbl">IP</span><span class="val mono"><?= htmlspecialchars((string) $subscriber->ip() ?: '—') ?></span></div>
            <div class="info-row"><span class="lbl">تاریخ عضویت</span><span class="val"><?= $subscriber->createdAt()->format('Y/m/d H:i') ?></span></div>
            <div class="info-row"><span class="lbl">آخرین بروزرسانی</span><span class="val"><?= $subscriber->updatedAt()->format('Y/m/d H:i') ?></span></div>
            <?php if ($subscriber->confirmedAt()): ?>
                <div class="info-row"><span class="lbl">تاریخ تأیید</span><span class="val"><?= $subscriber->confirmedAt()->format('Y/m/d H:i') ?></span></div>
            <?php endif; ?>
            <?php if ($subscriber->unsubscribedAt()): ?>
                <div class="info-row"><span class="lbl">تاریخ لغو</span><span class="val"><?= $subscriber->unsubscribedAt()->format('Y/m/d H:i') ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card">
        <h5>✏️ ویرایش نام</h5>
        <form method="post">
            <input type="hidden" name="action" value="save">
            <div class="form-row">
                <label>نام (اختیاری)</label>
                <input type="text" name="name" value="<?= htmlspecialchars((string) $subscriber->name()) ?>" placeholder="نام مشترک">
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check"></i> ذخیره تغییرات</button>
        </form>
    </div>
    <div class="card">
        <h5>⚙️ عملیات</h5>
        <div class="actions">
            <?php if ($subscriber->status() !== SubscriberStatus::ACTIVE): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="reactivate">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle"></i> فعال‌سازی</button>
                </form>
            <?php endif; ?>
            <?php if ($subscriber->status() !== SubscriberStatus::UNSUBSCRIBED): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="unsubscribe">
                    <button type="submit" class="btn btn-warning" onclick="return confirm('لغو اشتراک این مشترک؟');"><i class="bi bi-x-circle"></i> لغو اشتراک</button>
                </form>
            <?php endif; ?>
            <?php if ($subscriber->status() !== SubscriberStatus::BOUNCED): ?>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="mark_bounced">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('علامت‌گذاری به عنوان برگشتی؟');"><i class="bi bi-exclamation-circle"></i> علامت برگشتی</button>
                </form>
            <?php endif; ?>
            <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger" onclick="return confirm('حذف کامل مشترک؟ این عمل قابل بازگشت نیست.');"><i class="bi bi-trash"></i> حذف</button>
            </form>
        </div>
    </div>
    <div class="card">
        <h5>📧 لاگ ارسال ایمیل‌ها (<?= count($logs) ?>)</h5>
        <?php if (empty($logs)): ?>
            <div class="empty-logs"><i class="bi bi-inbox" style="font-size:32px; display:block; margin-bottom:10px;"></i>هیچ ایمیلی به این مشترک ارسال نشده</div>
        <?php else: ?>
            <table class="log-table">
                <thead><tr><th>کمپین</th><th>وضعیت</th><th>ارسال</th><th>باز شده</th><th>کلیک</th></tr></thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <?php
                        $statusColors = ['queued' => 'secondary', 'sent' => 'info', 'opened' => 'primary', 'clicked' => 'success', 'failed' => 'danger', 'bounced' => 'warning'];
                        $color = $statusColors[$log['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars((string) ($log['campaign_title'] ?? '—')) ?></td>
                            <td><span class="badge bg-<?= $color ?>"><?= htmlspecialchars($log['status']) ?></span></td>
                            <td><small><?= $log['sent_at'] ? date('Y/m/d H:i', strtotime($log['sent_at'])) : '—' ?></small></td>
                            <td><small><?= $log['opened_at'] ? '✅ ' . date('Y/m/d', strtotime($log['opened_at'])) : '—' ?></small></td>
                            <td><small><?= $log['clicked_at'] ? '🎯 ' . date('Y/m/d', strtotime($log['clicked_at'])) : '—' ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
