# ❓ ماژول سوالات متداول (FAQ)

ماژول حرفه‌ای سوالات متداول برای PartoCMS با معماری **DDD** (Domain-Driven Design).

## ✨ ویژگی‌ها
- ✅ مدیریت کامل در پنل ادمین
- ✅ ویرایشگر متن حرفه‌ای (TinyMCE)
- ✅ دسته‌بندی و ترتیب نمایش سفارشی
- ✅ نمایش آکاردئونی ریسپانسیو در فرانت‌اند
- ✅ پشتیبانی از شورت‌کد

## 📂 ساختار معماری (DDD)
- **Domain**: Entity, Repository, Service, Exception
- **Infrastructure**: PdoFaqRepository
- **Widget**: FaqWidget, FaqShortcode
- **Admin**: List, Edit

## 🚀 نصب
1. اجرای دیتابیس: `mariadb -u root -p grapesjs_cms < modules/faq/database/schema.sql`
2. ثبت در دیتابیس: `INSERT INTO modules ...`
3. ثبت در Registry (`module_registry.php`).

## 🧩 شورت‌کدها
- `[faq]` : نمایش همه سوالات
- `[faq category="پشتیبانی"]` : نمایش دسته خاص
