<?php
/**
 * PartoCMS - Content Translation Review
 * بازنگری و ویرایش ترجمه‌های محتوا
 *
 * @version 2.0
 * @date 2026-09-16
 */
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/includes/multi_translator.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . SITE_URL . '/admin/index.php');
    exit;
}

if (!isset($pdo) && function_exists('getDB')) $pdo = getDB();

// ============================================================
//   AJAX Endpoint: لیست Provider ها (بالاترین اولویت — قبل از هر چیز)
// ============================================================
if (($_GET['ajax'] ?? '') === 'providers') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache');

    $contentId = (int)($_GET['content_id'] ?? 0);
    $langId    = (int)($_GET['language_id'] ?? 0);

    if ($contentId <= 0 || $langId <= 0) {
        echo json_encode(['ok' => false, 'error' => 'پارامترها نامعتبر']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT provider, text, quality_score, is_selected, created_at
            FROM translation_versions
            WHERE content_id = ? AND language_id = ? AND field_name = 'title'
            ORDER BY is_selected DESC, quality_score DESC
        ");
        $stmt->execute([$contentId, $langId]);
        $versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'ok' => true,
            'content_id' => $contentId,
            'language_id' => $langId,
            'count' => count($versions),
            'versions' => $versions,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
//   عملیات POST
// ============================================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- ۱. ذخیره ویرایش دستی ----------
    if ($action === 'save_edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = $_POST['content'] ?? '';

        try {
            $stmt = $pdo->prepare("
                UPDATE content_translations
                SET title = ?, excerpt = ?, content = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $excerpt, $content, $id]);
            $message = "✅ ویرایش ذخیره شد (ID: $id)";
            $messageType = "success";
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    // ---------- ۲. حذف ترجمه ----------
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM content_translations WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM translation_versions WHERE content_id = (SELECT content_id FROM content_translations WHERE id = ?)")->execute([$id]);
            $message = "✅ ترجمه حذف شد";
            $messageType = "success";
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    // ---------- ۳. تولید مجدد تک ----------
    elseif ($action === 'regenerate') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = $pdo->prepare("
                SELECT ct.content_id, l.code as lang_code
                FROM content_translations ct
                JOIN languages l ON l.id = ct.language_id
                WHERE ct.id = ?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $message = "❌ ترجمه یافت نشد";
                $messageType = "danger";
            } else {
                $pdo->prepare("DELETE FROM translation_cache WHERE target_lang = ?")
                    ->execute([substr($row['lang_code'], 0, 2)]);

                $mt = new MultiTranslator($pdo);
                $result = $mt->translateContent(
                    (int)$row['content_id'],
                    $row['lang_code'],
                    $_SESSION['user_id'] ?? null
                );

                if (!empty($result['ok'])) {
                    $message = "✅ ترجمه مجدد انجام شد (avg: {$result['avg_score']})";
                    $messageType = "success";
                } else {
                    $message = "❌ خطا: " . ($result['error'] ?? 'نامشخص');
                    $messageType = "danger";
                }
            }
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    // ---------- ۴. تغییر Provider ----------
    elseif ($action === 'switch_provider') {
        $contentId = (int)($_POST['content_id'] ?? 0);
        $langId    = (int)($_POST['language_id'] ?? 0);
        $provider  = trim($_POST['provider'] ?? '');

        try {
            // غیرفعال کردن همه برای این content+lang
            $pdo->prepare("
                UPDATE translation_versions
                SET is_selected = 0
                WHERE content_id = ? AND language_id = ?
            ")->execute([$contentId, $langId]);

            // فعال کردن provider انتخاب‌شده (همه فیلدها)
            $pdo->prepare("
                UPDATE translation_versions
                SET is_selected = 1
                WHERE content_id = ? AND language_id = ? AND provider = ?
            ")->execute([$contentId, $langId, $provider]);

            // آپدیت content_translations از همه فیلدهای provider انتخابی
            $fields = ['title', 'excerpt', 'content'];
            $updates = [];
            $params = [];

            foreach ($fields as $field) {
                $stmt = $pdo->prepare("
                    SELECT text FROM translation_versions
                    WHERE content_id = ? AND language_id = ? AND provider = ? AND field_name = ?
                    LIMIT 1
                ");
                $stmt->execute([$contentId, $langId, $provider, $field]);
                $text = $stmt->fetchColumn();
                if ($text !== false) {
                    $updates[] = "$field = ?";
                    $params[] = $text;
                }
            }

            if (!empty($updates)) {
                $params[] = $contentId;
                $params[] = $langId;
                $sql = "UPDATE content_translations SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE content_id = ? AND language_id = ?";
                $pdo->prepare($sql)->execute($params);
            }

            $message = "✅ Provider تغییر کرد به: $provider";
            $messageType = "success";
        } catch (Throwable $e) {
            $message = "❌ خطا: " . $e->getMessage();
            $messageType = "danger";
        }
    }

    // ---------- ۵. تولید مجدد گروهی ----------
    elseif ($action === 'bulk_regenerate') {
        $ids = $_POST['ids'] ?? [];
        $ok = 0; $fail = 0;

        if (empty($ids)) {
            $message = "⚠️ هیچ موردی انتخاب نشده";
            $messageType = "warning";
        } else {
            $mt = new MultiTranslator($pdo);
            foreach ($ids as $id) {
                $id = (int)$id;
                try {
                    $stmt = $pdo->prepare("
                        SELECT ct.content_id, l.code as lang_code
                        FROM content_translations ct
                        JOIN languages l ON l.id = ct.language_id
                        WHERE ct.id = ?
                    ");
                    $stmt->execute([$id]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if (!$row) { $fail++; continue; }

                    $pdo->prepare("DELETE FROM translation_cache WHERE target_lang = ?")
                        ->execute([substr($row['lang_code'], 0, 2)]);

                    $r = $mt->translateContent(
                        (int)$row['content_id'],
                        $row['lang_code'],
                        $_SESSION['user_id'] ?? null
                    );
                    if (!empty($r['ok'])) $ok++; else $fail++;
                } catch (Throwable $e) {
                    $fail++;
                }
            }
            $message = "✅ $ok موفق، $fail خطا";
            $messageType = $ok > 0 ? "success" : "danger";
        }
    }
}

// ============================================================
//   فیلترها
// ============================================================
$filterLang      = (int)($_GET['lang'] ?? 0);
$filterProvider  = trim($_GET['provider'] ?? '');
$filterMinScore  = (isset($_GET['min_score']) && $_GET['min_score'] !== '') ? (int)$_GET['min_score'] : null;
$filterMaxScore  = (isset($_GET['max_score']) && $_GET['max_score'] !== '') ? (int)$_GET['max_score'] : null;
$filterStatus    = trim($_GET['status'] ?? '');
$filterContentId = (int)($_GET['content_id'] ?? 0);
$sortBy          = $_GET['sort'] ?? 'score_asc';

// ---------- ساخت WHERE برای کوئری داخلی ----------
$innerWhere = ["1=1"];
$params = [];

if ($filterLang > 0) {
    $innerWhere[] = "ct.language_id = ?";
    $params[] = $filterLang;
}
if ($filterStatus !== '') {
    $innerWhere[] = "ct.status = ?";
    $params[] = $filterStatus;
}
if ($filterContentId > 0) {
    $innerWhere[] = "ct.content_id = ?";
    $params[] = $filterContentId;
}

$innerWhereSql = implode(" AND ", $innerWhere);

// ---------- ساخت WHERE برای کوئری بیرونی (روی Derived Table) ----------
$outerWhere = ["1=1"];

if ($filterMinScore !== null) {
    $outerWhere[] = "avg_score >= " . (int)$filterMinScore;
}
if ($filterMaxScore !== null) {
    $outerWhere[] = "avg_score <= " . (int)$filterMaxScore;
}
if ($filterProvider !== '') {
    $outerWhere[] = "provider = " . $pdo->quote($filterProvider);
}

$outerWhereSql = implode(" AND ", $outerWhere);

// ---------- مرتب‌سازی ----------
$orderSql = match($sortBy) {
    'score_desc' => 'avg_score DESC, translated_at DESC',
    'date_asc'   => 'translated_at ASC',
    'date_desc'  => 'translated_at DESC',
    'content_id' => 'content_id ASC, language_id ASC',
    'provider'   => 'provider ASC, avg_score DESC',
    default      => 'avg_score ASC, translated_at DESC',
};

// ============================================================
//   کوئری اصلی با Derived Table (رفع مشکل ORDER BY/HAVING)
// ============================================================
$sql = "
    SELECT * FROM (
        SELECT
            ct.id,
            ct.content_id,
            ct.language_id,
            ct.title,
            ct.excerpt,
            ct.content,
            ct.status,
            ct.translated_at,
            ct.updated_at,
            l.code        AS lang_code,
            l.name        AS lang_name,
            l.flag        AS lang_flag,
            l.native_name AS lang_native,
            l.direction   AS lang_direction,
            ci.title      AS source_title,
            COALESCE((
                SELECT ROUND(AVG(quality_score))
                FROM translation_versions tv
                WHERE tv.content_id = ct.content_id
                  AND tv.language_id = ct.language_id
            ), 0) AS avg_score,
            COALESCE((
                SELECT provider FROM translation_versions tv
                WHERE tv.content_id = ct.content_id
                  AND tv.language_id = ct.language_id
                  AND tv.is_selected = 1
                LIMIT 1
            ), '—') AS provider,
            (
                SELECT COUNT(DISTINCT provider)
                FROM translation_versions tv
                WHERE tv.content_id = ct.content_id
                  AND tv.language_id = ct.language_id
            ) AS provider_count
        FROM content_translations ct
        JOIN languages l ON l.id = ct.language_id
        LEFT JOIN content_items ci ON ci.id = ct.content_id
        WHERE $innerWhereSql
    ) AS sub
    WHERE $outerWhereSql
    ORDER BY $orderSql
    LIMIT 200
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $translations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $translations = [];
    $message = "❌ خطا در کوئری: " . $e->getMessage();
    $messageType = "danger";
}

// ============================================================
//   آمار کلی
// ============================================================
$stats = [
    'total'         => 0,
    'avg'           => 0,
    'high'          => 0,
    'mid'           => 0,
    'low'           => 0,
    'with_provider' => 0,
];

try {
    $statsRow = $pdo->query("
        SELECT
            COUNT(*) AS total,
            ROUND(AVG(score)) AS avg,
            SUM(CASE WHEN score >= 80 THEN 1 ELSE 0 END) AS high,
            SUM(CASE WHEN score >= 60 AND score < 80 THEN 1 ELSE 0 END) AS mid,
            SUM(CASE WHEN score < 60 THEN 1 ELSE 0 END) AS low,
            SUM(CASE WHEN provider_cnt > 0 THEN 1 ELSE 0 END) AS with_provider
        FROM (
            SELECT
                COALESCE((
                    SELECT ROUND(AVG(quality_score))
                    FROM translation_versions tv
                    WHERE tv.content_id = ct.content_id
                      AND tv.language_id = ct.language_id
                ), 0) AS score,
                (
                    SELECT COUNT(*)
                    FROM translation_versions tv
                    WHERE tv.content_id = ct.content_id
                      AND tv.language_id = ct.language_id
                ) AS provider_cnt
            FROM content_translations ct
        ) AS sub
    ")->fetch(PDO::FETCH_ASSOC);

    $stats['total']         = (int)($statsRow['total'] ?? 0);
    $stats['avg']           = (int)($statsRow['avg'] ?? 0);
    $stats['high']          = (int)($statsRow['high'] ?? 0);
    $stats['mid']           = (int)($statsRow['mid'] ?? 0);
    $stats['low']           = (int)($statsRow['low'] ?? 0);
    $stats['with_provider'] = (int)($statsRow['with_provider'] ?? 0);
} catch (Throwable $e) {}

// ============================================================
//   لیست‌های کمکی
// ============================================================
$languages = $pdo->query("
    SELECT id, code, name, native_name, flag, direction, is_default
    FROM languages
    WHERE is_active = 1
    ORDER BY sort_order, id
")->fetchAll(PDO::FETCH_ASSOC);

$providers = $pdo->query("
    SELECT DISTINCT provider
    FROM translation_versions
    WHERE provider IS NOT NULL AND provider != ''
    ORDER BY provider
")->fetchAll(PDO::FETCH_COLUMN);

$sidebarFile = __DIR__ . '/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>بازنگری ترجمه‌ها — <?= htmlspecialchars($siteName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media (max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#0891b2,#155e75);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:25px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

/* Stats */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:25px}
.stat-card{background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,.05);text-align:center;transition:transform .2s}
.stat-card:hover{transform:translateY(-2px)}
.stat-card .value{font-size:26px;font-weight:bold;margin-bottom:5px}
.stat-card .label{font-size:12px;color:#64748b}
.stat-card.total .value{color:#0891b2}
.stat-card.avg .value{color:#7c3aed}
.stat-card.high .value{color:#10b981}
.stat-card.mid .value{color:#f59e0b}
.stat-card.low .value{color:#ef4444}
.stat-card.provider .value{color:#6366f1}

/* Cards */
.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.card .body{padding:20px}

/* Filters */
.filter-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;align-items:end}
.filter-group label{display:block;font-size:12px;color:#64748b;margin-bottom:5px;font-weight:bold}
.filter-group select,.filter-group input{width:100%;padding:8px 12px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box}
.filter-group select:focus,.filter-group input:focus{outline:none;border-color:#0891b2}

/* Buttons */
.btn-a{padding:8px 16px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:12px;color:#fff;display:inline-flex;align-items:center;gap:5px;transition:.2s;text-decoration:none}
.btn-a:hover{transform:translateY(-1px);opacity:.9;color:#fff}
.btn-primary-a{background:linear-gradient(135deg,#0891b2,#155e75)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-danger-a{background:linear-gradient(135deg,#ef4444,#b91c1c)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-info-a{background:linear-gradient(135deg,#6366f1,#4f46e5)}
.btn-sm{padding:5px 10px;font-size:11px}

/* Table */
.review-table{width:100%;border-collapse:collapse;font-size:13px}
.review-table th{background:#334155;color:#fff;padding:12px 10px;text-align:right;font-size:12px;font-weight:bold;white-space:nowrap}
.review-table td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.review-table tr:hover td{background:#f8fafc}
.review-table .content-id{font-family:monospace;color:#0891b2;font-weight:bold;cursor:pointer;font-size:11px}
.review-table .content-id:hover{text-decoration:underline}
.review-table .lang{display:inline-flex;align-items:center;gap:5px;font-weight:bold;font-size:12px}
.review-table .provider{display:inline-block;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold;background:#e0f2fe;color:#0369a1}
.review-table .provider.deepl{background:#d1fae5;color:#065f46}
.review-table .provider.microsoft{background:#dbeafe;color:#1e40af}
.review-table .provider.google{background:#fef3c7;color:#92400e}
.review-table .provider.mymemory{background:#fee2e2;color:#991b1b}
.review-table .provider.yandex{background:#fce7f3;color:#9d174d}

/* Badges */
.score-badge{display:inline-block;min-width:40px;padding:4px 8px;border-radius:12px;font-weight:bold;font-size:11px;text-align:center}
.score-high{background:#d1fae5;color:#065f46}
.score-mid{background:#fef3c7;color:#92400e}
.score-low{background:#fee2e2;color:#991b1b}
.score-none{background:#f1f5f9;color:#64748b}

.status-badge{display:inline-block;padding:3px 9px;border-radius:10px;font-size:10px;font-weight:bold}
.status-draft{background:#fef3c7;color:#92400e}
.status-auto{background:#e0e7ff;color:#4338ca}
.status-published{background:#d1fae5;color:#065f46}

/* Row actions */
.row-actions{display:flex;gap:4px;flex-wrap:wrap}
.row-actions .btn-a{width:32px;height:32px;padding:0;justify-content:center}

/* Modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center;padding:20px}
.modal-bg.active{display:flex}
.modal-box{background:#fff;border-radius:12px;max-width:800px;width:100%;max-height:90vh;overflow-y:auto}
.modal-box.wide{max-width:1000px}
.modal-box .modal-header{padding:15px 22px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center}
.modal-box .modal-header h5{margin:0;font-size:16px;color:#1e293b}
.modal-box .modal-body{padding:22px}
.modal-box .modal-footer{padding:15px 22px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px}
.modal-close{background:none;border:none;font-size:22px;cursor:pointer;color:#64748b;line-height:1}
.modal-close:hover{color:#ef4444}

.form-group{margin-bottom:14px}
.form-group label{display:block;font-weight:bold;font-size:12px;color:#374151;margin-bottom:6px}
.form-group input,.form-group textarea{width:100%;padding:9px 13px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box}
.form-group textarea{min-height:200px;resize:vertical}
.form-group textarea.ltr{direction:ltr;text-align:left;font-family:monospace}

/* Providers table in modal */
.providers-table{width:100%;border-collapse:collapse;font-size:13px}
.providers-table th{background:#f8fafc;padding:10px;text-align:right;font-size:11px;color:#64748b;border-bottom:1px solid #e2e8f0}
.providers-table td{padding:10px;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.providers-table tr:hover td{background:#f8fafc}
.providers-table .selected-row{background:#ecfdf5}
.providers-table .provider-name{font-weight:bold;color:#0891b2;font-family:monospace}
.providers-table .text-preview{font-size:11px;color:#64748b;max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

@media (max-width:768px){
    .review-table{font-size:11px}
    .review-table th,.review-table td{padding:6px 4px}
    .row-actions{flex-direction:column}
    .row-actions .btn-a{width:100%;height:auto;padding:5px 8px}
}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <!-- Header -->
    <div class="page-header">
        <h1>🔍 بازنگری ترجمه‌ها</h1>
        <p>بررسی کیفیت، ویرایش دستی، تغییر Provider و تولید مجدد ترجمه‌ها</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="value"><?= number_format($stats['total']) ?></div>
            <div class="label">کل ترجمه‌ها</div>
        </div>
        <div class="stat-card avg">
            <div class="value"><?= $stats['avg'] ?></div>
            <div class="label">میانگین کیفیت</div>
        </div>
        <div class="stat-card high">
            <div class="value"><?= number_format($stats['high']) ?></div>
            <div class="label">کیفیت ≥ 80</div>
        </div>
        <div class="stat-card mid">
            <div class="value"><?= number_format($stats['mid']) ?></div>
            <div class="label">کیفیت 60-79</div>
        </div>
        <div class="stat-card low">
            <div class="value"><?= number_format($stats['low']) ?></div>
            <div class="label">کیفیت &lt; 60</div>
        </div>
        <div class="stat-card provider">
            <div class="value"><?= number_format($stats['with_provider']) ?></div>
            <div class="label">با Provider فعال</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card">
        <div class="header">
            <span>🎯 فیلترها</span>
            <?php if ($filterLang || $filterProvider || $filterMinScore !== null || $filterMaxScore !== null || $filterStatus || $filterContentId): ?>
                <span style="font-size:11px;color:#0891b2;font-weight:normal">فیلتر فعال است</span>
            <?php endif; ?>
        </div>
        <div class="body">
            <form method="get" class="filter-row">
                <div class="filter-group">
                    <label>زبان</label>
                    <select name="lang">
                        <option value="">— همه —</option>
                        <?php foreach ($languages as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $filterLang === (int)$l['id'] ? 'selected' : '' ?>>
                                <?= $l['flag'] ?> <?= htmlspecialchars($l['native_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Provider</label>
                    <select name="provider">
                        <option value="">— همه —</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $filterProvider === $p ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>حداقل امتیاز</label>
                    <input type="number" name="min_score" min="0" max="100"
                           value="<?= $filterMinScore ?? '' ?>" placeholder="0">
                </div>

                <div class="filter-group">
                    <label>حداکثر امتیاز</label>
                    <input type="number" name="max_score" min="0" max="100"
                           value="<?= $filterMaxScore ?? '' ?>" placeholder="100">
                </div>

                <div class="filter-group">
                    <label>وضعیت</label>
                    <select name="status">
                        <option value="">— همه —</option>
                        <option value="draft"     <?= $filterStatus === 'draft'     ? 'selected' : '' ?>>پیش‌نویس</option>
                        <option value="auto"      <?= $filterStatus === 'auto'      ? 'selected' : '' ?>>خودکار</option>
                        <option value="published" <?= $filterStatus === 'published' ? 'selected' : '' ?>>منتشر شده</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>ID مقاله</label>
                    <input type="number" name="content_id"
                           value="<?= $filterContentId ?: '' ?>" placeholder="مثلاً 5">
                </div>

                <div class="filter-group">
                    <label>مرتب‌سازی</label>
                    <select name="sort">
                        <option value="score_asc"  <?= $sortBy === 'score_asc'  ? 'selected' : '' ?>>امتیاز ↑</option>
                        <option value="score_desc" <?= $sortBy === 'score_desc' ? 'selected' : '' ?>>امتیاز ↓</option>
                        <option value="date_desc"  <?= $sortBy === 'date_desc'  ? 'selected' : '' ?>>جدیدترین</option>
                        <option value="date_asc"   <?= $sortBy === 'date_asc'   ? 'selected' : '' ?>>قدیمی‌ترین</option>
                        <option value="content_id" <?= $sortBy === 'content_id' ? 'selected' : '' ?>>ID مقاله</option>
                        <option value="provider"   <?= $sortBy === 'provider'   ? 'selected' : '' ?>>Provider</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-a btn-primary-a" style="width:100%;justify-content:center">
                        <i class="bi bi-funnel"></i> اعمال
                    </button>
                </div>
            </form>

            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                <a href="content_review.php" class="btn-a btn-secondary-a btn-sm">
                    <i class="bi bi-x-circle"></i> پاک کردن
                </a>
                <a href="?min_score=0&max_score=59&sort=score_asc" class="btn-a btn-danger-a btn-sm">
                    <i class="bi bi-exclamation-triangle"></i> ضعیف (&lt; 60)
                </a>
                <a href="?min_score=60&max_score=79&sort=score_asc" class="btn-a btn-warning-a btn-sm">
                    <i class="bi bi-dash-circle"></i> متوسط (60-79)
                </a>
                <a href="?min_score=80&max_score=100&sort=score_desc" class="btn-a btn-success-a btn-sm">
                    <i class="bi bi-check-circle"></i> خوب (≥ 80)
                </a>
                <a href="?lang=2" class="btn-a btn-info-a btn-sm">
                    🇺🇸 انگلیسی
                </a>
                <a href="?lang=4" class="btn-a btn-info-a btn-sm">
                    🇸🇦 عربی
                </a>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="header">
            <span>📋 ترجمه‌ها</span>
            <span style="color:#64748b;font-weight:normal;font-size:12px"><?= count($translations) ?> نتیجه</span>
        </div>

        <?php if (empty($translations)): ?>
            <div class="body" style="text-align:center;padding:50px 20px;color:#64748b">
                <i class="bi bi-inbox" style="font-size:60px;display:block;margin-bottom:15px;color:#cbd5e1"></i>
                <h5 style="color:#475569">نتیجه‌ای یافت نشد</h5>
                <p style="font-size:13px">فیلترها را تغییر دهید یا مقاله جدید ترجمه کنید.</p>
                <a href="content_translate.php" class="btn-a btn-primary-a" style="margin-top:10px">
                    <i class="bi bi-translate"></i> رفتن به ترجمه محتوا
                </a>
            </div>
        <?php else: ?>
        <form method="post" id="bulkForm">
            <input type="hidden" name="action" value="bulk_regenerate">
            <div style="overflow-x:auto">
                <table class="review-table">
                    <thead>
                        <tr>
                            <th style="width:30px"><input type="checkbox" id="checkAll"></th>
                            <th>ID</th>
                            <th>مقاله</th>
                            <th>زبان</th>
                            <th>Provider</th>
                            <th>امتیاز</th>
                            <th>وضعیت</th>
                            <th>تاریخ</th>
                            <th style="width:160px">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($translations as $t): ?>
                        <?php
                            $score = $t['avg_score'] !== null ? (int)$t['avg_score'] : null;
                            $scoreClass = 'score-none';
                            if ($score !== null && $score > 0) {
                                if ($score >= 80) $scoreClass = 'score-high';
                                elseif ($score >= 60) $scoreClass = 'score-mid';
                                else $scoreClass = 'score-low';
                            }
                            $providerClass = '';
                            $provLower = strtolower($t['provider'] ?? '');
                            if (in_array($provLower, ['deepl','microsoft','google','mymemory','yandex'])) {
                                $providerClass = $provLower;
                            }
                        ?>
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="<?= $t['id'] ?>" class="row-check"></td>
                            <td>
                                <span class="content-id" onclick="openEdit(<?= $t['id'] ?>)" title="کلیک برای ویرایش">
                                    #<?= $t['content_id'] ?>-<?= $t['language_id'] ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight:bold;font-size:12px">
                                    <?= htmlspecialchars(mb_substr($t['title'] ?: $t['source_title'] ?: '(بدون عنوان)', 0, 45)) ?>
                                </div>
                                <div style="font-size:10px;color:#94a3b8">
                                    <?= htmlspecialchars(mb_substr($t['source_title'] ?? '', 0, 40)) ?>
                                </div>
                            </td>
                            <td>
                                <span class="lang">
                                    <?= $t['lang_flag'] ?>
                                    <?= htmlspecialchars($t['lang_code']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($t['provider'] && $t['provider'] !== '—'): ?>
                                    <span class="provider <?= $providerClass ?>">
                                        <?= htmlspecialchars($t['provider']) ?>
                                    </span>
                                    <?php if ((int)$t['provider_count'] > 1): ?>
                                        <span style="font-size:10px;color:#94a3b8">+<?= ((int)$t['provider_count'] - 1) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#94a3b8;font-size:11px">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="score-badge <?= $scoreClass ?>">
                                    <?= $score ?? '—' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $t['status'] ?>">
                                    <?= $t['status'] ?>
                                </span>
                            </td>
                            <td style="font-size:11px;color:#64748b;white-space:nowrap">
                                <?= date('Y/m/d', strtotime($t['translated_at'])) ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn-a btn-primary-a btn-sm"
                                            onclick="openEdit(<?= $t['id'] ?>)" title="ویرایش">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn-a btn-warning-a btn-sm"
                                            onclick="regenerate(<?= $t['id'] ?>)" title="تولید مجدد">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button type="button" class="btn-a btn-info-a btn-sm"
                                            onclick="openProviders(<?= $t['content_id'] ?>, <?= $t['language_id'] ?>)"
                                            title="Provider ها">
                                        <i class="bi bi-list-ul"></i>
                                    </button>
                                    <button type="button" class="btn-a btn-danger-a btn-sm"
                                            onclick="del(<?= $t['id'] ?>)" title="حذف">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="padding:15px 22px;border-top:1px solid #e2e8f0;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <button type="submit" class="btn-a btn-warning-a" onclick="return confirm('تولید مجدد همه انتخاب‌شده‌ها؟')">
                    <i class="bi bi-arrow-clockwise"></i> تولید مجدد انتخاب‌شده‌ها
                </button>
                <span id="selectedCount" style="color:#64748b;font-size:12px">0 انتخاب</span>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <a href="content_translate.php" class="btn-a btn-secondary-a">
        <i class="bi bi-arrow-right"></i> بازگشت به ترجمه محتوا
    </a>
</div>

<!-- ==================== Modal: Edit ==================== -->
<div class="modal-bg" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h5>✏️ ویرایش ترجمه <span id="editIdBadge" style="color:#0891b2;font-size:13px"></span></h5>
            <button type="button" class="modal-close" onclick="closeModal('editModal')">×</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="save_edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-body">
                <div class="form-group">
                    <label>عنوان</label>
                    <input type="text" name="title" id="editTitle">
                </div>
                <div class="form-group">
                    <label>خلاصه</label>
                    <textarea name="excerpt" id="editExcerpt" style="min-height:100px"></textarea>
                </div>
                <div class="form-group">
                    <label>محتوا</label>
                    <textarea name="content" id="editContent" class="ltr" style="min-height:350px"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-a btn-secondary-a" onclick="closeModal('editModal')">انصراف</button>
                <button type="submit" class="btn-a btn-primary-a">
                    <i class="bi bi-save"></i> ذخیره
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== Modal: Providers ==================== -->
<div class="modal-bg" id="providersModal">
    <div class="modal-box wide">
        <div class="modal-header">
            <h5>🎯 انتخاب Provider <span id="providersIdBadge" style="color:#0891b2;font-size:13px"></span></h5>
            <button type="button" class="modal-close" onclick="closeModal('providersModal')">×</button>
        </div>
        <div class="modal-body" id="providersBody">
            <div style="text-align:center;padding:30px;color:#64748b">
                <i class="bi bi-hourglass-split" style="font-size:32px"></i>
                <p>در حال بارگذاری...</p>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Hidden Action Form ==================== -->
<form method="post" id="actionForm" style="display:none">
    <input type="hidden" name="action" id="actionForm_action">
    <input type="hidden" name="id" id="actionForm_id">
    <input type="hidden" name="content_id" id="actionForm_content_id">
    <input type="hidden" name="language_id" id="actionForm_language_id">
    <input type="hidden" name="provider" id="actionForm_provider">
</form>

<!-- ==================== JavaScript ==================== -->
<script>
const TRANSLATIONS = <?= json_encode(array_map(fn($t) => [
    'id'          => (int)$t['id'],
    'content_id'  => (int)$t['content_id'],
    'language_id' => (int)$t['language_id'],
    'title'       => $t['title'],
    'excerpt'     => $t['excerpt'],
    'content'     => $t['content'],
    'lang_code'   => $t['lang_code'],
], $translations), JSON_UNESCAPED_UNICODE) ?>;

// ---------- Edit Modal ----------
function openEdit(id) {
    const t = TRANSLATIONS.find(x => x.id === id);
    if (!t) { alert('ترجمه یافت نشد'); return; }

    document.getElementById('editId').value = t.id;
    document.getElementById('editIdBadge').textContent = `#${t.content_id}-${t.language_id} (${t.lang_code})`;
    document.getElementById('editTitle').value = t.title || '';
    document.getElementById('editExcerpt').value = t.excerpt || '';
    document.getElementById('editContent').value = t.content || '';
    document.getElementById('editModal').classList.add('active');
}

// ---------- Close Modal ----------
function closeModal(name) {
    document.getElementById(name).classList.remove('active');
}

// ---------- Regenerate ----------
function regenerate(id) {
    if (!confirm('ترجمه مجدد با Provider ها؟ (DeepL → Microsoft → Google → MyMemory)')) return;
    document.getElementById('actionForm_action').value = 'regenerate';
    document.getElementById('actionForm_id').value = id;
    document.getElementById('actionForm').submit();
}

// ---------- Delete ----------
function del(id) {
    if (!confirm('این ترجمه حذف شود؟ (قابل بازگشت نیست)')) return;
    document.getElementById('actionForm_action').value = 'delete';
    document.getElementById('actionForm_id').value = id;
    document.getElementById('actionForm').submit();
}

// ---------- Providers Modal (AJAX) ----------
function openProviders(contentId, langId) {
    const modal = document.getElementById('providersModal');
    const body = document.getElementById('providersBody');

    document.getElementById('providersIdBadge').textContent = `#${contentId}-${langId}`;
    body.innerHTML = '<div style="text-align:center;padding:30px;color:#64748b"><i class="bi bi-hourglass-split" style="font-size:32px"></i><p>در حال بارگذاری...</p></div>';
    modal.classList.add('active');

    const url = '?ajax=providers&content_id=' + encodeURIComponent(contentId) + '&language_id=' + encodeURIComponent(langId);

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                body.innerHTML = '<div class="alert alert-danger">خطا: ' + (data.error || '?') + '</div>';
                return;
            }

            if (!data.versions || data.versions.length === 0) {
                body.innerHTML = '<div class="alert alert-warning">هیچ نسخه‌ای در translation_versions یافت نشد. ابتدا ترجمه را «تولید مجدد» کنید.</div>';
                return;
            }

            let html = '<p style="color:#64748b;font-size:12px;margin-bottom:12px">' + data.count + ' نسخه یافت شد. Provider مورد نظر را انتخاب کنید:</p>';
            html += '<table class="providers-table">';
            html += '<thead><tr>';
            html += '<th>Provider</th>';
            html += '<th>امتیاز</th>';
            html += '<th>وضعیت</th>';
            html += '<th>پیش‌نمایش متن</th>';
            html += '<th style="width:80px"></th>';
            html += '</tr></thead><tbody>';

            data.versions.forEach(v => {
                const isSelected = v.is_selected == 1;
                const rowClass = isSelected ? 'selected-row' : '';
                const preview = (v.text || '').substring(0, 80);
                const score = v.quality_score != null ? v.quality_score : '—';

                html += `<tr class="${rowClass}">`;
                html += `<td><span class="provider-name">${escapeHtml(v.provider)}</span></td>`;
                html += `<td><strong>${score}</strong></td>`;
                html += `<td>${isSelected ? '<span style="color:#10b981;font-size:11px">✅ انتخاب‌شده</span>' : '<span style="color:#94a3b8;font-size:11px">—</span>'}</td>`;
                html += `<td><div class="text-preview" title="${escapeHtml(v.text || '')}">${escapeHtml(preview)}</div></td>`;
                html += '<td>';
                if (!isSelected) {
                    html += `<button type="button" class="btn-a btn-success-a btn-sm" onclick="switchProvider(${contentId}, ${langId}, '${escapeJs(v.provider)}')">انتخاب</button>`;
                } else {
                    html += '<span style="color:#10b981;font-size:11px">فعال</span>';
                }
                html += '</td></tr>';
            });

            html += '</tbody></table>';
            body.innerHTML = html;
        })
        .catch(e => {
            body.innerHTML = '<div class="alert alert-danger">خطای شبکه: ' + escapeHtml(e.message) + '</div>';
        });
}

// ---------- Switch Provider ----------
function switchProvider(contentId, langId, provider) {
    if (!confirm('Provider تغییر کند به: ' + provider + '؟')) return;
    document.getElementById('actionForm_action').value = 'switch_provider';
    document.getElementById('actionForm_content_id').value = contentId;
    document.getElementById('actionForm_language_id').value = langId;
    document.getElementById('actionForm_provider').value = provider;
    document.getElementById('actionForm').submit();
}

// ---------- Escape Helpers ----------
function escapeHtml(str) {
    if (str == null) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
function escapeJs(str) {
    if (str == null) return '';
    return String(str).replace(/'/g, "\\'").replace(/\\/g, '\\\\');
}

// ---------- Bulk Select ----------
const checkAllEl = document.getElementById('checkAll');
if (checkAllEl) {
    checkAllEl.addEventListener('change', function() {
        document.querySelectorAll('.row-check').forEach(c => c.checked = this.checked);
        updateSelectedCount();
    });
}
document.querySelectorAll('.row-check').forEach(c => {
    c.addEventListener('change', updateSelectedCount);
});

function updateSelectedCount() {
    const n = document.querySelectorAll('.row-check:checked').length;
    const el = document.getElementById('selectedCount');
    if (el) el.textContent = n + ' انتخاب';
}

// ---------- Modal Close on ESC ----------
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-bg.active').forEach(m => m.classList.remove('active'));
    }
});

// ---------- Click outside modal to close ----------
document.querySelectorAll('.modal-bg').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) this.classList.remove('active');
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
