<?php
/**
 * PartoCMS - Auto Translator
 * ترجمه خودکار محتوا در زمان انتشار
 *
 * @version 1.1
 * @date 2026-09-16
 * - v1.1: setsid + nohup برای اجرای مستقل پس‌زمینه
 */
class AutoTranslator {

    private $pdo;
    private $multiTranslator;
    private $telegram = null;

    public function __construct($pdo) {
        $this->pdo = $pdo;

        if (!class_exists('MultiTranslator')) {
            require_once __DIR__ . '/multi_translator.php';
        }
        $this->multiTranslator = new MultiTranslator($pdo);

        $this->initTelegram();
    }

    // ============================================================
    //   Telegram
    // ============================================================
    private function initTelegram() {
        try {
            $token = $this->getSetting('telegram_bot_token', '');
            $chatId = $this->getSetting('telegram_chat_id', '');

            if ($token && $chatId) {
                $tgPath = __DIR__ . '/telegram_notifier.php';
                if (file_exists($tgPath)) {
                    require_once $tgPath;
                    if (class_exists('TelegramNotifier')) {
                        $this->telegram = new TelegramNotifier($token, $chatId);
                    }
                }
            }
        } catch (Throwable $e) {
            $this->telegram = null;
        }
    }

    private function getSetting($key, $default = '') {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
            $stmt->execute([$key]);
            return $stmt->fetchColumn() ?: $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function setSetting($key, $value) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            $stmt->execute([$key, $value]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ============================================================
    //   بررسی فعال بودن
    // ============================================================
    public function isEnabled(): bool {
        return $this->getSetting('auto_translate_on_publish', '0') === '1';
    }

    public function setEnabled(bool $enabled): bool {
        return $this->setSetting('auto_translate_on_publish', $enabled ? '1' : '0');
    }

    public function getTargetLanguages(): array {
        try {
            $stmt = $this->pdo->query("
                SELECT id, code, name, native_name, flag
                FROM languages
                WHERE is_active = 1 AND is_default = 0
                ORDER BY sort_order, id
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    // ============================================================
    //   اجرای اصلی
    // ============================================================
    public function onPublish(int $contentId, ?int $userId = null, bool $async = true): array {
        if (!$this->isEnabled()) {
            return ['ok' => false, 'skipped' => true, 'reason' => 'auto_translate_disabled'];
        }

        try {
            $stmt = $this->pdo->prepare("SELECT id, title, status FROM content_items WHERE id = ? LIMIT 1");
            $stmt->execute([$contentId]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$item) {
                return ['ok' => false, 'error' => 'content_not_found'];
            }
            if ($item['status'] !== 'published') {
                return ['ok' => false, 'skipped' => true, 'reason' => 'not_published'];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        if ($async) {
            return $this->runAsync($contentId, $userId);
        }

        return $this->run($contentId, $userId);
    }

    /**
     * ✅ اجرای پس‌زمینه مستقل (setsid + nohup)
     * کاربر فوری پاسخ می‌گیره، پردازش از سرور PHP جدا می‌شه
     */
    private function runAsync(int $contentId, ?int $userId): array {
        try {
            $script = realpath(__DIR__ . '/../cron_auto_translate.php');
            if (!$script) {
                return ['ok' => false, 'error' => 'cron_script_not_found'];
            }

            $php = PHP_BINARY ?: 'php';
            $logFile = __DIR__ . '/../../logs/auto_translate.log';

            // لاگ dir
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            // چک وجود setsid
            $setsidPath = trim(@shell_exec('which setsid 2>/dev/null') ?: '');

            if (!empty($setsidPath)) {
                // ✅ با setsid — پردازش کاملاً مستقل
                $cmd = sprintf(
                    '%s nohup %s %s %d %d >> %s 2>&1 < /dev/null &',
                    escapeshellcmd($setsidPath),
                    escapeshellcmd($php),
                    escapeshellarg($script),
                    $contentId,
                    (int) ($userId ?? 0),
                    escapeshellarg($logFile)
                );
            } else {
                // ⚠️ fallback به nohup تنها
                $cmd = sprintf(
                    'nohup %s %s %d %d >> %s 2>&1 < /dev/null &',
                    escapeshellcmd($php),
                    escapeshellarg($script),
                    $contentId,
                    (int) ($userId ?? 0),
                    escapeshellarg($logFile)
                );
            }

            @exec($cmd);

            $this->logActivity('auto_translate_queued', $contentId, $userId, 'queued');

            return [
                'ok' => true,
                'queued' => true,
                'content_id' => $contentId,
                'message' => 'ترجمه در پس‌زمینه شروع شد.',
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * اجرای همزمان (در cron یا CLI)
     */
    public function run(int $contentId, ?int $userId = null): array {
        $start = microtime(true);

        $targets = $this->getTargetLanguages();
        if (empty($targets)) {
            return ['ok' => false, 'error' => 'no_target_languages'];
        }

        $results = [];
        $ok = 0;
        $fail = 0;
        $errors = [];

        foreach ($targets as $lang) {
            try {
                $r = $this->multiTranslator->translateContent(
                    $contentId,
                    $lang['code'],
                    $userId
                );

                if (!empty($r['ok'])) {
                    $ok++;
                    $results[$lang['code']] = [
                        'ok' => true,
                        'avg' => $r['avg_score'] ?? 0,
                    ];
                } else {
                    $fail++;
                    $results[$lang['code']] = [
                        'ok' => false,
                        'error' => $r['error'] ?? '?',
                    ];
                    $errors[] = $lang['code'] . ': ' . ($r['error'] ?? '?');
                }
            } catch (Throwable $e) {
                $fail++;
                $results[$lang['code']] = ['ok' => false, 'error' => $e->getMessage()];
                $errors[] = $lang['code'] . ': ' . $e->getMessage();
            }

            usleep(300000);
        }

        $duration = round(microtime(true) - $start, 2);

        $this->logActivity('auto_translate_done', $contentId, $userId,
            "ok=$ok, fail=$fail, duration={$duration}s");

        $this->notifyTelegram($contentId, $ok, $fail, $duration, $errors);

        return [
            'ok' => true,
            'content_id' => $contentId,
            'total' => count($targets),
            'ok_count' => $ok,
            'fail_count' => $fail,
            'duration' => $duration,
            'results' => $results,
            'errors' => $errors,
        ];
    }

    // ============================================================
    //   Log
    // ============================================================
    private function logActivity(string $type, int $contentId, ?int $userId, string $message) {
        try {
            $this->pdo->prepare("
                INSERT INTO activity_log (user_id, action, entity_type, entity_id, description, ip, created_at)
                VALUES (?, ?, 'content', ?, ?, ?, NOW())
            ")->execute([
                $userId,
                $type,
                $contentId,
                $message,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            ]);
        } catch (Throwable $e) {
            // silent
        }
    }

    // ============================================================
    //   Telegram
    // ============================================================
    private function notifyTelegram(int $contentId, int $ok, int $fail, float $duration, array $errors = []) {
        if (!$this->telegram) return;

        try {
            $stmt = $this->pdo->prepare("SELECT title FROM content_items WHERE id = ? LIMIT 1");
            $stmt->execute([$contentId]);
            $title = $stmt->fetchColumn() ?: "#$contentId";

            $icon = $fail === 0 ? '✅' : ($ok > 0 ? '⚠️' : '❌');

            $msg = "{$icon} ترجمه خودکار انجام شد\n";
            $msg .= "━━━━━━━━━━━━━━━━\n";
            $msg .= "📝 مقاله: " . mb_substr($title, 0, 60) . "\n";
            $msg .= "🆔 ID: {$contentId}\n";
            $msg .= "✅ موفق: {$ok}\n";
            if ($fail > 0) {
                $msg .= "❌ خطا: {$fail}\n";
            }
            $msg .= "⏱ زمان: {$duration}s\n";
            $msg .= "🕐 " . date('Y-m-d H:i:s');

            if (!empty($errors) && count($errors) <= 3) {
                $msg .= "\n\n⚠️ خطاها:\n" . implode("\n", array_slice($errors, 0, 3));
            }

            $this->telegram->sendMessage($msg);
        } catch (Throwable $e) {
            // silent
        }
    }

    // ============================================================
    //   اطلاعات وضعیت
    // ============================================================
    public function getStats(): array {
        $stats = [
            'enabled' => $this->isEnabled(),
            'target_count' => count($this->getTargetLanguages()),
            'translated_content_count' => 0,
            'recent_activity' => [],
        ];

        try {
            $stats['translated_content_count'] = (int) $this->pdo->query("
                SELECT COUNT(DISTINCT content_id) FROM content_translations
            ")->fetchColumn();
        } catch (Throwable $e) {}

        try {
            $stmt = $this->pdo->query("
                SELECT action, entity_id, description, created_at
                FROM activity_log
                WHERE action IN ('auto_translate_queued', 'auto_translate_done')
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stats['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {}

        return $stats;
    }
}
