# 📧 ماژول خبرنامه (Newsletter)

ماژول حرفه‌ای خبرنامه برای PartoCMS با معماری **DDD** (Domain-Driven Design)، اصول **SOLID**، و **PSR-4** autoloading.

---

## فهرست

1. [معرفی](#معرفی)
2. [ویژگی‌ها](#ویژگیها)
3. [پیش‌نیازها](#پیشنیازها)
4. [نصب](#نصب)
5. [معماری](#معماری)
6. [Use Cases](#use-cases)
7. [Shortcodeها](#shortcodeها)
8. [AJAX Endpoints](#ajax-endpoints)
9. [تنظیمات SMTP](#تنظیمات-smtp)
10. [ساختار دیتابیس](#ساختار-دیتابیس)
11. [تست](#تست)
12. [مجوز](#مجوز)

---

## معرفی

ماژول خبرنامه امکان مدیریت مشترکین، ساخت کمپین‌ها، ارسال ایمیل گروهی، و ردیابی باز شدن را فراهم می‌کند. طراحی آن بر پایه **Clean Architecture** با لایه‌های جدا انجام شده:

- **Domain** — منطق کسب‌وکار خالص (بدون وابستگی به DB/HTTP)
- **Infrastructure** — پیاده‌سازی‌های فنی (PDO, SMTP)
- **Application** — سرویس‌ها (Use Cases)
- **Admin UI** — لایه نمایش (فقط ادمین)
- **Widget/Shortcode** — لایه فرانت‌اند (فرم ثبت‌نام)

---

## ویژگی‌ها

- ✅ ثبت‌نام دو مرحله‌ای (Double Opt-In)
- ✅ مدیریت مشترکین با فیلتر و جستجو
- ✅ ساخت و ویرایش کمپین (TinyMCE editor)
- ✅ ارسال گروهی (Batch Worker)
- ✅ ردیابی باز شدن ایمیل (Open Tracking)
- ✅ لغو عضویت یک‌کلیکی
- ✅ SMTP قابل تنظیم از پنل ادمین (PHPMailer)
- ✅ ویجت و Shortcode برای فرانت‌اند
- ✅ قالب‌های ایمیل RTL (Confirm, Welcome, Unsubscribe)
- ✅ Value Objectهای امن (`Email`, `SubscriberStatus`, ...)
- ✅ Repository pattern با interface

---

## پیش‌نیازها

| مورد | نسخه |
|------|------|
| PHP | 8.1 یا بالاتر |
| PartoCMS | 1.0.0 یا بالاتر |
| MariaDB / MySQL | 10.4+ |
| PHPMailer | 6.9+ (در `vendor/phpmailer`) |
---

## نصب

### ۱. فعال‌سازی ماژول

ماژول از طریق جدول modules در دیتابیس شناسایی می‌شود:

    INSERT INTO modules (slug, name, description, version, is_enabled, is_core, icon, menu_group, sort_order)
    VALUES ('newsletter', 'خبرنامه', 'سیستم خبرنامه حرفه‌ای', '1.0.0', 1, 0, '📧', 'marketing', 50);

### ۲. اجرای schema

از ریشه پروژه:

    mariadb -u root -p grapesjs_cms < modules/newsletter/database/schema.sql

سه جدول ساخته می‌شود: newsletter_subscribers، newsletter_campaigns، newsletter_sends.

### ۳. تنظیم SMTP

از پنل ادمین → خبرنامه → تنظیمات مقادیر SMTP را وارد کن:

- SMTP Host (مثلاً smtp.gmail.com)
- SMTP Port (587 برای TLS، 465 برای SSL)
- SMTP User / Password
- From Name / From Email
---

## معماری

### ساختار پوشه‌ها

    modules/newsletter/
    ├── manifest.json               # متادیتای ماژول
    ├── README.md                   # این فایل
    ├── bootstrap.php               # راه‌انداز + autoloader
    ├── config.php                  # تنظیمات ماژول
    │
    ├── database/
    │   └── schema.sql              # ساختار سه جدول
    │
    ├── src/
    │   ├── Support/
    │   │   ├── Container.php       # DI container
    │   │   └── TokenGenerator.php  # توکن امن
    │   │
    │   ├── Domain/
    │   │   ├── ValueObject/        # Email, SubscriberStatus, CampaignStatus, SendStatus
    │   │   ├── Entity/             # Subscriber, Campaign, SendLog
    │   │   ├── Repository/         # 3 interface
    │   │   ├── Service/            # SubscriptionService, CampaignService, SendService
    │   │   └── Exception/          # InvalidEmailException, CampaignException
    │   │
    │   ├── Infrastructure/
    │   │   ├── Persistence/        # 3 PDO Repository
    │   │   └── Mailer/             # MailerInterface, SmtpMailer, MailerFactory
    │   │
    │   ├── Mailer/                 # SubscriptionMailer (lifecycle emails)
    │   │
    │   └── Widget/                 # NewsletterWidget, Shortcode
    │
    ├── admin/                      # پنل ادمین (۸ صفحه)
    │   ├── _bootstrap.php
    │   ├── index.php               # مشترکین
    │   ├── subscriber-edit.php
    │   ├── campaigns.php
    │   ├── campaign-edit.php       # با TinyMCE
    │   ├── campaign-send.php       # ارسال + progress
    │   ├── campaign-delete.php
    │   └── settings.php
    │
    ├── ajax/
    │   ├── subscribe.php
    │   ├── confirm.php
    │   ├── unsubscribe.php
    │   └── track-open.php
    │
    └── templates/
        └── email/
            ├── layout.php
            ├── confirm.php
            ├── welcome.php
            └── unsubscribe-confirm.php

### لایه‌بندی

- **Domain** — قواعد کسب‌وکار، Entityها، Value Objectها (بدون وابستگی)
- **Domain.Service** — Use Caseها (فقط به Repository interface وابسته)
- **Infrastructure.Persistence** — ذخیره‌سازی PDO
- **Infrastructure.Mailer** — ارسال ایمیل (PHPMailer)
- **Admin / Ajax** — لایه نمایش / API (به Serviceها وابسته)

---

## Use Cases

### SubscriptionService

- `subscribe(Email, name, source, ip, ua)` — ثبت‌نام جدید + ارسال ایمیل تأیید
- `confirm(token)` — تأیید عضویت (double opt-in)
- `unsubscribe(token)` — لغو عضویت
- `updateProfile(...)` — ویرایش پروفایل مشترک
- `listSubscribers(page, perPage, filters)` — لیست با فیلتر
- `getStats()` — آمار کلی

### CampaignService

- `createCampaign(...)` — ساخت کمپین جدید
- `scheduleCampaign(id, when)` — زمان‌بندی
- `startCampaign(id)` — شروع — صف‌بندی برای همه مشترکین فعال
- `pauseCampaign(id)` / `resumeCampaign(id)` — توقف / ادامه
- `completeCampaign(id)` — اتمام دستی
- `listCampaigns(page, perPage, filters)` — لیست با فیلتر
- `getGlobalStats()` — آمار سراسری

### SendService (Worker)

- `processQueue(campaignId, batchSize)` — پردازش دسته‌ای صف ارسال
- `retryFailed(campaignId)` — تلاش مجدد برای ناموفق‌ها

---

## Shortcodeها

در محتوای صفحات/نوشته‌ها قابل استفاده:

- `[newsletter]` — فرم کامل عضویت
- `[newsletter_form]` — فقط فرم
- `[newsletter_success]` — پیام موفقیت

مثال:

    متن مقدمه ...

    [newsletter]

    با تشکر.

---

## AJAX Endpoints

- **subscribe.php** — POST با `email`, `name` — ثبت‌نام جدید
- **confirm.php** — GET با `token` — تأیید عضویت
- **unsubscribe.php** — GET با `token` — لغو عضویت
- **track-open.php** — GET با `send_id` — ردیابی باز شدن (pixel 1×1)

---

## تنظیمات SMTP

مقادیر در جدول settings ذخیره می‌شوند:

- `newsletter_smtp_host` — مثلاً smtp.gmail.com
- `newsletter_smtp_port` — 587 برای TLS، 465 برای SSL
- `newsletter_smtp_user` — مثلاً user@gmail.com
- `newsletter_smtp_pass` — رمز یا App Password
- `newsletter_smtp_secure` — tls یا ssl
- `newsletter_from_name` — نام فرستنده
- `newsletter_from_email` — ایمیل فرستنده

**توجه:** برای Gmail باید App Password بسازی (نه رمز اصلی).

---

## ساختار دیتابیس

- **newsletter_subscribers** — مشترکین (۱۳ ستون): email، name، status، token، source، ip، user_agent، created_at، ...
- **newsletter_campaigns** — کمپین‌ها (۱۷ ستون): title، subject، body_html، status، scheduled_at، sent_count، failed_count، open_count، ...
- **newsletter_sends** — صف ارسال (۱۰ ستون): campaign_id، subscriber_id، status، sent_at، opened_at، error، ...
