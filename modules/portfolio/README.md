# Portfolio Module

ماژول نمونه‌کارها برای PartoCMS.

- نسخه: 1.0.0
- نویسنده: Hooman Oliaei
- لایسنس: Proprietary

---

## ویژگی‌ها

- ثبت نمونه‌کار از پنل ادمین با **TinyMCE**
- گالری تصاویر (JSON array از URLها)
- تگ‌ها (JSON array)
- دسته‌بندی
- نام مشتری + تاریخ پروژه + لینک پروژه
- تصویر اصلی با ظاهر مدرن (16:10 aspect ratio)
- وضعیت (published/hidden) + Featured
- Bulk operations + فیلترها
- شورت‌کد [portfolio] با ۹ attribute
- ویجت grid/slider + CSS responsive

---

## نصب

### 1. جداول DB

    mariadb -u root -p grapesjs_cms < modules/portfolio/database/schema.sql

### 2. ثبت در Registry

قبلاً در فاز ۲ انجام شده.

---

## استفاده

### شورت‌کدها

    [portfolio]                          -> همه منتشرشده
    [portfolio limit="6"]                -> ۶ نمونه‌کار
    [portfolio style="slider"]           -> اسلایدر
    [portfolio columns="2"]              -> ۲ ستون
    [portfolio category="طراحی وب"]      -> فیلتر دسته
    [portfolio featured="true"]          -> فقط ویژه
    [portfolio thumbnail="false"]        -> مخفی تصویر
    [portfolio tags="false"]             -> مخفی تگ‌ها
    [portfolio description="false"]      -> مخفی توضیح

### در PHP

    require 'modules/portfolio/bootstrap.php';
    use PartoCMS\Portfolio\Widget\PortfolioWidget;
    echo PortfolioWidget::render(['limit' => 6, 'style' => 'grid']);

### از Service

    require 'modules/portfolio/bootstrap.php';
    $service = portfolio_service();
    $id = $service->createByAdmin([
        'title' => 'طراحی سایت شرکت آلفا',
        'client_name' => 'شرکت آلفا',
        'description' => '<p>توضیح کوتاه</p>',
        'content' => '<h2>محتوای کامل</h2>',
        'category' => 'طراحی وب',
        'tags' => ['HTML5', 'CSS3', 'PHP'],
        'gallery' => ['url1.jpg', 'url2.jpg'],
        'project_date' => '2026-09-15',
        'project_url' => 'https://example.com',
        'is_featured' => true,
    ], true);

---

## دیتابیس

جدول portfolio_items (۱۷ ستون):

- id, title, slug, client_name
- description (TEXT - TinyMCE کوتاه)
- content (LONGTEXT - TinyMCE کامل)
- thumbnail_url, gallery (JSON), category, tags (JSON)
- project_date, project_url
- status, is_featured, sort_order, language
- created_at, updated_at

۶ index: slug (unique), status, featured, sort, language, category

---

## ویرایشگر

از **TinyMCE 6.8.3** (CDN) با:
- RTL + فونت Tahoma
- plugins: lists, link, image, table, code, fullscreen
- ۲ ادیتور: description (کوتاه) + content (کامل)

---

## تنظیمات

از پنل ادمین -> نمونه‌کارها -> تنظیمات:

- وضعیت ماژول
- عمومی (items_per_page, default_status)
- نمایش (show_thumbnail, show_client, show_category, show_tags, show_date)
- ویجت (limit, columns, style)

---

## ساختار

    modules/portfolio/
      manifest.json, README.md, config.php, bootstrap.php
      database/schema.sql
      src/Domain/ (Entity, Exception, Repository, Service)
      src/Infrastructure/Persistence/PdoPortfolioRepository.php
      src/Widget/ (PortfolioWidget.php, PortfolioShortcode.php)
      admin/ (5 pages)
      assets/portfolio.css

---

## تغییرات

### v1.0.0 (2026-10-19)

- انتشار اولیه
- DDD Architecture
- Admin UI (5 pages) with TinyMCE
- Frontend (Widget + Shortcode)
- Gallery + Tags + Category
- 16:10 thumbnail with hover zoom
- 3-line description clamp
- RTL + LTR support

---

پایان README
