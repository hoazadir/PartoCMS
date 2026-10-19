# 📋 PartoCMS Development Roadmap

> **آخرین بروزرسانی:** 2026-10-18
> **طراحی و برنامه‌نویسی و مالک:** Hooman Oliaei (هومان اولیایی)
> **مخزن:** https://github.com/hoazadir/PartoCMS
> **نسخه:** v2.0 — پس از فاز ۳-F

---

## 🎯 خلاصه پروژه

**PartoCMS** یک CMS/Platform اختصاصی است که با PHP 8.5 و MariaDB در محیط Termux توسعه می‌یابد و برای نصب در هاست/VPS/کلود/سرور شخصی طراحی شده است.

**محیط فنی:**
- PHP: 8.5
- Database: MariaDB 12.3
- DB Name: `grapesjs_cms`
- Project Root: `~/PartoCMS/`
- **Libraries:** mPDF (PDF), GrapesJS (Editor), Chart.js, Bootstrap 5.3, Vazirmatn (Font)
- **AI Providers:** Ollama, OpenRouter, Groq, Gemini, OpenAI
- **Translation Providers:** DeepL, Microsoft, Yandex, Google, Lingva, Libre, MyMemory

---

## 📊 پیشرفت کلی پروژه

| فاز | عنوان | وضعیت | درصد |
|------|--------|--------|------|
| فاز ۰ | تثبیت پایه | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۱ | مستندسازی + امنیت ۱۰ لایه | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۲ | ماژول فروشگاه | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۲-B | مدیریت و گزارش‌ها | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۲-C | i18n — ۲۰ زبان + ترجمه محتوا | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۳ | ماژول تبلیغات + اسلایدشو | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۳-A | AI Copywriting | ✅ ۱۰۰٪ | ۱۰۰ |
| فاز ۳-B | AI Gateway + Cloud Providers | ✅ ۱۰۰٪ | ۱۰۰ |
| **فاز ۳-C** | **Anti-AdBlock** | **✅ ۱۰۰٪** | ۱۰۰ |
| **فاز ۳-E** | **Newsletter (DDD)** | **✅ ۱۰۰٪** | ۱۰۰ |
| **فاز ۳-F** | **Reviews + Comments + Registry** | **✅ ۱۰۰٪** | ۱۰۰ |
| **FAQ** | **سوالات متداول** | **✅ ۱۰۰٪** | ۱۰۰ |
| **B7** | **Content→Dynamic Registry** | **✅ ۱۰۰٪** | ۱۰۰ |
| **فاز ۳-G** | **Testimonials Module** | **✅ ۱۰۰٪** | ۱۰۰ |
| **فاز ۳-H** | **Team Module** | **✅ ۱۰۰٪** | ۱۰۰ |
| **فاز ۳-I** | **Services Module** | **✅ ۱۰۰٪** | ۱۰۰ |
| فاز ۳-D | Smart Installer + Tier | 🟡 ~۷۵٪ | ۷۵ |
| فاز ۴ | قالب‌ساز + ۶۰ تمپلیت | 🚨 ۰٪ | ۰ |
| فاز ۵ | ماژول وبلاگ | 🟡 ~۵۰٪ | ۵۰ |
| فاز ۶ | ماژول CRM | 🟡 ~۱۵٪ | ۱۵ |
| فاز ۷ | ماژول Excel | 🟡 ~۳۰٪ | ۳۰ |
| فاز ۸ | ماژول PowerPoint | ⏳ | ۰ |
| فاز ۹ | سیستم لایسنس | ⏳ | ۰ |
| فاز ۱۰ | اینستالر هوشمند | (تلفیق با ۳-D) | ۷۵ |
| فاز ۱۱ | مستندات کامل | 🟡 ~۴۰٪ | ۴۰ |
| فاز ۱۲ | تست و دیباگ | ⏳ | ۰ |

---

## ✅ فاز ۰: تثبیت پایه ✅ ۱۰۰٪

- [x] حذف `.git` از web root
- [x] ساخت `.htaccess` در ریشه و `admin/`
- [x] حذف `curl_close()` از `AiAssistant`
- [x] رفع Cron Whisper
- [x] حذف `mkcert` و `.bak`
- [x] ساخت `templates/`
- [x] ساخت `robots.txt` و `sitemap.xml`
- [x] Log Rotation
- [x] `manifest.json` برای ماژول‌ها

---

## 🛡️ فاز ۱: مستندسازی فنی + امنیت ۱۰ لایه ✅ ۱۰۰٪

**بخش A: مستندسازی فنی**
- `docs/API.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`
- `docs/I18N.md`, `docs/MODULES.md`, `docs/USER_GUIDE.md`
- `CONTRIBUTING.md`, `NOTICE`, `LICENSE`, `LICENSE-fa.html`

**بخش B: امنیت ۱۰ لایه**

| # | قابلیت | فایل |
|---|--------|------|
| ۱ | PHP Hardening | `security_config.php` |
| ۲ | FIM | `fim.php` |
| ۳ | Malware Scanner | `fim.php` |
| ۴ | Telegram Alerts | `telegram_notifier.php` |
| ۵ | Telegram Setup | `telegram_setup.php` |
| ۶ | VirusTotal | `virustotal_scanner.php` |
| ۷ | VirusTotal Setup | `virustotal_setup.php` |
| ۸ | Rate Limiting | `rate_limiter.php` |
| ۹ | 2FA (TOTP) | `totp.php` |
| ۱۰ | 2FA Setup | `2fa_setup.php` |
| ۱۱ | Security Audit | `security_audit.php` |
| ۱۲ | Security Dashboard | `security_dashboard.php` |
| ۱۳ | Security Graphs | `security_graphs.php` |
| ۱۴ | Backup Manager | `backup_manager.php` |
| ۱۵ | CSRF | همه فرم‌ها |
| ۱۶ | Logout | `logout.php` |

**جداول:** `file_hashes`, `security_logs`, `login_attempts`, `login_blocks`, `user_2fa`, `user_2fa_backup`

**Cron:** `cron_security_check.php`, `cron_weekly_report.php`

---

## 🛒 فاز ۲: ماژول فروشگاه (E-Commerce) ✅ ۱۰۰٪

**آمار:**
- ۴۳ فایل PHP
- ۸,۱۱۸ خط کد
- ۱۸ جدول دیتابیس

**زیرفازها:**

| # | زیرفاز | فایل‌های کلیدی | خطوط |
|---|---------|----------------|-------|
| ۲.۱ | ساختار پایه فروشگاه | `index.php`, `ShopManager.php` | ~۵۰۰ |
| ۲.۲ | محصولات و دسته‌بندی | `product.php`, `admin/products.php` | ~۱۵۰۰ |
| ۲.۳ | سبد خرید + آپلود | `cart.php`, `ajax/` | ~۱۱۰۰ |
| ۲.۴ | سفارشات + Checkout | `checkout.php`, `order-success.php` | ~۱۲۰۰ |
| ۲.۵ | درگاه‌های ایرانی | `zarinpal.php`, `idpay.php` | ~۶۰۰ |
| ۲.۶ | درگاه‌های بین‌المللی | `paypal.php`, `stripe.php` | ~۳۵۰ |
| ۲.۷ | درگاه‌های ارز دیجیتال | `coingate.php`, `nowpayments.php` | ~۳۰۰ |
| ۲.۸ | تخفیف، کوپن، ارسال | `admin/coupons.php`, `admin/shipping.php` | ~۱۲۰۰ |
| ۲.۹ | گزارش فروش | `admin/reports.php`, `ReportManager.php` | ~۵۷۰ |
| ۲.۱۰ | فاکتور PDF | `InvoiceGenerator.php`, `InvoiceHelper.php` | ~۶۷۰ |

**جدول‌های فروشگاه (۱۸):**
`shop_products`, `shop_categories`, `shop_product_images`, `shop_product_variants`, `shop_product_attributes`, `shop_carts`, `shop_cart_items`, `shop_orders`, `shop_order_items`, `shop_order_status_history`, `shop_customers`, `shop_coupons`, `shop_coupon_usage`, `shop_shipping_methods`, `shop_tax_rates`, `shop_payment_gateways`, `shop_transactions`, `shop_settings`

**ویژگی‌های کلیدی:**
- مدیریت کامل محصولات (با variants و attributes)
- سبد خرید session-based
- Checkout کامل با آدرس
- ۶ درگاه پرداخت (ایرانی/بین‌المللی/کریپتو)
- کوپن تخفیف + تخفیف خودکار
- روش‌های ارسال + مالیات
- گزارش فروش با Chart.js
- فاکتور PDF با mPDF
- پشتیبانی کامل i18n (fa/ar/en)
- تقویم شمسی/قمری/میلادی
- اعداد فارسی/عربی
- شماره فاکتور صعودی
- فاصله‌های داینامیک بر اساس تعداد آیتم

---

## 📊 فاز ۲-B: مدیریت و گزارش‌ها ✅ ۱۰۰٪

- Backup خودکار با gzip
- Clean Old Backups (۳۰ روز)
- گزارش هفتگی HTML ایمیل
- نمودارهای Chart.js (line, pie, bar)
- Dashboard گرافیکی

---

## 🌍 فاز ۲-C: i18n — ۲۰ زبان + ترجمه محتوا ✅ ۱۰۰٪

### i18n پایه

**۲۰ زبان فعال:**
`fa-IR`, `en-US`, `en-GB`, `ar-SA`, `tr-TR`, `de-DE`, `fr-FR`, `es-ES`, `ru-RU`, `zh-CN`, `ja-JP`, `ko-KR`, `it-IT`, `pt-BR`, `nl-NL`, `pl-PL`, `hi-IN`, `id-ID`, `vi-VN`, `he-IL`

**جداول:** `languages`, `translation_keys`, `translations`, `user_preferences`

**فایل‌ها:**
- `admin/includes/i18n.php`
- `admin/includes/language_switcher.php`
- `admin/languages.php`
- `admin/translations.php`

**توابع `config.php`:**
- `getI18n()`
- `__t($key, $params, $default)`
- `__html_attrs()`
- `__dir()`

**ویژگی‌ها:**
- Language persistence: DB + Cookie + Session + Browser
- کش فایل: `logs/i18n_cache/`
- RTL/LTR خودکار در ۴۷ فایل
- Bootstrap RTL/LTR خودکار

### ترجمه محتوا — MultiTranslator v6.1

**۷ Provider:**
DeepL, Microsoft, Yandex, Google, Lingva, Libre, MyMemory

**ویژگی‌ها:**
- ترجمه موازی از همه providerها
- Quality Scoring (0-100)
- تشخیص garbage
- `tag_handling=html` برای DeepL
- Cache با اعتبارسنجی مجدد
- Fallback خودکار

**فایل‌ها:**
- `admin/includes/multi_translator.php`
- `admin/content_translate.php`
- `admin/translator_settings.php`

**جداول:**
`content_translations`, `translation_versions`, `translation_cache`, `provider_stats`, `category_translations`

### نمایش ترجمه در فرانت‌اند

**`includes/content_i18n.php` v3:**
- `getCurrentLanguageInfo()`, `getCurrentLanguageId()`, `isDefaultLanguage()`
- `applyTranslationToPost()` / `applyTranslationsToPosts()`
- `applyTranslationToCategory()` / `applyTranslationsToCategories()`
- `translateAndCacheCategory()`

**فایل‌های فرانت‌اند:**
`post.php`, `index.php`, `category.php`, `search.php`, `login.php`, `register.php`

---


## 📢 فاز ۳: ماژول تبلیغات + اسلایدشو ✅ ۱۰۰٪

**آمار:** ۱۹ فایل PHP، ۷,۲۳۶ خط کد

**فایل‌ها:**
- `modules/ads/includes/AdManager.php` (837 خط)
- `modules/ads/includes/AdRenderer.php` (934 خط)
- `modules/ads/includes/AdWidget.php` (488 خط)
- `modules/ads/includes/AdTargeting.php` (371 خط)
- `modules/ads/includes/AdTracker.php` (295 خط)
- `modules/ads/includes/AdEffects.php` (236 خط)
- `modules/ads/includes/AdCache.php` (150 خط)
- `modules/ads/includes/AdObfuscator.php` (238 خط)

**ویژگی‌ها:**
- ✅ آپلود بنر و اسلایدشو
- ✅ ۸ موقعیت: `header`, `sidebar`, `footer`, `in-content`, `popup`, `sticky-footer`, `slider-home`, `home-top`
- ✅ بازه زمانی نمایش
- ✅ آمار نمایش (`ad_impressions`) و کلیک (`ad_clicks`)
- ✅ Targeting پیشرفته: `all` / `manual` / `rules`
- ✅ Auto-switch target_mode
- ✅ Prioritization + Campaigns
- ✅ AdSense + HTML + Text Ads
- ✅ گزارش‌های تحلیلی (`ad-reports.php`)
- ✅ ۷ حالت نمایش: single, slider, carousel, rotation, grid, stack, marquee
- ✅ ۴۵ افکت
- ✅ RTL + i18n (۲۰ زبان)
- ✅ AdWidget v3 (نمایش در همه صفحات)
- ✅ Context Detection خودکار (post/category/index)

**جداول:** `ads`, `ad_positions`, `ad_campaigns`, `ad_clicks`, `ad_impressions`, `ad_target_rules`, `ad_banners`

---

## 🤖 فاز ۳-A: AI Copywriting ✅ ۱۰۰٪

**فایل‌های کلیدی:**
- `modules/ads/includes/AdAiAssistant.php` (536 خط)
- `modules/ads/ajax/ad-ai-generate.php`
- `includes/AiAssistant.php` (`chatJson`)
- UI در `ad-edit.php` (کارت AI + Modal)

**ویژگی‌ها:**
- ✅ تولید متن تبلیغ از توضیح کوتاه
- ✅ چند variant (۲-۵ نسخه)
- ✅ بهبود متن موجود
- ✅ پیشنهاد Targeting
- ✅ پشتیبانی ۲۰ زبان
- ✅ دکمه «✨ کمک AI» در `ad-edit.php`
- ✅ Modal + One-click apply
- ✅ Robust JS init

**تست شده:**
- زمان پاسخ: ۱۰.۲۷s (ابری) vs ۷۰-۱۲۷s (محلی)
- کیفیت: متن حرفه‌ای فارسی
- Provider: NVIDIA Nemotron 3 Ultra 550B

---

## 🧠 فاز ۳-B: AI Gateway + Cloud Providers ✅ ۱۰۰٪

**فایل‌های کلیدی:**
- `includes/AI/AIGateway.php` (Fallback خودکار)
- `includes/AI/AIEnvironment.php` (تشخیص محیط)
- `includes/AI/AIProxyManager.php` (SOCKS5/HTTP)
- `includes/AI/AICircuitBreaker.php` (Cooldown خودکار)
- `includes/AI/Providers/LLM/OpenRouterProvider.php`
- `includes/AI/Providers/LLM/GroqProvider.php`
- `includes/AI/Providers/LLM/GeminiProvider.php`
- `includes/AI/Providers/LLM/OpenAIProvider.php`
- `includes/AI/Providers/LLM/OllamaProvider.php`

**UI مدیریت:**
- `admin/ai_providers.php` (۶ تب)
- گروه sidebar «🤖 هوش مصنوعی»
- دکمه «تست اتصال» برای هر provider
- ۱۶ مدل رایگان در OpenRouter dropdown
- بخش Proxy در تب تنظیمات
- Drag & Drop برای ترتیب Fallback

**تست شده:**
- ✅ OpenRouter: ۴۶۶ مدل در دسترس
- ✅ NVIDIA Nemotron: ۲.۱s پاسخ
- ✅ Fallback chain کار می‌کند
- ✅ Circuit Breaker + Cooldown فعال

---

## 🛡️ فاز ۳-C: Anti-AdBlock ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-04
**کامیت:** `11d0a70 feat(ads): Anti-AdBlock with Base64 dynamic injection (WORKING!)`

**راه‌حل اعمال‌شده:**
- ✅ Obfuscation کلاس‌ها: `ad-slot` → `pcms-slot`, `ad-slider` → `pcms-slider`, ...
- ✅ تغییر attributeها: `data-ad-id` → `data-pcms-id`
- ✅ تزریق پویا با Base64
- ✅ ادغام با `AdRenderer` (خط ۹۲-۹۳)
- ✅ ۲۳۸ خط در `AdObfuscator.php`

**وضعیت:** کامل و کامیت‌شده. در نسخه‌های جدید Aether نیز رفتار مشابه دیده شده.

**⚠️ نکته برای تست:**
- Edge Tracking Prevention باید در Basic باشد
- AdBlock Plus + uBlock باید تست شوند (نیمه‌کاره در COMPONENTS.md)

---


## 📮 فاز ۳-E: Newsletter (DDD + SOLID) ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-12
**معماری:** DDD + SOLID + PSR-4 + DI Container

**آمار:**
- ۱۹ فایل PHP
- ~۲۵۰۰ خط کد
- ۳ جدول DB
- ~۶۰ assertion تست موفق

**پیشرفت فازها:**

| فاز | عنوان | وضعیت |
|:---:|-------|:-----:|
| ۱ | Skeleton + DI Container | ✅ |
| ۲ | Value Objects + Exceptions | ✅ |
| ۳ | Entities + SendStatus | ✅ |
| ۴ | Repositories (PDO) + Schema | ✅ |
| ۵ | Services | ✅ |
| ۶ | Admin UI + AJAX + Frontend | ✅ |
| ۷ | Tests + README | ✅ |
| ۸.۱ | Cron Worker | ✅ |

**امکانات:**
- ✅ پنل ادمین کامل (مشترکین، کمپین‌ها، تنظیمات SMTP)
- ✅ ارسال دسته‌ای و خودکار (Auto-send AJAX + Cron Worker)
- ✅ بهینه‌سازی `SMTPKeepAlive` (سرعت ارسال ۳.۵ برابر بهبود)
- ✅ فرانت‌اند: شورت‌کد `[newsletter]` و ویجت
- ✅ یکپارچگی با `config.local.php` برای SITE_URL (تست موبایل)

**کامیت‌های مرتبط:**
- `6fa30f1` — فاز ۱
- `012f548` — فاز ۲
- `fa414fc` — فاز ۳
- `bcb227a` — فاز ۴
- `af6fa5f` — فاز ۸.۱ (Cron Worker)
- `0f5f1d6` — SMTPKeepAlive + idempotent startSending
- `f705228` — AJAX isolation + Tehran timezone

**جداول:** `newsletter_subscribers`, `newsletter_campaigns`, `newsletter_sends`

**⚠️ نکته Gmail:** Gmail «self-send» (به همان آدرس SMTP) را drop می‌کند. راه‌حل: App Password جدید از `hooman.oliaei` + `Send mail as` در Gmail.

---


## 🆕 فاز ۳-F: Reviews + Comments + Registry Fix ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-12
**کامیت‌ها:** `78a1cf4`, `93cbb28`, `594b834`

### Reviews Module ✅

- **مسیر:** `modules/reviews/`
- **۱۰ فایل PHP** (admin, ajax, database, includes, templates)
- **جداول:** `reviews`, `review_votes`, `review_reports`, `review_settings`

**امکانات:**
- ✅ ثبت نظر + امتیاز ستاره‌ای (AJAX)
- ✅ رأی «مفید / غیرمفید»
- ✅ moderation + صف تأیید
- ✅ polymorphic (محصول، مقاله، ملک، خودرو، ...)
- ✅ 3-state toggle (disabled / pending / published)
- ✅ ۴ ردیف در DB (داده واقعی)

**صفحات:**
- `list.php` — لیست همه نظرات
- `pending.php` — در انتظار تأیید
- `settings.php` — تنظیمات
- `view.php` — نمایش یک نظر
- AJAX endpoints

### Comments Module ✅

- **کامیت:** `78a1cf4 feat(comments): convert comments to standalone module`
- **مسیر:** `modules/comments/`
- **۵ فایل PHP** (admin, ajax, database, includes, menu)
- **جدول:** `comments`
- **۳ ردیف در DB (داده واقعی)**
- تبدیل از حالت توکار به ماژول مستقل

### Registry Fix ✅

**مشکل:** گروه `marketing` در sidebar نمایش داده نمی‌شد → `ads` و `reviews` بلوکه بودند.

**راه‌حل (کامیت `594b834`):**

| فایل | خط | تغییر |
|------|-----|-------|
| `admin/includes/module_registry.php` | ۲۶۵ | `$dynamicGroups` — افزودن `marketing`, `content`, `commerce` |
| `admin/includes/module_registry.php` | ۴۴۳ | `getGroupTitle('marketing') => 'بازاریابی'` |
| `admin/includes/module_registry_sidebar.php` | ۱۶۴ | `$groupIcons['marketing'] => '💼'` |

**تأیید:** `php -l` سبز، همه ماژول‌ها در sidebar نمایش داده می‌شوند.

---

## 🆕 FAQ Module ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-12
**کامیت:** `8c2a6a5 docs(faq): add README.md and update manifest.json`

- **مسیر:** `modules/faq/`
- **جدول:** `faqs`
- **معماری:** DDD (مثل Newsletter)
- **ویرایشگر:** TinyMCE با پشتیبانی RTL
- **فرانت‌اند:** ویجت + شورت‌کد `[faq]` و `[faq category="..."]`
- **طراحی:** آکاردئونی (Accordion)
- **مستندات:** `README.md` + `manifest.json`

**کامیت‌های مرتبط:**
- `cf342c1` — build complete FAQ module
- `37ceb90` — connect frontend shortcode to post content
- `8c2a6a5` — README + manifest

---

## 🆕 B7: Content Group → Dynamic Registry ✅ ۱۰۰٪

**تاریخ:** 2026-10-12
**کامیت:** `93cbb28`

- انتقال `content/categories/media/comments` به `dynamicGroups`
- `canAccess` با permission check
- حذف hardcoded (۳۷ خط)
- استانداردسازی ۱۰ فایل CSS legacy

---


## 🚨 بدهی فنی فوری (قبل از توسعه بعدی)

| # | مورد | وضعیت | اقدام |
|---|------|-------|------|
| ۱ | `TestDb` (۱ ردیف تستی) | 🟡 | `DROP TABLE TestDb;` |
| ۲ | `utyfi` (۰ ردیف) | 🟡 | `DROP TABLE utyfi;` |
| ۳ | پسورد DB لو رفته (`@PASS757ho@` — در ۳ مکالمه) | 🔴 | **تعویض پسورد + به‌روزرسانی `config.php`** |
| ۴ | `config.local.php` در `.gitignore` | ✅ | تأیید شد |
| ۵ | `config.php` در `.gitignore` | ✅ | تأیید شد |
| ۶ | `install/views/` خالی | 🟠 | تصمیم: پر کردن یا حذف |
| ۷ | `install/assets/` خالی | 🟠 | تصمیم: پر کردن یا حذف |
| ۸ | فایل‌های `.bak-*` در ریشه | 🟠 | انتقال به `_backups_archive` |
| ۹ | فایل `-p` خالی | ✅ | حذف شد |
| ۱۰ | Aether در Termux | 🟠 | رها شد — به VPS موکول |

**⚠️ پسورد DB:** `@PASS757ho@` در تاریخچه مکالمات ثبت شده. حتماً تعویض شود.

---

## 📊 جداول موجود که در TODO نیست

| جدول | معنی | UI مشخص؟ |
|------|------|----------|
| `forms`, `form_submissions` | Form Builder | ❓ |
| `menus`, `menu_items` | Menu Builder | ❓ |
| `media` | Media Library | ❓ |
| `seo_settings` | SEO Management | ❓ |
| `projects` | Project Management | ❓ (۰ ردیف) |
| `translation_queue` | Async Translation Queue | ❓ |
| `activity_log` | Audit Trail | ❓ |
| `views` | Page View Tracking | ❓ |
| `backup_settings` | Backup Config | ✅ |
| `contacts` | CRM مقدماتی | 🟡 (۵ ردیف) |

**نکته:** این جداول موجود هستند، ولی مشخص نیست UI مدیریتی دارند یا نه. نیاز به کاوش دقیق‌تر.

---


## ⏳ فاز ۳-D: Smart Installer + Tier System 🟡 ~۷۵٪

**وضعیت:** منطق نصب کامل، UI در `install/install.php`، ولی views/assets خالی.

**آمار:**
- `install/index.php` (397 خط)
- `install/database.php` (506 خط)
- `install/admin-user.php` (378 خط)
- `install/install.php` (358 خط)
- `install/site-type.php` (396 خط)
- `install/includes/RequirementChecker.php` (560 خط)
- **مجموع:** ~۲,۵۹۵ خط

**کامیت‌ها:** `dbba25e` (step 1), `6b8091d` (step 5)

**Tier System (فقط تعریف در `site-type.php`):**
- `basic`, `standard`, `pro`
- ۱۰ سایت-تایپ تعریف‌شده
- **منطق پیاده‌سازی Tier ناقص**

**ناقص‌ها:**
- 🟠 `install/views/` خالی
- 🟠 `install/assets/` خالی
- 🟠 README.md ندارد
- 🟠 تست end-to-end روی سرور واقعی انجام نشده

---

## 🎨 فاز ۴: قالب‌ساز + ۶۰ تمپلیت 🚨 ۰٪

**وضعیت واقعی:** جدول `templates` = **۰ ردیف** — هنوز شروع نشده!

**موجود:**
- `node_modules/grapesjs-plugin-forms` (نصب شده)
- جدول `templates` (خالی)
- `admin/templates.php` و `admin/editor.php` (وجود دارند ولی نیاز به کاوش)

**باید انجام شود:**
- بررسی و رفع باگ‌های GrapesJS
- افزودن کامپوننت‌های جدید
- بلوک‌های آماده (Hero, CTA, Pricing)
- ۶۰ تمپلیت آماده:
  - ۱۰ خبری
  - ۱۰ سازمانی
  - ۱۰ فروشگاهی
  - ۱۰ وبلاگ
  - ۱۰ خدماتی
  - ۱۰ خلاقانه

---

## 📰 فاز ۵: ماژول وبلاگ 🟡 ~۵۰٪

**موجود:**
- جدول `content_items` = **۸ ردیف**
- جدول `categories` = **۶ ردیف**
- جدول `comments` = **۳ ردیف** (ماژول مستقل)
- `modules/content/` (۳ فایل: admin, install, manifest)
- `post.php`, `index.php`, `category.php`, `search.php` (فرانت‌اند)

**ناقص‌ها:**
- تگ‌ها (جدول `tags`, `content_tags` هست ولی UI نامعلوم)
- Featured image + Gallery
- SEO پیشرفته (Meta, OG, Schema)
- RSS/Atom feed
- زمان‌بندی انتشار
- Multi-author
- آرشیو
- Post revisions

---

## 🤝 فاز ۶: ماژول CRM 🟡 ~۱۵٪

**موجود:**
- جدول `contacts` = **۵ ردیف**
- `modules/generated/contacts/` (۴ فایل: admin, form, list, view)

**باید انجام شود:**
- مدیریت مشتریان
- تاریخچه تعاملات
- تیکتینگ پشتیبانی
- تحلیل رفتار
- اتوماسیون
- قیف فروش
- مدیریت سرنخ‌ها
- ادغام با فروشگاه

**جداول موردنیاز:** `crm_contacts`, `crm_interactions`, `crm_tickets`, `crm_deals`, `crm_leads`, `crm_pipeline_stages`

---

## 📊 فاز ۷: ماژول Excel 🟡 ~۳۰٪

**موجود:**
- `modules/table_builder/` (۱۳ فایل: admin, ajax, export, create, row_save, row_edit, includes, to_module, data)
- جدول `projects` = **۰ ردیف**

**باید انجام شود:**
- Luckysheet (کتابخانه اصلی)
- ویرایشگر صفحه‌گسترده
- فرمول‌ها و توابع
- نمودارها
- Pivot Tables
- Conditional Formatting
- Data Validation
- صادرات/واردات XLSX/CSV
- ۳۰+ تمپلیت
- Collaboration

**جداول موردنیاز:** `spreadsheets`, `spreadsheet_versions`, `spreadsheet_shares`, `spreadsheet_templates`

---

## 🎯 فاز ۸: ماژول PowerPoint ⏳ ۰٪

**کتابخانه:** Reveal.js + Fabric.js

**باید انجام شود:**
- ویرایشگر اسلاید
- ۱۰۰+ قالب
- انیمیشن و ترنزیشن
- ضبط صدا
- ارائه زنده
- Presenter View
- صادرات/واردات PPTX

**جداول:** `presentations`, `presentation_versions`, `presentation_slides`, `presentation_shares`, `presentation_templates`

---

## 🔐 فاز ۹: سیستم لایسنس ⏳ ۰٪

**باید انجام شود:**
- نمایش نام مالک: Hooman Oliaei
- رمزنگاری کلید
- اعتبارسنجی آنلاین
- محدودیت دامنه
- تاریخ انقضا
- صفحه مدیریت
- ۳ سطح لایسنس: **Standard (۱۲ ماه)**، **Extended (۲۴ ماه)**، **Enterprise**

**جداول:** `licenses`, `license_keys`, `license_activations`, `license_logs`

**توجه:** باید با Tier System در `install/site-type.php` هم‌راستا شود.

---

## ⚙️ فاز ۱۰: اینستالر هوشمند (تلفیق با ۳-D)

**باید انجام شود:**
- بررسی پیش‌نیازها (✅ RequirementChecker موجود)
- فرم اطلاعات DB (✅)
- تست اتصال (✅)
- ساخت جداول (✅)
- ساخت ادمین اولیه (✅)
- ساخت `config.php` (✅)
- انتخاب زبان
- انتخاب قالب
- حذف خودکار `install/`
- پشتیبانی هاست/VPS/کلود
- پشتیبانی Linux/Windows/macOS
- **Tier System (ناقص)**

---

## 📚 فاز ۱۱: مستندات کامل 🟡 ~۴۰٪

**موجود (از Sep 27):**
- `docs/API.md` (4.6KB)
- `docs/ARCHITECTURE.md` (6.8KB)
- `docs/DATABASE.md` (4.9KB)
- `docs/I18N.md` (5.1KB)
- `docs/MODULES.md` (5.9KB)
- `docs/USER_GUIDE.md` (6.5KB)
- `CONTRIBUTING.md`
- `README.md` (قدیمی — آمار واقعی نمی‌دهد)
- `NOTICE`

**باید انجام شود:**
- آپدیت README با آمار واقعی (PHP ۸۵۰+، جدول ۸۱، ماژول ۱۱)
- راهنمای نصب گام‌به‌گام
- FAQ
- ویدیوهای آموزشی
- PDF راهنما

---

## 🧪 فاز ۱۲: تست و دیباگ ⏳ ۰٪

**باید انجام شود:**
- Unit tests (PHPUnit)
- Integration tests
- Security tests
- Performance tests
- Cross-browser testing
- Mobile responsive testing
- Load testing
- رفع باگ‌ها

**وضعیت فعلی:** هیچ فایل test/phpunit موجود نیست.

---


## 🔄 فازهای تکمیلی (اولویت پایین‌تر)

### بازنگری ترجمه‌ها
- صفحه `admin/content_review.php`
- فیلتر زبان/provider/کیفیت
- دکمه تولید مجدد
- ویرایش inline
- خروجی CSV/Excel

### ترجمه خودکار در انتشار
- Hook بعد از publish
- ترجمه async با Queue
- جدول `translation_queue` (موجود)
- اطلاع‌رسانی Telegram

### جدول‌ساز (Table Builder)
- تعریف جدول بدون SQL
- CRUD خودکار
- Export/Import
- Drag & Drop
- **موجود:** `modules/table_builder/` ۱۳ فایل

### آنتی‌ویروس و اسکن سایت
- اسکن دیتابیس
- اسکن فایل‌های آپلودی
- بررسی SSL/TLS
- گزارش به Telegram

### دستیار هوش مصنوعی (Ollama)
- نصب Ollama
- دانلود مدل
- چت با AI
- طراحی ماژول با AI
- اسکن سایت با AI

**جداول:** `ai_conversations`, `ai_messages`

---

## 🎯 فازهای پیشنهادی آینده (CMS کامل)

### اولویت بالا (ارزش تجاری)
| # | قابلیت | وضعیت |
|---|--------|--------|
| ۱ | **Testimonials** (نظرات مشتریان) | ⏳ |
| ۲ | **Team** (تیم ما) | ⏳ |
| ۳ | **Services** (خدمات) | ⏳ |
| ۴ | **Booking** (نوبت‌دهی/رزرو) | ⏳ |
| ۵ | **Advanced Search** | ⏳ |
| ۶ | **Dynamic Sitemap** | ⏳ |
| ۷ | **Multi-Currency** (فروشگاه) | ⏳ |
| ۸ | **Wishlist** (فروشگاه) | ⏳ |

### اولویت متوسط
| # | قابلیت | وضعیت |
|---|--------|--------|
| ۹ | Social Share | ⏳ |
| ۱۰ | Social Login | ⏳ |
| ۱۱ | Google Analytics/GTM | ⏳ |
| ۱۲ | Redirects Manager | ⏳ |
| ۱۳ | hreflang + Schema.org | ⏳ |
| ۱۴ | SMS Gateway (کاوه‌نگار/ملی‌پیامک) | ⏳ |
| ۱۵ | Webhooks Outbound | ⏳ |
| ۱۶ | API Token Management | ⏳ |
| ۱۷ | Error Tracking | ⏳ |
| ۱۸ | Performance Monitoring | ⏳ |

### اولویت پایین (پس از فروش)
| # | قابلیت | وضعیت |
|---|--------|--------|
| ۱۹ | PWA + اپ موبایل | ⏳ |
| ۲۰ | GDPR + Cookie Consent | ⏳ |
| ۲۱ | A/B Testing | ⏳ |
| ۲۲ | Email Automation + Segmentation | ⏳ |
| ۲۳ | Affiliates / Referral | ⏳ |
| ۲۴ | Forums + User Profiles | ⏳ |
| ۲۵ | AI Image/Video/TTS/SEO | ⏳ |
| ۲۶ | Accessibility WCAG | ⏳ |
| ۲۷ | Docker + CI/CD | ⏳ |
| ۲۸ | Updates System (core + modules) | ⏳ |
| ۲۹ | Migration Runner | ⏳ |

---

## 📦 ماژول‌های تخصصی (تمایز PartoCMS)

| # | ماژول | زمان تقریبی |
|---|-------|-------------|
| ۱ | **Real Estate** (مشاور املاک) — ۱۲ کامپوننت | ۲ هفته |
| ۲ | **Cars** (نمایشگاه خودرو) — ۱۲ کامپوننت | ۲ هفته |
| ۳ | **LMS** (آموزشگاه) — ۱۱ کامپوننت | ۳ هفته |
| ۴ | **Medical** (پزشکی) — ۱۳ کامپوننت | ۳ هفته |
| ۵ | **Restaurant** (رستوران) — ۹ کامپوننت | ۱ هفته |
| ۶ | **Corporate + Agency** — ۱۵ کامپوننت | ۱ هفته |
| ۷ | **Personal + Furniture** — ۱۸ کامپوننت | ۱ هفته |

**منبع:** `COMPONENTS.md` (۱۱۵ کامپوننت غایب در ۱۲ دسته)

---


## 📊 آمار کلی پروژه (آپدیت‌شده 2026-10-18)

- **فایل‌های PHP:** ۸۵۰+
- **جدول‌های دیتابیس:** ۸۱
- **زبان‌های فعال:** ۲۰
- **Providerهای ترجمه:** ۷
- **Providerهای AI:** ۵ (Ollama, OpenRouter, Groq, Gemini, OpenAI)
- **ماژول‌ها:** ۱۴ (ads, ai_assistant, ai_providers, comments, content, faq, generated, newsletter, reviews, services, shop, table_builder, team, testimonials)
- **خطوط فروشگاه:** ۸,۱۱۸
- **خطوط تبلیغات:** ۷,۲۳۶
- **خطوط Newsletter:** ~۲,۵۰۰
- **خطوط AI:** ۳,۰۰۰+
- **خطوط Installer:** ~۲,۵۹۵
- **حجم پروژه:** ~۸۵ MB
- **Repo:** عمومی (Public)

---

## 🎯 اولویت‌های پیشنهادی

### 🔴 فوری (بدهی فنی — قبل از هر توسعه)
1. تعویض پسورد DB (`@PASS757ho@` لو رفته)
2. `DROP TABLE TestDb, utyfi`
3. تصمیم درباره `install/views/` و `install/assets/`
4. پاک‌سازی `.bak-*` ریشه

### 🟠 کوتاه‌مدت (۱ هفته)
1. تکمیل فاز ۳-D (Installer Tier + views)
2. Quick Wins: Testimonials + Team + Services (۴ روز)
3. تست AdBlock Plus + uBlock (نیمه‌کاره)

### 🟡 میان‌مدت (۱ ماه)
1. فاز ۴: قالب‌ساز (بزرگ‌ترین ارزش تجاری)
2. فاز ۵: وبلاگ (تکمیل)
3. آپدیت README با آمار واقعی

### 🟢 بلندمدت (۲ ماه+)
1. فاز ۶: CRM
2. فاز ۷: Excel
3. فاز ۸: PowerPoint
4. فاز ۹: لایسنس
5. فاز ۱۱-۱۲: مستندات + تست

---

## 📌 نکات مهم برای توسعه‌دهنده بعدی

1. **قبل از هر تغییر:** `cp file.php file.php.bak-$(date +%s)`
2. **جداول جدید:** `SHOW TABLES` چک کن (پیشوند `shop_` برای فروشگاه)
3. **کش i18n:** اگر ترجمه‌ها به‌روز نشدند → `rm logs/i18n_cache/*.json`
4. **RTL/LTR:** همیشه از `__html_attrs()` و `isRtl()` استفاده کن
5. **تست امنیتی:** بعد از تغییرات مهم → `admin/security_audit.php`
6. **DeepL quota:** از `admin/translator_settings.php` چک کن
7. **ترجمه محتوا:** `admin/content_translate.php?id=X`
8. **فاکتور PDF:** از mPDF استفاده می‌شود (نه dompdf)
9. **git:** commit منظم با پیام‌های واضح
10. **فونت:** فونت‌های اصلی در `assets/fonts/Vazirmatn-*.ttf`
11. **AI Gateway:** Chain در DB (`ai_provider_chain`) — قابل تغییر از پنل
12. **AdBlock:** Edge Tracking Prevention باید در Basic باشد
13. **Aether در Termux:** رها شد — به VPS موکول
14. **`.gitignore`:** فایل‌های `aether*.toml` (به‌جز `.example` و `lastconn`) و `aether.sh` نگه‌داری می‌شوند

---

## 📞 برای ادامه توسعه

1. اول این فایل رو کامل بخون
2. **فاز ۲ (فروشگاه)** ۱۰۰٪ کامله
3. **فاز ۳ (تبلیغات + AI + Newsletter + Reviews + FAQ)** ۱۰۰٪ کامله
4. **فاز بعدی پیشنهادی:** تکمیل فاز ۳-D (Installer Tier) → فاز ۴ (قالب‌ساز)
5. ترتیب پیشنهادی: **۳-D → ۴ → ۵ → ۶ → ۷ → ۸ → ۹ → ۱۰ → ۱۱ → ۱۲**

**فایل‌های کلیدی:**
- `config.php`
- `includes/ModuleManager.php`
- `includes/Security.php`
- `includes/AiAssistant.php`
- `includes/AI/AIGateway.php`
- `admin/includes/module_registry.php`
- `admin/includes/module_registry_sidebar.php`
- `admin/includes/multi_translator.php`
- `includes/content_i18n.php`
- `modules/shop/includes/ShopManager.php`
- `modules/ads/includes/AdManager.php`
- `modules/ads/includes/AdRenderer.php`
- `modules/ads/includes/AdAiAssistant.php`
- `modules/newsletter/` (DDD)
- `modules/faq/` (DDD)
- `modules/reviews/`
- `modules/comments/`

---

**پایان TODO.md**

---

**طراحی و برنامه‌نویسی و مالک:** Hooman Oliaei (هومان اولیایی)
**تاریخ:** 2026-10-18
**مخزن:** https://github.com/hoazadir/PartoCMS
**لایسنس:** Proprietary — تمامی حقوق محفوظ است

---

## 🆕 فاز ۳-G: Testimonials Module ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-19
**کامیت‌ها:** `1558672`, `d99f06b`, `c881ea9`, `46a5c93`

**آمار:** ۲۱ فایل، ۴,۲۸۲ خط، ۱ جدول DB (testimonials با ۱۶ ستون و ۶ index)

**معماری:** DDD + SOLID + PSR-4 + autoloader

| فاز | عنوان | کامیت |
|:---:|-------|-------|
| ۱ | Backend (Domain + Infrastructure) | 1558672 |
| ۲ | Admin UI (۵ صفحه) | d99f06b |
| ۳ | Frontend (Widget + Shortcode + AJAX) | c881ea9 |
| ۴ | Registry + Manifest + README | 46a5c93 |

**امکانات:**
- CRUD کامل از پنل ادمین
- فرم AJAX از فرانت (بدون لاگین)
- امتیاز ستاره‌ای (۱-۵) + آواتار + Featured
- صف تایید (pending/published/rejected)
- i18n (fa-IR, en-US) + RTL/LTR
- ضد اسپم ۳ لایه
- Bulk operations + فیلترها
- شورت‌کد [testimonials] + ۷ attribute
- ویجت grid/slider + CSS responsive

**دسترسی:**
- Admin: /modules/testimonials/admin/index.php
- AJAX: /modules/testimonials/ajax/submit.php
- Sidebar: Marketing > Testimonials

**تست:** ۴۰+ assertion، همه php -l سبز، AJAX موفق

---

---

## 🆕 فاز ۳-H: Team Module ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-19
**کامیت‌ها:** `ea9fa50`, `97d2e54`, `287b913`, `e627a53`

**آمار:** ۱۸ فایل، ۲,۹۱۶ خط، ۱ جدول DB (team_members با ۱۷ ستون و ۴ index)

**معماری:** DDD + SOLID + PSR-4 + autoloader

| فاز | عنوان | کامیت |
|:---:|-------|-------|
| ۱ | Backend (Domain + Infrastructure) | ea9fa50 |
| ۲ | Admin UI (۵ صفحه) | 97d2e54 |
| ۳ | Frontend (Widget + Shortcode + CSS) | 287b913 |
| ۴ | Registry + Manifest + README | e627a53 |

**امکانات:**
- CRUD کامل از پنل ادمین
- عکس (URL) با fallback حرف اول
- بیوگرافی + سمت + دپارتمان
- شبکه‌های اجتماعی (LinkedIn, Twitter, Instagram, Email)
- وضعیت (published/hidden) + Featured + sort_order
- Bulk operations + فیلترها (status, language, department, search)
- شورت‌کد [team] + ۸ attribute
- ویجت grid/slider + CSS responsive
- RTL/LTR + i18n

**دسترسی:**
- Admin: /modules/team/admin/index.php
- Sidebar: Marketing > تیم ما

**تست:** ۱۳+ assertion، همه php -l سبز، Widget/Shortcode PASS

---

## 🆕 فاز ۳-I: Services Module ✅ ۱۰۰٪

**تاریخ تکمیل:** 2026-10-19
**کامیت‌ها:** `fb04ecd`, `b13c802`, `cc630e6`, `7845364`

**آمار:** ۱۸ فایل، ~۲,۹۶۵ خط، ۱ جدول DB (services با ۱۶ ستون و ۶ index)

**معماری:** DDD + SOLID + PSR-4 + autoloader

| فاز | عنوان | کامیت |
|:---:|-------|-------|
| ۱ | Backend (Domain + Infrastructure) | fb04ecd |
| ۲ | Admin UI (۵ صفحه) | b13c802 |
| ۳ | Frontend (Widget + Shortcode + CSS) | cc630e6 |
| ۴ | Registry + Manifest + README | 7845364 |

**امکانات:**
- CRUD کامل از پنل ادمین
- آیکون (emoji) یا تصویر
- قیمت + واحد قیمت
- ویژگی‌ها (JSON array)
- دسته‌بندی (فیلتر)
- وضعیت (published/hidden) + Featured + sort_order
- Bulk operations + فیلترها (status, category, search)
- شورت‌کد [services] + ۸ attribute
- ویجت grid/slider + CSS responsive
- RTL/LTR + i18n

**دسترسی:**
- Admin: /modules/services/admin/index.php
- Sidebar: Marketing > خدمات

**تست:** ۱۰+ assertion، همه php -l سبز، Widget/Shortcode PASS
