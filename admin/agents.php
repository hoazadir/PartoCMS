<?php
// admin/agents.php — پنل اعتبارسنجی مشاوران
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['agent_id'])) {
    if (!hash_equals($_SESSION['admin_csrf'], $_POST['_csrf'] ?? '')) {
        $error = 'توکن امنیتی نامعتبر است.';
    } else {
        $agentId = (int) $_POST['agent_id'];
        try {
            if ($_POST['action'] === 'approve') {
                $stmt = $pdo->prepare("UPDATE agent_profiles SET status = 'approved', verified_by = ?, verified_at = NOW() WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $agentId]);
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = (SELECT user_id FROM agent_profiles WHERE id = ?)");
                $stmt->execute([$agentId]);
                $success = 'مشاور تأیید شد و حساب کاربری فعال گردید.';
            } elseif ($_POST['action'] === 'reject') {
                $reason = trim((string)($_POST['rejection_reason'] ?? ''));
                if ($reason === '') {
                    $error = 'دلیل رد الزامی است.';
                } else {
                    $stmt = $pdo->prepare("UPDATE agent_profiles SET status = 'rejected', rejection_reason = ?, verified_by = ?, verified_at = NOW() WHERE id = ?");
                    $stmt->execute([$reason, $_SESSION['user_id'], $agentId]);
                    $success = 'درخواست مشاور رد شد.';
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
    $where = 'WHERE ap.status = ?';
    $params[] = $statusFilter;
}

$sql = "SELECT ap.*, u.username, u.email, u.phone AS user_phone
        FROM agent_profiles ap
        INNER JOIN users u ON u.id = ap.user_id
        {$where}
        ORDER BY ap.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf = $_SESSION['admin_csrf'];
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<div class="main">
    <h2 class="mb-4">👤 مدیریت مشاوران املاک</h2>

    <?php if ($success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div>
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

    <?php if (empty($agents)): ?>
        <div class="alert alert-info">هیچ مشاوری در این وضعیت یافت نشد.</div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($agents as $a): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($a['first_name'] . ' ' . $a['last_name']) ?></strong>
                            <?php
                            $statusMap = [
                                'pending'  => ['warning', 'در انتظار'],
                                'approved' => ['success', 'تأیید شده'],
                                'rejected' => ['danger', 'رد شده'],
                            ];
                            $st = $statusMap[$a['status']] ?? ['secondary', $a['status']];
                            ?>
                            <span class="badge bg-<?= $st[0] ?>"><?= $st[1] ?></span>
                        </div>
                        <div class="card-body">
                            <p class="mb-1 small">🏢 <strong><?= htmlspecialchars($a['agency_name']) ?></strong></p>
                            <p class="mb-1 small">👤 مدیر: <?= htmlspecialchars($a['agency_manager'] ?? '—') ?></p>
                            <p class="mb-1 small">📞 <?= htmlspecialchars($a['phone']) ?></p>
                            <p class="mb-1 small">🆔 کد ملی: <?= htmlspecialchars($a['national_code']) ?></p>
                            <p class="mb-3 small">📜 پروانه: <?= htmlspecialchars($a['agency_license_no']) ?></p>

                            <details>
                                <summary class="text-primary" style="cursor:pointer;">📎 مشاهده مدارک</summary>
                                <div class="row g-2 mt-2">
                                    <?php
                                    $docs = [
                                        'avatar'                => 'تصویر پرسنلی',
                                        'national_card_front'   => 'روی کارت ملی',
                                        'national_card_back'    => 'پشت کارت ملی',
                                        'agency_license_image'  => 'پروانه کسب',
                                        'manager_photo'         => 'تصویر مدیر',
                                        'contract_image'        => 'قرارداد',
                                        'social_security_image' => 'تامین اجتماعی',
                                    ];
                                    foreach ($docs as $field => $label):
                                        if (empty($a[$field])) continue;
                                    ?>
                                        <div class="col-6">
                                            <small class="text-muted d-block"><?= $label ?></small>
                                            <a href="<?= htmlspecialchars($a[$field]) ?>" target="_blank">
                                                <img src="<?= htmlspecialchars($a[$field]) ?>" style="width:100%;height:60px;object-fit:cover;border-radius:4px;">
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>

                            <?php if ($a['status'] === 'rejected' && !empty($a['rejection_reason'])): ?>
                                <div class="alert alert-danger mt-2 py-1 px-2 small">دلیل رد: <?= htmlspecialchars($a['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="edit-agent.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-outline-primary">✏️ ویرایش مدارک</a>

                            <?php if ($a['status'] === 'pending'): ?>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="agent_id" value="<?= $a['id'] ?>">
                                    <button type="button" class="btn btn-sm btn-success" onclick="showConfirm('آیا از تایید این مشاور مطمئن هستید؟', () => { this.closest('form').submit(); });">✅ تأیید</button>
                                </form>
                                <form method="post" style="display:inline-block;" onsubmit="event.preventDefault(); showConfirm('آیا از رد این مشاور مطمئن هستید؟', () => { this.submit(); });">
                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="agent_id" value="<?= $a['id'] ?>">
                                    <input type="text" name="rejection_reason" class="form-control form-control-sm mt-1" placeholder="دلیل رد..." required>
                                    <button type="submit" class="btn btn-sm btn-danger">❌ رد</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
