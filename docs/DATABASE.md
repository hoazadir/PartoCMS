# 🗄️ PartoCMS Database

> مستندات پایگاه داده PartoCMS
> آخرین بروزرسانی: 2026-09-27

---

## فهرست مطالب

- معرفی کلی
- آمار دیتابیس
- جداول کاربران و دسترسی‌ها
- جداول محتوا
- جداول فرم‌ها
- جداول منوها
- جداول چندزبانگی
- جداول SEO
- جداول امنیت
- جداول ماژول‌ها
- جداول AI
- جداول قالب‌ها
- جداول پشتیبان
- جداول تنظیمات

---

## معرفی کلی

**نام دیتابیس:** grapesjs_cms
**تعداد جداول:** 48
**حجم دیتابیس:** ~15.8 MB

---

## آمار دیتابیس

| مورد | مقدار |
|------|-------|
| تعداد جداول | 48 |
| حجم کل | 15.8 MB |
| Charset | utf8mb4 |
| Collation | utf8mb4_unicode_ci |
| Engine | InnoDB |

---

## جداول کاربران و دسترسی‌ها

### users

اطلاعات کاربران

| ستون | نوع | توضیح |
|------|-----|-------|
| id | INT | شناسه |
| username | VARCHAR(100) | نام کاربری |
| email | VARCHAR(255) | ایمیل |
| password | VARCHAR(255) | رمز (Hash) |
| role | VARCHAR(50) | نقش |
| created_at | TIMESTAMP | تاریخ ایجاد |

### roles

نقش‌های کاربری

### permissions

دسترسی‌های مجاز

### role_permissions

ارتباط نقش‌ها و دسترسی‌ها

### role_modules

ارتباط نقش‌ها و ماژول‌ها

### user_preferences

تنظیمات کاربران

### login_attempts

تلاش‌های ورود

### login_blocks

بلاک‌های ورود

### user_2fa

تنظیمات 2FA

### user_2fa_backup

کدهای پشتیبان 2FA

---

## جداول محتوا

### content_items

مقالات و محتوای اصلی

### content_types

انواع محتوا

### content_tags

ارتباط محتوا و برچسب‌ها

### content_translations

ترجمه‌های محتوا

### categories

دسته‌بندی‌ها

### category_translations

ترجمه دسته‌بندی‌ها

### tags

برچسب‌ها

### comments

دیدگاه‌ها

### views

آمار بازدید

### media

فایل‌های رسانه‌ای

---

## جداول فرم‌ها

### forms

فرم‌های سفارشی

### form_submissions

ارسال‌های فرم

### contacts

مخاطبین

---

## جداول منوها

### menus

منوهای اصلی

### menu_items

آیتم‌های منو

---

## جداول چندزبانگی

### languages

۲۰ زبان فعال

### translations

کلیدهای ترجمه

### translation_keys

کلیدهای ترجمه (Legacy)

### translation_cache

کش ترجمه‌ها

### translation_queue

صف ترجمه

### translation_versions

نسخه‌های ترجمه

### provider_stats

آمار Providerهای ترجمه

---

## جداول SEO

### seo_settings

تنظیمات SEO

- default_meta_title
- default_meta_description
- default_meta_keywords
- google_analytics_id
- robots_txt
- twitter_handle
- og_site_name

---

## جداول امنیت

### security_logs

لاگ‌های امنیتی

### file_hashes

هش فایل‌ها (FIM)

### rate_limits

محدودیت نرخ

### activity_log

فعالیت‌های کاربران

---

## جداول ماژول‌ها

### modules

ماژول‌های نصب‌شده

### module_files

فایل‌های ماژول

---

## جداول AI

### ai_conversations

مکالمات AI

### ai_messages

پیام‌های AI

---

## جداول قالب‌ها

### templates

قالب‌ها

---

## جداول پشتیبان

### backups

فایل‌های backup

### backup_settings

تنظیمات backup

---

## جداول تنظیمات

### settings

تنظیمات کلی سیستم

---

## جداول عجیب (نیاز به بررسی)

### TestDb

باقی‌مانده تست - باید حذف شود

### utyfi

نام عجیب - باید حذف شود

### projects

نامشخص - باید بررسی شود

---

## روابط کلیدی

- users → roles (Many-to-One)
- content_items → categories (Many-to-One)
- content_items → users (Many-to-One)
- comments → content_items (Many-to-One)
- menus → menu_items (One-to-Many)
- modules → module_files (One-to-Many)

---

## Charset و Collation

تمام جداول از utf8mb4 استفاده می‌کنند:
- پشتیبانی از زبان‌های مختلف (فارسی، عربی، چینی، ...)
- پشتیبانی از Emoji
- Collation: utf8mb4_unicode_ci

---

## Engine

تمام جداول از InnoDB استفاده می‌کنند:
- پشتیبانی از Foreign Key
- پشتیبانی از Transaction
- پشتیبانی از Row-level Locking

---

## لایسنس

© 2026 Hooman Oliaei (هومان اولیایی)

---

آخرین بروزرسانی: 2026-09-27
