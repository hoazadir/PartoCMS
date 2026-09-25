<?php
/**
 * PartoCMS - CRUD Generator
 * @version 1.0
 * @date 2026-09-19
 */
class CrudGenerator {

    private $pdo;
    private $tb;
    private $ma;
    private $fb;

    public function __construct($pdo, $tb, $ma, $fb) {
        $this->pdo = $pdo;
        $this->tb  = $tb;
        $this->ma  = $ma;
        $this->fb  = $fb;
    }

    // ═══════════════════════════════════════════════════════════
    //  helpers.php
    // ═══════════════════════════════════════════════════════════
    public function renderHelpers(array $a, array $opts): string {
        $table = $a['table']; $pk = $a['primary_key'];
        $searchable = $a['searchable'] ?? [];
        $searchList = !empty($searchable) ? "'" . implode("', '", $searchable) . "'" : '';

        $c  = "<?php\n/**\n * Helpers - {$table}\n * تولید خودکار\n * @date " . date('Y-m-d H:i') . "\n */\n\n";
        $c .= "if (!defined('CRUD_TABLE')) {\n";
        $c .= "    define('CRUD_TABLE', '" . addslashes($table) . "');\n";
        $c .= "    define('CRUD_PK', '" . addslashes($pk) . "');\n";
        $c .= "    define('CRUD_SORT_FIELD', '" . addslashes($a['sort_field']) . "');\n";
        $c .= "    define('CRUD_SORT_DIR', '" . addslashes($a['sort_direction']) . "');\n";
        $c .= "    define('CRUD_SEARCHABLE', [" . $searchList . "]);\n";
        $c .= "}\n\n";
        $c .= "if (!function_exists('crud_table')) {\n";
        $c .= "    function crud_table() { return CRUD_TABLE; }\n";
        $c .= "}\n\n";
        $c .= "if (!function_exists('crud_pk')) {\n";
        $c .= "    function crud_pk() { return CRUD_PK; }\n";
        $c .= "}\n\n";
        $c .= "if (!function_exists('crud_db')) {\n    function crud_db() {\n        if (!isset(\$GLOBALS['pdo']) || !\$GLOBALS['pdo']) {\n            if (function_exists('getDB')) \$GLOBALS['pdo'] = getDB();\n        }\n        return \$GLOBALS['pdo'];\n    }\n}\n\n";
        $c .= "if (!function_exists('crud_safeId')) {\n    function crud_safeId(\$id) {\n        return preg_replace('/[^a-zA-Z0-9_]/', '', (string)\$id);\n    }\n}\n\n";
        $c .= "if (!function_exists('crud_getAll')) {\n    function crud_getAll(array \$opts = []): array {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return [];\n";
        $c .= "        \$table = CRUD_TABLE;\n";
        $c .= "        \$limit = max(1, (int)(\$opts['limit'] ?? 20));\n";
        $c .= "        \$offset = max(0, (int)(\$opts['offset'] ?? 0));\n";
        $c .= "        \$search = trim((string)(\$opts['search'] ?? ''));\n";
        $c .= "        \$orderBy = \$opts['order_by'] ?? CRUD_SORT_FIELD;\n";
        $c .= "        \$orderDir = strtoupper(\$opts['order_dir'] ?? CRUD_SORT_DIR);\n";
        $c .= "        \$orderDir = \$orderDir === 'ASC' ? 'ASC' : 'DESC';\n";
        $c .= "        \$where = ''; \$params = [];\n";
        $c .= "        if (\$search !== '' && !empty(CRUD_SEARCHABLE)) {\n";
        $c .= "            \$parts = [];\n";
        $c .= "            foreach (CRUD_SEARCHABLE as \$col) { \$parts[] = \"`\$col` LIKE ?\"; \$params[] = '%' . \$search . '%'; }\n";
        $c .= "            \$where = ' WHERE (' . implode(' OR ', \$parts) . ')';\n";
        $c .= "        }\n";
        $c .= "        try {\n";
        $c .= "            \$sql = \"SELECT * FROM `\$table`\$where ORDER BY `\$orderBy` \$orderDir LIMIT \$limit OFFSET \$offset\";\n";
        $c .= "            \$stmt = \$pdo->prepare(\$sql); \$stmt->execute(\$params);\n";
        $c .= "            return \$stmt->fetchAll(PDO::FETCH_ASSOC);\n";
        $c .= "        } catch (Throwable \$e) { return []; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_count')) {\n    function crud_count(string \$search = ''): int {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return 0;\n";
        $c .= "        \$table = CRUD_TABLE; \$where = ''; \$params = [];\n";
        $c .= "        \$search = trim(\$search);\n";
        $c .= "        if (\$search !== '' && !empty(CRUD_SEARCHABLE)) {\n";
        $c .= "            \$parts = [];\n";
        $c .= "            foreach (CRUD_SEARCHABLE as \$col) { \$parts[] = \"`\$col` LIKE ?\"; \$params[] = '%' . \$search . '%'; }\n";
        $c .= "            \$where = ' WHERE (' . implode(' OR ', \$parts) . ')';\n";
        $c .= "        }\n";
        $c .= "        try { \$stmt = \$pdo->prepare(\"SELECT COUNT(*) FROM `\$table`\$where\"); \$stmt->execute(\$params); return (int)\$stmt->fetchColumn(); } catch (Throwable \$e) { return 0; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_getOne')) {\n    function crud_getOne(\$id): ?array {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return null;\n";
        $c .= "        \$table = CRUD_TABLE; \$pk = CRUD_PK;\n";
        $c .= "        try { \$stmt = \$pdo->prepare(\"SELECT * FROM `\$table` WHERE `\$pk` = ? LIMIT 1\"); \$stmt->execute([\$id]); return \$stmt->fetch(PDO::FETCH_ASSOC) ?: null; } catch (Throwable \$e) { return null; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_insert')) {\n    function crud_insert(array \$data): array {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return ['ok' => false, 'error' => 'DB error'];\n";
        $c .= "        \$table = CRUD_TABLE; \$pk = CRUD_PK;\n";
        $c .= "        unset(\$data[\$pk]);\n";
        $c .= "        if (empty(\$data)) return ['ok' => false, 'error' => 'داده‌ای ارسال نشد'];\n";
        $c .= "        try {\n";
        $c .= "            \$cols = array_keys(\$data); \$ph = array_fill(0, count(\$cols), '?');\n";
        $c .= "            \$sql = \"INSERT INTO `\$table` (`\" . implode('`, `', \$cols) . \"`) VALUES (\" . implode(', ', \$ph) . \")\";\n";
        $c .= "            \$stmt = \$pdo->prepare(\$sql); \$stmt->execute(array_values(\$data));\n";
        $c .= "            return ['ok' => true, 'id' => \$pdo->lastInsertId()];\n";
        $c .= "        } catch (Throwable \$e) { return ['ok' => false, 'error' => \$e->getMessage()]; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_update')) {\n    function crud_update(\$id, array \$data): array {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return ['ok' => false, 'error' => 'DB error'];\n";
        $c .= "        \$table = CRUD_TABLE; \$pk = CRUD_PK;\n";
        $c .= "        unset(\$data[\$pk]);\n";
        $c .= "        if (empty(\$data)) return ['ok' => false, 'error' => 'داده‌ای ارسال نشد'];\n";
        $c .= "        try {\n";
        $c .= "            \$sets = []; foreach (array_keys(\$data) as \$col) \$sets[] = \"`\$col` = ?\";\n";
        $c .= "            \$sql = \"UPDATE `\$table` SET \" . implode(', ', \$sets) . \" WHERE `\$pk` = ?\";\n";
        $c .= "            \$params = array_values(\$data); \$params[] = \$id;\n";
        $c .= "            \$stmt = \$pdo->prepare(\$sql); \$stmt->execute(\$params);\n";
        $c .= "            return ['ok' => true];\n";
        $c .= "        } catch (Throwable \$e) { return ['ok' => false, 'error' => \$e->getMessage()]; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_delete')) {\n    function crud_delete(\$id): array {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return ['ok' => false, 'error' => 'DB error'];\n";
        $c .= "        \$table = CRUD_TABLE; \$pk = CRUD_PK;\n";
        $c .= "        try { \$stmt = \$pdo->prepare(\"DELETE FROM `\$table` WHERE `\$pk` = ?\"); \$stmt->execute([\$id]); return ['ok' => true]; } catch (Throwable \$e) { return ['ok' => false, 'error' => \$e->getMessage()]; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_fkOptions')) {\n    function crud_fkOptions(string \$refTable, string \$refCol = 'id', int \$limit = 500): array {\n";
        $c .= "        \$pdo = crud_db(); if (!\$pdo) return [];\n";
        $c .= "        \$refTable = crud_safeId(\$refTable); \$refCol = crud_safeId(\$refCol);\n";
        $c .= "        try {\n";
        $c .= "            \$cols = \$pdo->query(\"SHOW COLUMNS FROM `\$refTable`\")->fetchAll(PDO::FETCH_ASSOC);\n";
        $c .= "            \$displayCol = null;\n";
        $c .= "            foreach (['name', 'title', 'label', 'subject'] as \$cand) {\n";
        $c .= "                foreach (\$cols as \$cc) { if (\$cc['Field'] === \$cand) { \$displayCol = \$cand; break 2; } }\n";
        $c .= "            }\n";
        $c .= "            if (!\$displayCol) \$displayCol = \$cols[0]['Field'] ?? \$refCol;\n";
        $c .= "            \$stmt = \$pdo->query(\"SELECT `\$refCol` as id, `\$displayCol` as label FROM `\$refTable` ORDER BY `\$displayCol` ASC LIMIT \$limit\");\n";
        $c .= "            return \$stmt->fetchAll(PDO::FETCH_ASSOC);\n";
        $c .= "        } catch (Throwable \$e) { return []; }\n";
        $c .= "    }\n}\n\n";
        $c .= "if (!function_exists('crud_sanitize')) {\n    function crud_sanitize(array \$data, array \$fields): array {\n";
        $c .= "        \$out = [];\n";
        $c .= "        foreach (\$fields as \$f) {\n";
        $c .= "            \$name = \$f['name'];\n";
        $c .= "            if (!array_key_exists(\$name, \$data)) continue;\n";
        $c .= "            \$val = \$data[\$name]; \$type = \$f['html_type'];\n";
        $c .= "            \$isNull = (\$val === '' || \$val === null);\n";
        $c .= "            if (\$isNull) {\n";
        $c .= "                if (!empty(\$f['required'])) continue;\n";
        $c .= "                if (\$type === 'checkbox') { \$out[\$name] = 0; continue; }\n";
        $c .= "                \$out[\$name] = null; continue;\n";
        $c .= "            }\n";
        $c .= "            switch (\$type) {\n";
        $c .= "                case 'number': case 'fk_select': \$out[\$name] = (\$val === '') ? null : (int)\$val; break;\n";
        $c .= "                case 'decimal': \$out[\$name] = (\$val === '') ? null : (float)\$val; break;\n";
        $c .= "                case 'checkbox': \$out[\$name] = (\$val == '1' || \$val === true) ? 1 : 0; break;\n";
        $c .= "                case 'date': case 'datetime-local': case 'time': \$out[\$name] = (\$val === '') ? null : \$val; break;\n";
        $c .= "                default: \$out[\$name] = is_string(\$val) ? trim(\$val) : \$val;\n";
        $c .= "            }\n";
        $c .= "        }\n";
        $c .= "        return \$out;\n";
        $c .= "    }\n}\n";
        return $c;
    }

    // ═══════════════════════════════════════════════════════════
    //  form.php
    // ═══════════════════════════════════════════════════════════
    public function renderForm(array $a, array $opts): string {
        $table = $a['table'];
        $title = $opts['title'] ?? $a['module_title'];
        $icon  = $opts['icon'] ?? $a['module_icon'];
        $slug  = $opts['slug'] ?? $table;
        $listUrl = "/modules/generated/{$slug}/admin.php";

        $c  = "<?php\n/**\n * Form (Add/Edit) - {$table}\n * @date " . date('Y-m-d H:i') . "\n */\n";
        $c .= "require_once __DIR__ . '/../../../admin/auth_check.php';\n";
        $c .= "require_once __DIR__ . '/helpers.php';\n";
        $c .= "require_once __DIR__ . '/../../../modules/table_builder/includes/TableBuilder.php';\n";
        $c .= "require_once __DIR__ . '/../../../modules/table_builder/includes/FormBuilder.php';\n\n";
        $c .= "if ((\$_SESSION['role'] ?? '') !== 'admin') { header('Location: ' . SITE_URL . '/admin/index.php'); exit; }\n";
        $c .= "if (!isset(\$pdo)) \$pdo = crud_db();\n\n";
        $c .= "\$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);\n";
        $c .= "\$fields = \$config['fields'] ?? [];\n\n";
        $c .= "\$id = isset(\$_GET['id']) ? (int)\$_GET['id'] : 0;\n";
        $c .= "\$isEdit = \$id > 0;\n";
        $c .= "\$item = \$isEdit ? crud_getOne(\$id) : null;\n\n";
        $c .= "if (\$isEdit && !\$item) { header('Location: ' . '{$listUrl}?msg=notfound'); exit; }\n\n";
        $c .= "\$errors = [];\n";
        $c .= "if (\$_SERVER['REQUEST_METHOD'] === 'POST') {\n";
        $c .= "    \$data = crud_sanitize(\$_POST, \$fields);\n";
        $c .= "    foreach (\$fields as \$f) {\n";
        $c .= "        if (!empty(\$f['required'])) {\n";
        $c .= "            \$v = \$data[\$f['name']] ?? null;\n";
        $c .= "            if (\$v === null || \$v === '') \$errors[\$f['name']] = 'این فیلد اجباری است';\n";
        $c .= "        }\n";
        $c .= "    }\n";
        $c .= "    if (empty(\$errors)) {\n";
        $c .= "        \$res = \$isEdit ? crud_update(\$id, \$data) : crud_insert(\$data);\n";
        $c .= "        if (!empty(\$res['ok'])) { header('Location: ' . '{$listUrl}?msg=saved'); exit; }\n";
        $c .= "        else \$errors['_global'] = \$res['error'] ?? 'خطای ناشناخته';\n";
        $c .= "    }\n";
        $c .= "}\n\n";
        $c .= "\$formFields = [];\n";
        $c .= "foreach (\$fields as \$f) { if (!empty(\$f['hidden']) || !empty(\$f['system'])) continue; \$formFields[] = \$f; }\n";
        $c .= "\$analysis = ['form_fields' => \$formFields, 'table' => crud_table(), 'primary_key' => CRUD_PK];\n\n";
        $c .= "\$tb = new TableBuilder(\$pdo);\n\$fb = new FormBuilder(\$tb);\n\n";
        $c .= "?>\n";
        $c .= "<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\">\n<head>\n";
        $c .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $c .= "<title><?= \$isEdit ? 'ویرایش' : 'افزودن' ?> - " . addslashes($title) . "</title>\n";
        $c .= "<script src=\"<?= SITE_URL ?>/assets/vendor/tailwind.js\"></script>\n";
        $c .= "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css\" rel=\"stylesheet\">\n";
        $c .= "<style>body{font-family:Tahoma,sans-serif;background:#f1f5f9}</style>\n</head>\n<body>\n";
        $c .= "<?php \$sf = __DIR__ . '/../../../admin/includes/sidebar.php'; if (file_exists(\$sf)) require_once \$sf; ?>\n";
        $c .= "<div class=\"mr-[260px] p-5 max-md:mr-0 max-md:pt-16 min-h-screen\">\n";
        $c .= "  <div class=\"max-w-4xl mx-auto\">\n";
        $c .= "    <div class=\"bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-2xl p-6 mb-6 shadow-lg\">\n";
        $c .= "      <div class=\"flex items-center justify-between flex-wrap gap-3\">\n";
        $c .= "        <div>\n";
        $c .= "          <h1 class=\"text-xl font-bold flex items-center gap-2\"><span>" . addslashes($icon) . "</span> <?= \$isEdit ? '✏️ ویرایش' : '➕ افزودن' ?> " . addslashes($title) . "</h1>\n";
        $c .= "          <p class=\"text-sm opacity-80 mt-1\"><?= \$isEdit ? 'شناسه: ' . \$id : 'ثبت رکورد جدید' ?></p>\n";
        $c .= "        </div>\n";
        $c .= "        <a href=\"{$listUrl}\" class=\"inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 px-4 py-2 rounded-lg text-sm font-medium transition\"><i class=\"bi bi-arrow-right\"></i> بازگشت</a>\n";
        $c .= "      </div>\n    </div>\n";
        $c .= "    <?php if (!empty(\$errors['_global'])): ?>\n";
        $c .= "    <div class=\"bg-red-50 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-4\">❌ <?= htmlspecialchars(\$errors['_global']) ?></div>\n";
        $c .= "    <?php endif; ?>\n";
        $c .= "    <div class=\"bg-white rounded-2xl shadow-sm p-6\">\n";
        $c .= "      <?php echo \$fb->setAction('')->setMethod('POST')->renderForm(\$analysis, \$item ?: [], [\n";
        $c .= "          'action' => '', 'method' => 'POST',\n";
        $c .= "          'submit_label' => \$isEdit ? '💾 بروزرسانی' : '💾 ذخیره',\n";
        $c .= "          'cancel_url' => '{$listUrl}', 'errors' => \$errors,\n";
        $c .= "      ]); ?>\n";
        $c .= "    </div>\n  </div>\n</div>\n</body>\n</html>\n";
        return $c;
    }

    // ═══════════════════════════════════════════════════════════
    //  admin.php
    // ═══════════════════════════════════════════════════════════
    public function renderAdmin(array $a, array $opts): string {
        $table = $a['table']; $pk = $a['primary_key'];
        $title = $opts['title'] ?? $a['module_title'];
        $icon  = $opts['icon'] ?? $a['module_icon'];
        $slug  = $opts['slug'] ?? $table;
        $baseUrl = "/modules/generated/{$slug}";
        $adminUrl = "{$baseUrl}/admin.php";
        $formUrl  = "{$baseUrl}/form.php";

        // ستون‌های لیست
        $listCols = $a['list_columns'] ?? [];
        $fieldsByKey = [];
        foreach ($a['fields'] as $f) $fieldsByKey[$f['name']] = $f;

        // ستون‌های نمایش: PK + listColumns
        $displayCols = array_merge([$pk], $listCols);
        $displayCols = array_values(array_unique($displayCols));

        $c  = "<?php\n/**\n * Admin Panel - {$table}\n * @date " . date('Y-m-d H:i') . "\n */\n";
        $c .= "require_once __DIR__ . '/../../../admin/auth_check.php';\n";
        $c .= "require_once __DIR__ . '/helpers.php';\n\n";
        $c .= "if ((\$_SESSION['role'] ?? '') !== 'admin') { header('Location: ' . SITE_URL . '/admin/index.php'); exit; }\n";
        $c .= "if (!isset(\$pdo)) \$pdo = crud_db();\n\n";

        // پردازش حذف
        $c .= "if (isset(\$_GET['delete']) && is_numeric(\$_GET['delete'])) {\n";
        $c .= "    \$res = crud_delete((int)\$_GET['delete']);\n";
        $c .= "    \$msg = !empty(\$res['ok']) ? 'deleted' : 'error';\n";
        $c .= "    header('Location: ' . '{$adminUrl}?msg=' . \$msg);\n";
        $c .= "    exit;\n";
        $c .= "}\n\n";

        // پیام‌ها
        $c .= "\$messages = [\n";
        $c .= "    'saved'    => '✅ با موفقیت ذخیره شد',\n";
        $c .= "    'deleted'  => '🗑 با موفقیت حذف شد',\n";
        $c .= "    'notfound' => '⚠️ رکورد یافت نشد',\n";
        $c .= "    'error'    => '❌ خطایی رخ داد',\n";
        $c .= "];\n";
        $c .= "\$msgKey = \$_GET['msg'] ?? '';\n";
        $c .= "\$message = \$messages[\$msgKey] ?? '';\n\n";

        // جستجو و صفحه‌بندی
        $c .= "\$search = trim((string)(\$_GET['q'] ?? ''));\n";
        $c .= "\$page = max(1, (int)(\$_GET['page'] ?? 1));\n";
        $c .= "\$perPage = 20;\n";
        $c .= "\$total = crud_count(\$search);\n";
        $c .= "\$totalPages = max(1, (int)ceil(\$total / \$perPage));\n";
        $c .= "\$page = min(\$page, \$totalPages);\n";
        $c .= "\$offset = (\$page - 1) * \$perPage;\n";
        $c .= "\$rows = crud_getAll(['limit' => \$perPage, 'offset' => \$offset, 'search' => \$search]);\n\n";

        // HTML
        $c .= "?>\n<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\">\n<head>\n";
        $c .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $c .= "<title>" . addslashes($title) . " | مدیریت</title>\n";
        $c .= "<script src=\"<?= SITE_URL ?>/assets/vendor/tailwind.js\"></script>\n";
        $c .= "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css\" rel=\"stylesheet\">\n";
        $c .= "<style>body{font-family:Tahoma,sans-serif;background:#f1f5f9}</style>\n</head>\n<body>\n";
        $c .= "<?php \$sf = __DIR__ . '/../../../admin/includes/sidebar.php'; if (file_exists(\$sf)) require_once \$sf; ?>\n";
        $c .= "<div class=\"mr-[260px] p-5 max-md:mr-0 max-md:pt-16 min-h-screen\">\n";
        $c .= "  <div class=\"max-w-7xl mx-auto\">\n\n";

        // هدر
        $c .= "    <div class=\"bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-2xl p-6 mb-6 shadow-lg\">\n";
        $c .= "      <div class=\"flex items-center justify-between flex-wrap gap-3\">\n";
        $c .= "        <div>\n";
        $c .= "          <h1 class=\"text-xl font-bold flex items-center gap-2\"><span>" . addslashes($icon) . "</span> " . addslashes($title) . "</h1>\n";
        $c .= "          <p class=\"text-sm opacity-80 mt-1\"><?= number_format(\$total) ?> رکورد — صفحه <?= \$page ?> از <?= \$totalPages ?></p>\n";
        $c .= "        </div>\n";
        $c .= "        <a href=\"{$formUrl}\" class=\"inline-flex items-center gap-2 bg-white text-indigo-700 hover:bg-indigo-50 px-4 py-2.5 rounded-lg text-sm font-bold shadow transition\"><i class=\"bi bi-plus-lg\"></i> افزودن جدید</a>\n";
        $c .= "      </div>\n    </div>\n\n";

        // پیام
        $c .= "    <?php if (\$message): ?>\n";
        $c .= "    <div class=\"bg-emerald-50 border-r-4 border-emerald-500 text-emerald-700 p-4 rounded-lg mb-4\"><?= htmlspecialchars(\$message) ?></div>\n";
        $c .= "    <?php endif; ?>\n\n";

        // جستجو
        $c .= "    <form method=\"GET\" class=\"bg-white rounded-xl shadow-sm p-4 mb-5 flex gap-2 flex-wrap items-center\">\n";
        $c .= "      <div class=\"flex-1 min-w-[200px] relative\">\n";
        $c .= "        <i class=\"bi bi-search absolute right-3 top-1/2 -translate-y-1/2 text-gray-400\"></i>\n";
        $c .= "        <input type=\"text\" name=\"q\" value=\"<?= htmlspecialchars(\$search) ?>\" placeholder=\"جستجو...\" class=\"w-full pr-10 pl-3 py-2.5 rounded-lg border border-gray-300 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none text-sm\">\n";
        $c .= "      </div>\n";
        $c .= "      <button type=\"submit\" class=\"px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg text-sm transition\">🔍 جستجو</button>\n";
        if (!empty($displayCols)) {
            $c .= "      <?php if (\$search): ?><a href=\"{$adminUrl}\" class=\"px-4 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm\">✖ پاک کردن</a><?php endif; ?>\n";
        }
        $c .= "    </form>\n\n";

        // جدول
        $c .= "    <div class=\"bg-white rounded-xl shadow-sm overflow-hidden\">\n";
        $c .= "      <?php if (empty(\$rows)): ?>\n";
        $c .= "        <div class=\"text-center py-20 text-gray-400\">\n";
        $c .= "          <i class=\"bi bi-inbox text-6xl\"></i>\n";
        $c .= "          <p class=\"mt-4 text-lg\">هیچ رکوردی یافت نشد</p>\n";
        $c .= "          <?php if (\$search): ?><p class=\"text-sm mt-1\">نتیجه‌ای برای «<?= htmlspecialchars(\$search) ?>» پیدا نشد</p><?php endif; ?>\n";
        $c .= "        </div>\n";
        $c .= "      <?php else: ?>\n";
        $c .= "      <div class=\"overflow-x-auto\">\n";
        $c .= "        <table class=\"w-full text-sm\">\n";
        $c .= "          <thead class=\"bg-gray-50 border-b border-gray-200\">\n";
        $c .= "            <tr>\n";
        $c .= "              <th class=\"px-4 py-3 text-right font-bold text-gray-600\">#</th>\n";
        foreach ($displayCols as $col) {
            $label = $fieldsByKey[$col]['label'] ?? $col;
            $c .= "              <th class=\"px-4 py-3 text-right font-bold text-gray-600\">" . addslashes($label) . "</th>\n";
        }
        $c .= "              <th class=\"px-4 py-3 text-center font-bold text-gray-600 w-32\">عملیات</th>\n";
        $c .= "            </tr>\n          </thead>\n";
        $c .= "          <tbody>\n";
        $c .= "          <?php foreach (\$rows as \$i => \$row): ?>\n";
        $c .= "            <tr class=\"border-b border-gray-100 hover:bg-indigo-50/40 transition\">\n";
        $c .= "              <td class=\"px-4 py-3 text-gray-500 text-xs\"><?= \$offset + \$i + 1 ?></td>\n";
        foreach ($displayCols as $col) {
            $field = $fieldsByKey[$col] ?? null;
            $htmlType = $field['html_type'] ?? 'text';
            if ($htmlType === 'checkbox') {
                $c .= "              <td class=\"px-4 py-3\"><?= !empty(\$row['" . $col . "']) ? '✅' : '➖' ?></td>\n";
            } elseif ($htmlType === 'fk_select' && $field && !empty($field['foreign_key'])) {
                $refTable = $field['foreign_key']['ref_table'] ?? '';
                // برای نمایش FK، از gزینه‌ها استفاده می‌کنیم
                $c .= "              <td class=\"px-4 py-3\">";
                $c .= "<?php \$fkOpts = crud_fkOptions('" . addslashes($refTable) . "'); \$fkLabel = ''; foreach (\$fkOpts as \$opt) { if ((string)\$opt['id'] === (string)(\$row['" . $col . "'] ?? '')) { \$fkLabel = \$opt['label']; break; } } echo htmlspecialchars(\$fkLabel ?: '—'); ?>";
                $c .= "</td>\n";
            } elseif (in_array($htmlType, ['textarea', 'json'], true)) {
                $c .= "              <td class=\"px-4 py-3 text-gray-600 max-w-xs truncate\" title=\"<?= htmlspecialchars(\$row['" . $col . "'] ?? '') ?>\"><?= htmlspecialchars(mb_substr((string)(\$row['" . $col . "'] ?? ''), 0, 50)) ?><?= mb_strlen((string)(\$row['" . $col . "'] ?? '')) > 50 ? '...' : '' ?></td>\n";
            } elseif ($htmlType === 'date' || $htmlType === 'datetime-local') {
                $c .= "              <td class=\"px-4 py-3 text-gray-600 text-xs\"><?= !empty(\$row['" . $col . "']) ? htmlspecialchars(\$row['" . $col . "']) : '—' ?></td>\n";
            } else {
                $c .= "              <td class=\"px-4 py-3 text-gray-800\"><?= htmlspecialchars((string)(\$row['" . $col . "'] ?? '—')) ?></td>\n";
            }
        }
        $c .= "              <td class=\"px-4 py-3\">\n";
        $c .= "                <div class=\"flex items-center justify-center gap-1\">\n";
        $c .= "                  <a href=\"{$formUrl}?id=<?= urlencode(\$row['{$pk}'] ?? '') ?>\" class=\"p-2 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg transition\" title=\"ویرایش\"><i class=\"bi bi-pencil\"></i></a>\n";
        $c .= "                  <a href=\"{$adminUrl}?delete=<?= urlencode(\$row['{$pk}'] ?? '') ?>\" onclick=\"return confirm('آیا از حذف این رکورد مطمئن هستید؟')\" class=\"p-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg transition\" title=\"حذف\"><i class=\"bi bi-trash\"></i></a>\n";
        $c .= "                </div>\n              </td>\n";
        $c .= "            </tr>\n          <?php endforeach; ?>\n";
        $c .= "          </tbody>\n        </table>\n      </div>\n";
        $c .= "      <?php endif; ?>\n";
        $c .= "    </div>\n\n";

        // صفحه‌بندی
        $c .= "    <?php if (\$totalPages > 1): ?>\n";
        $c .= "    <div class=\"flex justify-center items-center gap-1 mt-6 flex-wrap\">\n";
        $c .= "      <?php \$qs = \$search ? '&q=' . urlencode(\$search) : ''; ?>\n";
        $c .= "      <?php if (\$page > 1): ?>\n";
        $c .= "        <a href=\"{$adminUrl}?page=<?= \$page - 1 ?><?= \$qs ?>\" class=\"px-3 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 text-sm shadow-sm\">← قبلی</a>\n";
        $c .= "      <?php endif; ?>\n";
        $c .= "      <?php\n";
        $c .= "      \$start = max(1, \$page - 3);\n";
        $c .= "      \$end = min(\$totalPages, \$page + 3);\n";
        $c .= "      for (\$p = \$start; \$p <= \$end; \$p++):\n";
        $c .= "      ?>\n";
        $c .= "        <?php if (\$p == \$page): ?>\n";
        $c .= "          <span class=\"px-4 py-2 bg-gradient-to-l from-purple-600 to-indigo-700 text-white font-bold rounded-lg text-sm shadow\"><?= \$p ?></span>\n";
        $c .= "        <?php else: ?>\n";
        $c .= "          <a href=\"{$adminUrl}?page=<?= \$p ?><?= \$qs ?>\" class=\"px-4 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 text-sm shadow-sm\"><?= \$p ?></a>\n";
        $c .= "        <?php endif; ?>\n";
        $c .= "      <?php endfor; ?>\n";
        $c .= "      <?php if (\$page < \$totalPages): ?>\n";
        $c .= "        <a href=\"{$adminUrl}?page=<?= \$page + 1 ?><?= \$qs ?>\" class=\"px-3 py-2 bg-white text-gray-700 rounded-lg hover:bg-gray-50 text-sm shadow-sm\">بعدی →</a>\n";
        $c .= "      <?php endif; ?>\n";
        $c .= "    </div>\n";
        $c .= "    <?php endif; ?>\n\n";

        $c .= "  </div>\n</div>\n</body>\n</html>\n";
        return $c;
    }

    // ═══════════════════════════════════════════════════════════
    //  list.php (فرانت — لیست عمومی)
    // ═══════════════════════════════════════════════════════════
    public function renderList(array $a, array $opts): string {
        $table = $a['table']; $pk = $a['primary_key'];
        $title = $opts['title'] ?? $a['module_title'];
        $icon  = $opts['icon'] ?? $a['module_icon'];
        $slug  = $opts['slug'] ?? $table;
        $baseUrl = "/modules/generated/{$slug}";

        $titleField = $a['title_field'] ?? $pk;
        $bodyField  = $a['body_field'] ?? null;
        $listCols   = $a['list_columns'] ?? [];
        $fieldsByKey = [];
        foreach ($a['fields'] as $f) $fieldsByKey[$f['name']] = $f;

        $c  = "<?php\n/**\n * Frontend List - {$table}\n * @date " . date('Y-m-d H:i') . "\n */\n";
        $c .= "require_once __DIR__ . '/../../../config.php';\n";
        $c .= "require_once __DIR__ . '/helpers.php';\n\n";
        $c .= "if (!isset(\$pdo)) \$pdo = crud_db();\n\n";
        $c .= "\$search = trim((string)(\$_GET['q'] ?? ''));\n";
        $c .= "\$page = max(1, (int)(\$_GET['page'] ?? 1));\n";
        $c .= "\$perPage = 12;\n";
        $c .= "\$total = crud_count(\$search);\n";
        $c .= "\$totalPages = max(1, (int)ceil(\$total / \$perPage));\n";
        $c .= "\$page = min(\$page, \$totalPages);\n";
        $c .= "\$offset = (\$page - 1) * \$perPage;\n";
        $c .= "\$rows = crud_getAll(['limit' => \$perPage, 'offset' => \$offset, 'search' => \$search]);\n\n";
        $c .= "?>\n<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\">\n<head>\n";
        $c .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $c .= "<title>" . addslashes($title) . "</title>\n";
        $c .= "<script src=\"<?= SITE_URL ?>/assets/vendor/tailwind.js\"></script>\n";
        $c .= "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css\" rel=\"stylesheet\">\n";
        $c .= "<style>body{font-family:Tahoma,sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e0e7ff 100%);min-height:100vh}</style>\n</head>\n<body>\n";
        $c .= "<div class=\"max-w-6xl mx-auto p-5\">\n\n";
        // هدر
        $c .= "  <header class=\"bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-2xl p-8 mb-6 shadow-xl\">\n";
        $c .= "    <h1 class=\"text-2xl font-bold flex items-center gap-3\"><span class=\"text-3xl\">" . addslashes($icon) . "</span> " . addslashes($title) . "</h1>\n";
        $c .= "    <p class=\"text-sm opacity-90 mt-2\"><?= number_format(\$total) ?> آیتم موجود</p>\n";
        $c .= "  </header>\n\n";
        // جستجو
        $c .= "  <form method=\"GET\" class=\"mb-6\">\n";
        $c .= "    <div class=\"relative\">\n";
        $c .= "      <i class=\"bi bi-search absolute right-4 top-1/2 -translate-y-1/2 text-gray-400\"></i>\n";
        $c .= "      <input type=\"text\" name=\"q\" value=\"<?= htmlspecialchars(\$search) ?>\" placeholder=\"جستجو در " . addslashes($title) . "...\" class=\"w-full pr-12 pl-4 py-3.5 rounded-xl border border-white shadow-lg focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none text-sm bg-white\">\n";
        $c .= "    </div>\n  </form>\n\n";
        // لیست
        $c .= "  <?php if (empty(\$rows)): ?>\n";
        $c .= "    <div class=\"bg-white rounded-2xl shadow-lg text-center py-20 text-gray-400\">\n";
        $c .= "      <i class=\"bi bi-inbox text-6xl\"></i>\n";
        $c .= "      <p class=\"mt-4 text-lg\">هیچ آیتمی یافت نشد</p>\n";
        $c .= "      <?php if (\$search): ?><p class=\"text-sm mt-1\">نتیجه‌ای برای «<?= htmlspecialchars(\$search) ?>» پیدا نشد</p><?php endif; ?>\n";
        $c .= "    </div>\n";
        $c .= "  <?php else: ?>\n";
        $c .= "    <div class=\"grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4\">\n";
        $c .= "      <?php foreach (\$rows as \$row): ?>\n";
        $c .= "        <a href=\"{$baseUrl}/view.php?id=<?= urlencode(\$row['{$pk}'] ?? '') ?>\" class=\"group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden block\">\n";
        $c .= "          <div class=\"h-32 bg-gradient-to-br from-indigo-400 to-purple-500 flex items-center justify-center text-white text-5xl group-hover:scale-110 transition-transform\">\n";
        $c .= "            <i class=\"bi bi-file-earmark-text\"></i>\n";
        $c .= "          </div>\n";
        $c .= "          <div class=\"p-5\">\n";
        $c .= "            <h3 class=\"font-bold text-gray-800 text-base truncate mb-2\"><?= htmlspecialchars((string)(\$row['{$titleField}'] ?? '—')) ?></h3>\n";
        if ($bodyField) {
            $c .= "            <p class=\"text-xs text-gray-500 leading-relaxed\" style=\"display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden\"><?= htmlspecialchars(mb_substr(strip_tags((string)(\$row['{$bodyField}'] ?? '')), 0, 100)) ?></p>\n";
        } else {
            $c .= "            <p class=\"text-xs text-gray-400\">شناسه: <?= htmlspecialchars((string)(\$row['{$pk}'] ?? '')) ?></p>\n";
        }
        $c .= "          </div>\n";
        $c .= "        </a>\n";
        $c .= "      <?php endforeach; ?>\n";
        $c .= "    </div>\n";
        // صفحه‌بندی
        $c .= "    <?php if (\$totalPages > 1): ?>\n";
        $c .= "    <div class=\"flex justify-center items-center gap-2 mt-8 flex-wrap\">\n";
        $c .= "      <?php \$qs = \$search ? '&q=' . urlencode(\$search) : ''; ?>\n";
        $c .= "      <?php if (\$page > 1): ?><a href=\"{$baseUrl}/list.php?page=<?= \$page - 1 ?><?= \$qs ?>\" class=\"px-4 py-2.5 bg-white text-gray-700 rounded-xl hover:bg-indigo-50 shadow-md text-sm font-medium\">← قبلی</a><?php endif; ?>\n";
        $c .= "      <?php for (\$p = max(1, \$page - 3); \$p <= min(\$totalPages, \$page + 3); \$p++): ?>\n";
        $c .= "        <?php if (\$p == \$page): ?>\n";
        $c .= "          <span class=\"px-4 py-2.5 bg-gradient-to-l from-purple-600 to-indigo-700 text-white rounded-xl font-bold shadow-lg text-sm\"><?= \$p ?></span>\n";
        $c .= "        <?php else: ?>\n";
        $c .= "          <a href=\"{$baseUrl}/list.php?page=<?= \$p ?><?= \$qs ?>\" class=\"px-4 py-2.5 bg-white text-gray-700 rounded-xl hover:bg-indigo-50 shadow-md text-sm font-medium\"><?= \$p ?></a>\n";
        $c .= "        <?php endif; ?>\n";
        $c .= "      <?php endfor; ?>\n";
        $c .= "      <?php if (\$page < \$totalPages): ?><a href=\"{$baseUrl}/list.php?page=<?= \$page + 1 ?><?= \$qs ?>\" class=\"px-4 py-2.5 bg-white text-gray-700 rounded-xl hover:bg-indigo-50 shadow-md text-sm font-medium\">بعدی →</a><?php endif; ?>\n";
        $c .= "    </div>\n";
        $c .= "    <?php endif; ?>\n";
        $c .= "  <?php endif; ?>\n";
        $c .= "</div>\n</body>\n</html>\n";
        return $c;
    }

    // ═══════════════════════════════════════════════════════════
    //  view.php (فرانت — نمایش تکی)
    // ═══════════════════════════════════════════════════════════
    public function renderView(array $a, array $opts): string {
        $table = $a['table']; $pk = $a['primary_key'];
        $title = $opts['title'] ?? $a['module_title'];
        $icon  = $opts['icon'] ?? $a['module_icon'];
        $slug  = $opts['slug'] ?? $table;
        $baseUrl = "/modules/generated/{$slug}";

        $titleField = $a['title_field'] ?? $pk;
        $bodyField  = $a['body_field'] ?? null;
        $fieldsByKey = [];
        foreach ($a['fields'] as $f) $fieldsByKey[$f['name']] = $f;

        // ستون‌های نمایش (غیر از body)
        $viewCols = [];
        foreach ($a['fields'] as $f) {
            if ($f['name'] === $bodyField) continue;
            if ($f['name'] === $titleField) continue;
            $viewCols[] = $f;
        }

        $c  = "<?php\n/**\n * Frontend View - {$table}\n * @date " . date('Y-m-d H:i') . "\n */\n";
        $c .= "require_once __DIR__ . '/../../../config.php';\n";
        $c .= "require_once __DIR__ . '/helpers.php';\n\n";
        $c .= "if (!isset(\$pdo)) \$pdo = crud_db();\n\n";
        $c .= "\$id = \$_GET['id'] ?? null;\n";
        $c .= "\$item = \$id !== null ? crud_getOne(\$id) : null;\n";
        $c .= "if (!\$item) { http_response_code(404); }\n\n";
        $c .= "\$pageTitle = \$item ? (\$item['{$titleField}'] ?? " . var_export($title, true) . ") : 'یافت نشد';\n\n";
        $c .= "?>\n<!DOCTYPE html>\n<html lang=\"fa\" dir=\"rtl\">\n<head>\n";
        $c .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $c .= "<title><?= htmlspecialchars(\$pageTitle) ?></title>\n";
        $c .= "<script src=\"<?= SITE_URL ?>/assets/vendor/tailwind.js\"></script>\n";
        $c .= "<link href=\"https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css\" rel=\"stylesheet\">\n";
        $c .= "<style>body{font-family:Tahoma,sans-serif;background:linear-gradient(135deg,#f1f5f9 0%,#e0e7ff 100%);min-height:100vh}</style>\n</head>\n<body>\n";
        $c .= "<div class=\"max-w-3xl mx-auto p-5\">\n";
        $c .= "  <a href=\"{$baseUrl}/list.php\" class=\"inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-medium text-sm mb-5\"><i class=\"bi bi-arrow-right\"></i> بازگشت به لیست</a>\n\n";
        $c .= "  <?php if (!\$item): ?>\n";
        $c .= "    <div class=\"bg-white rounded-2xl shadow-xl text-center py-20\">\n";
        $c .= "      <i class=\"bi bi-exclamation-triangle text-6xl text-yellow-500\"></i>\n";
        $c .= "      <h2 class=\"text-xl font-bold text-gray-700 mt-4\">آیتم یافت نشد</h2>\n";
        $c .= "      <p class=\"text-gray-500 text-sm mt-2\">موردی که به دنبال آن هستید وجود ندارد یا حذف شده است.</p>\n";
        $c .= "    </div>\n";
        $c .= "  <?php else: ?>\n";
        $c .= "    <article class=\"bg-white rounded-2xl shadow-xl overflow-hidden\">\n";
        $c .= "      <div class=\"bg-gradient-to-l from-purple-600 to-indigo-700 text-white p-8\">\n";
        $c .= "        <div class=\"flex items-center gap-3 mb-2\">\n";
        $c .= "          <span class=\"text-3xl\">" . addslashes($icon) . "</span>\n";
        $c .= "          <h1 class=\"text-2xl font-bold\"><?= htmlspecialchars((string)(\$item['{$titleField}'] ?? '—')) ?></h1>\n";
        $c .= "        </div>\n";
        $c .= "        <p class=\"text-sm opacity-80\">شناسه: <?= htmlspecialchars((string)(\$item['{$pk}'] ?? '')) ?></p>\n";
        $c .= "      </div>\n\n";
        $c .= "      <div class=\"p-8\">\n";
        if ($bodyField) {
            $c .= "        <?php \$body = (string)(\$item['{$bodyField}'] ?? ''); ?>\n";
            $c .= "        <?php if (\$body): ?>\n";
            $c .= "          <div class=\"text-gray-700 leading-loose whitespace-pre-wrap mb-8 text-sm\"><?= htmlspecialchars(\$body) ?></div>\n";
            $c .= "        <?php endif; ?>\n";
        }
        $c .= "        <div class=\"border-t border-gray-200 pt-6\">\n";
        $c .= "          <h3 class=\"text-sm font-bold text-gray-500 mb-4\">📋 جزئیات کامل</h3>\n";
        $c .= "          <dl class=\"space-y-3\">\n";
        $c .= "            <div class=\"flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100\">\n";
        $c .= "              <dt class=\"text-sm font-medium text-gray-500 sm:w-40 shrink-0\">" . addslashes($fieldsByKey[$pk]['label'] ?? $pk) . "</dt>\n";
        $c .= "              <dd class=\"text-sm text-gray-800\"><?= htmlspecialchars((string)(\$item['{$pk}'] ?? '—')) ?></dd>\n";
        $c .= "            </div>\n";
        foreach ($viewCols as $f) {
            $fname = $f['name'];
            $flabel = $f['label'];
            $ftype = $f['html_type'];
            $c .= "            <div class=\"flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-3 py-2 border-b border-gray-100\">\n";
            $c .= "              <dt class=\"text-sm font-medium text-gray-500 sm:w-40 shrink-0\">" . addslashes(($f['icon'] ?? '📋') . ' ' . $flabel) . "</dt>\n";
            if ($ftype === 'checkbox') {
                $c .= "              <dd class=\"text-sm text-gray-800\"><?= !empty(\$item['{$fname}']) ? '✅ بله' : '➖ خیر' ?></dd>\n";
            } elseif ($ftype === 'fk_select' && !empty($f['foreign_key'])) {
                $refTable = $f['foreign_key']['ref_table'] ?? '';
                $c .= "              <dd class=\"text-sm text-gray-800\"><?php \$opts = crud_fkOptions('" . addslashes($refTable) . "'); \$lbl = '—'; foreach (\$opts as \$o) { if ((string)\$o['id'] === (string)(\$item['{$fname}'] ?? '')) { \$lbl = \$o['label']; break; } } echo htmlspecialchars(\$lbl); ?></dd>\n";
            } elseif (in_array($ftype, ['textarea', 'json'], true)) {
                $c .= "              <dd class=\"text-sm text-gray-800 whitespace-pre-wrap\"><?= htmlspecialchars((string)(\$item['{$fname}'] ?? '—')) ?></dd>\n";
            } else {
                $c .= "              <dd class=\"text-sm text-gray-800\"><?= htmlspecialchars((string)(\$item['{$fname}'] ?? '—')) ?></dd>\n";
            }
            $c .= "            </div>\n";
        }
        $c .= "          </dl>\n";
        $c .= "        </div>\n";
        $c .= "      </div>\n";
        $c .= "    </article>\n";
        $c .= "  <?php endif; ?>\n";
        $c .= "</div>\n</body>\n</html>\n";
        return $c;
    }

    // ═══════════════════════════════════════════════════════════
    //  generate() — تولید کامل ماژول + ثبت در DB
    // ═══════════════════════════════════════════════════════════
    public function generate(array $a, array $opts): array {
        $slug  = $opts['slug'] ?? '';
        $table = $a['table'];
        $title = $opts['title'] ?? $a['module_title'];
        $icon  = $opts['icon'] ?? $a['module_icon'];
        $desc  = $opts['description'] ?? $a['module_desc'];
        $group = 'generated';
        $sortOrder = (int)($opts['sort_order'] ?? 500);

        // اعتبارسنجی
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $slug)) {
            return ['ok' => false, 'error' => 'نام ماژول نامعتبر است (فقط a-z، 0-9، _ )'];
        }

        $baseDir = dirname(__DIR__, 2) . '/generated';
        $moduleDir = $baseDir . '/' . $slug;

        if (is_dir($moduleDir)) {
            return ['ok' => false, 'error' => "پوشه ماژول «{$slug}» از قبل وجود دارد"];
        }

        try {
            // 1. ساخت پوشه
            if (!@mkdir($moduleDir, 0755, true)) {
                return ['ok' => false, 'error' => 'خطا در ساخت پوشه ماژول'];
            }

            // 2. تولید فایل‌ها
            $files = [
                'helpers.php' => $this->renderHelpers($a, $opts),
                'form.php'    => $this->renderForm($a, $opts),
                'admin.php'   => $this->renderAdmin($a, $opts),
                'list.php'    => $this->renderList($a, $opts),
                'view.php'    => $this->renderView($a, $opts),
            ];

            foreach ($files as $fname => $content) {
                if (@file_put_contents($moduleDir . '/' . $fname, $content) === false) {
                    throw new Exception("خطا در نوشتن {$fname}");
                }
            }

            // 3. تولید config.json
            $config = [
                'table'         => $table,
                'slug'          => $slug,
                'title'         => $title,
                'icon'          => $icon,
                'description'   => $desc,
                'menu_group'    => $group,
                'sort_order'    => $sortOrder,
                'created_at'    => date('c'),
                'generator'     => 'PartoCMS Table Builder v2.0',
                // کل analysis را ذخیره می‌کنیم
                'primary_key'   => $a['primary_key'],
                'title_field'   => $a['title_field'],
                'body_field'    => $a['body_field'],
                'sort_field'    => $a['sort_field'],
                'sort_direction'=> $a['sort_direction'],
                'searchable'    => $a['searchable'],
                'list_columns'  => $a['list_columns'],
                'fields'        => $a['fields'],
            ];
            file_put_contents($moduleDir . '/config.json', json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 4. تولید module.json
            $moduleJson = [
                'slug'       => $slug,
                'name'       => $title,
                'icon'       => $icon,
                'menu_group' => $group,
                'sort_order' => $sortOrder,
                'is_core'    => 0,
                'pages'      => [
                    'admin' => "modules/generated/{$slug}/admin.php",
                    'form'  => "modules/generated/{$slug}/form.php",
                    'list'  => "modules/generated/{$slug}/list.php",
                    'view'  => "modules/generated/{$slug}/view.php",
                ],
            ];
            file_put_contents($moduleDir . '/module.json', json_encode($moduleJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 5. ثبت در DB
            $this->registerInDb($slug, $title, $desc, $icon, $group, $sortOrder);

            // 6. بازگشت نتیجه
            return [
                'ok'       => true,
                'slug'     => $slug,
                'dir'      => $moduleDir,
                'files'    => array_keys($files),
                'admin_url' => "/modules/generated/{$slug}/admin.php",
            ];
        } catch (Throwable $e) {
            // پاک کردن پوشه ناقص
            if (is_dir($moduleDir)) {
                $this->rrmdir($moduleDir);
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  ثبت در DB
    // ═══════════════════════════════════════════════════════════
    private function registerInDb(string $slug, string $name, string $desc, string $icon, string $group, int $sortOrder): void {
        $pdo = $this->pdo;

        // 1. modules
        $stmt = $pdo->prepare("
            INSERT INTO modules (slug, name, description, version, is_enabled, is_core, icon, menu_group, sort_order)
            VALUES (?, ?, ?, '1.0.0', 1, 0, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                menu_group = VALUES(menu_group),
                sort_order = VALUES(sort_order)
        ");
        $stmt->execute([$slug, $name, $desc, $icon, $group, $sortOrder]);

        // 2. module_files — فقط 5 فایل تولیدشده
        $pdo->prepare("DELETE FROM module_files WHERE module_slug = ?")->execute([$slug]);

        $files = [
            ['pages',    'admin',  "modules/generated/{$slug}/admin.php"],
            ['pages',    'form',   "modules/generated/{$slug}/form.php"],
            ['pages',    'list',   "modules/generated/{$slug}/list.php"],
            ['pages',    'view',   "modules/generated/{$slug}/view.php"],
            ['includes', 'helpers',"modules/generated/{$slug}/helpers.php"],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO module_files (module_slug, file_type, file_key, file_path)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($files as $f) {
            $stmt->execute([$slug, $f[0], $f[1], $f[2]]);
        }

        // 3. role_modules — دسترسی admin
        try {
            $adminRoleId = $pdo->query("SELECT id FROM roles WHERE slug = 'admin' LIMIT 1")->fetchColumn();
            if ($adminRoleId) {
                $pdo->prepare("
                    INSERT INTO role_modules (role_id, module_slug, can_access)
                    VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE can_access = 1
                ")->execute([$adminRoleId, $slug]);
            }
        } catch (Throwable $e) { /* optional */ }
    }

    // ═══════════════════════════════════════════════════════════
    //  حذف بازگشتی پوشه
    // ═══════════════════════════════════════════════════════════
    private function rrmdir(string $dir): void {
        if (!is_dir($dir)) return;
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) $this->rrmdir($path);
            else @unlink($path);
        }
        @rmdir($dir);
    }
}
