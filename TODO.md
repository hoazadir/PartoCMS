# PartoCMS TODO

> آخرین بروزرسانی: 2026-09-14

## ✅ تکمیل‌شده

### فاز ۱: امنیت ۱۰ لایه
- FIM, Malware Scanner, Telegram Alerts
- VirusTotal, Rate Limiting, 2FA/TOTP
- Security Dashboard, Security Audit, Backup Manager
- Security Graphs (Chart.js)
- Cron هر ۶ ساعت + گزارش هفتگی

جداول: file_hashes, security_logs, login_attempts, login_blocks, user_2fa, user_2fa_backup

### فاز ۲: مدیریت
- Backup خودکار gzip + Clean old
- گزارش هفتگی HTML ایمیل
- نمودارهای Chart.js

### فاز ۳-A1: i18n پایه
- ۲۰ زبان: fa-IR, en-US, en-GB, ar-SA, tr-TR, de-DE, fr-FR, es-ES, ru-RU, zh-CN, ja-JP, ko-KR, it-IT, pt-BR, nl-NL, pl-PL, hi-IN, id-ID, vi-VN, he-IL
- کلاس I18n + کش فایل + Modal انتخاب زبان
- صفحات: languages.php, translations.php
- توابع: getI18n(), __t(), __html_attrs(), __dir()
- RTL/LTR خودکار در ۴۷ فایل

### فاز ۳-A2: ترجمه فرانت
- ۳۴ کلید × ۲۰ زبان = ۶۸۰ ترجمه
- اعمال در ۱۱ فایل: index, post, category, search, login, register, form, user/*

## ⏳ در حال انجام

### فاز ۳-A3: ترجمه محتوا

جداول: content_translations, translation_versions, translation_cache, provider_stats

فایل‌ها:
- admin/includes/multi_translator.php (اصلی)
- admin/includes/content_translator.php (قدیمی)
- admin/content_translate.php
- admin/translator_settings.php

Providers:
- DeepL ✅ (key فعال، 500K/ماه)
- Microsoft ⏳ (بدون key)
- Yandex ⏳ (بدون key)
- Google ⚠️ (rate limit)
- Lingva ❌ (fail)
- Libre ❌ (fail)
- MyMemory ✅ (کیفیت پایین)

🐛 مشکل فعلی: MyMemory به جای DeepL انتخاب می‌شود

راه‌حل: وزن‌دهی به score در v6

## 📋 باقی‌مانده

- فاز ۳-A4: بازنگری ترجمه‌ها (content_review.php)
- فاز ۳-A5: ترجمه خودکار در publish
- فاز ۴: نمایش ترجمه در فرانت (post.php, index.php)
- فاز ۵: جدول‌ساز (table_builder.php)
- فاز ۶: اینستالر (install/index.php)
- فاز ۷: تکمیل قالب‌ساز
- فاز ۸: تبلیغات + بنر (ads.php)
- فاز ۹: فروشگاه‌ساز (محصولات، سبد، سفارش، زرین‌پال)
- فاز ۱۰: هوش مصنوعی Ollama
- فاز ۱۱: آنتی‌ویروس کامل

## 🛠️ اطلاعات فنی

- PHP 8.5, MariaDB 12.3
- DB: grapesjs_cms
- Server: php -S 127.0.0.1:8080
- Root: ~/PartoCMS/

الگوها:
- ترجمه: <?= __t('key', [], 'پیش‌فرض') ?>
- HTML: <html <?= __html_attrs() ?>>
- Bootstrap: bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css

## 🎯 اولویت‌ها

۱. حل tie-break در multi_translator.php
۲. تست ترجمه مقاله ۵
۳. نمایش ترجمه در فرانت
۴. جدول‌ساز
۵. اینستالر

## 📞 برای چت جدید

این فایل رو کامل بخون، از «حل tie-break» شروع کن.
