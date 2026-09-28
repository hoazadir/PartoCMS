<?php
/**
 * PartoCMS - Invoice Generator (mPDF)
 * تولید فاکتور PDF سفارش با پشتیبانی کامل RTL
 *
 * @author Hooman Oliaei
 * @version 2.0.0 - mPDF based
 */

use Mpdf\Mpdf;

require_once __DIR__ . '/InvoiceHelper.php';

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

    private function getRootPath(): string
    {
        return realpath(__DIR__ . '/../../..');
    }

    private function getFontsPath(): string
    {
        return $this->getRootPath() . '/assets/fonts';
    }

    private function createMpdf(): Mpdf
    {
        $tempDir = $this->getRootPath() . '/logs/mpdf';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'directionality' => 'rtl',
            'default_font' => 'vazirmatn',
            'default_font_size' => 11,
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 20,
            'fontDir' => array_merge(
                (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],
                [$this->getFontsPath()]
            ),
            'fontdata' => array_merge(
                (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'],
                [
                    'vazirmatn' => [
                        'R' => 'Vazirmatn-Regular.ttf',
                        'B' => 'Vazirmatn-Bold.ttf',
                        'useOTL' => 0xFF,
                        'useKashida' => 75,
                    ],
                ]
            ),
            'tempDir' => $tempDir,
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDirectionality('rtl');

        return $mpdf;
    }

    /**
     * گرفتن زبان سفارش
     * - اگر سفارش کاربر دارد، زبان آن کاربر
     * - اگر مهمان، زبان پیش‌فرض سایت
     * - خروجی نرمال‌شده: fa | ar | en
     */
    private function getOrderLanguage(array $order): string
    {
        $langCode = null;

        // اگر سفارش user_id دارد
        if (!empty($order['user_id'])) {
            try {
                $stmt = $this->pdo->prepare("SELECT preferred_language FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([(int) $order['user_id']]);
                $langCode = $stmt->fetchColumn();
            } catch (Throwable $e) {
                // ignore
            }
        }

        // fallback به زبان پیش‌فرض سایت
        if (empty($langCode)) {
            $langCode = getSetting('default_language', 'fa-IR');
        }

        // fallback نهایی: زبان فعلی i18n
        if (empty($langCode) && function_exists('getI18n')) {
            $i18n = getI18n();
            if ($i18n && method_exists($i18n, 'getCurrent')) {
                $langCode = $i18n->getCurrent();
            }
        }

        // نرمال‌سازی: fa-IR → fa
        $langCode = strtolower((string) $langCode);
        $short = substr($langCode, 0, 2);

        $supported = ['fa', 'ar', 'en'];
        return in_array($short, $supported, true) ? $short : 'fa';
    }

    /**
     * شماره فاکتور صعودی
     * ترتیب: بر اساس id سفارش (از ۱ شروع می‌شود)
     */
    private function getInvoiceNumber(array $order): int
    {
        return (int) ($order['id'] ?? 0);
    }

    /**
     * محاسبه داینامیک فاصله‌ها و اندازه‌ها بر اساس تعداد آیتم‌ها
     * - آیتم کم → فاصله بیشتر، خوانایی بهتر
     * - آیتم زیاد → فاصله کمتر، fit در یک صفحه A4
     */
    private function getLayoutConfig(int $itemCount): array
    {
        if ($itemCount <= 5) {
            // فاکتور کوچک — فاصله‌های راحت
            return [
                'header_margin'     => 35,
                'header_padding'    => 25,
                'title_margin'      => 12,
                'date_margin'       => 18,
                'box_padding'       => 22,
                'box_margin'        => 32,
                'box_line_height'   => 2.1,
                'table_padding'     => 12,
                'table_margin'      => 30,
                'totals_padding'    => 20,
                'totals_margin'     => 35,
                'total_row_padding' => 10,
                'footer_margin'     => 40,
                'font_size'         => 11,
                'table_font_size'   => 11,
                'title_font_size'   => 20,
                'site_font_size'    => 24,
                'totals_font_size'  => 15,
            ];
        }

        if ($itemCount <= 15) {
            // فاکتور متوسط — فاصله‌های متعادل
            return [
                'header_margin'     => 28,
                'header_padding'    => 20,
                'title_margin'      => 10,
                'date_margin'       => 15,
                'box_padding'       => 18,
                'box_margin'        => 26,
                'box_line_height'   => 1.9,
                'table_padding'     => 9,
                'table_margin'      => 24,
                'totals_padding'    => 16,
                'totals_margin'     => 28,
                'total_row_padding' => 8,
                'footer_margin'     => 32,
                'font_size'         => 11,
                'table_font_size'   => 10,
                'title_font_size'   => 18,
                'site_font_size'    => 22,
                'totals_font_size'  => 14,
            ];
        }

        // فاکتور بزرگ — فاصله‌های فشرده
        return [
            'header_margin'     => 18,
            'header_padding'    => 15,
            'title_margin'      => 8,
            'date_margin'       => 10,
            'box_padding'       => 12,
            'box_margin'        => 18,
            'box_line_height'   => 1.7,
            'table_padding'     => 6,
            'table_margin'      => 16,
            'totals_padding'    => 12,
            'totals_margin'     => 20,
            'total_row_padding' => 6,
            'footer_margin'     => 22,
            'font_size'         => 10,
            'table_font_size'   => 9,
            'title_font_size'   => 16,
            'site_font_size'    => 20,
            'totals_font_size'  => 13,
        ];
    }

    public function renderHtml(int $orderId): string
    {
        $order = $this->orderManager->getById($orderId);
        if (!$order) {
            throw new Exception('سفارش یافت نشد');
        }

        // زبان سفارش
        $lang = $this->getOrderLanguage($order);

        $items = $this->orderManager->getItems($orderId);
        $L = $this->getLayoutConfig(count($items));
        $siteName = getSetting('site_name', '');
        if (empty(trim($siteName))) {
            $siteName = getSetting('site_title', '');
        }
        if (empty(trim($siteName))) {
            $siteName = 'فروشگاه من';
        }
        $siteUrl = SITE_URL;

        $html = '<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>فاکتور ' . htmlspecialchars($order['order_number']) . '</title>
    <style>
        body {
            font-family: vazirmatn, sans-serif;
            direction: rtl;
            text-align: right;
            color: #333;
            font-size: ' . $L['font_size'] . 'px;
            line-height: ' . $L['box_line_height'] . ';
        }
        p {
            line-height: 2;
            margin: 6px 0;
        }
        .invoice-header {
            border-bottom: 3px solid #06b6d4;
            padding-bottom: ' . $L['header_padding'] . 'px;
            margin-bottom: ' . $L['header_margin'] . 'px;
        }
        .site-name {
            color: #06b6d4;
            font-size: ' . $L['site_font_size'] . 'px;
            font-weight: bold;
            margin: 0 0 8px 0;
            line-height: 1.4;
        }
        .site-url { color: #64748b; font-size: 10px; margin: 2px 0; }
        .invoice-title { color: #0f172a; font-size: ' . $L['title_font_size'] . 'px; font-weight: bold; margin: ' . $L['title_margin'] . 'px 0; line-height: 1.5; }
        .order-number {
            background: #f0f9ff;
            padding: 6px 12px;
            color: #0891b2;
            font-weight: bold;
            display: inline-block;
            margin-top: 12px;
            line-height: 1.8;
        }
        .customer-box {
            background: #f8fafc;
            padding: ' . $L['box_padding'] . 'px;
            margin-bottom: ' . $L['box_margin'] . 'px;
            line-height: ' . $L['box_line_height'] . ';
        }
        .customer-box h4 {
            color: #0f172a;
            margin: 0 0 12px 0;
            font-size: 12px;
            border-bottom: 2px solid #06b6d4;
            padding-bottom: 5px;
            display: inline-block;
            line-height: 1.6;
        }
        .customer-box p { margin: 8px 0; color: #475569; line-height: 2; }
        table { width: 100%; border-collapse: collapse; margin-bottom: ' . $L['table_margin'] . 'px; }
        table th {
            background: #06b6d4;
            color: #fff;
            padding: ' . $L['table_padding'] . 'px;
            text-align: right;
            font-size: ' . $L['table_font_size'] . 'px;
            font-weight: bold;
        }
        table td {
            padding: ' . $L['table_padding'] . 'px;
            border-bottom: 1px solid #e2e8f0;
            font-size: ' . $L['table_font_size'] . 'px;
        }
        table tr:nth-child(even) td { background: #f8fafc; }
        .totals {
            width: 280px;
            background: #f8fafc;
            padding: ' . $L['totals_padding'] . 'px;
            margin-right: auto;
        }
        .totals .row { padding: 4px 0; font-size: 11px; }
        .totals .row.grand-total {
            border-top: 2px solid #06b6d4;
            padding-top: ' . $L['total_row_padding'] . 'px;
            margin-top: 5px;
            font-size: ' . $L['totals_font_size'] . 'px;
            font-weight: bold;
            color: #0891b2;
        }
        .footer {
            margin-top: ' . $L['footer_margin'] . 'px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            color: #94a3b8;
            font-size: 9px;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #cffafe; color: #155e75; }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-secondary { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>

<div class="invoice-header">
    <table style="border: none;">
        <tr>
            <td style="border: none; vertical-align: top; text-align: right;">
                <div class="site-name">' . htmlspecialchars($siteName) . '</div>
                <div class="site-url">' . htmlspecialchars($siteUrl) . '</div>
            </td>
            <td style="border: none; vertical-align: top; text-align: left;">
                <div class="invoice-title">فاکتور فروش</div>
                <div style="font-size: 10px; color: #64748b; margin-bottom: ' . $L['date_margin'] . 'px; line-height: 1.8;">تاریخ: ' . InvoiceHelper::formatDate($order['created_at'], $lang) . '</div>
                <div class="order-number">شماره فاکتور: ' . InvoiceHelper::formatOrderNumber((string) $this->getInvoiceNumber($order), $lang) . '</div>
            </td>
        </tr>
    </table>
</div>

<div class="customer-box">
    <table style="border: none;">
        <tr>
            <td style="border: none; width: 60%; text-align: right; vertical-align: top;">
                <h4>اطلاعات مشتری</h4>
                <p>' . (!empty($order['shipping_address']) ? nl2br(htmlspecialchars($order['shipping_address'])) : '—') . '</p>
            </td>
            <td style="border: none; width: 40%; text-align: left; vertical-align: top;">
                <h4>وضعیت</h4>
                <p>سفارش: <span class="badge badge-' . $this->statusBadge($order['status']) . '">' . $this->getStatusLabel($order['status']) . '</span></p>
                <p>پرداخت: <span class="badge badge-' . $this->paymentBadge($order['payment_status']) . '">' . $this->getPaymentLabel($order['payment_status']) . '</span></p>
                ' . (!empty($order['payment_method']) ? '<p>روش پرداخت: ' . htmlspecialchars($order['payment_method']) . '</p>' : '') . '
            </td>
        </tr>
    </table>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 30px;">#</th>
            <th>نام محصول</th>
            <th style="width: 90px;">قیمت واحد</th>
            <th style="width: 50px;">تعداد</th>
            <th style="width: 90px;">جمع</th>
        </tr>
    </thead>
    <tbody>';

        $i = 1;
        foreach ($items as $item) {
            $html .= '<tr>
                <td>' . $i++ . '</td>
                <td>' . htmlspecialchars($item['product_name']) . '</td>
                <td>' . InvoiceHelper::formatPrice((float) $item['product_price'], $lang) . '</td>
                <td>' . (int) $item['quantity'] . '</td>
                <td>' . InvoiceHelper::formatPrice((float) $item['subtotal'], $lang) . '</td>
            </tr>';
        }

        $html .= '</tbody>
</table>

<div class="totals">
    <table style="border: none;">
        <tr>
            <td style="border: none; text-align: right;">جمع کالاها:</td>
            <td style="border: none; text-align: left;">' . InvoiceHelper::formatPrice((float) $order['subtotal'], $lang) . '</td>
        </tr>';

        if (!empty($order['discount']) && $order['discount'] > 0) {
            $html .= '<tr>
                <td style="border: none; text-align: right; color: #10b981;">تخفیف ' . (!empty($order['coupon_code']) ? '(' . htmlspecialchars($order['coupon_code']) . ')' : '') . ':</td>
                <td style="border: none; text-align: left; color: #10b981;">− ' . InvoiceHelper::formatPrice((float) $order['discount'], $lang) . '</td>
            </tr>';
        }

        $html .= '<tr>
            <td style="border: none; text-align: right;">مالیات:</td>
            <td style="border: none; text-align: left;">' . InvoiceHelper::formatPrice((float) $order['tax'], $lang) . '</td>
        </tr>
        <tr>
            <td style="border: none; text-align: right;">هزینه ارسال:</td>
            <td style="border: none; text-align: left;">' . ($order['shipping'] == 0 ? 'رایگان' : InvoiceHelper::formatPrice((float) $order['shipping'], $lang)) . '</td>
        </tr>
        <tr style="border-top: 2px solid #06b6d4;">
            <td style="border: none; text-align: right; padding-top: 8px; font-size: 14px; font-weight: bold; color: #0891b2;">جمع کل:</td>
            <td style="border: none; text-align: left; padding-top: 8px; font-size: 14px; font-weight: bold; color: #0891b2;">' . InvoiceHelper::formatPrice((float) $order['total'], $lang) . '</td>
        </tr>
    </table>
</div>

<div class="footer">
    <p>این فاکتور به صورت خودکار توسط سیستم ' . htmlspecialchars($siteName) . ' تولید شده است.</p>
    <p>با تشکر از خرید شما</p>
</div>

</body>
</html>';

        return $html;
    }

    public function download(int $orderId, bool $inline = false): void
    {
        $order = $this->orderManager->getById($orderId);
        if (!$order) {
            throw new Exception('سفارش یافت نشد');
        }

        $html = $this->renderHtml($orderId);
        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($html);

        $filename = 'invoice-' . $order['order_number'] . '.pdf';
        $mpdf->Output($filename, $inline ? 'I' : 'D');
        exit;
    }

    public function save(int $orderId, string $path): bool
    {
        try {
            $html = $this->renderHtml($orderId);
            $mpdf = $this->createMpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, 'F');
            return true;
        } catch (Throwable $e) {
            error_log('InvoiceGenerator error: ' . $e->getMessage());
            return false;
        }
    }

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

    private function getPaymentLabel(string $status): string
    {
        return [
            'unpaid' => 'پرداخت نشده',
            'paid' => 'پرداخت شده',
            'failed' => 'ناموفق',
            'refunded' => 'بازگشت داده شده',
        ][$status] ?? $status;
    }

    private function statusBadge(string $status): string
    {
        return [
            'pending' => 'warning',
            'processing' => 'info',
            'shipped' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            'refunded' => 'secondary',
        ][$status] ?? 'secondary';
    }

    private function paymentBadge(string $status): string
    {
        return [
            'unpaid' => 'secondary',
            'paid' => 'success',
            'failed' => 'danger',
            'refunded' => 'warning',
        ][$status] ?? 'secondary';
    }
}
