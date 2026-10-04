<?php
/**
 * PartoCMS - گزارش PDF امنیتی
 * استفاده از mPDF برای پشتیبانی کامل از فارسی و RTL
 *
 * @author Hooman Oliaei
 * @version 2.0.0
 * @date 2026-10-04
 *
 * ✅ تغییرات v2.0:
 * - جایگزینی Dompdf با mPDF
 * - پشتیبانی کامل از فارسی و RTL
 * - استفاده از فونت Vazirmatn
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../modules/shop/includes/InvoiceHelper.php';

use Mpdf\Mpdf;

class PdfReporter
{
    /** @var string مسیر فونت‌ها */
    private string $fontsPath;

    /** @var string مسیر temp */
    private string $tempDir;

    public function __construct()
    {
        $rootPath = dirname(__DIR__, 2);
        $this->fontsPath = $rootPath . '/assets/fonts';
        $this->tempDir = $rootPath . '/logs/mpdf';

        // ساخت پوشه temp اگر نیست
        if (!is_dir($this->tempDir)) {
            @mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * ساخت mPDF با تنظیمات فارسی
     */
    private function createMpdf(): Mpdf
    {
        $mpdf = new Mpdf([
            'mode'              => 'utf-8',
            'format'            => 'A4',
            'orientation'       => 'P',
            'directionality'    => 'rtl',
            'default_font'      => 'vazirmatn',
            'default_font_size' => 10,
            'margin_left'       => 12,
            'margin_right'      => 12,
            'margin_top'        => 12,
            'margin_bottom'     => 15,
            'tempDir'           => $this->tempDir,
            'fontDir'           => array_merge(
                (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'],
                [$this->fontsPath]
            ),
            'fontdata'          => array_merge(
                (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'],
                [
                    'vazirmatn' => [
                        'R' => 'Vazirmatn-Regular.ttf',
                        'B' => 'Vazirmatn-Bold.ttf',
                    ],
                ]
            ),
        ]);

        // پشتیبانی خودکار از زبان و اسکریپت
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDirectionality('rtl');

        // header/footer حذف
        $mpdf->SetHTMLHeader('');
        $mpdf->SetHTMLFooter('');

        return $mpdf;
    }

    /**
     * تولید گزارش امنیتی PDF
     *
     * @param array  $stats           آمار
     * @param array  $suspiciousFiles فایل‌های مشکوک
     * @param array  $logs            لاگ‌ها
     * @param string $siteName        نام سایت
     * @return string محتوای PDF (binary)
     */
    public function generateSecurityReport($stats, $suspiciousFiles, $logs, $siteName = 'PartoCMS'): string
    {
        $html = $this->buildHtml($stats, $suspiciousFiles, $logs, $siteName);

        $mpdf = $this->createMpdf();
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * ساخت HTML گزارش
     */
    private function buildHtml($stats, $suspiciousFiles, $logs, $siteName): string
    {
        // 🆕 تشخیص زبان فعلی برای تاریخ
        $lang = 'fa-IR';
        if (function_exists('getI18n')) {
            try {
                $langInfo = getI18n()->getCurrentInfo();
                $lang = $langInfo['code'] ?? 'fa-IR';
            } catch (Throwable $e) {
                $lang = 'fa-IR';
            }
        }

        // 🆕 تبدیل تاریخ بر اساس زبان (شمسی/قمری/میلادی)
        $now = InvoiceHelper::formatDate(date('Y-m-d H:i:s'), $lang);
        $suspiciousCount = count($suspiciousFiles);
        $logsCount = count($logs);

        $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: 'vazirmatn', sans-serif;
        direction: rtl;
        text-align: right;
        font-size: 10px;
        color: #1e293b;
        line-height: 1.6;
    }
    .header {
        background: #1e293b;
        color: #fff;
        padding: 15px;
        text-align: center;
        margin-bottom: 12px;
        border-radius: 4px;
    }
    .header h1 { margin: 0; font-size: 18px; font-weight: bold; }
    .header p { margin: 4px 0 0; font-size: 10px; opacity: 0.85; }

    .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .stats-table td {
        padding: 8px 4px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        text-align: center;
        width: 16.66%;
    }
    .stat-value { font-size: 16px; font-weight: bold; color: #1e293b; }
    .stat-label { font-size: 9px; color: #64748b; margin-top: 2px; }

    h2 {
        font-size: 13px;
        color: #1e293b;
        border-right: 4px solid #3b82f6;
        padding-right: 8px;
        margin: 15px 0 8px;
        font-weight: bold;
    }

    table.data {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        font-size: 9px;
    }
    table.data th {
        background: #334155;
        color: #fff;
        padding: 6px 5px;
        text-align: right;
        font-weight: bold;
    }
    table.data td {
        padding: 5px 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    table.data tr:nth-child(even) td { background: #f8fafc; }

    .badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 8px;
        font-size: 8px;
        font-weight: bold;
    }
    .badge-modified { background: #fef3c7; color: #92400e; }
    .badge-added { background: #fee2e2; color: #991b1b; }
    .badge-deleted { background: #e2e8f0; color: #475569; }
    .badge-scan { background: #dbeafe; color: #1e40af; }
    .badge-malware { background: #fee2e2; color: #991b1b; }
    .badge-login_fail { background: #fef3c7; color: #92400e; }
    .badge-integrity { background: #fce7f3; color: #9d174d; }

    .footer {
        margin-top: 20px;
        padding-top: 8px;
        border-top: 1px solid #e2e8f0;
        font-size: 8px;
        color: #94a3b8;
        text-align: center;
    }
    .empty {
        text-align: center;
        color: #94a3b8;
        padding: 12px;
        font-style: italic;
    }
    .mono { font-family: monospace; direction: ltr; text-align: left; }
</style>
</head>
<body>

<div class="header">
    <h1>🛡️ گزارش امنیتی {$siteName}</h1>
    <p>تاریخ تولید: {$now}</p>
</div>

<h2>📊 آمار کلی</h2>
<table class="stats-table">
    <tr>
        <td>
            <div class="stat-value">{$stats['files_monitored']}</div>
            <div class="stat-label">فایل‌های تحت نظر</div>
        </td>
        <td>
            <div class="stat-value">{$stats['modified_files']}</div>
            <div class="stat-label">دستکاری‌شده</div>
        </td>
        <td>
            <div class="stat-value">{$stats['added_files']}</div>
            <div class="stat-label">فایل جدید</div>
        </td>
        <td>
            <div class="stat-value">{$stats['deleted_files']}</div>
            <div class="stat-label">حذف‌شده</div>
        </td>
        <td>
            <div class="stat-value">{$stats['malware_blocks']}</div>
            <div class="stat-label">بدافزار مسدود</div>
        </td>
        <td>
            <div class="stat-value">{$stats['login_fails']}</div>
            <div class="stat-label">ورود ناموفق</div>
        </td>
    </tr>
</table>
HTML;

        // فایل‌های مشکوک
        $html .= '<h2>⚠️ فایل‌های مشکوک (' . $suspiciousCount . ')</h2>';
        if ($suspiciousCount > 0) {
            $html .= '<table class="data">';
            $html .= '<thead><tr><th>مسیر فایل</th><th>وضعیت</th><th>آخرین بررسی</th></tr></thead><tbody>';
            foreach ($suspiciousFiles as $f) {
                $html .= '<tr>';
                $html .= '<td class="mono">' . htmlspecialchars($f['file_path']) . '</td>';
                $html .= '<td><span class="badge badge-' . htmlspecialchars($f['status']) . '">' . htmlspecialchars($f['status']) . '</span></td>';
                $html .= '<td>' . htmlspecialchars(InvoiceHelper::formatDate($f['last_checked'], $lang)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<div class="empty">هیچ فایل مشکوکی یافت نشد ✅</div>';
        }

        // لاگ‌ها
        $html .= '<h2>📓 رویدادهای امنیتی (' . $logsCount . ' مورد اخیر)</h2>';
        if ($logsCount > 0) {
            $html .= '<table class="data">';
            $html .= '<thead><tr><th>نوع</th><th>پیام</th><th>IP</th><th>تاریخ</th></tr></thead><tbody>';
            foreach (array_slice($logs, 0, 50) as $log) {
                $html .= '<tr>';
                $html .= '<td><span class="badge badge-' . htmlspecialchars($log['type']) . '">' . htmlspecialchars($log['type']) . '</span></td>';
                $html .= '<td>' . htmlspecialchars(mb_substr($log['message'], 0, 120)) . '</td>';
                $html .= '<td class="mono">' . htmlspecialchars($log['ip'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars(InvoiceHelper::formatDate($log['created_at'], $lang)) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<div class="empty">هیچ رویدادی ثبت نشده است</div>';
        }

        $html .= <<<HTML
<div class="footer">
    PartoCMS Security Report — Generated automatically on {$now}
</div>
</body>
</html>
HTML;

        return $html;
    }
}
