# 📋 PartoCMS Development Roadmap

> آخرین بروزرسانی: 2026-09-28
> طراحی و برنامه‌نویسی و مالک: **Hooman Oliaei (هومان اولیایی)**
> مخزن: https://github.com/hoazadir/PartoCMS

---

## 🎯 خلاصه پروژه

**PartoCMS** یک CMS/Platform اختصاصی است که با PHP 8.5 و MariaDB در محیط Termux توسعه می‌یابد.

**محیط فنی:**
- PHP: 8.5
- Database: MariaDB 12.3
- DB Name: grapesjs_cms
- Project Root: ~/PartoCMS/

---

## 📊 پیشرفت کلی پروژه

| فاز | عنوان | وضعیت |
|------|--------|--------|
| فاز ۰ | تثبیت پایه | ✅ ۱۰۰٪ |
| فاز ۱ | مستندسازی + امنیت ۱۰ لایه | ✅ ۱۰۰٪ |
| فاز ۲ | ماژول فروشگاه | ✅ ۱۰۰٪ |
| فاز ۳ | ماژول تبلیغات + اسلایدشو | ✅ ۱۰۰٪ |
| فاز ۳-A | AI Copywriting برای تبلیغات | ✅ ۱۰۰٪ |
| فاز ۳-B | AI Gateway + Cloud Providers | ✅ ۱۰۰٪ |
| فاز ۳-C | Anti-AdBlock | 🔄 در حال |
| فاز ۴ | قالب‌ساز + ۶۰ تمپلیت | ⏳ |
| فاز ۵ | ماژول وبلاگ | ⏳ |
| فاز ۶ | ماژول CRM | ⏳ |
| فاز ۷ | ماژول Excel | ⏳ |
| فاز ۸ | ماژول PowerPoint | ⏳ |
| فاز ۹ | سیستم لایسنس | ⏳ |
| فاز ۱۰ | اینستالر | ⏳ |
| فاز ۱۱ | مستندات کامل | ⏳ |
| فاز ۱۲ | تست و دیباگ | ⏳ |

---

## ✅ فاز ۰: تثبیت پایه ✅

- [x] حذف .git از web root
- [x] ساخت .htaccess در ریشه و admin/
- [x] حذف curl_close() از AiAssistant
- [x] رفع Cron Whisper
- [x] حذف mkcert و .bak
- [x] ساخت templates/
- [x] ساخت robots.txt و sitemap.xml
- [x] Log Rotation
- [x] manifest.json برای ماژول‌ها

---

## 🛡️ فاز ۱: مستندسازی فنی + امنیت ۱۰ لایه ✅

**بخش A: مستندسازی فنی**
- مستندات ساختار پروژه
- راهنمای معماری
- راهنمای توسعه‌دهنده

**بخش B: امنیت ۱۰ لایه**

| # | قابلیت | فایل |
|---|--------|------|
| ۱ | PHP Hardening | security_config.php |
| ۲ | FIM | fim.php |
| ۳ | Malware Scanner | fim.php |
| ۴ | Telegram Alerts | telegram_notifier.php |
| ۵ | Telegram Setup | telegram_setup.php |
| ۶ | VirusTotal | virustotal_scanner.php |
| ۷ | VirusTotal Setup | virustotal_setup.php |
| ۸ | Rate Limiting | rate_limiter.php |
| ۹ | 2FA (TOTP) | totp.php |
| ۱۰ | 2FA Setup | 2fa_setup.php |
| ۱۱ | Security Audit | security_audit.php |
| ۱۲ | Security Dashboard | security_dashboard.php |
| ۱۳ | Security Graphs | security_graphs.php |
| ۱۴ | Backup Manager | backup_manager.php |
| ۱۵ | CSRF | همه فرم‌ها |
| ۱۶ | Logout | logout.php |

**جداول:** file_hashes, security_logs, login_attempts, login_blocks, user_2fa, user_2fa_backup

**Cron:** cron_security_check.php, cron_weekly_report.php


---

## 🛒 فاز ۲: ماژول فروشگاه (E-Commerce) ✅ ۱۰۰٪

**آمار:**
- ۴۳ فایل PHP
- ۸,۱۱۸ خط کد
- ۱۸ جدول دیتابیس

**زیرفازها:**

| # | زیرفاز | فایل‌های کلیدی | خطوط |
|---|---------|----------------|-------|
| ۲.۱ | ساختار پایه فروشگاه | index.php, ShopManager.php | ~500 |
| ۲.۲ | محصولات و دسته‌بندی | product.php, admin/products.php | ~1500 |
| ۲.۳ | سبد خرید + آپلود | cart.php, ajax/ | ~1100 |
| ۲.۴ | سفارشات + Checkout | checkout.php, order-success.php, admin/orders.php, admin/order-view.php | ~1200 |
| ۲.۵ | درگاه‌های ایرانی | zarinpal.php, idpay.php | ~600 |
| ۲.۶ | درگاه‌های بین‌المللی | paypal.php, stripe.php | ~350 |
| ۲.۷ | درگاه‌های ارز دیجیتال | coingate.php, nowpayments.php | ~300 |
| ۲.۸ | تخفیف، کوپن، ارسال | admin/coupons.php, admin/shipping.php | ~1200 |
| ۲.۹ | گزارش فروش | admin/reports.php, ReportManager.php | ~570 |
| ۲.۱۰ | فاکتور PDF | InvoiceGenerator.php, InvoiceHelper.php, order-invoice.php, my-orders.php | ~670 |
| ۲.۱۱ | تأیید نهایی | — | — |

**جدول‌های فروشگاه (۱۸):**

- shop_products, shop_categories, shop_product_images
- shop_product_variants, shop_product_attributes
- shop_carts, shop_cart_items
- shop_orders, shop_order_items, shop_order_status_history
- shop_customers, shop_coupons, shop_coupon_usage
- shop_shipping_methods, shop_tax_rates
- shop_payment_gateways, shop_transactions, shop_settings

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
- دکمه‌های دانلود/پیش‌نمایش فاکتور

---

## 📊 فاز ۲-B: مدیریت و گزارش‌ها ✅

- Backup خودکار با gzip
- Clean Old Backups (۳۰ روز)
- گزارش هفتگی HTML ایمیل
- نمودارهای Chart.js (line, pie, bar)
- Dashboard گرافیکی

---

## 🌍 فاز ۲-C: i18n — ۲۰ زبان + ترجمه محتوا ✅

### i18n پایه

**۲۰ زبان فعال:**
fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN, ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL

**جداول:** languages, translation_keys, translations, user_preferences

**فایل‌ها:**
- admin/includes/i18n.php
- admin/includes/language_switcher.php
- admin/languages.php
- admin/translations.php

**توابع config.php:**
- getI18n()
- __t($key, $params, $default)
- __html_attrs()
- __dir()

**ویژگی‌ها:**
- Language persistence: DB + Cookie + Session + Browser
- کش فایل: logs/i18n_cache/
- RTL/LTR خودکار در ۴۷ فایل
- Bootstrap RTL/LTR خودکار

### ترجمه فرانت‌اند

- ۳۴ کلید ترجمه فرانت‌اند
- ۶۸۰ ترجمه (۳۴ × ۲۰ زبان)
- اعمال در ۱۱ فایل فرانت‌اند

### ترجمه محتوا — MultiTranslator v6.1

**۷ Provider:**
DeepL, Microsoft, Yandex, Google, Lingva, Libre, MyMemory

**ویژگی‌ها:**
- ترجمه موازی از همه providerها
- Quality Scoring (0-100)
- تشخیص garbage
- tag_handling=html برای DeepL
- Cache با اعتبارسنجی مجدد
- Fallback خودکار

**فایل‌ها:**
- admin/includes/multi_translator.php
- admin/content_translate.php
- admin/translator_settings.php

**جداول:**
content_translations, translation_versions, translation_cache, provider_stats, category_translations

### نمایش ترجمه در فرانت‌اند

**includes/content_i18n.php v3 (Helper):**
- getCurrentLanguageInfo()
- getCurrentLanguageId()
- isDefaultLanguage()
- applyTranslationToPost() / applyTranslationsToPosts()
- applyTranslationToCategory() / applyTranslationsToCategories()
- translateAndCacheCategory()

**فایل‌های فرانت‌اند:**
post.php, index.php, category.php, search.php, login.php, register.php

---

## ⏳ فازهای باقی‌مانده

### 📢 فاز ۳: ماژول تبلیغات + اسلایدشو ← فاز بعدی

**فایل‌ها:** admin/ads.php, admin/ad-campaigns.php, admin/ad-positions.php

**ویژگی‌ها:**
- آپلود بنر و اسلایدشو
- تعریف موقعیت (header, sidebar, footer, in-content)
- بازه زمانی نمایش
- آمار نمایش و کلیک
- اهداف تبلیغاتی
- Prioritization
- مدیریت کمپین‌ها
- AdSense support
- گزارش‌های تحلیلی
- Targeting (زبان/دسته/کاربر)
- RTL + i18n

**جداول:** ads, ad_positions, ad_campaigns, ad_clicks, ad_impressions

**کامپوننت‌ها:** اسلایدر، بنر، Popup، In-content، Sticky footer

---

### 🎨 فاز ۴: تکمیل قالب‌ساز + ۶۰ تمپلیت

- بررسی admin/templates.php و admin/editor.php
- رفع باگ‌های GrapesJS
- افزودن کامپوننت‌های جدید
- بلوک‌های آماده (Hero, CTA, Pricing)
- **۶۰ تمپلیت آماده:**
  - ۱۰ خبری
  - ۱۰ سازمانی
  - ۱۰ فروشگاهی
  - ۱۰ وبلاگ
  - ۱۰ خدماتی
  - ۱۰ خلاقانه

---

### 📰 فاز ۵: ماژول وبلاگ

**ویژگی‌ها:**
- سیستم پست پیشرفته
- کامنت‌ها (Threaded)
- Featured image + Gallery
- SEO (Meta, OG, Schema)
- RSS/Atom feed
- Newsletter
- Related posts
- Reading time
- Draft / Scheduled publishing
- Multi-author
- آرشیو
- ترجمه محتوا

**جداول:** posts, post_categories, post_tags, post_comments, post_meta, post_revisions

---

### 🤝 فاز ۶: ماژول CRM

- مدیریت مشتریان
- تاریخچه تعاملات
- تیکتینگ پشتیبانی
- تحلیل رفتار
- اتوماسیون
- قیف فروش
- مدیریت سرنخ‌ها
- ادغام با فروشگاه

**جداول:** crm_contacts, crm_interactions, crm_tickets, crm_deals, crm_leads, crm_pipeline_stages

---

### 📊 فاز ۷: ماژول Excel

**کتابخانه:** Luckysheet

- ویرایشگر صفحه‌گسترده
- فرمول‌ها و توابع
- نمودارها
- Pivot Tables
- Conditional Formatting
- Data Validation
- صادرات/واردات XLSX/CSV
- ۳۰+ تمپلیت
- Collaboration

**جداول:** spreadsheets, spreadsheet_versions, spreadsheet_shares, spreadsheet_templates

---

### 🎯 فاز ۸: ماژول PowerPoint

**کتابخانه:** Reveal.js + Fabric.js

- ویرایشگر اسلاید
- ۱۰۰+ قالب
- انیمیشن و ترنزیشن
- ضبط صدا
- ارائه زنده
- Presenter View
- صادرات/واردات PPTX

**جداول:** presentations, presentation_versions, presentation_slides, presentation_shares, presentation_templates

---

### 🔐 فاز ۹: سیستم لایسنس

- نمایش نام مالک: Hooman Oliaei
- رمزنگاری کلید
- اعتبارسنجی آنلاین
- محدودیت دامنه
- تاریخ انقضا
- صفحه مدیریت

**جداول:** licenses, license_keys, license_activations, license_logs

---

### ⚙️ فاز ۱۰: اینستالر پیشرفته

- بررسی پیش‌نیازها
- فرم اطلاعات DB
- تست اتصال
- ساخت جداول
- ساخت ادمین اولیه
- ساخت config.php
- انتخاب زبان
- انتخاب قالب
- حذف خودکار install/
- پشتیبانی هاست/VPS/کلود
- پشتیبانی Linux/Windows/macOS

---

### 📚 فاز ۱۱: مستندات کامل

- راهنمای کاربری (HTML + PDF)
- راهنمای فنی
- مستندات API
- راهنمای معماری
- FAQ
- راهنمای نصب گام‌به‌گام
- ویدیوهای آموزشی

---

### 🧪 فاز ۱۲: تست و دیباگ

- Unit tests (PHPUnit)
- Integration tests
- Security tests
- Performance tests
- Cross-browser testing
- Mobile responsive testing
- Load testing
- رفع باگ‌ها

---

## 🔄 فازهای تکمیلی (اولویت پایین‌تر)

### بازنگری ترجمه‌ها

- صفحه admin/content_review.php
- فیلتر زبان/provider/کیفیت
- دکمه تولید مجدد
- ویرایش inline
- خروجی CSV/Excel

### ترجمه خودکار در انتشار

- Hook بعد از publish
- ترجمه async با Queue
- اطلاع‌رسانی Telegram

### جدول‌ساز (Table Builder)

- تعریف جدول بدون SQL
- CRUD خودکار
- Export/Import
- Drag & Drop

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

**جداول:** ai_conversations, ai_messages

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

### Helperهای modules/shop/includes/

**ShopManager:**
- formatPrice()
- generateSlug()
- getStats()
- getUrl()

**OrderManager:**
- create()
- getById()
- getItems()
- getUserOrders()
- markAsPaid()
- getStats()

**ReportManager:**
- getOverview()
- getDailySales()
- getTopProducts()
- getOrdersByStatus()
- getPaymentStats()
- getTopCustomers()
- getSalesByCategory()

**InvoiceGenerator:**
- renderHtml()
- download()
- save()
- getLayoutConfig()

**InvoiceHelper:**
- toPersianDigits()
- toArabicDigits()
- toJalali()
- toHijri()
- formatPrice()
- formatDate()
- formatOrderNumber()

---

## 🎨 الگوهای مهم کد

**ترجمه:** `<?= __t('key', [], 'پیش‌فرض') ?>`

**HTML tag:** `<html <?= __html_attrs() ?>>`

**Bootstrap RTL/LTR:** `bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css`

**اعمال ترجمه:** `$post = applyTranslationToPost($post);`

**فاکتور PDF:** از mPDF (نه dompdf)

**فونت فارسی:** assets/fonts/Vazirmatn-Regular.ttf + Vazirmatn-Bold.ttf

---

## 📌 نکات مهم برای توسعه‌دهنده بعدی

1. **قبل از هر تغییر:** `cp file.php file.php.bak-$(date +%s)`
2. **جداول جدید:** SHOW TABLES چک کن (پیشوند `shop_` برای فروشگاه)
3. **کش i18n:** اگه ترجمه‌ها به‌روز نشدند → `rm logs/i18n_cache/*.json`
4. **RTL/LTR:** همیشه از `__html_attrs()` و `isRtl()` استفاده کن
5. **تست امنیتی:** بعد از تغییرات مهم → admin/security_audit.php
6. **DeepL quota:** از admin/translator_settings.php چک کن
7. **ترجمه محتوا:** admin/content_translate.php?id=X
8. **فاکتور PDF:** از mPDF استفاده می‌شود (نه dompdf)
9. **git:** commit منظم با پیام‌های واضح
10. **فونت:** فونت‌های اصلی در `assets/fonts/Vazirmatn-*.ttf` — cache در .gitignore

---

## 📊 آمار کلی پروژه

- **فایل‌های PHP:** ۷۵۰+
- **جدول‌های دیتابیس:** ۶۶+
- **زبان‌های فعال:** ۲۰
- **Providerهای ترجمه:** ۷
- **ماژول‌ها:** ۵ (ai_assistant, content, generated, table_builder, shop)
- **خطوط فروشگاه:** ۸,۱۱۸
- **حجم پروژه:** ~۸۵ MB

---

## 🎯 اولویت‌های پیشنهادی

**کوتاه‌مدت (۱ هفته):**
1. فاز ۳: تبلیغات + اسلایدشو ← فاز بعدی
2. فاز ۴: قالب‌ساز + ۶۰ تمپلیت
3. فاز ۵: وبلاگ

**میان‌مدت (۱ ماه):**
4. فاز ۶: CRM
5. فاز ۷: Excel
6. فاز ۸: PowerPoint

**بلندمدت (۲ ماه+):**
7. فاز ۹: لایسنس
8. فاز ۱۰: اینستالر
9. فاز ۱۱: مستندات
10. فاز ۱۲: تست و دیباگ

**فازهای تکمیلی:**
- بازنگری ترجمه‌ها
- ترجمه خودکار در انتشار
- جدول‌ساز
- آنتی‌ویروس
- دستیار AI

---

## 📞 برای ادامه توسعه

1. اول این فایل رو کامل بخون
2. **فاز ۲ (فروشگاه) ۱۰۰٪ کامله**
3. **فاز بعدی: فاز ۳ (تبلیغات + اسلایدشو)**
4. ترتیب پیشنهادی: ۳ → ۴ → ۵ → ۶ → ۷ → ۸ → ۹ → ۱۰ → ۱۱ → ۱۲

**فایل‌های کلیدی:**
- config.php
- includes/ModuleManager.php
- includes/Security.php
- includes/AiAssistant.php
- admin/includes/multi_translator.php
- includes/content_i18n.php
- modules/shop/includes/ShopManager.php
- modules/shop/includes/OrderManager.php
- modules/shop/includes/InvoiceGenerator.php

---

**پایان TODO.md**
# 📋 PartoCMS Development Roadmap

> آخرین بروزرسانی: 2026-10-04
> طراحی و برنامه‌نویسی و مالک: **Hooman Oliaei (هومان اولیایی)**
> مخزن: https://github.com/hoazadir/PartoCMS

---

## 🎯 خلاصه پروژه

**PartoCMS** یک CMS/Platform اختصاصی است که با PHP 8.5 و MariaDB در محیط Termux توسعه می‌یابد.

**محیط فنی:**
- PHP: 8.5
- Database: MariaDB 12.3
- DB Name: grapesjs_cms
- Project Root: ~/PartoCMS/

---

## 📊 پیشرفت کلی پروژه

| فاز | عنوان | وضعیت |
|------|--------|--------|
| فاز ۰ | تثبیت پایه | ✅ ۱۰۰٪ |
| فاز ۱ | مستندسازی + امنیت ۱۰ لایه | ✅ ۱۰۰٪ |
| فاز ۲ | ماژول فروشگاه | ✅ ۱۰۰٪ |
| فاز ۳ | ماژول تبلیغات + اسلایدشو | ✅ ۱۰۰٪ |
| فاز ۳-A | AI Copywriting برای تبلیغات | ✅ ۱۰۰٪ |
| فاز ۳-B | AI Gateway + Cloud Providers | ✅ ۱۰۰٪ |
| فاز ۳-C | Anti-AdBlock | 🔄 در حال |
| فاز ۳-D | Smart Installer + Tier System | ⏳ |
| فاز ۴ | قالب‌ساز + ۶۰ تمپلیت | ⏳ |
| فاز ۵ | ماژول وبلاگ | ⏳ |
| فاز ۶ | ماژول CRM | ⏳ |
| فاز ۷ | ماژول Excel | ⏳ |
| فاز ۸ | ماژول PowerPoint | ⏳ |
| فاز ۹ | سیستم لایسنس | ⏳ |
| فاز ۱۰ | اینستالر هوشمند | ⏳ |
| فاز ۱۱ | مستندات کامل | ⏳ |
| فاز ۱۲ | تست و دیباگ | ⏳ |

---

## ✅ فاز ۰: تثبیت پایه ✅

- [x] حذف .git از web root
- [x] ساخت .htaccess در ریشه و admin/
- [x] حذف curl_close() از AiAssistant
- [x] رفع Cron Whisper
- [x] حذف mkcert و .bak
- [x] ساخت templates/
- [x] ساخت robots.txt و sitemap.xml
- [x] Log Rotation
- [x] manifest.json برای ماژول‌ها

---

## 🛡️ فاز ۱: مستندسازی فنی + امنیت ۱۰ لایه ✅

**بخش A: مستندسازی فنی**
- مستندات ساختار پروژه
- راهنمای معماری
- راهنمای توسعه‌دهنده

**بخش B: امنیت ۱۰ لایه**

| # | قابلیت | فایل |
|---|--------|------|
| ۱ | PHP Hardening | security_config.php |
| ۲ | FIM | fim.php |
| ۳ | Malware Scanner | fim.php |
| ۴ | Telegram Alerts | telegram_notifier.php |
| ۵ | Telegram Setup | telegram_setup.php |
| ۶ | VirusTotal | virustotal_scanner.php |
| ۷ | VirusTotal Setup | virustotal_setup.php |
| ۸ | Rate Limiting | rate_limiter.php |
| ۹ | 2FA (TOTP) | totp.php |
| ۱۰ | 2FA Setup | 2fa_setup.php |
| ۱۱ | Security Audit | security_audit.php |
| ۱۲ | Security Dashboard | security_dashboard.php |
| ۱۳ | Security Graphs | security_graphs.php |
| ۱۴ | Backup Manager | backup_manager.php |
| ۱۵ | CSRF | همه فرم‌ها |
| ۱۶ | Logout | logout.php |

**جداول:** file_hashes, security_logs, login_attempts, login_blocks, user_2fa, user_2fa_backup

**Cron:** cron_security_check.php, cron_weekly_report.php

---

## 🛒 فاز ۲: ماژول فروشگاه (E-Commerce) ✅ ۱۰۰٪

**آمار:**
- ۴۳ فایل PHP
- ۸,۱۱۸ خط کد
- ۱۸ جدول دیتابیس

**زیرفازها:**

| # | زیرفاز | فایل‌های کلیدی | خطوط |
|---|---------|----------------|-------|
| ۲.۱ | ساختار پایه فروشگاه | index.php, ShopManager.php | ~500 |
| ۲.۲ | محصولات و دسته‌بندی | product.php, admin/products.php | ~1500 |
| ۲.۳ | سبد خرید + آپلود | cart.php, ajax/ | ~1100 |
| ۲.۴ | سفارشات + Checkout | checkout.php, order-success.php | ~1200 |
| ۲.۵ | درگاه‌های ایرانی | zarinpal.php, idpay.php | ~600 |
| ۲.۶ | درگاه‌های بین‌المللی | paypal.php, stripe.php | ~350 |
| ۲.۷ | درگاه‌های ارز دیجیتال | coingate.php, nowpayments.php | ~300 |
| ۲.۸ | تخفیف، کوپن، ارسال | admin/coupons.php, admin/shipping.php | ~1200 |
| ۲.۹ | گزارش فروش | admin/reports.php, ReportManager.php | ~570 |
| ۲.۱۰ | فاکتور PDF | InvoiceGenerator.php, InvoiceHelper.php | ~670 |

**جدول‌های فروشگاه (۱۸):**

shop_products, shop_categories, shop_product_images, shop_product_variants, shop_product_attributes, shop_carts, shop_cart_items, shop_orders, shop_order_items, shop_order_status_history, shop_customers, shop_coupons, shop_coupon_usage, shop_shipping_methods, shop_tax_rates, shop_payment_gateways, shop_transactions, shop_settings

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

---

## 📊 فاز ۲-B: مدیریت و گزارش‌ها ✅

- Backup خودکار با gzip
- Clean Old Backups (۳۰ روز)
- گزارش هفتگی HTML ایمیل
- نمودارهای Chart.js (line, pie, bar)
- Dashboard گرافیکی

---

## 🌍 فاز ۲-C: i18n — ۲۰ زبان + ترجمه محتوا ✅

### i18n پایه

**۲۰ زبان فعال:**
fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN, ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL

**جداول:** languages, translation_keys, translations, user_preferences

**فایل‌ها:**
- admin/includes/i18n.php
- admin/includes/language_switcher.php
- admin/languages.php
- admin/translations.php

**توابع config.php:**
- getI18n()
- __t($key, $params, $default)
- __html_attrs()
- __dir()

**ویژگی‌ها:**
- Language persistence: DB + Cookie + Session + Browser
- کش فایل: logs/i18n_cache/
- RTL/LTR خودکار در ۴۷ فایل
- Bootstrap RTL/LTR خودکار

### ترجمه محتوا — MultiTranslator v6.1

**۷ Provider:**
DeepL, Microsoft, Yandex, Google, Lingva, Libre, MyMemory

**ویژگی‌ها:**
- ترجمه موازی از همه providerها
- Quality Scoring (0-100)
- تشخیص garbage
- tag_handling=html برای DeepL
- Cache با اعتبارسنجی مجدد
- Fallback خودکار

**فایل‌ها:**
- admin/includes/multi_translator.php
- admin/content_translate.php
- admin/translator_settings.php

**جداول:**
content_translations, translation_versions, translation_cache, provider_stats, category_translations

### نمایش ترجمه در فرانت‌اند

**includes/content_i18n.php v3 (Helper):**
- getCurrentLanguageInfo()
- getCurrentLanguageId()
- isDefaultLanguage()
- applyTranslationToPost() / applyTranslationsToPosts()
- applyTranslationToCategory() / applyTranslationsToCategories()

**فایل‌های فرانت‌اند:**
post.php, index.php, category.php, search.php, login.php, register.php

---

## 📢 فاز ۳: ماژول تبلیغات + اسلایدشو ✅ ۱۰۰٪

**فایل‌ها:** modules/ads/ (۱۹ فایل PHP، ۷,۲۳۶ خط)

**ویژگی‌های انجام‌شده:**
- ✅ آپلود بنر و اسلایدشو
- ✅ ۸ موقعیت: header, sidebar, footer, in-content, popup, sticky-footer, slider-home, home-top
- ✅ بازه زمانی نمایش
- ✅ آمار نمایش (ad_impressions) و کلیک (ad_clicks)
- ✅ Targeting پیشرفته: all/manual/rules
- ✅ Auto-switch target_mode (اگر rules داشت → rules)
- ✅ Prioritization + Campaigns
- ✅ AdSense + HTML + Text Ads
- ✅ گزارش‌های تحلیلی (ad-reports.php)
- ✅ ۷ حالت نمایش: single, slider, carousel, rotation, grid, stack, marquee
- ✅ ۴۵ افکت
- ✅ RTL + i18n (۲۰ زبان)
- ✅ AdWidget v3 (نمایش در همه صفحات)
- ✅ Context Detection خودکار (post/category/index)
- ✅ ادغام با AdWidget در post.php و category.php

**جداول:** ads, ad_positions, ad_campaigns, ad_clicks, ad_impressions, ad_target_rules

---

## 🤖 فاز ۳-A: AI Copywriting ✅ ۱۰۰٪

**فایل‌های کلیدی:**
- ✅ modules/ads/includes/AdAiAssistant.php
- ✅ modules/ads/ajax/ad-ai-generate.php
- ✅ includes/AiAssistant.php (chatJson)
- ✅ UI در ad-edit.php (کارت AI + Modal)

**ویژگی‌ها:**
- ✅ تولید متن تبلیغ از توضیح کوتاه
- ✅ چند variant (۲-۵ نسخه)
- ✅ بهبود متن موجود
- ✅ پیشنهاد Targeting
- ✅ پشتیبانی ۲۰ زبان
- ✅ دکمه «✨ کمک AI» در ad-edit.php
- ✅ Modal نمایش پیشنهادها + One-click apply
- ✅ Robust JS init (کار در Chrome/Edge/Firefox)

**تست شده:**
- ✅ زمان پاسخ: ۱۰.۲۷s (ابری) vs ۷۰-۱۲۷s (محلی)
- ✅ کیفیت: متن حرفه‌ای فارسی
- ✅ Provider: NVIDIA Nemotron 3 Ultra 550B

---


### فایل‌های کلیدی:

- ✅ includes/AI/AIGateway.php (Fallback خودکار)
- ✅ includes/AI/AIEnvironment.php (تشخیص محیط)
- ✅ includes/AI/AIProxyManager.php (SOCKS5/HTTP)
- ✅ includes/AI/AICircuitBreaker.php (Cooldown خودکار)
- ✅ includes/AI/Providers/LLM/OpenRouterProvider.php
- ✅ includes/AI/Providers/LLM/GroqProvider.php
- ✅ includes/AI/Providers/LLM/GeminiProvider.php

### UI مدیریت:

- ✅ admin/ai_providers.php (۶ تب)
- ✅ گروه sidebar «🤖 هوش مصنوعی»
- ✅ دکمه «تست اتصال» برای هر provider
- ✅ ۱۶ مدل رایگان در OpenRouter dropdown
- ✅ بخش Proxy در تب تنظیمات
- ✅ Drag & Drop برای ترتیب Fallback

### تست شده:

- ✅ OpenRouter: ۴۶۶ مدل در دسترس
- ✅ NVIDIA Nemotron: ۲.۱s پاسخ
- ✅ Fallback chain کار می‌کند
- ✅ Circuit Breaker + Cooldown فعال

---


### فایل‌های کلیدی:

- ✅ includes/AI/AIGateway.php (Fallback خودکار)
- ✅ includes/AI/AIEnvironment.php (تشخیص محیط)
- ✅ includes/AI/AIProxyManager.php (SOCKS5/HTTP)
- ✅ includes/AI/AICircuitBreaker.php (Cooldown خودکار)
- ✅ includes/AI/Providers/LLM/OpenRouterProvider.php
- ✅ includes/AI/Providers/LLM/GroqProvider.php
- ✅ includes/AI/Providers/LLM/GeminiProvider.php

### UI مدیریت:

- ✅ admin/ai_providers.php (۶ تب)
- ✅ گروه sidebar «🤖 هوش مصنوعی»
- ✅ دکمه «تست اتصال» برای هر provider
- ✅ ۱۶ مدل رایگان در OpenRouter dropdown
- ✅ بخش Proxy در تب تنظیمات
- ✅ Drag & Drop برای ترتیب Fallback

### تست شده:

- ✅ OpenRouter: ۴۶۶ مدل در دسترس
- ✅ NVIDIA Nemotron: ۲.۱s پاسخ
- ✅ Fallback chain کار می‌کند
- ✅ Circuit Breaker + Cooldown فعال

---

## 🛡️ فاز ۳-C: Anti-AdBlock 🔄 در حال

**مشکل:** Edge Tracking Prevention + AdBlockها تبلیغات را بلاک می‌کنند.

### راه‌حل‌های پیشنهادی:

**گام ۱: تغییر نام کلاس‌ها (Obfuscation)**
- ad-slot → pcms-slot
- ad-slider → pcms-slider
- ad-banner → pcms-banner
- ad-nav → pcms-nav
- ad-dot → pcms-dot
- ad-track → pcms-track

**گام ۲: دامنه اختصاصی**
- ads.yoursite.com → همین سرور

**گام ۳: تزریق پویا با JavaScript**

### فایل‌های موردنیاز:
- ⏳ modules/ads/includes/AdObfuscator.php
- ⏳ بروزرسانی AdRenderer.php
- ⏳ بروزرسانی AdWidget.php
- ⏳ مستندات

---

## 🎨 فاز ۴: تکمیل قالب‌ساز + ۶۰ تمپلیت

- بررسی admin/templates.php و admin/editor.php
- رفع باگ‌های GrapesJS
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

## 📰 فاز ۵: ماژول وبلاگ

**ویژگی‌ها:**
- سیستم پست پیشرفته
- کامنت‌ها (Threaded)
- Featured image + Gallery
- SEO (Meta, OG, Schema)
- RSS/Atom feed
- Newsletter
- Related posts
- Reading time
- Draft / Scheduled publishing
- Multi-author
- آرشیو
- ترجمه محتوا

**جداول:** posts, post_categories, post_tags, post_comments, post_meta, post_revisions

---

## 🤝 فاز ۶: ماژول CRM

- مدیریت مشتریان
- تاریخچه تعاملات
- تیکتینگ پشتیبانی
- تحلیل رفتار
- اتوماسیون
- قیف فروش
- مدیریت سرنخ‌ها
- ادغام با فروشگاه

**جداول:** crm_contacts, crm_interactions, crm_tickets, crm_deals, crm_leads, crm_pipeline_stages

---

## 📊 فاز ۷: ماژول Excel

**کتابخانه:** Luckysheet

- ویرایشگر صفحه‌گسترده
- فرمول‌ها و توابع
- نمودارها
- Pivot Tables
- Conditional Formatting
- Data Validation
- صادرات/واردات XLSX/CSV
- ۳۰+ تمپلیت
- Collaboration

**جداول:** spreadsheets, spreadsheet_versions, spreadsheet_shares, spreadsheet_templates

---

## 🎯 فاز ۸: ماژول PowerPoint

**کتابخانه:** Reveal.js + Fabric.js

- ویرایشگر اسلاید
- ۱۰۰+ قالب
- انیمیشن و ترنزیشن
- ضبط صدا
- ارائه زنده
- Presenter View
- صادرات/واردات PPTX

**جداول:** presentations, presentation_versions, presentation_slides, presentation_shares, presentation_templates

---

## 🔐 فاز ۹: سیستم لایسنس

- نمایش نام مالک: Hooman Oliaei
- رمزنگاری کلید
- اعتبارسنجی آنلاین
- محدودیت دامنه
- تاریخ انقضا
- صفحه مدیریت

**جداول:** licenses, license_keys, license_activations, license_logs

---

## ⚙️ فاز ۱۰: اینستالر پیشرفته

- بررسی پیش‌نیازها
- فرم اطلاعات DB
- تست اتصال
- ساخت جداول
- ساخت ادمین اولیه
- ساخت config.php
- انتخاب زبان
- انتخاب قالب
- حذف خودکار install/
- پشتیبانی هاست/VPS/کلود
- پشتیبانی Linux/Windows/macOS

---

## 📚 فاز ۱۱: مستندات کامل

- راهنمای کاربری (HTML + PDF)
- راهنمای فنی
- مستندات API
- راهنمای معماری
- FAQ
- راهنمای نصب گام‌به‌گام
- ویدیوهای آموزشی

---

## 🧪 فاز ۱۲: تست و دیباگ

- Unit tests (PHPUnit)
- Integration tests
- Security tests
- Performance tests
- Cross-browser testing
- Mobile responsive testing
- Load testing
- رفع باگ‌ها

---

## 🔄 فازهای تکمیلی (اولویت پایین‌تر)

### بازنگری ترجمه‌ها

- صفحه admin/content_review.php
- فیلتر زبان/provider/کیفیت
- دکمه تولید مجدد
- ویرایش inline
- خروجی CSV/Excel

### ترجمه خودکار در انتشار

- Hook بعد از publish
- ترجمه async با Queue
- اطلاع‌رسانی Telegram

### جدول‌ساز (Table Builder)

- تعریف جدول بدون SQL
- CRUD خودکار
- Export/Import
- Drag & Drop

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

**جداول:** ai_conversations, ai_messages

---

## 🛠️ اطلاعات فنی مهم

### توابع config.php

- getI18n(), __t(), __html_attrs(), __dir()
- isLoggedIn(), isAdmin()
- getSetting($key, $default)
- getDB()

### Helperهای کلیدی

**modules/ads/includes/AdManager:**
- getActiveAdsForPosition(), getStats(), getCtr()
- create(), update(), delete(), getTargeting()

**modules/ads/includes/AdRenderer:**
- renderPosition(), renderAd()
- renderSlider(), renderCarousel(), renderGrid()
- setContextFromContent(), setContextFromCategory()

**modules/ads/includes/AdAiAssistant (جدید):**
- generateCopy(), generateVariants(), improveCopy()
- suggestTargeting(), getAvailableLanguages()

**includes/AI/AIGateway (جدید):**
- chat(), chatJson(), translate()
- getActiveProviders(), getStatus()
- testProvider(), resetBreaker()

---

## 🎨 الگوهای مهم کد

**ترجمه:** `<?= __t('key', [], 'پیش‌فرض') ?>`

**HTML tag:** `<html <?= __html_attrs() ?>>`

**Bootstrap RTL/LTR:** `bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css`

**اعمال ترجمه:** `$post = applyTranslationToPost($post);`

**فاکتور PDF:** از mPDF (نه dompdf)

**فونت فارسی:** assets/fonts/Vazirmatn-Regular.ttf + Vazirmatn-Bold.ttf

**AdWidget در صفحات:**

    <?= AdWidget::header() ?>
    <?= AdWidget::footer() ?>
    <?= AdWidget::js() ?>

**AI Chat:**

    $ai = new AdAiAssistant();
    $result = $ai->generateVariants([...]);

---

## 📌 نکات مهم برای توسعه‌دهنده بعدی

1. قبل از هر تغییر: cp file.php file.php.bak-$(date +%s)
2. جداول جدید: SHOW TABLES چک کن (پیشوند shop_ برای فروشگاه)
3. کش i18n: اگر ترجمه‌ها به‌روز نشدند → rm logs/i18n_cache/*.json
4. RTL/LTR: همیشه از __html_attrs() و isRtl() استفاده کن
5. تست امنیتی: بعد از تغییرات مهم → admin/security_audit.php
6. DeepL quota: از admin/translator_settings.php چک کن
7. ترجمه محتوا: admin/content_translate.php?id=X
8. فاکتور PDF: از mPDF استفاده می‌شود (نه dompdf)
9. git: commit منظم با پیام‌های واضح
10. فونت: فونت‌های اصلی در assets/fonts/Vazirmatn-*.ttf
11. AI Gateway: Chain در DB (ai_provider_chain) — قابل تغییر از پنل
12. AdBlock: Edge Tracking Prevention باید در Basic باشد

---

## 📊 آمار کلی پروژه

- فایل‌های PHP: ۷۵۰+
- جدول‌های دیتابیس: ۷۳+
- زبان‌های فعال: ۲۰
- Providerهای ترجمه: ۷
- Providerهای AI: ۵ (Ollama, OpenRouter, Groq, Gemini, OpenAI)
- ماژول‌ها: ۶ (ads, ai_assistant, content, generated, table_builder, shop)
- خطوط فروشگاه: ۸,۱۱۸
- خطوط تبلیغات: ۷,۲۳۶
- خطوط AI: ۳,۰۰۰+
- حجم پروژه: ~۸۵ MB

---

## 🎯 اولویت‌های پیشنهادی

**کوتاه‌مدت (۱ هفته):**
1. ✅ فاز ۳: تبلیغات + اسلایدشو (۱۰۰٪)
2. ✅ فاز ۳-A: AI Copywriting (۱۰۰٪)
3. ✅ فاز ۳-B: AI Gateway + Cloud Providers (۱۰۰٪)
4. 🔄 فاز ۳-C: Anti-AdBlock (جلوگیری از بلاک)
5. ⏳ فاز ۳-D: Smart Installer + Tier System
6. فاز ۴: قالب‌ساز + ۶۰ تمپلیت

**میان‌مدت (۱ ماه):**
7. فاز ۵: وبلاگ
8. فاز ۶: CRM
9. فاز ۷: Excel
10. فاز ۸: PowerPoint

**بلندمدت (۲ ماه+):**
11. فاز ۹: لایسنس
12. فاز ۱۰: اینستالر
13. فاز ۱۱: مستندات
14. فاز ۱۲: تست و دیباگ

**فازهای تکمیلی:**
- بازنگری ترجمه‌ها
- ترجمه خودکار در انتشار
- جدول‌ساز
- آنتی‌ویروس
- دستیار AI

---

## 📞 برای ادامه توسعه

1. اول این فایل رو کامل بخون
2. فاز ۲ (فروشگاه) ۱۰۰٪ کامله
3. فاز ۳ (تبلیغات + AI) ۱۰۰٪ کامله
4. فاز بعدی: ۳-C (Anti-AdBlock) یا ۳-D (Smart Installer)
5. ترتیب پیشنهادی: ۳-C → ۳-D → ۴ → ۵ → ۶ → ۷ → ۸ → ۹ → ۱۰ → ۱۱ → ۱۲

**فایل‌های کلیدی:**
- config.php
- includes/ModuleManager.php
- includes/Security.php
- includes/AiAssistant.php
- includes/AI/AIGateway.php
- admin/includes/multi_translator.php
- includes/content_i18n.php
- modules/shop/includes/ShopManager.php
- modules/ads/includes/AdManager.php
- modules/ads/includes/AdRenderer.php
- modules/ads/includes/AdAiAssistant.php

---

**پایان TODO.md**
