<?php
/**
 * PartoCMS - Weekly Security Report Generator
 * تولید گزارش هفتگی HTML که به ایمیل ارسال می‌شود
 */

class WeeklyReporter {

    private $pdo;
    private $siteName;
    private $siteUrl;

    public function __construct($pdo, $siteName = 'PartoCMS', $siteUrl = '') {
        $this->pdo = $pdo;
        $this->siteName = $siteName;
        $this->siteUrl = $siteUrl;
    }

    /**
     * جمع‌آوری آمار هفتگی
     */
    public function collectStats() {
        $stats = [
            'period_start' => date('Y-m-d', strtotime('-7 days')),
            'period_end' => date('Y-m-d'),
            'generated_at' => date('Y-m-d H:i:s'),

            // امنیت
            'total_events' => 0,
            'malware_blocked' => 0,
            'login_attempts' => 0,
            'login_failed' => 0,
            'login_success' => 0,
            'active_blocks' => 0,
            'files_monitored' => 0,
            'scans_done' => 0,
            'integrity_changes' => 0,

            // کاربران
            'total_users' => 0,
            'users_2fa' => 0,

            // جزئیات
            'event_types' => [],
            'top_ips' => [],
            'recent_events' => [],
        ];

        try {
            // رویدادهای ۷ روز اخیر
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['total_events'] = (int) $stmt->fetchColumn();

            // بدافزار مسدود
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE type='malware' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['malware_blocked'] = (int) $stmt->fetchColumn();

            // اسکن‌ها
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE type='scan' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['scans_done'] = (int) $stmt->fetchColumn();

            // تغییرات یکپارچگی
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE type='integrity' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['integrity_changes'] = (int) $stmt->fetchColumn();

            // لاگین
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['login_attempts'] = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE success=0 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['login_failed'] = (int) $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE success=1 AND attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stmt->execute();
            $stats['login_success'] = (int) $stmt->fetchColumn();

            // IP های بلاک شده فعال
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM login_blocks WHERE blocked_until > NOW()");
            $stmt->execute();
            $stats['active_blocks'] = (int) $stmt->fetchColumn();

            // فایل‌های تحت نظر
            $stats['files_monitored'] = (int) $this->pdo->query("SELECT COUNT(*) FROM file_hashes")->fetchColumn();

            // کاربران
            $stats['total_users'] = (int) $this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            $stats['users_2fa'] = (int) $this->pdo->query("SELECT COUNT(*) FROM user_2fa WHERE enabled=1")->fetchColumn();

            // توزیع انواع رویداد
            $stmt = $this->pdo->prepare("
                SELECT type, COUNT(*) as cnt
                FROM security_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY type ORDER BY cnt DESC
            ");
            $stmt->execute();
            $stats['event_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Top 5 IP
            $stmt = $this->pdo->prepare("
                SELECT ip, COUNT(*) as cnt
                FROM login_attempts
                WHERE attempted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY ip ORDER BY cnt DESC LIMIT 5
            ");
            $stmt->execute();
            $stats['top_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 10 رویداد اخیر
            $stmt = $this->pdo->prepare("
                SELECT type, message, ip, created_at
                FROM security_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ORDER BY created_at DESC LIMIT 10
            ");
            $stmt->execute();
            $stats['recent_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Throwable $e) {
            // silent
        }

        return $stats;
    }

    /**
     * تولید HTML گزارش
     */
    public function generateHtml($stats) {
        $healthColor = '#10b981'; // سبز
        $healthText = 'سالم';
        if ($stats['malware_blocked'] > 0 || $stats['integrity_changes'] > 5) {
            $healthColor = '#ef4444';
            $healthText = 'نیاز به بررسی';
        } elseif ($stats['login_failed'] > 20) {
            $healthColor = '#f59e0b';
            $healthText = 'هشدار';
        }

        $html = '<!DOCTYPE html><html <?= __html_attrs() ?>><head><meta charset="UTF-8">';
        $html .= '<style>';
        $html .= 'body{font-family:Tahoma,Arial,sans-serif;background:#f1f5f9;margin:0;padding:20px;direction:rtl}';
        $html .= '.container{max-width:700px;margin:0 auto;background:#fff;border-radius:15px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)}';
        $html .= '.header{background:linear-gradient(135deg,#1e40af,#3730a3);color:#fff;padding:30px;text-align:center}';
        $html .= '.header h1{margin:0;font-size:24px}';
        $html .= '.header p{margin:10px 0 0;opacity:.85;font-size:13px}';
        $html .= '.health{padding:15px 30px;text-align:center;color:#fff;font-weight:bold;font-size:16px}';
        $html .= '.content{padding:30px}';
        $html .= '.section{margin-bottom:30px}';
        $html .= '.section h2{color:#1e293b;font-size:16px;margin:0 0 15px;padding-bottom:10px;border-bottom:2px solid #e2e8f0}';
        $html .= '.stats-grid{display:table;width:100%;border-spacing:8px}';
        $html .= '.stat-box{display:table-cell;background:#f8fafc;border-radius:10px;padding:15px;text-align:center;width:33%;border:1px solid #e2e8f0}';
        $html .= '.stat-value{font-size:24px;font-weight:bold;color:#1e293b}';
        $html .= '.stat-label{font-size:11px;color:#64748b;margin-top:5px}';
        $html .= 'table.data{width:100%;border-collapse:collapse;font-size:12px}';
        $html .= 'table.data th{background:#334155;color:#fff;padding:10px;text-align:right;font-size:11px}';
        $html .= 'table.data td{padding:8px 10px;border-bottom:1px solid #f1f5f9}';
        $html .= 'table.data tr:nth-child(even) td{background:#f8fafc}';
        $html .= '.badge{display:inline-block;padding:3px 8px;border-radius:10px;font-size:10px;font-weight:bold}';
        $html .= '.b-scan{background:#dbeafe;color:#1e40af}';
        $html .= '.b-malware{background:#fee2e2;color:#991b1b}';
        $html .= '.b-login{background:#fef3c7;color:#92400e}';
        $html .= '.b-integrity{background:#fce7f3;color:#9d174d}';
        $html .= '.footer{background:#1e293b;color:#94a3b8;padding:20px;text-align:center;font-size:11px}';
        $html .= '.footer a{color:#7dd3fc;text-decoration:none}';
        $html .= '</style></head><body>';

        $html .= '<div class="container">';
        $html .= '<div class="header">';
        $html .= '<h1>🛡️ گزارش هفتگی امنیتی</h1>';
        $html .= '<p>' . htmlspecialchars($this->siteName) . '</p>';
        $html .= '<p>دوره: ' . $stats['period_start'] . ' تا ' . $stats['period_end'] . '</p>';
        $html .= '</div>';

        $html .= '<div class="health" style="background:' . $healthColor . '">';
        $html .= 'وضعیت کلی: ' . $healthText;
        $html .= '</div>';

        $html .= '<div class="content">';

        // ============ خلاصه امنیتی ============
        $html .= '<div class="section">';
        $html .= '<h2>📊 خلاصه فعالیت هفتگی</h2>';
        $html .= '<div class="stats-grid">';
        $html .= '<div class="stat-box"><div class="stat-value">' . number_format($stats['total_events']) . '</div><div class="stat-label">کل رویدادها</div></div>';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#ef4444">' . $stats['malware_blocked'] . '</div><div class="stat-label">بدافزار مسدود</div></div>';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#3b82f6">' . $stats['scans_done'] . '</div><div class="stat-label">اسکن انجام‌شده</div></div>';
        $html .= '</div>';
        $html .= '</div>';

        // ============ لاگین ============
        $html .= '<div class="section">';
        $html .= '<h2>🔐 تلاش‌های ورود</h2>';
        $html .= '<div class="stats-grid">';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#10b981">' . $stats['login_success'] . '</div><div class="stat-label">موفق</div></div>';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#ef4444">' . $stats['login_failed'] . '</div><div class="stat-label">ناموفق</div></div>';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#f59e0b">' . $stats['active_blocks'] . '</div><div class="stat-label">IP بلاک‌شده</div></div>';
        $html .= '</div>';
        $html .= '</div>';

        // ============ یکپارچگی ============
        $html .= '<div class="section">';
        $html .= '<h2>📁 یکپارچگی فایل‌ها</h2>';
        $html .= '<div class="stats-grid">';
        $html .= '<div class="stat-box"><div class="stat-value">' . number_format($stats['files_monitored']) . '</div><div class="stat-label">فایل تحت نظر</div></div>';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#f59e0b">' . $stats['integrity_changes'] . '</div><div class="stat-label">تغییرات شناسایی‌شده</div></div>';
        $html .= '<div class="stat-box"><div class="stat-value" style="color:#8b5cf6">' . $stats['users_2fa'] . '</div><div class="stat-label">کاربر با 2FA</div></div>';
        $html .= '</div>';
        $html .= '</div>';

        // ============ توزیع رویدادها ============
        if (!empty($stats['event_types'])) {
            $html .= '<div class="section">';
            $html .= '<h2>📋 توزیع انواع رویداد</h2>';
            $html .= '<table class="data"><thead><tr><th>نوع رویداد</th><th>تعداد</th></tr></thead><tbody>';
            foreach ($stats['event_types'] as $et) {
                $badgeClass = 'b-' . preg_replace('/[^a-z]/', '', $et['type']);
                if (!in_array($badgeClass, ['b-scan', 'b-malware', 'b-login', 'b-integrity'])) {
                    $badgeClass = 'b-scan';
                }
                $html .= '<tr>';
                $html .= '<td><span class="badge ' . $badgeClass . '">' . htmlspecialchars($et['type']) . '</span></td>';
                $html .= '<td><b>' . number_format($et['cnt']) . '</b></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // ============ Top IPs ============
        if (!empty($stats['top_ips'])) {
            $html .= '<div class="section">';
            $html .= '<h2>🌐 پرتلاش‌ترین IP ها</h2>';
            $html .= '<table class="data"><thead><tr><th>IP</th><th>تعداد تلاش</th></tr></thead><tbody>';
            foreach ($stats['top_ips'] as $ip) {
                $html .= '<tr>';
                $html .= '<td style="font-family:monospace;direction:ltr;text-align:left">' . htmlspecialchars($ip['ip']) . '</td>';
                $html .= '<td><b>' . $ip['cnt'] . '</b></td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        // ============ رویدادهای اخیر ============
        if (!empty($stats['recent_events'])) {
            $html .= '<div class="section">';
            $html .= '<h2>📓 ۱۰ رویداد اخیر</h2>';
            $html .= '<table class="data"><thead><tr><th>نوع</th><th>پیام</th><th>زمان</th></tr></thead><tbody>';
            foreach ($stats['recent_events'] as $e) {
                $badgeClass = 'b-' . preg_replace('/[^a-z]/', '', $e['type']);
                if (!in_array($badgeClass, ['b-scan', 'b-malware', 'b-login', 'b-integrity'])) {
                    $badgeClass = 'b-scan';
                }
                $html .= '<tr>';
                $html .= '<td><span class="badge ' . $badgeClass . '">' . htmlspecialchars($e['type']) . '</span></td>';
                $html .= '<td>' . htmlspecialchars(mb_substr($e['message'], 0, 60)) . '</td>';
                $html .= '<td style="font-size:10px">' . htmlspecialchars(date('m/d H:i', strtotime($e['created_at']))) . '</td>';
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            $html .= '</div>';
        }

        $html .= '</div>'; // end content

        $html .= '<div class="footer">';
        $html .= '<p>🛡️ این گزارش به‌صورت خودکار توسط سیستم امنیتی PartoCMS تولید شده است.</p>';
        $html .= '<p>تاریخ تولید: ' . $stats['generated_at'] . '</p>';
        if ($this->siteUrl) {
            $html .= '<p><a href="' . $this->siteUrl . '/admin/security_dashboard.php">مشاهده داشبورد امنیتی</a></p>';
        }
        $html .= '</div>';

        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * ارسال ایمیل با گزارش
     */
    public function sendReport($toEmail, $fromEmail = null) {
        $stats = $this->collectStats();
        $html = $this->generateHtml($stats);

        $fromEmail = $fromEmail ?: ('noreply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $subject = '🛡️ گزارش هفتگی امنیتی ' . $this->siteName . ' — ' . date('Y-m-d');

        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: PartoCMS Security <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "X-Mailer: PartoCMS-WeeklyReporter\r\n";

        $sent = @mail($toEmail, $subject, $html, $headers);

        return [
            'ok' => $sent,
            'to' => $toEmail,
            'subject' => $subject,
            'stats' => $stats,
            'html' => $html,
        ];
    }
}
