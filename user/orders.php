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


<?php require_once 'includes/header.php'; ?>


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


</div> <!-- end main-content -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
