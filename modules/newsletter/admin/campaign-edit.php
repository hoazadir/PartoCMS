<?php
/**
 * PartoCMS - Newsletter Admin
 * ساخت / ویرایش کمپین — با ویرایشگر TinyMCE
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\CampaignService;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;
use PartoCMS\Newsletter\Infrastructure\Persistence\PdoCampaignRepository;

/** @var CampaignService $svc */
$svc          = $GLOBALS['nl_container']->get(CampaignService::class);
$campaignRepo = $GLOBALS['nl_container']->get(PdoCampaignRepository::class);

$id       = (int) ($_GET['id'] ?? 0);
$isEdit   = $id > 0;
$campaign = $isEdit ? $campaignRepo->findById($id) : null;

if ($isEdit && $campaign === null) {
    nl_flash('کمپین یافت نشد', 'error');
    header('Location: ' . nl_admin_url('campaigns.php'));
    exit;
}

// ─── پردازش POST ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $title     = trim($_POST['title'] ?? '');
    $subject   = trim($_POST['subject'] ?? '');
    $preheader = trim($_POST['preheader'] ?? '') ?: null;
    $bodyHtml  = (string) ($_POST['body_html'] ?? '');
    $bodyText  = trim($_POST['body_text'] ?? '') ?: null;

    try {
        switch ($action) {
            case 'save':
                if ($isEdit) {
                    $campaign = $svc->updateCampaign($id, $title, $subject, $bodyHtml, $bodyText);
                    nl_flash('کمپین ذخیره شد');
                } else {
                    $campaign = $svc->createCampaign(
                        title: $title,
                        subject: $subject,
                        bodyHtml: $bodyHtml,
                        bodyText: $bodyText,
                        preheader: $preheader,
                        createdBy: (int) ($_SESSION['user_id'] ?? 0) ?: null
                    );
                    nl_flash('کمپین ساخته شد — می‌توانی ویرایش را ادامه دهی');
                    header('Location: ' . nl_admin_url('campaign-edit.php?id=' . $campaign->id()));
                    exit;
                }
                break;

            case 'schedule':
                $when = trim($_POST['scheduled_at'] ?? '');
                if ($when === '') {
                    nl_flash('تاریخ زمان‌بندی الزامی است', 'error');
                    break;
                }
                $dt = new DateTimeImmutable($when);
                $svc->schedule($id, $dt);
                nl_flash('کمپین زمان‌بندی شد: ' . $dt->format('Y/m/d H:i'));
                break;

            case 'unschedule':
                $svc->unschedule($id);
                nl_flash('زمان‌بندی لغو شد');
                break;

            case 'delete':
                $svc->delete($id);
                nl_flash('کمپین حذف شد');
                header('Location: ' . nl_admin_url('campaigns.php'));
                exit;
        }
    } catch (Throwable $e) {
        nl_flash('خطا: ' . $e->getMessage(), 'error');
    }

    header('Location: ' . nl_admin_url('campaign-edit.php?id=' . $id));
    exit;
}

$flash = nl_flash();

$v = [
    'title'     => $campaign?->title()     ?? '',
    'subject'   => $campaign?->subject()   ?? '',
    'preheader' => $campaign?->preheader() ?? '',
    'body_html' => $campaign?->bodyHtml()  ?? '',
    'body_text' => $campaign?->bodyText()  ?? '',
];

$statusColor = $campaign ? $campaign->status()->color() : 'secondary';
$statusLabel = $campaign ? $campaign->status()->label() : 'جدید';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'ویرایش' : 'کمپین جدید' ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js"></script>
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .top-bar h4 { margin: 5px 0 0; }
        .card { background: #fff; border-radius: 10px; padding: 25px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .card h5 { margin: 0 0 20px; color: #1e293b; font-size: 15px; padding-bottom: 12px; border-bottom: 2px solid #f1f5f9; }
        .form-row { margin-bottom: 18px; }
        .form-row label { display: block; font-size: 13px; font-weight: bold; color: #334155; margin-bottom: 6px; }
        .form-row label .req { color: #dc2626; }
        .form-row input[type="text"],
        .form-row input[type="datetime-local"] {
            width: 100%; padding: 10px 15px; border: 2px solid #e2e8f0;
            border-radius: 8px; font-family: inherit; font-size: 14px; box-sizing: border-box;
        }
        .form-row input:focus { outline: none; border-color: #6366f1; }
        .form-row .hint { font-size: 11px; color: #94a3b8; margin-top: 5px; }
        .btn { padding: 11px 22px; border-radius: 8px; border: none; cursor: pointer; font-family: inherit; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-primary { background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff; }
        .btn-success { background: #16a34a; color: #fff; }
        .btn-warning { background: #f59e0b; color: #fff; }
        .btn-danger  { background: #dc2626; color: #fff; }
        .btn-secondary { background: #e2e8f0; color: #334155; }
        .btn:hover { opacity: 0.9; }
        .actions-bar { display: flex; gap: 10px; flex-wrap: wrap; padding: 20px; background: #f8fafc; border-radius: 10px; margin-top: 10px; align-items: center; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; color: #fff; display: inline-block; }
        .alert { padding: 14px 20px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .stats-row { display: flex; gap: 20px; flex-wrap: wrap; padding: 15px 0; }
        .stat-item { font-size: 13px; color: #334155; }
        .stat-item strong { color: #1e293b; }
        .schedule-box { background: #fef3c7; border: 2px solid #fcd34d; border-radius: 8px; padding: 15px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .tokens-hint { background: #eef2ff; border-right: 4px solid #6366f1; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 12px; }
        .tokens-hint code { background: #fff; padding: 2px 8px; border-radius: 4px; color: #4338ca; font-weight: bold; direction: ltr; display: inline-block; }
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
            <h4><?= $isEdit ? '✏️ ویرایش کمپین #' . (int) $campaign->id() : '➕ کمپین جدید' ?></h4>
        </div>
        <div>
            <span class="badge bg-<?= $statusColor ?>"><?= htmlspecialchars($statusLabel) ?></span>
        </div>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <?php if ($campaign && $campaign->status() === CampaignStatus::SCHEDULED && $campaign->scheduledAt()): ?>
        <div class="schedule-box">
            <div>🕐 <strong>زمان‌بندی شده برای:</strong> <?= $campaign->scheduledAt()->format('Y/m/d H:i') ?></div>
            <form method="post" style="display:inline; margin:0;">
                <input type="hidden" name="action" value="unschedule">
                <button type="submit" class="btn btn-secondary" style="padding:6px 14px; font-size:12px;">
                    لغو زمان‌بندی
                </button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($campaign && $campaign->sentCount() > 0): ?>
        <div class="card">
            <h5>📊 آمار ارسال</h5>
            <div class="stats-row" style="padding:0;">
                <div class="stat-item">📤 ارسال شده: <strong><?= number_format($campaign->sentCount()) ?></strong></div>
                <div class="stat-item">❌ ناموفق: <strong><?= number_format($campaign->failedCount()) ?></strong></div>
                <div class="stat-item">👁 باز شده: <strong><?= number_format($campaign->openCount()) ?> (<?= $campaign->openRate() ?>%)</strong></div>
                <div class="stat-item">🎯 کلیک: <strong><?= number_format($campaign->clickCount()) ?> (<?= $campaign->clickRate() ?>%)</strong></div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" id="campaign-form">
        <div class="card">
            <h5>📝 اطلاعات ایمیل</h5>

            <div class="form-row">
                <label>عنوان کمپین <span class="req">*</span></label>
                <input type="text" name="title" value="<?= htmlspecialchars($v['title']) ?>" required placeholder="مثال: خبرنامه شماره ۵">
                <div class="hint">فقط برای مدیریت داخلی — در ایمیل نمایش داده نمی‌شود</div>
            </div>

            <div class="form-row">
                <label>موضوع ایمیل (Subject) <span class="req">*</span></label>
                <input type="text" name="subject" value="<?= htmlspecialchars($v['subject']) ?>" required placeholder="مثال: 🎉 خبرهای تازه این هفته">
                <div class="hint">عنوانی که کاربر در inbox می‌بیند</div>
            </div>

            <div class="form-row">
                <label>Preheader (اختیاری)</label>
                <input type="text" name="preheader" value="<?= htmlspecialchars((string) $v['preheader']) ?>" placeholder="متن کوتاه نمایش‌داده‌شده کنار موضوع در inbox">
                <div class="hint">۶۰ تا ۱۰۰ کاراکتر — برای پیش‌نمایش بهتر در Gmail/Outlook</div>
            </div>
        </div>

        <div class="card">
            <h5>📄 محتوای خبرنامه</h5>

            <div class="tokens-hint">
                💡 <strong>راهنما:</strong>
                اینجا محتوای خبرنامه را بنویسید. برای شخصی‌سازی از متغیرهای زیر استفاده کنید:
                <code>{نام}</code> = نام مشترک،
                <code>{ایمیل}</code> = ایمیل مشترک
                <br>
                <small style="color:#64748b;">(این راهنما در ایمیل ارسالی نمایش داده نمی‌شود)</small>
            </div>

            <div class="form-row">
                <label>متن اصلی <span class="req">*</span></label>
                <textarea name="body_html" id="body_html"><?= htmlspecialchars($v['body_html']) ?></textarea>
            </div>

            <div class="form-row">
                <label>نسخه متنی (اختیاری)</label>
                <textarea name="body_text" rows="4" style="width:100%; padding:10px 15px; border:2px solid #e2e8f0; border-radius:8px; font-family:inherit; box-sizing:border-box; resize:vertical;" placeholder="اگر خالی بگذارید، از HTML تبدیل خودکار می‌شود"><?= htmlspecialchars((string) $v['body_text']) ?></textarea>
                <div class="hint">برای ایمیل‌کلاینت‌هایی که HTML را نمایش نمی‌دهند</div>
            </div>
        </div>

        <div class="actions-bar">
            <button type="submit" name="action" value="save" class="btn btn-primary">
                <i class="bi bi-save"></i> ذخیره
            </button>

            <?php if ($campaign && $campaign->status()->canSend()): ?>
                <a href="<?= nl_admin_url('campaign-send.php?id=' . $campaign->id()) ?>" class="btn btn-success">
                    <i class="bi bi-send"></i> ارسال
                </a>
            <?php endif; ?>

            <?php if ($campaign && in_array($campaign->status(), [CampaignStatus::DRAFT, CampaignStatus::SCHEDULED], true)): ?>
                <div style="display:flex; gap:8px; align-items:center;">
                    <input type="datetime-local" name="scheduled_at"
                           value="<?= $campaign->scheduledAt()?->format('Y-m-d\TH:i') ?>"
                           style="padding:10px; border:2px solid #e2e8f0; border-radius:8px; font-family:inherit;">
                    <button type="submit" name="action" value="schedule" class="btn btn-warning">
                        <i class="bi bi-clock"></i> زمان‌بندی
                    </button>
                </div>
            <?php endif; ?>

            <?php if ($isEdit && $campaign && !$campaign->status()->isTerminal()): ?>
                <button type="submit" name="action" value="delete" class="btn btn-danger" style="margin-right:auto;"
                        onclick="return confirm('حذف کمپین #<?= $campaign->id() ?>؟ این عمل قابل بازگشت نیست.');">
                    <i class="bi bi-trash"></i> حذف
                </button>
            <?php endif; ?>
        </div>
    </form>

</div>

<script>
tinymce.init({
    selector: '#body_html',
    directionality: 'rtl',
    language: 'fa',
    height: 500,
    menubar: false,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons'
    ],
    toolbar: 'undo redo | blocks | ' +
             'bold italic forecolor backcolor | alignleft aligncenter ' +
             'alignright alignjustify | bullist numlist outdent indent | ' +
             'link image media table emoticons | ' +
             'removeformat code fullscreen preview help',
    content_style: 'body { font-family: Tahoma, Vazirmatn, sans-serif; font-size: 14px; line-height: 1.7; }',
    branding: false,
    promotion: false,
    images_upload_url: '<?= nl_admin_url('upload-image.php') ?>',
    automatic_uploads: true,
    file_picker_types: 'image',
    valid_elements: '*[*]',
    extended_valid_elements: '*[*]',
    verify_html: false,
    placeholder: 'محتوای خبرنامه را اینجا بنویسید... (مثلاً: سلام {نام}، این هفته خبرهای تازه‌ای داریم)',
    setup: function (editor) {
        editor.on('change', function () {
            editor.save();
        });
    }
});
</script>

</body>
</html>
