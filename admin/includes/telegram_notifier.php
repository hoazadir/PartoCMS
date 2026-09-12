<?php
/**
 * PartoCMS - اطلاع‌رسانی تلگرام
 * ارسال هشدارهای امنیتی به کانال/چت تلگرام
 */

class TelegramNotifier {
    private $botToken;
    private $chatId;
    private $apiUrl;
    private $enabled;

    public function __construct($botToken = '', $chatId = '', $enabled = true) {
        $this->botToken = $botToken;
        $this->chatId = $chatId;
        $this->enabled = $enabled && !empty($botToken) && !empty($chatId);
        $this->apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
    }

    /**
     * ارسال پیام ساده
     */
    public function sendMessage($text, $parseMode = 'HTML') {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'تلگرام غیرفعال یا تنظیم نشده است'];
        }

        $data = [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ];

        return $this->request($data);
    }

    /**
     * ارسال هشدار امنیتی فرمت‌شده
     */
    public function sendSecurityAlert($type, $title, $details = [], $severity = 'warning') {
        $emojis = [
            'info' => 'ℹ️',
            'warning' => '⚠️',
            'danger' => '🚨',
            'success' => '✅',
        ];
        $emoji = $emojis[$severity] ?? '⚠️';

        $message = "{$emoji} <b>PartoCMS Security Alert</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "<b>نوع:</b> <code>{$type}</code>\n";
        $message .= "<b>عنوان:</b> {$title}\n\n";

        if (!empty($details)) {
            foreach ($details as $key => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $message .= "<b>{$key}:</b> <code>" . htmlspecialchars($value) . "</code>\n";
            }
        }

        $message .= "\n🕐 " . date('Y-m-d H:i:s');
        $message .= "\n🌐 " . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        return $this->sendMessage($message);
    }

    /**
     * ارسال گزارش کامل
     */
    public function sendFullReport($stats, $suspiciousFiles = [], $logs = []) {
        $message = "📊 <b>گزارش امنیتی PartoCMS</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n\n";

        $message .= "📁 <b>آمار کلی:</b>\n";
        $message .= "• فایل‌های تحت نظر: <b>{$stats['files_monitored']}</b>\n";
        $message .= "• دستکاری‌شده: <b>{$stats['modified_files']}</b>\n";
        $message .= "• فایل جدید: <b>{$stats['added_files']}</b>\n";
        $message .= "• حذف‌شده: <b>{$stats['deleted_files']}</b>\n";
        $message .= "• بدافزار مسدود: <b>{$stats['malware_blocks']}</b>\n";
        $message .= "• ورود ناموفق (۲۴س): <b>{$stats['login_fails']}</b>\n\n";

        if (!empty($suspiciousFiles)) {
            $message .= "⚠️ <b>فایل‌های مشکوک (" . count($suspiciousFiles) . "):</b>\n";
            foreach (array_slice($suspiciousFiles, 0, 5) as $f) {
                $message .= "• <code>{$f['file_path']}</code> [{$f['status']}]\n";
            }
            if (count($suspiciousFiles) > 5) {
                $message .= "• و " . (count($suspiciousFiles) - 5) . " مورد دیگر...\n";
            }
        }

        $message .= "\n🕐 " . date('Y-m-d H:i:s');
        return $this->sendMessage($message);
    }

    /**
     * ارسال فایل (برای PDF)
     */
    public function sendDocument($filePath, $caption = '') {
        if (!$this->enabled) return false;
        if (!file_exists($filePath)) return false;

        $url = "https://api.telegram.org/bot{$this->botToken}/sendDocument";

        $post = [
            'chat_id' => $this->chatId,
            'caption' => $caption,
            'document' => new CURLFile($filePath),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        $response = curl_exec($ch);
        // curl_close($ch); // deprecated in PHP 8.0+

        return json_decode($response, true);
    }

    /**
     * تست اتصال
     */
    public function testConnection() {
        return $this->sendMessage("✅ تست اتصال PartoCMS — موفق بود!\n🕐 " . date('Y-m-d H:i:s'));
    }

    private function request($data) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        // curl_close($ch); // deprecated in PHP 8.0+

        if ($error) {
            return ['ok' => false, 'error' => $error];
        }
        return json_decode($response, true);
    }
}
?>
