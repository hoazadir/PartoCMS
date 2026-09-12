<?php
// api/load.php
require_once __DIR__ . '/../config.php';

jsonHeader();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['status' => 'error', 'message' => 'متد غیرمجاز'], 405);
}

$templateId = $_GET['id'] ?? null;

$pdo = getDB();

try {
    if ($templateId && is_numeric($templateId)) {
        $stmt = $pdo->prepare("SELECT * FROM templates WHERE id = ?");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();
        
        if ($template) {
            jsonResponse([
                'gjs-components' => $template['components'] ?: '[]',
                'gjs-styles' => $template['styles'] ?: '[]',
                'gjs-html' => $template['content'] ?: '',
                'gjs-css' => $template['css'] ?: '',
                'gjs-assets' => '[]',
                'gjs-pages' => '[]',
                'name' => $template['name'],
                'description' => $template['description'],
            ]);
        }
    }
    
    // اگر قالب مشخص نشده باشد، قالب پیش‌فرض را برمی‌گرداند
    $stmt = $pdo->query("SELECT * FROM templates WHERE is_default = 1 LIMIT 1");
    $default = $stmt->fetch();
    
    if ($default) {
        jsonResponse([
            'gjs-components' => $default['components'] ?: '[]',
            'gjs-styles' => $default['styles'] ?: '[]',
            'gjs-html' => $default['content'] ?: '',
            'gjs-css' => $default['css'] ?: '',
            'gjs-assets' => '[]',
            'gjs-pages' => '[]',
        ]);
    }
    
    // پاسخ خالی
    jsonResponse([
        'gjs-components' => '[]',
        'gjs-styles' => '[]',
        'gjs-html' => '',
        'gjs-css' => '',
        'gjs-assets' => '[]',
        'gjs-pages' => '[]',
    ]);
    
} catch (PDOException $e) {
    error_log('Load Error: ' . $e->getMessage());
    jsonResponse([
        'gjs-components' => '[]',
        'gjs-styles' => '[]',
        'gjs-html' => '',
        'gjs-css' => '',
        'gjs-assets' => '[]',
        'gjs-pages' => '[]',
    ]);
}
