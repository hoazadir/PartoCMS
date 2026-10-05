# 📦 PartoCMS Components Roadmap

> آخرین بروزرسانی: 2026-10-05
> طراحی و برنامه‌نویسی: **Hooman Oliaei (هومان اولیایی)**
> مخزن: https://github.com/hoazadir/PartoCMS

---

### 🎯 هدف این فایل

این فایل دو بخش اصلی دارد:

1. **کارهای معلق از قبل** — تسک‌هایی که در جلسات قبل شروع شدند ولی کامل نشدند
2. **تحلیل کامپوننت‌ها** — چه کامپوننت‌هایی داریم، چه نداریم، برای هر نوع سایت چه لازم است

---

## 🔴 بخش ۱: کارهای معلق از قبل

### 🟠 اولویت بالا (فوری)

| # | کار | فاز | وضعیت |
|---|------|------|--------|
| ۱ | تست Tracking کلیک/نمایش | ۳ | 🟡 نیمه‌کاره |
| ۲ | تست Adblock Plus + Edge | ۳-C | 🟡 نیمه‌کاره |
| ۳ | تست Installer روی سرور شخصی | ۳-D | 🔴 انجام نشده |
| ۴ | پاکسازی بکاپ‌ها (.bak-*) | عمومی | 🔴 انجام نشده |
| ۵ | Groq API Key (نیاز VPN) | ۳-B | 🔴 انجام نشده |
| ۶ | تست AdAiAssistant در مرورگرها | ۳-A | 🟡 نیمه‌کاره |

### 🟡 اولویت متوسط

| # | کار | وضعیت |
|---|------|--------|
| ۷ | AJAX test AdAiAssistant | ✅ انجام شد |
| ۸ | دکمه تست اتصال ai_providers | ✅ انجام شد |
| ۹ | رفع باگ PDF security (Dompdf→mPDF) | ✅ انجام شد |
| ۱۰ | رفع باگ sidebar AI group | ✅ انجام شد |
| ۱۱ | مستندسازی Installer (README) | 🔴 انجام نشده |
| ۱۲ | i18n تاریخ PDF (شمسی/قمری/میلادی) | ✅ انجام شد |

### 🟢 اولویت پایین

| # | کار | وضعیت |
|---|------|--------|
| ۱۳ | آپدیت TODO.md | ✅ انجام شد |
| ۱۴ | حذف track-*.php قدیمی | ✅ انجام شد |
| ۱۵ | Commit همه تغییرات | ✅ انجام شد |

---

## ✅ بخش ۲: کامپوننت‌های موجود (۵۱ مورد)

### 📦 کامپوننت‌های پایه (۱۳)
content_items, categories, tags, media, comments, forms, menus, seo_settings, i18n, security, backups, users, table_builder

### 🛒 ماژول فروشگاه (۱۴)
shop_products, shop_categories, shop_product_images, shop_product_variants, shop_product_attributes, shop_carts, shop_orders, shop_customers, shop_coupons, shop_shipping_methods, shop_tax_rates, shop_payment_gateways, shop_transactions, InvoiceGenerator

### 📢 ماژول تبلیغات (۹)
ads, ad_positions, ad_campaigns, ad_banners, ad_impressions, ad_clicks, ad_target_rules, AdAiAssistant, AdObfuscator

### 🤖 ماژول AI (۱۰)
AIGateway, AIEnvironment, AIProxyManager, AICircuitBreaker, OllamaProvider, OpenRouterProvider, GroqProvider, GeminiProvider, ai_providers.php, AdAiAssistant

### 🚀 Smart Installer (۵)
index.php, database.php, admin-user.php, site-type.php, install.php

---

## ❌ بخش ۳: کامپوننت‌های Missing (۱۱۵ مورد)

### 🌐 عمومی — مشترک همه سایت‌ها (۸)

| # | کامپوننت | اولویت | جدول |
|---|----------|--------|------|
| ۱ | Reviews + Rating | 🔴 بالا | reviews |
| ۲ | Newsletter | 🔴 بالا | newsletter_subscribers |
| ۳ | Social Share | 🟡 متوسط | — |
| ۴ | Live Chat | 🟢 کم | chat_conversations |
| ۵ | Advanced Search | 🔴 بالا | — |
| ۶ | FAQ | 🟡 متوسط | faqs |
| ۷ | Testimonials | 🟡 متوسط | testimonials |
| ۸ | Dynamic Sitemap | 🟡 متوسط | — |

### 🏢 سازمانی / شرکتی (۸)
Team, Services, Testimonials, FAQ, Certificates, Timeline, Partners, Case Studies

### 🛒 فروشگاهی (۹)
Product Reviews, Wishlist, Compare, Related Products, Advanced Filter, Bundle, Multi-Currency, Subscription, Order Tracking

### 📝 وبلاگ / خبری (۸)
Related Posts, Reading Time, Featured Posts, Post Series, TOC, Social Share, Post Rating, Author Profile

### 🏠 مشاور املاک (۱۲)
properties, property_categories, property_images, property_features, agents, property_inquiries, property_favorites, Advanced Search, Map, Mortgage Calculator, Compare, Virtual Tour

### 🏥 پزشکی / دندانپزشکی (۱۳)
doctors, specialties, appointments, patients, medical_records, prescriptions, insurance_providers, medical_services, reviews, online consult, patient portal, doctor_schedules, reminders

### 🍽️ رستوران / کافه (۹)
menu_categories, menu_items, online order, reservations, business_hours, delivery_zones, specials, QR menu, takeaway toggle

### 🎓 آموزشگاه (۱۱)
courses, course_categories, instructors, enrollments, student portal, course_materials, certificates, class_schedules, course_progress, quizzes, live class

### 👤 شخصی / رزومه (۸)
cv_sections, skills, work_experience, education, certificates, CV download, portfolio gallery, social links

### 📢 آژانس تبلیغاتی (۷)
CRM client, case studies, team, testimonials, pricing packages, brief form, performance report

### 🚗 نمایشگاه خودرو (۱۲)
vehicles, vehicle_brands, vehicle_models, vehicle_images, vehicle_specs, test_drive_bookings, trade_in_requests, warranties, Advanced Filter, Installment Calculator, Compare, vehicle_history

### 🛋️ نمایشگاه مبلمان (۱۰)
room_categories, 3D view, Room Planner, custom_orders, quote_requests, consultation, collections, fabric/color picker, gallery+, portfolio

---

## 🎯 بخش ۴: اولویت‌بندی ساخت

### 🔴 فاز ۳-ب (فوری — ۱ هفته)

| # | کامپوننت | زمان |
|---|----------|------|
| ۱ | Reviews + Rating | ۲ روز |
| ۲ | Newsletter | ۱ روز |
| ۳ | FAQ | ۱ روز |
| ۴ | Testimonials | ۱ روز |
| ۵ | Team | ۱ روز |
| ۶ | Services | ۱ روز |

### 🟡 فاز ۶ — CRM (۲ هفته)
مدیریت مشتریان، تعاملات، تیکتینگ، قیف فروش، اتوماسیون

### 🟡 فاز جدید — Booking (۱ هفته)
نوبت‌دهی (پزشکی)، رزرو میز (رستوران)، ثبت‌نام دوره، تست درایو، بازدید ملک

### 🟡 فازهای تخصصی

| # | ماژول | زمان |
|---|-------|------|
| ۱ | مشاور املاک | ۲ هفته |
| ۲ | نمایشگاه خودرو | ۲ هفته |
| ۳ | آموزشگاه (LMS) | ۳ هفته |
| ۴ | رستوران | ۱ هفته |
| ۵ | پزشکی / دندانپزشکی | ۳ هفته |
| ۶ | سازمانی + آژانس | ۱ هفته |
| ۷ | شخصی + مبلمان | ۱ هفته |

**جمع: ~۱۳ هفته**

---

## 📊 بخش ۵: خلاصه آماری

### کامپوننت‌های موجود
- ✅ پایه: ۱۳
- ✅ فروشگاه: ۱۴
- ✅ تبلیغات: ۹
- ✅ AI: ۱۰
- ✅ Installer: ۵
- **✅ مجموع: ۵۱ کامپوننت**

### کامپوننت‌های Missing
- ❌ عمومی: ۸
- ❌ سازمانی: ۸
- ❌ فروشگاهی: ۹
- ❌ وبلاگ: ۸
- ❌ املاک: ۱۲
- ❌ پزشکی: ۱۳
- ❌ رستوران: ۹
- ❌ آموزشگاه: ۱۱
- ❌ شخصی: ۸
- ❌ آژانس: ۷
- ❌ خودرو: ۱۲
- ❌ مبلمان: ۱۰
- **❌ مجموع: ۱۱۵ کامپوننت جدید**

### آمار کل
- 📊 **مجموع کامپوننت‌ها: ۱۶۶**

---

### ⏱ زمان تخمینی کل

| فاز | زمان |
|------|------|
| فاز ۳-ب (عمومی) | ۱ هفته |
| فاز ۶ (CRM) | ۲ هفته |
| Booking | ۱ هفته |
| ماژول‌های تخصصی | ~۱۳ هفته |
| **مجموع** | **~۱۷ هفته** |

---

### 📌 نکات مهم

1. **کامپوننت‌های عمومی** باید در `modules/core/` باشند (نه جدا)
2. **کامپوننت‌های تخصصی** در `modules/<name>/`
3. **Reviews** در همه جا استفاده می‌شود (محصول، مقاله، ملک، خودرو، ...)
4. **Newsletter و CRM** بین همه سایت‌ها مشترک هستند
5. **Booking** باید ماژول مستقل باشد ولی در چند نوع سایت استفاده شود
6. **همه کامپوننت‌های عمومی** باید قبل از ماژول‌های تخصصی ساخته شوند

---

### 🎯 مسیر پیشنهادی اجرایی

```

گام ۱: فاز ۳-ب (عمومی) ← شروع
└─ Reviews + Newsletter + FAQ + Testimonials + Team + Services

گام ۲: فاز ۶ (CRM)
└─ مدیریت مشتریان + تعاملات + تیکتینگ + قیف فروش

گام ۳: Booking (نوبت‌دهی مشترک)

گام ۴: فاز ۴ (قالب‌ساز + ۶۰ تمپلیت)

گام ۵: ماژول‌های تخصصی
├─ مشاور املاک
├─ نمایشگاه خودرو
├─ آموزشگاه (LMS)
├─ رستوران
└─ پزشکی

```

---

### 🔄 ارتباط با TODO.md

این فایل مکمل `TODO.md` است:

- **TODO.md** — نقشه راه کلی پروژه (فاز ۰ تا ۱۲)
- **COMPONENTS.md** — تحلیل دقیق کامپوننت‌ها برای هر نوع سایت

هر دو باید با هم بروز شوند.

---

**پایان COMPONENTS.md**
