<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../admin/auth_check.php';
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>تست میکروفون در قالب PartoCMS</title></head>
<body style="font-family:Tahoma; text-align:center; padding:50px;">
    <h1>تست میکروفون در قالب PartoCMS</h1>
    <p>اگر اینجا کار کرد، یعنی مشکل از iframe یا مترجم گوگل است.</p>
    <button onclick="startMic()" style="padding:15px 30px; font-size:18px; background:#06b6d4; color:#fff; border:none; border-radius:8px; cursor:pointer;">🎤 شروع ضبط</button>
    <div id="result" style="margin-top:20px; font-size:18px; font-weight:bold;"></div>

    <script>
    async function startMic() {
        const resDiv = document.getElementById('result');
        resDiv.innerHTML = "⏳ در حال درخواست دسترسی...";
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            resDiv.innerHTML = "✅ موفقیت! دسترسی به میکروفون داده شد.";
            stream.getTracks().forEach(t => t.stop());
        } catch (e) {
            resDiv.innerHTML = "❌ خطا: " + e.name + "<br>" + e.message;
        }
    }
    </script>
</body>
</html>
