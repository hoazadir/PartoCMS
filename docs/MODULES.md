# 📦 PartoCMS Modules

> مستندات ماژول‌های PartoCMS
> آخرین بروزرسانی: 2026-09-27

---

## فهرست مطالب

- معرفی کلی
- ساختار ماژول
- manifest.json
- ModuleManager
- ماژول ai_assistant
- ماژول content
- ماژول generated
- ماژول table_builder
- ساخت ماژول جدید

---

## معرفی کلی

PartoCMS یک سیستم ماژولار است که هر قابلیت در یک ماژول مستقل قرار می‌گیرد.

### ماژول‌های نصب‌شده

| ماژول | تعداد فایل | manifest | وضعیت |
|-------|-----------|----------|-------|
| ai_assistant | 6 | ✅ | فعال |
| content | 5 | ❌ | نیاز به manifest |
| generated | 7 | ❌ | نیاز به manifest |
| table_builder | 12 | ❌ | نیاز به manifest |

---

## ساختار ماژول

هر ماژول باید این ساختار را داشته باشد:

modules/MODULE_NAME/
├── manifest.json      (اجباری - شناسنامه)
├── index.php          (اجباری - نقطه ورود)
├── admin/             (اختیاری - صفحات ادمین)
│   └── index.php
├── includes/          (اختیاری - کلاس‌های اصلی)
│   └── MODULE_NAME.php
├── assets/            (اختیاری)
│   ├── css/
│   └── js/
├── templates/         (اختیاری)
├── database/          (اختیاری)
│   └── migrations/
└── config.json        (اختیاری - تنظیمات)

---

## manifest.json

manifest.json شناسنامه ماژول است و شامل این فیلدها:

{
  "name": "نام ماژول",
  "slug": "نام کوتاه",
  "version": "1.0.0",
  "description": "توضیحات",
  "author": "نام نویسنده",
  "author_url": "https://example.com",
  "license": "MIT",
  "requires": {
    "php": ">=8.0",
    "partocms": ">=1.0.0"
  },
  "permissions": [
    "module.view",
    "module.create",
    "module.edit",
    "module.delete"
  ],
  "menu": {
    "title": "عنوان منو",
    "icon": "bi-icon",
    "url": "/modules/slug/index.php",
    "order": 10,
    "parent": "content"
  },
  "routes": {
    "/slug": "index.php"
  },
  "database": {
    "tables": ["table1", "table2"],
    "migrations": "database/migrations/"
  },
  "settings": {
    "enabled": true,
    "auto_update": false
  }
}

---

## ModuleManager

کلاس ModuleManager در includes/ModuleManager.php مسئول مدیریت ماژول‌ها است.

### توابع اصلی

- getAll() - دریافت تمام ماژول‌ها
- getBySlug($slug) - دریافت ماژول با slug
- install($slug) - نصب ماژول
- uninstall($slug) - حذف ماژول
- activate($slug) - فعال‌سازی
- deactivate($slug) - غیرفعال‌سازی
- canCurrentUserAccess($slug) - بررسی دسترسی

### نمونه استفاده

$manager = new ModuleManager($pdo);

$modules = $manager->getAll();

if ($manager->canCurrentUserAccess('ai_assistant')) {
    // کاربر دسترسی دارد
}

---

## ماژول ai_assistant

### اطلاعات

- نام: دستیار هوشمند
- slug: ai_assistant
- نسخه: 1.0.0
- manifest: دارد

### فایل‌ها

- chat.php (صفحه چت)
- recorder.php (ضبط صدا)
- module.json
- manifest.json

### قابلیت‌ها

- چت با AI (مبتنی بر Ollama)
- تبدیل صدا به متن (Whisper)
- تاریخچه مکالمات
- پشتیبانی از چند زبان

### جداول دیتابیس

- ai_conversations
- ai_messages

---

## ماژول content

### اطلاعات

- نام: مدیریت محتوا
- slug: content
- نسخه: 1.0.0
- manifest: ندارد (نیاز به ساخت)

### فایل‌ها

- admin.php (پنل مدیریت)
- diag.php (تشخیص)
- install.php (نصب)
- menu.php (منو)

### قابلیت‌ها

- مدیریت مقالات
- دسته‌بندی
- برچسب‌ها
- ترجمه محتوا

---

## ماژول generated

### اطلاعات

- نام: ماژول‌های تولیدشده
- slug: generated
- نسخه: 1.0.0
- manifest: ندارد

### فایل‌ها

- contacts/ (نمونه ماژول تولیدشده)
  - admin.php
  - config.json
  - form.php
  - helpers.php
  - list.php
  - module.json
  - view.php

### قابلیت‌ها

- ماژول‌های تولیدشده به صورت خودکار
- CRUD کامل
- فرم‌های داینامیک

---

## ماژول table_builder

### اطلاعات

- نام: جدول‌ساز
- slug: table_builder
- نسخه: 1.0.0
- manifest: ندارد

### فایل‌ها

- admin.php (پنل مدیریت)
- create.php (ساخت جدول)
- data.php (داده‌ها)
- export.php (صادرات)
- row_edit.php (ویرایش ردیف)
- row_save.php (ذخیره ردیف)
- to_module.php (تبدیل به ماژول)
- includes/
  - TableBuilder.php
  - CrudGenerator.php
  - FormBuilder.php
  - ModuleAnalyzer.php

### قابلیت‌ها

- ساخت جدول بدون SQL
- CRUD خودکار
- صادرات SQL/JSON/CSV
- تبدیل جدول به ماژول

---

## ساخت ماژول جدید

### مرحله ۱: ساخت پوشه

mkdir -p modules/my_module/{admin,includes,assets/{css,js}}

### مرحله ۲: ساخت manifest.json

nano modules/my_module/manifest.json

### مرحله ۳: ساخت index.php

nano modules/my_module/index.php

### مرحله ۴: ساخت admin/index.php

nano modules/my_module/admin/index.php

### مرحله ۵: ساخت includes/MyModule.php

nano modules/my_module/includes/MyModule.php

### مرحله ۶: ثبت در دیتابیس

INSERT INTO modules (slug, name, version, is_active)
VALUES ('my_module', 'My Module', '1.0.0', 1);

---

## لایسنس

© 2026 Hooman Oliaei (هومان اولیایی)

---

آخرین بروزرسانی: 2026-09-27
