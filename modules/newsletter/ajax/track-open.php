<?php
/**
 * PartoCMS - Newsletter AJAX
 * ردیابی باز شدن ایمیل (tracking pixel)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../modules/newsletter/bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\SendService;

$transparentGif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

$campaignId   = (int) ($_GET['c'] ?? 0);
$subscriberId = (int) ($_GET['s'] ?? 0);

if ($campaignId > 0 && $subscriberId > 0) {
    try {
        $sendSvc = $GLOBALS['nl_container']->get(SendService::class);
        $sendSvc->recordOpen($campaignId, $subscriberId);
    } catch (Throwable $e) {
        error_log('Newsletter track-open error: ' . $e->getMessage());
    }
}

header('Content-Type: image/gif');
header('Content-Length: ' . strlen($transparentGif));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

echo $transparentGif;
exit;
