<?php
/**
 * PartoCMS - Import All Translations
 * یک بار اجرا کن — همه ترجمه‌ها را وارد می‌کند
 */
require_once __DIR__ . '/../config.php';

// این اسکریپت هم در CLI و هم در مرورگر کار می‌کند
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "<pre>";
}

$pdo = getDB();

// ============ همه ترجمه‌ها ============
$translations = [

'fa-IR' => [
    'menu_dashboard' => 'داشبورد',
    'menu_reports' => 'گزارش‌ها',
    'menu_seo' => 'تنظیمات SEO',
    'menu_backups' => 'پشتیبان‌گیری',
    'menu_backup_auto' => 'بکاپ خودکار',
    'menu_languages' => 'مدیریت زبان‌ها',
    'menu_security' => 'داشبورد امنیتی',
    'menu_telegram' => 'راه‌اندازی تلگرام',
    'menu_virustotal' => 'ویروس‌یاب VirusTotal',
    'menu_audit' => 'تست جامع امنیتی',
    'menu_graphs' => 'نمودارهای امنیتی',
    'menu_2fa' => 'احراز هویت دو مرحله‌ای',
    'menu_content' => 'مدیریت محتوا',
    'menu_tools' => 'ابزارها',
    'menu_appearance' => 'ظاهر سایت',
    'menu_users' => 'کاربران',
    'menu_settings' => 'تنظیمات',
    'menu_view_site' => 'مشاهده سایت',
    'menu_logout' => 'خروج',
    'menu_language' => 'زبان',
    'menu_posts' => 'مقالات و صفحات',
    'menu_categories' => 'دسته‌بندی‌ها',
    'menu_media' => 'رسانه‌ها',
    'menu_comments' => 'دیدگاه‌ها',
    'menu_forms' => 'فرم‌ساز',
    'menu_menus' => 'منوساز',
    'menu_templates' => 'مدیریت قالب‌ها',
    'menu_editor' => 'طراح قالب',
    'menu_users_list' => 'لیست کاربران',
    'menu_roles' => 'نقش‌ها و دسترسی',
    'menu_modules' => 'ماژول‌ها',
    'menu_settings_site' => 'تنظیمات سایت',
],

'en-US' => [
    'menu_dashboard' => 'Dashboard',
    'menu_reports' => 'Reports',
    'menu_seo' => 'SEO Settings',
    'menu_backups' => 'Backups',
    'menu_backup_auto' => 'Auto Backup',
    'menu_languages' => 'Languages',
    'menu_security' => 'Security Dashboard',
    'menu_telegram' => 'Telegram Setup',
    'menu_virustotal' => 'VirusTotal Scanner',
    'menu_audit' => 'Security Audit',
    'menu_graphs' => 'Security Graphs',
    'menu_2fa' => 'Two-Factor Authentication',
    'menu_content' => 'Content Management',
    'menu_tools' => 'Tools',
    'menu_appearance' => 'Appearance',
    'menu_users' => 'Users',
    'menu_settings' => 'Settings',
    'menu_view_site' => 'View Site',
    'menu_logout' => 'Logout',
    'menu_language' => 'Language',
    'menu_posts' => 'Posts & Pages',
    'menu_categories' => 'Categories',
    'menu_media' => 'Media',
    'menu_comments' => 'Comments',
    'menu_forms' => 'Form Builder',
    'menu_menus' => 'Menu Builder',
    'menu_templates' => 'Templates',
    'menu_editor' => 'Template Editor',
    'menu_users_list' => 'Users List',
    'menu_roles' => 'Roles & Permissions',
    'menu_modules' => 'Modules',
    'menu_settings_site' => 'Site Settings',
],

'en-GB' => [
    'menu_dashboard' => 'Dashboard',
    'menu_reports' => 'Reports',
    'menu_seo' => 'SEO Settings',
    'menu_backups' => 'Backups',
    'menu_backup_auto' => 'Automatic Backup',
    'menu_languages' => 'Languages',
    'menu_security' => 'Security Dashboard',
    'menu_telegram' => 'Telegram Setup',
    'menu_virustotal' => 'VirusTotal Scanner',
    'menu_audit' => 'Security Audit',
    'menu_graphs' => 'Security Charts',
    'menu_2fa' => 'Two-Factor Authentication',
    'menu_content' => 'Content Management',
    'menu_tools' => 'Tools',
    'menu_appearance' => 'Appearance',
    'menu_users' => 'Users',
    'menu_settings' => 'Settings',
    'menu_view_site' => 'View Site',
    'menu_logout' => 'Log out',
    'menu_language' => 'Language',
    'menu_posts' => 'Posts & Pages',
    'menu_categories' => 'Categories',
    'menu_media' => 'Media',
    'menu_comments' => 'Comments',
    'menu_forms' => 'Form Builder',
    'menu_menus' => 'Menu Builder',
    'menu_templates' => 'Templates',
    'menu_editor' => 'Template Editor',
    'menu_users_list' => 'Users List',
    'menu_roles' => 'Roles & Permissions',
    'menu_modules' => 'Modules',
    'menu_settings_site' => 'Site Settings',
],

'ar-SA' => [
    'menu_dashboard' => 'لوحة التحكم',
    'menu_reports' => 'التقارير',
    'menu_seo' => 'إعدادات SEO',
    'menu_backups' => 'النسخ الاحتياطي',
    'menu_backup_auto' => 'النسخ الاحتياطي التلقائي',
    'menu_languages' => 'إدارة اللغات',
    'menu_security' => 'لوحة الأمان',
    'menu_telegram' => 'إعداد تلغرام',
    'menu_virustotal' => 'ماسح VirusTotal',
    'menu_audit' => 'التدقيق الأمني',
    'menu_graphs' => 'الرسوم البيانية الأمنية',
    'menu_2fa' => 'المصادقة الثنائية',
    'menu_content' => 'إدارة المحتوى',
    'menu_tools' => 'الأدوات',
    'menu_appearance' => 'المظهر',
    'menu_users' => 'المستخدمون',
    'menu_settings' => 'الإعدادات',
    'menu_view_site' => 'عرض الموقع',
    'menu_logout' => 'تسجيل الخروج',
    'menu_language' => 'اللغة',
    'menu_posts' => 'المقالات والصفحات',
    'menu_categories' => 'التصنيفات',
    'menu_media' => 'الوسائط',
    'menu_comments' => 'التعليقات',
    'menu_forms' => 'منشئ النماذج',
    'menu_menus' => 'منشئ القوائم',
    'menu_templates' => 'القوالب',
    'menu_editor' => 'محرر القوالب',
    'menu_users_list' => 'قائمة المستخدمين',
    'menu_roles' => 'الأدوار والصلاحيات',
    'menu_modules' => 'الوحدات',
    'menu_settings_site' => 'إعدادات الموقع',
],

'tr-TR' => [
    'menu_dashboard' => 'Kontrol Paneli',
    'menu_reports' => 'Raporlar',
    'menu_seo' => 'SEO Ayarları',
    'menu_backups' => 'Yedekler',
    'menu_backup_auto' => 'Otomatik Yedekleme',
    'menu_languages' => 'Diller',
    'menu_security' => 'Güvenlik Paneli',
    'menu_telegram' => 'Telegram Kurulumu',
    'menu_virustotal' => 'VirusTotal Tarayıcı',
    'menu_audit' => 'Güvenlik Denetimi',
    'menu_graphs' => 'Güvenlik Grafikleri',
    'menu_2fa' => 'İki Faktörlü Kimlik Doğrulama',
    'menu_content' => 'İçerik Yönetimi',
    'menu_tools' => 'Araçlar',
    'menu_appearance' => 'Görünüm',
    'menu_users' => 'Kullanıcılar',
    'menu_settings' => 'Ayarlar',
    'menu_view_site' => 'Siteyi Görüntüle',
    'menu_logout' => 'Çıkış Yap',
    'menu_language' => 'Dil',
    'menu_posts' => 'Yazılar ve Sayfalar',
    'menu_categories' => 'Kategoriler',
    'menu_media' => 'Medya',
    'menu_comments' => 'Yorumlar',
    'menu_forms' => 'Form Oluşturucu',
    'menu_menus' => 'Menü Oluşturucu',
    'menu_templates' => 'Şablonlar',
    'menu_editor' => 'Şablon Düzenleyici',
    'menu_users_list' => 'Kullanıcı Listesi',
    'menu_roles' => 'Roller ve İzinler',
    'menu_modules' => 'Modüller',
    'menu_settings_site' => 'Site Ayarları',
],

'de-DE' => [
    'menu_dashboard' => 'Dashboard',
    'menu_reports' => 'Berichte',
    'menu_seo' => 'SEO-Einstellungen',
    'menu_backups' => 'Sicherungen',
    'menu_backup_auto' => 'Automatische Sicherung',
    'menu_languages' => 'Sprachen',
    'menu_security' => 'Sicherheits-Dashboard',
    'menu_telegram' => 'Telegram-Einrichtung',
    'menu_virustotal' => 'VirusTotal-Scanner',
    'menu_audit' => 'Sicherheitsprüfung',
    'menu_graphs' => 'Sicherheitsdiagramme',
    'menu_2fa' => 'Zwei-Faktor-Authentifizierung',
    'menu_content' => 'Inhaltsverwaltung',
    'menu_tools' => 'Werkzeuge',
    'menu_appearance' => 'Erscheinungsbild',
    'menu_users' => 'Benutzer',
    'menu_settings' => 'Einstellungen',
    'menu_view_site' => 'Website anzeigen',
    'menu_logout' => 'Abmelden',
    'menu_language' => 'Sprache',
    'menu_posts' => 'Beiträge und Seiten',
    'menu_categories' => 'Kategorien',
    'menu_media' => 'Medien',
    'menu_comments' => 'Kommentare',
    'menu_forms' => 'Formular-Generator',
    'menu_menus' => 'Menü-Generator',
    'menu_templates' => 'Vorlagen',
    'menu_editor' => 'Vorlagen-Editor',
    'menu_users_list' => 'Benutzerliste',
    'menu_roles' => 'Rollen und Berechtigungen',
    'menu_modules' => 'Module',
    'menu_settings_site' => 'Website-Einstellungen',
],

'fr-FR' => [
    'menu_dashboard' => 'Tableau de bord',
    'menu_reports' => 'Rapports',
    'menu_seo' => 'Paramètres SEO',
    'menu_backups' => 'Sauvegardes',
    'menu_backup_auto' => 'Sauvegarde automatique',
    'menu_languages' => 'Langues',
    'menu_security' => 'Tableau de bord de sécurité',
    'menu_telegram' => 'Configuration Telegram',
    'menu_virustotal' => 'Scanner VirusTotal',
    'menu_audit' => 'Audit de sécurité',
    'menu_graphs' => 'Graphiques de sécurité',
    'menu_2fa' => 'Authentification à deux facteurs',
    'menu_content' => 'Gestion du contenu',
    'menu_tools' => 'Outils',
    'menu_appearance' => 'Apparence',
    'menu_users' => 'Utilisateurs',
    'menu_settings' => 'Paramètres',
    'menu_view_site' => 'Voir le site',
    'menu_logout' => 'Déconnexion',
    'menu_language' => 'Langue',
    'menu_posts' => 'Articles et pages',
    'menu_categories' => 'Catégories',
    'menu_media' => 'Médias',
    'menu_comments' => 'Commentaires',
    'menu_forms' => 'Générateur de formulaires',
    'menu_menus' => 'Générateur de menus',
    'menu_templates' => 'Modèles',
    'menu_editor' => 'Éditeur de modèles',
    'menu_users_list' => 'Liste des utilisateurs',
    'menu_roles' => 'Rôles et permissions',
    'menu_modules' => 'Modules',
    'menu_settings_site' => 'Paramètres du site',
],

'es-ES' => [
    'menu_dashboard' => 'Panel de control',
    'menu_reports' => 'Informes',
    'menu_seo' => 'Configuración SEO',
    'menu_backups' => 'Copias de seguridad',
    'menu_backup_auto' => 'Copia de seguridad automática',
    'menu_languages' => 'Idiomas',
    'menu_security' => 'Panel de seguridad',
    'menu_telegram' => 'Configuración de Telegram',
    'menu_virustotal' => 'Escáner VirusTotal',
    'menu_audit' => 'Auditoría de seguridad',
    'menu_graphs' => 'Gráficos de seguridad',
    'menu_2fa' => 'Autenticación de dos factores',
    'menu_content' => 'Gestión de contenido',
    'menu_tools' => 'Herramientas',
    'menu_appearance' => 'Apariencia',
    'menu_users' => 'Usuarios',
    'menu_settings' => 'Configuración',
    'menu_view_site' => 'Ver sitio',
    'menu_logout' => 'Cerrar sesión',
    'menu_language' => 'Idioma',
    'menu_posts' => 'Publicaciones y páginas',
    'menu_categories' => 'Categorías',
    'menu_media' => 'Medios',
    'menu_comments' => 'Comentarios',
    'menu_forms' => 'Generador de formularios',
    'menu_menus' => 'Generador de menús',
    'menu_templates' => 'Plantillas',
    'menu_editor' => 'Editor de plantillas',
    'menu_users_list' => 'Lista de usuarios',
    'menu_roles' => 'Roles y permisos',
    'menu_modules' => 'Módulos',
    'menu_settings_site' => 'Configuración del sitio',
],

'ru-RU' => [
    'menu_dashboard' => 'Панель управления',
    'menu_reports' => 'Отчёты',
    'menu_seo' => 'Настройки SEO',
    'menu_backups' => 'Резервные копии',
    'menu_backup_auto' => 'Автоматическое резервное копирование',
    'menu_languages' => 'Языки',
    'menu_security' => 'Панель безопасности',
    'menu_telegram' => 'Настройка Telegram',
    'menu_virustotal' => 'Сканер VirusTotal',
    'menu_audit' => 'Аудит безопасности',
    'menu_graphs' => 'Графики безопасности',
    'menu_2fa' => 'Двухфакторная аутентификация',
    'menu_content' => 'Управление контентом',
    'menu_tools' => 'Инструменты',
    'menu_appearance' => 'Внешний вид',
    'menu_users' => 'Пользователи',
    'menu_settings' => 'Настройки',
    'menu_view_site' => 'Просмотр сайта',
    'menu_logout' => 'Выход',
    'menu_language' => 'Язык',
    'menu_posts' => 'Записи и страницы',
    'menu_categories' => 'Категории',
    'menu_media' => 'Медиа',
    'menu_comments' => 'Комментарии',
    'menu_forms' => 'Конструктор форм',
    'menu_menus' => 'Конструктор меню',
    'menu_templates' => 'Шаблоны',
    'menu_editor' => 'Редактор шаблонов',
    'menu_users_list' => 'Список пользователей',
    'menu_roles' => 'Роли и разрешения',
    'menu_modules' => 'Модули',
    'menu_settings_site' => 'Настройки сайта',
],

'zh-CN' => [
    'menu_dashboard' => '仪表板',
    'menu_reports' => '报告',
    'menu_seo' => 'SEO 设置',
    'menu_backups' => '备份',
    'menu_backup_auto' => '自动备份',
    'menu_languages' => '语言',
    'menu_security' => '安全仪表板',
    'menu_telegram' => 'Telegram 设置',
    'menu_virustotal' => 'VirusTotal 扫描器',
    'menu_audit' => '安全审计',
    'menu_graphs' => '安全图表',
    'menu_2fa' => '双因素认证',
    'menu_content' => '内容管理',
    'menu_tools' => '工具',
    'menu_appearance' => '外观',
    'menu_users' => '用户',
    'menu_settings' => '设置',
    'menu_view_site' => '查看网站',
    'menu_logout' => '登出',
    'menu_language' => '语言',
    'menu_posts' => '文章和页面',
    'menu_categories' => '分类',
    'menu_media' => '媒体',
    'menu_comments' => '评论',
    'menu_forms' => '表单生成器',
    'menu_menus' => '菜单生成器',
    'menu_templates' => '模板',
    'menu_editor' => '模板编辑器',
    'menu_users_list' => '用户列表',
    'menu_roles' => '角色和权限',
    'menu_modules' => '模块',
    'menu_settings_site' => '网站设置',
],

];

// ============ ذخیره در دیتابیس ============
$totalKeys = 0;
$totalTranslations = 0;
$languagesProcessed = 0;

echo "═══════════════════════════════════════\n";
echo "  🌍 Import Translations\n";
echo "═══════════════════════════════════════\n\n";

try {
    foreach ($translations as $langCode => $keys) {
        // پیدا کردن زبان
        $stmt = $pdo->prepare("SELECT id, native_name FROM languages WHERE code = ?");
        $stmt->execute([$langCode]);
        $lang = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lang) {
            echo "⚠️  زبان {$langCode} در دیتابیس نیست — رد شد\n";
            continue;
        }

        $langId = $lang['id'];
        $inserted = 0;

        foreach ($keys as $keyName => $value) {
            // پیدا کردن یا ساخت کلید
            $stmt = $pdo->prepare("SELECT id FROM translation_keys WHERE `key` = ?");
            $stmt->execute([$keyName]);
            $keyId = $stmt->fetchColumn();

            if (!$keyId) {
                $stmt = $pdo->prepare("INSERT INTO translation_keys (`key`, category) VALUES (?, 'sidebar')");
                $stmt->execute([$keyName]);
                $keyId = $pdo->lastInsertId();
                $totalKeys++;
            }

            // ذخیره ترجمه
            $stmt = $pdo->prepare("
                INSERT INTO translations (language_id, key_id, `value`) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");
            $stmt->execute([$langId, $keyId, $value]);
            $inserted++;
            $totalTranslations++;
        }

        echo "✅ {$langCode} ({$lang['native_name']}) — {$inserted} ترجمه\n";
        $languagesProcessed++;
    }

    echo "\n═══════════════════════════════════════\n";
    echo "  📊 خلاصه\n";
    echo "═══════════════════════════════════════\n";
    echo "🌍 زبان‌های پردازش‌شده: {$languagesProcessed}\n";
    echo "🔑 کلیدهای جدید: {$totalKeys}\n";
    echo "📝 کل ترجمه‌ها: {$totalTranslations}\n";
    echo "\n✅ همه ترجمه‌ها وارد شد!\n";

} catch (Throwable $e) {
    echo "❌ خطا: " . $e->getMessage() . "\n";
}

if (!$isCli) echo "</pre>";
