# 📋 PartoCMS Development Roadmap

> آخرین بروزرسانی: 2026-09-25
> این فایل نمای کامل وضعیت پروژه است و به عنوان context برای ادامه توسعه استفاده می‌شود.
> مالک و توسعه‌دهنده اصلی: **Hooman Oliaei (هومان اولیایی)**
> مخزن: https://github.com/hoazadir/PartoCMS

---

## 🎯 خلاصه پروژه

**PartoCMS** یک CMS/Platform اختصاصی است که با PHP 8.5 و MariaDB در محیط Termux (Android) توسعه می‌یابد و هدف آن تبدیل شدن به یک **پلتفرم Enterprise** با قابلیت‌های Office و AI است.

**محیط فنی:**
- PHP: 8.5
- Database: MariaDB 12.3
- DB Name: grapesjs_cms
- Project Root: ~/PartoCMS/
- Git Repo: https://github.com/hoazadir/PartoCMS

---

## ✅ فازهای تکمیل‌شده

### 🛡️ فاز ۱: سیستم امنیتی ۱۰ لایه ✅

| # | قابلیت | فایل |
|---|--------|------|
| ۱ | PHP Hardening | admin/includes/security_config.php |
| ۲ | File Integrity Monitoring | admin/includes/fim.php |
| ۳ | Malware Pattern Scanner | admin/includes/fim.php |
| ۴ | Telegram Alerts | admin/includes/telegram_notifier.php |
| ۵ | Telegram Setup Wizard | admin/telegram_setup.php |
| ۶ | VirusTotal Scanner | admin/includes/virustotal_scanner.php |
| ۷ | VirusTotal Setup | admin/virustotal_setup.php |
| ۸ | Rate Limiting | admin/includes/rate_limiter.php |
| ۹ | Two-Factor Auth (TOTP) | admin/includes/totp.php |
| ۱۰ | 2FA Setup | admin/2fa_setup.php |
| ۱۱ | Security Audit | admin/security_audit.php |
| ۱۲ | Security Dashboard | admin/security_dashboard.php |
| ۱۳ | Security Graphs | admin/security_graphs.php |
| ۱۴ | Backup Manager | admin/backup_manager.php |
| ۱۵ | CSRF Protection | همه فرم‌ها |
| ۱۶ | Logout اصلاح‌شده | admin/logout.php |

**جداول امنیتی:** file_hashes, security_logs, login_attempts, login_blocks, user_2fa, user_2fa_backup

**Cron Jobs:** admin/cron_security_check.php (هر ۶ ساعت), admin/cron_weekly_report.php (هر شنبه ۹ صبح)

---

### 📊 فاز ۲: مدیریت و گزارش‌ها ✅

- Backup خودکار با gzip
- Clean Old Backups (۳۰ روز)
- گزارش هفتگی HTML ایمیل
- نمودارهای Chart.js (line, pie, bar)
- Dashboard گرافیکی

---

### 🌍 فاز ۳-A1: i18n پایه ✅

**۲۰ زبان فعال:** fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN, ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL

**جداول:** languages, translation_keys, translations, user_preferences

**فایل‌ها:** admin/includes/i18n.php, admin/includes/language_switcher.php, admin/languages.php, admin/translations.php

**توابع config.php:** getI18n(), __t($key, $params, $default), __html_attrs(), __dir()

**ویژگی‌ها:**
- Language persistence: DB + Cookie + Session + Browser
- کش فایل: logs/i18n_cache/
- RTL/LTR خودکار در ۴۷ فایل
- Bootstrap RTL/LTR خودکار

---

### 📝 فاز ۳-A2: ترجمه فرانت‌اند ✅

- ۳۴ کلید ترجمه فرانت‌اند
- ۶۸۰ ترجمه (۳۴ × ۲۰ زبان)
- اعمال در ۱۱ فایل فرانت‌اند

---

### 🔄 فاز ۳-A3: ترجمه محتوا (MultiTranslator v6.1) ✅

**۷ Provider:** DeepL, Microsoft, Yandex, Google, Lingva, Libre, MyMemory

**ویژگی‌ها:**
- ترجمه موازی از همه provider ها
- Quality Scoring (0-100)
- تشخیص garbage
- tag_handling=html برای DeepL
- Cache با اعتبارسنجی مجدد
- Fallback خودکار

**فایل‌ها:** admin/includes/multi_translator.php, admin/content_translate.php, admin/translator_settings.php

**جداول:** content_translations, translation_versions, translation_cache, provider_stats, category_translations

---

### 🌐 فاز ۴: نمایش ترجمه در فرانت‌اند ✅

**includes/content_i18n.php v3 (Helper):**
- getCurrentLanguageInfo(), getCurrentLanguageId(), isDefaultLanguage()
- applyTranslationToPost(), applyTranslationsToPosts()
- applyTranslationToCategory(), applyTranslationsToCategories()
- translateAndCacheCategory()

**فایل‌های فرانت‌اند:** post.php, index.php, category.php, search.php, login.php, register.php

---

## ⏳ فازهای باقی‌مانده

### 📝 فاز ۳-A4: بازنگری ترجمه‌ها (اولویت بالا)
- [ ] صفحه admin/content_review.php
- [ ] فیلتر بر اساس زبان/provider/کیفیت
- [ ] دکمه «تولید مجدد»
- [ ] ویرایش دستی inline
- [ ] خروجی CSV/Excel

### 🔄 فاز ۳-A5: ترجمه خودکار در انتشار
- [ ] Hook بعد از publish در modules/content/admin.php
- [ ] ترجمه async با Queue
- [ ] اطلاع‌رسانی Telegram

### 📊 فاز ۵: جدول‌ساز (Table Builder)
- [ ] تعریف جدول بدون SQL
- [ ] تعریف ستون‌ها و روابط
- [ ] CRUD خودکار
- [ ] Export/Import (SQL/JSON/CSV)
- [ ] رابط Drag & Drop

### ⚙️ فاز ۶: اینستالر پیشرفته

**مراحل:**
- [ ] بررسی پیش‌نیازها (PHP، extensions، permissions)
- [ ] فرم دریافت اطلاعات DB
- [ ] تست اتصال DB
- [ ] ساخت جداول از فایل SQL
- [ ] ساخت ادمین اولیه
- [ ] ساخت config.php خودکار
- [ ] انتخاب زبان (از ۲۰ زبان)
- [ ] انتخاب قالب (از ۶۰ تمپلیت)
- [ ] حذف خودکار پوشه install/ بعد از نصب
- [ ] پشتیبانی از هاست/VPS/کلود/سرور شخصی
- [ ] پشتیبانی از Linux/Windows Server/macOS

### 🎨 فاز ۷: تکمیل قالب‌ساز + ۶۰ تمپلیت
- [ ] بررسی admin/templates.php و admin/editor.php
- [ ] رفع باگ‌های GrapesJS
- [ ] افزودن کامپوننت‌های جدید
- [ ] بلوک‌های آماده (Hero, CTA, Pricing, ...)
- [ ] **۶۰ تمپلیت آماده:**
  - ۱۰ خبری (خبری، سیاسی، ورزشی، اقتصادی، ...)
  - ۱۰ سازمانی (شرکتی، دولتی، آموزشی، ...)
  - ۱۰ فروشگاهی (پوشاک، دیجیتال، لوازم خانگی، ...)
  - ۱۰ وبلاگ (شخصی، تخصصی، سفر، غذا، ...)
  - ۱۰ خدماتی (پزشکی، وکالت، مشاوره، ...)
  - ۱۰ خلاقانه (هنری، تفریحی، مذهبی، ...)

### 📢 فاز ۸: تبلیغات + بنر

**فایل:** admin/ads.php

**ویژگی‌ها:**
- [ ] آپلود بنر و اسلایدشو
- [ ] تعریف موقعیت (header, sidebar, footer, in-content)
- [ ] بازه زمانی نمایش
- [ ] آمار نمایش (impressions) و کلیک (clicks)
- [ ] اهداف تبلیغاتی
- [ ] Prioritization
- [ ] مدیریت کمپین‌ها

**جداول:** ads, ad_positions, ad_campaigns, ad_clicks, ad_impressions

### 🛒 فاز ۹: فروشگاه‌ساز (E-Commerce)

**۹-۱ محصولات:** products, product_categories, product_images, product_variants, product_attributes
**۹-۲ سبد خرید:** cart, cart_items
**۹-۳ سفارشات:** orders, order_items, order_status_history
**۹-۴ پرداخت ایرانی:** زرین‌پال، آیدی‌پی، پی‌پینگ، نکست‌پی، ملت، سامان، پارسیان
**۹-۵ پرداخت بین‌المللی:** PayPal, Stripe, Paystack
**۹-۶ پرداخت ارز دیجیتال:** NowPayments (USDT, BTC, ETH), CoinGate, Binance Pay
**۹-۷ حمل و نقل:** shipping_methods, tracking
**۹-۸ مالیات:** tax_rates
**۹-۹ کوپن تخفیف:** coupons, discounts
**۹-۱۰ گزارش فروش:** روزانه/هفتگی/ماهانه + نمودار
**۹-۱۱ فاکتور PDF:** با dompdf موجود

**جداول:** ۱۵+ جدول جدید

### 🤖 فاز ۱۰: دستیار هوش مصنوعی (Ollama)

- [ ] نصب Ollama در Termux
- [ ] دانلود مدل (llama3.2:3b یا qwen2.5:3b)
- [ ] چت با AI (شبیه ChatGPT)
- [ ] طراحی ماژول با AI
- [ ] اسکن سایت با AI
- [ ] چیدمان سایت با AI

**جداول:** ai_conversations, ai_messages

### 🦠 فاز ۱۱: تکمیل آنتی‌ویروس و اسکن سایت
- [ ] اسکن دیتابیس برای کدهای مخرب
- [ ] اسکن فایل‌های آپلودی
- [ ] بررسی SSL/TLS
- [ ] گزارش به Telegram

### 📊 فاز ۱۲: ماژول Excel حرفه‌ای (NEW)
- [ ] انتخاب کتابخانه (Luckysheet)
- [ ] ویرایشگر صفحه‌گسترده
- [ ] فرمول‌ها و توابع کامل
- [ ] نمودارها و گراف‌ها
- [ ] Pivot Tables
- [ ] Conditional Formatting
- [ ] Data Validation
- [ ] صادرات/واردات XLSX/CSV
- [ ] ۳۰+ تمپلیت آماده

**جداول:** spreadsheets, spreadsheet_versions, spreadsheet_shares, spreadsheet_templates

### 🎯 فاز ۱۳: ماژول PowerPoint حرفه‌ای (NEW)
- [ ] انتخاب کتابخانه (Reveal.js + Fabric.js)
- [ ] ویرایشگر اسلاید
- [ ] ۱۰۰+ قالب حرفه‌ای
- [ ] انیمیشن و ترنزیشن
- [ ] ضبط صدا روی اسلاید
- [ ] ارائه زنده
- [ ] Presenter View
- [ ] صادرات/واردات PPTX

**جداول:** presentations, presentation_versions, presentation_slides, presentation_shares, presentation_templates

### 🤝 فاز ۱۴: ماژول CRM
- [ ] مدیریت مشتریان
- [ ] تاریخچه تعاملات
- [ ] تیکتینگ پشتیبانی
- [ ] تحلیل رفتار مشتری
- [ ] اتوماسیون (ایمیل، یادآوری)
- [ ] قیف فروش

### 🔐 فاز ۱۵: سیستم لایسنس
- [ ] نمایش نام مالک: **Hooman Oliaei (هومان اولیایی)**
- [ ] رمزنگاری کلید لایسنس
- [ ] اعتبارسنجی آنلاین
- [ ] محدودیت دامنه
- [ ] تاریخ انقضا
- [ ] صفحه مدیریت لایسنس

### 📚 فاز ۱۶: مستندات کامل
- [ ] راهنمای کاربری (HTML + PDF)
- [ ] راهنمای فنی
- [ ] مستندات API
- [ ] راهنمای معماری
- [ ] FAQ
- [ ] راهنمای نصب گام‌به‌گام

### 🏁 فاز ۱۷: فاز ۰ (تثبیت پایه) - فوری

**مشکلات بحرانی شناسایی‌شده در audit:**
- [ ] حذف `.git` از web root یا مسدودسازی با .htaccess
- [ ] ساخت `.htaccess` در ریشه
- [ ] ساخت `.htaccess` در admin/
- [ ] حذف `curl_close()` از includes/AiAssistant.php
- [ ] رفع خطای Cron Whisper (cron/check_whisper_queue.php)
- [ ] حذف فایل `mkcert` و `.bak` از ماژول
- [ ] ساخت پوشه templates/
- [ ] ساخت robots.txt و sitemap.xml
- [ ] پیاده‌سازی Log Rotation
- [ ] ساخت manifest.json برای ماژول‌های بدون manifest

---

## 🛠️ اطلاعات فنی مهم

### توابع config.php
- getI18n(), __t(), __html_attrs(), __dir()
- isLoggedIn(), isAdmin()
- getSetting($key, $default)
- getDB()

### Helperهای includes/content_i18n.php
- getCurrentLanguageInfo(), getCurrentLanguageId(), isDefaultLanguage()
- applyTranslationToPost(), applyTranslationsToPosts()
- applyTranslationToCategory(), applyTranslationsToCategories()
- translateAndCacheCategory()

### الگوهای مهم کد

**ترجمه:** `<?= __t('key', [], 'پیش‌فرض') ?>`
**HTML tag:** `<html <?= __html_attrs() ?>>`
**Bootstrap RTL/LTR:** `bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css`
**اعمال ترجمه:** `$post = applyTranslationToPost($post);`

---

## 📌 نکات مهم برای توسعه‌دهنده بعدی

1. **قبل از هر تغییر:** `cp file.php file.php.bak-$(date +%s)`
2. **جداول جدید:** SHOW TABLES چک کن
3. **کش i18n:** اگه ترجمه‌ها به‌روز نشدند → `rm logs/i18n_cache/*.json`
4. **RTL/LTR:** همیشه از `__html_attrs()` و `isRtl()` استفاده کن
5. **تست امنیتی:** بعد از تغییرات مهم → admin/security_audit.php
6. **DeepL quota:** از admin/translator_settings.php چک کن
7. **ترجمه محتوا:** admin/content_translate.php?id=X
8. **گیت:** commit منظم با پیام‌های واضح

---

## 📊 آمار کلی پروژه

- **فایل‌های PHP:** ۷۱۰
- **جدول‌های دیتابیس:** ۴۸
- **زبان‌های فعال:** ۲۰
- **Provider های ترجمه:** ۷
- **حجم پروژه:** 79 MB
- **ماژول‌ها:** ۴ (ai_assistant, content, generated, table_builder)

---

## 🎯 اولویت‌های پیشنهادی (به‌روزرسانی‌شده)

**فاز ۰ - فوری (۱-۲ روز):**
1. تثبیت پایه (رفع مشکلات بحرانی audit)

**کوتاه‌مدت (۱ هفته):**
2. فاز ۳-A4: بازنگری ترجمه‌ها
3. فاز ۳-A5: ترجمه خودکار در انتشار
4. فاز ۵: جدول‌ساز
5. فاز ۶: اینستالر

**میان‌مدت (۱ ماه):**
6. فاز ۷: قالب‌ساز + ۶۰ تمپلیت
7. فاز ۸: تبلیغات
8. فاز ۹: فروشگاه
9. فاز ۱۴: CRM

**بلندمدت (۲ ماه+):**
10. فاز ۱۰: هوش مصنوعی
11. فاز ۱۲: Excel حرفه‌ای
12. فاز ۱۳: PowerPoint حرفه‌ای
13. فاز ۱۵: لایسنس
14. فاز ۱۶: مستندات کامل

---

## 📞 برای ادامه توسعه

1. اول این فایل رو کامل بخون
2. از **فاز ۰ (تثبیت پایه)** شروع کن
3. ترتیب: ۰ → ۳-A4 → ۳-A5 → ۵ → ۶ → ۷ → ۸ → ۹ → ۱۴ → ۱۰ → ۱۲ → ۱۳ → ۱۵ → ۱۶

**فایل‌های کلیدی:**
- config.php
- includes/ModuleManager.php
- includes/Security.php
- includes/AiAssistant.php
- admin/includes/multi_translator.php
- includes/content_i18n.php

---

**پایان TODO.md**
