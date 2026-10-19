# Team Module

ماژول تیم ما برای PartoCMS.

- نسخه: 1.0.0
- نویسنده: Hooman Oliaei
- لایسنس: Proprietary

## ویژگی‌ها

- ثبت عضو از پنل ادمین
- عکس (URL) با fallback حرف اول
- بیوگرافی + سمت + دپارتمان
- شبکه‌های اجتماعی
- وضعیت (published/hidden)
- Featured + sort_order + language
- Bulk operations
- شورت‌کد [team] با ۸ attribute
- ویجت grid/slider + CSS responsive

## نصب

DB:
    mariadb -u root -p grapesjs_cms < modules/team/database/schema.sql

## شورت‌کدها

    [team]                    -> همه
    [team limit="6"]         -> ۶ عضو
    [team style="slider"]    -> اسلایدر
    [team columns="2"]       -> ۲ ستون
    [team department="فنی"]  -> فیلتر
    [team featured="true"]   -> ویژه

## در PHP

    require 'modules/team/bootstrap.php';
    use PartoCMS\Team\Widget\TeamWidget;
    echo TeamWidget::render(['limit' => 6]);

## ساختار

    modules/team/
      manifest.json, README.md, config.php, bootstrap.php
      database/schema.sql
      src/Domain/, src/Infrastructure/, src/Widget/
      admin/, assets/

## تغییرات

### v1.0.0 (2026-10-19)
- انتشار اولیه
- DDD Architecture
- Admin UI + Frontend Widget
- RTL + LTR

---

پایان README
