<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../admin/auth_check.php';

if (!isLoggedIn()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

// تشخیص داینامیک آدرس فعلی (با پروتکل و هاست)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ضبط صدا</title>
<style>
*{box-sizing:border-box}
body{font-family:Tahoma,sans-serif;background:#1e293b;color:#fff;margin:0;padding:20px;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;overflow:hidden}
h2{margin:0 0 20px;font-size:18px}
.mic-btn{width:120px;height:120px;border-radius:50%;border:none;background:linear-gradient(135deg,#10b981,#059669);color:#fff;font-size:48px;cursor:pointer;transition:.3s;box-shadow:0 8px 30px rgba(16,185,129,.5)}
.mic-btn.recording{background:linear-gradient(135deg,#ef4444,#dc2626);box-shadow:0 8px 30px rgba(239,68,68,.6);animation:pulse 1.2s infinite}
.mic-btn:disabled{opacity:.5;cursor:not-allowed}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
.timer{font-size:24px;font-weight:bold;margin-top:20px;color:#06b6d4;font-family:monospace}
.status{margin-top:15px;font-size:14px;color:#94a3b8;text-align:center;max-width:90%}
.btn-send{margin-top:25px;padding:15px 40px;font-size:16px;border:none;border-radius:12px;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;cursor:pointer;font-weight:bold;display:none}
.btn-send.show{display:block}
.btn-cancel{margin-top:10px;padding:10px 30px;font-size:14px;border:1px solid #475569;border-radius:8px;background:transparent;color:#94a3b8;cursor:pointer;display:none}
.btn-cancel.show{display:block}
.error{background:#dc2626;color:#fff;padding:15px;border-radius:8px;margin-top:20px;font-size:13px;line-height:1.8;max-width:90%;word-break:break-all}
.success{background:#10b981;color:#fff;padding:15px;border-radius:8px;margin-top:20px;font-size:13px;line-height:1.8;max-width:90%}
</style>
</head>
<body>
    <h2>🎤 ضبط صدا</h2>
    <button class="mic-btn" id="micBtn" onclick="toggleRec()" disabled>🎤</button>
    <div class="timer" id="timer">00:00</div>
    <div class="status" id="status">⏳ در حال درخواست دسترسی به میکروفون...</div>
    <button class="btn-send" id="sendBtn" onclick="sendToParent()">✅ ارسال به دستیار</button>
    <button class="btn-cancel" id="cancelBtn" onclick="cancelRec()">❌ انصراف</button>
    <div id="errorBox"></div>

<script>
const BASE_URL = "<?= $baseUrl ?>";
console.log("Recorder BASE_URL:", BASE_URL);

let mediaRecorder = null;
let audioChunks = [];
let isRecording = false;
let timerInterval = null;
let startTime = 0;
let mimeType = "audio/webm";
let recordedBlob = null;
let micStream = null;

const micBtn = document.getElementById('micBtn');
const timerEl = document.getElementById('timer');
const statusEl = document.getElementById('status');
const sendBtn = document.getElementById('sendBtn');
const cancelBtn = document.getElementById('cancelBtn');
const errorBox = document.getElementById('errorBox');

function requestMicrophonePermission() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showError("❌ مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.");
        return;
    }
    
    navigator.mediaDevices.getUserMedia({ audio: true })
        .then(stream => {
            micStream = stream;
            statusEl.textContent = "✅ دسترسی داده شد. برای شروع ضبط دکمه را بزنید.";
            statusEl.style.color = "#10b981";
            micBtn.disabled = false;
            errorBox.innerHTML = '';
        })
        .catch(e => {
            micBtn.disabled = true;
            let msg = "❌ خطا در دسترسی به میکروفون:\n\n";
            msg += "نام خطا: " + e.name + "\nپیام: " + e.message;
            if (e.name === "NotAllowedError") {
                msg += "\n\nلطفاً در تنظیمات مرورگر Allow کنید.";
            }
            showError(msg);
        });
}

function toggleRec() {
    if (isRecording) stopRec();
    else startRec();
}

function startRec() {
    if (!micStream) {
        showError("❌ دسترسی به میکروفون هنوز داده نشده است.");
        return;
    }
    
    if (MediaRecorder.isTypeSupported("audio/webm")) mimeType = "audio/webm";
    else if (MediaRecorder.isTypeSupported("audio/mp4")) mimeType = "audio/mp4";
    else if (MediaRecorder.isTypeSupported("audio/ogg")) mimeType = "audio/ogg";
    else mimeType = "";
    
    mediaRecorder = mimeType 
        ? new MediaRecorder(micStream, { mimeType: mimeType }) 
        : new MediaRecorder(micStream);
    
    audioChunks = [];
    mediaRecorder.ondataavailable = e => { if (e.data.size > 0) audioChunks.push(e.data); };
    mediaRecorder.onstop = () => {
        recordedBlob = new Blob(audioChunks, { type: mimeType || "audio/webm" });
        statusEl.textContent = "✅ ضبط به پایان رسید. حالا ارسال کنید.";
        statusEl.style.color = "#10b981";
        sendBtn.classList.add('show');
        cancelBtn.classList.add('show');
    };
    
    mediaRecorder.start();
    isRecording = true;
    startTime = Date.now();
    micBtn.classList.add('recording');
    micBtn.textContent = '⏹';
    statusEl.textContent = "🔴 در حال ضبط...";
    statusEl.style.color = "#ef4444";
    timerInterval = setInterval(updateTimer, 200);
}

function stopRec() {
    if (mediaRecorder && mediaRecorder.state !== "inactive") mediaRecorder.stop();
    isRecording = false;
    micBtn.classList.remove('recording');
    micBtn.textContent = '🎤';
    clearInterval(timerInterval);
}

function updateTimer() {
    const sec = Math.floor((Date.now() - startTime) / 1000);
    const m = String(Math.floor(sec / 60)).padStart(2, '0');
    const s = String(sec % 60).padStart(2, '0');
    timerEl.textContent = m + ":" + s;
}

function cancelRec() {
    recordedBlob = null;
    audioChunks = [];
    timerEl.textContent = "00:00";
    statusEl.textContent = "برای شروع ضبط دکمه را بزنید";
    statusEl.style.color = "#94a3b8";
    sendBtn.classList.remove('show');
    cancelBtn.classList.remove('show');
    errorBox.innerHTML = '';
}

function sendToParent() {
    if (!recordedBlob) return;
    statusEl.textContent = "⏳ در حال ارسال و تبدیل صدا به متن...";
    statusEl.style.color = "#06b6d4";
    sendBtn.disabled = true;
    
    const fd = new FormData();
    let ext = "webm";
    if (mimeType.includes("mp4")) ext = "mp4";
    else if (mimeType.includes("ogg")) ext = "ogg";
    fd.append("audio", recordedBlob, `recording.${ext}`);
    fd.append("language", "auto");
    
    const transcribeUrl = BASE_URL + "/ajax/ai_transcribe.php";
    console.log("Sending to:", transcribeUrl);
    
    fetch(transcribeUrl, { method: "POST", body: fd })
        .then(r => {
            console.log("Response status:", r.status);
            return r.json();
        })
        .then(data => {
            sendBtn.disabled = false;
            if (!data.ok) {
                showError("❌ خطا در تبدیل صدا: " + (data.error || "نامشخص"));
                return;
            }
            const message = { type: "transcribed", text: data.text };
            if (window.parent !== window) {
                window.parent.postMessage(message, "*");
            }
            if (window.opener) {
                window.opener.postMessage(message, "*");
            }
            statusEl.textContent = "✅ ارسال شد!";
            statusEl.style.color = "#10b981";
            if (micStream) micStream.getTracks().forEach(t => t.stop());
            setTimeout(() => window.close(), 1000);
        })
        .catch(e => {
            sendBtn.disabled = false;
            showError("❌ خطای شبکه: " + e.message + "\n\nURL: " + transcribeUrl);
        });
}

function showError(msg) {
    errorBox.innerHTML = '<div class="error">' + msg.replace(/\n/g, '<br>') + '</div>';
    console.error(msg);
}

requestMicrophonePermission();
</script>
</body>
</html>
