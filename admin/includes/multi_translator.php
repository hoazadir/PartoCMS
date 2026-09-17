<?php
/**
 * PartoCMS - Multi-Provider Translator (Final v6.1)
 * - چند provider موازی
 * - کیفیت‌سنجی خودکار با تشخیص garbage
 * - Tie-breaking هوشمند (score + provider weight + priority)
 * - Cache با اعتبارسنجی مجدد
 * - v6:   وزن‌دهی به score + متعادل‌سازی qualityScore + محدودسازی MyMemory
 * - v6.1: یکسان‌سازی وزن در testProviders + source language داینامیک + کش وزنی
 */

class MultiTranslator {

    private $pdo;
    private $keys = [];
    private $providers = [];

    private $libreInstances = [
        'https://translate.terraprint.co',
        'https://lt.vern.cc',
        'https://libretranslate.de',
        'https://translate.argosopentech.com',
    ];

    private $lingvaInstances = [
        'https://lingva.ml',
        'https://lingva.lunar.icu',
        'https://translate.plausibility.cloud',
        'https://lingva.garudalinux.org',
    ];

    /**
     * وزن provider ها برای tie-breaking
     * هر چه بیشتر = اولویت بالاتر
     */
    private $providerWeight = [
        'deepl'     => 100,
        'microsoft' => 90,
        'yandex'    => 80,
        'google'    => 70,
        'lingva'    => 60,
        'libre'     => 50,
        'mymemory'  => 20,
        'argos'     => 85,
        'cache'     => 0,
    ];

    /**
     * v6.1: priority صریح — عدد کمتر = برنده در tie-break نهایی
     * (پشتیبان weight در موارد لبه‌ای)
     */
    private $providerPriority = [
        'deepl'     => 1,
        'microsoft' => 2,
        'yandex'    => 3,
        'google'    => 4,
        'lingva'    => 5,
        'libre'     => 6,
        'mymemory'  => 7,
        'cache'     => 99,
    ];

    /** v6: حداقل اختلاف وزن provider برای invalidate کردن کش ضعیف */
    private $cacheInvalidateThreshold = 30;

    /** v6.1: حداکثر افت score مجاز DeepL نسبت به بهترین provider دیگر (برای انتخاب ترجیحی) */
    private $deeplToleranceBonus = 8;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureCacheColumn();
        $this->loadKeys();
        $this->loadProviders();
    }

    // ============================================================
    //   Setup
    // ============================================================

    private function ensureCacheColumn() {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM translation_cache LIKE 'provider'")->fetch();
            if (!$cols) {
                $this->pdo->exec("ALTER TABLE translation_cache ADD COLUMN provider VARCHAR(30) DEFAULT NULL");
            }
        } catch (Throwable $e) {}
    }

    private function loadKeys() {
        $keys = ['deepl_api_key', 'microsoft_api_key', 'microsoft_region', 'yandex_api_key'];
        foreach ($keys as $k) {
            try {
                $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
                $stmt->execute([$k]);
                $val = trim($stmt->fetchColumn() ?: '');
                if ($val) $this->keys[$k] = $val;
            } catch (Throwable $e) {}
        }
    }

    private function loadProviders() {
        $this->providers = [];

        if (!empty($this->keys['deepl_api_key']))     $this->providers[] = 'deepl';
        if (!empty($this->keys['microsoft_api_key'])) $this->providers[] = 'microsoft';
        if (!empty($this->keys['yandex_api_key']))    $this->providers[] = 'yandex';

        // همیشه در دسترس
        $this->providers[] = 'google';
        $this->providers[] = 'lingva';
        $this->providers[] = 'libre';
        $this->providers[] = 'mymemory';
    }

    // ============================================================
    //   v6.1: Scoring helper (یکسان برای translateAll و testProviders)
    // ============================================================

    /**
     * وزن provider رو روی base score اعمال می‌کنه
     * DeepL (100) → +10، MyMemory (20) → -6، Google (70) → +4
     */
    private function applyWeight(int $baseScore, string $provider): int {
        $weight = $this->providerWeight[$provider] ?? 30;
        $weightBonus = (int) round(($weight - 50) / 5);
        return max(0, min(100, $baseScore + $weightBonus));
    }

    // ============================================================
    //   Main Translate
    // ============================================================

    public function translateAll($text, $from, $to, $saveToCache = true) {
        if (empty($text) || $from === $to) {
            return ['best' => ['provider' => 'none', 'text' => $text, 'score' => 100], 'all' => []];
        }

        $from = substr($from, 0, 2);
        $to   = substr($to, 0, 2);

        // چک کش اول
        $cached = $this->getCache($text, $from, $to);
        if ($cached) {
            // v6.1: score کش رو هم وزنی کن
            $weightedScore = $this->applyWeight($cached['score'], $cached['provider']);
            return [
                'best' => [
                    'provider' => $cached['provider'] ?? 'cache',
                    'text'     => $cached['text'],
                    'score'    => $weightedScore,
                ],
                'all' => [[
                    'provider' => $cached['provider'] ?? 'cache',
                    'text'     => $cached['text'],
                    'score'    => $weightedScore,
                    'ms'       => 0,
                    'from_cache' => true,
                ]],
            ];
        }

        $allResults = [];

        foreach ($this->providers as $provider) {
            $start = microtime(true);
            $result = $this->callProvider($provider, $text, $from, $to);
            $ms = round((microtime(true) - $start) * 1000);

            if (is_string($result) && !empty($result)) {
                $result    = $this->cleanResult($result);
                $baseScore = $this->qualityScore($result, $text, $from, $to);
                $score     = $this->applyWeight($baseScore, $provider);

                $allResults[] = [
                    'provider' => $provider,
                    'text'     => $result,
                    'score'    => $score,
                    'base'     => $baseScore, // برای debug
                    'ms'       => $ms,
                ];

                $this->recordProvider($provider, true, $ms, $score);
            } else {
                $err = is_array($result) ? ($result['error'] ?? '?') : 'failed';
                $this->recordProvider($provider, false, $ms, 0, $err);
            }
        }

        if (empty($allResults)) {
            return [
                'best' => ['provider' => 'none', 'text' => null, 'score' => 0, 'error' => 'همه provider ها fail'],
                'all'  => [],
            ];
        }

        // v6.1: مرتب‌سازی با weight + priority + speed (سه لایه tie-break)
        usort($allResults, function($a, $b) {
            // ۱) score نزولی
            if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];

            // ۲) weight نزولی
            $wa = $this->providerWeight[$a['provider']] ?? 30;
            $wb = $this->providerWeight[$b['provider']] ?? 30;
            if ($wa !== $wb) return $wb <=> $wa;

            // ۳) priority صعودی (کمتر بهتر)
            $pa = $this->providerPriority[$a['provider']] ?? 50;
            $pb = $this->providerPriority[$b['provider']] ?? 50;
            if ($pa !== $pb) return $pa <=> $pb;

            // ۴) سرعت صعودی
            return $a['ms'] <=> $b['ms'];
        });

        $best = $allResults[0];

        // v6.1: ترجیح DeepL — اگه اختلاف score کمتر از tolerance باشه، DeepL برنده
        if ($best['provider'] !== 'deepl') {
            $deeplResult = null;
            foreach ($allResults as $r) {
                if ($r['provider'] === 'deepl') { $deeplResult = $r; break; }
            }
            if ($deeplResult && ($best['score'] - $deeplResult['score']) <= $this->deeplToleranceBonus) {
                $best = $deeplResult;
            }
        }

        // ذخیره در کش فقط اگر نمره قابل قبول باشد
        if ($saveToCache && $best['score'] >= 60) {
            $this->saveCache($text, $from, $to, $best['text'], $best['provider']);
        }

        return ['best' => $best, 'all' => $allResults];
    }

    // ============================================================
    //   Quality Scoring
    // ============================================================

    private function qualityScore($translated, $original, $from, $to) {
        $score = 50;

        if (empty($translated)) return 0;
        if ($translated === $original) return 5;

        // ❌ اگر با [ شروع شود (کش community MyMemory)
        if (preg_match('/^\s*\[/', $translated)) $score -= 30;

        // ❌ کلمات ممنوعه
        $bad = ['MYMEMORY', 'PLEASE SELECT', 'INVALID', 'ERROR', 'TRANSLATE NOW',
                'NO TRANSLATION', 'NOT AVAILABLE', 'SERVICE UNAVAILABLE'];
        foreach ($bad as $b) {
            if (stripos($translated, $b) !== false) $score -= 45;
        }

        // ❌ متن‌های uppercase (garbage MyMemory)
        $upperCount  = preg_match_all('/[A-Z]/', $translated);
        $letterCount = preg_match_all('/[a-zA-Z]/', $translated);
        if ($letterCount > 5 && ($upperCount / $letterCount) > 0.85) {
            $score -= 25;
        }

        // ✅ نسبت طول — برای زبان مقصد لاتین آسان‌گیرتر
        $latinTargets  = ['en', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'pl', 'tr', 'id', 'vi'];
        $isLatinTarget = in_array($to, $latinTargets);
        $ratio = mb_strlen($translated) / max(mb_strlen($original), 1);

        if ($isLatinTarget) {
            if ($ratio < 0.2 || $ratio > 3.5) {
                $score -= 20;
            } elseif ($ratio >= 0.4 && $ratio <= 2.0) {
                $score += 10;
            }
        } else {
            if ($ratio < 0.4 || $ratio > 3) {
                $score -= 20;
            } elseif ($ratio >= 0.6 && $ratio <= 2.0) {
                $score += 10;
            }
        }

        // ✅ چک شباهت با متن اصلی (جلوگیری از کپی)
        if (mb_strlen($original) > 20) {
            $similarity = 0;
            similar_text(
                mb_strtolower($original),
                mb_strtolower($translated),
                $similarity
            );
            if ($similarity > 80) {
                $score -= 40;
            }
        }

        // ✅ وجود حروف الفبای مقصد
        $targetRanges = [
            'ar' => '/[\x{0600}-\x{06FF}]/u',
            'fa' => '/[\x{0600}-\x{06FF}]/u',
            'he' => '/[\x{0590}-\x{05FF}]/u',
            'zh' => '/[\x{4E00}-\x{9FFF}]/u',
            'ja' => '/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}]/u',
            'ko' => '/[\x{AC00}-\x{D7AF}]/u',
            'ru' => '/[\x{0400}-\x{04FF}]/u',
            'hi' => '/[\x{0900}-\x{097F}]/u',
            'th' => '/[\x{0E00}-\x{0E7F}]/u',
        ];
        if (isset($targetRanges[$to])) {
            if (preg_match($targetRanges[$to], $translated)) {
                $score += 20;
            } else {
                $score -= 30;
            }
        }

        // ✅ اگر لاتین هدف است
        if ($isLatinTarget) {
            $latinCount = preg_match_all('/[a-zA-Z]/', $translated);
            $totalChars = mb_strlen($translated);
            if ($totalChars > 0 && $latinCount / $totalChars > 0.6) {
                $score += 15;
            }
        }

        // ✅ شروع با حرف بزرگ
        if (preg_match('/^[A-Z\x{0600}-\x{06FF}\x{4E00}-\x{9FFF}]/u', $translated)) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }

    // ============================================================
    //   Translate Content
    // ============================================================

    public function translateContent($contentId, $targetLangCode, $userId = null) {
        $stmt = $this->pdo->prepare("SELECT * FROM content_items WHERE id = ?");
        $stmt->execute([$contentId]);
        $content = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$content) return ['error' => 'Content not found'];

        $stmt = $this->pdo->prepare("SELECT * FROM languages WHERE code = ?");
        $stmt->execute([$targetLangCode]);
        $lang = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lang) return ['error' => 'Language not found'];

        // ✅ v6.1: زبان مبدأ داینامیک
        $sourceLangCode = 'fa-IR'; // پیش‌فرض
        if (!empty($content['language_id'])) {
            try {
                $s = $this->pdo->prepare("SELECT code FROM languages WHERE id = ? LIMIT 1");
                $s->execute([$content['language_id']]);
                $detected = $s->fetchColumn();
                if ($detected) $sourceLangCode = $detected;
            } catch (Throwable $e) {}
        } elseif (!empty($content['language_code'])) {
            $sourceLangCode = $content['language_code'];
        } elseif (!empty($content['lang'])) {
            $sourceLangCode = $content['lang'];
        }

        $fields       = ['title', 'excerpt', 'content'];
        $translations = [];
        $report       = [];

        foreach ($fields as $field) {
            $sourceText = $content[$field] ?? '';
            if (empty($sourceText)) {
                $translations[$field] = '';
                continue;
            }

            $result = $this->translateAll($sourceText, $sourceLangCode, $targetLangCode, true);
            $translations[$field] = $result['best']['text'] ?? '';

            // ذخیره همه نسخه‌ها
            foreach ($result['all'] as $v) {
                $this->saveVersion($contentId, $lang['id'], $field,
                    $v['provider'], $v['text'], $v['score'], false);
            }

            // ذخیره بهترین
            if (!empty($result['best']['text'])) {
                $this->saveVersion($contentId, $lang['id'], $field,
                    $result['best']['provider'], $result['best']['text'],
                    $result['best']['score'], true);
            }

            $report[$field] = $result['best'];
        }

        // میانگین نمره
        $scores   = array_map(fn($r) => $r['score'] ?? 0, $report);
        $avgScore = !empty($scores) ? round(array_sum($scores) / count($scores)) : 0;
        $status   = $avgScore >= 40 ? 'auto' : 'draft';

        // ذخیره در content_translations
        try {
            $slug = 'post-' . $contentId . '-' . substr(md5(($translations['title'] ?? '') . time()), 0, 6);
            $stmt = $this->pdo->prepare("
                INSERT INTO content_translations
                    (content_id, language_id, title, excerpt, content, slug, status, translated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title), excerpt = VALUES(excerpt),
                    content = VALUES(content), status = VALUES(status), translated_at = NOW()
            ");
            $stmt->execute([
                $contentId, $lang['id'],
                $translations['title'] ?? '',
                $translations['excerpt'] ?? '',
                $translations['content'] ?? '',
                $slug, $status, $userId,
            ]);
        } catch (Throwable $e) {
            return ['error' => 'DB: ' . $e->getMessage()];
        }

        return [
            'ok'             => true,
            'avg_score'      => $avgScore,
            'status'         => $status,
            'source_lang'    => $sourceLangCode,
            'target_lang'    => $targetLangCode,
            'report'         => $report,
            'providers_used' => count($this->providers),
        ];
    }

    public function translateAllLanguages($contentId, $userId = null) {
        $langs = $this->pdo->query(
            "SELECT code FROM languages WHERE is_active = 1 AND code != 'fa-IR'"
        )->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($langs as $code) {
            $results[$code] = $this->translateContent($contentId, $code, $userId);
            usleep(300000);
        }
        return $results;
    }

    // ============================================================
    //   Save / Cache
    // ============================================================

    private function saveVersion($contentId, $langId, $field, $provider, $text, $score, $isSelected) {
        try {
            if ($isSelected) {
                $this->pdo->prepare(
                    "UPDATE translation_versions SET is_selected = 0
                     WHERE content_id = ? AND language_id = ? AND field_name = ?"
                )->execute([$contentId, $langId, $field]);
            }
            $this->pdo->prepare("
                INSERT INTO translation_versions
                    (content_id, language_id, field_name, provider, text, quality_score, is_selected)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$contentId, $langId, $field, $provider, $text, $score, $isSelected ? 1 : 0]);
        } catch (Throwable $e) {}
    }

    private function getCache($text, $from, $to) {
        try {
            $hash = hash('sha256', $from . '|' . $to . '|' . $text);
            $stmt = $this->pdo->prepare(
                "SELECT translated_text, provider FROM translation_cache WHERE source_hash = ? LIMIT 1"
            );
            $stmt->execute([$hash]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) return null;

            $cachedText     = $row['translated_text'];
            $cachedProvider = $row['provider'] ?? 'cache';
            $rescore        = $this->qualityScore($cachedText, $text, $from, $to);

            // ✅ اگر کش از provider ضعیف و provider قوی‌تر در دسترسه → بی‌اعتبار
            $cachedWeight = $this->providerWeight[$cachedProvider] ?? 0;
            $bestAvailableWeight = 0;
            foreach ($this->providers as $p) {
                $bestAvailableWeight = max($bestAvailableWeight, $this->providerWeight[$p] ?? 0);
            }

            if ($bestAvailableWeight - $cachedWeight > $this->cacheInvalidateThreshold) {
                // provider قوی‌تر در دسترسه → کش رو نادیده بگیر
                return null;
            }

            // اگر کش کیفیت پایین داشت → بی‌اعتبار
            if ($rescore < 60) {
                try {
                    $this->pdo->prepare("DELETE FROM translation_cache WHERE source_hash = ?")
                        ->execute([$hash]);
                } catch (Throwable $e) {}
                return null;
            }

            return [
                'text'     => $cachedText,
                'provider' => $cachedProvider,
                'score'    => $rescore, // خام؛ در translateAll وزنی می‌شه
            ];
        } catch (Throwable $e) { return null; }
    }

    private function saveCache($text, $from, $to, $translated, $provider) {
        try {
            $hash = hash('sha256', $from . '|' . $to . '|' . $text);
            $this->pdo->prepare("
                INSERT INTO translation_cache
                    (source_hash, source_lang, target_lang, source_text, translated_text, provider)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    translated_text = VALUES(translated_text),
                    provider = VALUES(provider)
            ")->execute([$hash, $from, $to, $text, $translated, $provider]);
        } catch (Throwable $e) {}
    }

    // ============================================================
    //   Provider Stats
    // ============================================================

    private function recordProvider($provider, $success, $ms, $score = 0, $error = null) {
        try {
            $this->pdo->prepare("
                UPDATE provider_stats SET
                    total_calls       = total_calls + 1,
                    success_calls     = success_calls + ?,
                    failed_calls      = failed_calls + ?,
                    total_response_ms = total_response_ms + ?,
                    avg_quality_score = IF(success_calls > 0,
                        ROUND((avg_quality_score * success_calls + ?) / (success_calls + 1), 2),
                        ?),
                    last_success = IF(? = 1, NOW(), last_success),
                    last_failure = IF(? = 0, NOW(), last_failure),
                    last_error   = IF(? = 0, ?, last_error)
                WHERE provider = ?
            ")->execute([
                $success ? 1 : 0,
                $success ? 0 : 1,
                $ms,
                $score, $score,
                $success ? 1 : 0,
                $success ? 1 : 0,
                $success ? 1 : 0, $error,
                $provider
            ]);
        } catch (Throwable $e) {}
    }

    // ============================================================
    //   Helpers
    // ============================================================

    private function cleanResult($text) {
        // فقط bullet های ابتدای خط، نه خط تیره‌های ترجمه واقعی
        $text = preg_replace('/^\s*[•*]\s+/u', '', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    private function splitText($text, $maxLen) {
        if (mb_strlen($text) <= $maxLen) return [$text];
        $chunks    = [];
        $sentences = preg_split('/(?<=[.!?؟\n])\s+/u', $text);
        $current   = '';
        foreach ($sentences as $s) {
            if (mb_strlen($current . ' ' . $s) > $maxLen) {
                if ($current) $chunks[] = trim($current);
                $current = $s;
            } else {
                $current .= ' ' . $s;
            }
        }
        if (trim($current)) $chunks[] = trim($current);
        return $chunks;
    }

    // ============================================================
    //   Provider Dispatchers
    // ============================================================

    private function callProvider($p, $text, $from, $to) {
        switch ($p) {
            case 'deepl':     return $this->pDeepL($text, $from, $to);
            case 'microsoft': return $this->pMicrosoft($text, $from, $to);
            case 'yandex':    return $this->pYandex($text, $from, $to);
            case 'google':    return $this->pGoogle($text, $from, $to);
            case 'lingva':    return $this->pLingva($text, $from, $to);
            case 'libre':     return $this->pLibre($text, $from, $to);
            case 'mymemory':  return $this->pMyMemory($text, $from, $to);
            case 'argos':     return $this->pArgos($text, $from, $to);
            default: return ['error' => 'unknown provider'];
        }
    }

    // ---------- DeepL ----------
    private function pDeepL($text, $from, $to) {
        $key = $this->keys['deepl_api_key'] ?? '';
        if (!$key) return ['error' => 'no key'];

        $isFree = substr($key, -3) === ':fx';
        $url = $isFree
            ? 'https://api-free.deepl.com/v2/translate'
            : 'https://api.deepl.com/v2/translate';

        $result = '';
        foreach ($this->splitText($text, 4000) as $chunk) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'text' => $chunk,
                    'source_lang' => strtoupper($from),
                    'target_lang' => strtoupper($to === 'en' ? 'EN-US' : $to),
                    'preserve_formatting' => '1',
                    'tag_handling' => 'html',
                ]),
                CURLOPT_HTTPHEADER => ['Authorization: DeepL-Auth-Key ' . $key],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code === 403) return ['error' => 'DeepL: key نامعتبر'];
            if ($code === 456) return ['error' => 'DeepL: سهمیه ماهانه تمام شد'];
            if ($code === 429) return ['error' => 'DeepL: rate limit'];
            if ($code !== 200) return ['error' => "DeepL HTTP $code"];

            $data = json_decode($res, true);
            foreach ($data['translations'] ?? [] as $t) $result .= $t['text'];
        }
        return $result ?: ['error' => 'DeepL: empty'];
    }

    // ---------- Microsoft ----------
    private function pMicrosoft($text, $from, $to) {
        $key = $this->keys['microsoft_api_key'] ?? '';
        if (!$key) return ['error' => 'no key'];

        $region = $this->keys['microsoft_region'] ?? 'global';
        $url    = "https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from=$from&to=$to";
        $body   = json_encode([['Text' => $text]], JSON_UNESCAPED_UNICODE);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $key,
                'Ocp-Apim-Subscription-Region: ' . $region,
                'Content-Type: application/json; charset=UTF-8',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($code === 401) return ['error' => 'Microsoft: key نامعتبر'];
        if ($code === 429) return ['error' => 'Microsoft: rate limit'];
        if ($code !== 200) return ['error' => "Microsoft HTTP $code"];

        $data = json_decode($res, true);
        return $data[0]['translations'][0]['text'] ?? ['error' => 'no result'];
    }

    // ---------- Yandex ----------
    private function pYandex($text, $from, $to) {
        $key = $this->keys['yandex_api_key'] ?? '';
        if (!$key) return ['error' => 'no key'];

        $url = 'https://translate.yandex.net/api/v1.5/tr.json/translate?' . http_build_query([
            'key'    => $key,
            'text'   => substr($text, 0, 10000),
            'lang'   => $from . '-' . $to,
            'format' => 'plain',
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code !== 200) return ['error' => "Yandex HTTP $code"];
        $data = json_decode($res, true);
        return $data['text'][0] ?? ['error' => 'no result'];
    }

    // ---------- Google (unofficial) ----------
    private function pGoogle($text, $from, $to) {
        $result = '';
        foreach ($this->splitText($text, 4500) as $chunk) {
            $url = 'https://translate.googleapis.com/translate_a/single?' . http_build_query([
                'client' => 'gtx',
                'sl'     => $from,
                'tl'     => $to,
                'dt'     => 't',
                'q'      => $chunk,
            ]);
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Chrome/120.0 Mobile Safari/537.36',
                    'Accept: application/json',
                ],
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($code === 429) return ['error' => 'Google: rate limited'];
            if ($code !== 200) return ['error' => "Google HTTP $code"];

            $data = json_decode($res, true);
            if (!is_array($data) || empty($data[0])) return ['error' => 'Google: invalid response'];
            foreach ($data[0] as $part) if (isset($part[0])) $result .= $part[0];
            usleep(200000);
        }
        return $result;
    }

    // ---------- Lingva (Google proxy) ----------
    private function pLingva($text, $from, $to) {
        foreach ($this->lingvaInstances as $base) {
            $url = rtrim($base, '/') . "/api/v1/$from/$to/" . urlencode(substr($text, 0, 2000));
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 200) {
                $data = json_decode($res, true);
                if (!empty($data['translation'])) return $data['translation'];
            }
        }
        return ['error' => 'all lingva instances failed'];
    }

    // ---------- LibreTranslate ----------
    private function pLibre($text, $from, $to) {
        foreach ($this->libreInstances as $base) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => rtrim($base, '/') . '/translate',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode([
                    'q'      => substr($text, 0, 3000),
                    'source' => $from,
                    'target' => $to,
                    'format' => 'text',
                ]),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 12,
            ]);
            $res  = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($code === 200) {
                $data = json_decode($res, true);
                if (!empty($data['translatedText'])) return $data['translatedText'];
            }
        }
        return ['error' => 'all libre instances failed'];
    }

    // ---------- MyMemory (محدود شده در v6) ----------

    // ---------- Argos Translate (آفلاین) ----------
    private function pArgos($text, $from, $to) {
        if (!class_exists('Environment')) {
            $envPath = __DIR__ . '/environment.php';
            if (file_exists($envPath)) require_once $envPath;
        }
        if (!class_exists('Environment') || !Environment::hasArgos()) {
            return ['error' => 'Argos not installed'];
        }

        $fromCode = strtolower(substr($from, 0, 2));
        $toCode   = strtolower(substr($to, 0, 2));

        if ($fromCode === $toCode) {
            return $text;
        }

        try {
            $cmd = sprintf(
                'argos-translate --from %s --to %s %s 2>&1',
                escapeshellarg($fromCode),
                escapeshellarg($toCode),
                escapeshellarg($text)
            );

            $output = [];
            $code = 0;
            @exec($cmd, $output, $code);

            if ($code !== 0 || empty($output)) {
                return ['error' => 'Argos CLI failed (exit: ' . $code . ')'];
            }

            $result = trim(implode("\n", $output));

            if (empty($result)) {
                return ['error' => 'Argos empty result'];
            }

            return $result;

        } catch (Throwable $e) {
            return ['error' => 'Argos exception: ' . $e->getMessage()];
        }
    }

    private function pMyMemory($text, $from, $to) {
        // فقط برای متن‌های کوتاه
        if (mb_strlen($text) > 300) {
            return ['error' => 'MyMemory: text too long'];
        }

        $url = 'https://api.mymemory.translated.net/get?' . http_build_query([
            'q'        => substr($text, 0, 500),
            'langpair' => $from . '|' . $to,
            'mt'       => '1',
        ]);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($code !== 200) return ['error' => 'MyMemory: HTTP fail'];

        $data = json_decode($res, true);
        $t    = $data['responseData']['translatedText'] ?? '';
        if (empty($t)) return ['error' => 'MyMemory: empty'];

        // چک‌های اضافی
        if (stripos($t, 'MYMEMORY WARNING') !== false)     return ['error' => 'MyMemory: limit'];
        if (stripos($t, 'PLEASE SELECT') !== false)        return ['error' => 'MyMemory: invalid'];
        if (stripos($t, 'QUERY LENGTH LIMIT') !== false)   return ['error' => 'MyMemory: length'];

        // اگر match دقیق با اصلی (کپی) → رد کن
        if (mb_strtolower(trim($t)) === mb_strtolower(trim($text))) {
            return ['error' => 'MyMemory: returned source'];
        }

        // اگر quality پایین از خود MyMemory
        $quality = $data['responseData']['match'] ?? 0;
        if ($quality < 0.5 && mb_strlen($text) > 30) {
            return ['error' => 'MyMemory: low match'];
        }

        return $t;
    }

    // ============================================================
    //   Test — v6.1: با وزن
    // ============================================================

    public function testProviders() {
        $results  = [];
        $testText = 'سلام، حال شما چطور است؟';

        foreach ($this->providers as $p) {
            $start = microtime(true);
            $r     = $this->callProvider($p, $testText, 'fa', 'en');
            $ms    = round((microtime(true) - $start) * 1000);

            if (is_string($r) && !empty($r)) {
                $r         = $this->cleanResult($r);
                $baseScore = $this->qualityScore($r, $testText, 'fa', 'en');
                $score     = $this->applyWeight($baseScore, $p); // ✅ v6.1: وزنی

                $results[$p] = [
                    'ok'     => true,
                    'result' => $r,
                    'score'  => $score,
                    'base'   => $baseScore,
                    'ms'     => $ms,
                ];
            } else {
                $results[$p] = [
                    'ok'    => false,
                    'error' => is_array($r) ? ($r['error'] ?? '?') : 'failed',
                    'ms'    => $ms,
                ];
            }
        }

        // ✅ v6.1: sort بر اساس score نزولی برای راحتی خواندن
        uasort($results, function($a, $b) {
            $sa = $a['score'] ?? -1;
            $sb = $b['score'] ?? -1;
            if ($sa !== $sb) return $sb <=> $sa;
            return 0;
        });

        return $results;
    }
}
