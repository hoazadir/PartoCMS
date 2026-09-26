# Contributing to PartoCMS

از اینکه می‌خواهید در توسعه PartoCMS مشارکت کنید، سپاسگزاریم.

---

## فهرست مطالب

- پیش‌نیازها
- شروع کار
- Git Workflow
- استانداردهای کدنویسی
- ساختار پروژه
- تست
- امنیت
- ارتباط

---

## پیش‌نیازها

| مورد | حداقل نسخه |
|------|-------------|
| PHP | 8.0 |
| MariaDB | 10.4 |
| MySQL | 5.7 |
| Git | 2.30 |
| Composer | 2.0 |
| Node.js | 16 (اختیاری) |

آشنایی با Bootstrap 5، JavaScript و PHP توصیه می‌شود.

---

## شروع کار

### ۱. Fork کردن مخزن

به آدرس مخزن بروید و روی دکمه Fork کلیک کنید.

### ۲. Clone کردن مخزن

git clone https://github.com/YOUR_USERNAME/PartoCMS.git
cd PartoCMS

### ۳. نصب وابستگی‌ها

composer install
npm install

### ۴. تنظیم فایل config

cp config.example.php config.php

سپس فایل config.php را ویرایش کنید و اطلاعات دیتابیس خود را وارد کنید.

### ۵. ایمپورت دیتابیس

mysql -u root -p grapesjs_cms < database/schema.sql

### ۶. اجرای سرور

php -S 127.0.0.1:8080 -t .

### ۷. دسترسی به سایت

- سایت اصلی: http://127.0.0.1:8080/
- پنل ادمین: http://127.0.0.1:8080/admin/

---

## Git Workflow

### ساخت Branch جدید

git checkout -b feature/AmazingFeature

### Commit کردن تغییرات

git add .
git commit -m "feat: add AmazingFeature"

### Push کردن

git push origin feature/AmazingFeature

### ارسال Pull Request

به GitHub بروید و Pull Request ارسال کنید. توضیحات کامل بنویسید و در صورت تغییر UI، Screenshot اضافه کنید.

---

## Commit Message Convention

ما از Conventional Commits استفاده می‌کنیم:

| Type | توضیح | مثال |
|------|-------|------|
| feat | قابلیت جدید | feat: add Zarinpal gateway |
| fix | رفع باگ | fix: resolve CSRF issue |
| docs | مستندات | docs: update README |
| style | فرمت کد | style: fix indentation |
| refactor | بازسازی کد | refactor: simplify User class |
| test | افزودن تست | test: add LoginTest |
| chore | تغییرات build | chore: update dependencies |
| perf | بهبود کارایی | perf: optimize queries |
| security | امنیت | security: fix XSS |

---

## استانداردهای کدنویسی

### PHP

- استاندارد PSR-12
- Type Hints برای پارامترها و return
- DocBlocks برای همه توابع
- Sanitization برای ورودی‌ها
- Prepared Statements برای دیتابیس
- استفاده از Namespace

نمونه کد صحیح:

class UserManager
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}

### JavaScript

- ES6+ syntax
- const و let به جای var
- async/await به جای callback
- Semantic naming

### CSS

- BEM Naming: .block__element--modifier
- CSS Variables برای رنگ‌ها
- Mobile-first approach

---

## ساختار پروژه

PartoCMS/
- admin/          پنل مدیریت
- ajax/           Endpointهای AJAX
- api/            API عمومی
- assets/         CSS, JS, Images
- includes/       هسته اصلی
- modules/        ماژول‌ها
  - ai_assistant/  دستیار هوشمند
  - content/       مدیریت محتوا
  - generated/     ماژول‌های تولیدشده
  - table_builder/ جدول‌ساز
- libs/           کتابخانه‌های خارجی
- vendor/         Composer packages
- logs/           فایل‌های لاگ
- backups/        backupها
- config.php      تنظیمات اصلی

---

## تست

### تست PHP Syntax

php -l path/to/file.php

### تست امنیت

php admin/security_audit.php

### بررسی لاگ‌ها

tail -f logs/php_errors.log

---

## امنیت

هرگز اطلاعات حساس را در Git قرار ندهید:

- config.php
- *.pem و *.key
- .env
- توکن‌ها و API Keys
- رمزهای عبور

### اگر اشتباهی این کار را کردید

۱. فوراً توکن/رمز را در سرویس مربوطه Revoke کنید
۲. فایل را از Git history حذف کنید
۳. به GitHub Force Push کنید
۴. همه Collaborators را مطلع کنید

### چک‌لیست امنیتی قبل از Commit

- فایل config.php در .gitignore هست
- فایل‌های .pem و .key در .gitignore هستند
- هیچ توکنی در کد نیست
- هیچ رمزی در کد نیست
- .htaccess درست تنظیم شده
- CSRF Token در فرم‌ها هست
- Prepared Statements در کوئری‌ها هست

---

## Checklist قبل از Pull Request

- کد تست شده است
- مستندات به‌روزرسانی شده
- Commit message استاندارد است
- فایل‌های .bak حذف شده‌اند
- توکن‌ها در کد نیستند
- .gitignore به‌روزرسانی شده
- PHP Syntax بدون خطاست
- تست امنیت انجام شده

---

## ارتباط

- GitHub Issues: برای گزارش باگ و درخواست قابلیت
- GitHub Discussions: برای سوالات و بحث
- Email: hooman.oliaei@gmail.com

---

## لایسنس

با مشارکت در این پروژه، شما با لایسنس پروژه موافقت می‌کنید.

---

آخرین بروزرسانی: 2026-09-27
توسعه‌دهنده اصلی: Hooman Oliaei (هومان اولیایی)
