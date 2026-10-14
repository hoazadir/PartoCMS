<?php
/**
 * PartoCMS - Newsletter AJAX
 * ثبت‌نام در خبرنامه (فرانت‌اند)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../modules/newsletter/bootstrap.php';
require_once __DIR__ . '/../../../admin/includes/rate_limiter.php';

use PartoCMS\Newsletter\Infrastructure\Persistence\PdoSubscriberRepository;
use PartoCMS\Newsletter\Domain\Service\SubscriptionService;
use PartoCMS\Newsletter\Domain\ValueObject\Email;
use PartoCMS\Newsletter\Domain\Exception\InvalidEmailException;

header('Content-Type: application/json; charset=utf-8');

// ─── فقط POST ───
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'فقط POST مجاز است']);
    exit;
}

// ─── Rate limiting ───
try {
    $pdo     = getDB();
    $limiter = new RateLimiter($pdo, 5, 60); // 5 attempt per hour
    $ip      = RateLimiter::getClientIp();

    if (!$limiter->check($ip)) {
        http_response_code(429);
        echo json_encode([
            'ok' => false,
            'error' => 'تعداد درخواست‌ها زیاد است. لطفاً بعداً تلاش کنید.',
        ]);
        exit;
    }
} catch (Throwable $e) {
    error_log('Newsletter subscribe rate limit error: ' . $e->getMessage());
    $pdo = getDB();
}

// ─── دریافت داده ───
$email  = trim($_POST['email'] ?? '');
$name   = trim($_POST['name'] ?? '');
$source = trim($_POST['source'] ?? 'website');

if ($email === '') {
    echo json_encode(['ok' => false, 'error' => 'ایمیل الزامی است']);
    exit;
}

// ─── اعتبارسنجی ایمیل ───
try {
    $emailVO = Email::fromString($email);
} catch (InvalidEmailException $e) {
    echo json_encode(['ok' => false, 'error' => 'ایمیل نامعتبر است']);
    exit;
}

// ─── ثبت‌نام ───
try {
    $repo = new PdoSubscriberRepository($pdo);

    // چک تنظیمات double opt-in
    $settings = [];
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute(['newsletter_double_optin']);
        $doiValue = $stmt->fetchColumn();
        $doubleOptIn = $doiValue === '1';
    } catch (Throwable $e) {
        $doubleOptIn = false;
    }

    $svc = new SubscriptionService($repo, $doubleOptIn);

    $result = $svc->subscribe(
        email: $emailVO,
        name: $name !== '' ? $name : null,
        source: $source,
        ip: $_SERVER['REMOTE_ADDR'] ?? null,
        userAgent: $_SERVER['HTTP_USER_AGENT'] ?? null
    );

    // ثبت در rate limiter
    try { $limiter->record('subscribe:' . $email, true, $ip); } catch (Throwable $e) {}

    // پیام موفقیت
    $messages = [
        'subscribed'             => '✅ با موفقیت در خبرنامه عضو شدید',
        'already_subscribed'     => '✅ شما قبلاً در خبرنامه عضو هستید',
        'reactivated'            => '✅ عضویت شما فعال شد',
        'confirmation_sent'      => '📧 لطفاً ایمیل خود را بررسی کنید و لینک تأیید را کلیک کنید',
        'reconfirmation_required' => '📧 لطفاً ایمیل خود را بررسی کنید و لینک تأیید را کلیک کنید',
    ];

    $message = $messages[$result['message']] ?? '✅ درخواست شما ثبت شد';

    echo json_encode([
        'ok'                 => true,
        'message'            => $message,
        'needs_confirmation' => $result['needs_confirmation'],
    ]);
} catch (Throwable $e) {
    error_log('Newsletter subscribe error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطای سرور']);
}
