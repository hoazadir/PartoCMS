<?php
/**
 * 🆕 PartoCMS - Google Gemini LLM Provider
 *
 * Google Gemini API — مدل چندزبانه باکیفیت
 * Free Tier: 15 RPM / 1,500 RPD
 * مستندات: https://ai.google.dev/gemini-api/docs
 *
 * ⚠️ این فایل جدید است — به OllamaProvider دست نزده‌ایم.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

require_once __DIR__ . '/LLMProviderInterface.php';

class GeminiProvider implements LLMProviderInterface {

    private string $apiKey;
    private string $defaultModel;
    private int $timeout;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * مدل‌های معروف Gemini (رایگان)
     */
    private array $knownModels = [
        'gemini-2.5-flash'        => 'Gemini 2.5 Flash (توصیه شده)',
        'gemini-2.5-flash-lite'   => 'Gemini 2.5 Flash Lite (سریع)',
        'gemini-2.5-pro'          => 'Gemini 2.5 Pro (دقیق)',
        'gemini-1.5-flash'        => 'Gemini 1.5 Flash',
        'gemini-1.5-pro'          => 'Gemini 1.5 Pro',
    ];

    public function __construct(array $config = []) {
        $this->apiKey       = $config['api_key'] ?? '';
        $this->defaultModel = $config['model'] ?? 'gemini-2.5-flash';
        $this->timeout      = (int) ($config['timeout'] ?? 60);
    }

    public function getName(): string {
        return 'Gemini';
    }

    public function getSlug(): string {
        return 'gemini';
    }

    /**
     * لیست مدل‌های موجود از API Gemini
     */
    public function listModels(): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API key تنظیم نشده'];
        }

        try {
            $url = "{$this->baseUrl}/models?key=" . urlencode($this->apiKey);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "HTTP {$code}: {$err}"];
            }

            $data = json_decode($resp, true);
            $models = [];

            foreach ($data['models'] ?? [] as $m) {
                $id = str_replace('models/', '', $m['name'] ?? '');
                $models[] = [
                    'name'        => $id,
                    'size'        => 0,
                    'size_human'  => 'Cloud',
                    'family'      => 'gemini',
                    'parameters'  => '',
                    'description' => $m['displayName'] ?? '',
                ];
            }

            return ['ok' => true, 'models' => $models];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ارسال درخواست به Gemini
     *
     * Gemini API با OpenAI فرق دارد:
     * - URL: /models/{model}:generateContent
     * - Body: { contents: [{ parts: [{ text: "..." }] }] }
     * - Auth: ?key=API_KEY
     */
    public function chat(array $messages, array $options = []): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'Gemini API key تنظیم نشده'];
        }

        $model = $options['model'] ?? $this->defaultModel;

        // تبدیل پیام‌ها به فرمت Gemini
        $converted = $this->convertMessages($messages);

        $payload = [
            'contents' => $converted['contents'],
        ];

        // system instruction (اگر بود)
        if (!empty($converted['system'])) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $converted['system']]],
            ];
        }

        // تنظیمات تولید
        $genConfig = [
            'temperature'     => $options['temperature'] ?? 0.7,
            'maxOutputTokens' => $options['num_predict'] ?? 2048,
        ];

        // JSON Mode
        if (!empty($options['format']) && $options['format'] === 'json') {
            $genConfig['responseMimeType'] = 'application/json';
        }

        $payload['generationConfig'] = $genConfig;

        try {
            $url = "{$this->baseUrl}/models/{$model}:generateContent?key=" . urlencode($this->apiKey);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);

            if ($code !== 200) {
                $errorMsg = "HTTP {$code}";
                $errorData = json_decode($resp, true);
                if (isset($errorData['error']['message'])) {
                    $errorMsg .= ': ' . $errorData['error']['message'];
                } elseif ($err) {
                    $errorMsg .= ': ' . $err;
                }
                return ['ok' => false, 'error' => $errorMsg];
            }

            $data = json_decode($resp, true);

            // استخراج متن از پاسخ Gemini
            $content = '';
            if (isset($data['candidates'][0]['content']['parts'])) {
                foreach ($data['candidates'][0]['content']['parts'] as $part) {
                    $content .= $part['text'] ?? '';
                }
            }

            if ($content === '') {
                return ['ok' => false, 'error' => 'پاسخ خالی از Gemini'];
            }

            return [
                'ok'       => true,
                'response' => trim($content),
                'model'    => $model,
                'usage'    => $data['usageMetadata'] ?? [],
            ];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function testConnection(): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API key تنظیم نشده'];
        }

        try {
            $url = "{$this->baseUrl}/models?key=" . urlencode($this->apiKey);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code === 200) {
                $data = json_decode($resp, true);
                return [
                    'ok'       => true,
                    'models'   => count($data['models'] ?? []),
                    'provider' => 'gemini',
                ];
            }

            if ($code === 400 || $code === 403) {
                return ['ok' => false, 'error' => 'API key نامعتبر است (' . $code . ')'];
            }

            if ($code === 429) {
                return ['ok' => false, 'error' => 'Rate limit رسیده (429)'];
            }

            return ['ok' => false, 'error' => "HTTP {$code}"];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function pullModel(string $modelName): array {
        return [
            'ok'    => false,
            'error' => 'Gemini مدل‌ها را از Cloud فراهم می‌کند — pull لازم نیست',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل پیام‌های OpenAI-style به فرمت Gemini
     *
     * OpenAI:   [['role' => 'user', 'content' => '...']]
     * Gemini:   [['role' => 'user', 'parts' => [['text' => '...']]]]
     */
    private function convertMessages(array $messages): array {
        $contents = [];
        $system   = '';

        foreach ($messages as $msg) {
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            if ($content === '') continue;

            // System message جدا می‌شود
            if ($role === 'system') {
                $system .= ($system ? "\n\n" : '') . $content;
                continue;
            }

            // Gemini از 'model' به جای 'assistant' استفاده می‌کند
            if ($role === 'assistant') {
                $role = 'model';
            }

            // فقط user و model مجاز
            if (!in_array($role, ['user', 'model'], true)) {
                $role = 'user';
            }

            $contents[] = [
                'role'  => $role,
                'parts' => [['text' => (string) $content]],
            ];
        }

        return [
            'contents' => $contents,
            'system'   => $system,
        ];
    }
}
