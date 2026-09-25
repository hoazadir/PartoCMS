<?php
/**
 * Frontend List - contacts
 * @date 2026-09-19 11:01
 */
require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/helpers.php';

if (!isset($pdo)) $pdo = crud_db();

$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$total = crud_count($search);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$rows = crud_getAll(['limit' => $perPage, 'offset' => $offset, 'search' => $search]);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>مخاطبین</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>body{font-family:Tahoma,sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e0e7ff 100%);min-height:100vh}</style>
</head>
<body>
<div class="max-w-6xl mx-auto p-5">

  <header class="bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-2xl p-8 mb-6 shadow-xl">
    <h1 class="text-2xl font-bold flex items-center gap-3"><span class="text-3xl">📇</span> مخاطبین</h1>
    <p class="text-sm opacity-90 mt-2"><?= number_format($total) ?> آیتم موجود</p>
  </header>

  <form method="GET" class="mb-6">
    <div class="relative">
      <i class="bi bi-search absolute right-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="جستجو در مخاطبین..." class="w-full pr-12 pl-4 py-3.5 rounded-xl border border-white shadow-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none text-sm bg-white">
    </div>
  </form>

  <?php if (empty($rows)): ?>
    <div class="bg-white rounded-2xl shadow-lg text-center py-20 text-gray-400">
      <i class="bi bi-inbox text-6xl"></i>
      <p class="mt-4 text-lg">هیچ آیتمی یافت نشد</p>
      <?php if ($search): ?><p class="text-sm mt-1">نتیجه‌ای برای «<?= htmlspecialchars($search) ?>» پیدا نشد</p><?php endif; ?>
    </div>
  <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($rows as $row): ?>
        <a href="/modules/generated/contacts/view.php?id=<?= urlencode($row['id'] ?? '') ?>" class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden block">
          <div class="h-32 bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-5xl group-hover:scale-110 transition-transform">
            <i class="bi bi-file-earmark-text"></i>
          </div>
          <div class="p-5">
            <h3 class="font-bold text-gray-800 text-base truncate mb-2"><?= htmlspecialchars((string)($row['first_name'] ?? '—')) ?></h3>
            <p class="text-xs text-gray-500 leading-relaxed" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= htmlspecialchars(mb_substr(strip_tags((string)($row['notes'] ?? '')), 0, 100)) ?></p>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-2 mt-8 flex-wrap">
      <?php $qs = $search ? '&q=' . urlencode($search) : ''; ?>
      <?php if ($page > 1): ?><a href="/modules/generated/contacts/list.php?page=<?= $page - 1 ?><?= $qs ?>" class="px-4 py-2.5 bg-white text-gray-700 rounded-xl hover:bg-indigo-50 shadow-md text-sm font-medium">← قبلی</a><?php endif; ?>
      <?php for ($p = max(1, $page - 3); $p <= min($totalPages, $page + 3); $p++): ?>
        <?php if ($p == $page): ?>
          <span class="px-4 py-2.5 bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-xl font-bold shadow-lg text-sm"><?= $p ?></span>
        <?php else: ?>
          <a href="/modules/generated/contacts/list.php?page=<?= $p ?><?= $qs ?>" class="px-4 py-2.5 bg-white text-gray-700 rounded-xl hover:bg-indigo-50 shadow-md text-sm font-medium"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?><a href="/modules/generated/contacts/list.php?page=<?= $page + 1 ?><?= $qs ?>" class="px-4 py-2.5 bg-white text-gray-700 rounded-xl hover:bg-indigo-50 shadow-md text-sm font-medium">بعدی →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
</body>
</html>
