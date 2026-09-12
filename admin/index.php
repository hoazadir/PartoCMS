<?php
// admin/index.php
require_once __DIR__ . '/../config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');

// آمار
$stats = [
    'templates' => $pdo->query("SELECT COUNT(*) FROM templates")->fetchColumn(),
    'active_templates' => $pdo->query("SELECT COUNT(*) FROM templates WHERE is_active = 1")->fetchColumn(),
    'posts' => $pdo->query("SELECT COUNT(*) FROM content_items")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'comments' => (function($pdo) { try { return $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn(); } catch(Exception $e){ return 0; } })($pdo),
    'forms' => (function($pdo) { try { return $pdo->query("SELECT COUNT(*) FROM forms")->fetchColumn(); } catch(Exception $e){ return 0; } })($pdo),
    'pending_comments' => (function($pdo) { try { return $pdo->query("SELECT COUNT(*) FROM comments WHERE status='pending'")->fetchColumn(); } catch(Exception $e){ return 0; } })($pdo),
    'new_submissions' => (function($pdo) { try { return $pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status='new'")->fetchColumn(); } catch(Exception $e){ return 0; } })($pdo),
];

$recentPosts = $pdo->query("SELECT id, title, status, created_at FROM content_items ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentUsers = $pdo->query("SELECT id, username, role_id, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();

// نقش‌ها
$rolesMap = [];
foreach ($pdo->query("SELECT id, name, slug FROM roles")->fetchAll() as $r) {
    $rolesMap[$r['id']] = $r;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد | پنل مدیریت</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .main { margin-right: 260px; padding: 25px; }
        .stat-card {
            background: #fff; border-radius: 12px; padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; align-items: center; gap: 15px;
            transition: transform 0.2s;
            border-right: 4px solid transparent;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .icon {
            width: 55px; height: 55px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: #fff; flex-shrink: 0;
        }
        .stat-card .info .num { font-size: 26px; font-weight: bold; color: #1e293b; line-height: 1; }
        .stat-card .info .label { font-size: 12px; color: #7f8c8d; margin-top: 5px; }

        .card { border: none; box-shadow: 0 2px 10px rgba(0,0,0,0.05); border-radius: 12px; }
        .card-header { background: #fff; border-bottom: 1px solid #f0f0f0; padding: 15px 20px; border-radius: 12px 12px 0 0; font-weight: bold; color: #1e293b; }

        .quick-action {
            display: flex; align-items: center; gap: 12px;
            background: #f8fafc; border-radius: 10px; padding: 14px;
            text-decoration: none; color: #1e293b;
            margin-bottom: 10px; transition: all 0.2s;
            border: 1px solid #e2e8f0;
        }
        .quick-action:hover { background: #e0f2fe; border-color: #3b82f6; color: #1e40af; transform: translateX(-5px); }
        .quick-action .qa-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #fff; flex-shrink: 0;
        }

        .activity-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0; border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }
        .activity-item:last-child { border-bottom: none; }

        .badge-status { font-size: 10px; padding: 3px 8px; border-radius: 10px; }

        @media (max-width: 900px) {
            .main { margin-right: 70px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 style="margin:0; color:#1e293b;">📊 داشبورد</h3>
            <small class="text-muted">خوش آمدید، <?= htmlspecialchars($_SESSION['username'] ?? 'کاربر') ?></small>
        </div>
        <a href="../index.php" target="_blank" class="btn btn-primary">
            <i class="bi bi-globe"></i> مشاهده سایت
        </a>
    </div>

    <!-- آمار اصلی -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #667eea;">
                <div class="icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <i class="bi bi-file-text"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['posts'] ?></div>
                    <div class="label">مقالات</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #27ae60;">
                <div class="icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">
                    <i class="bi bi-people"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['users'] ?></div>
                    <div class="label">کاربران</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #f39c12;">
                <div class="icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                    <i class="bi bi-layers"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['templates'] ?></div>
                    <div class="label">قالب‌ها</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #3b82f6;">
                <div class="icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                    <i class="bi bi-chat-dots"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['comments'] ?></div>
                    <div class="label">دیدگاه‌ها</div>
                </div>
            </div>
        </div>
    </div>

    <!-- آمار ثانویه -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #8b5cf6;">
                <div class="icon" style="background: linear-gradient(135deg, #8b5cf6, #a78bfa);">
                    <i class="bi bi-ui-checks"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['forms'] ?></div>
                    <div class="label">فرم‌ها</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #ef4444;">
                <div class="icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['pending_comments'] ?></div>
                    <div class="label">دیدگاه در انتظار</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #f59e0b;">
                <div class="icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                    <i class="bi bi-envelope"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['new_submissions'] ?></div>
                    <div class="label">ارسال جدید</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card" style="border-right-color: #10b981;">
                <div class="icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div class="info">
                    <div class="num"><?= $stats['active_templates'] ?></div>
                    <div class="label">قالب فعال</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- دسترسی سریع -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-lightning-charge"></i> دسترسی سریع
                </div>
                <div class="card-body">
                    <a href="../modules/content/admin.php" class="quick-action">
                        <div class="qa-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">📝</div>
                        <div>
                            <div style="font-weight:bold;">مدیریت محتوا</div>
                            <small class="text-muted">ایجاد و ویرایش مقالات</small>
                        </div>
                    </a>
                    <a href="editor.php" class="quick-action">
                        <div class="qa-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">🎨</div>
                        <div>
                            <div style="font-weight:bold;">طراح قالب</div>
                            <small class="text-muted">با GrapesJS</small>
                        </div>
                    </a>
                    <a href="users.php" class="quick-action">
                        <div class="qa-icon" style="background: linear-gradient(135deg, #11998e, #38ef7d);">👥</div>
                        <div>
                            <div style="font-weight:bold;">کاربران</div>
                            <small class="text-muted">مدیریت اعضا</small>
                        </div>
                    </a>
                    <a href="settings.php" class="quick-action">
                        <div class="qa-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">⚙️</div>
                        <div>
                            <div style="font-weight:bold;">تنظیمات</div>
                            <small class="text-muted">پیکربندی سایت</small>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- آخرین مقالات -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-text"></i> آخرین مقالات</span>
                    <a href="../modules/content/admin.php" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentPosts)): ?>
                        <p class="text-center text-muted py-3">هنوز مقاله‌ای ساخته نشده</p>
                    <?php else: ?>
                        <?php foreach ($recentPosts as $p): ?>
                            <div class="activity-item">
                                <div style="flex:1;">
                                    <a href="../modules/content/admin.php?edit=<?= $p['id'] ?>" style="color:#1e293b;text-decoration:none;">
                                        <?= htmlspecialchars(mb_substr($p['title'], 0, 40)) ?>
                                    </a>
                                </div>
                                <?php
                                $statusMap = [
                                    'published' => ['label' => 'منتشر', 'color' => '#27ae60'],
                                    'draft' => ['label' => 'پیش‌نویس', 'color' => '#f39c12'],
                                    'archived' => ['label' => 'بایگانی', 'color' => '#95a5a6'],
                                ];
                                $s = $statusMap[$p['status']] ?? ['label' => $p['status'], 'color' => '#95a5a6'];
                                ?>
                                <span class="badge-status" style="background:<?= $s['color'] ?>;color:#fff;"><?= $s['label'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- آخرین کاربران -->
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-people"></i> آخرین کاربران</span>
                    <a href="users.php" class="btn btn-sm btn-outline-primary">مشاهده همه</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recentUsers)): ?>
                        <p class="text-center text-muted py-3">کاربری یافت نشد</p>
                    <?php else: ?>
                        <?php foreach ($recentUsers as $u): ?>
                            <div class="activity-item">
                                <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:bold;font-size:13px;">
                                    <?= htmlspecialchars(mb_substr($u['username'], 0, 1)) ?>
                                </div>
                                <div style="flex:1;">
                                    <strong><?= htmlspecialchars($u['username']) ?></strong>
                                    <div style="font-size:11px;color:#94a3b8;">
                                        <?= htmlspecialchars($rolesMap[$u['role_id']]['name'] ?? 'بدون نقش') ?>
                                    </div>
                                </div>
                                <small class="text-muted"><?= date('m/d', strtotime($u['created_at'])) ?></small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
