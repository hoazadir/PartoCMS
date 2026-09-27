# 🔌 PartoCMS API

> مستندات API داخلی PartoCMS
> آخرین بروزرسانی: 2026-09-27

---

## فهرست مطالب

- معرفی کلی
- API عمومی (api/)
- AJAX Endpoints (ajax/)
- فرمت درخواست
- فرمت پاسخ
- احراز هویت
- محدودیت نرخ

---

## معرفی کلی

PartoCMS دارای دو نوع API است:

1. **API عمومی (api/)** - برای دسترسی خارجی
2. **AJAX Endpoints (ajax/)** - برای پنل ادمین و فرانت‌اند

---

## API عمومی (api/)

### api/comment.php

ارسال دیدگاه

- روش: POST
- پارامترها:
  - content_id (int) - شناسه مقاله
  - name (string) - نام
  - email (string) - ایمیل
  - comment (text) - متن دیدگاه
- پاسخ: ok true، message "دیدگاه ارسال شد"

### api/form-submit.php

ارسال فرم

- روش: POST
- پارامترها:
  - form_id (int) - شناسه فرم
  - data (array) - داده‌های فرم

### api/load.php

بارگذاری قالب

- روش: GET
- پارامترها:
  - id (int) - شناسه قالب

### api/store.php

ذخیره قالب

- روش: POST
- پارامترها:
  - name (string) - نام قالب
  - html (text) - HTML
  - css (text) - CSS

### api/templates.php

مدیریت قالب‌ها

- روش: GET, POST, DELETE

### api/upload-editor.php

آپلود فایل ویرایشگر

- روش: POST
- پارامترها:
  - file (file) - فایل

### api/export-zip.php

صادرات ZIP

- روش: GET
- پارامترها:
  - template_id (int) - شناسه قالب

### api/reports.php

گزارش‌ها

- روش: GET

---

## AJAX Endpoints (ajax/)

### ajax/ai_chat.php

چت با AI

- روش: POST
- پارامترها:
  - message (string) - پیام کاربر
  - conversation_id (int) - شناسه مکالمه
- پاسخ: ok، response، conversation_id

### ajax/ai_transcribe.php

تبدیل صدا به متن

- روش: POST
- پارامترها:
  - audio (file) - فایل صوتی
  - language (string) - زبان
- پاسخ: ok، text، language

### ajax/ai_analyze.php

تحلیل امنیتی

- روش: POST
- پارامترها:
  - file (string) - مسیر فایل

### ajax/ai_stt_pull_start.php

شروع دانلود مدل STT

- روش: POST

### ajax/ai_stt_pull_status.php

وضعیت دانلود مدل STT

- روش: GET

### ajax/ai_pull_stream.php

جریان دانلود

- روش: GET

### ajax/test_pull.php

تست دانلود

- روش: GET

---

## فرمت درخواست

### Headers

Content-Type: application/json

### Body

{
  "param1": "value1",
  "param2": "value2"
}

### برای آپلود فایل

Content-Type: multipart/form-data

---

## فرمت پاسخ

### موفق

{
  "ok": true,
  "data": {...},
  "message": "عملیات موفق"
}

### خطا

{
  "ok": false,
  "error": "پیام خطا",
  "code": 400
}

---

## احراز هویت

### برای ادمین

تمام Endpointهای ajax/ نیاز به Session معتبر دارند.

### برای عمومی

Endpointهای api/ معمولاً عمومی هستند اما بعضی نیاز به احراز هویت دارند.

### Session

session_start();

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

---

## محدودیت نرخ

### Rate Limiting

- حداکثر 60 درخواست در دقیقه
- بر اساس IP و User ID

### CSRF Protection

تمام درخواست‌های POST نیاز به CSRF Token دارند.

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

---

## کدهای وضعیت HTTP

| کد | معنی |
|----|------|
| 200 | موفق |
| 201 | ایجاد شد |
| 400 | درخواست نامعتبر |
| 401 | احراز هویت لازم |
| 403 | دسترسی ممنوع |
| 404 | یافت نشد |
| 429 | محدودیت نرخ |
| 500 | خطای سرور |

---

## نمونه استفاده

### با curl

curl -X POST http://localhost:8080/ajax/ai_chat.php -H "Content-Type: application/json" -d '{"message": "سلام", "conversation_id": 0}'

### با JavaScript

fetch('/ajax/ai_chat.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        message: 'سلام',
        conversation_id: 0
    })
})
.then(r => r.json())
.then(data => console.log(data));

---

## لایسنس

© 2026 Hooman Oliaei (هومان اولیایی)

---

آخرین بروزرسانی: 2026-09-27
