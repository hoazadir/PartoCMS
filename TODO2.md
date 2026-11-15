# 📋 TODO2 — PartoCMS (وضعیت تا آخرین جلسه)

> **تاریخ آخرین به‌روزرسانی:** 2026-11-15
> **فاز فعلی:** ۴-الف (ثبت‌نام بنگاه)
> **جلسه بعد:** ساخت `register-agency.php` (فرم + نقشه Leaflet)

---

## 🎯 خلاصه پروژه

**PartoCMS** — یک CMS ماژولار برای ساخت سایت‌های املاک (مشابه دیوار/شیپور).

- **معماری:** DDD + Modular + PHP 8.5 + MySQL
- **دیتابیس:** `grapesjs_cms` (۸۷+ جدول)
- **ماژول اصلی:** `properties` (املاک)
- **هدف نهایی:** اینستالر حرفه‌ای + پرداخت + Panel Presets

---

## ✅ کارهای انجام‌شده (تا این جلسه)

### ماژول Properties (املاک)
- [x] Backend DDD (Entity, Repository, Service)
- [x] پنل ادمین (لیست/ایجاد/ویرایش/حذف)
- [x] تنظیمات (`admin/settings.php`)
- [x] ویجت + شورت‌کد (`PropertyWidget`, `PropertyShortcode`)
- [x] Swiper.js (اسلایدر حرفه‌ای)
- [x] `submit-property.php` (فرم ثبت آگهی از فرانت)
- [x] `property.php` (صفحه تک‌ملک + کارت مشاور)
- [x] `properties.php` (آرشیو + فیلتر)
- [x] `video_url` (نمایش ویدیو)

### سیستم احراز هویت مشاور
- [x] جدول `agent_profiles` (۲۳ ستون)
- [x] `register-agent.php` (فرم + آپلود ۷ مدرک)
- [x] `upload-agent-docs.php` (AJAX + ClamAV + VirusTotal)
- [x] `admin/agents.php` (پنل تأیید/رد/ویرایش)
- [x] `admin/edit-agent.php` (ویرایش مدارک توسط ادمین)
- [x] `user/agent-profile.php` (ویرایش توسط مشاور)
- [x] بازپردازش تصویر (`imagejpeg`) + اطلاع‌رسانی تلگرام + لاگ
- [x] `get-agents.php` (فیلتر `approved`)

### سیستم پیام‌رسانی
- [x] `notifications.js` (Toast زیبا)
- [x] `modal.js` (مودال تأیید سفارشی)
- [x] `modal.css` (استایل مدرن)

### سایدبار
- [x] `admin/agents.php` در `module_files` ثبت شد
- [x] آیکون 👤 + عنوان «مدیریت مشاوران»

### فاز ۴-الف (ناتمام)
- [x] جدول `agencies` (ساخته شد)
- [x] نقش `agency_manager` (اضافه شد)
- [ ] `register-agency.php` (فرم ثبت‌نام بنگاه + نقشه)
- [ ] `admin/agencies.php` (پنل تأیید بنگاه)

---

## ⏳ کارهای باقی‌مانده

### فاز ۴-الف (تکمیل ثبت‌نام بنگاه)
- [ ] `register-agency.php` (فرم + Leaflet + آپلود ۶ مدرک)
- [ ] `admin/agencies.php` (تأیید/رد بنگاه)
- [ ] `admin/edit-agency.php` (ویرایش توسط ادمین)
- [ ] ثبت در `module_files` + سایدبار

### فاز ۴-ب (قرارداد + امضای دیجیتال)
- [ ] جدول `contracts` (version, content, signed_hash, signed_ip)
- [ ] `agency/contract.php` (نمایش + امضا)
- [ ] `admin/contracts.php` (مدیریت)

### فاز ۴-ج (مشاوران بنگاه)
- [ ] `agent_profiles.agency_id` (اتصال به بنگاه)
- [ ] `agency/consultants.php` (لیست مشاوران)
- [ ] `agency/consultant-create.php` (ثبت مشاور توسط مدیر)
- [ ] تأیید دو مرحله‌ای (مشاور → بنگاه → ادمین)

### فاز ۴-د (مالک + مدارک + نقشه)
- [ ] نقش `property_owner`
- [ ] `properties.owner_id`, `properties.location_coords`
- [ ] `property_documents` (سند، وکالت‌نامه)
- [ ] `owner/register.php` (ثبت‌نام مالک)
- [ ] `owner/submit-property.php` (فرم + نقشه Leaflet + آپلود مدارک)
- [ ] `admin/property-documents.php` (بررسی)

### فاز ۴-ه (موتور هوشمند — جدید)
- [ ] جدول `workflow_history`
- [ ] جدول `assignment_queue`
- [ ] جدول `safety_alerts`
- [ ] جدول `notifications`
- [ ] جدول `sla_tracking`
- [ ] `WorkflowEngine.php` (State Machine)
- [ ] `AssignmentService.php` (تخصیص خودکار)
- [ ] `SafetyMonitor.php` (تشخیص الگو)
- [ ] `NotificationService.php`
- [ ] `AuditLogger.php`

### فاز ۴-و (تخصیص + درخواست بازدید)
- [ ] `property_assignments` (max 3، change_count، change_reason)
- [ ] `property_requests` (درخواست بازدید)
- [ ] `agency/requests.php` (پنل بنگاه)
- [ ] `user/requests.php` (پنل مشتری)

### فاز ۴-ز (معاملات + کمیسیون)
- [ ] جدول `transactions`
- [ ] جدول `commissions`
- [ ] درگاه پرداخت (زرین‌پال/پی‌پال/کردیب)
- [ ] `admin/financial.php` (گزارش مالی)

### فاز ۵ (اینستالر حرفه‌ای)
- [ ] `install.php` (wizard)
- [ ] Panel Presets (`panels/real_estate.json`, `clinic.json`, ...)
- [ ] لایسنس + پرداخت
- [ ] داده‌های دمو

---

## 🗄️ تغییرات دیتابیس (تا این جلسه)

### جداول جدید
- `agent_profiles` (۲۳ ستون)
- `agencies` (۲۲ ستون)
- `cities` (۶۷۷ شهر)
- `provinces` (۳۱ استان)

### ستون‌های اضافه‌شده
- `properties.city_id` (BIGINT, FK به `cities`)
- `properties.video_url` (VARCHAR)
- `agent_profiles.national_card_image` (VARCHAR)
- `agent_profiles.national_card_front` (VARCHAR)
- `agent_profiles.national_card_back` (VARCHAR)
- `agent_profiles.contract_image` (VARCHAR)
- `agent_profiles.social_security_image` (VARCHAR)
- `users.national_code` (VARCHAR(10), UNIQUE)

### نقش‌های اضافه‌شده
- `agent` (مشاور املاک) — id=6
- `agency_manager` (مدیر بنگاه) — در جلسه قبل

---

## 📂 فایل‌های جدید (تا این جلسه)

### روت پروژه
- `register-agent.php`
- `submit-property.php`

### `modules/properties/ajax/`
- `get-agents.php`
- `get-cities.php`
- `get-provinces.php`
- `upload-agent-docs.php`
- `upload-public.php`

### `modules/properties/assets/`
- `css/properties.css`
- `js/properties-admin.js`
- `uploads/agents/*.jpg` (۲۱ فایل)

### `admin/`
- `agents.php`
- `edit-agent.php`

### `user/`
- `agent-profile.php`

### `assets/`
- `css/notifications.css`
- `css/modal.css`
- `js/notifications.js`
- `js/modal.js`
- `uploads/agents/` (پوشه)

### `modules/properties/src/`
- `Domain/Service/PropertySettingsService.php`
- `Widget/PropertyWidget.php`
- `Widget/PropertyShortcode.php`

---

## 🔑 اطلاعات مهم

### دیتابیس
- **نام:** `grapesjs_cms`
- **کاربر:** `root`
- **رمز:** `@PASS757ho@`
- **نکته:** اطلاعات آزمایشی — نیازی به تغییر رمز نیست.

### URLs مهم
| صفحه | URL |
|---|---|
| پنل ادمین | `http://0.0.0.0:8000/admin/index.php` |
| مدیریت مشاوران | `http://0.0.0.0:8000/admin/agents.php` |
| مدیریت املاک | `http://0.0.0.0:8000/modules/properties/admin/index.php` |
| تنظیمات املاک | `http://0.0.0.0:8000/modules/properties/admin/settings.php` |
| ثبت‌نام مشاور | `http://0.0.0.0:8000/register-agent.php` |
| ثبت‌نام بنگاه | `http://0.0.0.0:8000/register-agency.php` (بعدی) |
| پنل کاربری | `http://0.0.0.0:8000/user/index.php` |
| پروفایل مشاور | `http://0.0.0.0:8000/user/agent-profile.php` |
| آرشیو املاک | `http://0.0.0.0:8000/properties.php` |
| تک‌ملک | `http://0.0.0.0:8000/property.php?id=1` |

### کاربران تستی
| نقش | username | password | role_id |
|---|---|---|---|
| ادمین | `admin` | (موجود) | 1 |
| مشاور | `09338729334` | (تصادفی) | 6 |
| مشاور تستی | `agent_test` | `agent123` | 6 |

### مشاوران ثبت‌شده
| id | نام | کد ملی | وضعیت |
|---|---|---|---|
| 1 | هومان اولیائی | 3932567854 | approved |
| 2 | حسین خاوری | 3932567980 | pending |

---

## ⚠️ نکات مهم (برای جلسه بعد)

1. **`set +H`** در ابتدای هر دستور.
2. **`/tmp` در Termux کار نمی‌کند** — فایل موقت در `~/PartoCMS/`.
3. **`Ctrl+Shift+R`** برای پاک کردن cache.
4. **`display_errors`** در production خاموش.
5. **قبل از هر تغییر:** بک‌آپ + بررسی (Read-Only).
6. **از `alert()` و `confirm()` استفاده نکنید** — از `showToast()` و `showConfirm()`.
7. **ویروس‌یابی اجباری:** ClamAV + VirusTotal + بازپردازش (`imagejpeg`).
8. **اطلاع‌رسانی:** تلگرام + `security_logs`.
9. **مدیریت خطا:** `try/catch` + `error_log`.
10. **`agencies`** جدول ساخته شد اما فرم ندارد.

---

## 🚀 اولین دستور جلسه بعد

```bash
cd ~/PartoCMS
set +H

echo "═══ ۱. بررسی جدول agencies ═══"
mysql -h 127.0.0.1 -u root -p'@PASS757ho@' grapesjs_cms -e "DESCRIBE agencies" 2>/dev/null

echo ""
echo "═══ ۲. بررسی نقش agency_manager ═══"
mysql -h 127.0.0.1 -u root -p'@PASS757ho@' grapesjs_cms -e "SELECT id, name, slug FROM roles WHERE slug = 'agency_manager'" 2>/dev/null

echo ""
echo "═══ ۳. بررسی Leaflet در پروژه ═══"
grep -rn "leaflet" . --include="*.php" 2>/dev/null | grep -v "\.bak" | head -n 3

echo ""
echo "═══ ۴. بررسی cities (برای انتخاب شهر بنگاه) ═══"
mysql -h 127.0.0.1 -u root -p'@PASS757ho@' grapesjs_cms -e "SELECT COUNT(*) AS total FROM cities" 2>/dev/null
