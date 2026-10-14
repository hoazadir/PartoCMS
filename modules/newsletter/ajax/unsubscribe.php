<?php
/**
 * PartoCMS - Newsletter AJAX
 * لغو عضویت از خبرنامه (token-based)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../modules/newsletter/bootstrap.php';

use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSubscriberRepository;
use PartoCMS\Newsletter\Domain\Service\SubscriptionService;

$token = trim($_GET['token'] ?? '');

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
       && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($token === '') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'توکن نامعتبر است']);
        exit;
    }
    $success = false;
    $message = 'توکن لغو عضویت یافت نشد یا نامعتبر است.';
} else {
    try {
        $pdo  = getDB();
        $repo = new PdoSubscriberRepository($pdo);
        $svc  = new SubscriptionService($repo);

        $result  = $svc->unsubscribe($token);
        $success = $result['success'];

        $messages = [
            'unsubscribed'         => '✅ عضویت شما در خبرنامه لغو شد. دیگر ایمیلی از ما دریافت نخواهید کرد.',
            'already_unsubscribed' => '✅ عضویت شما قبلاً لغو شده بود.',
            'invalid_token'        => '❌ توکن لغو عضویت نامعتبر یا منقضی شده است.',
        ];
        $message = $messages[$result['message']] ?? 'درخواست شما پردازش شد.';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => $result['success'],
                'message' => $message,
            ]);
            exit;
        }
    } catch (Throwable $e) {
        error_log('Newsletter unsubscribe error: ' . $e->getMessage());
        $success = false;
        $message = 'خطای سرور در لغو عضویت.';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'خطای سرور']);
            exit;
        }
    }
}

$pageTitle = 'لغو عضویت خبرنامه';
$siteName  = getSetting('site_name', 'PartoCMS');
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= (function_exists('getI18n') && getI18n() && getI18n()->isRtl()) ? '.rtl' : '' ?>.min.css">
    <style>
        body {
            font-family: Tahoma, sans-serif;
            background: linear-gradient(135deg, #64748b, #334155);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            text-align: center;
        }
        .icon {
            font-size: 72px;
            margin-bottom: 20px;
            display: block;
        }
        h1 {
            color: #1e293b;
            font-size: 22px;
            margin: 0 0 15px;
        }
        p {
            color: #475569;
            font-size: 15px;
            line-height: 1.7;
            margin: 0 0 25px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #64748b, #334155);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
        }
        .btn:hover { opacity: 0.9; color: #fff; }
        .hint {
            margin-top: 20px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

<div class="card">
    <span class="icon"><?= $success ? '👋' : '⚠️' ?></span>
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="<?= SITE_URL ?>" class="btn">بازگشت به سایت</a>
    <?php if ($success): ?>
        <div class="hint">
            اگر به اشتباه لغو کردید، می‌توانید دوباره در خبرنامه ثبت‌نام کنید.
        </div>
    <?php endif; ?>
</div>

</body>
</html>
