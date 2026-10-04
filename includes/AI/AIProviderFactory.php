<?php
/**
 * AI Provider Factory
 * ساخت providerهای LLM و STT بر اساس تنظیمات
 */
class AIProviderFactory {

    private static $llmProviders = [
        // ─── Local ───
        'ollama' => 'OllamaProvider',

        // ─── Cloud (🆕 2026-10-01) ───
        'groq'       => 'GroqProvider',
        'openai'     => 'OpenAIProvider',
        'gemini'     => 'GeminiProvider',
        'openrouter' => 'OpenRouterProvider',
    ];

    private static $sttProviders = [
        'whisper_cpp' => 'WhisperCppProvider',
        'openai_whisper' => 'OpenAIWhisperProvider',
        'vosk' => 'VoskProvider',
    ];

    /**
     * ساخت LLM Provider
     */
    public static function makeLLM(string $providerSlug = ''): ?LLMProviderInterface {
        if (empty($providerSlug)) {
            $providerSlug = getSetting('ai_llm_provider', 'ollama');
        }

        if (!isset(self::$llmProviders[$providerSlug])) return null;

        $class = self::$llmProviders[$providerSlug];
        $file = __DIR__ . '/Providers/LLM/' . $class . '.php';
        if (!file_exists($file)) return null;

        require_once $file;

        $config = self::getLLMConfig($providerSlug);
        return new $class($config);
    }

    /**
     * ساخت STT Provider
     */
    public static function makeSTT(string $providerSlug = ''): ?STTProviderInterface {
        if (empty($providerSlug)) {
            $providerSlug = getSetting('ai_stt_provider', 'whisper_cpp');
        }

        if (!isset(self::$sttProviders[$providerSlug])) return null;

        $class = self::$sttProviders[$providerSlug];
        $file = __DIR__ . '/Providers/STT/' . $class . '.php';
        if (!file_exists($file)) return null;

        require_once $file;

        $config = self::getSTTConfig($providerSlug);
        return new $class($config);
    }

    /**
     * لیست providerهای موجود
     */
    public static function listAvailableLLM(): array {
        $result = [];
        foreach (self::$llmProviders as $slug => $class) {
            $file = __DIR__ . '/Providers/LLM/' . $class . '.php';
            $result[$slug] = [
                'slug' => $slug,
                'class' => $class,
                'available' => file_exists($file),
            ];
        }
        return $result;
    }

    public static function listAvailableSTT(): array {
        $result = [];
        foreach (self::$sttProviders as $slug => $class) {
            $file = __DIR__ . '/Providers/STT/' . $class . '.php';
            $result[$slug] = [
                'slug' => $slug,
                'class' => $class,
                'available' => file_exists($file),
            ];
        }
        return $result;
    }

    private static function getLLMConfig(string $slug): array {
        switch ($slug) {
            case 'ollama':
                return [
                    'endpoint' => getSetting('ai_endpoint', 'http://localhost:11434'),
                    'timeout' => (int) getSetting('ai_timeout', '300'),
                    'model' => getSetting('ai_model', 'qwen2.5:1.5b'),
                ];
            case 'openai':
                return [
                    'api_key' => getSetting('ai_openai_key', ''),
                    'model' => getSetting('ai_openai_model', 'gpt-4o-mini'),
                ];

            // ═══ 🆕 Cloud Providers ═══
            case 'groq':
                return [
                    'api_key' => getSetting('ai_groq_api_key', ''),
                    'model'   => getSetting('ai_groq_model', 'llama-3.3-70b-versatile'),
                    'timeout' => (int) getSetting('ai_groq_timeout', '60'),
                ];

            case 'gemini':
                return [
                    'api_key' => getSetting('ai_gemini_api_key', ''),
                    'model'   => getSetting('ai_gemini_model', 'gemini-2.5-flash'),
                    'timeout' => (int) getSetting('ai_gemini_timeout', '60'),
                ];

            case 'openrouter':
                return [
                    'api_key' => getSetting('ai_openrouter_api_key', ''),
                    'model'   => getSetting('ai_openrouter_model', 'nvidia/nemotron-3-ultra-550b-a55b:free'),
                    'timeout' => (int) getSetting('ai_openrouter_timeout', '60'),
                ];

            default:
                return [];
        }
    }

    private static function getSTTConfig(string $slug): array {
        switch ($slug) {
            case 'whisper_cpp':
                return [
                    'bin_path' => getSetting('ai_whisper_bin', '/data/data/com.termux/files/home/whisper.cpp/build/bin/whisper-cli'),
                    'models_dir' => getSetting('ai_whisper_models_dir', '/data/data/com.termux/files/home/whisper.cpp/models'),
                    'model' => getSetting('ai_stt_model', 'base'),
                    'ffmpeg_path' => 'ffmpeg',
                ];
            case 'openai_whisper':
                return [
                    'api_key' => getSetting('ai_openai_key', ''),
                    'model' => getSetting('ai_openai_stt_model', 'whisper-1'),
                ];
            default:
                return [];
        }
    }
}
