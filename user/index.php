<?php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];

// اطلاعات کاربر
$stmt = $pdo->prepare("
    SELECT u.*, r.name as role_name, r.slug as role_slug
    FROM users u
    LEFT JOIN roles r ON r.id = u.role_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ../logout.php');
    exit;
}

// آمار کاربر (مقاله، دیدگاه، سفارش، مجموع خرید)
$stats = [
    'posts' => (int) $pdo->query("SELECT COUNT(*) FROM content_items WHERE author_id = {$userId}")->fetchColumn(),
    'comments' => (int) $pdo->query("SELECT COUNT(*) FROM comments WHERE user_id = {$userId}")->fetchColumn(),
];

$stmtOrders = $pdo->prepare("SELECT COUNT(*) as total_orders, COALESCE(SUM(total),0) as total_spent FROM shop_orders WHERE user_id = ?");
$stmtOrders->execute([$userId]);
$orderStats = $stmtOrders->fetch();
$stats['orders'] = (int)$orderStats['total_orders'];
$stats['spent'] = (float)$orderStats['total_spent'];

// آخرین ۳ سفارش
$stmtRecent = $pdo->prepare("SELECT * FROM shop_orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
$stmtRecent->execute([$userId]);
$recentOrders = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

$siteName = getSetting('site_name', 'وب‌سایت من');
$pageTitle = 'داشبورد کاربری';
?>

<?php require_once 'includes/header.php'; ?>

<style>
    .hero-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .hero-avatar {
        width: 90px; height: 90px;
        background: rgba(255,255,255,0.2);
        border: 3px solid rgba(255,255,255,0.5);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 36px; font-weight: bold;
        backdrop-filter: blur(5px);
    }
    .stat-card {
        background: #fff;
        border-radius: 15px;
        padding: 20px;
        display: flex; align-items: center; gap: 15px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        transition: transform 0.3s;
        border: 1px solid #f0f0f0;
    }
    .stat-card:hover { transform: translateY(-5px); }
    .stat-icon {
        width: 55px; height: 55px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 24px; color: #fff;
    }
    .stat-info h3 { margin: 0; font-size: 22px; font-weight: bold; color: #333; }
    .stat-info p { margin: 0; font-size: 13px; color: #777; }
    
    .action-card {
        display: block; text-align: center; padding: 20px;
        background: #fff; border-radius: 15px; text-decoration: none;
        color: #333; border: 1px solid #f0f0f0;
        transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
    }
    .action-card:hover {
        background: #f8f9ff; color: #667eea;
        transform: translateY(-5px); border-color: #667eea;
    }
    .action-card i { font-size: 28px; margin-bottom: 10px; display: block; color: #667eea; }
    .action-card h6 { margin: 0; font-weight: bold; font-size: 15px; }

    


    .hero-avatar {
        position: relative; cursor: pointer;
        width: 90px !important; height: 90px !important;
        display: flex; align-items: center; justify-content: center;
        background: #f0f0f0; border: 3px solid #fff;
        border-radius: 50%; overflow: hidden;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        transition: transform 0.3s;
    }
    .hero-avatar:hover { transform: scale(1.05); }
    
    #avatar-img {
        width: 100% !important; height: 100% !important;
        object-fit: cover !important;
        margin: 0 !important; padding: 0 !important;
        display: block;
    }
    
    .avatar-overlay {
        position: absolute; top:0; left:0; right:0; bottom:0;
        background: rgba(0,0,0,0.4); display: flex;
        align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s;
        color: #fff; font-size: 24px; z-index: 10;
    }
    .hero-avatar:hover .avatar-overlay { opacity: 1; }
    
</style>

<!-- Hero Banner -->
<div class="hero-card mb-4 d-flex align-items-center gap-4">
    <div class="hero-avatar" id="avatar-container" title="برای تغییر عکس کلیک کنید">
        <img id="avatar-img" src="<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : '' ?>" 
             style="<?= !empty($user['avatar']) ? 'display:block;width:100%;height:100%;border-radius:50%;object-fit:cover;' : 'display:none;' ?>">
        <span id="avatar-letter" style="<?= !empty($user['avatar']) ? 'display:none;' : '' ?>">
            <?= mb_substr($user['username'], 0, 1) ?>
        </span>
        <div class="avatar-overlay">
            <i class="bi bi-camera"></i>
        </div>
    </div>
    <input type="file" id="avatar-input" accept="image/*" style="display: none;">
    <div>
        <h3 class="mb-1">خوش آمدید، <?= htmlspecialchars($user['username']) ?> 👋</h3>
        <p class="mb-1" style="opacity:0.85; font-size: 14px;"><?= htmlspecialchars($user['email']) ?> | <?= htmlspecialchars($user['role_name'] ?? 'کاربر') ?></p>
        <?php if (!empty($user['last_login'])): ?>
            <small style="opacity:0.7; font-size: 12px;"><i class="bi bi-clock"></i> آخرین ورود: <?= date('Y/m/d H:i', strtotime($user['last_login'])) ?></small>
        <?php endif; ?>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe);"><i class="bi bi-bag-check"></i></div>
            <div class="stat-info"><h3><?= number_format($stats['orders']) ?></h3><p>سفارش</p></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#43e97b,#38f9d7);"><i class="bi bi-cash-stack"></i></div>
            <div class="stat-info"><h3><?= number_format($stats['spent']) ?></h3><p>مجموع خرید (تومان)</p></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#667eea,#764ba2);"><i class="bi bi-file-text"></i></div>
            <div class="stat-info"><h3><?= number_format($stats['posts']) ?></h3><p>مقاله</p></div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="stat-card">
            <div class="stat-icon" style="background:linear-gradient(135deg,#fa709a,#fee140);"><i class="bi bi-chat-dots"></i></div>
            <div class="stat-info"><h3><?= number_format($stats['comments']) ?></h3><p>دیدگاه</p></div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recent Orders -->
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold"><i class="bi bi-clock-history text-primary"></i> آخرین سفارشات</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-primary rounded-pill">مشاهده همه</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentOrders)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-cart-x fs-1 mb-3 d-block opacity-50"></i>
                        <p>شما هنوز سفارشی ثبت نکرده‌اید.</p>
                        <a href="../shop.php" class="btn btn-primary rounded-pill mt-2">رفتن به فروشگاه</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>شماره سفارش</th>
                                    <th>تاریخ</th>
                                    <th>مبلغ</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><span class="fw-bold">#<?= htmlspecialchars($order['order_number']) ?></span></td>
                                    <td><?= date('Y/m/d', strtotime($order['created_at'])) ?></td>
                                    <td><?= number_format($order['total']) ?> <?= htmlspecialchars($order['currency']) ?></td>
                                    <td>
                                        <?php
                                        $statusMap = [
                                            'pending' => ['در انتظار', 'warning'],
                                            'processing' => ['در حال پردازش', 'info'],
                                            'shipped' => ['ارسال شده', 'primary'],
                                            'completed' => ['تکمیل شده', 'success'],
                                            'cancelled' => ['لغو شده', 'danger'],
                                        ];
                                        $st = $statusMap[$order['status']] ?? [$order['status'], 'secondary'];
                                        ?>
                                        <span class="badge bg-<?= $st[1] ?> rounded-pill"><?= $st[0] ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="row g-3">
            <div class="col-6">
                <a href="profile.php" class="action-card">
                    <i class="bi bi-person-circle"></i>
                    <h6>پروفایل</h6>
                </a>
            </div>
            <div class="col-6">
                <a href="password.php" class="action-card">
                    <i class="bi bi-key-fill"></i>
                    <h6>تغییر رمز</h6>
                </a>
            </div>
            <div class="col-6">
                <a href="posts.php" class="action-card">
                    <i class="bi bi-file-earmark-text"></i>
                    <h6>مقالات من</h6>
                </a>
            </div>
            <div class="col-6">
                <a href="comments.php" class="action-card">
                    <i class="bi bi-chat-square-text"></i>
                    <h6>دیدگاه‌ها</h6>
                </a>
            </div>
        </div>
    </div>
</div>

</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('avatar-container');
        const input = document.getElementById('avatar-input');
        const img = document.getElementById('avatar-img');
        const letter = document.getElementById('avatar-letter');
        
        if (container && input) {
            container.addEventListener('click', () => input.click());
            
            input.addEventListener('change', async function() {
                if (!this.files.length) return;
                const file = this.files[0];
                
                const reader = new FileReader();
                reader.onload = (e) => {
                    img.src = e.target.result;
                    img.style.display = 'block';
                    if(letter) letter.style.display = 'none';
                };
                reader.readAsDataURL(file);

                const fd = new FormData();
                fd.append('avatar', file);
                // گرفتن توکن امنیتی از فرم یا متغیرهای سراسری اگر وجود دارد
                const csrfToken = document.querySelector('input[name="_csrf"]')?.value || '<?= $_SESSION['csrf_token'] ?? '' ?>';
                fd.append('_csrf', csrfToken);
                
                try {
                    const res = await fetch('ajax/upload-avatar.php', { method: 'POST', body: fd });
                    const text = await res.text(); // خواندن به صورت متن برای جلوگیری از خطای JSON Parse
                    
                    try {
                        const data = JSON.parse(text);
                        if (!data.success) {
                            alert('خطای سرور: ' + data.message);
                        }
                    } catch (e) {
                        alert('پاسخ نامعتبر از سرور. لطفا کنسول را بررسی کنید.');
                        console.error('Server response:', text);
                    }
                } catch (err) {
                    alert('خطای شبکه: ' + err.message);
                }
            });
        }
    });
    </script>
    
    
</body>
</html>
