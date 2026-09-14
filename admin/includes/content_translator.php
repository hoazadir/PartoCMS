<?php
/**
 * PartoCMS - Content Translator (Final Version)
 * - بدون curl_close deprecated
 * - MyMemory با mt=1 (متن ماشینی)
 * - Google با headers بهتر
 * - LibreTranslate با سرورهای فعال
 */

class ContentTranslator {

    private $pdo;
    private $providers = ['google', 'mymemory', 'libre'];
    public $lastProvider = null;

    // LibreTranslate instances فعال (2024)
    private $libreInstances = [
        'https://translate.terraprint.co',
        'https://lt.vern.cc',
        'https://translate.fedilab.app',
        'https://trans.zillyhuhn.com',
    ];

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // ============================================================
    //   Main Translate
    // ============================================================

    public function translate($text, $fromLang, $toLang) {
        if (empty($text) || $fromLang === $toLang) return $text;

        $from = substr($fromLang, 0, 2);
        $to = substr($toLang, 0, 2);

        $cached = $this->getCache($text, $from, $to);
        if ($cached !== null) {
            $this->lastProvider = 'cache';
            return $cached;
        }

        $errors = [];
        foreach ($this->providers as $provider) {
            $result = $this->callProvider($provider, $text, $from, $to);

            if (is_string($result) && !empty($result) && !$this->isGarbage($result)) {
                $this->saveCache($text, $from, $to, $result);
                $this->lastProvider = $provider;
                return $result;
            }
            $errors[] = "$provider: " . (is_array($result) ? ($result['error'] ?? '?') : 'garbage');
        }

        return ['error' => implode(' | ', $errors)];
    }

    public function translateContent($contentId, $targetLangCode, $userId = null) {
        $stmt = $this->pdo->prepare("SELECT * FROM content_items WHERE id = ?");
        $stmt->execute([$contentId]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$content) return ['error' => 'Content not found'];

        $stmt = $this->pdo->prepare("SELECT id, code FROM languages WHERE code = ?");
        $stmt->execute([$targetLangCode]);
        $lang = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lang) return ['error' => 'Language not found'];

        $translated = [
            'title'   => $this->translate($content['title'] ?? '', 'fa-IR', $targetLangCode),
            'excerpt' => $this->translate($content['excerpt'] ?? '', 'fa-IR', $targetLangCode),
            'content' => $this->translate($content['content'] ?? '', 'fa-IR', $targetLangCode),
        ];

        foreach ($translated as $key => $val) {
            if (is_array($val) && isset($val['error'])) {
                return ['error' => "برای $key: " . $val['error']];
            }
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO content_translations
                    (content_id, language_id, title, excerpt, content, slug, status, translated_by)
                VALUES (?, ?, ?, ?, ?, ?, 'auto', ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title), excerpt = VALUES(excerpt),
                    content = VALUES(content), status = 'auto', translated_at = NOW()
            ");
            $slug = 'post-' . $contentId . '-' . substr(md5($translated['title'] . time()), 0, 6);
            $stmt->execute([
                $contentId, $lang['id'],
                $translated['title'], $translated['excerpt'], $translated['content'],
                $slug, $userId,
            ]);
        } catch (Throwable $e) {
            return ['error' => 'DB: ' . $e->getMessage()];
        }

        return ['ok' => true, 'title' => $translated['title'], 'lang' => $targetLangCode, 'provider' => $this->lastProvider];
    }

    public function translateAll($contentId, $userId = null) {
        $langs = $this->pdo->query("SELECT code FROM languages WHERE is_active = 1 AND code != 'fa-IR'")->fetchAll(PDO::FETCH_COLUMN);
        $results = [];
        foreach ($langs as $code) {
            $results[$code] = $this->translateContent($contentId, $code, $userId);
            usleep(500000); // 0.5s بین زبان‌ها
        }
        return $results;
    }

    public function testProviders() {
        $tests = [
            'سلام، حال شما چطور است؟' => 'en',  // تست واقعی
        ];
        $results = [];

        foreach ($this->providers as $provider) {
            $start = microtime(true);
            $result = $this->callProvider($provider, 'سلام، حال شما چطور است؟', 'fa', 'en');
            $time = round((microtime(true) - $start) * 1000);

            if (is_string($result) && !empty($result)) {
                $results[$provider] = ['ok' => true, 'result' => $result, 'ms' => $time];
            } else {
                $results[$provider] = ['ok' => false, 'error' => is_array($result) ? ($result['error'] ?? '?') : 'failed', 'ms' => $time];
            }
        }
        return $results;
    }

    // ============================================================
    //   Providers
    // ============================================================

    private function callProvider($provider, $text, $from, $to) {
        switch ($provider) {
            case 'google':   return $this->translateGoogle($text, $from, $to);
            case 'mymemory': return $this->translateMyMemory($text, $from, $to);
            case 'libre':    return $this->translateLibre($text, $from, $to);
            default: return ['error' => 'Unknown'];
        }
    }

    // ============================================================
    //   Google (unofficial API)
    // ============================================================

    private function translateGoogle($text, $from, $to) {
        $chunks = $this->splitText($text, 4500);
        $result = '';

        foreach ($chunks as $chunk) {
            $url = 'https://translate.googleapis.com/translate_a/single?' . http_build_query([
                'client' => 'gtx',
                'sl' => $from,
                'tl' => $to,
                'dt' => 't',
                'q' => $chunk,
            ]);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
                    'Accept: application/json',
                    'Accept-Language: en-US,en;q=0.9',
                    'Referer: https://translate.google.com/',
                ],
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            // curl_close حذف شد (deprecated در PHP 8+)

            if ($err) return ['error' => 'Google: ' . $err];

            if ($httpCode === 429) {
                return ['error' => 'Google: rate limit (429) — کمی صبر کنید'];
            }
            if ($httpCode !== 200) {
                return ['error' => "Google: HTTP $httpCode"];
            }

            $data = json_decode($response, true);
            if (!is_array($data) || empty($data[0])) {
                return ['error' => 'Google: invalid response'];
            }

            foreach ($data[0] as $part) {
                if (isset($part[0])) $result .= $part[0];
            }

            usleep(300000);
        }

        return $result;
    }

    // ============================================================
    //   MyMemory (با mt=1 برای ترجمه ماشینی)
    // ============================================================

    private function translateMyMemory($text, $from, $to) {
        $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
            'q' => substr($text, 0, 500),
            'langpair' => $from . '|' . $to,
            'mt' => '1',  // ← کلید: ترجمه ماشینی، نه کش community
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close حذف شد

        if ($httpCode !== 200) return ['error' => "MyMemory HTTP $httpCode"];

        $data = json_decode($response, true);
        if (empty($data['responseData']['translatedText'])) {
            return ['error' => 'MyMemory: no result'];
        }

        $translated = $data['responseData']['translatedText'];
        if (stripos($translated, 'MYMEMORY WARNING') !== false) {
            return ['error' => 'MyMemory: daily limit'];
        }

        return $translated;
    }

    // ============================================================
    //   LibreTranslate
    // ============================================================

    private function translateLibre($text, $from, $to) {
        foreach ($this->libreInstances as $baseUrl) {
            $url = rtrim($baseUrl, '/') . '/translate';

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'q' => substr($text, 0, 3000),
                    'source' => $from,
                    'target' => $to,
                    'format' => 'text',
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'PartoCMS/1.0',
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            // curl_close حذف شد

            if ($err || $httpCode !== 200) continue;

            $data = json_decode($response, true);
            if (!empty($data['translatedText'])) {
                return $data['translatedText'];
            }
        }

        return ['error' => 'LibreTranslate: همه سرورها fail'];
    }

    // ============================================================
    //   Helpers
    // ============================================================

    /**
     * چک کن آیا ترجمه garbage است (مثل "[ana lucia] hey.")
     */
    private function isGarbage($text) {
        // اگر با [ شروع شود = کش community MyMemory
        if (preg_match('/^\s*\[[^\]]+\]/', $text)) return true;
        // اگر طولش بیش از ۳ برابر متن مبدأ بود، مشکوک
        if (mb_strlen($text) > 200 && mb_strlen($text) > 500) return true;
        return false;
    }

    private function splitText($text, $maxLen) {
        if (mb_strlen($text) <= $maxLen) return [$text];

        $chunks = [];
        $sentences = preg_split('/(?<=[.!?؟\n])\s+/u', $text);
        $current = '';

        foreach ($sentences as $sentence) {
            if (mb_strlen($current . ' ' . $sentence) > $maxLen) {
                if ($current) $chunks[] = trim($current);
                $current = $sentence;
            } else {
                $current .= ' ' . $sentence;
            }
        }
        if (trim($current)) $chunks[] = trim($current);
        return $chunks;
    }

    private function getCache($text, $from, $to) {
        try {
            $hash = hash('sha256', $from . '|' . $to . '|' . $text);
            $stmt = $this->pdo->prepare("SELECT translated_text FROM translation_cache WHERE source_hash = ? LIMIT 1");
            $stmt->execute([$hash]);
            return $stmt->fetchColumn() ?: null;
        } catch (Throwable $e) { return null; }
    }

    private function saveCache($text, $from, $to, $translated) {
        try {
            $hash = hash('sha256', $from . '|' . $to . '|' . $text);
            $stmt = $this->pdo->prepare("
                INSERT INTO translation_cache (source_hash, source_lang, target_lang, source_text, translated_text)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE translated_text = VALUES(translated_text)
            ");
            $stmt->execute([$hash, $from, $to, $text, $translated]);
        } catch (Throwable $e) {}
    }
}
