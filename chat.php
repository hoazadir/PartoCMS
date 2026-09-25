<?php
/**
 * PartoCMS - AI Chat                                              * صفحه چت تعاملی با دستیار هوشمند
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../admin/auth_check.php';
require_once __DIR__ . '/../../includes/AiAssistant.php';

if (!isLoggedIn()) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

$pdo = getDB();
$ai = new AiAssistant();

// بررسی دسترسی
$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? 'user';
$isAdmin = $userRole === 'admin';

if (!$ai->isEnabled()) {
    if (!$isAdmin) {
        header('Location: ' . ADMIN_URL . '/index.php');
        exit;
    }
}

$canUseAI = $isAdmin;
try {
    $mm = function_exists('getModuleManager') ? getModuleManager() : null;
    if ($mm && !$isAdmin) {
        $canUseAI = $mm->canCurrentUserAccess('ai_assistant');
    }
} catch (Throwable $e) {}

if (!$canUseAI) {
    die('<div style="padding:50px;text-align:center;font-family:Tahoma"><h2>⛔ دسترسی غیرمجاز</h2><p>شما به دستیار هوشمند دسترسی ندارید.</p><a href="' . ADMIN_URL . '/index.php">بازگشت به داشبورد</a></div>');
}

$conversations = $ai->listConversations($userId);
$sidebarFile = __DIR__ . '/../../admin/includes/sidebar.php';
$siteName = function_exists('getSetting') ? getSetting('site_name', 'وب‌سایت من') : 'وب‌سایت من';
$currentConvId = (int)($_GET['conv'] ?? 0);
$messages = $currentConvId ? $ai->getMessages($currentConvId, $userId) : [];

$baseUrl = defined('SITE_URL') ? SITE_URL : '';
$directUrl = SITE_URL . '/modules/ai_assistant/chat.php';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?> translate="no">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="google" content="notranslate">
<title>دستیار هوشمند | PartoCMS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0;height:100vh;overflow:hidden}
.main{margin-right:260px;height:100vh;display:flex;flex-direction:column}
@media(max-width:900px){.main{margin-right:0}}
.chat-header{background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;padding:15px 25px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 8px rgba(0,0,0,.1)}
.chat-header h1{margin:0;font-size:18px;display:flex;align-items:center;gap:10px}
.chat-header .model-badge{background:rgba(255,255,255,.2);padding:4px 12px;border-radius:12px;font-size:11px;font-family:monospace}
.chat-body{display:flex;flex:1;overflow:hidden}
.conv-sidebar{width:280px;background:#fff;border-left:1px solid #e2e8f0;display:flex;flex-direction:column}
.conv-sidebar .sidebar-top{padding:15px;border-bottom:1px solid #e2e8f0}
.conv-sidebar .sidebar-top button{width:100%;padding:10px;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;border:none;border-radius:8px;font-weight:bold;cursor:pointer;font-size:13px;display:flex;align-items:center;justify-content:center;gap:6px}
.conv-list{flex:1;overflow-y:auto;padding:8px}
.conv-item{padding:12px;border-radius:8px;margin-bottom:4px;cursor:pointer;transition:.15s;display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:13px;color:#475569}
.conv-item:hover{background:#f1f5f9}
.conv-item.active{background:linear-gradient(135deg,#ecfeff,#cffafe);color:#0891b2;font-weight:bold;border-right:3px solid #06b6d4}
.conv-item .title{flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.conv-item .del-btn{opacity:0;color:#dc2626;cursor:pointer;padding:2px;font-size:11px;transition:opacity .15s}
.conv-item:hover .del-btn{opacity:1}
.chat-area{flex:1;display:flex;flex-direction:column;background:#f8fafc}
.messages{flex:1;overflow-y:auto;padding:25px;display:flex;flex-direction:column;gap:16px}
.message{display:flex;gap:12px;max-width:85%;animation:fadeIn .3s}
@keyframes fadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.message.user{margin-right:auto;flex-direction:row-reverse}
.message.assistant{margin-left:auto}
.msg-avatar{width:38px;height:38px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.message.user .msg-avatar{background:linear-gradient(135deg,#6366f1,#4f46e5)}
.message.assistant .msg-avatar{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.msg-content{background:#fff;padding:12px 16px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.05);font-size:13.5px;line-height:1.8;color:#334155;white-space:pre-wrap;word-wrap:break-word}
.message.user .msg-content{background:linear-gradient(135deg,#6366f1,#4f46e5);color:#fff}
.msg-time{font-size:10px;color:#94a3b8;margin-top:5px}
.message.user .msg-time{text-align:left;color:rgba(255,255,255,.7)}
.input-area{padding:16px 25px;background:#fff;border-top:1px solid #e2e8f0}
.input-wrapper{display:flex;gap:10px;align-items:flex-end;background:#f1f5f9;border-radius:12px;padding:8px;border:2px solid #e2e8f0}
.input-wrapper:focus-within{border-color:#06b6d4;background:#fff}
.input-wrapper textarea{flex:1;border:none;background:transparent;outline:none;resize:none;font-family:inherit;font-size:14px;padding:8px 10px;max-height:150px;min-height:24px;line-height:1.6}
.send-btn{width:42px;height:42px;border-radius:10px;border:none;background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;cursor:pointer;font-size:18px;display:flex;align-items:center;justify-content:center;transition:.15s;flex-shrink:0}
.send-btn:hover{transform:scale(1.05)}
.send-btn:disabled{opacity:.5;cursor:not-allowed;transform:none}
.empty-chat{text-align:center;padding:50px 20px;color:#94a3b8}
.empty-chat .icon{font-size:70px;margin-bottom:15px}
.empty-chat h3{color:#475569;margin:0 0 10px}
.suggestion{padding:8px 15px;background:#fff;border:1px solid #e2e8f0;border-radius:20px;cursor:pointer;font-size:12px;color:#475569;margin:5px 3px;display:inline-block;transition:.15s}
.suggestion:hover{border-color:#06b6d4;background:#ecfeff;color:#0891b2}
.voice-btn{width:42px;height:42px;border-radius:10px;border:2px solid #e2e8f0;background:#fff;color:#475569;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:.15s;flex-shrink:0}
.voice-btn:hover{border-color:#06b6d4;color:#0891b2;background:#ecfeff}
.voice-btn.recording{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;border-color:#dc2626;animation:pulse-red 1.2s infinite}
@keyframes pulse-red{0%,100%{box-shadow:0 0 0 0 rgba(220,38,38,.5)}50%{box-shadow:0 0 0 10px rgba(220,38,38,0)}}
.voice-status{position:fixed;bottom:120px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:12px 24px;border-radius:12px;font-size:13px;display:none;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.3);animation:fadeIn .3s}
.voice-status .dot{display:inline-block;width:10px;height:10px;background:#ef4444;border-radius:50%;margin-left:8px;animation:pulse-red 1.2s infinite}
.voice-lang-select{padding:5px 10px;border:1px solid #e2e8f0;border-radius:8px;font-size:11px;background:#fff;font-family:inherit;color:#475569;cursor:pointer}
.typing{display:flex;gap:4px;padding:8px 0}
.typing span{width:8px;height:8px;background:#06b6d4;border-radius:50%;animation:bounce 1.4s infinite}
.typing span:nth-child(2){animation-delay:.2s}
.typing span:nth-child(3){animation-delay:.4s}
@keyframes bounce{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-8px)}}
@media(max-width:768px){
    body{overflow:auto}
    .main{margin-right:0!important;height:auto;min-height:100vh}
    .conv-sidebar{position:fixed;right:0;top:0;bottom:0;width:85%;max-width:320px;z-index:1050;transform:translateX(100%);transition:transform .3s;box-shadow:-5px 0 25px rgba(0,0,0,.2)}
    .conv-sidebar.open{transform:translateX(0)}
    .sidebar-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1040;opacity:0;visibility:hidden;transition:.3s}
    .sidebar-backdrop.open{opacity:1;visibility:visible}
    .chat-header{padding:12px 15px;flex-wrap:nowrap}
    .chat-header h1{font-size:14px}
    .chat-header .model-badge{font-size:10px;padding:3px 8px}
    .chat-header a{padding:6px 10px!important;font-size:11px!important}
    .mobile-toggle-btn{display:flex!important}
    .messages{padding:12px;gap:12px}
    .message{max-width:92%}
    .msg-avatar{width:32px;height:32px;font-size:16px}
    .msg-content{padding:10px 13px;font-size:13px}
    .input-area{padding:10px 12px}
    .input-wrapper{padding:6px;gap:6px}
    .voice-btn,.send-btn{width:38px;height:38px;font-size:15px}
    .input-wrapper textarea{font-size:14px;padding:6px 8px}
    .voice-status{bottom:80px;font-size:12px;padding:10px 18px}
}
</style>
</head>
<body>

<script>
    // ⚠️ اگر صفحه در iframe باشد، کاربر را مجبور به باز کردن در تب جدید می‌کنیم
    if (window.self !== window.top) {
        try { window.top.location.href = "<?= $directUrl ?>"; } catch(e) {}
    }
</script>

<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>

<div class="main">
    <div class="chat-header">
        <h1>
            <span>🤖</span>
            <span>دستیار هوشمند</span>
            <span class="model-badge"><?= htmlspecialchars($ai->getModel()) ?></span>
        </h1>
        <div style="display:flex;gap:8px">
            <a href="<?= $directUrl ?>" target="_blank" style="background:#f59e0b; color:#fff; padding:8px 15px; border-radius:8px; text-decoration:none; font-size:12px; display:flex; align-items:center; gap:5px; font-weight:bold;" title="باز کردن در تب جدید">
                <i class="bi bi-box-arrow-up-right"></i> تب جدید
            </a>
            <button onclick="toggleSidebar()" class="mobile-toggle-btn" style="background:rgba(255,255,255,.2);border:none;color:#fff;padding:8px 12px;border-radius:8px;cursor:pointer;font-size:16px">
                <i class="bi bi-list"></i>
            </button>
            <a href="<?= ADMIN_URL ?>/ai_settings.php" style="background:rgba(255,255,255,.2);color:#fff;padding:8px 15px;border-radius:8px;text-decoration:none;font-size:12px;display:flex;align-items:center;gap:5px">
                <i class="bi bi-gear"></i> تنظیمات
            </a>
        </div>
    </div>

    <div class="chat-body">
        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>
        <div class="conv-sidebar" id="convSidebar">
            <div class="sidebar-top">
                <button onclick="newConversation()">
                    <i class="bi bi-plus-lg"></i> مکالمه جدید
                </button>
            </div>
            <div class="conv-list" id="convList">
                <?php if (empty($conversations)): ?>
                    <div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px">هنوز مکالمه‌ای ندارید</div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conv-item <?= $conv['id'] == $currentConvId ? 'active' : '' ?>" data-id="<?= $conv['id'] ?>" onclick="loadConversation(<?= $conv['id'] ?>)">
                            <span class="title"><?= htmlspecialchars($conv['title']) ?></span>
                            <span class="del-btn" onclick="event.stopPropagation(); deleteConv(<?= $conv['id'] ?>)">✖</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="chat-area">
            <div class="messages" id="messages">
                <?php if (empty($messages)): ?>
                    <div class="empty-chat">
                        <div class="icon">🤖</div>
                        <h3>سلام! من دستیار هوشمند شما هستم</h3>
                        <p>می‌توانید از من سوال بپرسید یا فرمان بدهید</p>
                        <div style="margin-top:20px">
                            <span class="suggestion" onclick="useSuggestion(this)">چند کاربر داریم؟</span>
                            <span class="suggestion" onclick="useSuggestion(this)">جدول‌های دیتابیس را لیست کن</span>
                            <span class="suggestion" onclick="useSuggestion(this)">یک جدول blog بساز</span>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $m): ?>
                        <div class="message <?= $m['role'] ?>">
                            <div class="msg-avatar"><?= $m['role'] === 'user' ? '👤' : '🤖' ?></div>
                            <div>
                                <div class="msg-content"><?= htmlspecialchars($m['content']) ?></div>
                                <div class="msg-time"><?= date('H:i', strtotime($m['created_at'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="input-area">
                <div class="input-wrapper">
                    <button class="voice-btn" id="micBtn" onclick="toggleRecording()" title="ضبط صدا">
                        <i class="bi bi-mic-fill"></i>
                    </button>
                    <textarea id="userInput" placeholder="پیام خود را بنویسید یا 🎤 بزنید..." rows="1" onkeydown="handleKey(event)" oninput="autoResize(this)"></textarea>
                    <button class="voice-btn" id="ttsBtn" onclick="toggleTTS()" title="خواندن پاسخ">
                        <i class="bi bi-volume-up-fill"></i>
                    </button>
                    <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;font-size:10.5px;color:#94a3b8">
                    <select class="voice-lang-select" id="voiceLang" onchange="updateVoiceLang()">
                        <option value="fa-IR">🇮🇷 فارسی</option>
                        <option value="en-US">🇺🇸 English</option>
                        <option value="ar-SA">🇸🇦 العربية</option>
                    </select>
                    <span>⏱ پاسخ: ۶۰-۹۰ ثانیه • مدل: <?= htmlspecialchars($ai->getModel()) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="voice-status" id="voiceStatus">
    <span id="voiceStatusText">🎤 در حال ضبط...</span>
    <span class="dot"></span>
</div>

<script>
// ═══════════════════════════════════════════════════════════
// متغیرهای سراسری
// ═══════════════════════════════════════════════════════════
const BASE_URL = "<?= $baseUrl ?>";
let currentConvId = <?= $currentConvId ?>;
let isSending = false;
let ttsEnabled = false;
let voiceLang = "fa-IR";
let isRecording = false;

// ==================== ارسال پیام ====================
async function sendMessage() {
    if (isSending) return;
    const input = document.getElementById("userInput");
    const text = input.value.trim();
    if (!text) return;

    isSending = true;
    input.value = "";
    autoResize(input);
    document.getElementById("sendBtn").disabled = true;

    const emptyChat = document.querySelector(".empty-chat");
    if (emptyChat) emptyChat.remove();

    appendMessage("user", text);
    const typingEl = appendTyping();

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 300000);

        const res = await fetch(`${BASE_URL}/ajax/ai_chat.php`, {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ message: text, conversation_id: currentConvId }),
            signal: controller.signal,
        });

        clearTimeout(timeoutId);
        const data = await res.json();
        typingEl.remove();

        if (!data.ok) {
            appendMessage("assistant", "❌ خطا: " + (data.error || "نامشخص"));
        } else {
            appendMessage("assistant", data.response);
            if (ttsEnabled) speakText(data.response);
            if (data.conversation_id && !currentConvId) {
                currentConvId = data.conversation_id;
                refreshConversations();
            }
        }
    } catch (err) {
        typingEl.remove();
        let msg = "❌ خطای شبکه";
        if (err.name === "AbortError") msg = "❌ زمان پاسخ به پایان رسید (بیش از ۵ دقیقه).";
        else if (err.message) msg = "❌ " + err.message;
        appendMessage("assistant", msg);
    } finally {
        isSending = false;
        document.getElementById("sendBtn").disabled = false;
        input.focus();
    }
}

// ==================== افزودن پیام ====================
function appendMessage(role, content) {
    const messages = document.getElementById("messages");
    const div = document.createElement("div");
    div.className = "message " + role;
    div.innerHTML = `
        <div class="msg-avatar">${role === "user" ? "👤" : "🤖"}</div>
        <div>
            <div class="msg-content">${escapeHtml(content)}</div>
            <div class="msg-time">${new Date().toLocaleTimeString("fa-IR", {hour:"2-digit",minute:"2-digit"})}</div>
        </div>
    `;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
}

function appendTyping() {
    const messages = document.getElementById("messages");
    const div = document.createElement("div");
    div.className = "message assistant";
    div.innerHTML = `<div class="msg-avatar">🤖</div><div class="msg-content"><div class="typing"><span></span><span></span><span></span></div></div>`;
    messages.appendChild(div);
    messages.scrollTop = messages.scrollHeight;
    return div;
}

// ==================== ابزارها ====================
function handleKey(e) { if (e.key === "Enter" && !e.shiftKey) { e.preventDefault(); sendMessage(); } }
function autoResize(el) { el.style.height = "auto"; el.style.height = Math.min(el.scrollHeight, 150) + "px"; }
function escapeHtml(text) { const div = document.createElement("div"); div.textContent = text; return div.innerHTML; }
function useSuggestion(el) { document.getElementById("userInput").value = el.textContent; document.getElementById("userInput").focus(); }

// ==================== مکالمات ====================
function newConversation() {
    currentConvId = 0;
    document.getElementById("messages").innerHTML = `<div class="empty-chat"><div class="icon">🤖</div><h3>مکالمه جدید</h3><p>سوال یا فرمان خود را بنویسید</p></div>`;
    document.querySelectorAll(".conv-item").forEach(i => i.classList.remove("active"));
}
function loadConversation(id) { if (window.innerWidth <= 768) toggleSidebar(); window.location.href = "?conv=" + id; }
async function deleteConv(id) { if (!confirm("این مکالمه حذف شود؟")) return; try { const res = await fetch(`${BASE_URL}/ajax/ai_chat.php?delete=` + id, {method: "DELETE"}); const data = await res.json(); if (data.ok) { if (id == currentConvId) window.location.href = "?"; else refreshConversations(); } } catch (err) { alert("خطا در حذف"); } }
async function refreshConversations() { try { const res = await fetch(`${BASE_URL}/ajax/ai_chat.php?list=1`); const data = await res.json(); if (data.ok) { const list = document.getElementById("convList"); if (data.conversations.length === 0) list.innerHTML = '<div style="padding:20px;text-align:center;color:#94a3b8;font-size:12px">هنوز مکالمه‌ای ندارید</div>'; else list.innerHTML = data.conversations.map(c => `<div class="conv-item ${c.id == currentConvId ? 'active' : ''}" onclick="loadConversation(${c.id})"><span class="title">${escapeHtml(c.title)}</span><span class="del-btn" onclick="event.stopPropagation(); deleteConv(${c.id})">✖</span></div>`).join(""); } } catch (err) {} }
function toggleSidebar() { const s = document.getElementById("convSidebar"); const b = document.getElementById("sidebarBackdrop"); s.classList.toggle("open"); b.classList.toggle("open"); }

document.addEventListener("DOMContentLoaded", () => {
    document.getElementById("userInput")?.focus();
    document.getElementById("messages").scrollTop = 999999;
});

// ═══════════════════════════════════════════════════════════
// ضبط صدا (MediaRecorder API حرفه‌ای)
// ═══════════════════════════════════════════════════════════
let mediaRecorder = null;
let audioChunks = [];
let recordingStartTime = 0;
let recordingTimer = null;
let currentMimeType = "audio/webm";

function toggleRecording() {
    if (isRecording) {
        stopRecording();
    } else {
        // درخواست مستقیم و بدون واسطه برای جلوگیری از خطای موبایل
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            alert("❌ مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.");
            return;
        }

        // ⚠️ نکته کلیدی: درخواست getUserMedia باید مستقیماً در کلیک کاربر باشد
        navigator.mediaDevices.getUserMedia({ audio: true })
            .then(stream => {
                let mimeType = "";
                if (typeof MediaRecorder !== "undefined") {
                    if (MediaRecorder.isTypeSupported("audio/webm")) mimeType = "audio/webm";
                    else if (MediaRecorder.isTypeSupported("audio/mp4")) mimeType = "audio/mp4";
                    else if (MediaRecorder.isTypeSupported("audio/ogg")) mimeType = "audio/ogg";
                }
                currentMimeType = mimeType || "audio/webm";
                mediaRecorder = mimeType ? new MediaRecorder(stream, { mimeType: mimeType }) : new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) audioChunks.push(e.data); };
                mediaRecorder.onstop = async () => {
                    stream.getTracks().forEach(t => t.stop());
                    const blob = new Blob(audioChunks, { type: currentMimeType });
                    const input = document.getElementById("userInput");
                    input.value = "⏳ در حال تبدیل صدا به متن...";
                    input.disabled = true;
                    await uploadAudio(blob, currentMimeType);
                };

                mediaRecorder.start();
                isRecording = true;
                recordingStartTime = Date.now();
                document.getElementById("micBtn").classList.add("recording");
                showVoiceStatus("🎤 در حال ضبط... کلیک کنید تا متوقف شود", true);
                recordingTimer = setInterval(updateRecordingTimer, 500);
            })
            .catch(e => {
                // نمایش خطا مستقیماً در چت به جای alert
                let errorMsg = "❌ خطا در دسترسی به میکروفون:\n";
                errorMsg += `نام خطا: ${e.name}\nپیام: ${e.message}\n\n`;
                if (e.name === "NotAllowedError") {
                    errorMsg += "راه‌حل:\n۱. در تنظیمات مرورگر، برای این سایت Microphone را روی Allow بگذارید.\n۲. مطمئن شوید مترجم گوگل خاموش است.\n۳. حافظه مرورگر را پاک کنید (Clear Browsing Data).";
                } else {
                    errorMsg += "لطفاً مرورگر را تغییر دهید یا دستگاه را ری‌استارت کنید.";
                }
                
                // نمایش خطا در چت
                const input = document.getElementById("userInput");
                input.value = errorMsg;
                input.style.color = "#dc2626";
                input.focus();
                console.error("Microphone Error Details:", e);
            });
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state !== "inactive") mediaRecorder.stop();
    isRecording = false;
    document.getElementById("micBtn")?.classList.remove("recording");
    if (recordingTimer) { clearInterval(recordingTimer); recordingTimer = null; }
    hideVoiceStatus();
}

function updateRecordingTimer() {
    const sec = Math.floor((Date.now() - recordingStartTime) / 1000);
    const min = Math.floor(sec / 60);
    const s = sec % 60;
    const timeStr = (min > 0 ? min + ":" : "") + String(s).padStart(2, "0");
    showVoiceStatus("🎤 در حال ضبط... " + timeStr + " (برای توقف کلیک کنید)", true);
}

async function uploadAudio(blob, mimeType) {
    const input = document.getElementById("userInput");
    const fd = new FormData();
    let extension = "webm";
    if (mimeType.includes("mp4")) extension = "mp4";
    else if (mimeType.includes("ogg")) extension = "ogg";
    fd.append("audio", blob, `recording.${extension}`);
    fd.append("language", "auto");

    try {
        const r = await fetch(`${BASE_URL}/ajax/ai_transcribe.php`, { method: "POST", body: fd });
        const data = await r.json();
        input.disabled = false;
        input.style.color = ""; // برگرداندن رنگ به حالت عادی
        if (!data.ok) { input.value = ""; alert("خطا در تبدیل صدا: " + (data.error || "نامشخص")); return; }
        input.value = data.text;
        input.focus();
        showVoiceStatus("✅ تبدیل شد: " + data.text.substring(0, 40), false);
        setTimeout(hideVoiceStatus, 2000);
    } catch (e) {
        input.disabled = false; input.value = ""; alert("خطای شبکه: " + e.message);
    }
}

function showVoiceStatus(text, isRecording) {
    let el = document.getElementById("voiceStatus");
    if (!el) {
        el = document.createElement("div");
        el.id = "voiceStatus";
        el.style.cssText = "position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:12px 24px;border-radius:12px;font-size:13px;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.3);max-width:80%;text-align:center;direction:rtl";
        document.body.appendChild(el);
    }
    el.textContent = text; el.style.display = "block";
    el.style.background = isRecording ? "linear-gradient(135deg,#ef4444,#dc2626)" : "linear-gradient(135deg,#10b981,#059669)";
}
function hideVoiceStatus() { const el = document.getElementById("voiceStatus"); if (el) el.style.display = "none"; }

// ═══════════════════════════════════════════════════════════
// خواندن پاسخ (TTS)
// ═══════════════════════════════════════════════════════════
function toggleTTS() { if (!window.speechSynthesis) { alert("مرورگر شما از خواندن صوتی پشتیبانی نمی‌کند"); return; } ttsEnabled = !ttsEnabled; const btn = document.getElementById("ttsBtn"); if (ttsEnabled) { btn.classList.add("active"); speakText("خواندن پاسخ فعال شد"); } else { btn.classList.remove("active"); window.speechSynthesis.cancel(); } }
function updateVoiceLang() { const select = document.getElementById("voiceLang"); if (select) { voiceLang = select.value; if (ttsEnabled) speakText("زبان تغییر کرد"); } }
function speakText(text) { if (!ttsEnabled || !window.speechSynthesis) return; const clean = text.replace(/[🤖✨✅❌⚠️📌🔍📍💬🎤🔊📥⚡💾🔢⚙️📝 📄📚📋🔧✉️🔗📞📱🖼️📎🎨🔤🔒👤🌐💻📅⏰📆⏱️]/g, "").trim(); if (!clean) return; window.speechSynthesis.cancel(); const utter = new SpeechSynthesisUtterance(clean); utter.lang = voiceLang; utter.rate = 1.0; utter.pitch = 1.0; const voices = window.speechSynthesis.getVoices(); const targetVoice = voices.find(v => v.lang.startsWith(voiceLang.split('-')[0])); if (targetVoice) utter.voice = targetVoice; window.speechSynthesis.speak(utter); }
</script>
</body>
</html>
