# 📋 PartoCMS Development Roadmap

> آخرین بروزرسانی: 2026-09-14
> این فایل نمای کامل وضعیت پروژه است و به عنوان context برای ادامه توسعه استفاده می‌شود.

---

## 🎯 خلاصه پروژه

**PartoCMS** یک CMS اختصاصی است که با PHP 8.5 و MariaDB 12.3 در محیط Termux (Android) توسعه می‌یابد.

**محیط فنی:**
- PHP: 8.5
- Database: MariaDB 12.3
- DB Name: grapesjs_cms
- DB User: root
- Server: php -S 127.0.0.1:8080
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

**جداول امنیتی:**
- file_hashes
- security_logs
- login_attempts
- login_blocks
- user_2fa
- user_2fa_backup

**Cron Jobs:**
- admin/cron_security_check.php — هر ۶ ساعت
- admin/cron_weekly_report.php — هر شنبه ساعت ۹

---

### 📊 فاز ۲: مدیریت و گزارش‌ها ✅

- Backup خودکار با gzip
- Clean Old Backups (۳۰ روز)
- گزارش هفتگی HTML ایمیل
- نمودارهای Chart.js (line, pie, bar)
- Dashboard گرافیکی

---

### 🌍 فاز ۳-A1: i18n پایه ✅

**۲۰ زبان فعال:**
🇮🇷 fa-IR | 🇺🇸 en-US | 🇬🇧 en-GB | 🇸🇦 ar-SA | 🇹🇷 tr-TR
🇩🇪 de-DE | 🇫🇷 fr-FR | 🇪🇸 es-ES | 🇷🇺 ru-RU | 🇨🇳 zh-CN
🇯🇵 ja-JP | 🇰🇷 ko-KR | 🇮🇹 it-IT | 🇧🇷 pt-BR | 🇳🇱 nl-NL
🇵🇱 pl-PL | 🇮🇳 hi-IN | 🇮🇩 id-ID | 🇻🇳 vi-VN | 🇮🇱 he-IL

**جداول:**
- languages
- translation_keys
- translations
- user_preferences

**فایل‌ها:**
- admin/includes/i18n.php
- admin/includes/language_switcher.php
- admin/languages.php
- admin/translations.php

**توابع در config.php:**
- getI18n()
- __t($key, $params, $default)
- __html_attrs()
- __dir()

**ویژگی‌ها:**
- Language persistence: DB + Cookie (partocms_lang) + Session + Browser
- کش فایل: logs/i18n_cache/
- Modal انتخاب زبان
- RTL/LTR خودکار در ۴۷ فایل
- Bootstrap RTL/LTR خودکار

---

### 📝 فاز ۳-A2: ترجمه فرانت‌اند ✅

- ۳۴ کلید ترجمه فرانت‌اند
- ۶۸۰ ترجمه (۳۴ × ۲۰ زبان)
- اعمال در ۱۱ فایل فرانت‌اند
- ۷۸ متن جایگزین با __t()

**فایل‌های اصلاح‌شده:**
- index.php, post.php, category.php, search.php
- login.php, register.php, form.php
- user/index.php, user/profile.php, user/password.php, user/posts.php, user/comments.php

**کلیدهای فرانت‌اند:**
fe_home, fe_search, fe_search_site, fe_login, fe_register, fe_logout, fe_admin_panel, fe_user_panel, fe_dashboard, fe_profile, fe_my_posts, fe_my_comments, fe_change_password, fe_edit_profile, fe_save, fe_cancel, fe_send, fe_view, fe_read_more, fe_back_home, fe_previous, fe_next, fe_username, fe_password, fe_email, fe_website, fe_phone, fe_welcome, fe_no_results, fe_not_found, fe_latest_posts, fe_views, fe_comments, fe_write_comment

---

### 🔄 فاز ۳-A3: ترجمه محتوا ✅

**MultiTranslator v6.1** (admin/includes/multi_translator.php):

**۷ Provider:**
| Provider | وضعیت | رایگان | نیاز به Key |
|----------|-------|--------|-------------|
| 🥇 DeepL | ✅ فعال | ۵۰۰K/ماه | ✅ (داریم) |
| 🥈 Microsoft | ⏳ آماده | ۲M/ماه | ❌ (بدون key) |
| 🥉 Yandex | ⏳ آماده | ۱M (۹۰ روز) | ❌ (بدون key) |
| Google | ⚠️ Rate Limit | نامحدود | ❌ |
| Lingva | ❌ Fail | — | ❌ |
| Libre | ❌ Fail | — | ❌ |
| MyMemory | ✅ فعال | ۱۰۰۰/روز | ❌ |

**ویژگی‌های MultiTranslator v6.1:**
- ترجمه موازی از همه provider ها
- Quality Scoring (0-100)
- تشخیص garbage (mymemory cache, uppercase, similarity)
- Weight-based scoring + tie-break صریح
- `tag_handling=html` برای DeepL (حفظ جداول HTML)
- Cache با اعتبارسنجی مجدد
- محدودسازی MyMemory (text > 300 chars رد می‌شه)
- Fallback خودکار: اگه DeepL تمام شه → Microsoft → Google → MyMemory

**جدول‌ها:**
- content_translations — ترجمه نهایی هر مقاله به هر زبان
- translation_versions — همه نسخه‌ها از provider های مختلف
- translation_cache — کش ترجمه‌ها
- provider_stats — آمار provider ها (کیفیت، سرعت، خطا)
- category_translations — ترجمه دسته‌ها

**فایل‌ها:**
- admin/includes/multi_translator.php (اصلی)
- admin/includes/content_translator.php (قدیمی، fallback)
- admin/content_translate.php (UI ترجمه)
- admin/translator_settings.php (تنظیمات API Keys)

**تست موفق:**
- مقاله ۵ به ۱۹ زبان ترجمه شد (avg score 73-90)
- DeepL برای همه زبان‌ها برنده شد ✅
- جداول HTML در ترجمه سالم موندن ✅

---

### 🌐 فاز ۴: نمایش ترجمه در فرانت‌اند ✅

**includes/content_i18n.php v3 (Helper):**

توابع کلیدی:
- getCurrentLanguageInfo() — اولویت: URL → Cookie → Session → I18n → DB default
- getCurrentLanguageId()
- isDefaultLanguage()
- applyTranslationToPost($post) — ترجمه یک پست
- applyTranslationsToPosts($posts) — batch
- applyTranslationToCategory($cat) — ترجمه یک دسته
- applyTranslationsToCategories($cats) — batch
- translateAndCacheCategory($cat, $langId, $pdo)

**فایل‌های فرانت‌اند:**
- post.php ✅
- index.php ✅
- category.php ✅
- search.php ✅ (جستجو در ترجمه‌ها با JOIN)
- login.php ✅ (کامل i18n)
- register.php ✅ (کامل i18n)

**کلیدهای i18n اضافه‌شده در فاز ۴:**
- ۲۵ کلید auth (login/register)
- ۷ کلید search
- ۴ کلید category

**باگ‌های رفع‌شده:**
- مشکل redirect بعد از set_lang (cookie partocms_lang)
- جستجو در ترجمه‌ها (نه فقط content_items)
- جداول HTML در ترجمه (tag_handling=html)

---

## ⏳ فازهای باقی‌مانده

### 📝 فاز ۳-A4: بازنگری ترجمه‌ها (۳۰ دقیقه) ⏳ بعدی

**هدف:** صفحه‌ای برای ویرایش ترجمه‌های ضعیف

**فایل پیشنهادی:** admin/content_review.php

**ویژگی‌ها:**
- [ ] لیست ترجمه‌ها با quality_score
- [ ] فیلتر بر اساس زبان / provider / کیفیت
- [ ] دکمه «تولید مجدد با provider دیگه»
- [ ] ویرایش دستی inline
- [ ] آمار: درصد ترجمه‌های کیفیت بالای ۸۰
- [ ] خروجی CSV/Excel

---

### 🔄 فاز ۳-A5: ترجمه خودکار در انتشار (۲۰ دقیقه)

**هدف:** Hook بعد از publish

**فایل:** modules/content/admin.php

**ویژگی‌ها:**
- [ ] Hook بعد از publish مقاله
- [ ] ترجمه async یا با Queue
- [ ] اطلاع‌رسانی در Telegram
- [ ] امکان غیرفعال کردن

---

### 📊 فاز ۵: جدول‌ساز (Table Builder) (۴۵ دقیقه)

**هدف:** ساخت جدول‌های دلخواه بدون SQL

**فایل:** admin/table_builder.php

**ویژگی‌ها:**
- [ ] تعریف نام جدول
- [ ] تعریف ستون‌ها (نام، نوع، طول، null/not null)
- [ ] تعریف روابط (foreign key)
- [ ] Index و Unique
- [ ] CRUD خودکار برای جدول
- [ ] Export/Import (SQL/JSON/CSV)
- [ ] رابط کاربری دراگ‌اند‌دراپ

---

### ⚙️ فاز ۶: اینستالر پیشرفته (۶۰ دقیقه)

**هدف:** Wizard نصب روی هاست

**فایل:** install/index.php

**مراحل:**
- [ ] بررسی پیش‌نیازهای PHP (نسخه، extension ها)
- [ ] بررسی دسترسی نوشتن در پوشه‌ها
- [ ] فرم دریافت اطلاعات DB
- [ ] تست اتصال DB
- [ ] ساخت جداول از فایل SQL
- [ ] ساخت ادمین اولیه
- [ ] ساخت config.php خودکار
- [ ] حذف پوشه install/ بعد از نصب

---

### 🎨 فاز ۷: تکمیل قالب‌ساز (۳۰ دقیقه)

**کارها:**
- [ ] بررسی admin/templates.php
- [ ] بررسی admin/editor.php
- [ ] رفع باگ‌های موجود
- [ ] به‌روزرسانی GrapesJS به آخرین نسخه
- [ ] افزودن کامپوننت‌های جدید
- [ ] بلوک‌های آماده (Hero, CTA, Pricing, ...)

---

### 📢 فاز ۸: تبلیغات + بنر (۳۰ دقیقه)

**فایل:** admin/ads.php

**ویژگی‌ها:**
- [ ] آپلود بنر
- [ ] تعریف موقعیت (header, sidebar, footer)
- [ ] بازه زمانی نمایش
- [ ] آمار نمایش (impressions) و کلیک (clicks)
- [ ] اهداف تبلیغاتی
- [ ] Prioritization

**جدول:** ads

---

### 🛒 فاز ۹: فروشگاه‌ساز (پروژه بزرگ — ۲-۴ هفته)

**زیرسیستم‌ها:**

**۹-۱ محصولات و دسته‌بندی:**
- [ ] جدول products (نام، توضیح، قیمت، موجودی، تصویر)
- [ ] جدول product_categories (سلسله‌مراتبی)
- [ ] جدول product_images (چند تصویر)
- [ ] جدول product_variants (اندازه، رنگ، ...)
- [ ] جدول product_attributes
- [ ] صفحه ادمین محصولات
- [ ] صفحه فرانت محصولات

**۹-۲ سبد خرید:**
- [ ] جدول cart (session-based)
- [ ] افزودن/حذف/ویرایش تعداد
- [ ] ذخیره سبد برای کاربران عضو
- [ ] Session برای مهمان‌ها

**۹-۳ سفارشات:**
- [ ] جدول orders
- [ ] جدول order_items
- [ ] جدول order_status_history
- [ ] آدرس ارسال و صورتحساب
- [ ] تاریخچه سفارشات کاربر

**۹-۴ پرداخت:**
- [ ] درگاه زرین‌پال
- [ ] درگاه آیدی‌پی
- [ ] پرداخت در محل (COD)
- [ ] درگاه‌های بین‌المللی (اختیاری)

**۹-۵ حمل و نقل:**
- [ ] جدول shipping_methods
- [ ] هزینه بر اساس وزن/مکان
- [ ] Tracking

**۹-۶ مالیات:**
- [ ] جدول tax_rates
- [ ] اعمال بر اساس منطقه

**۹-۷ کوپن تخفیف:**
- [ ] جدول coupons
- [ ] اعتبارسنجی

**۹-۸ گزارش فروش:**
- [ ] فروش روزانه/هفتگی/ماهانه
- [ ] پرفروش‌ترین محصولات
- [ ] نمودار درآمد

**۹-۹ فاکتور PDF:**
- [ ] تولید PDF سفارش
- [ ] ارسال به ایمیل

**فایل‌های پیشنهادی:**
- admin/products.php
- admin/orders.php
- admin/coupons.php
- shop.php
- product.php
- cart.php
- checkout.php
- payment/

**جداول:** ۱۵+ جدول جدید

---

### 🤖 فاز ۱۰: دستیار هوش مصنوعی (Ollama)

**پیش‌نیاز:**
- [ ] نصب Ollama در Termux
- [ ] دانلود مدل (llama3.2:3b یا qwen2.5:3b)
- [ ] تست API

**ویژگی‌ها:**

**۱۰-۱ چت با AI:**
- [ ] صفحه چت شبیه ChatGPT
- [ ] تاریخچه چت
- [ ] ذخیره در دیتابیس

**۱۰-۲ طراحی ماژول با AI:**
- [ ] درخواست: «یک ماژول نظرسنجی بساز»
- [ ] AI کد PHP تولید کند
- [ ] نمایش در ویرایشگر و تأیید

**۱۰-۳ اسکن سایت با AI:**
- [ ] تحلیل امنیتی کدها
- [ ] پیشنهاد بهبود
- [ ] رفع خودکار مشکلات

**۱۰-۴ چیدمان سایت با AI:**
- [ ] درخواست: «صفحه اصلی با طراحی مدرن»
- [ ] AI HTML/CSS تولید کند

**فایل‌ها:**
- admin/ai_assistant.php
- admin/includes/ollama_client.php

**جداول:**
- ai_conversations
- ai_messages

---

### 🦠 فاز ۱۱: تکمیل آنتی‌ویروس و اسکن سایت (۴۵ دقیقه)

**فایل:** admin/full_security_scan.php

**ویژگی‌ها:**
- [ ] اسکن دیتابیس برای کدهای مخرب (<script>, eval, base64_decode)
- [ ] اسکن فایل‌های آپلودی
- [ ] اسکن تنظیمات .htaccess
- [ ] بررسی Permissions فایل‌ها
- [ ] بررسی SSL/TLS
- [ ] گزارش به Telegram

---

## 🛠️ اطلاعات فنی مهم

### توابع config.php
- getI18n() — نمونه I18n
- __t($key, $params, $default) — ترجمه
- __html_attrs() — lang + dir خودکار
- __dir() — جهت زبان فعلی
- isLoggedIn(), isAdmin()
- getSetting($key, $default)
- getDB()

### Helperهای includes/content_i18n.php
- getCurrentLanguageInfo() — اطلاعات کامل زبان فعلی
- getCurrentLanguageId() — ID زبان فعلی
- isDefaultLanguage() — آیا زبان پیش‌فرضه
- applyTranslationToPost($post) — ترجمه یک پست
- applyTranslationsToPosts($posts) — batch
- applyTranslationToCategory($cat) — ترجمه یک دسته
- applyTranslationsToCategories($cats) — batch
- translateAndCacheCategory($cat, $langId, $pdo)

### الگوهای مهم کد

**ترجمه:**
<?= __t('key', [], 'پیش‌فرض') ?>

**HTML tag:**
<html <?= __html_attrs() ?>>

**Bootstrap RTL/LTR خودکار:**
bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css

**اعمال ترجمه روی پست:**
$post = applyTranslationToPost($post);

**اعمال ترجمه روی لیست:**
$posts = applyTranslationsToPosts($posts);

**ترجمه دسته:**
$category = applyTranslationToCategory($category);

---

## 📌 نکات مهم برای توسعه‌دهنده بعدی

1. **قبل از هر تغییر:** cp file.php file.php.bak-$(date +%s)
2. **جداول جدید:** SHOW TABLES چک کن
3. **کش i18n:** اگه ترجمه‌ها به‌روز نشدند → rm logs/i18n_cache/*.json
4. **RTL/LTR:** همیشه از __html_attrs() و isRtl() استفاده کن
5. **تست امنیتی:** بعد از تغییرات مهم → admin/security_audit.php
6. **DeepL quota:** از admin/translator_settings.php چک کن
7. **ترجمه محتوا:** admin/content_translate.php?id=X
8. **گیت:** commit منظم با پیام‌های واضح

---

## 📊 آمار کلی پروژه

- **فایل‌های PHP:** ۶۵+
- **جدول‌های دیتابیس:** ۴۲+
- **زبان‌های فعال:** ۲۰
- **Provider های ترجمه:** ۷
- **کلیدهای ترجمه:** ۱۰۰+
- **ترجمه‌های موجود:** ۲۰۰۰+

---

## 🎯 اولویت‌های پیشنهادی

**کوتاه‌مدت (۱-۲ روز):**
1. فاز ۳-A4: بازنگری ترجمه‌ها
2. فاز ۳-A5: ترجمه خودکار در انتشار

**میان‌مدت (۱ هفته):**
3. فاز ۵: جدول‌ساز
4. فاز ۶: اینستالر
5. فاز ۷: تکمیل قالب‌ساز
6. فاز ۸: تبلیغات

**بلندمدت (۱ ماه+):**
7. فاز ۹: فروشگاه‌ساز
8. فاز ۱۰: هوش مصنوعی
9. فاز ۱۱: آنتی‌ویروس کامل

---

## 📞 برای ادامه توسعه

اگه این فایل رو در چت جدید می‌خوانی:

1. اول این فایل رو کامل بخون
2. از فاز ۳-A4 (بازنگری ترجمه‌ها) ادامه بده
3. ترتیب: ۳-A4 → ۳-A5 → ۵ → ۶ → ۷ → ۸ → ۹ → ۱۰ → ۱۱

**فایل‌های کلیدی:**
- admin/includes/multi_translator.php
- includes/content_i18n.php
- admin/content_translate.php
- config.php

---

**پایان TODO.md**
