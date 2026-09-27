<?php
/**
 * PartoCMS - IDPay Payment Gateway
 * درگاه پرداخت آیدی‌پی
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @link https://idpay.ir/web-service/v1.1
 */

class Gateway_Idpay
{
    private array $config;
    private bool $testMode;
    private string $apiKey;
    private string $baseUrl;

    public function __construct(array $config, bool $testMode = true)
    {
        $this->config = $config;
        $this->testMode = $testMode;
        $this->apiKey = $config['api_key'] ?? '';
        $this->baseUrl = 'https://api.idpay.ir/v1.1';
    }

    /**
     * درخواست پرداخت
     */
    public function request(array $order): array
    {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API Key تنظیم نشده است'];
        }

        $amount = (int) $order['total'];
        $callbackUrl = SITE_URL . '/modules/shop/payment/verify.php?gateway=idpay&order=' . $order['id'];

        $data = [
            'order_id' => (string) $order['id'],
            'amount' => $amount,
            'callback' => $callbackUrl,
            'phone' => '',
            'desc' => 'پرداخت سفارش ' . $order['order_number'],
        ];

        $response = $this->curlRequest($this->baseUrl . '/payment', $data);

        if (!$response || empty($response['id'])) {
            $error = $response['error_message'] ?? 'خطا در ارتباط با آیدی‌پی';
            return ['ok' => false, 'error' => $error];
        }

        // ذخیره اطلاعات در session
        $_SESSION['idpay_id'] = $response['id'];

        return [
            'ok' => true,
            'redirect' => $response['link'] ?? '',
            'id' => $response['id'],
        ];
    }

    /**
     * تایید پرداخت
     */
    public function verify(array $order, array $params = []): array
    {
        $id = $params['id'] ?? $_POST['id'] ?? $_GET['id'] ?? $_SESSION['idpay_id'] ?? '';
        $orderId = $params['order_id'] ?? $_POST['order_id'] ?? $_GET['order_id'] ?? '';

        if (empty($id)) {
            return ['ok' => false, 'error' => 'شناسه پرداخت یافت نشد'];
        }

        $data = [
            'id' => $id,
            'order_id' => (string) $order['id'],
        ];

        $response = $this->curlRequest($this->baseUrl . '/payment/verify', $data);

        if (!$response || empty($response['status'])) {
            return ['ok' => false, 'error' => 'خطا در تایید پرداخت'];
        }

        $status = (int) $response['status'];
        if ($status !== 100 && $status !== 101) {
            return ['ok' => false, 'error' => 'پرداخت ناموفق (کد: ' . $status . ')'];
        }

        return [
            'ok' => true,
            'reference_id' => (string) ($response['track_id'] ?? ''),
            'transaction_id' => $id,
            'card_pan' => $response['payment']['card_no'] ?? '',
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
                'X-API-KEY: ' . $this->apiKey,
                'X-SANDBOX: ' . ($this->testMode ? '1' : '0'),
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) return null;

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * نام درگاه
     */
    public function getName(): string
    {
        return 'آیدی‌پی';
    }
}
