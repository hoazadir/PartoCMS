<?php
/**
 * PartoCMS - CoinGate Crypto Gateway
 * درگاه پرداخت ارز دیجیتال CoinGate
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class Gateway_Coingate
{
    private array $config;
    private bool $testMode;
    private string $apiToken;
    private string $baseUrl;

    public function __construct(array $config, bool $testMode = true)
    {
        $this->config = $config;
        $this->testMode = $testMode;
        $this->apiToken = $config['api_token'] ?? '';
        $this->baseUrl = $testMode
            ? 'https://api-sandbox.coingate.com/v2'
            : 'https://api.coingate.com/v2';
    }

    /**
     * درخواست پرداخت
     */
    public function request(array $order): array
    {
        if (empty($this->apiToken)) {
            return ['ok' => false, 'error' => 'API Token تنظیم نشده است'];
        }

        // تبدیل مبلغ به دلار
        $usdRate = (float) ($this->config['usd_rate'] ?? 600000);
        $amountUsd = round($order['total'] / $usdRate, 2);
        if ($amountUsd < 1) $amountUsd = 1;

        $callbackUrl = SITE_URL . '/modules/shop/payment/verify.php?gateway=coingate&order=' . $order['id'];

        $data = [
            'order_id' => (string) $order['id'],
            'price_amount' => $amountUsd,
            'price_currency' => 'USD',
            'receive_currency' => $this->config['receive_currency'] ?? 'EUR',
            'title' => 'Order ' . $order['order_number'],
            'description' => 'Payment for order ' . $order['order_number'],
            'callback_url' => $callbackUrl,
            'success_url' => SITE_URL . '/modules/shop/payment/verify.php?gateway=coingate&order=' . $order['id'] . '&status=success',
            'cancel_url' => SITE_URL . '/modules/shop/cart.php',
        ];

        $response = $this->curlRequest('/orders', $data);

        if (empty($response['id'])) {
            $error = $response['message'] ?? $response['reason'] ?? 'خطا در ساخت پرداخت CoinGate';
            return ['ok' => false, 'error' => $error];
        }

        $_SESSION['coingate_order_id'] = $response['id'];

        return [
            'ok' => true,
            'redirect' => $response['payment_url'] ?? '',
            'coingate_order_id' => $response['id'],
        ];
    }

    /**
     * تایید پرداخت
     */
    public function verify(array $order, array $params = []): array
    {
        $token = $params['token'] ?? $_GET['token'] ?? $_SESSION['coingate_order_id'] ?? '';
        if (empty($token)) {
            return ['ok' => false, 'error' => 'شناسه پرداخت یافت نشد'];
        }

        $response = $this->curlRequest('/orders/' . $token, [], 'GET');

        if (empty($response['status'])) {
            return ['ok' => false, 'error' => 'خطا در دریافت وضعیت پرداخت'];
        }

        $status = $response['status'];

        if ($status === 'paid' || $status === 'confirmed') {
            return [
                'ok' => true,
                'reference_id' => (string) ($response['id'] ?? $token),
                'transaction_id' => (string) ($response['id'] ?? $token),
                'message' => 'پرداخت ارز دیجیتال با موفقیت انجام شد',
            ];
        }

        if (in_array($status, ['new', 'pending', 'processing'])) {
            return ['ok' => false, 'error' => 'پرداخت در حال پردازش است', 'pending' => true];
        }

        return ['ok' => false, 'error' => 'پرداخت ناموفق (وضعیت: ' . $status . ')'];
    }

    /**
     * درخواست با cURL
     */
    private function curlRequest(string $endpoint, array $data = [], string $method = 'POST'): ?array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Token ' . $this->apiToken,
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) return null;

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function getName(): string
    {
        return 'CoinGate';
    }
}
