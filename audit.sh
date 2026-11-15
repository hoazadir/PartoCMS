#!/bin/bash
echo "═══════════════════════════════════════════════════════════════"
echo "🔍 بررسی فنی و مهندسی جامع PartoCMS"
echo "📅 تاریخ: $(date)"
echo "═══════════════════════════════════════════════════════════════"
echo ""

PROJECT_DIR=~/PartoCMS

echo "📌 بخش ۱: ساختار کلی پروژه"
echo "─────────────────────────────────────────────────────────────"
echo "📁 مسیر پروژه: $PROJECT_DIR"
echo "📊 حجم کل: $(du -sh $PROJECT_DIR 2>/dev/null | cut -f1)"
echo "📊 تعداد فایل‌های PHP: $(find $PROJECT_DIR -name '*.php' -type f 2>/dev/null | wc -l)"
echo "📊 تعداد کل فایل‌ها: $(find $PROJECT_DIR -type f 2>/dev/null | wc -l)"
echo "📊 تعداد پوشه‌ها: $(find $PROJECT_DIR -type d 2>/dev/null | wc -l)"
echo ""
echo "📂 پوشه‌های اصلی:"
ls -d $PROJECT_DIR/*/ 2>/dev/null | sed 's|.*/||' | sort
echo ""

echo "📌 بخش ۲: ماژول‌های نصب‌شده"
echo "─────────────────────────────────────────────────────────────"
if [ -d "$PROJECT_DIR/modules" ]; then
    for module in $PROJECT_DIR/modules/*/; do
        if [ -d "$module" ]; then
            mod_name=$(basename "$module")
            mod_files=$(find "$module" -name '*.php' -type f 2>/dev/null | wc -l)
            mod_size=$(du -sh "$module" 2>/dev/null | cut -f1)
            has_manifest="❌"
            [ -f "$module/manifest.json" ] || [ -f "$module/module.json" ] || [ -f "$module/config.php" ] && has_manifest="✅"
            echo "  📦 $mod_name (فایل PHP: $mod_files | حجم: $mod_size | manifest: $has_manifest)"
        fi
    done
else
    echo "  ⚠️ پوشه modules وجود ندارد"
fi
echo ""

echo "📌 بخش ۳: امنیت"
echo "─────────────────────────────────────────────────────────────"
echo "🔐 فایل‌های امنیتی:"
for f in "admin/auth_check.php" "includes/Security.php" "includes/Permissions.php" "config.php" ".htaccess" "admin/.htaccess"; do
    if [ -f "$PROJECT_DIR/$f" ]; then
        size=$(stat -c%s "$PROJECT_DIR/$f" 2>/dev/null)
        echo "  ✅ $f ($size بایت)"
    else
        echo "  ❌ $f (یافت نشد)"
    fi
done
echo ""

echo "🔐 فایل‌های حساس (نباید در web root باشند):"
for f in ".env" ".env.local" "config.local.php" "database.sql" "dump.sql" ".git/config"; do
    if [ -f "$PROJECT_DIR/$f" ]; then
        echo "  ⚠️ $f (وجود دارد - نیاز به بررسی)"
    fi
done
echo ""

echo "🔐 بررسی Permissions-Policy:"
grep -rn "Permissions-Policy" $PROJECT_DIR --include="*.php" 2>/dev/null | head -10
echo ""

echo "🔐 بررسی CSRF:"
grep -rln "csrf_token\|CSRF\|csrf" $PROJECT_DIR --include="*.php" 2>/dev/null | head -10
echo ""

echo "📌 بخش ۴: پایگاه داده"
echo "─────────────────────────────────────────────────────────────"
if command -v mysql &> /dev/null; then
    DB_NAME=$(grep "DB_NAME" $PROJECT_DIR/config.php 2>/dev/null | head -1 | cut -d"'" -f4)
    DB_USER=$(grep "DB_USER" $PROJECT_DIR/config.php 2>/dev/null | head -1 | cut -d"'" -f4)
    DB_PASS=$(grep "DB_PASS" $PROJECT_DIR/config.php 2>/dev/null | head -1 | cut -d"'" -f4)
    echo "📊 دیتابیس: $DB_NAME"
    echo "📊 کاربر: $DB_USER"
    if [ -n "$DB_NAME" ]; then
        TABLES=$(mysql -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 | wc -l)
        echo "📊 تعداد جداول: $TABLES"
        echo ""
        echo "📋 لیست جداول:"
        mysql -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -e "SHOW TABLES;" 2>/dev/null | tail -n +2 | head -20 | sed 's/^/  - /'
    fi
else
    echo "  ⚠️ mysql client نصب نیست (نصب با: pkg install mariadb)"
fi
echo ""

echo "📌 بخش ۵: چندزبانگی (i18n)"
echo "─────────────────────────────────────────────────────────────"
for f in "admin/includes/i18n.php" "includes/content_i18n.php"; do
    if [ -f "$PROJECT_DIR/$f" ]; then
        echo "  ✅ $f"
    else
        echo "  ❌ $f (یافت نشد)"
    fi
done
echo ""
echo "📂 پوشه‌های زبان:"
find $PROJECT_DIR -type d -name "lang*" -o -type d -name "locale*" -o -type d -name "i18n*" 2>/dev/null | head -10
echo ""
echo "📂 فایل‌های ترجمه:"
find $PROJECT_DIR -name "*.po" -o -name "*.mo" -o -name "*lang*.php" -o -name "*locale*.php" 2>/dev/null | head -15
echo ""

echo "📌 بخش ۶: قالب و رندر"
echo "─────────────────────────────────────────────────────────────"
for f in "includes/TemplateRenderer.php" "includes/MenuManager.php"; do
    if [ -f "$PROJECT_DIR/$f" ]; then
        lines=$(wc -l < "$PROJECT_DIR/$f")
        echo "  ✅ $f ($lines خط)"
    else
        echo "  ❌ $f (یافت نشد)"
    fi
done
echo ""
echo "📂 قالب‌های نصب‌شده:"
if [ -d "$PROJECT_DIR/templates" ]; then
    ls -d $PROJECT_DIR/templates/*/ 2>/dev/null | sed 's|.*/||'
else
    echo "  ⚠️ پوشه templates وجود ندارد"
fi
echo ""
echo "📂 بررسی GrapesJS:"
grep -rln "grapesjs\|GrapesJS" $PROJECT_DIR --include="*.php" --include="*.html" --include="*.js" 2>/dev/null | head -5
echo ""

echo "📌 بخش ۷: فایل‌های کلیدی"
echo "─────────────────────────────────────────────────────────────"
for f in "config.php" "includes/ModuleManager.php" "includes/Permissions.php" "includes/AiAssistant.php" "includes/Backup.php" "includes/Seo.php" "admin/index.php" "admin/auth_check.php"; do
    if [ -f "$PROJECT_DIR/$f" ]; then
        lines=$(wc -l < "$PROJECT_DIR/$f")
        size=$(stat -c%s "$PROJECT_DIR/$f")
        echo "  ✅ $f ($lines خط | $size بایت)"
    else
        echo "  ❌ $f (یافت نشد)"
    fi
done
echo ""

echo "📌 بخش ۸: لاگ‌ها و خطاها"
echo "─────────────────────────────────────────────────────────────"
if [ -d "$PROJECT_DIR/logs" ]; then
    echo "📂 پوشه logs وجود دارد:"
    ls -la $PROJECT_DIR/logs/ 2>/dev/null | head -10
    echo ""
    echo "📋 خطاهای اخیر (آخرین ۱۰ خط از هر فایل لاگ):"
    for log in $PROJECT_DIR/logs/*.log; do
        if [ -f "$log" ]; then
            echo "  📄 $(basename $log):"
            tail -5 "$log" 2>/dev/null | sed 's/^/    /'
        fi
    done
else
    echo "  ⚠️ پوشه logs وجود ندارد"
fi
echo ""
echo "📂 session files:"
ls -la $PROJECT_DIR/logs/sessions/ 2>/dev/null | head -5
echo ""

echo "📌 بخش ۹: وابستگی‌ها و پکیج‌ها"
echo "─────────────────────────────────────────────────────────────"
for f in "composer.json" "package.json" ".htaccess" "robots.txt" "sitemap.xml"; do
    if [ -f "$PROJECT_DIR/$f" ]; then
        echo "  ✅ $f"
    else
        echo "  ❌ $f"
    fi
done
echo ""
if [ -d "$PROJECT_DIR/vendor" ]; then
    echo "  📦 پوشه vendor وجود دارد (composer):"
    ls $PROJECT_DIR/vendor/ 2>/dev/null | head -10
fi
if [ -d "$PROJECT_DIR/node_modules" ]; then
    echo "  📦 پوشه node_modules وجود دارد:"
    du -sh $PROJECT_DIR/node_modules 2>/dev/null
fi
echo ""

echo "📌 بخش ۱۰: بررسی فایل‌های README و مستندات"
echo "─────────────────────────────────────────────────────────────"
find $PROJECT_DIR -maxdepth 2 -name "README*" -o -name "*.md" 2>/dev/null | head -10
echo ""

echo "📌 بخش ۱۱: بررسی مجوزهای فایل (Permissions)"
echo "─────────────────────────────────────────────────────────────"
echo "📊 فایل‌های با مجوز 777 (خطرناک):"
find $PROJECT_DIR -type f -perm -0777 2>/dev/null | head -10
echo ""
echo "📊 فایل‌های با مجوز 666 (خطرناک):"
find $PROJECT_DIR -type f -perm -0666 2>/dev/null | head -10
echo ""
echo "📊 پوشه‌های با مجوز 777:"
find $PROJECT_DIR -type d -perm -0777 2>/dev/null | head -10
echo ""

echo "📌 بخش ۱۲: بررسی فایل‌های اجرایی مشکوک"
echo "─────────────────────────────────────────────────────────────"
find $PROJECT_DIR -type f -executable 2>/dev/null | head -20
echo ""

echo "═══════════════════════════════════════════════════════════════"
echo "✅ بررسی جامع به پایان رسید"
echo "═══════════════════════════════════════════════════════════════"
