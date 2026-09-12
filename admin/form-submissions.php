<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
$perm->require('content.view');

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$formId = (int) ($_GET['form_id'] ?? 0);

if (!$formId) {
    header('Location: forms.php');
    exit;
}

// اطلاعات فرم
$stmt = $pdo->prepare("SELECT * FROM forms WHERE id = ?");
$stmt->execute([$formId]);
$form = $stmt->fetch();
if (!$form) {
    header('Location: forms.php');
    exit;
}

$fields = json_decode($form['fields'] ?? '[]', true) ?: [];

// خروجی CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("SELECT * FROM form_submissions WHERE form_id = ? ORDER BY created_at DESC");
    $stmt->execute([$formId]);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="form-' . $form['slug'] . '-' . date('Ymd') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM برای UTF-8

    // هدر
    $headers = ['#', 'تاریخ'];
    foreach ($fields as $f) $headers[] = $f['label'] ?? $f['name'];
    $headers[] = 'IP';
    echo implode(',', array_map(fn($h) => '"' . str_replace('"', '""', $h) . '"', $headers)) . "\n";

    foreach ($rows as $i => $r) {
        $data = json_decode($r['data'], true) ?: [];
        $line = [$i + 1, date('Y/m/d H:i', strtotime($r['created_at']))];
        foreach ($fields as $f) $line[] = $data[$f['name']] ?? '';
        $line[] = $r['ip_address'];
        echo implode(',', array_map(fn($c) => '"' . str_replace('"', '""', $c) . '"', $line)) . "\n";
    }
    exit;
}

// حذف
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM form_submissions WHERE id = ? AND form_id = ?")->execute([$_GET['delete'], $formId]);
    header('Location: form-submissions.php?form_id=' . $formId . '&msg=deleted');
    exit;
}

// تغییر وضعیت
if (isset($_GET['status']) && isset($_GET['id'])) {
    $allowed = ['new', 'read', 'replied', 'archived'];
    if (in_array($_GET['status'], $allowed)) {
        $pdo->prepare("UPDATE form_submissions SET status = ? WHERE id = ? AND form_id = ?")
            ->execute([$_GET['status'], $_GET['id'], $formId]);
    }
    header('Location: form-submissions.php?form_id=' . $formId);
    exit;
}

$success = isset($_GET['msg']) && $_GET['msg'] === 'deleted' ? 'ارسال حذف شد' : '';

// لیست ارسال‌ها
$stmt = $pdo->prepare("SELECT * FROM form_submissions WHERE form_id = ? ORDER BY created_at DESC");
$stmt->execute([$formId]);
$submissions = $stmt->fetchAll();

$statusLabels = [
    'new' => ['label' => 'جدید', 'class' => 'danger'],
    'read' => ['label' => 'خوانده شده', 'class' => 'warning'],
    'replied' => ['label' => 'پاسخ داده', 'class' => 'success'],
    'archived' => ['label' => 'بایگانی', 'class' => 'secondary'],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ارسال‌های <?= htmlspecialchars($form['name']) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .submission-card { background: #fff; border-radius: 10px; padding: 20px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); border-right: 4px solid #ccc; }
        .submission-card.new { border-right-color: #e74c3c; }
        .submission-card.read { border-right-color: #f39c12; }
        .submission-card.replied { border-right-color: #27ae60; }
        .submission-card.archived { border-right-color: #95a5a6; }
        .submission-data { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0; }
        .submission-data .row-data { display: flex; padding: 6px 0; border-bottom: 1px dashed #e0e0e0; }
        .submission-data .row-data:last-child { border-bottom: none; }
        .submission-data .label { font-weight: bold; color: #34495e; min-width: 130px; font-size: 13px; }
        .submission-data .value { color: #2c3e50; font-size: 13px; flex: 1; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <div>
            <h4 style="margin:0;">📨 ارسال‌های فرم: <?= htmlspecialchars($form['name']) ?></h4>
            <small class="text-muted"><?= count($submissions) ?> ارسال</small>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="?form_id=<?= $formId ?>&export=csv" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-excel"></i> خروجی Excel</a>
            <a href="forms.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right"></i> بازگشت</a>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>

    <?php if (empty($submissions)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-inbox fs-1 d-block mb-3"></i>
            <h5>هنوز ارسالی دریافت نشده</h5>
            <a href="../form.php?slug=<?= urlencode($form['slug']) ?>" class="btn btn-primary mt-3" target="_blank">
                <i class="bi bi-eye"></i> مشاهده فرم
            </a>
        </div>
    <?php else: ?>
        <?php foreach ($submissions as $s): ?>
            <?php $st = $statusLabels[$s['status']] ?? ['label' => $s['status'], 'class' => 'secondary']; ?>
            <div class="submission-card <?= htmlspecialchars($s['status']) ?>">
                <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
                    <div>
                        <strong>#<?= $s['id'] ?></strong>
                        <span class="badge bg-<?= $st['class'] ?> ms-2"><?= $st['label'] ?></span>
                        <small class="text-muted ms-2">📅 <?= date('Y/m/d H:i', strtotime($s['created_at'])) ?></small>
                        <small class="text-muted ms-2">| IP: <code><?= htmlspecialchars($s['ip_address']) ?></code></small>
                    </div>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <?php if ($s['status'] !== 'read'): ?>
                            <a href="?form_id=<?= $formId ?>&id=<?= $s['id'] ?>&status=read" class="btn btn-sm btn-warning">📖 خوانده</a>
                        <?php endif; ?>
                        <?php if ($s['status'] !== 'replied'): ?>
                            <a href="?form_id=<?= $formId ?>&id=<?= $s['id'] ?>&status=replied" class="btn btn-sm btn-success">✓ پاسخ</a>
                        <?php endif; ?>
                        <?php if ($s['status'] !== 'archived'): ?>
                            <a href="?form_id=<?= $formId ?>&id=<?= $s['id'] ?>&status=archived" class="btn btn-sm btn-secondary">📦 بایگانی</a>
                        <?php endif; ?>
                        <a href="?form_id=<?= $formId ?>&delete=<?= $s['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('حذف؟')">🗑</a>
                    </div>
                </div>

                <div class="submission-data">
                    <?php
                    $data = json_decode($s['data'], true) ?: [];
                    foreach ($fields as $f):
                        $val = $data[$f['name']] ?? '—';
                    ?>
                        <div class="row-data">
                            <div class="label"><?= htmlspecialchars($f['label'] ?? $f['name']) ?>:</div>
                            <div class="value"><?= nl2br(htmlspecialchars($val)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
