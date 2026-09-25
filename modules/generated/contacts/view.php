<?php
/**
 * Frontend View - contacts
 * @date 2026-09-19 11:29
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/helpers.php';

if (!isset($pdo)) $pdo = crud_db();

$id = $_GET['id'] ?? null;
$item = $id !== null ? crud_getOne($id) : null;
if (!$item) { http_response_code(404); }

$pageTitle = $item ? ($item['first_name'] ?? 'مخاطبین') : 'یافت نشد';

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>
<script src="<?= SITE_URL ?>/assets/vendor/tailwind.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>body{font-family:Tahoma,sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e0e7ff 100%);min-height:100vh}</style>
</head>
<body>
<div class="max-w-3xl mx-auto p-5">
  <a href="/modules/generated/contacts/list.php" class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-medium text-sm mb-5"><i class="bi bi-arrow-right"></i> بازگشت به لیست</a>

  <?php if (!$item): ?>
    <div class="bg-white rounded-2xl shadow-xl text-center py-20">
      <i class="bi bi-exclamation-triangle text-6xl text-yellow-500"></i>
      <h2 class="text-xl font-bold text-gray-700 mt-4">آیتم یافت نشد</h2>
      <p class="text-gray-500 text-sm mt-2">موردی که به دنبال آن هستید وجود ندارد یا حذف شده است.</p>
    </div>
  <?php else: ?>
    <article class="bg-white rounded-2xl shadow-xl overflow-hidden">
      <div class="bg-gradient-to-l from-purple-600 to-indigo-700 text-white p-8">
        <div class="flex items-center gap-3 mb-2">
          <span class="text-3xl">📇</span>
          <h1 class="text-2xl font-bold"><?= htmlspecialchars((string)($item['first_name'] ?? '—')) ?></h1>
        </div>
        <p class="text-sm opacity-80">شناسه: <?= htmlspecialchars((string)($item['id'] ?? '')) ?></p>
      </div>

      <div class="p-8">
        <?php $body = (string)($item['notes'] ?? ''); ?>
        <?php if ($body): ?>
          <div class="text-gray-700 leading-loose whitespace-pre-wrap mb-8 text-sm"><?= htmlspecialchars($body) ?></div>
        <?php endif; ?>
        <div class="border-t border-gray-200 pt-6">
          <h3 class="text-sm font-bold text-gray-500 mb-4">📋 جزئیات کامل</h3>
          <dl class="space-y-3">
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">شناسه</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['id'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">🔢 شناسه</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['id'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">👤 نام خانوادگی</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['last_name'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">✉️ ایمیل</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['email'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">📞 تلفن</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['phone'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">📱 موبایل</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['mobile'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">📍 آدرس</dt>
              <dd class="text-sm text-gray-800 whitespace-pre-wrap"><?= htmlspecialchars((string)($item['address'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">📅 تاریخ تولد</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['birth_date'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">🏷 دسته‌بندی</dt>
              <dd class="text-sm text-gray-800"><?php $opts = crud_fkOptions('categories'); $lbl = '—'; foreach ($opts as $o) { if ((string)$o['id'] === (string)($item['category_id'] ?? '')) { $lbl = $o['label']; break; } } echo htmlspecialchars($lbl); ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">☑️ فعال</dt>
              <dd class="text-sm text-gray-800"><?= !empty($item['is_active']) ? '✅ بله' : '➖ خیر' ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">🕒 تاریخ ایجاد</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['created_at'] ?? '—')) ?></dd>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100">
              <dt class="text-sm font-medium text-gray-500 sm:w-40 shrink-0">📅 تاریخ بروزرسانی</dt>
              <dd class="text-sm text-gray-800"><?= htmlspecialchars((string)($item['updated_at'] ?? '—')) ?></dd>
            </div>
          </dl>
        </div>
      </div>
    </article>
  <?php endif; ?>
</div>
</body>
</html>
