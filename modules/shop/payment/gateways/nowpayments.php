<?php
/**
 * PartoCMS - NowPayments Crypto Gateway
 * درگاه پرداخت ارز دیجیتال NowPayments (USDT, BTC, ETH, ...)
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class Gateway_Nowpayments
{
    private array $config;
    private bool $testMode;
    private string $apiKey;
    private string $ipnSecret;
    private string $baseUrl;

    public function __construct(array $config, bool $testMode = true)
    {
        $this->config = $config;
        $this->testMode = $testMode;
        $this->apiKey = $config['api_key'] ?? '';
        $this->ipnSecret = $config['ipn_secret'] ?? '';
        $this->baseUrl = 'https://api.nowpayments.io/v1';
    }

    /**
     * درخواست پرداخت
     */
    public function request(array $order): array
    {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API Key تنظیم نشده است'];
        }

        // تبدیل مبلغ به دلار
        $usdRate = (float) ($this->config['usd_rate'] ?? 600000);
        $amountUsd = round($order['total'] / $usdRate, 2);
        if ($amountUsd < 1) $amountUsd = 1;

        $callbackUrl = SITE_URL . '/modules/shop/payment/verify.php?gateway=nowpayments&order=' . $order['id'];

        $data = [
            'price_amount' => $amountUsd,
            'price_currency' => 'usd',
            'pay_currency' => $this->config['default_currency'] ?? 'usdttrc20', // USDT TRC20
            'order_id' => (string) $order['id'],
            'order_description' => 'Order ' . $order['order_number'],
            'ipn_callback_url' => $callbackUrl,
            'success_url' => SITE_URL . '/modules/shop/payment/verify.php?gateway=nowpayments&order=' . $order['id'] . '&status=success',
            'cancel_url' => SITE_URL . '/modules/shop/cart.php',
        ];

        $response = $this->curlRequest('/payment', $data);

        if (empty($response['payment_id'])) {
            $error = $response['message'] ?? 'خطا در ساخت پرداخت NowPayments';
            return ['ok' => false, 'error' => $error];
        }

        $_SESSION['nowpayments_payment_id'] = $response['payment_id'];

        return [
            'ok' => true,
            'redirect' => $response['invoice_url'] ?? $response['pay_address'] ?? '',
            'payment_id' => $response['payment_id'],
            'pay_address' => $response['pay_address'] ?? '',
            'pay_amount' => $response['pay_amount'] ?? 0,
            'pay_currency' => $response['pay_currency'] ?? '',
        ];
    }

    /**
     * تایید پرداخت
     */
    public function verify(array $order, array $params = []): array
    {
        $paymentId = $params['payment_id'] ?? $_GET['payment_id'] ?? $_SESSION['nowpayments_payment_id'] ?? '';
        if (empty($paymentId)) {
            return ['ok' => false, 'error' => 'شناسه پرداخت یافت نشد'];
        }

        $response = $this->curlRequest('/payment/' . $paymentId, [], 'GET');

        if (empty($response['payment_status'])) {
            return ['ok' => false, 'error' => 'خطا در دریافت وضعیت پرداخت'];
        }

        $status = $response['payment_status'];

        if ($status === 'finished' || $status === 'confirmed') {
            return [
                'ok' => true,
                'reference_id' => $paymentId,
                'transaction_id' => $response['payin_hash'] ?? $paymentId,
                'message' => 'پرداخت ارز دیجیتال با موفقیت انجام شد',
            ];
        }

        if (in_array($status, ['waiting', 'confirming', 'sending'])) {
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
                'x-api-key: ' . $this->apiKey,
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
        return 'NowPayments (USDT, BTC, ETH)';
    }
}
