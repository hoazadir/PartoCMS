<?php
/**
 * PartoCMS - Content i18n Helper (v3)
 * نمایش ترجمه محتوا در فرانت‌اند بر اساس زبان فعلی
 *
 * v3: اولویت URL → Cookie(partocms_lang) → Session → I18n → default
 */

// ============================================================
//   زبان فعلی
// ============================================================

if (!function_exists('getCurrentLanguageInfo')) {
    /**
     * اطلاعات کامل زبان فعلی
     * اولویت: URL param → Cookie → Session → I18n → DB default
     */
    function getCurrentLanguageInfo() {
        static $cached = null;
        static $cachedKey = null;

        try {
            // مقادیر کاندید
            $urlCode     = $_GET['set_lang'] ?? null;
            $cookieCode  = $_COOKIE['partocms_lang'] ?? null;
            $sessionCode = $_SESSION['lang'] ?? null;

            // کلید کش
            $cacheKey = ($urlCode ?: '') . '|' . ($cookieCode ?: '') . '|' . ($sessionCode ?: '');
            if ($cached !== null && $cachedKey === $cacheKey) {
                return $cached;
            }

            // انتخاب اولویت‌دار
            $code = null;
            if (!empty($urlCode) && is_string($urlCode)) {
                $code = $urlCode;          // ۱. URL
            } elseif (!empty($cookieCode) && is_string($cookieCode)) {
                $code = $cookieCode;       // ۲. Cookie (main!)
            } elseif (!empty($sessionCode) && is_string($sessionCode)) {
                $code = $sessionCode;      // ۳. Session
            }

            $pdo = getDB();

            // اگه کد پیدا شد، از DB بگیر
            if ($code) {
                $stmt = $pdo->prepare("
                    SELECT id, code, name, native_name, direction, flag,
                           is_active, is_default, sort_order, created_at
                    FROM languages
                    WHERE code = ? AND is_active = 1
                    LIMIT 1
                ");
                $stmt->execute([$code]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $cached    = $row;
                    $cachedKey = $cacheKey;
                    return $row;
                }
            }

            // fallback: I18n
            $i18n = getI18n();
            if ($i18n) {
                $info = $i18n->getCurrentInfo();
                if ($info && !empty($info['code'])) {
                    $cached    = $info;
                    $cachedKey = $cacheKey;
                    return $info;
                }
            }

            // آخرین راه: زبان پیش‌فرض از DB
            $stmt = $pdo->query("
                SELECT id, code, name, native_name, direction, flag,
                       is_active, is_default, sort_order, created_at
                FROM languages
                WHERE is_default = 1 AND is_active = 1
                LIMIT 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $cached    = $row ?: null;
            $cachedKey = $cacheKey;
            return $cached;

        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('isDefaultLanguage')) {
    function isDefaultLanguage() {
        $info = getCurrentLanguageInfo();
        if (!$info) return true;
        if (!empty($info['is_default'])) return true;
        $code = $info['code'] ?? 'fa-IR';
        return in_array($code, ['fa-IR', 'fa', 'fa_IR'], true);
    }
}

if (!function_exists('getCurrentLanguageId')) {
    function getCurrentLanguageId($pdo = null) {
        $info = getCurrentLanguageInfo();
        if (!$info) return null;

        if (!empty($info['id'])) return (int) $info['id'];
        if (empty($info['code'])) return null;

        if (!$pdo) $pdo = getDB();
        try {
            $stmt = $pdo->prepare("SELECT id FROM languages WHERE code = ? LIMIT 1");
            $stmt->execute([$info['code']]);
            $id = $stmt->fetchColumn();
            return $id ? (int) $id : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

// ============================================================
//   ترجمه پست
// ============================================================

if (!function_exists('applyTranslationToPost')) {
    function applyTranslationToPost(array $post, $pdo = null) {
        if (empty($post['id'])) return $post;
        if (isDefaultLanguage()) return $post;

        $langId = getCurrentLanguageId($pdo);
        if (!$langId) return $post;

        if (!$pdo) $pdo = getDB();

        try {
            $stmt = $pdo->prepare("
                SELECT title, excerpt, content, meta_title, meta_description
                FROM content_translations
                WHERE content_id = ?
                  AND language_id = ?
                  AND status IN ('published', 'auto')
                LIMIT 1
            ");
            $stmt->execute([$post['id'], $langId]);
            $trans = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$trans) return $post;

            if (!empty($trans['title']))            $post['title']            = $trans['title'];
            if (!empty($trans['excerpt']))          $post['excerpt']          = $trans['excerpt'];
            if (!empty($trans['content']))          $post['content']          = $trans['content'];
            if (!empty($trans['meta_title']))       $post['meta_title']       = $trans['meta_title'];
            if (!empty($trans['meta_description'])) $post['meta_description'] = $trans['meta_description'];

            $post['_translated']     = true;
            $post['_translation_id'] = $langId;
        } catch (Throwable $e) {}

        return $post;
    }
}

if (!function_exists('applyTranslationsToPosts')) {
    function applyTranslationsToPosts(array $posts, $pdo = null) {
        if (empty($posts)) return $posts;
        if (isDefaultLanguage()) return $posts;

        $langId = getCurrentLanguageId($pdo);
        if (!$langId) return $posts;

        if (!$pdo) $pdo = getDB();

        $ids = array_values(array_filter(array_column($posts, 'id')));
        if (empty($ids)) return $posts;

        try {
            $in   = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT content_id, title, excerpt, content
                FROM content_translations
                WHERE content_id IN ($in)
                  AND language_id = ?
                  AND status IN ('published', 'auto')
            ");
            $stmt->execute(array_merge($ids, [$langId]));

            $map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $map[(int) $row['content_id']] = $row;
            }

            foreach ($posts as &$p) {
                $pid = (int) ($p['id'] ?? 0);
                if (isset($map[$pid])) {
                    $t = $map[$pid];
                    if (!empty($t['title']))   $p['title']   = $t['title'];
                    if (!empty($t['excerpt'])) $p['excerpt'] = $t['excerpt'];
                    if (!empty($t['content'])) $p['content'] = $t['content'];
                    $p['_translated'] = true;
                }
            }
            unset($p);
        } catch (Throwable $e) {}

        return $posts;
    }
}

// ============================================================
//   ترجمه دسته
// ============================================================

if (!function_exists('applyTranslationToCategory')) {
    function applyTranslationToCategory(array $category, $pdo = null) {
        if (empty($category['id'])) return $category;
        if (isDefaultLanguage()) return $category;

        $langId = getCurrentLanguageId($pdo);
        if (!$langId) return $category;
        if (!$pdo) $pdo = getDB();

        // ۱. چک کش
        try {
            $stmt = $pdo->prepare("
                SELECT name, description, provider
                FROM category_translations
                WHERE category_id = ? AND language_id = ?
                LIMIT 1
            ");
            $stmt->execute([$category['id'], $langId]);
            $trans = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($trans && !empty($trans['name'])) {
                $category['name'] = $trans['name'];
                if (!empty($trans['description'])) {
                    $category['description'] = $trans['description'];
                }
                $category['_translated'] = true;
                $category['_provider']   = $trans['provider'];
                return $category;
            }
        } catch (Throwable $e) {}

        // ۲. ترجمه و ذخیره
        return translateAndCacheCategory($category, $langId, $pdo);
    }
}

if (!function_exists('translateAndCacheCategory')) {
    function translateAndCacheCategory(array $category, int $langId, $pdo) {
        if (!class_exists('MultiTranslator')) {
            $path = __DIR__ . '/../admin/includes/multi_translator.php';
            if (file_exists($path)) require_once $path;
        }
        if (!class_exists('MultiTranslator')) return $category;

        // زبان مقصد
        try {
            $stmt = $pdo->prepare("SELECT code FROM languages WHERE id = ? LIMIT 1");
            $stmt->execute([$langId]);
            $targetCode = $stmt->fetchColumn();
            if (!$targetCode) return $category;
        } catch (Throwable $e) { return $category; }

        $sourceCode = 'fa-IR';

        try {
            $mt = new MultiTranslator($pdo);
            $name = ''; $desc = ''; $provider = 'unknown'; $score = 0;

            if (!empty($category['name'])) {
                $r = $mt->translateAll($category['name'], $sourceCode, $targetCode, true);
                if (!empty($r['best']['text'])) {
                    $name     = $r['best']['text'];
                    $provider = $r['best']['provider'] ?? 'unknown';
                    $score    = (int) ($r['best']['score'] ?? 0);
                }
            }
            if (!empty($category['description'])) {
                $r = $mt->translateAll($category['description'], $sourceCode, $targetCode, true);
                if (!empty($r['best']['text'])) {
                    $desc = $r['best']['text'];
                }
            }

            if ($name !== '') {
                $stmt = $pdo->prepare("
                    INSERT INTO category_translations
                        (category_id, language_id, name, description, provider, quality_score)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        name           = VALUES(name),
                        description    = VALUES(description),
                        provider       = VALUES(provider),
                        quality_score  = VALUES(quality_score),
                        translated_at  = NOW()
                ");
                $stmt->execute([$category['id'], $langId, $name, $desc, $provider, $score]);

                $category['name']        = $name;
                $category['description'] = $desc;
                $category['_translated'] = true;
                $category['_provider']   = $provider;
            }
        } catch (Throwable $e) {}

        return $category;
    }
}

// ============================================================
//   ترجمه batch دسته‌ها
// ============================================================

if (!function_exists('applyTranslationsToCategories')) {
    function applyTranslationsToCategories(array $categories, $pdo = null) {
        if (empty($categories)) return $categories;
        if (isDefaultLanguage()) return $categories;

        $langId = getCurrentLanguageId($pdo);
        if (!$langId) return $categories;
        if (!$pdo) $pdo = getDB();

        $ids = array_values(array_filter(array_column($categories, 'id')));
        if (empty($ids)) return $categories;

        try {
            $in   = str_repeat('?,', count($ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT category_id, name, description, provider
                FROM category_translations
                WHERE category_id IN ($in) AND language_id = ?
            ");
            $stmt->execute(array_merge($ids, [$langId]));

            $map = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $map[(int) $row['category_id']] = $row;
            }

            foreach ($categories as &$c) {
                $cid = (int) ($c['id'] ?? 0);
                if (isset($map[$cid]) && !empty($map[$cid]['name'])) {
                    $c['name'] = $map[$cid]['name'];
                    if (!empty($map[$cid]['description'])) {
                        $c['description'] = $map[$cid]['description'];
                    }
                    $c['_translated'] = true;
                    $c['_provider']   = $map[$cid]['provider'];
                }
            }
            unset($c);
        } catch (Throwable $e) {}

        return $categories;
    }
}
