<?php
/**
 * PartoCMS - گزارش PDF امنیتی
 * استفاده از Dompdf برای تولید PDF استاندارد
 */
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PdfReporter {
    private $dompdf;

    public function __construct() {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'dejavusans');
        $options->set('chroot', realpath(__DIR__ . '/../..'));

        $this->dompdf = new Dompdf($options);
    }

    /**
     * تولید گزارش امنیتی
     */
    public function generateSecurityReport($stats, $suspiciousFiles, $logs, $siteName = 'PartoCMS') {
        $html = $this->buildHtml($stats, $suspiciousFiles, $logs, $siteName);

        $this->dompdf->loadHtml($html, 'UTF-8');
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return $this->dompdf->output();
    }

    /**
     * ساخت HTML گزارش
     */
    private function buildHtml($stats, $suspiciousFiles, $logs, $siteName) {
        $now = date('Y-m-d H:i:s');
        $suspiciousCount = count($suspiciousFiles);
        $logsCount = count($logs);

        $html = <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
<meta charset="UTF-8">
<style>
    @font-face {
        font-family: 'DejaVu';
        src: url('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf') format('truetype');
    }
    body { font-family: 'DejaVu', sans-serif; direction: rtl; text-align: right; font-size: 11px; color: #1e293b; }
    .header { background: #1e293b; color: #fff; padding: 20px; text-align: center; margin-bottom: 15px; }
    .header h1 { margin: 0; font-size: 20px; }
    .header p { margin: 5px 0 0; font-size: 11px; opacity: 0.8; }
    .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    .stats-table td { padding: 10px; background: #f1f5f9; border: 1px solid #e2e8f0; text-align: center; width: 16.66%; }
    .stat-value { font-size: 18px; font-weight: bold; color: #1e293b; }
    .stat-label { font-size: 10px; color: #64748b; margin-top: 3px; }
    h2 { font-size: 14px; color: #1e293b; border-right: 4px solid #3b82f6; padding-right: 10px; margin: 20px 0 10px; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10px; }
    table.data th { background: #334155; color: #fff; padding: 8px; text-align: right; font-weight: bold; }
    table.data td { padding: 7px 8px; border-bottom: 1px solid #e2e8f0; }
    table.data tr:nth-child(even) td { background: #f8fafc; }
    .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .badge-modified { background: #fef3c7; color: #92400e; }
    .badge-added { background: #fee2e2; color: #991b1b; }
    .badge-deleted { background: #e2e8f0; color: #475569; }
    .badge-scan { background: #dbeafe; color: #1e40af; }
    .badge-malware { background: #fee2e2; color: #991b1b; }
    .badge-login_fail { background: #fef3c7; color: #92400e; }
    .badge-integrity { background: #fce7f3; color: #9d174d; }
    .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
    .empty { text-align: center; color: #94a3b8; padding: 15px; font-style: italic; }
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
        <td><div class="stat-value">{$stats['files_monitored']}</div><div class="stat-label">فایل‌های تحت نظر</div></td>
        <td><div class="stat-value">{$stats['modified_files']}</div><div class="stat-label">دستکاری‌شده</div></td>
        <td><div class="stat-value">{$stats['added_files']}</div><div class="stat-label">فایل جدید</div></td>
        <td><div class="stat-value">{$stats['deleted_files']}</div><div class="stat-label">حذف‌شده</div></td>
        <td><div class="stat-value">{$stats['malware_blocks']}</div><div class="stat-label">بدافزار مسدود</div></td>
        <td><div class="stat-value">{$stats['login_fails']}</div><div class="stat-label">ورود ناموفق</div></td>
    </tr>
</table>
HTML;

        // فایل‌های مشکوک
        $html .= '<h2>⚠️ فایل‌های مشکوک (' . $suspiciousCount . ')</h2>';
        if ($suspiciousCount > 0) {
            $html .= '<table class="data"><thead><tr><th>مسیر فایل</th><th>وضعیت</th><th>آخرین بررسی</th></tr></thead><tbody>';
            foreach ($suspiciousFiles as $f) {
                $html .= '<tr>';
                $html .= '<td style="font-family: monospace; direction: ltr; text-align: left;">' . htmlspecialchars($f['file_path']) . '</td>';
                $html .= '<td><span class="badge badge-' . htmlspecialchars($f['status']) . '">' . htmlspecialchars($f['status']) . '</span></td>';
                $html .= '<td>' . htmlspecialchars($f['last_checked']) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        } else {
            $html .= '<div class="empty">هیچ فایل مشکوکی یافت نشد ✅</div>';
        }

        // لاگ‌ها
        $html .= '<h2>📓 رویدادهای امنیتی (' . $logsCount . ' مورد اخیر)</h2>';
        if ($logsCount > 0) {
            $html .= '<table class="data"><thead><tr><th>نوع</th><th>پیام</th><th>IP</th><th>تاریخ</th></tr></thead><tbody>';
            foreach (array_slice($logs, 0, 50) as $log) {
                $html .= '<tr>';
                $html .= '<td><span class="badge badge-' . htmlspecialchars($log['type']) . '">' . htmlspecialchars($log['type']) . '</span></td>';
                $html .= '<td>' . htmlspecialchars(mb_substr($log['message'], 0, 120)) . '</td>';
                $html .= '<td style="direction: ltr;">' . htmlspecialchars($log['ip'] ?? '-') . '</td>';
                $html .= '<td>' . htmlspecialchars($log['created_at']) . '</td>';
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
?>
