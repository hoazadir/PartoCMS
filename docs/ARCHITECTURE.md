# 🏗️ PartoCMS Architecture

> مستندات معماری فنی پروژه PartoCMS
> آخرین بروزرسانی: 2026-09-27

---

## فهرست مطالب

- معرفی کلی
- تکنولوژی‌ها
- ساختار پوشه‌ها
- فایل‌های هسته اصلی
- ماژول‌ها
- جریان درخواست
- لایه‌های امنیتی
- دیتابیس
- چندزبانگی
- SEO
- قالب‌بندی

---

## معرفی کلی

PartoCMS یک سیستم مدیریت محتوای اختصاصی، ماژولار و چندزبانه است که با PHP 8.5 و MariaDB توسعه یافته است.

### ویژگی‌های کلیدی

- معماری ماژولار
- سیستم امنیتی ۱۰ لایه
- چندزبانگی کامل (۲۰ زبان)
- ترجمه خودکار محتوا (۷ Provider)
- قالب‌ساز GrapesJS
- دستیار هوش مصنوعی

---

## تکنولوژی‌ها

| بخش | تکنولوژی |
|-----|----------|
| Backend | PHP 8.5 |
| Database | MariaDB 12.3 |
| Frontend | Bootstrap 5.3 |
| Editor | GrapesJS |
| Charts | Chart.js |
| PDF | dompdf |
| AI | Ollama |

---

## ساختار پوشه‌ها

PartoCMS/
├── admin/           پنل مدیریت (84 فایل)
├── ajax/            Endpointهای AJAX (8 فایل)
├── api/             API عمومی (9 فایل)
├── assets/          CSS, JS, Images
├── backups/         فایل‌های backup
├── cron/            Cron Jobs
├── database/        فایل‌های SQL
├── docs/            مستندات
├── includes/        هسته اصلی (9 فایل کلیدی)
├── libs/            کتابخانه‌های خارجی
├── logs/            فایل‌های لاگ
├── modules/         ماژول‌ها
├── uploads/         فایل‌های آپلودی
├── user/            پنل کاربر
└── vendor/          Composer packages

---

## فایل‌های هسته اصلی

### includes/AiAssistant.php

مدیریت دستیار هوشمند مبتنی بر Ollama

- 526 خط کد
- 18983 بایت
- ارتباط با Ollama API
- مدیریت مکالمات

### includes/Backup.php

مدیریت پشتیبان‌گیری

- 483 خط کد
- 15135 بایت
- Backup خودکار
- Clean Old Backups

### includes/MenuManager.php

مدیریت منوها

- 175 خط کد
- 5510 بایت

### includes/ModuleManager.php

مدیریت ماژول‌ها

- 295 خط کد
- 9450 بایت
- نصب/حذف/فعال‌سازی

### includes/Permissions.php

مدیریت دسترسی‌ها

- 75 خط کد
- 2865 بایت

### includes/Security.php

توابع امنیتی

- 234 خط کد
- 7755 بایت
- Permissions-Policy
- CSRF Token

### includes/Seo.php

مدیریت SEO

- 198 خط کد
- 8359 بایت
- متاتگ‌ها
- Sitemap و robots
- Open Graph

### includes/TemplateRenderer.php

رندر قالب‌ها

- 440 خط کد
- 18385 بایت

### includes/content_i18n.php

چندزبانگی محتوا

- 361 خط کد
- 13173 بایت

---

## ماژول‌ها

### modules/ai_assistant/

دستیار هوشمند مبتنی بر Ollama

- manifest.json ✓
- chat.php
- recorder.php
- module.json

### modules/content/

مدیریت محتوا

- admin.php
- diag.php
- install.php
- menu.php

### modules/generated/

ماژول‌های تولیدشده

- contacts/ (نمونه)

### modules/table_builder/

جدول‌ساز

- admin.php
- create.php
- data.php
- export.php
- includes/ (TableBuilder, CrudGenerator, FormBuilder, ModuleAnalyzer)

---

## جریان درخواست

مرورگر
   ↓
Nginx / Apache
   ↓
index.php یا admin/index.php
   ↓
config.php (تنظیمات)
   ↓
auth_check.php (احراز هویت)
   ↓
ModuleManager (بارگذاری ماژول)
   ↓
TemplateRenderer / Seo
   ↓
پاسخ HTML

---

## لایه‌های امنیتی

1. PHP Hardening
2. File Integrity Monitoring (FIM)
3. Malware Pattern Scanner
4. Telegram Alerts
5. VirusTotal Scanner
6. Rate Limiting
7. Two-Factor Auth (TOTP)
8. Security Audit
9. CSRF Protection
10. Permissions-Policy

---

## دیتابیس

### جداول اصلی (48 جدول)

**کاربران و دسترسی:**
- users
- roles
- permissions
- role_permissions
- role_modules
- user_preferences
- login_attempts
- login_blocks
- user_2fa
- user_2fa_backup

**محتوا:**
- content_items
- content_types
- content_tags
- content_translations
- categories
- category_translations
- tags
- comments
- views
- media

**فرم‌ها:**
- forms
- form_submissions
- contacts

**منوها:**
- menus
- menu_items

**چندزبانگی:**
- languages
- translations
- translation_keys
- translation_cache
- translation_queue
- translation_versions
- provider_stats

**SEO:**
- seo_settings

**امنیت:**
- security_logs
- file_hashes
- rate_limits
- activity_log

**ماژول‌ها:**
- modules
- module_files

**AI:**
- ai_conversations
- ai_messages

**قالب‌ها:**
- templates

**پشتیبان:**
- backups
- backup_settings

**تنظیمات:**
- settings

**سایر:**
- projects

---

## چندزبانگی

### سیستم i18n

- admin/includes/i18n.php
- includes/content_i18n.php
- admin/includes/language_switcher.php
- admin/languages.php
- admin/translations.php

### ۲۰ زبان فعال

fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN, ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL

### ترجمه خودکار

MultiTranslator v6.1 با ۷ Provider:
- DeepL
- Microsoft
- Yandex
- Google
- Lingva
- Libre
- MyMemory

---

## SEO

### includes/Seo.php

- متاتگ‌های پایه (title, description, keywords, canonical, robots)
- Open Graph (og:title, og:description, og:image, og:url, og:type, og:site_name)
- Twitter Cards
- Google Analytics
- Sitemap داینامیک
- Robots داینامیک

### فایل‌های SEO

- robots.txt (فیزیکی)
- robots.php (داینامیک)
- sitemap.php (داینامیک)
- admin/seo.php (پنل ادمین)

---

## قالب‌بندی

### GrapesJS

- admin/editor.php (ویرایشگر)
- admin/templates.php (مدیریت قالب)
- libs/grapes*.js
- api/templates.php
- api/load.php
- api/store.php
- api/upload-editor.php
- api/export-zip.php

### TemplateRenderer

- includes/TemplateRenderer.php (440 خط)
- رندر قالب‌های داینامیک

---

## لایه‌های ماژولار

1. هسته اصلی (includes/)
2. ماژول‌ها (modules/)
3. پنل ادمین (admin/)
4. API (api/)
5. AJAX (ajax/)
6. فرانت‌اند (index.php, post.php, category.php)

---

## لایسنس

© 2026 Hooman Oliaei (هومان اولیایی)
تمام حقوق محفوظ است.

---

آخرین بروزرسانی: 2026-09-27
