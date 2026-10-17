<?php
/**
 * PartoCMS - Newsletter Admin
 * ارسال کمپین + پیگیری پیشرفت
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\CampaignService;
use PartoCMS\Newsletter\Domain\Service\SendService;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;
use PartoCMS\Newsletter\Domain\ValueObject\SendStatus;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoCampaignRepository;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSendLogRepository;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSubscriberRepository;

/** @var CampaignService $campaignSvc */
$campaignSvc = $GLOBALS['nl_container']->get(CampaignService::class);
$campaignRepo = $GLOBALS['nl_container']->get(PdoCampaignRepository::class);
$logRepo      = $GLOBALS['nl_container']->get(PdoSendLogRepository::class);
$subRepo      = $GLOBALS['nl_container']->get(PdoSubscriberRepository::class);

// تعداد مشترکین فعال — مبنای نمایش دکمه «شروع ارسال»
$activeSubscribers = $subRepo->count(SubscriberStatus::ACTIVE);

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    nl_flash('شناسه کمپین نامعتبر است', 'error');
    header('Location: ' . nl_admin_url('campaigns.php'));
    exit;
}

$campaign = $campaignRepo->findById($id);
if ($campaign === null) {
    nl_flash('کمپین یافت نشد', 'error');
    header('Location: ' . nl_admin_url('campaigns.php'));
    exit;
}

// ─── پردازش POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'start':
                // اگر draft است، start sending
                if ($campaign->status() === CampaignStatus::DRAFT) {
                    $result = $campaignSvc->startSending($id);
                    nl_flash('ارسال شروع شد — ' . $result['queued'] . ' ایمیل در صف قرار گرفت');
                } elseif ($campaign->status() === CampaignStatus::PAUSED) {
                    $campaignSvc->resume($id);
                    nl_flash('ارسال ادامه یافت');
                }
                break;

            case 'pause':
                $campaignSvc->pause($id);
                nl_flash('ارسال متوقف شد');
                break;

            case 'process_batch':
                // پردازش یک batch (AJAX-like)
                $sendSvc = $GLOBALS['nl_container']->get(SendService::class);
                $result = $sendSvc->processQueue($id, 10);
                echo json_encode([
                    'ok' => true,
                    'data' => $result,
                ]);
                exit;

            case 'force_complete':
                $campaignSvc->complete($id);
                nl_flash('کمپین به صورت دستی تکمیل شد');
                break;

            case 'force_fail':
                $campaignSvc->fail($id, 'manual_cancel_by_admin');
                nl_flash('کمپین به عنوان ناموفق علامت خورد');
                break;
        }
    } catch (Throwable $e) {
        nl_flash('خطا: ' . $e->getMessage(), 'error');
    }

    header('Location: ' . nl_admin_url('campaign-send.php?id=' . $id));
    exit;
}

$flash = nl_flash();

// ─── آمار زنده ───
$breakdown = $logRepo->getStatusBreakdown($id);
$total     = array_sum($breakdown);
$processed = $breakdown[SendStatus::SENT->value]
           + $breakdown[SendStatus::OPENED->value]
           + $breakdown[SendStatus::CLICKED->value]
           + $breakdown[SendStatus::FAILED->value]
           + $breakdown[SendStatus::BOUNCED->value];
$queued    = $breakdown[SendStatus::QUEUED->value];
$percent   = $total > 0 ? round(($processed / $total) * 100, 1) : 0;

$isDone    = $campaign->status()->isTerminal() || ($total > 0 && $queued === 0);
$canStart  = in_array($campaign->status(), [CampaignStatus::DRAFT, CampaignStatus::PAUSED], true);
$canPause  = $campaign->status() === CampaignStatus::SENDING;

// گروه‌بندی breakdown برای نمایش
$breakdownUi = [
    'queued'  => ['label' => 'در صف',      'color' => '#64748b', 'value' => $breakdown[SendStatus::QUEUED->value]  ?? 0],
    'sent'    => ['label' => 'ارسال شده',   'color' => '#0ea5e9', 'value' => $breakdown[SendStatus::SENT->value]    ?? 0],
    'opened'  => ['label' => 'باز شده',    'color' => '#3b82f6', 'value' => $breakdown[SendStatus::OPENED->value]  ?? 0],
    'clicked' => ['label' => 'کلیک شده',   'color' => '#8b5cf6', 'value' => $breakdown[SendStatus::CLICKED->value] ?? 0],
    'failed'  => ['label' => 'ناموفق',     'color' => '#dc2626', 'value' => $breakdown[SendStatus::FAILED->value]  ?? 0],
    'bounced' => ['label' => 'برگشتی',     'color' => '#f59e0b', 'value' => $breakdown[SendStatus::BOUNCED->value] ?? 0],
];
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال کمپین #<?= (int) $campaign->id() ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h4 { margin: 5px 0 0; }
        .card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h5 { margin: 0 0 20px; color: #1e293b; font-size: 15px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
        .btn { padding: 12px 24px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; font-size: 14px; font-weight: bold; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-danger  { background: #dc2626; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn:hover { opacity: 0.9; }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .actions-bar { display: flex; gap: 12px; flex-wrap: wrap; padding: 20px; background: #f8fafc; border-radius: 10px; }
        .badge { padding: 6px 14px; border-radius: 20px; font-size: 13px; color: #fff; display: inline-block; font-weight: bold; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .progress-wrap { background: #f1f5f9; height: 32px; border-radius: 8px; overflow: hidden; position: relative; margin: 15px 0; }
        .progress-bar { height: 100%; background: linear-gradient(90deg, #6366f1, #8b5cf6); transition: width 0.5s; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: bold; font-size: 13px; }
        .stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
        .stat-card { padding: 15px; background: #f8fafc; border-radius: 8px; border-right: 4px solid #6366f1; }
        .stat-card .num { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .stat-card .lbl { font-size: 12px; color: #64748b; }
        .info-line { display: flex; align-items: baseline; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .info-line:last-child { border-bottom: none; }
        .info-line .lbl { color: #64748b; }
        .info-line .val {
            color: #1e293b;
            font-weight: bold;
            unicode-bidi: plaintext;
        }
        .info-line .lbl {
            color: #64748b;
            font-weight: bold;
            flex-shrink: 0;
        }
        .loading-spinner { display: inline-block; width: 16px; height: 16px; border: 3px solid #fff; border-radius: 50%; border-top-color: transparent; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 1100px) { .main { margin-right: 0; padding: 70px 15px 15px; } }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/../../../admin/includes/sidebar.php'; ?>

<div class="main">

    <div class="top-bar">
        <div>
            <a href="<?= nl_admin_url('campaigns.php') ?>" style="text-decoration:none; color:#6366f1;">
                <i class="bi bi-arrow-right"></i> بازگشت به لیست
            </a>
            <h4>🚀 ارسال کمپین #<?= (int) $campaign->id() ?> — <?= htmlspecialchars($campaign->title()) ?></h4>
        </div>
        <div>
            <span class="badge bg-<?= $campaign->status()->color() ?>">
                <?= htmlspecialchars($campaign->status()->label()) ?>
            </span>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <!-- Progress -->
    <div class="card">
        <h5>📊 پیشرفت ارسال</h5>
        <div class="progress-wrap">
            <div class="progress-bar" id="progressBar" style="width: <?= $percent ?>%;" data-total="<?= $total ?>">
                <?= $percent ?>%
            </div>
        </div>
        <div style="display: flex; justify-content: space-between; font-size: 13px; color: #64748b;">
            <span>پردازش شده: <strong id="statProcessed"><?= number_format($processed) ?></strong> از <strong id="statTotal"><?= number_format($total) ?></strong></span>
            <span>در صف: <strong id="statQueued"><?= number_format($queued) ?></strong></span>
        </div>
    </div>

    <!-- Breakdown -->
    <div class="card">
        <h5>📈 تفکیک وضعیت‌ها</h5>
        <div class="stat-grid">
            <?php foreach ($breakdownUi as $key => $item): ?>
                <div class="stat-card" style="border-right-color: <?= $item['color'] ?>;">
                    <div class="num" style="color: <?= $item['color'] ?>;"><?= number_format($item['value']) ?></div>
                    <div class="lbl"><?= $item['label'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Campaign Info -->
    <div class="card">
        <h5>📧 اطلاعات کمپین</h5>
        <div class="info-line">
            <span class="lbl">عنوان</span>
            <span class="val" dir="auto"><?= htmlspecialchars($campaign->title()) ?></span>
        </div>
        <div class="info-line">
            <span class="lbl">موضوع</span>
            <span class="val" dir="auto"><?= htmlspecialchars($campaign->subject()) ?></span>
        </div>
        <?php if ($campaign->startedAt()): ?>
            <div class="info-line">
                <span class="lbl">شروع ارسال</span>
                <span class="val" dir="ltr" style="text-align: end;"><?= $campaign->startedAt()->format('Y/m/d H:i') ?></span>
            </div>
        <?php endif; ?>
        <?php if ($campaign->completedAt()): ?>
            <div class="info-line">
                <span class="lbl">پایان ارسال</span>
                <span class="val" dir="ltr" style="text-align: end;"><?= $campaign->completedAt()->format('Y/m/d H:i') ?></span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="card">
        <h5>⚙️ عملیات</h5>
        <div class="actions-bar">

            <?php if ($canStart && $activeSubscribers > 0): ?>
                <form method="post" style="display:inline; margin:0;">
                    <input type="hidden" name="action" value="start">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-play-circle"></i>
                        <?= $campaign->status() === CampaignStatus::PAUSED ? 'ادامه ارسال' : 'شروع ارسال' ?>
                    </button>
                </form>
            <?php elseif ($canStart && $activeSubscribers === 0): ?>
                <div style="color: #94a3b8; font-size: 13px; padding: 10px;">
                    ⚠️ هیچ مشترک فعالی برای ارسال وجود ندارد — ابتدا از بخش «مشترکین» مشترک اضافه یا تأیید کنید
                    <a href="<?= nl_admin_url() ?>" style="color: #6366f1; margin-right: 10px;">افزودن مشترک</a>
                </div>
            <?php endif; ?>

            <?php if ($canPause): ?>
                <form method="post" style="display:inline; margin:0;">
                    <input type="hidden" name="action" value="pause">
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-pause-circle"></i> توقف موقت
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($total > 0 && !$isDone): ?>
                <button type="button" id="btnBatch" class="btn btn-primary" onclick="processBatch()">
                    <i class="bi bi-lightning-charge"></i> پردازش دسته‌ای (۱۰ ایمیل)
                </button>
                <button type="button" id="btnAuto" class="btn btn-success" onclick="toggleAutoSend()">
                    <i class="bi bi-rocket-takeoff"></i> <span id="autoLabel">ارسال خودکار کامل</span>
                </button>
            <?php endif; ?>

            <?php if ($campaign->status() === CampaignStatus::SENDING && $queued > 0): ?>
                <form method="post" style="display:inline; margin:0;" onsubmit="return confirm('تکمیل دستی؟ ایمیل‌های باقیمانده ارسال نمی‌شوند.');">
                    <input type="hidden" name="action" value="force_complete">
                    <button type="submit" class="btn btn-secondary">
                        <i class="bi bi-check-all"></i> تکمیل دستی
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= nl_admin_url('campaign-edit.php?id=' . $campaign->id()) ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> ویرایش
            </a>

        </div>
    </div>

</div>

<script>
// ─── Auto-Send Loop ───
let autoSendActive = false;
let autoSendTimer  = null;

function toggleAutoSend() {
    if (autoSendActive) {
        stopAutoSend();
    } else {
        startAutoSend();
    }
}

function startAutoSend() {
    autoSendActive = true;
    const btn = document.getElementById('btnAuto');
    if (!btn) return;
    btn.classList.remove('btn-success');
    btn.classList.add('btn-warning');
    btn.innerHTML = '<span class="loading-spinner"></span> <span id="autoLabel">در حال ارسال خودکار — کلیک برای توقف</span>';
    runAutoCycle();
}

function stopAutoSend() {
    autoSendActive = false;
    if (autoSendTimer) {
        clearTimeout(autoSendTimer);
        autoSendTimer = null;
    }
    const btn = document.getElementById('btnAuto');
    if (!btn) return;
    btn.classList.remove('btn-warning');
    btn.classList.add('btn-success');
    btn.innerHTML = '<i class="bi bi-rocket-takeoff"></i> <span id="autoLabel">ادامه ارسال خودکار</span>';
}

async function runAutoCycle() {
    if (!autoSendActive) return;

    try {
        const formData = new FormData();
        formData.append('action', 'process_batch');

        const resp = await fetch('?id=<?= (int) $campaign->id() ?>', {
            method: 'POST',
            body: formData,
        });
        const data = await resp.json();

        if (!data.ok) {
            stopAutoSend();
            alert('خطا در پردازش خودکار.');
            return;
        }

        const d = data.data;
        updateProgressLive(d);

        if (d.campaign_completed || d.remaining === 0) {
            stopAutoSend();
            setTimeout(() => location.reload(), 1500);
            return;
        }

        if (d.processed === 0) {
            stopAutoSend();
            alert('هیچ ایمیلی پردازش نشد. متوقف شد.');
            return;
        }

        autoSendTimer = setTimeout(runAutoCycle, 2000);
    } catch (e) {
        stopAutoSend();
        alert('خطای شبکه: ' + e.message);
    }
}

function updateProgressLive(data) {
    const totalEl = document.getElementById('statTotal');
    if (!totalEl) return;
    const total = parseInt(totalEl.textContent.replace(/[^0-9]/g, ''), 10);
    if (!total) return;

    const remaining = data.remaining ?? 0;
    const processed = total - remaining;
    const percent   = Math.round((processed / total) * 1000) / 10;

    const bar = document.getElementById('progressBar');
    if (bar) {
        bar.style.width = percent + '%';
        bar.textContent = percent + '%';
    }
    const p = document.getElementById('statProcessed');
    if (p) p.textContent = processed.toLocaleString('en-US');
    const q = document.getElementById('statQueued');
    if (q) q.textContent = Number(remaining).toLocaleString('en-US');
}

async function processBatch() {
    const btn = document.getElementById('btnBatch');
    const original = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> در حال پردازش...';

    try {
        const formData = new FormData();
        formData.append('action', 'process_batch');

        const resp = await fetch('?id=<?= (int) $campaign->id() ?>', {
            method: 'POST',
            body: formData,
        });
        const data = await resp.json();

        if (data.ok) {
            // رفرش کن تا progress جدید را ببینیم
            location.reload();
        } else {
            alert('خطا در پردازش');
            btn.disabled = false;
            btn.innerHTML = original;
        }
    } catch (e) {
        alert('خطای شبکه: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// Auto-refresh هر ۱۰ ثانیه اگر sending است
<?php if ($campaign->status() === CampaignStatus::SENDING && $queued > 0): ?>
setInterval(() => {
    if (!autoSendActive) location.reload();
}, 10000);
<?php endif; ?>
</script>

</body>
</html>
