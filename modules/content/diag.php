<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='UTF-8'><title>تشخیص</title>";
echo "<style>body{font-family:Tahoma;padding:20px;background:#f4f6f9;} h3{color:#2c3e50;border-bottom:2px solid #3498db;padding-bottom:5px;} .ok{color:green;font-weight:bold;} .err{color:red;font-weight:bold;}</style>";
echo "</head><body>";

echo "<h1>🔍 تشخیص مشکل</h1>";
echo "<h3>1. مسیر فایل‌ها</h3>";
echo "این فایل: <code>" . __FILE__ . "</code><br>";
echo "config.php: <code>" . dirname(__DIR__, 2) . "/config.php</code><br>";
echo "config موجوده؟ " . (file_exists(dirname(__DIR__, 2) . '/config.php') ? '<span class="ok">✅ بله</span>' : '<span class="err">❌ خیر</span>') . "<br>";

echo "<h3>2. لود config</h3>";
try {
    require_once dirname(__DIR__, 2) . '/config.php';
    echo "<span class='ok'>✅ config.php لود شد</span><br>";
} catch (Exception $e) {
    echo "<span class='err'>❌ خطا: " . $e->getMessage() . "</span><br>";
    exit;
}

echo "<h3>3. توابع موجود</h3>";
echo "getPermissions: " . (function_exists('getPermissions') ? '<span class="ok">✅</span>' : '<span class="err">❌</span>') . "<br>";
echo "getModuleManager: " . (function_exists('getModuleManager') ? '<span class="ok">✅</span>' : '<span class="err">❌</span>') . "<br>";
echo "isLoggedIn: " . (function_exists('isLoggedIn') ? '<span class="ok">✅</span>' : '<span class="err">❌</span>') . "<br>";
echo "getDB: " . (function_exists('getDB') ? '<span class="ok">✅</span>' : '<span class="err">❌</span>') . "<br>";

echo "<h3>4. سشن</h3>";
echo "isLoggedIn: " . (isLoggedIn() ? '<span class="ok">✅ لاگین هستی</span>' : '<span class="err">❌ لاگین نیستی</span>') . "<br>";
if (isLoggedIn()) {
    echo "User ID: " . ($_SESSION['user_id'] ?? '?') . "<br>";
    echo "Username: " . ($_SESSION['username'] ?? '?') . "<br>";
    echo "Role: " . ($_SESSION['role'] ?? '?') . "<br>";
}

echo "<h3>5. دسترسی‌ها</h3>";
if (isLoggedIn()) {
    try {
        $perm = getPermissions();
        echo "content.view: " . ($perm->can($_SESSION['user_id'], 'content.view') ? '<span class="ok">✅ بله</span>' : '<span class="err">❌ خیر</span>') . "<br>";
        echo "content.create: " . ($perm->can($_SESSION['user_id'], 'content.create') ? '<span class="ok">✅ بله</span>' : '<span class="err">❌ خیر</span>') . "<br>";
        echo "modules.manage: " . ($perm->can($_SESSION['user_id'], 'modules.manage') ? '<span class="ok">✅ بله</span>' : '<span class="err">❌ خیر</span>') . "<br>";
    } catch (Exception $e) {
        echo "<span class='err'>❌ خطا: " . $e->getMessage() . "</span><br>";
    }
}

echo "<h3>6. دیتابیس</h3>";
try {
    $pdo = getDB();
    echo "<span class='ok'>✅ اتصال DB موفق</span><br>";
    echo "تعداد content_items: " . $pdo->query("SELECT COUNT(*) FROM content_items")->fetchColumn() . "<br>";
    echo "تعداد content_types: " . $pdo->query("SELECT COUNT(*) FROM content_types")->fetchColumn() . "<br>";
    echo "تعداد permissions: " . $pdo->query("SELECT COUNT(*) FROM permissions")->fetchColumn() . "<br>";
} catch (Exception $e) {
    echo "<span class='err'>❌ خطا: " . $e->getMessage() . "</span><br>";
}

echo "<h3>7. لینک‌های تست</h3>";
echo "<a href='admin.php' style='display:inline-block;padding:10px 20px;background:#3498db;color:#fff;text-decoration:none;border-radius:5px;margin:5px;'>→ admin.php (مدیریت محتوا)</a><br>";
echo "<a href='/admin/templates.php' style='display:inline-block;padding:10px 20px;background:#95a5a6;color:#fff;text-decoration:none;border-radius:5px;margin:5px;'>→ templates.php (لیست قالب‌ها)</a>";

echo "</body></html>";
