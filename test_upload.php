<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تست آپلود مستقیم</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
</head>
<body class="p-4">
    <div class="container">
        <h3>تست آپلود مستقیم (بدون JavaScript)</h3>
        <p>این فرم مستقیماً فایل را به <code>modules/real_estate/admin/ajax/upload.php</code> ارسال می‌کند.</p>
        
        <form action="modules/real_estate/admin/ajax/upload.php" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">انتخاب فایل:</label>
                <input type="file" name="file" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary">آپلود</button>
        </form>
        
        <hr class="my-4">
        
        <h5>نتیجه:</h5>
        <p>اگر روی «آپلود» کلیک کنید و صفحه‌ای با پیام JSON (مثل <code>{"success":true, "url":"..."}</code>) باز شود، یعنی <code>upload.php</code> سالم است و مشکل از JavaScript است.</p>
        <p>اگر خطا بدهد یا صفحه سفید باشد، مشکل از <code>upload.php</code> است.</p>
    </div>
</body>
</html>
