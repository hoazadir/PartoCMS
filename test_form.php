<?php
/**
 * Form (Add/Edit) - contacts
 * تولید خودکار توسط PartoCMS Table Builder
 * @date 2026-09-19 10:56
 */
require_once __DIR__ . '/../../../admin/auth_check.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../../../modules/table_builder/includes/TableBuilder.php';
require_once __DIR__ . '/../../../modules/table_builder/includes/FormBuilder.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo)) $pdo = crud_db();

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$fields = $config['fields'] ?? [];

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = $id > 0;
$item = $isEdit ? crud_getOne($id) : null;

if ($isEdit && !$item) {
    header('Location: ' . '/modules/generated/contacts/admin.php?msg=notfound');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = crud_sanitize($_POST, $fields);
    foreach ($fields as $f) {
        if (!empty($f['required'])) {
            $v = $data[$f['name']] ?? null;
            if ($v === null || $v === '') {
                $errors[$f['name']] = 'این فیلد اجباری است';
            }
        }
    }
    if (empty($errors)) {
        $res = $isEdit ? crud_update($id, $data) : crud_insert($data);
        if (!empty($res['ok'])) {
            header('Location: ' . '/modules/generated/contacts/admin.php?msg=saved');
            exit;
        } else {
            $errors['_global'] = $res['error'] ?? 'خطای ناشناخته';
        }
    }
}

$formFields = [];
foreach ($fields as $f) {
    if (!empty($f['hidden']) || !empty($f['system'])) continue;
    $formFields[] = $f;
}
$analysis = ['form_fields' => $formFields, 'table' => crud_table(), 'primary_key' => CRUD_PK];

$tb = new TableBuilder($pdo);
$fb = new FormBuilder($tb);

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'ویرایش' : 'افزودن' ?> - مخاطبین</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>body{font-family:Tahoma,sans-serif;background:#f1f5f9}</style>
</head>
<body>
<?php $sidebarFile = __DIR__ . '/../../../admin/includes/sidebar.php'; if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="mr-[260px] p-5 max-md:mr-0 max-md:pt-16 min-h-screen">
  <div class="max-w-4xl mx-auto">
    <div class="bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-2xl p-6 mb-6 shadow-lg">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h1 class="text-xl font-bold flex items-center gap-2">
            <span>📇</span>
            <?= $isEdit ? '✏️ ویرایش' : '➕ افزودن' ?> مخاطبین
          </h1>
          <p class="text-sm opacity-80 mt-1"><?= $isEdit ? 'شناسه: ' . $id : 'ثبت رکورد جدید' ?></p>
        </div>
        <a href="/modules/generated/contacts/admin.php" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-medium transition">
          <i class="bi bi-arrow-right"></i> بازگشت
        </a>
      </div>
    </div>
    <?php if (!empty($errors['_global'])): ?>
    <div class="bg-red-50 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-4">
      ❌ <?= htmlspecialchars($errors['_global']) ?>
    </div>
    <?php endif; ?>
    <div class="bg-white rounded-2xl shadow-sm p-6">
      <?php
      echo $fb->setAction('')->setMethod('POST')->renderForm($analysis, $item ?: [], [
          'action' => '', 'method' => 'POST',
          'submit_label' => $isEdit ? '💾 بروزرسانی' : '💾 ذخیره',
          'cancel_url' => '/modules/generated/contacts/admin.php',
          'errors' => $errors,
      ]);
      ?>
    </div>
  </div>
</div>
</body>
</html>
