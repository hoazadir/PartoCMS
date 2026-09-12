<?php
require_once __DIR__ . '/../config.php';

// چک لاگین
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$perm = getPermissions();
if (!$perm->can($_SESSION['user_id'], 'content.view')) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');

$success = '';
$error = '';

// ==================== آپلود ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'خطا در آپلود فایل (کد ' . $file['error'] . ')';
    } else {
        // بررسی نوع فایل
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file["tmp_name"]);

        if (!in_array($mime, $allowed)) {
            $error = 'فقط تصویر مجاز است (JPG, PNG, GIF, WEBP, SVG)';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'حجم فایل بیشتر از ۵ مگابایت است';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/uploads/';
            $filepath = $uploadDir . $filename;

            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // گرفتن ابعاد تصویر
                $width = $height = null;
                if ($mime !== 'image/svg+xml') {
                    $size = @getimagesize($filepath);
                    if ($size) {
                        $width = $size[0];
                        $height = $size[1];
                    }
                }

                $altText = trim($_POST['alt_text'] ?? '');

                $stmt = $pdo->prepare("
                    INSERT INTO media (filename, original_name, filepath, mime_type, file_size, width, height, alt_text, uploaded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $filename,
                    $file['name'],
                    'assets/uploads/' . $filename,
                    $mime,
                    $file['size'],
                    $width,
                    $height,
                    $altText,
                    $_SESSION['user_id']
                ]);

                header('Location: media.php?msg=uploaded');
                exit;
            } else {
                $error = 'خطا در ذخیره فایل';
            }
        }
    }
}

// ==================== حذف ====================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT filepath FROM media WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $media = $stmt->fetch();

    if ($media) {
        $fullPath = __DIR__ . '/../' . $media['filepath'];
        if (file_exists($fullPath)) @unlink($fullPath);

        $pdo->prepare("DELETE FROM media WHERE id = ?")->execute([$_GET['delete']]);
    }
    header('Location: media.php?msg=deleted');
    exit;
}

if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'uploaded' ? 'فایل با موفقیت آپلود شد' : 'فایل حذف شد';
}

$mediaList = $pdo->query("
    SELECT m.*, u.username as uploader
    FROM media m
    LEFT JOIN users u ON u.id = m.uploaded_by
    ORDER BY m.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت رسانه‌ها | <?= htmlspecialchars($siteName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Tahoma, sans-serif; background: #f4f6f9; margin: 0; }
        .sidebar { background: #2c3e50; min-height: 100vh; color: #fff; padding: 0; position: fixed; right: 0; top: 0; width: 240px; z-index: 100; overflow-y: auto; }
        .sidebar .brand { padding: 20px; text-align: center; border-bottom: 1px solid #34495e; }
        .sidebar a { color: #ecf0f1; text-decoration: none; padding: 14px 20px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #34495e; font-size: 14px; }
        .sidebar a:hover, .sidebar a.active { background: #3498db; }
        .main { margin-right: 240px; padding: 25px; }
        .top-bar { background: #fff; padding: 15px 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .upload-zone { background: #fff; border: 3px dashed #bdc3c7; border-radius: 12px; padding: 40px; text-align: center; margin-bottom: 25px; cursor: pointer; transition: all 0.2s; }
        .upload-zone:hover { border-color: #3498db; background: #f0f7ff; }
        .upload-zone.dragover { border-color: #27ae60; background: #d4edda; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 15px; }
        .media-card { background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: relative; transition: all 0.2s; }
        .media-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.12); }
        .media-card img { width: 100%; height: 140px; object-fit: cover; display: block; }
        .media-card .info { padding: 10px; font-size: 11px; }
        .media-card .info .name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: bold; }
        .media-card .actions { position: absolute; top: 5px; left: 5px; display: flex; gap: 3px; opacity: 0; transition: opacity 0.2s; }
        .media-card:hover .actions { opacity: 1; }
        .media-card .actions a { background: rgba(255,255,255,0.9); border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; color: #e74c3c; text-decoration: none; }
        @media (max-width: 768px) {
            .sidebar { width: 60px; }
            .sidebar .brand h5, .sidebar .brand small, .sidebar a span { display: none; }
            .sidebar a { justify-content: center; }
            .main { margin-right: 60px; padding: 15px; }
        }
    </style>
</head>
<body>

<?php require_once __DIR__ . '/includes/sidebar.php'; ?>

<div class="main">
    <div class="top-bar">
        <h4 style="margin:0;">🖼 مدیریت رسانه‌ها</h4>
        <span class="badge bg-info"><?= count($mediaList) ?> فایل</span>
    </div>

    <?php if ($success): ?><div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">❌ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- آپلود -->
    <form method="post" enctype="multipart/form-data" id="uploadForm">
        <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
            <div style="font-size: 60px;">📤</div>
            <h5>فایل را بکشید و رها کنید یا کلیک کنید</h5>
            <p class="text-muted" style="margin:0;">JPG, PNG, GIF, WEBP, SVG — حداکثر ۵ مگابایت</p>
        </div>
        <input type="file" name="file" id="fileInput" accept="image/*" style="display:none" onchange="this.form.submit()">
    </form>

    <!-- گالری -->
    <?php if (empty($mediaList)): ?>
        <div class="alert alert-info text-center py-5">
            <i class="bi bi-images fs-1 d-block mb-2"></i>
            هنوز فایلی آپلود نشده است
        </div>
    <?php else: ?>
        <div class="media-grid">
            <?php foreach ($mediaList as $m): ?>
                <div class="media-card">
                    <div class="actions">
                        <a href="?delete=<?= $m['id'] ?>" onclick="return confirm('حذف فایل؟')" title="حذف">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                    <img src="<?= SITE_URL ?>/<?= htmlspecialchars($m['filepath']) ?>" alt="<?= htmlspecialchars($m['alt_text'] ?: $m['original_name']) ?>">
                    <div class="info">
                        <div class="name" title="<?= htmlspecialchars($m['original_name']) ?>">
                            <?= htmlspecialchars($m['original_name']) ?>
                        </div>
                        <div class="text-muted">
                            <?= number_format($m['file_size'] / 1024, 1) ?> KB
                            <?php if ($m['width']): ?> | <?= $m['width'] ?>×<?= $m['height'] ?><?php endif; ?>
                        </div>
                        <div class="text-muted" style="font-size:10px;">
                            توسط: <?= htmlspecialchars($m['uploader'] ?? '-') ?>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary w-100 mt-2"
                                onclick="copyUrl('<?= SITE_URL ?>/<?= htmlspecialchars($m['filepath']) ?>')">
                            <i class="bi bi-clipboard"></i> کپی لینک
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function copyUrl(url) {
    navigator.clipboard.writeText(url).then(() => {
        alert('لینک کپی شد:\n' + url);
    });
}
// Drag & Drop
const zone = document.querySelector('.upload-zone');
['dragenter', 'dragover'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('dragover'); }));
['dragleave', 'drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('dragover'); }));
zone.addEventListener('drop', ev => {
    const file = ev.dataTransfer.files[0];
    if (file) {
        const input = document.getElementById('fileInput');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        document.getElementById('uploadForm').submit();
    }
});
</script>
</body>
</html>
