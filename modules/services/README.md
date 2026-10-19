# Services Module

ماژول خدمات برای PartoCMS.

- نسخه: 1.0.0
- نویسنده: Hooman Oliaei
- لایسنس: Proprietary

---

## ویژگی‌ها

- ثبت خدمت از پنل ادمین
- آیکون (emoji) یا تصویر
- توضیحات + محتوای کامل
- قیمت + واحد قیمت
- ویژگی‌ها (JSON array)
- دسته‌بندی (فیلتر)
- وضعیت (published/hidden)
- Featured (نمایش ویژه)
- ترتیب نمایش
- چندزبانه
- Bulk operations
- فیلترها: وضعیت، دسته، جستجو
- شورت‌کد [services] با ۸ attribute
- ویجت grid/slider + CSS responsive

---

## نصب

### 1. جداول DB

    mariadb -u root -p grapesjs_cms < modules/services/database/schema.sql

### 2. ثبت در Registry

قبلاً در فاز ۲ انجام شده.

---

## استفاده

### شورت‌کدها

    [services]                          -> همه منتشرشده
    [services limit="6"]                -> ۶ خدمت
    [services style="slider"]           -> اسلایدر
    [services columns="2"]              -> ۲ ستون
    [services category="توسعه وب"]      -> فیلتر دسته
    [services featured="true"]          -> فقط ویژه
    [services price="false"]            -> مخفی قیمت
    [services icon="false"]             -> مخفی آیکون

### در PHP

    require 'modules/services/bootstrap.php';
    use PartoCMS\Services\Widget\ServiceWidget;
    echo ServiceWidget::render(['limit' => 6, 'style' => 'grid']);

### از Service

    require 'modules/services/bootstrap.php';
    $service = services_service();
    $id = $service->createByAdmin([
        'title' => 'طراحی سایت',
        'price' => 5000000,
        'price_unit' => 'تومان',
        'features' => ['واکنش‌گرا', 'سئو'],
    ], true);

---

## دیتابیس

جدول services (۱۶ ستون):

id, title, slug, description, content, icon, image_url,
price, price_unit, features, category, status, is_featured,
sort_order, language, created_at, updated_at

۵ index: slug (unique), status, featured, sort, language, category

---

## تنظیمات

از پنل ادمین -> خدمات -> تنظیمات:

- وضعیت ماژول
- عمومی (items_per_page, default_status)
- نمایش (show_icon, show_price, show_features, show_category)
- ویجت (limit, columns, style)

---

## ساختار

    modules/services/
      manifest.json, README.md, config.php, bootstrap.php
      database/schema.sql
      src/Domain/ (Entity, Exception, Repository, Service)
      src/Infrastructure/Persistence/PdoServiceRepository.php
      src/Widget/ (ServiceWidget.php, ServiceShortcode.php)
      admin/ (5 pages)
      assets/services.css

---

## تغییرات

### v1.0.0 (2026-10-19)
- انتشار اولیه
- DDD Architecture
- Admin UI (5 pages)
- Frontend (Widget + Shortcode)
- Price + Features + Category
- RTL + LTR support

---

پایان README
