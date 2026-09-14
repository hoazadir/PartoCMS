<?php
/**
 * PartoCMS - i18n Manager Pro
 * سیستم چندزبانی استاندارد با:
 * - Language persistence (DB + Session + Cookie + Browser)
 * - File cache برای سرعت
 * - Auto-detect user language
 * - Content translation ready
 */

class I18n {

    private $pdo;
    private $currentLang = null;
    private $languages = [];
    private $translations = [];
    private $loaded = false;
    private $cacheFile = null;
    private $cacheEnabled = true;
    private $cacheTTL = 3600; // 1 ساعت

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->cacheFile = $this->getCachePath();
        $this->currentLang = $this->detectLanguage();
    }

    // ==================== PERSISTENCE ====================

    /**
     * تشخیص زبان با اولویت‌بندی
     */
    private function detectLanguage() {
        // اولویت ۱: زبان انتخابی کاربر لاگین‌شده (DB)
        if (isset($_SESSION['user_id'])) {
            $dbLang = $this->getUserLanguage($_SESSION['user_id']);
            if ($dbLang && $this->languageExists($dbLang)) {
                return $dbLang;
            }
        }

        // اولویت ۲: Session
        if (!empty($_SESSION['lang']) && is_string($_SESSION['lang'])) {
            if ($this->languageExists($_SESSION['lang'])) {
                return $_SESSION['lang'];
            }
        }

        // اولویت ۳: Cookie
        if (!empty($_COOKIE['partocms_lang']) && is_string($_COOKIE['partocms_lang'])) {
            if ($this->languageExists($_COOKIE['partocms_lang'])) {
                return $_COOKIE['partocms_lang'];
            }
        }

        // اولویت ۴: Browser Accept-Language
        if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLangs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($browserLangs as $bl) {
                $code = trim(explode(';', $bl)[0]);
                // تبدیل en به en-US اگر ممکن
                if ($this->languageExists($code)) {
                    return $code;
                }
                // تلاش برای حالت مشابه
                $baseLang = explode('-', $code)[0];
                foreach ($this->getAllLanguagesFromDB() as $l) {
                    if (strpos($l['code'], $baseLang) === 0) {
                        return $l['code'];
                    }
                }
            }
        }

        // اولویت ۵: زبان پیش‌فرض
        return $this->getDefaultCode();
    }

    /**
     * دریافت زبان کاربر از DB
     */
    private function getUserLanguage($userId) {
        try {
            // چک user_preferences
            $stmt = $this->pdo->prepare("SELECT language FROM user_preferences WHERE user_id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $lang = $stmt->fetchColumn();
            if ($lang) return $lang;

            // fallback: users.preferred_language
            $stmt = $this->pdo->prepare("SELECT preferred_language FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * ذخیره زبان برای کاربر (DB)
     */
    private function saveUserLanguage($userId, $code) {
        try {
            // user_preferences
            $stmt = $this->pdo->prepare("
                INSERT INTO user_preferences (user_id, language) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE language = VALUES(language)
            ");
            $stmt->execute([$userId, $code]);

            // users.preferred_language
            $stmt = $this->pdo->prepare("UPDATE users SET preferred_language = ? WHERE id = ?");
            $stmt->execute([$code, $userId]);

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ==================== CACHE ====================

    private function getCachePath() {
        $dir = __DIR__ . '/../../logs/i18n_cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function getCacheFileName($langCode) {
        return $this->cacheFile . '/' . preg_replace('/[^a-z0-9\-]/i', '_', $langCode) . '.json';
    }

    private function loadFromCache($langCode) {
        if (!$this->cacheEnabled) return null;
        $file = $this->getCacheFileName($langCode);
        if (!file_exists($file)) return null;
        if (filemtime($file) + $this->cacheTTL < time()) return null;

        $data = @json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    private function saveToCache($langCode, $data) {
        if (!$this->cacheEnabled) return;
        $file = $this->getCacheFileName($langCode);
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function clearCache($langCode = null) {
        if ($langCode) {
            @unlink($this->getCacheFileName($langCode));
        } else {
            foreach (glob($this->cacheFile . '/*.json') as $f) {
                @unlink($f);
            }
        }
    }

    // ==================== CORE METHODS ====================

    private function languageExists($code) {
        if (!is_string($code) || empty($code)) return false;
        foreach ($this->getAllLanguagesFromDB() as $l) {
            if ($l['code'] === $code && $l['is_active']) return true;
        }
        return false;
    }

    private function getAllLanguagesFromDB() {
        static $all = null;
        if ($all !== null) return $all;
        try {
            $all = $this->pdo->query("SELECT * FROM languages WHERE is_active = 1 ORDER BY sort_order, name")
                ->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $all = [];
        }
        return $all;
    }

    public function getDefaultCode() {
        try {
            $code = $this->pdo->query("SELECT code FROM languages WHERE is_default = 1 LIMIT 1")->fetchColumn();
            return ($code && is_string($code)) ? $code : 'en-US';
        } catch (Throwable $e) {
            return 'en-US';
        }
    }

    /**
     * بارگذاری ترجمه‌ها (با کش)
     */
    public function load() {
        if ($this->loaded) return;

        // تلاش از کش
        $cached = $this->loadFromCache($this->currentLang);
        if ($cached !== null) {
            $this->translations = $cached;
            $this->languages = $this->getAllLanguagesFromDB();
            $this->loaded = true;
            return;
        }

        // بارگذاری از DB
        $this->translations = [];
        try {
            $stmt = $this->pdo->prepare("
                SELECT k.`key` AS tkey, t.`value` AS tvalue
                FROM translation_keys k
                INNER JOIN translations t ON t.key_id = k.id
                INNER JOIN languages l ON l.id = t.language_id
                WHERE l.code = ?
            ");
            $stmt->execute([$this->currentLang]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (is_array($rows)) {
                foreach ($rows as $r) {
                    if (isset($r['tkey'])) {
                        $this->translations[$r['tkey']] = $r['tvalue'];
                    }
                }
            }

            // ذخیره در کش
            $this->saveToCache($this->currentLang, $this->translations);
        } catch (Throwable $e) {}

        $this->languages = $this->getAllLanguagesFromDB();
        $this->loaded = true;
    }

    // ==================== PUBLIC API ====================

    public function t($key, $default = null, $params = []) {
        $this->load();
        $value = isset($this->translations[$key]) 
            ? $this->translations[$key] 
            : ($default !== null ? $default : $key);
        if (!empty($params) && is_array($params)) {
            foreach ($params as $k => $v) {
                $value = str_replace('{' . $k . '}', $v, $value);
            }
        }
        return $value;
    }

    public function getCurrent() { return $this->currentLang; }

    public function getCurrentInfo() {
        $this->load();
        foreach ($this->languages as $lang) {
            if ($lang['code'] === $this->currentLang) return $lang;
        }
        return null;
    }

    /**
     * تغییر زبان با ذخیره در همه لایه‌ها
     */
    public function setLanguage($code) {
        if (!is_string($code) || !$this->languageExists($code)) {
            return false;
        }

        $this->currentLang = $code;

        // Session
        $_SESSION['lang'] = $code;

        // Cookie (فقط در وب)
        if (php_sapi_name() !== 'cli' && !headers_sent()) {
            @setcookie('partocms_lang', $code, [
                'expires' => time() + (365 * 86400),
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        // DB (اگر کاربر لاگین است)
        if (isset($_SESSION['user_id'])) {
            $this->saveUserLanguage($_SESSION['user_id'], $code);
        }

        // ریست کش داخلی
        $this->loaded = false;
        $this->translations = [];
        $this->languages = [];

        return true;
    }

    public function getLanguages() {
        $this->load();
        return $this->languages;
    }

    public function isRtl() {
        $info = $this->getCurrentInfo();
        return $info && $info['direction'] === 'rtl';
    }

    public function getDirection() { return $this->isRtl() ? 'rtl' : 'ltr'; }
    public function getBootstrapDir() { return $this->isRtl() ? 'rtl' : 'ltr'; }

    /**
     * HTML tag helper
     */
    public function getHtmlAttrs() {
        return 'lang="' . htmlspecialchars($this->getCurrent()) . '" dir="' . $this->getDirection() . '"';
    }
}
