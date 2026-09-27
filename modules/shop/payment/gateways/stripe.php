<?php
/**
 * PartoCMS - Stripe Payment Gateway
 * درگاه پرداخت بین‌المللی استرایپ
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class Gateway_Stripe
{
    private array $config;
    private bool $testMode;
    private string $secretKey;
    private string $publishableKey;
    private string $baseUrl;

    public function __construct(array $config, bool $testMode = true)
    {
        $this->config = $config;
        $this->testMode = $testMode;
        $this->secretKey = $config['secret_key'] ?? '';
        $this->publishableKey = $config['publishable_key'] ?? '';
        $this->baseUrl = 'https://api.stripe.com/v1';
    }

    /**
     * درخواست پرداخت (Checkout Session)
     */
    public function request(array $order): array
    {
        if (empty($this->secretKey)) {
            return ['ok' => false, 'error' => 'Secret Key تنظیم نشده است'];
        }

        // تبدیل مبلغ به دلار
        $usdRate = (float) ($this->config['usd_rate'] ?? 600000);
        $amountUsd = round($order['total'] / $usdRate, 2);
        if ($amountUsd < 0.5) $amountUsd = 0.5;

        $amountCents = (int) round($amountUsd * 100); // Stripe به سنت

        $successUrl = SITE_URL . '/modules/shop/payment/verify.php?gateway=stripe&order=' . $order['id'] . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = SITE_URL . '/modules/shop/cart.php';

        $data = [
            'mode' => 'payment',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $order['id'],
            'metadata' => [
                'order_id' => (string) $order['id'],
                'order_number' => $order['order_number'],
            ],
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => 'Order ' . $order['order_number'],
                            'description' => 'Payment for order ' . $order['order_number'],
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->curlRequest('/checkout/sessions', $data);

        if (empty($response['id'])) {
            $error = $response['error']['message'] ?? 'خطا در ساخت Checkout Session';
            return ['ok' => false, 'error' => $error];
        }

        $_SESSION['stripe_session_id'] = $response['id'];

        return [
            'ok' => true,
            'redirect' => $response['url'] ?? '',
            'session_id' => $response['id'],
        ];
    }

    /**
     * تایید پرداخت
     */
    public function verify(array $order, array $params = []): array
    {
        $sessionId = $params['session_id'] ?? $_GET['session_id'] ?? $_SESSION['stripe_session_id'] ?? '';
        if (empty($sessionId)) {
            return ['ok' => false, 'error' => 'Session ID یافت نشد'];
        }

        $response = $this->curlRequest('/checkout/sessions/' . $sessionId, [], 'GET');

        if (empty($response['id'])) {
            return ['ok' => false, 'error' => 'خطا در دریافت اطلاعات پرداخت'];
        }

        $paymentStatus = $response['payment_status'] ?? '';

        if ($paymentStatus !== 'paid') {
            return ['ok' => false, 'error' => 'پرداخت تکمیل نشد (وضعیت: ' . $paymentStatus . ')'];
        }

        $paymentIntentId = $response['payment_intent'] ?? '';

        return [
            'ok' => true,
            'reference_id' => $sessionId,
            'transaction_id' => $paymentIntentId,
            'message' => 'پرداخت Stripe با موفقیت انجام شد',
        ];
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
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        if ($method === 'POST' && !empty($data)) {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = http_build_query($data);
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
        return 'Stripe';
    }
}
