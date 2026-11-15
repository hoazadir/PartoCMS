#!/bin/bash
echo "═══════════════════════════════════════════════════════════"
echo "🔍 تشخیص کامل پورت‌های اشغال شده و سرویس‌های فعال"
echo "═══════════════════════════════════════════════════════════"
echo ""

echo "📌 ۱. بررسی پورت‌های 8080، 8443، 9443"
echo "─────────────────────────────────────────"
for port in 8080 8443 9443; do
    echo "🔎 پورت $port:"
    PID=$(fuser $port/tcp 2>/dev/null | tr -d ' ')
    if [ -n "$PID" ]; then
        echo "   ⚠️ اشغال توسط PID: $PID"
        if [ -d "/proc/$PID" ]; then
            echo "   📋 دستور: $(cat /proc/$PID/cmdline 2>/dev/null | tr '\0' ' ')"
            echo "   📁 مسیر: $(readlink /proc/$PID/exe 2>/dev/null)"
            echo "   👤 مالک: $(stat -c '%U' /proc/$PID 2>/dev/null)"
        fi
    else
        echo "   ✅ آزاد (یا قابل تشخیص نیست)"
    fi
    echo ""
done

echo "📌 ۲. تمام فرآیندهای در حال اجرا"
echo "─────────────────────────────────────────"
ps -ef | grep -v grep | grep -v "check-ports" | head -50
echo ""

echo "📌 ۳. فرآیندهای مرتبط با شبکه"
echo "─────────────────────────────────────────"
ps -ef | grep -v grep | grep -E "stunnel|php|python|node|nc|socat|ssh|vpn|proxy" | head -30
echo ""

echo "📌 ۴. تمام پورت‌های LISTEN (با ss)"
echo "─────────────────────────────────────────"
if command -v ss &> /dev/null; then
    ss -tlnp 2>/dev/null
else
    echo "⚠️ ss نصب نیست. نصب می‌کنیم..."
    pkg install iproute2 -y 2>/dev/null
    ss -tlnp 2>/dev/null
fi
echo ""

echo "📌 ۵. بررسی فایل‌های stunnel قدیمی"
echo "─────────────────────────────────────────"
find /data/data/com.termux/files/home -name "stunnel*.log" -o -name "stunnel*.conf" 2>/dev/null
echo ""

echo "📌 ۶. برنامه‌های نصب‌شده که ممکن است پورت بگیرند"
echo "─────────────────────────────────────────"
pkg list-installed 2>/dev/null | grep -E "nginx|apache|node|python|php|stunnel|openssh|tor|proxychains"
echo ""

echo "📌 ۷. بررسی پروسه‌های مخفی در پس‌زمینه"
echo "─────────────────────────────────────────"
ps -ef | grep -v grep | grep -v "\[" | head -30
echo ""

echo "═══════════════════════════════════════════════════════════"
echo "✅ تشخیص کامل شد"
echo "═══════════════════════════════════════════════════════════"
