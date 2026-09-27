<?php
/**
 * PartoCMS - Invoice Generator
 * تولید فاکتور PDF سفارش
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoiceGenerator
{
    private PDO $pdo;
    private OrderManager $orderManager;
    private ShopManager $shop;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->orderManager = new OrderManager($pdo);
        $this->shop = new ShopManager($pdo);
    }

    /**
     * تولید HTML فاکتور
     */
    public function renderHtml(int $orderId): string
    {
        $order = $this->orderManager->getById($orderId);
        if (!$order) {
            throw new Exception('سفارش یافت نشد');
        }

        $items = $this->orderManager->getItems($orderId);
        $siteName = getSetting('site_name', 'وب‌سایت من');
        $siteUrl = SITE_URL;

        // تولید HTML
        $html = '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>فاکتور ' . htmlspecialchars($order['order_number']) . '</title>
    <style>
        @font-face {
            font-family: "Vazirmatn";
            src: url("' . $siteUrl . '/assets/fonts/Vazirmatn-Regular.ttf") format("truetype");
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: "Vazirmatn";
            src: url("' . $siteUrl . '/assets/fonts/Vazirmatn-Bold.ttf") format("truetype");
            font-weight: bold;
            font-style: normal;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Vazirmatn", Tahoma, sans-serif;
            direction: rtl;
            text-align: right;
            background: #fff;
            color: #333;
            font-size: 12px;
            margin: 0;
            padding: 25px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #06b6d4;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .site-info h1 {
            color: #06b6d4;
            margin: 0 0 5px 0;
            font-size: 24px;
        }
        .site-info p {
            margin: 2px 0;
            color: #64748b;
            font-size: 11px;
        }
        .invoice-title {
            text-align: left;
        }
        .invoice-title h2 {
            color: #0f172a;
            margin: 0 0 5px 0;
            font-size: 20px;
        }
        .invoice-title p {
            margin: 2px 0;
            font-size: 11px;
            color: #64748b;
        }
        .invoice-title .order-number {
            background: #f0f9ff;
            padding: 6px 12px;
            border-radius: 6px;
            font-family: monospace;
            color: #0891b2;
            font-weight: bold;
            display: inline-block;
            margin-top: 5px;
        }
        .customer-info {
            display: flex;
            justify-content: space-between;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 11px;
        }
        .customer-info .box {
            flex: 1;
        }
        .customer-info .box h4 {
            color: #0f172a;
            margin: 0 0 8px 0;
            font-size: 13px;
            border-bottom: 2px solid #06b6d4;
            padding-bottom: 5px;
            display: inline-block;
        }
        .customer-info .box p {
            margin: 3px 0;
            color: #475569;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        table th {
            background: #06b6d4;
            color: #fff;
            padding: 10px;
            text-align: right;
            font-size: 11px;
            font-weight: bold;
        }
        table th:first-child { border-radius: 0 8px 0 0; }
        table th:last-child { border-radius: 8px 0 0 0; }
        table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11px;
        }
        table tr:last-child td { border-bottom: none; }
        table tr:nth-child(even) td { background: #f8fafc; }
        .totals {
            margin-right: auto;
            width: 300px;
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 12px;
        }
        .totals .row.discount { color: #10b981; }
        .totals .row.grand-total {
            border-top: 2px solid #06b6d4;
            padding-top: 10px;
            margin-top: 5px;
            font-size: 15px;
            font-weight: bold;
            color: #0891b2;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 10px;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
        }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-processing { background: #cffafe; color: #155e75; }
        .status-shipped { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #d1fae5; color: #065f46; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-unpaid { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>

<div class="invoice-header">
    <div class="site-info">
        <h1>' . htmlspecialchars($siteName) . '</h1>
        <p>' . htmlspecialchars($siteUrl) . '</p>
    </div>
    <div class="invoice-title">
        <h2>فاکتور فروش</h2>
        <p>تاریخ: ' . date('Y/m/d H:i', strtotime($order['created_at'])) . '</p>
        <div class="order-number">' . htmlspecialchars($order['order_number']) . '</div>
    </div>
</div>

<div class="customer-info">
    <div class="box">
        <h4>اطلاعات مشتری</h4>
        ' . (!empty($order['shipping_address']) ? '<p>' . nl2br(htmlspecialchars($order['shipping_address'])) . '</p>' : '<p>—</p>') . '
    </div>
    <div class="box" style="text-align: left;">
        <h4>وضعیت</h4>
        <p>سفارش: <span class="status-badge status-' . htmlspecialchars($order['status']) . '">' . $this->getStatusLabel($order['status']) . '</span></p>
        <p>پرداخت: <span class="status-badge status-' . htmlspecialchars($order['payment_status']) . '">' . $this->getPaymentLabel($order['payment_status']) . '</span></p>
        ' . (!empty($order['payment_method']) ? '<p>روش پرداخت: ' . htmlspecialchars($order['payment_method']) . '</p>' : '') . '
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 40px;">#</th>
            <th>نام محصول</th>
            <th style="width: 100px;">قیمت واحد</th>
            <th style="width: 60px;">تعداد</th>
            <th style="width: 100px;">جمع</th>
        </tr>
    </thead>
    <tbody>';

        $i = 1;
        foreach ($items as $item) {
            $html .= '<tr>
                <td>' . $i++ . '</td>
                <td>' . htmlspecialchars($item['product_name']) . '</td>
                <td>' . $this->shop->formatPrice((float) $item['product_price']) . '</td>
                <td>' . (int) $item['quantity'] . '</td>
                <td>' . $this->shop->formatPrice((float) $item['subtotal']) . '</td>
            </tr>';
        }

        $html .= '</tbody>
</table>

<div class="totals">
    <div class="row">
        <span>جمع کالاها:</span>
        <span>' . $this->shop->formatPrice((float) $order['subtotal']) . '</span>
    </div>';

        if (!empty($order['discount']) && $order['discount'] > 0) {
            $html .= '<div class="row discount">
                <span>تخفیف ' . (!empty($order['coupon_code']) ? '(' . htmlspecialchars($order['coupon_code']) . ')' : '') . ':</span>
                <span>− ' . $this->shop->formatPrice((float) $order['discount']) . '</span>
            </div>';
        }

        $html .= '<div class="row">
        <span>مالیات:</span>
        <span>' . $this->shop->formatPrice((float) $order['tax']) . '</span>
    </div>
    <div class="row">
        <span>هزینه ارسال:</span>
        <span>' . ($order['shipping'] == 0 ? 'رایگان' : $this->shop->formatPrice((float) $order['shipping'])) . '</span>
    </div>
    <div class="row grand-total">
        <span>جمع کل:</span>
        <span>' . $this->shop->formatPrice((float) $order['total']) . '</span>
    </div>
</div>

<div class="footer">
    <p>این فاکتور به صورت خودکار توسط سیستم ' . htmlspecialchars($siteName) . ' تولید شده است.</p>
    <p>با تشکر از خرید شما 🌹</p>
</div>

</body>
</html>';

        return $html;
    }

    /**
     * تولید PDF و ارسال به مرورگر
     */
    public function download(int $orderId, bool $inline = false): void
    {
        $order = $this->orderManager->getById($orderId);
        if (!$order) {
            throw new Exception('سفارش یافت نشد');
        }

        $html = $this->renderHtml($orderId);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'dejavu sans');
        $options->set('chroot', __DIR__ . '/../../../');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'invoice-' . $order['order_number'] . '.pdf';

        $dompdf->stream($filename, ['Attachment' => !$inline]);
    }

    /**
     * ذخیره PDF در فایل
     */
    public function save(int $orderId, string $path): bool
    {
        try {
            $html = $this->renderHtml($orderId);

            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'dejavu sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            file_put_contents($path, $dompdf->output());
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * برچسب وضعیت سفارش
     */
    private function getStatusLabel(string $status): string
    {
        return [
            'pending' => 'در انتظار',
            'processing' => 'در حال پردازش',
            'shipped' => 'ارسال شده',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازگشت داده شده',
        ][$status] ?? $status;
    }

    /**
     * برچسب وضعیت پرداخت
     */
    private function getPaymentLabel(string $status): string
    {
        return [
            'unpaid' => 'پرداخت نشده',
            'paid' => 'پرداخت شده',
            'failed' => 'ناموفق',
            'refunded' => 'بازگشت داده شده',
        ][$status] ?? $status;
    }
}
