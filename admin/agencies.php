<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$success = '';
$error = '';

// ─── پردازش تأیید/رد ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['agency_id'])) {
    if (!hash_equals($_SESSION['admin_csrf'], $_POST['_csrf'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $agencyId = (int) $_POST['agency_id'];
        try {
            if ($_POST['action'] === 'approve') {
                // ۱. تأیید بنگاه
                $stmt = $pdo->prepare("UPDATE agencies SET status = 'approved', verified_by = ?, verified_at = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $agencyId]);

                // ۲. دریافت user_id از بنگاه
                $stmt = $pdo->prepare("SELECT user_id FROM agencies WHERE id = ?");
                $stmt->execute([$agencyId]);
                $userId = (int)$stmt->fetchColumn();

                // ۳. دریافت role_id نقش «مدیر بنگاه»
                $stmt = $pdo->prepare("SELECT id FROM roles WHERE slug = 'agency_manager' LIMIT 1");
                $stmt->execute();
                $managerRoleId = (int)$stmt->fetchColumn();

                // ۴. اعطای نقش + فعال‌سازی
                if ($userId > 0 && $managerRoleId > 0) {
                    $stmt = $pdo->prepare("UPDATE users SET is_active = 1, role_id = ? WHERE id = ?");
                    $stmt->execute([$managerRoleId, $userId]);
                }

                $success = 'بنگاه تأیید شد. نقش «مدیر بنگاه» به کاربر اعطا گردید.';
            } elseif ($_POST['action'] === 'reject') {
                $reason = trim((string)($_POST['rejection_reason'] ?? ''));
                if ($reason === '') {
                    $error = 'دلیل رد الزامی است.';
                } else {
                    $stmt = $pdo->prepare("UPDATE agencies SET status = 'rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
                    $stmt->execute([$reason, $_SESSION['user_id'], $agencyId]);
                    $success = 'درخواست بنگاه رد شد.';
                }
            }
        } catch (Throwable $e) {
            $error = 'خطا: ' . $e->getMessage();
        }
    }
}

// ─── فیلتر ───
$statusFilter = $_GET['status'] ?? '';
$where = '';
$params = [];
if (in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
    $where = 'WHERE a.status = ?';
    $params[] = $statusFilter;
}

$sql = "SELECT a.*, u.username, u.email, u.is_active, c.name AS city_name
        FROM agencies a
        INNER JOIN users u ON u.id = a.user_id
        LEFT JOIN cities c ON c.id = a.city_id
        {$where}
        ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agencies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = $_SESSION['admin_csrf'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<style>
    .agency-card { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 4px 20px rgba(0,0,0,0.06); margin-bottom:20px; }
    .agency-card-header { padding:16px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color:#fff; display:flex; justify-content:space-between; align-items:center; }
    .agency-card-body { padding:20px; }
    .agency-info-row { display:flex; justify-content:space-between; padding:6px 0; font-size:14px; border-bottom:1px dashed #eef0f2; }
    .agency-info-row:last-child { border-bottom:none; }
    .agency-doc-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:10px; margin-top:12px; }
    .agency-doc-item { border:1px solid #eef0f2; border-radius:8px; padding:8px; text-align:center; }
    .agency-doc-item img { width:100%; height:80px; object-fit:cover; border-radius:6px; }
    .agency-doc-item small { display:block; margin-top:4px; font-size:11px; color:#64748b; }
</style>

<div class="main">
    <h2 class="mb-4">🏢 مدیریت بنگاه‌های املاک</h2>

    <?php if ($success): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($success, JSON_UNESCAPED_UNICODE) ?>, 'success', 5000));</script>
    <?php endif; ?>
    <?php if ($error): ?>
        <script>document.addEventListener('DOMContentLoaded', () => showToast(<?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>, 'error', 8000));</script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small">فیلتر وضعیت</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">همه</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>در انتظار</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>تأیید شده</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>رد شده</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">🔍 فیلتر</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($agencies)): ?>
        <div class="alert alert-info">هیچ بنگاهی در این وضعیت یافت نشد.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($agencies as $a): ?>
                <div class="col-md-6">
                    <div class="agency-card">
                        <div class="agency-card-header">
                            <div>
                                <strong><?= htmlspecialchars($a['name']) ?></strong><br>
                                <small>پروانه: <?= htmlspecialchars($a['license_no']) ?></small>
                            </div>
                            <?php
                            $statusMap = ['pending' => ['warning', 'در انتظار'], 'approved' => ['success', 'تأیید شده'], 'rejected' => ['danger', 'رد شده']];
                            $st = $statusMap[$a['status']] ?? ['secondary', $a['status']];
                            ?>
                            <span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span>
                        </div>
                        <div class="agency-card-body">
                            <div class="agency-info-row"><span>مدیر:</span><span><?= htmlspecialchars($a['owner_name']) ?></span></div>
                            <div class="agency-info-row"><span>کد ملی:</span><span><?= htmlspecialchars($a['owner_national_code']) ?></span></div>
                            <div class="agency-info-row"><span>تلفن:</span><span dir="ltr"><?= htmlspecialchars($a['phone']) ?></span></div>
                            <div class="agency-info-row"><span>شهر:</span><span><?= htmlspecialchars($a['city_name'] ?? '—') ?></span></div>
                            <div class="agency-info-row"><span>آدرس:</span><span><?= htmlspecialchars($a['address']) ?></span></div>
                            
                            <div class="agency-doc-grid">
                                <?php
                                $docs = ['license_image' => 'پروانه', 'owner_id_front' => 'کارت ملی (رو)', 'owner_id_back' => 'کارت ملی (پشت)', 'deed_image' => 'سند/اجاره'];
                                foreach ($docs as $field => $label):
                                    if (empty($a[$field])) continue;
                                ?>
                                    <div class="agency-doc-item">
                                        <a href="<?= htmlspecialchars($a[$field]) ?>" target="_blank">
                                            <img src="<?= htmlspecialchars($a[$field]) ?>" alt="<?= $label ?>">
                                        </a>
                                        <small><?= $label ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (!empty($a['latitude']) && !empty($a['longitude'])): ?>
                                <div class="mt-3">
                                    <a href="https://www.openstreetmap.org/?mlat=<?= $a['latitude'] ?>&mlon=<?= $a['longitude'] ?>#map=15/<?= $a['latitude'] ?>/<?= $a['longitude'] ?>" target="_blank" class="btn btn-sm btn-outline-info w-100">
                                        📍 مشاهده روی نقشه
                                    </a>
                                </div>
                            <?php endif; ?>

                            <?php if ($a['status'] === 'rejected' && !empty($a['rejection_reason'])): ?>
                                <div class="alert alert-danger mt-2 py-1 px-2 small">دلیل رد: <?= htmlspecialchars($a['rejection_reason']) ?></div>
                            <?php endif; ?>

                            <div class="mt-3 d-flex gap-2">
                                <a href="edit-agency.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">✏️ ویرایش</a>
                                <?php if ($a['status'] === 'pending'): ?>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <input type="hidden" name="agency_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-success" onclick="showConfirm('تأیید شود؟', () => this.closest('form').submit()); return false;">✅ تأیید</button>
                                    </form>
                                    <form method="post" style="display:inline-block;" onsubmit="event.preventDefault(); showConfirm('رد شود؟', () => this.submit());">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <input type="hidden" name="agency_id" value="<?= $a['id'] ?>">
                                        <input type="text" name="rejection_reason" class="form-control form-control-sm mt-1" placeholder="دلیل رد..." required>
                                        <button type="submit" class="btn btn-sm btn-danger">❌ رد</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
