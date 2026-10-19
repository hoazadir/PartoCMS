<?php
require_once __DIR__ . '/../config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }

$userId = $_SESSION['user_id'];
$pdo = getDB();

$stmt = $pdo->prepare("SELECT * FROM shop_orders WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$siteName = getSetting('site_name', 'وب‌سایت من');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سفارش‌های من | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { width: 250px; background: #2c3e50; color: #fff; position: fixed; height: 100vh; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #34495e; border-right: 4px solid #3498db; }
        .main { margin-right: 250px; padding: 25px; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 10px; }
        .card-header { background: #fff; border-bottom: 1px solid #eee; font-weight: bold; }
        .accordion-button:not(.collapsed) { background-color: #e8f4fd; color: #0d6efd; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar .brand small, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><h5>👤 پنل کاربری</h5><small><?= htmlspecialchars($siteName) ?></small></div>
    <a href="index.php"><i class="bi bi-speedometer2"></i> <span>داشبورد</span></a>
    <a href="profile.php"><i class="bi bi-person"></i> <span>پروفایل</span></a>
    <a href="password.php"><i class="bi bi-key"></i> <span>تغییر رمز</span></a>
    <a href="posts.php"><i class="bi bi-file-text"></i> <span>مقالات من</span></a>
    <a href="comments.php"><i class="bi bi-chat-dots"></i> <span>دیدگاه‌های من</span></a>
    <a href="orders.php" class="active"><i class="bi bi-bag-check"></i> <span>سفارش‌های من</span></a>
    <hr style="border-color:#34495e;margin:5px 0;">
    <a href="../index.php"><i class="bi bi-house"></i> <span>صفحه اصلی</span></a>
    <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> <span>خروج</span></a>
</div>

<div class="main">
    <h4 class="mb-4">🛒 سفارش‌های من</h4>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">شما تاکنون هیچ سفارشی ثبت نکرده‌اید.</div>
    <?php else: ?>
        <div class="accordion" id="ordersAccordion">
            <?php foreach ($orders as $index => $order): ?>
                <div class="accordion-item mb-2 card">
                    <h2 class="accordion-header" id="heading<?= $order['id'] ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $order['id'] ?>" aria-expanded="false">
                            <div class="d-flex w-100 justify-content-between align-items-center pe-3">
                                <span><strong>سفارش #<?= htmlspecialchars($order['order_number']) ?></strong></span>
                                <span class="text-muted ms-3"><?= date('Y/m/d', strtotime($order['created_at'])) ?></span>
                                <span class="badge bg-primary ms-auto"><?= number_format($order['total']) ?> <?= htmlspecialchars($order['currency'] ?? 'تومان') ?></span>
                            </div>
                        </button>
                    </h2>
                    <div id="collapse<?= $order['id'] ?>" class="accordion-collapse collapse" data-bs-parent="#ordersAccordion">
                        <div class="accordion-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>وضعیت سفارش:</strong> <?= htmlspecialchars($order['status']) ?><br>
                                    <strong>وضعیت پرداخت:</strong> <?= htmlspecialchars($order['payment_status']) ?>
                                </div>
                                <div class="col-md-6 text-start">
                                    <?php if (!empty($order['tracking_code'])): ?>
                                        <strong>کد رهگیری:</strong> <?= htmlspecialchars($order['tracking_code']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>محصول / خدمت</th>
                                        <th>قیمت واحد</th>
                                        <th>تعداد</th>
                                        <th>جمع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmtItems = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
                                    $stmtItems->execute([$order['id']]);
                                    $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($items as $item):
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= number_format($item['product_price']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td><?= number_format($item['subtotal']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
