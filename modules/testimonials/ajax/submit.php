<?php
/**
 * PartoCMS - Testimonials AJAX Submit
 * ثبت نظر از فرانت‌اند (بدون نیاز به لاگین)
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

// JSON response header
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// فقط POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../../../config.php';
    require_once __DIR__ . '/../bootstrap.php';
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
    error_log('Testimonials AJAX bootstrap: ' . $e->getMessage());
    exit;
}

// ================================================================
// دریافت + sanitize ورودی‌ها
// ================================================================
$input = [
    'name'      => trim((string) ($_POST['name'] ?? '')),
    'email'     => trim((string) ($_POST['email'] ?? '')),
    'company'   => trim((string) ($_POST['company'] ?? '')),
    'position'  => trim((string) ($_POST['position'] ?? '')),
    'content'   => trim((string) ($_POST['content'] ?? '')),
    'rating'    => isset($_POST['rating']) ? (int) $_POST['rating'] : null,
    'avatar_url'=> trim((string) ($_POST['avatar_url'] ?? '')),
    'language'  => trim((string) ($_POST['language'] ?? 'fa-IR')),
];

// ================================================================
// اعتبارسنجی اولیه
// ================================================================
$errors = [];

if ($input['name'] === '') {
    $errors[] = 'نام الزامی است';
} elseif (mb_strlen($input['name']) > 100) {
    $errors[] = 'نام حداکثر ۱۰۰ کاراکتر';
}

if ($input['content'] === '') {
    $errors[] = 'متن نظر الزامی است';
}

if ($input['email'] !== '' && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'ایمیل نامعتبر است';
}

if ($input['avatar_url'] !== '' && !filter_var($input['avatar_url'], FILTER_VALIDATE_URL)) {
    $errors[] = 'URL آواتار نامعتبر است';
}

if ($input['rating'] !== null && ($input['rating'] < 1 || $input['rating'] > 5)) {
    $errors[] = 'امتیاز باید بین ۱ تا ۵ باشد';
}

// تنظیمات ماژول
$requireEmail = (bool) testimonials_setting('require_email', false);
if ($requireEmail && $input['email'] === '') {
    $errors[] = 'ایمیل الزامی است';
}

$allowGuest = (bool) testimonials_setting('allow_guest', true);
if (!$allowGuest && !function_exists('isLoggedIn')) {
    // نمی‌توان guest را تشخیص داد — اجازه بده
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $errors[0],
        'errors'  => $errors,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ================================================================
// تشخیص IP
// ================================================================
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if ($ip !== null) {
    // اگر چند IP دارد (proxy chain)، اولی را بردار
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    // فقط IP معتبر
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = null;
    }
}

// ================================================================
// ذخیره از طریق Service (شامل anti-spam)
// ================================================================
try {
    $service = testimonials_service();

    $id = $service->submitFromFrontend([
        'name'       => $input['name'],
        'email'      => $input['email'] ?: null,
        'company'    => $input['company'] ?: null,
        'position'   => $input['position'] ?: null,
        'content'    => $input['content'],
        'rating'     => $input['rating'],
        'avatar_url' => $input['avatar_url'] ?: null,
        'language'   => $input['language'] ?: 'fa-IR',
    ], $ip);

    // پیام بر اساس تنظیم auto_approve
    $autoApprove = (bool) testimonials_setting('auto_approve', false);
    $message = $autoApprove
        ? 'نظر شما با موفقیت ثبت و منتشر شد.'
        : 'نظر شما با موفقیت ثبت شد. پس از تأیید نمایش داده می‌شود.';

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => $message,
        'id'      => $id,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\PartoCMS\Testimonials\Domain\Exception\TestimonialException $e) {
    // خطای domain (anti-spam، validation، ...)
    $code = $e->getCode();
    http_response_code($code >= 400 && $code < 600 ? $code : 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (\Throwable $e) {
    error_log('Testimonials submit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطای داخلی — لطفاً بعداً تلاش کنید',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
