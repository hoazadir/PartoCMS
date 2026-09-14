# 📋 PartoCMS Development Roadmap

> آخرین بروزرسانی: 2026-09-14
> این فایل نمای کامل وضعیت پروژه است.

## 🎯 خلاصه پروژه

**PartoCMS** یک CMS اختصاصی PHP/MySQL در Termux.

**محیط فنی:**
- PHP: 8.5
- Database: MariaDB 12.3
- DB: grapesjs_cms
- Server: php -S 127.0.0.1:8080
- Root: ~/PartoCMS/

---

## ✅ فاز ۱: امنیت ۱۰ لایه

| # | قابلیت | فایل |
|---|--------|------|
| ۱ | PHP Hardening | admin/includes/security_config.php |
| ۲ | FIM | admin/includes/fim.php |
| ۳ | Malware Scanner | admin/includes/fim.php |
| ۴ | Telegram Alerts | admin/includes/telegram_notifier.php |
| ۵ | Telegram Setup | admin/telegram_setup.php |
| ۶ | VirusTotal | admin/includes/virustotal_scanner.php |
| ۷ | VirusTotal Setup | admin/virustotal_setup.php |
| ۸ | Rate Limiting | admin/includes/rate_limiter.php |
| ۹ | 2FA (TOTP) | admin/includes/totp.php |
| ۱۰ | 2FA Setup | admin/2fa_setup.php |
| ۱۱ | Security Audit | admin/security_audit.php |
| ۱۲ | Security Dashboard | admin/security_dashboard.php |
| ۱۳ | Security Graphs | admin/security_graphs.php |
| ۱۴ | Backup Manager | admin/backup_manager.php |
| ۱۵ | Logout | admin/logout.php |

**جداول امنیتی:**
- file_hashes
- security_logs
- login_attempts
- login_blocks
- user_2fa
- user_2fa_backup

**Cron Jobs:**
- admin/cron_security_check.php (هر ۶ ساعت)
- admin/cron_weekly_report.php (هر شنبه ۹ صبح)

---

## ✅ فاز ۲: مدیریت و گزارش‌ها

| # | قابلیت | فایل |
|---|--------|------|
| ۱ | Backup gzip | admin/backup_manager.php |
| ۲ | Clean Old | admin/backup_manager.php |
| ۳ | گزارش هفتگی HTML | admin/includes/weekly_reporter.php |
| ۴ | نمودار Chart.js | admin/security_graphs.php |

---

## ✅ فاز ۳-A1: i18n پایه

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

**۲۰ زبان فعال:**
fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN,
ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL

**ویژگی‌ها:**
- Language persistence (DB + Cookie + Session + Browser)
- کش فایل
- Modal انتخاب زبان
- RTL/LTR خودکار

---

## ✅ فاز ۳-A2: ترجمه فرانت‌اند

- ۳۴ کلید ترجمه فرانت
- ۶۸۰ ترجمه (۳۴ × ۲۰)
- اعمال در ۱۱ فایل: index, post, category, search, login, register, form, user/index, user/profile, user/password, user/posts, user/comments
- ۷۸ متن جایگزین با __t()

---

## ✅ فاز ۳-A2: ترجمه فرانت‌اند

- ۳۴ کلید ترجمه فرانت
- ۶۸۰ ترجمه (۳۴ × ۲۰)
- اعمال در ۱۱ فایل: index, post, category, search, login, register, form, user/index, user/profile, user/password, user/posts, user/comments
- ۷۸ متن جایگزین با __t()

---

## ⏳ فاز ۳-A3: ترجمه محتوا (فعلی)

**جداول:**
- content_translations
- translation_versions
- translation_cache
- provider_stats

**فایل‌ها:**
- admin/includes/multi_translator.php
- admin/content_translate.php
- admin/translator_settings.php

**Providers:**

| Provider | وضعیت |
|----------|-------|
| DeepL | ✅ فعال |
| Microsoft | ⏳ آماده |
| Yandex | ⏳ آماده |
| Google | ⚠️ Rate Limit |
| Lingva | ❌ Fail |
| Libre | ❌ Fail |
| MyMemory | ✅ فعال |

**مشکل فعلی:**
MyMemory به جای DeepL انتخاب می‌شود وقتی نمره مساوی است.

**قدم بعدی:**
۱. حل tie-break
۲. تست ترجمه مقاله ۵
۳. تأیید ذخیره

---

## 📋 فازهای باقی‌مانده

### فاز ۳-A4: بازنگری ترجمه‌ها (۲۰ دقیقه)
- admin/content_review.php

### فاز ۳-A5: ترجمه خودکار در انتشار (۱۵ دقیقه)

### فاز ۴: نمایش ترجمه در فرانت (۳۰ دقیقه)
- post.php, index.php, category.php از content_translations بخوانند

### فاز ۵: جدول‌ساز (۴۵ دقیقه)
- admin/table_builder.php

### فاز ۶: اینستالر پیشرفته (۶۰ دقیقه)
- install/index.php

### فاز ۷: تکمیل قالب‌ساز (۳۰ دقیقه)

### فاز ۸: تبلیغات + بنر (۳۰ دقیقه)
- admin/ads.php

### فاز ۹: فروشگاه‌ساز (۲-۴ هفته)
- محصولات، سبد خرید، سفارشات، پرداخت
- زرین‌پال، آیدی‌پی
- حمل و نقل، مالیات، کوپن

### فاز ۱۰: هوش مصنوعی (Ollama)
- admin/ai_assistant.php
- admin/includes/ollama_client.php

### فاز ۱۱: تکمیل آنتی‌ویروس (۴۵ دقیقه)
- admin/full_security_scan.php

---

## 🛠️ اطلاعات فنی

**الگوهای مهم:**
- ترجمه: `<?= __t('key', [], 'پیش‌فرض') ?>`
- HTML: `<html <?= __html_attrs() ?>>`
- Bootstrap: `bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css`

**نکات:**
۱. قبل از تغییر: `cp file.php file.php.bak-$(date +%s)`
۲. کش i18n: `rm logs/i18n_cache/*.json`
۳. تست امنیتی: `admin/security_audit.php`

---

## 🎯 اولویت‌های پیشنهادی

**کوتاه‌مدت:**
۱. حل tie-break در multi_translator.php
۲. تست ترجمه مقاله
۳. نمایش ترجمه در فرانت
۴. بازنگری ترجمه‌ها

**میان‌مدت:**
۵. جدول‌ساز
۶. اینستالر
۷. تکمیل قالب‌ساز
۸. تبلیغات

**بلندمدت:**
۹. فروشگاه‌ساز
۱۰. هوش مصنوعی
۱۱. آنتی‌ویروس

---

## 📞 درخواست ادامه

اگر این فایل را در چت جدید می‌خوانی:
۱. ابتدا مشکل tie-break در multi_translator.php را حل کن
۲. سپس از فاز ۴ ادامه بده

---

**پایان TODO.md**
