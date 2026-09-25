#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════
# PartoCMS - Whisper Queue Cron Installer
# نصب و راه‌اندازی Cron در محیط‌های مختلف
# ═══════════════════════════════════════════════════════════

set -e

# رنگ‌ها
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log()  { echo -e "${BLUE}[INFO]${NC} $1"; }
ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
err()  { echo -e "${RED}[ERROR]${NC} $1"; }

# ═══════════════════════════════════════════════════════════
#  ۱. تشخیص محیط
# ═══════════════════════════════════════════════════════════
detect_environment() {
    if [ -d "/data/data/com.termux" ] || [ -n "$TERMUX_VERSION" ]; then
        echo "termux"
    elif command -v systemctl >/dev/null 2>&1 && [ "$EUID" -eq 0 ]; then
        echo "systemd"
    elif command -v crontab >/dev/null 2>&1; then
        echo "vps"
    else
        echo "unknown"
    fi
}

ENV=$(detect_environment)
log "محیط شناسایی‌شده: ${ENV}"
echo ""

# ═══════════════════════════════════════════════════════════
#  ۲. مسیرها
# ═══════════════════════════════════════════════════════════
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CRON_FILE="${PROJECT_ROOT}/cron/whisper_queue_cron.php"
LOG_FILE="${PROJECT_ROOT}/logs/queue_cron.log"

if [ ! -f "$CRON_FILE" ]; then
    err "فایل cron یافت نشد: $CRON_FILE"
    exit 1
fi

mkdir -p "${PROJECT_ROOT}/logs"

PHP_BIN=$(command -v php)
if [ -z "$PHP_BIN" ]; then
    err "PHP یافت نشد!"
    exit 1
fi

log "مسیر پروژه: $PROJECT_ROOT"
log "فایل Cron: $CRON_FILE"
log "PHP: $PHP_BIN"
echo ""

# ═══════════════════════════════════════════════════════════
#  ۳. نصب بر اساس محیط
# ═══════════════════════════════════════════════════════════

# ─────────── Termux ───────────
install_termux() {
    log "نصب cronie در Termux..."
    pkg install cronie -y >/dev/null 2>&1 || true

    local CRON_DIR="${HOME}/.cron"
    mkdir -p "$CRON_DIR"

    cat > "$CRON_DIR/partocms_whisper" <<EOF
# PartoCMS Whisper Queue — هر دقیقه
* * * * * cd ${PROJECT_ROOT} && ${PHP_BIN} ${CRON_FILE} >> ${LOG_FILE} 2>&1
EOF

    crontab "$CRON_DIR/partocms_whisper"

    # شروع crond اگر اجرا نیست
    if ! pgrep crond >/dev/null 2>&1; then
        crond
        log "crond شروع شد"
    else
        log "crond از قبل در حال اجرا است"
    fi

    ok "Cron در Termux نصب شد"
    log "بررسی: crontab -l"
}

# ─────────── VPS / cPanel ───────────
install_vps() {
    log "نصب cron برای VPS..."

    local CRON_LINE="* * * * * cd ${PROJECT_ROOT} && ${PHP_BIN} ${CRON_FILE} >> ${LOG_FILE} 2>&1"

    if crontab -l 2>/dev/null | grep -q "whisper_queue_cron"; then
        warn "Cron از قبل نصب شده است"
        return 0
    fi

    (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -

    ok "Cron در VPS نصب شد"
    log "بررسی: crontab -l"
}

# ─────────── Systemd ───────────
install_systemd() {
    log "نصب systemd timer..."

    cat > /etc/systemd/system/partocms-whisper.service <<EOF
[Unit]
Description=PartoCMS Whisper Queue
After=network.target

[Service]
Type=oneshot
WorkingDirectory=${PROJECT_ROOT}
ExecStart=${PHP_BIN} ${CRON_FILE}
StandardOutput=append:${LOG_FILE}
StandardError=append:${LOG_FILE}
User=www-data
EOF

    cat > /etc/systemd/system/partocms-whisper.timer <<EOF
[Unit]
Description=Run PartoCMS Whisper Queue every minute

[Timer]
OnBootSec=1min
OnUnitActiveSec=1min
Unit=partocms-whisper.service

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    systemctl enable partocms-whisper.timer
    systemctl start partocms-whisper.timer

    ok "Systemd timer نصب و فعال شد"
    log "بررسی: systemctl status partocms-whisper.timer"
}

# ─────────── Fallback (Loop) ───────────
install_loop() {
    log "استفاده از روش loop در پس‌زمینه..."

    local PID_FILE="${PROJECT_ROOT}/cron/.loop.pid"

    if [ -f "$PID_FILE" ]; then
        OLD_PID=$(cat "$PID_FILE")
        if kill -0 "$OLD_PID" 2>/dev/null; then
            kill "$OLD_PID"
            log "loop قدیمی متوقف شد"
        fi
    fi

    nohup bash -c "while true; do
        ${PHP_BIN} ${CRON_FILE} >> ${LOG_FILE} 2>&1
        sleep 60
    done" >/dev/null 2>&1 &

    echo $! > "$PID_FILE"

    ok "Loop در پس‌زمینه اجرا شد (PID: $(cat $PID_FILE))"
    warn "این روش با restart سرور متوقف می‌شود"
}

# ═══════════════════════════════════════════════════════════
#  ۴. اجرا
# ═══════════════════════════════════════════════════════════
case "$ENV" in
    termux)  install_termux ;;
    vps)     install_vps ;;
    systemd) install_systemd ;;
    *)       warn "محیط ناشناخته — استفاده از loop"; install_loop ;;
esac

echo ""
ok "✅ نصب کامل شد!"
echo ""
log "📋 تست:"
echo "  php ${CRON_FILE}"
echo ""
log "📋 لاگ:"
echo "  tail -f ${LOG_FILE}"
