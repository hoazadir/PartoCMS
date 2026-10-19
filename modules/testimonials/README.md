# Testimonials Module

ماژول نظرات مشتریان برای PartoCMS

- نسخه: 1.0.0
- نویسنده: Hooman Oliaei
- لایسنس: Proprietary

---

## ویژگی‌ها

- ثبت نظر از پنل ادمین
- ثبت نظر از فرانت‌اند (AJAX)
- امتیاز ستاره‌ای 1 تا 5
- آواتار (URL)
- صف تایید
- Featured (نمایش ویژه)
- ترتیب نمایش
- چندزبانه
- ضد اسپم (cooldown + daily limit + spam words)
- Bulk operations
- فیلترها

---

## نصب

### 1. جداول DB

دستور: mariadb -u root -p grapesjs_cms < modules/testimonials/database/schema.sql

### 2. ثبت در Registry

در module_registry.php به defaultMap اضافه کن: testimonials با menu_group=marketing، sort_order=50

در module_registry_sidebar.php به moduleLabels: testimonials با icon=chat

### 3. ثبت در DB

INSERT INTO modules (slug, name, version, is_active, menu_group, sort_order) VALUES ('testimonials', 'Testimonials', '1.0.0', 1, 'marketing', 50);

---

## استفاده

### شورت‌کدها

- testimonials -> همه منتشرشده
- testimonials limit=6 -> 6 نظر
- testimonials style=slider -> اسلایدر
- testimonials columns=2 -> 2 ستون
- testimonials featured=true -> فقط ویژه
- testimonials rating=4 -> 4+ ستاره
- testimonials show_form=true -> با فرم

### در PHP

require bootstrap.php
service = testimonials_service()
service->approve(id)
service->getFeatured(6)

---

## دیتابیس

جدول testimonials با ستون‌ها:

- id, name, email, company, position
- content, rating, avatar_url
- status (pending/published/rejected)
- is_featured, sort_order, language
- submitted_ip, created_at, updated_at, published_at

6 index روی status, featured, language, sort, created, published

---

## تنظیمات

از پنل ادمین -> testimonials -> تنظیمات

- عمومی: auto_approve, require_email, allow_guest, items_per_page
- ضد اسپم: spam_cooldown, spam_daily_limit, min/max_content_length
- نمایش: show_avatar, show_company, show_position, show_rating
- ویجت: limit, columns, style

ذخیره در settings با پیشوند testimonials_*

---

## ساختار پروژه

modules/testimonials/ شامل:

- manifest.json, README.md, config.php, bootstrap.php
- database/schema.sql
- src/Domain/ (Entity, Exception, Repository, Service)
- src/Infrastructure/Persistence/
- src/Widget/ (Widget, Shortcode)
- admin/ (5 pages)
- ajax/submit.php
- assets/testimonials.css

---

## تغییرات

### v1.0.0 (2026-10-19)

- انتشار اولیه
- DDD Architecture
- Admin UI (5 pages)
- Frontend (Widget + Shortcode + AJAX)
- Anti-spam (3 layers)
- i18n (fa-IR, en-US)
- RTL + LTR support

---

پایان README
