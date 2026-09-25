<?php
/**
 * PartoCMS - Whisper.cpp STT Provider v3.0 (Clean Modular)
 * 
 * @version 3.0
 * @date 2026-09-21
 */
require_once __DIR__ . '/STTProviderInterface.php';

class WhisperCppProvider implements STTProviderInterface {

    private $binPath;
    private $modelsDir;
    private $defaultModel;
    private $ffmpegPath;
    private $mirrorUrl;

    // ═══════════════════════════════════════════════════════════
    //  مدل‌های شناخته‌شده Whisper.cpp
    // ═══════════════════════════════════════════════════════════
    private static $knownModels = [
        'tiny'      => ['size' => 75,   'display' => 'Tiny — سریع‌ترین'],
        'tiny.en'   => ['size' => 75,   'display' => 'Tiny.en — فقط انگلیسی'],
        'base'      => ['size' => 141,  'display' => 'Base — تعادل'],
        'base.en'   => ['size' => 141,  'display' => 'Base.en — فقط انگلیسی'],
        'small'     => ['size' => 466,  'display' => 'Small — دقت خوب'],
        'small.en'  => ['size' => 466,  'display' => 'Small.en — فقط انگلیسی'],
        'medium'    => ['size' => 1500, 'display' => 'Medium — دقت بالا'],
        'medium.en' => ['size' => 1500, 'display' => 'Medium.en — فقط انگلیسی'],
        'large-v1'  => ['size' => 3000, 'display' => 'Large v1'],
        'large-v2'  => ['size' => 3000, 'display' => 'Large v2'],
        'large-v3'  => ['size' => 3000, 'display' => 'Large v3 — جدیدترین'],
    ];

    // ═══════════════════════════════════════════════════════════
    //  منابع دانلود (به ترتیب اولویت)
    // ═══════════════════════════════════════════════════════════
    private static $downloadSources = [
        [
            'name' => 'HuggingFace',
            'url'  => 'https://huggingface.co/ggerganov/whisper.cpp/resolve/main/ggml-{model}.bin',
        ],
        [
            'name' => 'hf-mirror',
            'url'  => 'https://hf-mirror.com/ggerganov/whisper.cpp/resolve/main/ggml-{model}.bin',
        ],
        [
            'name' => 'jsDelivr',
            'url'  => 'https://cdn.jsdelivr.net/gh/ggerganov/whisper.cpp@master/models/ggml-{model}.bin',
        ],
    ];

    // ═══════════════════════════════════════════════════════════
    //  Constructor
    // ═══════════════════════════════════════════════════════════
    public function __construct(array $config = []) {
        $this->binPath      = $config['bin_path'] ?? '/data/data/com.termux/files/home/whisper.cpp/build/bin/whisper-cli';
        $this->modelsDir    = $config['models_dir'] ?? '/data/data/com.termux/files/home/whisper.cpp/models';
        $this->defaultModel = $config['model'] ?? 'base';
        $this->ffmpegPath   = $config['ffmpeg_path'] ?? 'ffmpeg';
        $this->mirrorUrl    = $config['mirror_url'] ?? '';
    }

    // ═══════════════════════════════════════════════════════════
    //  شناسه
    // ═══════════════════════════════════════════════════════════
    public function getName(): string {
        return 'Whisper.cpp (آفلاین)';
    }

    public function getSlug(): string {
        return 'whisper_cpp';
    }

    // ═══════════════════════════════════════════════════════════
    //  لیست مدل‌ها
    // ═══════════════════════════════════════════════════════════
    /**
     * اندازه مورد انتظار هر مدل (از فایل جداگانه)
     */
    private function getExpectedSize(string $modelName): int {
        $sizesFile = __DIR__ . '/whisper_expected_sizes.php';
        if (!file_exists($sizesFile)) return 0;
        $sizes = require $sizesFile;
        return (int) ($sizes[$modelName] ?? 0);
    }

    public function listModels(): array {
        $result = [];
        foreach (self::$knownModels as $name => $info) {
            $path = $this->modelsDir . '/ggml-' . $name . '.bin';
            $exists = file_exists($path);
            $actualSize = $exists ? filesize($path) : 0;
            $actualMB = $exists ? round($actualSize / 1048576, 0) : 0;
            $expectedSize = $this->getExpectedSize($name);

            // ═══ بررسی یکپارچگی ═══
            $installed = false;
            $incomplete = false;
            $status = 'not_installed';

            if ($exists) {
                if ($expectedSize > 0 && $actualSize < $expectedSize) {
                    // فایل ناقص است
                    $incomplete = true;
                    $status = 'incomplete';
                } else {
                    $installed = true;
                    $status = 'installed';
                }
            }

            $result[$name] = [
                'name'              => $name,
                'display'           => $info['display'],
                'size_mb'           => $installed ? $actualMB : $info['size'],
                'actual_size_mb'    => $actualMB,
                'expected_size_mb'  => $info['size'],
                'installed'         => $installed,
                'incomplete'        => $incomplete,
                'status'            => $status,
                'is_default'        => $name === $this->defaultModel,
            ];
        }
        return ['ok' => true, 'models' => $result];
    }

    // ═══════════════════════════════════════════════════════════
    //  تبدیل صدا به متن
    // ═══════════════════════════════════════════════════════════
    public function transcribe(string $audioPath, array $options = []): array {
        if (!file_exists($audioPath)) {
            return ['ok' => false, 'error' => 'فایل صوتی یافت نشد'];
        }

        $model = $options['model'] ?? $this->defaultModel;
        $language = $options['language'] ?? 'auto';

        $modelPath = $this->modelsDir . '/ggml-' . $model . '.bin';
        if (!file_exists($modelPath)) {
            return ['ok' => false, 'error' => "مدل «{$model}» نصب نشده"];
        }

        // تبدیل به WAV 16kHz mono
        $wavFile = tempnam(sys_get_temp_dir(), 'whisper_wav_') . '.wav';
        $convertCmd = escapeshellarg($this->ffmpegPath) . ' -y -i ' . escapeshellarg($audioPath) .
                      ' -ar 16000 -ac 1 -c:a pcm_s16le ' . escapeshellarg($wavFile) . ' 2>&1';
        exec($convertCmd, $convOut, $convCode);

        if (!file_exists($wavFile)) {
            return ['ok' => false, 'error' => 'خطا در تبدیل فرمت صوتی'];
        }

        // اجرای whisper
        $outFile = tempnam(sys_get_temp_dir(), 'whisper_out_');
        $cmd = escapeshellarg($this->binPath) .
               ' -m ' . escapeshellarg($modelPath) .
               ' -f ' . escapeshellarg($wavFile) .
               ' -l ' . escapeshellarg($language) .
               ' -otxt -of ' . escapeshellarg($outFile) .
               ' -np -nt 2>&1';

        exec($cmd, $whisperOut, $whisperCode);

        $text = '';
        $detectedLang = $language;
        if (file_exists($outFile . '.txt')) {
            $text = trim(file_get_contents($outFile . '.txt'));
        }

        if ($language === 'auto') {
            $allOut = implode("\n", $whisperOut);
            if (preg_match('/auto-detected language:\s*([a-z]{2,3})/i', $allOut, $m)) {
                $detectedLang = strtolower($m[1]);
            }
        }

        // پاک‌سازی
        @unlink($wavFile);
        @unlink($outFile);
        @unlink($outFile . '.txt');

        if (empty($text)) {
            return ['ok' => false, 'error' => 'متنی شناسایی نشد'];
        }

        return [
            'ok'       => true,
            'text'     => $text,
            'language' => $detectedLang,
            'model'    => $model,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  تست اتصال
    // ═══════════════════════════════════════════════════════════
    public function testConnection(): array {
        if (!file_exists($this->binPath)) {
            return ['ok' => false, 'error' => 'فایل whisper-cli یافت نشد'];
        }
        if (!is_executable($this->binPath)) {
            return ['ok' => false, 'error' => 'whisper-cli قابل اجرا نیست'];
        }
        if (!is_dir($this->modelsDir)) {
            return ['ok' => false, 'error' => 'پوشه مدل‌ها وجود ندارد'];
        }
        if (!is_writable($this->modelsDir)) {
            return ['ok' => false, 'error' => 'پوشه مدل‌ها قابل نوشتن نیست'];
        }
        return ['ok' => true, 'message' => 'whisper.cpp آماده است'];
    }

    // ═══════════════════════════════════════════════════════════
    //  دانلود مدل — فقط در صف قرار می‌دهد
    // ═══════════════════════════════════════════════════════════
    public function pullModel(string $modelName): array {
        // اعتبارسنجی
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $modelName)) {
            return ['ok' => false, 'error' => 'نام مدل نامعتبر'];
        }

        if (!isset(self::$knownModels[$modelName])) {
            return ['ok' => false, 'error' => 'این مدل در لیست شناخته‌شده‌ها نیست'];
        }

        if (!is_dir($this->modelsDir)) {
            @mkdir($this->modelsDir, 0755, true);
        }

        $target = $this->modelsDir . "/ggml-{$modelName}.bin";

        // ═══ ساخت لیست منابع ═══
        $sources = self::$downloadSources;

        if (!empty($this->mirrorUrl)) {
            array_unshift($sources, [
                'name' => 'Custom Mirror',
                'url'  => str_replace('{model}', $modelName, $this->mirrorUrl),
            ]);
        }

        $urls = [];
        foreach ($sources as $source) {
            $urls[] = [
                'name' => $source['name'],
                'url'  => str_replace('{model}', $modelName, $source['url']),
            ];
        }

        // ═══ ذخیره منابع ═══
        $sourcesFile = sys_get_temp_dir() . '/whisper_sources_' . md5($modelName) . '.json';
        file_put_contents($sourcesFile, json_encode($urls));

        // ═══ پاک کردن status قبلی ═══
        $statusFile = $this->getDownloadStatusFile($modelName);
        @unlink($statusFile);

        // ═══ ساخت status اولیه ═══
        file_put_contents($statusFile, json_encode([
            'status'     => 'queued',
            'downloaded' => 0,
            'total'      => 0,
            'percent'    => 0,
            'source'     => 'در انتظار پردازش...',
            'queued_at'  => date('H:i:s'),
        ]));

        // ═══ ساخت queue ═══
        $queueDir = sys_get_temp_dir() . '/whisper_queue';
        if (!is_dir($queueDir)) {
            @mkdir($queueDir, 0755, true);
        }

        $queueFile = $queueDir . '/' . md5($modelName) . '.json';

        $queueData = [
            'model_name'     => $modelName,
            'sources_file'   => $sourcesFile,
            'target'         => $target,
            'status_file'    => $statusFile,
            'worker_script'  => realpath(__DIR__ . '/../../../../cron/whisper_worker.php'),
            'queued_at'      => date('c'),
        ];

        file_put_contents($queueFile, json_encode($queueData));

        return [
            'ok'            => true,
            'message'       => 'مدل در صف دانلود قرار گرفت. Cron آن را پردازش می‌کند.',
            'sources_count' => count($urls),
            'queue_file'    => $queueFile,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  وضعیت دانلود
    // ═══════════════════════════════════════════════════════════
    public function getDownloadStatus(string $modelName): array {
        $statusFile = $this->getDownloadStatusFile($modelName);

        if (!file_exists($statusFile)) {
            return ['ok' => false, 'error' => 'دانلودی در جریان نیست'];
        }

        $data = json_decode(file_get_contents($statusFile), true);
        if (!$data) {
            return ['ok' => false, 'error' => 'وضعیت نامعتبر'];
        }

        return [
            'ok'          => true,
            'status'      => $data,
            'age_seconds' => time() - filemtime($statusFile),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  تست منابع
    // ═══════════════════════════════════════════════════════════
    public function testSources(string $modelName = 'tiny'): array {
        $results = [];

        foreach (self::$downloadSources as $source) {
            $url   = str_replace('{model}', $modelName, $source['url']);
            $start = microtime(true);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY         => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            curl_exec($ch);
            $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $elapsed = round(microtime(true) - $start, 2);
            // curl_close deprecated

            $results[] = [
                'name'          => $source['name'],
                'url'           => $url,
                'http_code'     => $code,
                'response_time' => $elapsed,
                'available'     => in_array($code, [200, 301, 302], true),
            ];
        }

        usort($results, function ($a, $b) {
            if ($a['available'] !== $b['available']) {
                return $b['available'] <=> $a['available'];
            }
            return $a['response_time'] <=> $b['response_time'];
        });

        return ['ok' => true, 'sources' => $results];
    }

    // ═══════════════════════════════════════════════════════════
    //  Private: مسیر فایل وضعیت
    // ═══════════════════════════════════════════════════════════
    private function getDownloadStatusFile(string $modelName): string {
        return sys_get_temp_dir() . '/whisper_status_' . md5($modelName) . '.json';
    }
}
