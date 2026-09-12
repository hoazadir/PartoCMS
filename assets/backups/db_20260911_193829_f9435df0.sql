-- GrapesJS CMS Backup
-- تاریخ: 2026-09-11 19:38:29
-- تعداد جداول: 26
-- نسخه PHP: 8.5.1

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

--
-- Table: `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
CREATE TABLE `activity_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_date` (`created_at`),
  KEY `idx_action` (`action`),
  CONSTRAINT `1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES ('1', '1', 'backup_full_created', 'backup', '1', 'پشتیبان کامل ساخته شد', '127.0.0.1', '2026-09-11 18:30:22');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES ('2', '1', 'backup_created', 'backup', '2', 'پشتیبان دیتابیس ساخته شد', '127.0.0.1', '2026-09-11 18:30:32');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES ('3', '1', 'backup_full_created', 'backup', '3', 'پشتیبان کامل ساخته شد', '127.0.0.1', '2026-09-11 20:25:21');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES ('4', '1', 'backup_deleted', 'backup', '1', 'پشتیبان حذف شد: full_20260911_150022_ed697202.zip', '127.0.0.1', '2026-09-11 20:25:47');
INSERT INTO `activity_log` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES ('5', '1', 'backup_restored', 'backup', '4', 'پشتیبان بازیابی شد', '127.0.0.1', '2026-09-11 20:26:21');


--
-- Table: `backup_settings`
--

DROP TABLE IF EXISTS `backup_settings`;
CREATE TABLE `backup_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `backup_settings` (`id`, `setting_key`, `setting_value`) VALUES ('1', 'auto_backup_enabled', '0');
INSERT INTO `backup_settings` (`id`, `setting_key`, `setting_value`) VALUES ('2', 'auto_backup_frequency', 'daily');
INSERT INTO `backup_settings` (`id`, `setting_key`, `setting_value`) VALUES ('3', 'auto_backup_keep', '7');
INSERT INTO `backup_settings` (`id`, `setting_key`, `setting_value`) VALUES ('4', 'last_auto_backup', '');


--
-- Table: `backups`
--

DROP TABLE IF EXISTS `backups`;
CREATE TABLE `backups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `type` enum('database','files','full') DEFAULT 'database',
  `size` bigint(20) DEFAULT 0,
  `tables_count` int(11) DEFAULT 0,
  `records_count` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `backups` (`id`, `filename`, `filepath`, `type`, `size`, `tables_count`, `records_count`, `description`, `created_by`, `created_at`) VALUES ('2', 'db_20260911_150032_0d4c670a.sql', 'assets/backups/db_20260911_150032_0d4c670a.sql', 'database', '41460', '25', '98', 'پشتیبان دستی دیتابیس', '1', '2026-09-11 18:30:32');
INSERT INTO `backups` (`id`, `filename`, `filepath`, `type`, `size`, `tables_count`, `records_count`, `description`, `created_by`, `created_at`) VALUES ('3', 'full_20260911_165520_61400f3f.zip', 'assets/backups/full_20260911_165520_61400f3f.zip', 'full', '1561392', '25', '100', 'پشتیبان کامل', '1', '2026-09-11 20:25:21');


--
-- Table: `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#3498db',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES ('1', NULL, 'دسته‌بندی نشده', 'uncategorized', 'مطالب بدون دسته‌بندی', '📁', '#95a5a6', '0', '1', '2026-09-10 21:11:01');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES ('2', NULL, 'اخبار', 'news', 'اخبار و اطلاعیه‌های سایت', '📰', '#e74c3c', '0', '1', '2026-09-10 21:11:01');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES ('3', NULL, 'آموزش', 'tutorials', 'مقالات آموزشی', '📚', '#3498db', '0', '1', '2026-09-10 21:11:01');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES ('4', NULL, 'اخبار فناوری', 'tech-news', 'اخبار دنیای فناوری', '💻', '#9b59b6', '0', '1', '2026-09-10 21:11:01');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES ('5', NULL, 'اخبار ورزشی', 'sports-news', 'اخبار ورزشی', '⚽', '#27ae60', '0', '1', '2026-09-10 21:11:01');
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `icon`, `color`, `sort_order`, `is_active`, `created_at`) VALUES ('6', NULL, 'محصولات', 'محصولات', '', '📁', '#3498db', '0', '1', '2026-09-11 17:21:37');


--
-- Table: `comments`
--

DROP TABLE IF EXISTS `comments`;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_email` varchar(255) NOT NULL,
  `author_website` varchar(255) DEFAULT NULL,
  `author_ip` varchar(45) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `comment` text NOT NULL,
  `status` enum('pending','approved','spam','rejected') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_content` (`content_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `content_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `comments` (`id`, `content_id`, `parent_id`, `author_name`, `author_email`, `author_website`, `author_ip`, `user_id`, `comment`, `status`, `created_at`) VALUES ('1', '5', NULL, 'admin', 'hooman.oliaei@gmail.com', 'https://www.negasht.xyz', '127.0.0.1', '1', 'مطلب رو خوندم فکر کنم میشه اصلاحش کرد .', 'approved', '2026-09-10 23:45:23');
INSERT INTO `comments` (`id`, `content_id`, `parent_id`, `author_name`, `author_email`, `author_website`, `author_ip`, `user_id`, `comment`, `status`, `created_at`) VALUES ('2', '5', '1', 'admin', 'admin@example.com', NULL, '127.0.0.1', '1', 'خوب چطوری میشه اصلاح کرد اگه می تونی اصلاح کن .', 'approved', '2026-09-11 00:11:49');


--
-- Table: `content_items`
--

DROP TABLE IF EXISTS `content_items`;
CREATE TABLE `content_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id` int(11) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `featured_image` varchar(500) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `category_id` int(11) DEFAULT NULL,
  `featured_image_id` int(11) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL,
  `og_image` varchar(500) DEFAULT NULL,
  `canonical_url` varchar(500) DEFAULT NULL,
  `no_index` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `type_id` (`type_id`),
  KEY `author_id` (`author_id`),
  KEY `idx_status` (`status`),
  KEY `idx_slug` (`slug`),
  KEY `idx_category` (`category_id`),
  CONSTRAINT `1` FOREIGN KEY (`type_id`) REFERENCES `content_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `content_items` (`id`, `type_id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `meta`, `status`, `author_id`, `created_at`, `updated_at`, `category_id`, `featured_image_id`, `views`, `meta_title`, `meta_description`, `meta_keywords`, `og_image`, `canonical_url`, `no_index`) VALUES ('4', '1', 'نگاشت پرو', 'نگاشت-پرو', '<p>سه تا مشکل واقعی می&zwnj;بینم، به ترتیب اولویت درستشون می&zwnj;کنم. اول (مهم&zwnj;ترین): صفحه&zwnj;ساز داره از CDN خارجی (unpkg.com) لود می&zwnj;شه، و با توجه به اینکه همیشه VPN روی گوشیت روشنه، به&zwnj;نظر می&zwnj;رسه این آدرس&zwnj;ها لود نمی&zwnj;شن یا قطع می&zwnj;شن &mdash; برای همین بوم خالی می&zwnj;مونه. دارم فایل&zwnj;های GrapesJS رو دانلود می&zwnj;کنم و مستقیم توی پروژه میذارم تا هیچ وابستگی به اینترنت لحظه&zwnj;ای نداشته باشه: حالا فایل&zwnj;های اصلی GrapesJS رو دانلود و داخل خود پروژه ذخیره می&zwnj;کنم (دیگه به CDN بیرونی وابسته نباشه): متوجه شدم که نمی&zwnj;تونم خودم فایل&zwnj;های GrapesJS رو دانلود و داخل پروژه bundle کنم (ابزار من به شبکه&zwnj;ی باز دسترسی نداره، فقط می&zwnj;تونه صفحات رو بخونه). ولی یه راه&zwnj;حل بهتر و مطمئن&zwnj;تر پیدا کردم: توی کل این گفتگو، فونت وزیرمتن از cdn.jsdelivr.net همیشه بدون مشکل لود شده، در حالی که unpkg.com (که GrapesJS رو ازش می&zwnj;گرفتم) یه CDN متفاوته که توی ایران معمولاً قطع/کند می&zwnj;شه. دارم آدرس&zwnj;ها رو به jsDelivr عوض می&zwnj;کنم، به&zwnj;علاوه یه پیام خطای واضح اگه بازم لود نشد (به&zwnj;جای صفحه&zwnj;ی خالی و مبهم): حالا فیکس &laquo;قالب جدید&raquo; &mdash; بعد از ساخت، برای قالب&zwnj;های نوع &laquo;سایت&raquo; مستقیم بره توی صفحه&zwnj;ساز حرفه&zwnj;ای، نه مسیر قدیمی:</p>', 'در باره نگاشت پرو', '', NULL, 'published', '1', '2026-09-10 20:14:43', '2026-09-11 18:15:26', '2', NULL, '1', 'آموزش نگاشت پروژه | وب‌سایت من', 'آموزش برنامه سایت ساز نگاشت', 'آموزش ٬نگاشت ٬پرو', 'http://127.0.0.1:8080/assets/uploads/img_20260910_184133_c3cd1630.jpg', '', '0');
INSERT INTO `content_items` (`id`, `type_id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `meta`, `status`, `author_id`, `created_at`, `updated_at`, `category_id`, `featured_image_id`, `views`, `meta_title`, `meta_description`, `meta_keywords`, `og_image`, `canonical_url`, `no_index`) VALUES ('5', '1', 'برنامه نویسی مدیریت محتوا', 'برنامه-نویسی-مدیریت-محتوا', '<p># در ترمینال جدید:<br>ps aux | grep php<br>netstat -tlnp 2&gt;/dev/null | grep 8080<br>curl -I http://localhost:8080/ 2&gt;&amp;1 | head -5</p>\r\n<table style=\"border-collapse: collapse; width: 100%; border-width: 1px; margin-right: 0px; margin-left: auto;\" border=\"1\"><caption>تست شماره ۶&nbsp; قرار بود انجام شود به خاطر کمبود منابع و مخالفت مدیریت سازمان انجام نشد .</caption><colgroup><col style=\"width: 25.0471%;\"><col style=\"width: 25.0471%;\"><col style=\"width: 25.0471%;\"><col style=\"width: 25.0471%;\"></colgroup>\r\n<tbody>\r\n<tr>\r\n<td style=\"text-align: center;\">نام ماژول&nbsp;</td>\r\n<td style=\"text-align: center;\">مدت اعتبار&nbsp;</td>\r\n<td style=\"text-align: center;\">نویسنده</td>\r\n<td style=\"text-align: center;\">مبلغ قابل پرداخت</td>\r\n</tr>\r\n<tr>\r\n<td>تست شماره ۱</td>\r\n<td style=\"text-align: center;\">۳ ماه</td>\r\n<td>عزیز یدالهی</td>\r\n<td style=\"text-align: center;\">۲۵۴۶۷۰</td>\r\n</tr>\r\n<tr>\r\n<td>تست شماره ۲</td>\r\n<td style=\"text-align: center;\">۵</td>\r\n<td>عزیز نسین</td>\r\n<td style=\"text-align: center;\">۵۳۵۷۶۳۶۷</td>\r\n</tr>\r\n<tr>\r\n<td>تست شماره ۳</td>\r\n<td style=\"text-align: center;\">۶</td>\r\n<td>فاروق اسد</td>\r\n<td style=\"text-align: center;\">۷۴۶۷۳۶۶</td>\r\n</tr>\r\n<tr>\r\n<td>تست شماره ۴</td>\r\n<td style=\"text-align: center;\">۷</td>\r\n<td>محمد عبدل</td>\r\n<td style=\"text-align: center;\">۷۵۶۷۷۶۹۰</td>\r\n</tr>\r\n<tr>\r\n<td>تست شماره ۵</td>\r\n<td style=\"text-align: center;\">۹</td>\r\n<td>رسول محمدی</td>\r\n<td style=\"text-align: center;\">۹۵۶۸۵۷۵</td>\r\n</tr>\r\n</tbody>\r\n</table>', '', '', NULL, 'published', '1', '2026-09-10 21:58:26', '2026-09-11 21:41:50', '3', NULL, '1', '', '', '', '', '', '0');
INSERT INTO `content_items` (`id`, `type_id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `meta`, `status`, `author_id`, `created_at`, `updated_at`, `category_id`, `featured_image_id`, `views`, `meta_title`, `meta_description`, `meta_keywords`, `og_image`, `canonical_url`, `no_index`) VALUES ('6', '2', 'محصولات', 'محصولات', '', '', 'http://127.0.0.1:8080/assets/uploads/img_20260911_135240_756aca54.jpg', NULL, 'published', '1', '2026-09-11 17:23:26', '2026-09-11 21:32:18', '6', NULL, '2', NULL, NULL, NULL, NULL, NULL, '0');


--
-- Table: `content_tags`
--

DROP TABLE IF EXISTS `content_tags`;
CREATE TABLE `content_tags` (
  `content_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`content_id`,`tag_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `content_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


--
-- Table: `content_types`
--

DROP TABLE IF EXISTS `content_types`;
CREATE TABLE `content_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(20) DEFAULT NULL,
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fields`)),
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `content_types` (`id`, `slug`, `name`, `description`, `icon`, `fields`, `is_active`) VALUES ('1', 'post', 'مقاله', 'مقالات وبلاگ', '📝', NULL, '1');
INSERT INTO `content_types` (`id`, `slug`, `name`, `description`, `icon`, `fields`, `is_active`) VALUES ('2', 'page', 'صفحه', 'صفحات ثابت سایت', '📄', NULL, '1');
INSERT INTO `content_types` (`id`, `slug`, `name`, `description`, `icon`, `fields`, `is_active`) VALUES ('3', 'product', 'محصول', 'محصولات فروشگاه', '📦', NULL, '1');
INSERT INTO `content_types` (`id`, `slug`, `name`, `description`, `icon`, `fields`, `is_active`) VALUES ('4', 'news', 'خبر', 'اخبار و اطلاعیه‌ها', '📰', NULL, '1');
INSERT INTO `content_types` (`id`, `slug`, `name`, `description`, `icon`, `fields`, `is_active`) VALUES ('5', 'portfolio', 'نمونه کار', 'نمونه کارها و پروژه‌ها', '🎨', NULL, '1');


--
-- Table: `form_submissions`
--

DROP TABLE IF EXISTS `form_submissions`;
CREATE TABLE `form_submissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('new','read','replied','archived') DEFAULT 'new',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_form` (`form_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `form_submissions` (`id`, `form_id`, `data`, `ip_address`, `user_agent`, `status`, `notes`, `created_at`) VALUES ('1', '2', '{\"field_1789076079066\":\"هومان اولیائی\",\"field_1789076131665\":\"081-32211843\",\"field_1789076174230\":\"09188116133\",\"field_1789076224100\":\"6571616668\",\"field_1789076262837\":\"ملایر -خیابان سعدی-خیابان باباطاهر بن بست شهید فریدون ترکمان سهرابی پلاک ۸\",\"field_1789076300204\":\"hooman.oliaei@gmail.com\"}', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'new', NULL, '2026-09-11 01:11:29');
INSERT INTO `form_submissions` (`id`, `form_id`, `data`, `ip_address`, `user_agent`, `status`, `notes`, `created_at`) VALUES ('2', '1', '{\"name\":\"هومان اولیائی\",\"email\":\"hooman.oliaei@gmail.com\",\"subject\":\"تست ارسال\",\"message\":\"این پست صرفا تست ارسال است .\"}', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'new', NULL, '2026-09-11 01:26:19');


--
-- Table: `forms`
--

DROP TABLE IF EXISTS `forms`;
CREATE TABLE `forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`fields`)),
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `success_message` text DEFAULT NULL,
  `submit_label` varchar(100) DEFAULT 'ارسال',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `forms` (`id`, `name`, `slug`, `description`, `fields`, `settings`, `success_message`, `submit_label`, `is_active`, `created_by`, `created_at`) VALUES ('1', 'فرم تماس', 'contact', 'فرم ارتباط با ما', '[{\"type\":\"text\",\"name\":\"name\",\"label\":\"نام و نام خانوادگی\",\"required\":true,\"placeholder\":\"نام خود را وارد کنید\"},{\"type\":\"email\",\"name\":\"email\",\"label\":\"ایمیل\",\"required\":true,\"placeholder\":\"ایمیل شما\"},{\"type\":\"text\",\"name\":\"subject\",\"label\":\"موضوع\",\"required\":true},{\"type\":\"textarea\",\"name\":\"message\",\"label\":\"پیام\",\"required\":true,\"rows\":5,\"placeholder\":\"متن پیام...\"}]', NULL, '✅ پیام شما با موفقیت ارسال شد. به زودی پاسخ می‌دهیم.', 'ارسال پیام', '1', '1', '2026-09-11 00:55:47');
INSERT INTO `forms` (`id`, `name`, `slug`, `description`, `fields`, `settings`, `success_message`, `submit_label`, `is_active`, `created_by`, `created_at`) VALUES ('2', 'دفتر تلفن', 'form-1789076003374', 'دفتر تلفن مشتریان و همکاران', '[{\"type\":\"text\",\"name\":\"field_1789076079066\",\"label\":\"نام و نام خانوادگی\",\"placeholder\":\"\",\"required\":false},{\"type\":\"tel\",\"name\":\"field_1789076131665\",\"label\":\"شماره تلفن ثابت\",\"placeholder\":\"\",\"required\":false},{\"type\":\"tel\",\"name\":\"field_1789076174230\",\"label\":\"شماره تلفن همراه\",\"placeholder\":\"\",\"required\":false},{\"type\":\"textarea\",\"name\":\"field_1789076262837\",\"label\":\"نشانی\",\"placeholder\":\"\",\"required\":false,\"rows\":5},{\"type\":\"number\",\"name\":\"field_1789076656785\",\"label\":\"کد پستی\",\"placeholder\":\"\",\"required\":false},{\"type\":\"email\",\"name\":\"field_1789076688121\",\"label\":\"ایمیل\",\"placeholder\":\"\",\"required\":false}]', NULL, '✅ پیام شما با موفقیت ارسال شد.', 'ارسال', '1', '1', '2026-09-11 01:08:37');


--
-- Table: `media`
--

DROP TABLE IF EXISTS `media`;
CREATE TABLE `media` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `filepath` varchar(500) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `media` (`id`, `filename`, `original_name`, `filepath`, `mime_type`, `file_size`, `width`, `height`, `alt_text`, `uploaded_by`, `created_at`) VALUES ('1', 'img_20260910_184133_c3cd1630.jpg', '1000077411.jpg', 'assets/uploads/img_20260910_184133_c3cd1630.jpg', 'image/jpeg', '329065', '1200', '1920', '', '1', '2026-09-10 22:11:33');
INSERT INTO `media` (`id`, `filename`, `original_name`, `filepath`, `mime_type`, `file_size`, `width`, `height`, `alt_text`, `uploaded_by`, `created_at`) VALUES ('2', 'img_20260910_190725_193762de.jpg', '1000077411.jpg', 'assets/uploads/img_20260910_190725_193762de.jpg', 'image/jpeg', '329065', '1200', '1920', '', '1', '2026-09-10 22:37:25');
INSERT INTO `media` (`id`, `filename`, `original_name`, `filepath`, `mime_type`, `file_size`, `width`, `height`, `alt_text`, `uploaded_by`, `created_at`) VALUES ('3', 'img_20260911_135240_756aca54.jpg', '1000062801.jpg', 'assets/uploads/img_20260911_135240_756aca54.jpg', 'image/jpeg', '499009', '1080', '1088', '', '1', '2026-09-11 17:22:40');


--
-- Table: `menu_items`
--

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `url` varchar(500) DEFAULT '#',
  `type` enum('custom','page','post','category','home') DEFAULT 'custom',
  `target_id` int(11) DEFAULT NULL,
  `target_blank` tinyint(1) DEFAULT 0,
  `icon` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_menu` (`menu_id`),
  KEY `idx_parent` (`parent_id`),
  CONSTRAINT `1` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `title`, `url`, `type`, `target_id`, `target_blank`, `icon`, `sort_order`, `is_active`) VALUES ('1', '1', NULL, 'خانه', 'index.php', 'home', NULL, '0', '🏠', '1', '1');
INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `title`, `url`, `type`, `target_id`, `target_blank`, `icon`, `sort_order`, `is_active`) VALUES ('2', '1', NULL, 'مقالات', 'index.php#posts', 'custom', NULL, '0', '📰', '2', '1');
INSERT INTO `menu_items` (`id`, `menu_id`, `parent_id`, `title`, `url`, `type`, `target_id`, `target_blank`, `icon`, `sort_order`, `is_active`) VALUES ('3', '1', NULL, 'تماس با ما', 'form.php?slug=contact', 'custom', NULL, '0', '📧', '3', '1');


--
-- Table: `menus`
--

DROP TABLE IF EXISTS `menus`;
CREATE TABLE `menus` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `location` varchar(50) DEFAULT 'header',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `menus` (`id`, `name`, `slug`, `location`, `description`, `is_active`, `created_at`) VALUES ('1', 'منوی اصلی', 'main-menu', 'header', 'منوی اصلی سایت در هدر', '1', '2026-09-11 01:30:27');
INSERT INTO `menus` (`id`, `name`, `slug`, `location`, `description`, `is_active`, `created_at`) VALUES ('2', 'منوی فوتر', 'footer-menu', 'footer', 'منوی پاورقی سایت', '1', '2026-09-11 01:30:27');


--
-- Table: `modules`
--

DROP TABLE IF EXISTS `modules`;
CREATE TABLE `modules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `version` varchar(20) DEFAULT '1.0.0',
  `is_enabled` tinyint(1) DEFAULT 0,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `installed_at` timestamp NULL DEFAULT current_timestamp(),
  `is_core` tinyint(1) DEFAULT 0,
  `icon` varchar(20) DEFAULT NULL,
  `menu_group` varchar(50) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('1', 'content', 'مدیریت محتوا', 'ایجاد و مدیریت مقالات، صفحات و محصولات', '1.0.0', '1', NULL, '2026-09-10 18:37:59', '0', '📝', 'content', '1');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('3', 'categories', 'دسته‌بندی‌ها', 'مدیریت دسته‌بندی محتوا', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '🏷', 'content', '2');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('4', 'media', 'رسانه‌ها', 'گالری تصاویر و فایل‌ها', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '🖼', 'content', '3');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('5', 'comments', 'دیدگاه‌ها', 'مدیریت نظرات کاربران', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '💬', 'content', '4');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('6', 'forms', 'فرم‌ساز', 'ساخت فرم‌های پویا', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '📋', 'tools', '5');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('7', 'menus', 'منوساز', 'ساخت منوهای سایت', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '🔗', 'tools', '6');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('8', 'templates', 'مدیریت قالب‌ها', 'قالب‌های سایت', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '📦', 'appearance', '7');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('9', 'editor', 'طراح قالب', 'طراح گرافیکی GrapesJS', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '✏️', 'appearance', '8');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('10', 'reports', 'گزارش‌ها', 'آمار و نمودارها', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '📈', 'reports', '9');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('11', 'seo', 'تنظیمات SEO', 'بهینه‌سازی موتور جستجو', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '0', '🔍', 'seo', '10');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('12', 'users', 'کاربران', 'مدیریت کاربران', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '1', '👥', 'admin', '11');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('13', 'roles', 'نقش‌ها', 'نقش‌ها و دسترسی‌ها', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '1', '🛡', 'admin', '12');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('14', 'modules', 'ماژول‌ها', 'مدیریت ماژول‌های سیستم', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '1', '🧩', 'admin', '13');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('15', 'settings', 'تنظیمات سایت', 'پیکربندی کلی سایت', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '1', '⚙️', 'admin', '14');
INSERT INTO `modules` (`id`, `slug`, `name`, `description`, `version`, `is_enabled`, `config`, `installed_at`, `is_core`, `icon`, `menu_group`, `sort_order`) VALUES ('16', 'backups', 'پشتیبان‌گیری', 'بکاپ و بازیابی', '1.0.0', '1', NULL, '2026-09-11 20:34:29', '1', '💾', 'admin', '15');


--
-- Table: `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('1', 'داشبورد', 'dashboard.view', 'dashboard', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('2', 'کاربران', 'users.manage', 'users', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('3', 'نقش‌ها', 'roles.manage', 'roles', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('4', 'مشاهده محتوا', 'content.view', 'content', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('5', 'ایجاد محتوا', 'content.create', 'content', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('6', 'ویرایش محتوا', 'content.edit', 'content', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('7', 'حذف محتوا', 'content.delete', 'content', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('8', 'انتشار محتوا', 'content.publish', 'content', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('9', 'قالب‌ها', 'templates.manage', 'templates', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('10', 'ماژول‌ها', 'modules.manage', 'modules', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('11', 'فرم‌ها', 'forms.manage', 'forms', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('12', 'ارسال‌ها', 'submissions.view', 'forms', NULL);
INSERT INTO `permissions` (`id`, `name`, `slug`, `module`, `description`) VALUES ('13', 'تنظیمات', 'settings.manage', 'settings', NULL);


--
-- Table: `projects`
--

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `html` longtext DEFAULT NULL,
  `css` longtext DEFAULT NULL,
  `components` longtext DEFAULT NULL,
  `styles` longtext DEFAULT NULL,
  `settings` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `template_id` (`template_id`),
  CONSTRAINT `1` FOREIGN KEY (`template_id`) REFERENCES `templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;


--
-- Table: `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `action` varchar(50) NOT NULL,
  `count` int(11) DEFAULT 1,
  `first_attempt` timestamp NULL DEFAULT current_timestamp(),
  `last_attempt` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_action` (`ip_address`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `rate_limits` (`id`, `ip_address`, `action`, `count`, `first_attempt`, `last_attempt`) VALUES ('3', '127.0.0.1', 'backup_create', '1', '2026-09-11 23:08:29', '2026-09-11 23:08:29');


--
-- Table: `role_modules`
--

DROP TABLE IF EXISTS `role_modules`;
CREATE TABLE `role_modules` (
  `role_id` int(11) NOT NULL,
  `module_slug` varchar(50) NOT NULL,
  `can_access` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`role_id`,`module_slug`),
  KEY `idx_role` (`role_id`),
  KEY `idx_module` (`module_slug`),
  CONSTRAINT `1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'backups', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'categories', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'comments', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'content', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'editor', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'forms', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'media', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'menus', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'modules', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'reports', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'roles', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'seo', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'settings', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'templates', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('1', 'users', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'categories', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'comments', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'content', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'forms', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'media', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'menus', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'reports', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'seo', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('2', 'templates', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('3', 'categories', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('3', 'comments', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('3', 'content', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('3', 'media', '1');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'backups', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'categories', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'comments', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'content', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'editor', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'forms', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'media', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'menus', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'modules', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'reports', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'roles', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'seo', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'settings', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'templates', '0');
INSERT INTO `role_modules` (`role_id`, `module_slug`, `can_access`) VALUES ('4', 'users', '0');


--
-- Table: `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('4', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('5', '1');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '2');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '3');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('5', '4');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('5', '5');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '6');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '6');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('3', '6');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '7');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '8');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('2', '8');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '9');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '10');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '11');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '12');
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES ('1', '13');


--
-- Table: `roles`
--

DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES ('1', 'مدیر کل', 'admin', 'دسترسی کامل', '1', '2026-09-10 19:25:15');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES ('2', 'ویرایشگر', 'editor', 'ایجاد و ویرایش محتوا', '1', '2026-09-10 19:25:15');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES ('3', 'نویسنده', 'author', 'محتوای خودش', '1', '2026-09-10 19:25:15');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES ('4', 'کاربر عادی', 'user', 'محدود', '1', '2026-09-10 19:25:15');
INSERT INTO `roles` (`id`, `name`, `slug`, `description`, `is_system`, `created_at`) VALUES ('5', 'مدیر فروش', 'sales_manager', 'مدیر فروش شرکت', '0', '2026-09-10 20:40:17');


--
-- Table: `seo_settings`
--

DROP TABLE IF EXISTS `seo_settings`;
CREATE TABLE `seo_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('1', 'default_meta_title', '');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('2', 'default_meta_description', '');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('3', 'default_meta_keywords', '');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('4', 'og_site_name', '');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('5', 'twitter_handle', '');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('6', 'google_analytics_id', '');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('7', 'enable_sitemap', '1');
INSERT INTO `seo_settings` (`id`, `setting_key`, `setting_value`) VALUES ('8', 'robots_txt', 'User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /api/\nDisallow: /user/');


--
-- Table: `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('1', 'site_name', 'وب‌سایت من', '2026-09-10 14:54:28');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('2', 'site_description', 'ساخته شده با GrapesJS CMS', '2026-09-10 14:54:28');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('3', 'admin_email', 'admin@example.com', '2026-09-10 14:54:28');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('4', 'theme_color', '#3b97e3', '2026-09-10 14:54:28');
INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES ('5', 'logo', '', '2026-09-10 14:54:28');


--
-- Table: `tags`
--

DROP TABLE IF EXISTS `tags`;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;


--
-- Table: `templates`
--

DROP TABLE IF EXISTS `templates`;
CREATE TABLE `templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `content` longtext DEFAULT NULL COMMENT 'محتوای HTML قالب',
  `css` longtext DEFAULT NULL COMMENT 'استایل‌های CSS قالب',
  `components` longtext DEFAULT NULL COMMENT 'داده‌های JSON کامپوننت‌ها',
  `styles` longtext DEFAULT NULL COMMENT 'داده‌های JSON استایل‌ها',
  `thumbnail` varchar(255) DEFAULT NULL COMMENT 'مسیر تصویر بندانگشتی',
  `is_active` tinyint(1) DEFAULT 0 COMMENT 'وضعیت فعال/غیرفعال',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'قالب پیش‌فرض',
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `use_for_posts` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `author_id` (`author_id`),
  CONSTRAINT `1` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;


--
-- Table: `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `avatar` varchar(500) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`, `is_active`, `created_at`, `avatar`, `bio`, `phone`, `website`, `last_login`, `email_verified`) VALUES ('1', 'admin', 'admin@example.com', '$2y$12$FKx28oTkIyuImFqxw4Kk6OFM.vRh0ze3KhtJz10kCL7rEyPp4302O', '1', '1', '2026-09-10 14:26:47', NULL, NULL, NULL, NULL, NULL, '0');
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role_id`, `is_active`, `created_at`, `avatar`, `bio`, `phone`, `website`, `last_login`, `email_verified`) VALUES ('3', 'sales', 'sales@negadht.xyz', '$2y$12$zNQKl75YyiPgchJNW8Gz3upt99/V4GtatdDaZ/MjNmyGvaH0C9Mqa', '5', '1', '2026-09-10 20:59:25', NULL, NULL, NULL, NULL, NULL, '0');


--
-- Table: `views`
--

DROP TABLE IF EXISTS `views`;
CREATE TABLE `views` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `referer` varchar(500) DEFAULT NULL,
  `viewed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_content` (`content_id`),
  KEY `idx_date` (`viewed_at`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `1` FOREIGN KEY (`content_id`) REFERENCES `content_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

INSERT INTO `views` (`id`, `content_id`, `user_id`, `ip_address`, `user_agent`, `referer`, `viewed_at`) VALUES ('1', '5', '1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'http://127.0.0.1:8080/category.php?slug=tutorials', '2026-09-11 17:17:20');
INSERT INTO `views` (`id`, `content_id`, `user_id`, `ip_address`, `user_agent`, `referer`, `viewed_at`) VALUES ('2', '6', '1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'http://127.0.0.1:8080/', '2026-09-11 17:25:02');
INSERT INTO `views` (`id`, `content_id`, `user_id`, `ip_address`, `user_agent`, `referer`, `viewed_at`) VALUES ('3', '4', '1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '', '2026-09-11 17:55:39');
INSERT INTO `views` (`id`, `content_id`, `user_id`, `ip_address`, `user_agent`, `referer`, `viewed_at`) VALUES ('4', '6', '1', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', 'http://127.0.0.1:8080/category.php?slug=%D9%85%D8%AD%D8%B5%D9%88%D9%84%D8%A7%D8%AA', '2026-09-11 21:32:18');



SET FOREIGN_KEY_CHECKS = 1;
