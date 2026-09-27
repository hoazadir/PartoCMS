<?php
/**
 * PartoCMS - Zarinpal Payment Gateway
 * درگاه پرداخت زرین‌پال
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @link https://docs.zarinpal.com
 */

class Gateway_Zarinpal
{
    private array $config;
    private bool $testMode;
    private string $merchantId;
    private string $baseUrl;

    public function __construct(array $config, bool $testMode = true)
    {
        $this->config = $config;
        $this->testMode = $testMode;
        $this->merchantId = $config['merchant_id'] ?? '';

        $this->baseUrl = $testMode
            ? 'https://sandbox.zarinpal.com'
            : 'https://payment.zarinpal.com';
    }

    /**
     * درخواست پرداخت
     */
    public function request(array $order): array
    {
        if (empty($this->merchantId)) {
            return ['ok' => false, 'error' => 'Merchant ID تنظیم نشده است'];
        }

        $amount = (int) $order['total'];
        $description = 'پرداخت سفارش ' . $order['order_number'];

        $callbackUrl = SITE_URL . '/modules/shop/payment/verify.php?gateway=zarinpal&order=' . $order['id'];

        $data = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'description' => $description,
            'callback_url' => $callbackUrl,
            'metadata' => [
                'order_id' => (string) $order['id'],
                'order_number' => $order['order_number'],
            ],
        ];

        $response = $this->curlRequest($this->baseUrl . '/pg/v4/payment/request.json', $data);

        if (!$response || empty($response['data'])) {
            return ['ok' => false, 'error' => $response['errors']['message'] ?? 'خطا در ارتباط با زرین‌پال'];
        }

        $authority = $response['data']['authority'] ?? '';
        if (empty($authority)) {
            return ['ok' => false, 'error' => 'Authority دریافت نشد'];
        }

        // ذخیره authority در session
        $_SESSION['payment_authority'] = $authority;

        // ذخیره authority در دیتابیس
        try {
            $pdo = getDB();
            $pdo->prepare("
                INSERT INTO shop_transactions (order_id, gateway_slug, transaction_id, amount, status, response)
                VALUES (?, 'zarinpal', ?, ?, 'pending', ?)
            ")->execute([$order['id'], $authority, $amount, json_encode($response, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) {}

        $redirectUrl = $this->baseUrl . '/pg/StartPay/' . $authority;

        return [
            'ok' => true,
            'redirect' => $redirectUrl,
            'authority' => $authority,
        ];
    }

    /**
     * تایید پرداخت
     */
    public function verify(array $order, array $params = []): array
    {
        $authority = $params['Authority'] ?? $_GET['Authority'] ?? $_SESSION['payment_authority'] ?? '';
        $status = $params['Status'] ?? $_GET['Status'] ?? '';

        if (empty($authority)) {
            return ['ok' => false, 'error' => 'Authority یافت نشد'];
        }

        if ($status !== 'OK' && $status !== 'NOK') {
            return ['ok' => false, 'error' => 'وضعیت پرداخت نامشخص'];
        }

        if ($status === 'NOK') {
            return ['ok' => false, 'error' => 'پرداخت توسط کاربر لغو شد'];
        }

        $data = [
            'merchant_id' => $this->merchantId,
            'amount' => (int) $order['total'],
            'authority' => $authority,
        ];

        $response = $this->curlRequest($this->baseUrl . '/pg/v4/payment/verify.json', $data);

        if (!$response || empty($response['data'])) {
            return ['ok' => false, 'error' => $response['errors']['message'] ?? 'خطا در تایید پرداخت'];
        }

        $code = (int) ($response['data']['code'] ?? 0);
        if ($code !== 100 && $code !== 101) {
            return ['ok' => false, 'error' => 'کد تایید نامعتبر: ' . $code];
        }

        return [
            'ok' => true,
            'reference_id' => (string) ($response['data']['ref_id'] ?? ''),
            'transaction_id' => $authority,
            'card_pan' => $response['data']['card_pan'] ?? '',
            'message' => 'پرداخت با موفقیت انجام شد',
        ];
    }

    /**
     * درخواست با cURL
     */
    private function curlRequest(string $url, array $data): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * نام درگاه
     */
    public function getName(): string
    {
        return 'زرین‌پال';
    }
}
