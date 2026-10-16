# 🔄 Newsletter Cron Worker

کارگر پس‌زمینه برای پردازش خودکار صف ارسال خبرنامه.

## چرا؟

بدون این worker، ادمین باید مکرر روی «پردازش دسته‌ای» کلیک کند تا صف خالی شود. با این worker:

- کمپین‌های **scheduled** در زمان مقرر خودکار شروع می‌شوند
- صف ارسال **sending** به‌طور خودکار تا پایان پردازش می‌شود
- کمپین‌های **گیرکرده** بعد از timeout ریکاوری می‌شوند
- نیازی به باز نگه داشتن مرورگر نیست

## استفاده دستی

از ریشه پروژه:

    php modules/newsletter/cron/worker.php
    php modules/newsletter/cron/worker.php --dry-run
    php modules/newsletter/cron/worker.php --batch=100 --stuck=900
    php modules/newsletter/cron/worker.php --help

## نصب cron (روی سرور)

در crontab (اجرای هر ۲ دقیقه):

    crontab -e

سپس اضافه کن:

    */2 * * * * cd /path/to/PartoCMS && php modules/newsletter/cron/worker.php >> /dev/null 2>&1

## نصب cron در Termux

Termux از `crond` پشتیبانی می‌کند:

    pkg install cronie
    crond
    crontab -e

محتوای crontab:

    */2 * * * * cd ~/PartoCMS && php modules/newsletter/cron/worker.php >> /dev/null 2>&1

## گزینه‌ها

| گزینه | پیش‌فرض | توضیح |
|-------|---------|-------|
| `--batch=N` | 50 | تعداد ایمیل در هر دسته |
| `--stuck=N` | 600 | timeout (ثانیه) برای کمپین‌های گیرکرده |
| `--max-batches=N` | 100 | حداکثر دسته در هر کمپین در هر اجرا |
| `--dry-run` | — | فقط بازرسی، بدون تغییر |
| `--verbose` | — | لاگ به STDERR هم چاپ شود |
| `--help` | — | راهنما |

## Exit codes

| کد | معنی |
|----|------|
| 0 | موفق |
| 1 | worker دیگری در حال اجراست (lock) |
| 2 | خطای fatal (config، DB، mailer) |
| 3 | برخی فازها خطا دادند ولی worker ادامه داد |

## لاگ

- `worker.log` — لاگ کامل (در `.gitignore`)
- `worker.lock` — فایل قفل (در `.gitignore`)

## عیب‌یابی

**worker چیزی پردازش نمی‌کند:**
- بررسی کن که کمپین‌های `sending` یا `scheduled` وجود داشته باشند
- با `--dry-run --verbose` ببین هر فاز چه می‌گوید

**worker متوقف می‌شود:**
- مقدار `--batch` را کم کن (SMTP rate limit)
- مقدار `--max-batches` را کم کن
- لاگ `worker.log` را ببین

**Gmail rate limit:**
- Gmail محدودیت ~۵۰۰ ایمیل در روز دارد
- `--batch=10` و اجرای هر ۵ دقیقه توصیه می‌شود
