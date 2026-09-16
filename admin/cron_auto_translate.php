<?php
/**
 * PartoCMS - Cron Auto Translate Worker
 * Usage: php cron_auto_translate.php <content_id> [user_id]
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/auto_translator.php';

$contentId = (int) ($argv[1] ?? 0);
$userId    = (int) ($argv[2] ?? 0) ?: null;

if ($contentId <= 0) {
    fwrite(STDERR, "Usage: php cron_auto_translate.php <content_id> [user_id]\n");
    exit(1);
}

echo "🚀 Auto Translate Worker\n";
echo "Content ID: $contentId\n";
echo "User ID: " . ($userId ?? 'NULL') . "\n";
echo "─────────────────────────\n";

try {
    $pdo = getDB();
    $at = new AutoTranslator($pdo);

    if (!$at->isEnabled()) {
        echo "⚠️ Auto translate disabled in settings. Exiting.\n";
        exit(0);
    }

    $result = $at->run($contentId, $userId);

    if (!empty($result['ok'])) {
        echo "✅ Done: {$result['ok_count']} ok, {$result['fail_count']} fail\n";
        echo "⏱ Duration: {$result['duration']}s\n";
        exit(0);
    } else {
        echo "❌ Error: " . ($result['error'] ?? 'unknown') . "\n";
        exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, "❌ Fatal: " . $e->getMessage() . "\n");
    exit(1);
}
