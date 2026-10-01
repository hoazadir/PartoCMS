<?php
/**
 * 🆕 PartoCMS - OpenRouter LLM Provider
 *
 * OpenRouter — دسترسی به ۱۰۰+ مدل از providers مختلف
 * Free Tier: 20 RPM / 200 RPD (مدل‌های :free)
 * مستندات: https://openrouter.ai/docs
 *
 * ⚠️ این فایل جدید است — به OllamaProvider دست نزده‌ایم.
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 * @date 2026-10-01
 */

require_once __DIR__ . '/LLMProviderInterface.php';

class OpenRouterProvider implements LLMProviderInterface {

    private string $apiKey;
    private string $defaultModel;
    private int $timeout;
    private string $endpoint = 'https://openrouter.ai/api/v1';
    private string $siteUrl = '';
    private string $siteName = 'PartoCMS';

    /**
     * مدل‌های معروف Free در OpenRouter
     */
    private array $knownFreeModels = [
        'meta-llama/llama-3.3-70b-instruct:free'         => 'Llama 3.3 70B (Free)',
        'meta-llama/llama-3.1-8b-instruct:free'           => 'Llama 3.1 8B (Free)',
        'google/gemma-2-9b-it:free'                       => 'Gemma 2 9B (Free)',
        'mistralai/mistral-7b-instruct:free'              => 'Mistral 7B (Free)',
        'qwen/qwen-2.5-72b-instruct:free'                 => 'Qwen 2.5 72B (Free)',
        'microsoft/phi-3-mini-128k-instruct:free'         => 'Phi-3 Mini (Free)',
        'openchat/openchat-7b:free'                       => 'OpenChat 7B (Free)',
        'undi95/toppy-m-7b:free'                          => 'Toppy M 7B (Free)',
    ];

    public function __construct(array $config = []) {
        $this->apiKey       = $config['api_key'] ?? '';
        $this->defaultModel = $config['model'] ?? 'meta-llama/llama-3.3-70b-instruct:free';
        $this->timeout      = (int) ($config['timeout'] ?? 60);

        // برای OpenRouter توصیه می‌شود Site URL و Name ارسال شود
        if (function_exists('getSetting')) {
            $this->siteUrl  = getSetting('site_url', '');
            $this->siteName = getSetting('site_name', 'PartoCMS');
        }
    }

    public function getName(): string {
        return 'OpenRouter';
    }

    public function getSlug(): string {
        return 'openrouter';
    }

    /**
     * لیست مدل‌ها از API OpenRouter
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
                    'family'      => 'openrouter',
                    'parameters'  => '',
                    'description' => $m['name'] ?? '',
                    'is_free'     => strpos($m['id'], ':free') !== false,
                ];
            }

            return ['ok' => true, 'models' => $models];

        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ارسال درخواست به OpenRouter (OpenAI Compatible)
     */
    public function chat(array $messages, array $options = []): array {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'OpenRouter API key تنظیم نشده'];
        }

        $model = $options['model'] ?? $this->defaultModel;

        $payload = [
            'model'       => $model,
            'messages'    => $this->normalizeMessages($messages),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['num_predict'] ?? 1024,
        ];

        if (!empty($options['format']) && $options['format'] === 'json') {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ];

            // OpenRouter توصیه می‌کند این هدرها ارسال شوند
            if ($this->siteUrl) {
                $headers[] = 'HTTP-Referer: ' . $this->siteUrl;
            }
            if ($this->siteName) {
                $headers[] = 'X-Title: ' . $this->siteName;
            }

            $ch = curl_init("{$this->endpoint}/chat/completions");
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER     => $headers,
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
                return ['ok' => false, 'error' => 'پاسخ خالی از OpenRouter'];
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
                    'ok'       => true,
                    'models'   => count($data['data'] ?? []),
                    'provider' => 'openrouter',
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

    public function pullModel(string $modelName): array {
        return [
            'ok'    => false,
            'error' => 'OpenRouter مدل‌ها را از Cloud فراهم می‌کند — pull لازم نیست',
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function normalizeMessages(array $messages): array {
        $normalized = [];

        foreach ($messages as $msg) {
            $role    = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';

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
