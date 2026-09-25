<?php
/**
 * Admin Panel - contacts
 * @date 2026-09-19 11:29
 */
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/helpers.php';

if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ' . SITE_URL . '/admin/index.php'); exit; }
if (!isset($pdo)) $pdo = crud_db();

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $res = crud_delete((int)$_GET['delete']);
    $msg = !empty($res['ok']) ? 'deleted' : 'error';
    header('Location: ' . '/modules/generated/contacts/admin.php?msg=' . $msg);
    exit;
}

$messages = [
    'saved'    => '✅ با موفقیت ذخیره شد',
    'deleted'  => '🗑 با موفقیت حذف شد',
    'notfound' => '⚠️ رکورد یافت نشد',
    'error'    => '❌ خطایی رخ داد',
];
$msgKey = $_GET['msg'] ?? '';
$message = $messages[$msgKey] ?? '';

$search = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
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
<title>مخاطبین | مدیریت</title>
<script src="<?= SITE_URL ?>/assets/vendor/tailwind.js"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>body{font-family:Tahoma,sans-serif;background:#f1f5f9}</style>
</head>
<body>
<?php $sf = __DIR__ . '/../../../admin/includes/sidebar.php'; if (file_exists($sf)) require_once $sf; ?>
<div class="mr-[260px] p-5 max-md:mr-0 max-md:pt-16 min-h-screen">
  <div class="max-w-7xl mx-auto">

    <div class="bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-2xl p-6 mb-6 shadow-lg">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold flex items-center gap-2"><span>📇</span> مخاطبین</h1>
          <p class="text-sm opacity-80 mt-1"><?= number_format($total) ?> رکورد — صفحه <?= $page ?> از <?= $totalPages ?></p>
        </div>
        <a href="/modules/generated/contacts/form.php" class="inline-flex items-center gap-2 bg-white text-indigo-700 hover:bg-indigo-50 px-4 py-2.5 rounded-lg text-sm font-bold shadow transition"><i class="bi bi-plus-lg"></i> افزودن جدید</a>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="bg-emerald-50 border-r-4 border-emerald-500 text-emerald-700 p-4 rounded-lg mb-4"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="GET" class="bg-white rounded-xl shadow-sm p-4 mb-5 flex gap-2 flex-wrap items-center">
      <div class="flex-1 min-w-[200px] relative">
        <i class="bi bi-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="جستجو..." class="w-full pr-10 pl-3 py-2.5 rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none text-sm">
      </div>
      <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition">🔍 جستجو</button>
      <?php if ($search): ?><a href="/modules/generated/contacts/admin.php" class="px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm">✖ پاک کردن</a><?php endif; ?>
    </form>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <?php if (empty($rows)): ?>
        <div class="text-center py-20 text-gray-400">
          <i class="bi bi-inbox text-6xl"></i>
          <p class="mt-4 text-lg">هیچ رکوردی یافت نشد</p>
          <?php if ($search): ?><p class="text-sm mt-1">نتیجه‌ای برای «<?= htmlspecialchars($search) ?>» پیدا نشد</p><?php endif; ?>
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
              <th class="px-4 py-3 text-right font-bold text-gray-600">#</th>
              <th class="px-4 py-3 text-right font-bold text-gray-600">شناسه</th>
              <th class="px-4 py-3 text-right font-bold text-gray-600">نام</th>
              <th class="px-4 py-3 text-right font-bold text-gray-600">نام خانوادگی</th>
              <th class="px-4 py-3 text-right font-bold text-gray-600">ایمیل</th>
              <th class="px-4 py-3 text-right font-bold text-gray-600">تلفن</th>
              <th class="px-4 py-3 text-right font-bold text-gray-600">موبایل</th>
              <th class="px-4 py-3 text-center font-bold text-gray-600 w-32">عملیات</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $i => $row): ?>
            <tr class="border-b border-gray-100 hover:bg-indigo-50/40 transition">
              <td class="px-4 py-3 text-gray-500 text-xs"><?= $offset + $i + 1 ?></td>
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string)($row['id'] ?? '—')) ?></td>
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string)($row['first_name'] ?? '—')) ?></td>
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string)($row['last_name'] ?? '—')) ?></td>
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string)($row['email'] ?? '—')) ?></td>
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string)($row['phone'] ?? '—')) ?></td>
              <td class="px-4 py-3 text-gray-800"><?= htmlspecialchars((string)($row['mobile'] ?? '—')) ?></td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1">
                  <a href="/modules/generated/contacts/form.php?id=<?= urlencode($row['id'] ?? '') ?>" class="p-2 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition" title="ویرایش"><i class="bi bi-pencil"></i></a>
                  <a href="/modules/generated/contacts/admin.php?delete=<?= urlencode($row['id'] ?? '') ?>" onclick="return confirm('آیا از حذف این رکورد مطمئن هستید؟')" class="p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition" title="حذف"><i class="bi bi-trash"></i></a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center items-center gap-1 mt-6 flex-wrap">
      <?php $qs = $search ? '&q=' . urlencode($search) : ''; ?>
      <?php if ($page > 1): ?>
        <a href="/modules/generated/contacts/admin.php?page=<?= $page - 1 ?><?= $qs ?>" class="px-3 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 text-sm shadow-sm">← قبلی</a>
      <?php endif; ?>
      <?php
      $start = max(1, $page - 3);
      $end = min($totalPages, $page + 3);
      for ($p = $start; $p <= $end; $p++):
      ?>
        <?php if ($p == $page): ?>
          <span class="px-4 py-2 bg-gradient-to-l from-purple-600 to-indigo-700 text-white font-bold rounded-lg text-sm shadow"><?= $p ?></span>
        <?php else: ?>
          <a href="/modules/generated/contacts/admin.php?page=<?= $p ?><?= $qs ?>" class="px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 text-sm shadow-sm"><?= $p ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $totalPages): ?>
        <a href="/modules/generated/contacts/admin.php?page=<?= $page + 1 ?><?= $qs ?>" class="px-3 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 text-sm shadow-sm">بعدی →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
