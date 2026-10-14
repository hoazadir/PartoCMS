<?php
/**
 * PartoCMS - Newsletter Admin
 * Endpoint: حذف کمپین (POST only, with CSRF check)
 */

require_once __DIR__ . '/_bootstrap.php';

use PartoCMS\Newsletter\Domain\Service\CampaignService;

// فقط POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    nl_flash('فقط درخواست POST مجاز است', 'error');
    header('Location: ' . nl_admin_url('campaigns.php'));
    exit;
}

$id = (int) ($_POST['id'] ?? 0);

if ($id < 1) {
    nl_flash('شناسه کمپین نامعتبر است', 'error');
    header('Location: ' . nl_admin_url('campaigns.php'));
    exit;
}

try {
    /** @var CampaignService $svc */
    $svc = $GLOBALS['nl_container']->get(CampaignService::class);

    // بررسی وجود
    $list = $svc->listCampaigns(1, 1, []);
    $campaign = $GLOBALS['nl_container']
        ->get(\PartoCMS\Newsletter\Infrastructure\Persistence\PdoCampaignRepository::class)
        ->findById($id);

    if ($campaign === null) {
        nl_flash('کمپین یافت نشد', 'error');
    } else {
        $svc->delete($id);
        nl_flash('کمپین «' . $campaign->title() . '» حذف شد');
    }
} catch (Throwable $e) {
    nl_flash('خطا در حذف: ' . $e->getMessage(), 'error');
}

header('Location: ' . nl_admin_url('campaigns.php'));
exit;
