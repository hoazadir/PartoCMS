<div align="center">

# 🚀 PartoCMS

**پلتفرم مدیریت محتوای Enterprise با قابلیت‌های Office و AI**

[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![MariaDB](https://img.shields.io/badge/MariaDB-12.3-003545?logo=mariadb&logoColor=white)](https://mariadb.org)
[![License](https://img.shields.io/badge/License-Proprietary-red.svg)](LICENSE)
[![GitHub](https://img.shields.io/badge/GitHub-hoazadir%2FPartoCMS-blue?logo=github)](https://github.com/hoazadir/PartoCMS)

**طراحی و برنامه‌نویسی و مالک:** Hooman Oliaei (هومان اولیایی)

</div>

---

## 📖 درباره پروژه

**PartoCMS** یک سیستم مدیریت محتوای اختصاصی، ماژولار و چندزبانه است که با هدف تبدیل شدن به یک **پلتفرم Enterprise** طراحی شده است. این پروژه شامل قابلیت‌های پیشرفته‌ای مانند:

- 🛡️ **سیستم امنیتی ۱۰ لایه**
- 🌍 **چندزبانگی کامل (۲۰ زبان)**
- 🤖 **ترجمه خودکار محتوا با ۷ Provider**
- 🎨 **قالب‌ساز حرفه‌ای با GrapesJS**
- 🛒 **فروشگاه‌ساز با درگاه‌های پرداخت ایرانی و بین‌المللی**
- 📊 **ماژول Excel حرفه‌ای**
- 🎯 **ماژول PowerPoint حرفه‌ای**
- 🤝 **ماژول CRM**
- 🧠 **دستیار هوش مصنوعی (Ollama)**

---

## 🛠 تکنولوژی‌ها

| بخش | تکنولوژی |
|-----|----------|
| Backend | PHP 8.5 |
| Database | MariaDB 12.3 |
| Frontend | Bootstrap 5.3, Vanilla JS |
| Editor | GrapesJS |
| Charts | Chart.js |
| PDF | dompdf |
| AI | Ollama |
| Translation | DeepL, Microsoft, Google, Yandex, MyMemory |

---

## 📋 پیش‌نیازها

### حداقل
- PHP >= 8.0
- MariaDB >= 10.4 یا MySQL >= 5.7
- Extensions: pdo_mysql, curl, mbstring, openssl, gd, zip, json
- وب‌سرور: Apache/Nginx یا PHP Built-in Server

### پیشنهادی
- PHP 8.5
- MariaDB 12.3
- HTTPS (Let's Encrypt)

---

## 🚀 نصب سریع

### روش ۱: نصب خودکار (توصیه می‌شود)

    git clone https://github.com/hoazadir/PartoCMS.git
    cd PartoCMS
    php -S 127.0.0.1:8080 -t .

سپس در مرورگر: http://localhost:8080/install/

### روش ۲: نصب دستی

    git clone https://github.com/hoazadir/PartoCMS.git
    cd PartoCMS
    cp config.example.php config.php
    nano config.php
    mysql -u root -p grapesjs_cms < database/schema.sql
    php -S 127.0.0.1:8080 -t .

---

## 📂 ساختار پروژه

    PartoCMS/
    ├── admin/                  # پنل مدیریت
    │   ├── includes/           # توابع ادمین
    │   └── *.php               # صفحات ادمین
    ├── ajax/                   # Endpoint های AJAX
    ├── includes/               # هسته اصلی
    │   ├── ModuleManager.php   # مدیریت ماژول‌ها
    │   ├── Security.php        # امنیت
    │   ├── Permissions.php     # دسترسی‌ها
    │   ├── AiAssistant.php     # دستیار AI
    │   └── content_i18n.php    # چندزبانگی محتوا
    ├── modules/                # ماژول‌ها
    │   ├── ai_assistant/       # دستیار هوشمند
    │   ├── content/            # مدیریت محتوا
    │   ├── table_builder/      # جدول‌ساز
    │   └── generated/          # تولیدشده‌ها
    ├── assets/                 # CSS, JS, Images
    ├── libs/                   # کتابخانه‌های خارجی
    ├── vendor/                 # Composer packages
    ├── logs/                   # لاگ‌ها (محرمانه)
    ├── config.php              # تنظیمات اصلی
    ├── index.php               # صفحه اصلی فرانت
    └── README.md               # همین فایل

---

## 🎯 وضعیت پروژه

| فاز | عنوان | وضعیت |
|-----|-------|--------|
| ۱ | سیستم امنیتی ۱۰ لایه | ✅ تکمیل |
| ۲ | مدیریت و گزارش‌ها | ✅ تکمیل |
| ۳-A1 | i18n پایه (۲۰ زبان) | ✅ تکمیل |
| ۳-A2 | ترجمه فرانت‌اند | ✅ تکمیل |
| ۳-A3 | ترجمه محتوا (MultiTranslator) | ✅ تکمیل |
| ۴ | نمایش ترجمه فرانت‌اند | ✅ تکمیل |
| ۳-A4 | بازنگری ترجمه‌ها | ⏳ بعدی |
| ۳-A5 | ترجمه خودکار در انتشار | ⏳ |
| ۵ | جدول‌ساز | ⏳ |
| ۶ | اینستالر پیشرفته | ⏳ |
| ۷ | قالب‌ساز + ۶۰ تمپلیت | ⏳ |
| ۸ | تبلیغات | ⏳ |
| ۹ | فروشگاه‌ساز | ⏳ |
| ۱۰ | دستیار هوش مصنوعی | ⏳ |
| ۱۲ | ماژول Excel | ⏳ |
| ۱۳ | ماژول PowerPoint | ⏳ |
| ۱۴ | ماژول CRM | ⏳ |
| ۱۵ | سیستم لایسنس | ⏳ |
| ۱۶ | مستندات کامل | ⏳ |

برای جزئیات کامل، فایل TODO.md را ببینید.

---

## 🌍 زبان‌های پشتیبانی‌شده (۲۰ زبان)

fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN, ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL

---

## 📊 آمار پروژه

- فایل‌های PHP: ۷۱۰+
- جدول‌های دیتابیس: ۴۸+
- زبان‌های فعال: ۲۰
- Provider های ترجمه: ۷
- ماژول‌ها: ۴+
- حجم پروژه: 79 MB

---

## 🤝 مشارکت

این یک پروژه اختصاصی است. برای مشارکت:

1. Fork کنید
2. Branch جدید بسازید: git checkout -b feature/AmazingFeature
3. Commit کنید: git commit -m 'Add AmazingFeature'
4. Push کنید: git push origin feature/AmazingFeature
5. Pull Request ارسال کنید

---

## 📝 لایسنس

© 2026 **Hooman Oliaei** (هومان اولیایی). تمامی حقوق محفوظ است.

این پروژه اختصاصی است و استفاده تجاری از آن نیازمند مجوز کتبی از مالک است.

---

## 📞 تماس

- **GitHub:** [@hoazadir](https://github.com/hoazadir)
- **پروژه:** [PartoCMS](https://github.com/hoazadir/PartoCMS)

---

<div align="center">

**⭐ اگر این پروژه برایتان مفید بود، ستاره بدهید! ⭐**

Made with ❤️ by **Hooman Oliaei**

</div>
