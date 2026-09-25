<?php
/**
 * Ollama LLM Provider
 */
require_once __DIR__ . '/LLMProviderInterface.php';

class OllamaProvider implements LLMProviderInterface {

    private $endpoint;
    private $timeout;
    private $defaultModel;

    public function __construct(array $config = []) {
        $this->endpoint     = rtrim($config['endpoint'] ?? 'http://localhost:11434', '/');
        $this->timeout      = (int) ($config['timeout'] ?? 300);
        $this->defaultModel = $config['model'] ?? 'qwen2.5:1.5b';
    }

    public function getName(): string {
        return 'Ollama';
    }

    public function getSlug(): string {
        return 'ollama';
    }

    public function listModels(): array {
        try {
            $ch = curl_init("{$this->endpoint}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code !== 200) return ['ok' => false, 'error' => "HTTP {$code}"];

            $data = json_decode($resp, true);
            $models = [];
            foreach ($data['models'] ?? [] as $m) {
                $models[] = [
                    'name' => $m['name'],
                    'size' => $m['size'] ?? 0,
                    'size_human' => $this->humanSize($m['size'] ?? 0),
                    'family' => $m['details']['family'] ?? '',
                    'parameters' => $m['details']['parameter_size'] ?? '',
                ];
            }
            return ['ok' => true, 'models' => $models];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function chat(array $messages, array $options = []): array {
        $model = $options['model'] ?? $this->defaultModel;
        $prompt = $this->buildPrompt($messages);

        $payload = [
            'model' => $model,
            'prompt' => $prompt,
            'stream' => false,
            'keep_alive' => '30m',
            'options' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'num_predict' => $options['num_predict'] ?? 1000,
            ],
        ];

        try {
            $ch = curl_init("{$this->endpoint}/api/generate");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "HTTP {$code}: {$err}"];
            }

            $data = json_decode($resp, true);
            if (!isset($data['response'])) {
                return ['ok' => false, 'error' => 'پاسخ نامعتبر'];
            }

            return ['ok' => true, 'response' => trim($data['response']), 'model' => $model];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function testConnection(): array {
        try {
            $ch = curl_init("{$this->endpoint}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code === 200) {
                return ['ok' => true, 'message' => 'اتصال موفق'];
            }
            return ['ok' => false, 'error' => "HTTP {$code}"];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function pullModel(string $modelName): array {
        if (!preg_match('/^[a-zA-Z0-9._:\/-]+$/', $modelName)) {
            return ['ok' => false, 'error' => 'نام مدل نامعتبر'];
        }

        try {
            $ch = curl_init("{$this->endpoint}/api/pull");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode(['name' => $modelName, 'stream' => false]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 1800,
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            return $code === 200
                ? ['ok' => true, 'message' => 'دانلود موفق']
                : ['ok' => false, 'error' => "HTTP {$code}"];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildPrompt(array $messages): string {
        $prompt = "You are a helpful AI assistant for a multilingual CMS. ";
        $prompt .= "Answer concisely and accurately in the user's language.\n\n";

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';
            $content = $m['content'] ?? '';
            if ($role === 'user') $prompt .= "User: {$content}\n";
            elseif ($role === 'assistant') $prompt .= "Assistant: {$content}\n";
        }
        return $prompt . "Assistant: ";
    }

    private function humanSize(int $bytes): string {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 0) . ' MB';
        return round($bytes / 1024, 0) . ' KB';
    }
}
