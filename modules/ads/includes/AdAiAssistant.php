<?php
/**
 * PartoCMS - Ads Module - AdAiAssistant (Smart v2)
 * دستیار هوش مصنوعی هوشمند برای تولید و بهینه‌سازی تبلیغات
 *
 * @author Hooman Oliaei
 * @version 2.0.0
 *
 * معماری هوشمند:
 * - اگر مدل چندزبانه (dorna/aya/gemma/llama3.1) → مستقیم تولید
 * - اگر مدل کوچک (qwen2.5:1.5b) → AI انگلیسی + MultiTranslator
 */

require_once __DIR__ . '/../../../includes/AiAssistant.php';

class AdAiAssistant
{
    private AiAssistant $ai;
    private ?MultiTranslator $translator = null;

    public function __construct()
    {
        $this->ai = new AiAssistant();

        // لود MultiTranslator اگر موجود باشد
        $mtPath = __DIR__ . '/../../../admin/includes/multi_translator.php';
        if (file_exists($mtPath)) {
            require_once $mtPath;
            if (class_exists('MultiTranslator')) {
                $this->translator = new MultiTranslator(getDB());
            }
        }
    }

    public function isAvailable(): bool
    {
        return $this->ai->isEnabled();
    }

    public function getModel(): string
    {
        return $this->ai->getModel();
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص هوشمند استراتژی
    // ═══════════════════════════════════════════════════════════

    /**
     * آیا مدل فعلی از تولید مستقیم در زبان هدف پشتیبانی می‌کند؟
     */
    private function canGenerateDirectly(string $langCode): bool
    {
        $model = $this->getModel();

        // مدل‌های چندزبانه و فارسی که مستقیم پشتیبانی می‌کنند
        $multilingualModels = [
            'dorna-llama3',   // فارسی تخصصی
            'aya-expanse',    // ۲۳ زبان
            'gemma2',         // چندزبانه
            'llama3.1',       // چندزبانه
        ];

        foreach ($multilingualModels as $prefix) {
            if (stripos($model, $prefix) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * آیا زبان هدف انگلیسی است؟
     */
    private function isEnglish(string $langCode): bool
    {
        return strpos($langCode, 'en') === 0;
    }

    /**
     * گرفتن نام بومی یک زبان از کد آن
     */
    private function getLanguageName(?string $code = null): string
    {
        static $cache = [];

        if ($code === null || $code === '') {
            if (function_exists('getI18n')) {
                $info = getI18n()->getCurrentInfo();
                $code = $info['code'] ?? 'fa-IR';
            } else {
                $code = 'fa-IR';
            }
        }

        if (isset($cache[$code])) {
            return $cache[$code];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT native_name, name
            FROM languages
            WHERE code = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $name = $row['native_name'] ?? $row['name'] ?? $code;
        $cache[$code] = $name;
        return $name;
    }

    /**
     * گرفتن کد ۲ حرفی زبان (برای MultiTranslator)
     */
    private function getShortCode(string $code): string
    {
        return substr($code, 0, 2);
    }

    public function getAvailableLanguages(): array
    {
        $pdo = getDB();
        $stmt = $pdo->query("
            SELECT code, name, native_name, direction, flag
            FROM languages
            WHERE is_active = 1
            ORDER BY sort_order ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ═══════════════════════════════════════════════════════════
    // ۱. تولید متن تبلیغ (هوشمند)
    // ═══════════════════════════════════════════════════════════

    public function generateCopy(array $brief): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        $langCode = $brief['language'] ?? null;
        if ($langCode === null || $langCode === '') {
            $info = getI18n()->getCurrentInfo();
            $langCode = $info['code'] ?? 'fa-IR';
        }

        // تصمیم: مستقیم یا با ترجمه؟
        if ($this->canGenerateDirectly($langCode) || $this->isEnglish($langCode)) {
            // 🌟 مستقیم به زبان هدف
            return $this->generateCopyDirect($brief, $langCode);
        }

        // 🔄 AI انگلیسی + MultiTranslator
        return $this->generateCopyViaTranslation($brief, $langCode);
    }

    /**
     * تولید مستقیم به زبان هدف (برای مدل‌های چندزبانه)
     */
    private function generateCopyDirect(array $brief, string $langCode): array
    {
        $product  = trim($brief['product'] ?? '');
        $audience = trim($brief['audience'] ?? '');
        $tone     = $brief['tone'] ?? 'professional';
        $cta      = trim($brief['cta'] ?? '');
        $langName = $this->getLanguageName($langCode);

        if ($product === '') {
            return ['ok' => false, 'error' => 'نام محصول/خدمت الزامی است'];
        }

        $toneMap = [
            'professional' => 'حرفه‌ای و قابل اعتماد',
            'friendly'     => 'دوستانه و صمیمی',
            'exciting'     => 'هیجان‌انگیز و پرشور',
            'formal'       => 'رسمی و اداری',
            'humorous'     => 'طنزآمیز و سرگرم‌کننده',
            'luxury'       => 'لوکس و خاص',
        ];
        $toneText = $toneMap[$tone] ?? 'حرفه‌ای';
        $ctaLine = $cta !== ''
            ? "دعوت به اقدام مورد نظر: {$cta}"
            : "یک دعوت به اقدام مناسب پیشنهاد بده";

        $prompt = <<<PROMPT
برای یک بنر تبلیغاتی، متن جذاب و خلاقانه بنویس.

اطلاعات:
- محصول/خدمت: {$product}
- مخاطب هدف: {$audience}
- لحن: {$toneText}
- زبان خروجی: {$langName}
- {$ctaLine}

قوانین:
- عنوان حداکثر ۶۰ کاراکتر
- توضیح حداکثر ۱۵۰ کاراکتر
- دعوت به اقدام حداکثر ۳۰ کاراکتر
- حتماً جذاب و گیرا باشد
- خروجی حتماً به زبان {$langName} باشد
- ⚠️ مهم: تمام متن باید کاملاً به {$langName} باشد. هیچ کلمه انگلیسی، روسی، عربی یا هر زبان دیگری استفاده نکن.
- اعداد را به فارسی بنویس (۱، ۲، ۳ به جای 1، 2، 3)

خروجی را دقیقاً به این ساختار JSON بده:
{
  "title": "عنوان جذاب",
  "description": "توضیح کوتاه",
  "cta": "دعوت به اقدام"
}
PROMPT;

        $result = $this->ai->chatJson(
            [['role' => 'user', 'content' => $prompt]],
            ['temperature' => 0.85, 'num_predict' => 800]
        );

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => $result['error'] ?? 'خطا در تولید'];
        }

        $data = $result['data'];
        if (!isset($data['title'])) {
            return [
                'ok'    => false,
                'error' => 'پاسخ AI ساختار درست نداشت',
                'raw'   => $result['raw'] ?? '',
            ];
        }

        return [
            'ok'   => true,
            'data' => [
                'title'       => mb_substr(trim($data['title']), 0, 100),
                'description' => mb_substr(trim($data['description'] ?? ''), 0, 300),
                'cta'         => mb_substr(trim($data['cta'] ?? ''), 0, 50),
                'language'    => $langName,
                'strategy'    => 'direct',
            ],
        ];
    }

    /**
     * تولید با AI انگلیسی + ترجمه با MultiTranslator
     */
    private function generateCopyViaTranslation(array $brief, string $langCode): array
    {
        // ۱. تولید به انگلیسی
        $englishBrief = $brief;
        $englishBrief['language'] = 'en-US';

        // موقتاً زبان را به انگلیسی تغییر بده
        $result = $this->generateCopyDirect($englishBrief, 'en-US');

        if (empty($result['ok'])) {
            return $result;
        }

        $englishData = $result['data'];

        // ۲. ترجمه با MultiTranslator
        if ($this->translator === null) {
            // MultiTranslator موجود نیست → همان انگلیسی را برگردان
            $englishData['strategy'] = 'direct-english-fallback';
            return ['ok' => true, 'data' => $englishData];
        }

        $shortFrom = 'en';
        $shortTo   = $this->getShortCode($langCode);

        $translated = [
            'title'       => $this->translateField($englishData['title'], $shortFrom, $shortTo),
            'description' => $this->translateField($englishData['description'], $shortFrom, $shortTo),
            'cta'         => $this->translateField($englishData['cta'], $shortFrom, $shortTo),
            'language'    => $this->getLanguageName($langCode),
            'strategy'    => 'translate',
            'english_source' => $englishData,
        ];

        return ['ok' => true, 'data' => $translated];
    }

    /**
     * ترجمه یک فیلد با MultiTranslator
     */
    private function translateField(string $text, string $from, string $to): string
    {
        if ($text === '' || $this->translator === null) {
            return $text;
        }

        try {
            $result = $this->translator->translateAll($text, $from, $to, true);
            return $result['best']['text'] ?? $text;
        } catch (Throwable $e) {
            error_log('AdAiAssistant translation error: ' . $e->getMessage());
            return $text;
        }
    }

    // ═══════════════════════════════════════════════════════════
    // ۲. تولید چند variant
    // ═══════════════════════════════════════════════════════════

    public function generateVariants(array $brief, int $count = 3): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        $count = max(2, min(5, $count));

        $langCode = $brief['language'] ?? null;
        if ($langCode === null || $langCode === '') {
            $info = getI18n()->getCurrentInfo();
            $langCode = $info['code'] ?? 'fa-IR';
        }

        $product  = trim($brief['product'] ?? '');
        $audience = trim($brief['audience'] ?? '');
        $tone     = $brief['tone'] ?? 'professional';

        if ($product === '') {
            return ['ok' => false, 'error' => 'نام محصول/خدمت الزامی است'];
        }

        // اگر مدل مستقیم پشتیبانی نمی‌کند → انگلیسی تولید کن
        $useEnglish = !$this->canGenerateDirectly($langCode) && !$this->isEnglish($langCode);
        $genLangCode = $useEnglish ? 'en-US' : $langCode;
        $genLangName = $this->getLanguageName($genLangCode);

        $toneMap = [
            'professional' => 'حرفه‌ای',
            'friendly'     => 'دوستانه',
            'exciting'     => 'هیجان‌انگیز',
            'formal'       => 'رسمی',
            'humorous'     => 'طنزآمیز',
            'luxury'       => 'لوکس',
        ];
        $toneText = $toneMap[$tone] ?? 'حرفه‌ای';

        $prompt = <<<PROMPT
{$count} نسخه مختلف از متن تبلیغاتی برای محصول زیر بنویس.

محصول: {$product}
مخاطب: {$audience}
لحن: {$toneText}
زبان خروجی: {$genLangName}

قوانین مهم:
- هر نسخه باید کاملاً متفاوت از بقیه باشد (زاویه دید، سبک، کلمات)
- همه نسخه‌ها به زبان {$genLangName} باشند
- ⚠️ تمام متن باید کاملاً به {$genLangName} باشد. هیچ کلمه انگلیسی، روسی، عربی یا هر زبان دیگری استفاده نکن.
- اعداد را به فارسی بنویس (۱، ۲، ۳ به جای 1، 2, 3)
- از کلمات کلیشه‌ای پرهیز کن
- عنوان حداکثر ۶۰ کاراکتر
- توضیح حداکثر ۱۵۰ کاراکتر
- CTA حداکثر ۳۰ کاراکتر

خروجی JSON:
{
  "variants": [
    {"title": "...", "description": "...", "cta": "..."}
  ]
}
PROMPT;

        $result = $this->ai->chatJson(
            [['role' => 'user', 'content' => $prompt]],
            ['temperature' => 0.95, 'num_predict' => 1500]
        );

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => $result['error'] ?? 'خطا'];
        }

        $variants = $result['data']['variants'] ?? [];
        if (!is_array($variants) || empty($variants)) {
            return ['ok' => false, 'error' => 'هیچ variant برنگشت'];
        }

        // ترجمه اگر لازم است
        $shortFrom = 'en';
        $shortTo   = $this->getShortCode($langCode);

        $clean = [];
        foreach ($variants as $v) {
            if (!isset($v['title'])) continue;

            $title = trim($v['title']);
            $desc  = trim($v['description'] ?? '');
            $cta   = trim($v['cta'] ?? '');

            if ($useEnglish && $this->translator !== null) {
                $title = $this->translateField($title, $shortFrom, $shortTo);
                $desc  = $this->translateField($desc, $shortFrom, $shortTo);
                $cta   = $this->translateField($cta, $shortFrom, $shortTo);
            }

            $clean[] = [
                'title'       => mb_substr($title, 0, 100),
                'description' => mb_substr($desc, 0, 300),
                'cta'         => mb_substr($cta, 0, 50),
            ];
        }

        return [
            'ok'   => true,
            'data' => [
                'variants' => $clean,
                'language' => $this->getLanguageName($langCode),
                'strategy' => $useEnglish ? 'translate' : 'direct',
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // ۳. بهبود متن موجود
    // ═══════════════════════════════════════════════════════════

    public function improveCopy(string $currentTitle, string $currentDesc = '', ?string $langCode = null): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        $currentTitle = trim($currentTitle);
        if ($currentTitle === '') {
            return ['ok' => false, 'error' => 'عنوان فعلی خالی است'];
        }

        if ($langCode === null || $langCode === '') {
            $info = getI18n()->getCurrentInfo();
            $langCode = $info['code'] ?? 'fa-IR';
        }

        $langName = $this->getLanguageName($langCode);

        $prompt = <<<PROMPT
متن تبلیغ زیر را بهبود بده. جذاب‌تر، کوتاه‌تر و گیراتر کن.

عنوان فعلی: {$currentTitle}
توضیح فعلی: {$currentDesc}
زبان: {$langName}

خروجی JSON:
{
  "title": "عنوان بهبودیافته",
  "description": "توضیح بهبودیافته",
  "changes": "توضیح کوتاه تغییرات"
}
PROMPT;

        $result = $this->ai->chatJson(
            [['role' => 'user', 'content' => $prompt]],
            ['temperature' => 0.7, 'num_predict' => 800]
        );

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => $result['error'] ?? 'خطا'];
        }

        $data = $result['data'];

        return [
            'ok'   => true,
            'data' => [
                'title'       => mb_substr(trim($data['title'] ?? ''), 0, 100),
                'description' => mb_substr(trim($data['description'] ?? ''), 0, 300),
                'changes'     => trim($data['changes'] ?? ''),
            ],
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // ۴. پیشنهاد Targeting
    // ═══════════════════════════════════════════════════════════

    public function suggestTargeting(array $ad, array $categories, array $tags = []): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'دستیار هوشمند غیرفعال است'];
        }

        $title = $ad['title'] ?? '';
        $desc  = $ad['description'] ?? '';

        $catList = '';
        foreach ($categories as $c) {
            $catList .= '- #' . $c['id'] . ': ' . $c['name'] . "\n";
        }

        $tagList = '';
        if (!empty($tags)) {
            foreach ($tags as $t) {
                $tagList .= '- #' . $t['id'] . ': ' . $t['name'] . "\n";
            }
        }

        $prompt = <<<PROMPT
این تبلیغ را تحلیل کن و بگو در کدام دسته‌ها و برچسب‌ها نمایش داده شود.

تبلیغ:
- عنوان: {$title}
- توضیح: {$desc}

دسته‌های موجود:
{$catList}

برچسب‌های موجود:
{$tagList}

خروجی JSON:
{
  "suggested_categories": [1, 2],
  "suggested_tags": [5, 7],
  "reason": "دلیل انتخاب"
}
PROMPT;

        $result = $this->ai->chatJson(
            [['role' => 'user', 'content' => $prompt]],
            ['temperature' => 0.5, 'num_predict' => 800]
        );

        if (empty($result['ok'])) {
            return ['ok' => false, 'error' => $result['error'] ?? 'خطا'];
        }

        return ['ok' => true, 'data' => $result['data']];
    }
}
