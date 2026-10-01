<?php
/**
 * 🆕 PartoCMS - Groq LLM Provider
 *
 * Groq Cloud API — فوق‌سریع با LPU
 * Free Tier: 30 RPM / 14,400 RPD
 * مستندات: https://console.groq.com/docs/openai
 *
 * ⚠️ این فایل جدید است — به OllamaProvider دست نزده‌ایم.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

require_once __DIR__ . '/LLMProviderInterface.php';

class GroqProvider implements LLMProviderInterface {

    private string $apiKey;
    private string $defaultModel;
    private int $timeout;
    private string $endpoint = 'https://api.groq.com/openai/v1';

    /**
     * مدل‌های معروف Groq (رایگان)
     */
    private array $knownModels = [
        'llama-3.3-70b-versatile'   => 'Llama 3.3 70B (توصیه شده)',
        'llama-3.1-8b-instant'       => 'Llama 3.1 8B (سریع)',
        'llama3-70b-8192'            => 'Llama 3 70B',
        'llama3-8b-8192'             => 'Llama 3 8B',
        'mixtral-8x7b-32768'         => 'Mixtral 8x7B',
        'gemma2-9b-it'               => 'Gemma 2 9B',
        'qwen/qwen3-32b'             => 'Qwen 3 32B',
    ];

    public function __construct(array $config = []) {
        $this->apiKey       = $config['api_key'] ?? '';
        $this->defaultModel = $config['model'] ?? 'llama-3.3-70b-versatile';
        $this->timeout      = (int) ($config['timeout'] ?? 60);
    }

    public function getName(): string {
        return 'Groq';
    }

    public function getSlug(): string {
        return 'groq';
    }

    /**
     * Groq لیست مدل‌ها را از API می‌گیرد
     */
    public function listModels(): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API key تنظیم نشده'];
        }

        try {
            $ch = curl_init("{$this->endpoint}/models");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->apiKey,
                ],
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

            foreach ($data['data'] ?? [] as $m) {
                $models[] = [
                    'name'        => $m['id'],
                    'size'        => 0,
                    'size_human'  => 'Cloud',
                    'family'      => 'groq',
                    'parameters'  => '',
                    'description' => $this->knownModels[$m['id']] ?? '',
                ];
            }

            return ['ok' => true, 'models' => $models];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ارسال درخواست به Groq (OpenAI Compatible)
     */
    public function chat(array $messages, array $options = []): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'Groq API key تنظیم نشده'];
        }

        $model = $options['model'] ?? $this->defaultModel;

        $payload = [
            'model'       => $model,
            'messages'    => $this->normalizeMessages($messages),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['num_predict'] ?? 1024,
            'stream'      => false,
        ];

        // پشتیبانی JSON mode
        if (!empty($options['format']) && $options['format'] === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $ch = curl_init("{$this->endpoint}/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
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
            $content = $data['choices'][0]['message']['content'] ?? '';

            if ($content === '') {
                return ['ok' => false, 'error' => 'پاسخ خالی از Groq'];
            }

            return [
                'ok'       => true,
                'response' => trim($content),
                'model'    => $model,
                'usage'    => $data['usage'] ?? [],
            ];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * تست اتصال به Groq
     */
    public function testConnection(): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API key تنظیم نشده'];
        }

        try {
            $ch = curl_init("{$this->endpoint}/models");
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code === 200) {
                $data = json_decode($resp, true);
                return [
                    'ok'     => true,
                    'models' => count($data['data'] ?? []),
                    'provider' => 'groq',
                ];
            }

            if ($code === 401) {
                return ['ok' => false, 'error' => 'API key نامعتبر است (401)'];
            }

            if ($code === 429) {
                return ['ok' => false, 'error' => 'Rate limit رسیده (429)'];
            }

            return ['ok' => false, 'error' => "HTTP {$code}"];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Groq نیازی به pull ندارد (مدل‌ها ابری هستند)
     */
    public function pullModel(string $modelName): array {
        return [
            'ok'    => false,
            'error' => 'Groq مدل‌ها را از Cloud فراهم می‌کند — pull لازم نیست',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Helper Methods
    // ═══════════════════════════════════════════════════════════

    /**
     * تبدیل پیام‌ها به فرمت OpenAI
     */
    private function normalizeMessages(array $messages): array {
        $normalized = [];

        foreach ($messages as $msg) {
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

            // نقش‌های مجاز Groq: system, user, assistant
            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }

            $normalized[] = [
                'role'    => $role,
                'content' => (string) $content,
            ];
        }

        return $normalized;
    }
}
