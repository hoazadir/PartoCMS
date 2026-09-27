<?php
/**
 * PartoCMS - PayPal Payment Gateway
 * درگاه پرداخت بین‌المللی پی‌پال
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class Gateway_Paypal
{
    private array $config;
    private bool $testMode;
    private string $clientId;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct(array $config, bool $testMode = true)
    {
        $this->config = $config;
        $this->testMode = $testMode;
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';

        $this->baseUrl = $testMode
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    /**
     * دریافت Access Token
     */
    private function getAccessToken(): ?string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_USERPWD => $this->clientId . ':' . $this->clientSecret,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded['access_token'] ?? null;
    }

    /**
     * درخواست پرداخت
     */
    public function request(array $order): array
    {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            return ['ok' => false, 'error' => 'تنظیمات PayPal کامل نیست'];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['ok' => false, 'error' => 'خطا در دریافت Access Token'];
        }

        // تبدیل مبلغ به دلار (فرض می‌کنیم قیمت به تومان است)
        $usdRate = (float) ($this->config['usd_rate'] ?? 600000);
        $amountUsd = round($order['total'] / $usdRate, 2);
        if ($amountUsd < 0.01) $amountUsd = 0.01;

        $returnUrl = SITE_URL . '/modules/shop/payment/verify.php?gateway=paypal&order=' . $order['id'];
        $cancelUrl = SITE_URL . '/modules/shop/cart.php';

        $data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => (string) $order['id'],
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => number_format($amountUsd, 2, '.', ''),
                ],
                'description' => 'Order ' . $order['order_number'],
            ]],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => 'PartoCMS',
                'user_action' => 'PAY_NOW',
            ],
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/checkout/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        if (empty($decoded['id'])) {
            return ['ok' => false, 'error' => 'خطا در ساخت سفارش PayPal'];
        }

        $approveUrl = '';
        foreach ($decoded['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approveUrl = $link['href'];
                break;
            }
        }

        $_SESSION['paypal_order_id'] = $decoded['id'];

        return [
            'ok' => true,
            'redirect' => $approveUrl,
            'paypal_order_id' => $decoded['id'],
        ];
    }

    /**
     * تایید پرداخت
     */
    public function verify(array $order, array $params = []): array
    {
        $paypalOrderId = $params['token'] ?? $_GET['token'] ?? $_SESSION['paypal_order_id'] ?? '';
        if (empty($paypalOrderId)) {
            return ['ok' => false, 'error' => 'شناسه PayPal یافت نشد'];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['ok' => false, 'error' => 'خطا در دریافت Access Token'];
        }

        // Capture
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/checkout/orders/' . $paypalOrderId . '/capture',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (($decoded['status'] ?? '') !== 'COMPLETED') {
            return ['ok' => false, 'error' => 'پرداخت PayPal تکمیل نشد'];
        }

        $captureId = $decoded['purchase_units'][0]['payments']['captures'][0]['id'] ?? '';

        return [
            'ok' => true,
            'reference_id' => $captureId,
            'transaction_id' => $paypalOrderId,
            'message' => 'پرداخت PayPal با موفقیت انجام شد',
        ];
    }

    public function getName(): string
    {
        return 'PayPal';
    }
}
