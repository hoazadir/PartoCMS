<?php
/**
 * PartoCMS - AI Assistant
 * دستیار هوشمند با پشتیبانی از Ollama
 *
 * @version 1.0
 * @date 2026-09-19
 */
class AiAssistant {

    private $endpoint;
    private $model;
    private $timeout;
    private $enabled;
    private $cacheDir;

    public function __construct() {
        $this->enabled  = getSetting('ai_enabled', '0') === '1';
        $this->endpoint = rtrim(getSetting('ai_endpoint', 'http://localhost:11434'), '/');
        $this->model    = getSetting('ai_model', 'llama3.1:latest');
        $this->timeout  = max(5, (int) getSetting('ai_timeout', '60'));
        $this->cacheDir = __DIR__ . '/../cache/ai';
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function getModel(): string {
        return $this->model;
    }

    public function getEndpoint(): string {
        return $this->endpoint;
    }

    /**
     * تست اتصال به Ollama
     */
    public function testConnection(): array {
        try {
            $ch = curl_init("{$this->endpoint}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "Ollama پاسخ نداد (HTTP {$code})" . ($error ? ": {$error}" : '')];
            }

            $data = json_decode($resp, true);
            $models = array_map(fn($m) => $m['name'], $data['models'] ?? []);

            return [
                'ok' => true,
                'models' => $models,
                'current_model_installed' => in_array($this->model, $models),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * تحلیل خطای SQL
     */
    public function analyzeError(string $error, array $context = []): array {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        $prompt = $this->buildErrorPrompt($error, $context);
        $result = $this->ask($prompt, ['format' => 'json', 'temperature' => 0.3]);

        if (empty($result['ok'])) return $result;

        // تلاش برای پارس JSON
        $parsed = $this->extractJson($result['response']);
        if ($parsed) {
            return ['ok' => true, 'analysis' => $parsed, 'raw' => $result['response']];
        }

        return ['ok' => true, 'analysis' => ['summary' => $result['response']], 'raw' => $result['response']];
    }

    /**
     * پیشنهاد نوع داده بر اساس نام ستون
     */
    public function suggestType(string $columnName, array $context = []): array {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        $prompt = "ستون دیتابیس با نام «{$columnName}» را تحلیل کن. ";
        $prompt .= "بهترین نوع داده MySQL برای آن کدام است؟ ";
        $prompt .= "پاسخ فقط در قالب JSON: {\"type\": \"نوع\", \"length\": عدد, \"reason\": \"دلیل\"}";

        $result = $this->ask($prompt, ['format' => 'json', 'temperature' => 0.2]);
        if (empty($result['ok'])) return $result;

        $parsed = $this->extractJson($result['response']);
        return $parsed ? ['ok' => true, 'suggestion' => $parsed] : ['ok' => false, 'error' => 'پاسخ نامعتبر'];
    }

    // ═══════════════════════════════════════════════════════════
    //  Private Methods
    // ═══════════════════════════════════════════════════════════

    /**
     * لیست مدل‌های نصب‌شده با اطلاعات کامل
     */
    public function listModelsDetailed(): array {
        try {
            $ch = curl_init("{$this->endpoint}/api/tags");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "HTTP {$code}"];
            }

            $data = json_decode($resp, true);
            $models = [];
            foreach ($data['models'] ?? [] as $m) {
                $models[] = [
                    'name' => $m['name'],
                    'size' => $m['size'] ?? 0,
                    'size_human' => $this->humanSize($m['size'] ?? 0),
                    'family' => $m['details']['family'] ?? '',
                    'parameters' => $m['details']['parameter_size'] ?? '',
                    'quantization' => $m['details']['quantization_level'] ?? '',
                    'modified' => $m['modified_at'] ?? '',
                ];
            }

            // مرتب‌سازی: مدل فعلی اول
            usort($models, function($a, $b) {
                if ($a['name'] === $this->model) return -1;
                if ($b['name'] === $this->model) return 1;
                return strcmp($a['name'], $b['name']);
            });

            return ['ok' => true, 'models' => $models];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * دانلود مدل جدید از Ollama
     */
    public function pullModel(string $modelName): array {
        // اعتبارسنجی نام مدل
        if (!preg_match('/^[a-zA-Z0-9._:\\/-]+$/', $modelName)) {
            return ['ok' => false, 'error' => 'نام مدل نامعتبر است'];
        }

        try {
            $ch = curl_init("{$this->endpoint}/api/pull");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'name' => $modelName,
                    'stream' => false,
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 1800, // 30 دقیقه
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "خطا در دانلود (HTTP {$code}): {$err}"];
            }

            return ['ok' => true, 'message' => 'مدل با موفقیت دانلود شد'];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * تست سرعت مدل
     */
    public function testSpeed(string $modelName = ''): array {
        $model = $modelName ?: $this->model;

        $payload = [
            'model' => $model,
            'prompt' => 'پاسخ فقط: 2+2=؟',
            'stream' => false,
            'options' => ['num_predict' => 10],
        ];

        $start = microtime(true);

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

            $elapsed = round(microtime(true) - $start, 1);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "HTTP {$code}", 'elapsed' => $elapsed];
            }

            $data = json_decode($resp, true);
            return [
                'ok' => true,
                'model' => $model,
                'elapsed' => $elapsed,
                'response' => trim($data['response'] ?? ''),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'elapsed' => round(microtime(true) - $start, 1)];
        }
    }

    /**
     * تبدیل بایت به فرمت خوانا
     */
    private function humanSize(int $bytes): string {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
        if ($bytes >= 1048576) return round($bytes / 1048576, 0) . ' MB';
        if ($bytes >= 1024) return round($bytes / 1024, 0) . ' KB';
        return $bytes . ' B';
    }

    /**
     * ارسال پیام چت (با تاریخچه)
     */
    public function chat(array $messages, array $options = []): array {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        try {
            // ساخت پرامپت از تاریخچه
            $prompt = $this->buildChatPrompt($messages);

            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'keep_alive' => '30m',
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'num_predict' => $options['num_predict'] ?? 1000,
                ],
            ];

            $ch = curl_init("{$this->endpoint}/api/generate");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "خطا در ارتباط (HTTP {$code}): {$err}"];
            }

            $data = json_decode($resp, true);
            if (!isset($data['response'])) {
                return ['ok' => false, 'error' => 'پاسخ نامعتبر از Ollama'];
            }

            return [
                'ok' => true,
                'response' => trim($data['response']),
                'model' => $this->model,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * چت با خروجی JSON تضمینی
     * مناسب برای مواردی که خروجی ساختاریافته لازم است
     */
    public function chatJson(array $messages, array $options = []): array {
        if (!$this->enabled) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        try {
            $prompt = $this->buildChatPrompt($messages);

            $payload = [
                'model' => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'keep_alive' => '30m',
                'format' => 'json',
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.7,
                    'num_predict' => $options['num_predict'] ?? 1500,
                ],
            ];

            $ch = curl_init("{$this->endpoint}/api/generate");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "خطا در ارتباط (HTTP {$code}): {$err}"];
            }

            $data = json_decode($resp, true);
            if (!isset($data['response'])) {
                return ['ok' => false, 'error' => 'پاسخ نامعتبر از Ollama'];
            }

            $responseText = trim($data['response']);
            $parsed = json_decode($responseText, true);

            if (!is_array($parsed)) {
                return [
                    'ok' => false,
                    'error' => 'پاسخ JSON قابل پارس نبود',
                    'raw' => $responseText,
                ];
            }

            return [
                'ok' => true,
                'data' => $parsed,
                'raw' => $responseText,
                'model' => $this->model,
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * ساخت پرامپت از تاریخچه پیام‌ها
     */
    private function buildChatPrompt(array $messages): string {
        $system = "تو یک دستیار هوشمند برای CMS فارسی هستی. ";
        $system .= "کاربر ممکن است سوال بپرسد یا فرمان بدهد. ";
        $system .= "اگر سوال است، دقیق و مفید پاسخ بده. ";
        $system .= "اگر فرمان است، مراحل اجرا را توضیح بده. ";
        $system .= "همیشه فارسی و کوتاه پاسخ بده.

";

        $prompt = $system;

        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            if ($role === 'user') {
                $prompt .= "کاربر: {$content}
";
            } elseif ($role === 'assistant') {
                $prompt .= "دستیار: {$content}
";
            }
        }

        $prompt .= "دستیار: ";
        return $prompt;
    }

    /**
     * ذخیره پیام در تاریخچه
     */
    public function saveMessage(int $conversationId, string $role, string $content): int {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO ai_messages (conversation_id, role, content)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$conversationId, $role, $content]);

            // بروزرسانی تعداد پیام
            $pdo->prepare("UPDATE ai_conversations SET message_count = message_count + 1 WHERE id = ?")->execute([$conversationId]);

            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * ساخت مکالمه جدید
     */
    public function createConversation(int $userId, string $title = 'مکالمه جدید'): int {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                INSERT INTO ai_conversations (user_id, title, model)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $title, $this->model]);
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * لیست مکالمات کاربر
     */
    public function listConversations(int $userId, int $limit = 50): array {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("
                SELECT id, title, model, message_count, created_at, updated_at
                FROM ai_conversations
                WHERE user_id = ?
                ORDER BY updated_at DESC
                LIMIT ?
            ");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * پیام‌های یک مکالمه
     */
    public function getMessages(int $conversationId, int $userId): array {
        try {
            $pdo = getDB();
            // بررسی مالکیت
            $stmt = $pdo->prepare("SELECT id FROM ai_conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$conversationId, $userId]);
            if (!$stmt->fetchColumn()) return [];

            $stmt = $pdo->prepare("
                SELECT role, content, created_at
                FROM ai_messages
                WHERE conversation_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$conversationId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * حذف مکالمه
     */
    public function deleteConversation(int $conversationId, int $userId): bool {
        try {
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT id FROM ai_conversations WHERE id = ? AND user_id = ?");
            $stmt->execute([$conversationId, $userId]);
            if (!$stmt->fetchColumn()) return false;

            $pdo->prepare("DELETE FROM ai_messages WHERE conversation_id = ?")->execute([$conversationId]);
            $pdo->prepare("DELETE FROM ai_conversations WHERE id = ?")->execute([$conversationId]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function buildErrorPrompt(string $error, array $context): string {
        $prompt  = "تو یک متخصص دیتابیس MySQL/MariaDB هستی. ";
        $prompt .= "خطای زیر هنگام ساخت جدول در یک CMS رخ داده است:\n\n";
        $prompt .= "خطا: {$error}\n\n";

        if (!empty($context['table'])) {
            $prompt .= "نام جدول: {$context['table']}\n";
        }
        if (!empty($context['columns'])) {
            $prompt .= "ستون‌ها:\n";
            foreach ($context['columns'] as $col) {
                $prompt .= "  - {$col['name']} ({$col['type']})\n";
            }
        }

        $prompt .= "\nپاسخ را دقیقاً در قالب JSON زیر بده (فقط JSON، بدون توضیح اضافه):\n";
        $prompt .= "{\n";
        $prompt .= "  \"summary\": \"خلاصه خطا در یک جمله\",\n";
        $prompt .= "  \"cause\": \"علت اصلی خطا\",\n";
        $prompt .= "  \"solution\": \"راه‌حل گام‌به‌گام\",\n";
        $prompt .= "  \"field\": \"نام فیلد مشکل‌دار (اگر مشخص است)\",\n";
        $prompt .= "  \"severity\": \"low|medium|high\"\n";
        $prompt .= "}";

        return $prompt;
    }

    private function ask(string $prompt, array $options = []): array {
        try {
            $payload = [
                'model'  => $this->model,
                'prompt' => $prompt,
                'stream' => false,
                'keep_alive' => '30m',
                'options' => [
                    'temperature' => $options['temperature'] ?? 0.3,
                    'num_predict' => 500,
                ],
            ];

            // اگر format=json درخواست شده
            if (!empty($options['format'])) {
                $payload['format'] = $options['format'];
            }

            $ch = curl_init("{$this->endpoint}/api/generate");
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);

            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);

            if ($code !== 200) {
                return ['ok' => false, 'error' => "خطا در ارتباط با Ollama (HTTP {$code})" . ($err ? ": {$err}" : '')];
            }

            $data = json_decode($resp, true);
            if (!isset($data['response'])) {
                return ['ok' => false, 'error' => 'پاسخ نامعتبر از Ollama'];
            }

            return ['ok' => true, 'response' => trim($data['response'])];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function extractJson(string $text): ?array {
        // تلاش مستقیم
        $decoded = json_decode($text, true);
        if (is_array($decoded)) return $decoded;

        // استخراج JSON از متن (اگر مدل توضیح اضافه داد)
        if (preg_match('/\{[\s\S]*\}/m', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) return $decoded;
        }

        return null;
    }
}
