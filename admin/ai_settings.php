<?php
/**
 * PartoCMS - AI Settings
 * 
 * @version 4.0
 * @date 2026-09-21
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../includes/AI/AIProviderFactory.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDB();
$siteName = getSetting('site_name', 'وب‌سایت من');
$message = '';
$messageType = '';

// ═══════════════════════════════════════════════════════════
//  پردازش POST
// ═══════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'save') {
        try {
            $settings = [
                'ai_enabled'             => isset($_POST['ai_enabled']) ? '1' : '0',
                'ai_user_model_choice'   => isset($_POST['ai_user_model_choice']) ? '1' : '0',
                'ai_llm_provider'        => trim($_POST['ai_llm_provider'] ?? 'ollama'),
                'ai_endpoint'            => trim($_POST['ai_endpoint'] ?? 'http://localhost:11434'),
                'ai_timeout'             => max(5, (int)($_POST['ai_timeout'] ?? 300)),
                'ai_model'               => trim($_POST['ai_model'] ?? 'qwen2.5:1.5b'),
                'ai_stt_provider'        => trim($_POST['ai_stt_provider'] ?? 'whisper_cpp'),
                'ai_stt_model'           => trim($_POST['ai_stt_model'] ?? 'base'),
                'ai_stt_language'        => trim($_POST['ai_stt_language'] ?? 'auto'),
                'ai_whisper_bin'         => trim($_POST['ai_whisper_bin'] ?? ''),
                'ai_whisper_models_dir'  => trim($_POST['ai_whisper_models_dir'] ?? ''),
            ];

            foreach ($settings as $key => $val) {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$key, $val]);
            }

            $message = '✅ تنظیمات با موفقیت ذخیره شد';
            $messageType = 'success';
        } catch (Throwable $e) {
            $message = '❌ خطا: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// ═══════════════════════════════════════════════════════════
//  خواندن تنظیمات
// ═══════════════════════════════════════════════════════════
$settings = [
    'ai_enabled'             => getSetting('ai_enabled', '0'),
    'ai_user_model_choice'   => getSetting('ai_user_model_choice', '0'),
    'ai_llm_provider'        => getSetting('ai_llm_provider', 'ollama'),
    'ai_endpoint'            => getSetting('ai_endpoint', 'http://localhost:11434'),
    'ai_timeout'             => getSetting('ai_timeout', '300'),
    'ai_model'               => getSetting('ai_model', 'qwen2.5:1.5b'),
    'ai_stt_provider'        => getSetting('ai_stt_provider', 'whisper_cpp'),
    'ai_stt_model'           => getSetting('ai_stt_model', 'base'),
    'ai_stt_language'        => getSetting('ai_stt_language', 'auto'),
    'ai_whisper_bin'         => getSetting('ai_whisper_bin', '/data/data/com.termux/files/home/whisper.cpp/build/bin/whisper-cli'),
    'ai_whisper_models_dir'  => getSetting('ai_whisper_models_dir', '/data/data/com.termux/files/home/whisper.cpp/models'),
];

// ═══════════════════════════════════════════════════════════
//  وضعیت اتصال LLM
// ═══════════════════════════════════════════════════════════
$connStatus = ['ok' => false, 'message' => 'در حال بررسی...', 'models_count' => 0];
$llmModelList = [];

$llm = AIProviderFactory::makeLLM($settings['ai_llm_provider']);
if ($llm) {
    $test = $llm->testConnection();
    $connStatus['ok'] = $test['ok'] ?? false;
    $connStatus['message'] = $test['message'] ?? ($test['error'] ?? 'خطا');

    $models = $llm->listModels();
    if (!empty($models['ok'])) {
        $connStatus['models_count'] = count($models['models']);
        $llmModelList = $models['models'];
    }
}

// ═══════════════════════════════════════════════════════════
//  وضعیت STT Models
// ═══════════════════════════════════════════════════════════
$sttModelList = [];
$stt = AIProviderFactory::makeSTT($settings['ai_stt_provider']);
if ($stt) {
    $res = $stt->listModels();
    if (!empty($res['ok'])) $sttModelList = $res['models'];
}

$sidebarFile = __DIR__ . '/includes/sidebar.php';
?>
<!DOCTYPE html>
<html <?= __html_attrs() ?>>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تنظیمات دستیار هوشمند</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= getI18n()->isRtl() ? '.rtl' : '' ?>.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
<style>
body{background:#f1f5f9;font-family:Tahoma,sans-serif;margin:0}
.main{margin-right:260px;padding:20px;min-height:100vh}
@media(max-width:900px){.main{margin-right:0!important;padding:70px 15px 15px!important}}
.page-header{background:linear-gradient(135deg,#06b6d4,#0891b2);color:#fff;padding:25px 30px;border-radius:15px;margin-bottom:20px}
.page-header h1{margin:0;font-size:22px}
.page-header p{margin:5px 0 0;opacity:.85;font-size:13px}

.status-banner{padding:15px 22px;border-radius:12px;margin-bottom:20px;display:flex;align-items:center;gap:15px;font-size:13px}
.status-banner.ok{background:linear-gradient(135deg,#d1fae5,#a7f3d0);border-right:4px solid #10b981;color:#065f46}
.status-banner.fail{background:linear-gradient(135deg,#fee2e2,#fecaca);border-right:4px solid #dc2626;color:#991b1b}
.status-banner .icon{font-size:30px}

.card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:20px;overflow:hidden}
.card .header{padding:15px 22px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px;color:#1e293b;display:flex;justify-content:space-between;align-items:center}
.card .body{padding:22px}

.form-group{margin-bottom:18px}
.form-group label{display:block;font-weight:bold;font-size:13px;margin-bottom:6px;color:#374151}
.form-group .hint{font-size:11px;color:#94a3b8;margin-top:4px}
.form-group input[type=text],.form-group input[type=number],.form-group select{width:100%;padding:10px 14px;border:2px solid #e2e8f0;border-radius:8px;font-family:inherit;font-size:13px;box-sizing:border-box}
.form-group input:focus,.form-group select:focus{outline:none;border-color:#06b6d4}

.switch-row{display:flex;align-items:center;gap:12px;padding:14px;background:#f8fafc;border-radius:10px;margin-bottom:12px}
.switch{position:relative;width:50px;height:26px;flex-shrink:0}
.switch input{display:none}
.switch label{position:absolute;inset:0;background:#cbd5e1;border-radius:26px;cursor:pointer;transition:.3s}
.switch label::after{content:'';position:absolute;top:3px;left:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:.3s}
.switch input:checked + label{background:#10b981}
.switch input:checked + label::after{left:27px}

.btn-a{padding:9px 18px;border-radius:8px;border:none;cursor:pointer;font-weight:bold;font-size:13px;color:#fff;display:inline-flex;align-items:center;gap:6px;text-decoration:none;transition:.15s}
.btn-a:hover{transform:translateY(-1px);opacity:.9;color:#fff}
.btn-primary-a{background:linear-gradient(135deg,#06b6d4,#0891b2)}
.btn-success-a{background:linear-gradient(135deg,#10b981,#059669)}
.btn-warning-a{background:linear-gradient(135deg,#f59e0b,#d97706)}
.btn-secondary-a{background:linear-gradient(135deg,#64748b,#334155)}
.btn-sm{padding:5px 10px;font-size:11px}

/* Model Grid */
.models-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:12px}
.model-card{border:2px solid #e2e8f0;border-radius:12px;padding:16px;background:#fff;transition:.2s;cursor:pointer;position:relative}
.model-card:hover{border-color:#06b6d4;box-shadow:0 4px 12px rgba(6,182,212,.1)}
.model-card.active{border-color:#10b981;background:linear-gradient(135deg,#f0fdf4,#ecfdf5);box-shadow:0 4px 16px rgba(16,185,129,.15)}
.model-card.active::before{content:'✓ فعال';position:absolute;top:10px;left:10px;background:#10b981;color:#fff;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:bold}
.model-name{font-family:monospace;font-size:14px;font-weight:bold;color:#1e293b;margin-bottom:10px;word-break:break-all}
.model-meta{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.model-badge{padding:3px 9px;border-radius:10px;font-size:10.5px;font-weight:bold}
.badge-size{background:#dbeafe;color:#1e40af}
.badge-param{background:#fef3c7;color:#92400e}
.badge-installed{background:#d1fae5;color:#065f46}
.badge-notinstalled{background:#fee2e2;color:#991b1b}
.model-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}

/* Suggestion Tags */
.suggestion-tag{display:inline-block;padding:5px 12px;background:#e0e7ff;color:#4338ca;border-radius:10px;font-size:11.5px;cursor:pointer;margin:3px;font-family:monospace;transition:.15s;font-weight:bold}
.suggestion-tag:hover{background:#c7d2fe;color:#3730a3;transform:translateY(-1px)}

/* Download Progress - Enhanced */
.progress-box{
    display:none;
    margin-top:14px;
    padding:18px;
    background:linear-gradient(135deg,#f8fafc,#ecfeff);
    border-radius:12px;
    border:2px solid #cbd5e1;
}
.progress-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:14px;
    font-weight:bold;
    font-size:14px;
    color:#1e293b;
}
.progress-bar-wrap{
    position:relative;
    height:28px;
    background:#e2e8f0;
    border-radius:14px;
    overflow:hidden;
    margin-bottom:16px;
    box-shadow:inset 0 1px 3px rgba(0,0,0,.08);
}
.progress-fill{
    height:100%;
    width:0%;
    background:linear-gradient(90deg,#06b6d4,#0891b2,#0e7490);
    background-size:200% 100%;
    animation:progress-shine 2s linear infinite;
    transition:width .4s ease;
    display:flex;
    align-items:center;
    justify-content:flex-end;
    padding-right:10px;
    color:#fff;
    font-size:12px;
    font-weight:bold;
    border-radius:14px;
}
@keyframes progress-shine{
    0%{background-position:0% 0%}
    100%{background-position:200% 0%}
}
.progress-stats{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:10px;
    margin-bottom:12px;
}
.progress-stat{
    background:#fff;
    padding:10px 12px;
    border-radius:8px;
    border:1px solid #e2e8f0;
    text-align:center;
}
.progress-stat .lbl{
    font-size:10px;
    color:#94a3b8;
    margin-bottom:4px;
    font-weight:bold;
}
.progress-stat .val{
    font-size:13px;
    color:#1e293b;
    font-weight:bold;
    font-family:monospace;
}
.progress-meta{
    display:flex;
    justify-content:space-between;
    font-size:11.5px;
    color:#64748b;
    padding:8px 0;
    border-top:1px dashed #cbd5e1;
    margin-top:8px;
}
.progress-meta span{display:inline-flex;align-items:center;gap:4px}
.progress-log{
    margin-top:10px;
    padding:10px;
    background:#1e293b;
    color:#10b981;
    border-radius:8px;
    font-family:monospace;
    font-size:11px;
    max-height:90px;
    overflow-y:auto;
    direction:ltr;
    text-align:left;
    line-height:1.6;
}
.progress-log:empty{display:none}

/* Guides */
.guide-box{background:#f8fafc;border-radius:10px;padding:16px;margin-top:10px;font-size:12.5px;line-height:1.9;color:#475569}
.guide-box strong{color:#1e293b}
.guide-box code{background:#e2e8f0;padding:2px 6px;border-radius:4px;font-family:monospace;font-size:11px;color:#4338ca}
</style>
</head>
<body>
<?php if (file_exists($sidebarFile)) require_once $sidebarFile; ?>
<div class="main">

    <div class="page-header">
        <h1>🤖 تنظیمات دستیار هوشمند</h1>
        <p>پیکربندی Ollama، مدیریت مدل‌ها و انتخاب مدل پیش‌فرض</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
        <?= $message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Banner وضعیت -->
    <div class="status-banner <?= $connStatus['ok'] ? 'ok' : 'fail' ?>">
        <span class="icon"><?= $connStatus['ok'] ? '✅' : '❌' ?></span>
        <div>
            <strong><?= $connStatus['ok'] ? 'اتصال موفق به ' . htmlspecialchars($settings['ai_llm_provider']) : 'خطا در اتصال' ?></strong>
            <div style="font-size:11.5px;opacity:.85;margin-top:2px">
                <?php if ($connStatus['ok']): ?>
                    <?= (int)$connStatus['models_count'] ?> مدل نصب‌شده در <code><?= htmlspecialchars($settings['ai_endpoint']) ?></code>
                <?php else: ?>
                    <?= htmlspecialchars($connStatus['message']) ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="action" value="save">

        <!-- پیکربندی کلی -->
        <div class="card">
            <div class="header">⚙️ پیکربندی کلی</div>
            <div class="body">
                <div class="switch-row">
                    <div class="switch">
                        <input type="checkbox" name="ai_enabled" id="ai_enabled" value="1" <?= $settings['ai_enabled'] === '1' ? 'checked' : '' ?>>
                        <label for="ai_enabled"></label>
                    </div>
                    <div>
                        <div style="font-weight:bold;font-size:13px;color:#1e293b">فعال کردن دستیار هوشمند</div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px">تحلیل خودکار خطاها و پیشنهاد انواع داده</div>
                    </div>
                </div>
                <div class="switch-row">
                    <div class="switch">
                        <input type="checkbox" name="ai_user_model_choice" id="ai_user_model_choice" value="1" <?= $settings['ai_user_model_choice'] === '1' ? 'checked' : '' ?>>
                        <label for="ai_user_model_choice"></label>
                    </div>
                    <div>
                        <div style="font-weight:bold;font-size:13px;color:#1e293b">اجازه به کاربران برای انتخاب مدل</div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px">اگر فعال باشد، هر کاربر می‌تواند مدل خودش را انتخاب کند</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>آدرس <?= htmlspecialchars($settings['ai_llm_provider']) ?> (Endpoint)</label>
                    <input type="text" name="ai_endpoint" value="<?= htmlspecialchars($settings['ai_endpoint']) ?>" dir="ltr">
                    <div class="hint">💡 در Termux/VPS معمولاً <code>http://localhost:11434</code> است</div>
                </div>

                <div class="form-group">
                    <label>Timeout (ثانیه)</label>
                    <input type="number" name="ai_timeout" value="<?= (int)$settings['ai_timeout'] ?>" min="5" max="600">
                    <div class="hint">حداکثر زمان انتظار. برای مدل‌های سنگین حداقل ۱۲۰ ثانیه توصیه می‌شود.</div>
                </div>
            </div>
        </div>

        <!-- LLM -->
        <div class="card">
            <div class="header">🧠 مدل متنی (LLM) — <?= htmlspecialchars($settings['ai_llm_provider']) ?></div>
            <div class="body">
                <input type="hidden" name="ai_llm_provider" value="<?= htmlspecialchars($settings['ai_llm_provider']) ?>">

                <div class="form-group">
                    <label>مدل پیش‌فرض LLM برای CMS</label>
                    <?php if (empty($llmModelList)): ?>
                        <div style="padding:15px;background:#fef3c7;border-radius:8px;color:#92400e;font-size:12px">
                            ⚠️ هیچ مدلی یافت نشد. اتصال را بررسی کنید.
                        </div>
                    <?php else: ?>
                        <div class="models-grid" id="llmModelsGrid">
                            <?php foreach ($llmModelList as $m): ?>
                                <div class="model-card <?= $m['name'] === $settings['ai_model'] ? 'active' : '' ?>" onclick="selectLlmModel(this)">
                                    <input type="radio" name="ai_model" value="<?= htmlspecialchars($m['name']) ?>" <?= $m['name'] === $settings['ai_model'] ? 'checked' : '' ?> style="display:none">
                                    <div class="model-name"><?= htmlspecialchars($m['name']) ?></div>
                                    <div class="model-meta">
                                        <span class="model-badge badge-size">💾 <?= $m['size_human'] ?></span>
                                        <?php if ($m['parameters']): ?>
                                            <span class="model-badge badge-param">🔢 <?= htmlspecialchars($m['parameters']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="model-actions">
                                        <a href="?speed_test=1&model=<?= urlencode($m['name']) ?>" class="btn-a btn-warning-a btn-sm" onclick="event.stopPropagation()">
                                            ⚡ تست سرعت
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- دانلود مدل LLM جدید -->
        <div class="card">
            <div class="header">📥 دانلود مدل جدید (<?= htmlspecialchars($settings['ai_llm_provider']) ?>)</div>
            <div class="body">
                <div style="font-size:12.5px;color:#475569;margin-bottom:12px;line-height:1.8">
                    مدل مورد نظر خود را دانلود کنید. <strong>مدل‌های پیشنهادی:</strong>
                </div>

                <div style="margin-bottom:15px">
                    <span class="suggestion-tag" onclick="setNewLlmModel('qwen2.5:1.5b')">⭐ qwen2.5:1.5b (1GB)</span>
                    <span class="suggestion-tag" onclick="setNewLlmModel('qwen2.5:3b')">qwen2.5:3b (2GB)</span>
                    <span class="suggestion-tag" onclick="setNewLlmModel('llama3.2:1b')">llama3.2:1b (1.3GB)</span>
                    <span class="suggestion-tag" onclick="setNewLlmModel('llama3.2:3b')">llama3.2:3b (2GB)</span>
                    <span class="suggestion-tag" onclick="setNewLlmModel('gemma2:2b')">gemma2:2b (1.6GB)</span>
                    <span class="suggestion-tag" onclick="setNewLlmModel('phi3:mini')">phi3:mini (2.3GB)</span>
                    <span class="suggestion-tag" onclick="setNewLlmModel('llama3.1:latest')">llama3.1 (4.9GB)</span>
                </div>

                <div class="pull-form">
                    <label style="font-weight:bold;font-size:13px;display:block;margin-bottom:8px;color:#1e293b">
                        نام مدل (از ollama.com/library):
                    </label>
                    <input type="text" id="newLlmModel" placeholder="مثال: qwen2.5:3b" dir="ltr" style="font-family:monospace;width:100%;padding:10px;border:2px solid #e2e8f0;border-radius:8px;font-size:13px">

                    <div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
                        <button type="button" id="pullBtn" onclick="startLlmPull()" class="btn-a btn-success-a">
                            <i class="bi bi-download"></i> دانلود مدل
                        </button>
                        <a href="https://ollama.com/library" target="_blank" class="btn-a btn-secondary-a">
                            <i class="bi bi-box-arrow-up-right"></i> مشاهده لیست کامل مدل‌ها
                        </a>
                    </div>

                    <div style="font-size:11px;color:#94a3b8;margin-top:10px">
                        ⚠️ دانلود مدل‌های بزرگ ممکن است چند دقیقه طول بکشد. صفحه را نبندید.
                    </div>
                </div>

                <!-- نوار پیشرفت LLM -->
                <div id="llmProgressBox" style="display:none;margin-top:20px;padding:20px;background:#f8fafc;border:2px dashed #cbd5e1;border-radius:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px">
                        <strong id="llmProgressTitle" style="font-size:14px;color:#1e293b">📥 در حال دانلود...</strong>
                        <button type="button" onclick="cancelLlmPull()" style="background:#fef2f2;color:#dc2626;border:1px solid #fecaca;padding:5px 12px;border-radius:8px;font-size:11px;font-weight:bold;cursor:pointer">
                            ✖ لغو
                        </button>
                    </div>

                    <div style="height:32px;background:#e2e8f0;border-radius:16px;overflow:hidden;position:relative;margin-bottom:12px">
                        <div id="llmProgressFill" style="height:100%;width:0%;background:linear-gradient(90deg,#06b6d4,#0891b2);transition:width 0.3s ease;display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:bold;white-space:nowrap;overflow:hidden">
                            <span id="llmProgressPercent" style="text-shadow:0 1px 2px rgba(0,0,0,.3)">0%</span>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:12px;font-size:12px">
                        <div style="background:#fff;padding:10px;border-radius:8px">
                            <div style="color:#94a3b8;font-size:10.5px;margin-bottom:3px">📦 حجم</div>
                            <div id="llmProgressSize" style="font-weight:bold;color:#1e293b;font-family:monospace">۰ / ۰</div>
                        </div>
                        <div style="background:#fff;padding:10px;border-radius:8px">
                            <div style="color:#94a3b8;font-size:10.5px;margin-bottom:3px">⚡ سرعت</div>
                            <div id="llmProgressSpeed" style="font-weight:bold;color:#1e293b;font-family:monospace">— MB/s</div>
                        </div>
                        <div style="background:#fff;padding:10px;border-radius:8px">
                            <div style="color:#94a3b8;font-size:10.5px;margin-bottom:3px">⏱ زمان باقیمانده</div>
                            <div id="llmProgressEta" style="font-weight:bold;color:#1e293b;font-family:monospace">—</div>
                        </div>
                        <div style="background:#fff;padding:10px;border-radius:8px">
                            <div style="color:#94a3b8;font-size:10.5px;margin-bottom:3px">📊 وضعیت</div>
                            <div id="llmProgressStatus" style="font-weight:bold;color:#0891b2">شروع...</div>
                        </div>
                    </div>

                    <div id="llmProgressLog" style="margin-top:12px;padding:10px;background:#1e293b;color:#10b981;border-radius:8px;font-family:monospace;font-size:11px;max-height:100px;overflow-y:auto;direction:ltr;text-align:left"></div>
                </div>
            </div>
        </div>

        <!-- STT -->
        <div class="card">
            <div class="header">🎤 مدل تبدیل گفتار (STT) — <?= htmlspecialchars($settings['ai_stt_provider']) ?></div>
            <div class="body">
                <input type="hidden" name="ai_stt_provider" value="<?= htmlspecialchars($settings['ai_stt_provider']) ?>">

                <div class="form-group">
                    <label>مسیر whisper-cli</label>
                    <input type="text" name="ai_whisper_bin" value="<?= htmlspecialchars($settings['ai_whisper_bin']) ?>" dir="ltr">
                </div>

                <div class="form-group">
                    <label>پوشه مدل‌ها</label>
                    <input type="text" name="ai_whisper_models_dir" value="<?= htmlspecialchars($settings['ai_whisper_models_dir']) ?>" dir="ltr">
                </div>

                <div class="form-group">
                    <label>زبان پیش‌فرض</label>
                    <select name="ai_stt_language">
                        <option value="auto" <?= $settings['ai_stt_language'] === 'auto' ? 'selected' : '' ?>>🔄 خودکار (توصیه)</option>
                        <option value="fa" <?= $settings['ai_stt_language'] === 'fa' ? 'selected' : '' ?>>فارسی</option>
                        <option value="en" <?= $settings['ai_stt_language'] === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="ar" <?= $settings['ai_stt_language'] === 'ar' ? 'selected' : '' ?>>العربية</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>مدل پیش‌فرض STT</label>
                    <?php if (empty($sttModelList)): ?>
                        <div style="padding:15px;background:#fef3c7;border-radius:8px;color:#92400e;font-size:12px">
                            ⚠️ هیچ مدلی یافت نشد.
                        </div>
                    <?php else: ?>
                        <div class="models-grid" id="sttModelsGrid">
                            <?php foreach ($sttModelList as $m): ?>
                                <div class="model-card <?= $m['name'] === $settings['ai_stt_model'] ? 'active' : '' ?>" data-model="<?= htmlspecialchars($m['name']) ?>">
                                    <input type="radio" name="ai_stt_model" value="<?= htmlspecialchars($m['name']) ?>" <?= $m['name'] === $settings['ai_stt_model'] ? 'checked' : '' ?> <?= !$m['installed'] ? 'disabled' : '' ?> style="display:none">
                                    <div class="model-name"><?= htmlspecialchars($m['name']) ?></div>
                                    <div class="model-meta">
                                        <span class="model-badge badge-size">💾 <?= $m['size_mb'] ?> MB</span>
                                        <?php if ($m['installed']): ?>
                                            <span class="model-badge badge-installed">✅ نصب</span>
                                        <?php elseif (!empty($m['incomplete'])): ?>
                                            <span class="model-badge" style="background:#fef3c7;color:#92400e">⚠️ ناقص (<?= $m['actual_size_mb'] ?>/<?= $m['expected_size_mb'] ?> MB)</span>
                                        <?php else: ?>
                                            <span class="model-badge badge-notinstalled">❌ نصب نشده</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:11px;color:#64748b;margin-bottom:8px"><?= htmlspecialchars($m['display']) ?></div>
                                    <div class="model-actions">
                                        <?php if ($m['installed']): ?>
                                            <button type="button" class="btn-a btn-primary-a btn-sm" onclick="selectSttModel(this)" style="width:100%">✓ انتخاب</button>
                                        <?php elseif (!empty($m['incomplete'])): ?>
                                            <button type="button" class="btn-a btn-warning-a btn-sm" onclick="downloadSttModel('<?= htmlspecialchars($m['name']) ?>')" style="width:100%">
                                                <i class="bi bi-arrow-clockwise"></i> دانلود مجدد
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn-a btn-success-a btn-sm" onclick="downloadSttModel('<?= htmlspecialchars($m['name']) ?>')" style="width:100%">
                                                <i class="bi bi-download"></i> دانلود
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="progress-box" id="progress-<?= htmlspecialchars($m['name']) ?>">
                                        <div class="progress-header">
                                            <span>📥 دانلود «<?= htmlspecialchars($m['name']) ?>»</span>
                                            <span class="progress-percent" style="color:#0891b2">0%</span>
                                        </div>
                                        <div class="progress-bar-wrap">
                                            <div class="progress-fill"></div>
                                        </div>
                                        <div class="progress-stats">
                                            <div class="progress-stat">
                                                <div class="lbl">📦 حجم</div>
                                                <div class="val progress-size">— / —</div>
                                            </div>
                                            <div class="progress-stat">
                                                <div class="lbl">⚡ سرعت</div>
                                                <div class="val progress-speed">— MB/s</div>
                                            </div>
                                            <div class="progress-stat">
                                                <div class="lbl">⏱ باقی‌مانده</div>
                                                <div class="val progress-eta">—</div>
                                            </div>
                                        </div>
                                        <div class="progress-meta">
                                            <span>🌐 <span class="progress-source">در حال اتصال...</span></span>
                                            <span>📊 <span class="progress-status">شروع...</span></span>
                                        </div>
                                        <div class="progress-log"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- دکمه‌ها -->
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:30px">
            <button type="submit" class="btn-a btn-success-a" style="font-size:14px;padding:12px 30px">
                <i class="bi bi-check-lg"></i> ذخیره تنظیمات
            </button>
            <a href="modules.php" class="btn-a btn-secondary-a" style="font-size:14px;padding:12px 30px">
                <i class="bi bi-arrow-right"></i> بازگشت
            </a>
        </div>
    </form>

    <!-- راهنما -->
    <div class="card">
        <div class="header">📖 راهنمای انتخاب مدل</div>
        <div class="body">
            <div class="guide-box">
                <strong>📱 برای Termux (موبایل):</strong><br>
                <code>qwen2.5:1.5b</code> یا <code>llama3.2:1b</code> یا <code>tinyllama</code> — سبک و سریع<br><br>

                <strong>💻 برای VPS با 4GB RAM:</strong><br>
                <code>qwen2.5:3b</code> یا <code>llama3.2:3b</code> — متعادل<br><br>

                <strong>🖥 برای سرور با 8GB+ RAM:</strong><br>
                <code>llama3.1:latest</code> یا <code>qwen2.5:7b</code> — دقیق و کامل<br><br>

                <strong>💡 نکته:</strong> مدل‌های کوچک‌تر سریع‌تر هستند اما تحلیل کم‌دقت‌تری دارند.
            </div>
        </div>
    </div>

</div>

<script>
// ═══════════════════════════════════════════════════════════
//  متغیرهای سراسری
// ═══════════════════════════════════════════════════════════
let llmEventSource = null;
let llmLastBytes = 0;
let llmLastTime = 0;

// ═══════════════════════════════════════════════════════════
//  LLM: دانلود مدل جدید (Ollama via SSE)
// ═══════════════════════════════════════════════════════════
function setNewLlmModel(name) {
    document.getElementById("newLlmModel").value = name;
    document.getElementById("newLlmModel").focus();
}

async function startLlmPull() {
    const model = document.getElementById("newLlmModel").value.trim();
    if (!model) { alert("نام مدل را وارد کنید"); return; }
    if (llmEventSource) { alert("یک دانلود در حال اجراست"); return; }

    const box = document.getElementById("llmProgressBox");
    const fill = document.getElementById("llmProgressFill");
    const percent = document.getElementById("llmProgressPercent");
    const title = document.getElementById("llmProgressTitle");
    const sizeEl = document.getElementById("llmProgressSize");
    const speedEl = document.getElementById("llmProgressSpeed");
    const etaEl = document.getElementById("llmProgressEta");
    const statusEl = document.getElementById("llmProgressStatus");
    const logEl = document.getElementById("llmProgressLog");
    const btn = document.getElementById("pullBtn");

    box.style.display = "block";
    fill.style.width = "0%";
    percent.textContent = "0%";
    title.textContent = "📥 دانلود «" + model + "»...";
    logEl.innerHTML = "";
    sizeEl.textContent = "۰ / ۰";
    speedEl.textContent = "— MB/s";
    etaEl.textContent = "—";
    statusEl.textContent = "شروع...";
    statusEl.style.color = "#0891b2";
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> در حال دانلود...';

    llmLastBytes = 0;
    llmLastTime = Date.now();

    llmEventSource = new EventSource("/ajax/ai_pull_stream.php?model=" + encodeURIComponent(model));

    llmEventSource.onmessage = (e) => {
        try {
            const data = JSON.parse(e.data);

            if (data.error) {
                logEl.innerHTML += "❌ " + data.error + "\n";
                statusEl.textContent = "خطا";
                statusEl.style.color = "#dc2626";
                finishLlmPull();
                return;
            }

            if (data.done || data.status === "success") {
                fill.style.width = "100%";
                percent.textContent = "100%";
                statusEl.textContent = "✅ تکمیل شد";
                statusEl.style.color = "#10b981";
                logEl.innerHTML += "✅ دانلود کامل شد\n";
                finishLlmPull();
                setTimeout(() => location.reload(), 2000);
                return;
            }

            const total = data.total || 0;
            const completed = data.completed || 0;

            if (total > 0) {
                const pct = Math.min(100, Math.round((completed / total) * 100));
                fill.style.width = pct + "%";
                percent.textContent = pct + "%";
                sizeEl.textContent = formatBytes(completed) + " / " + formatBytes(total);

                const now = Date.now();
                const diff = (now - llmLastTime) / 1000;
                if (diff > 0.5) {
                    const speed = (completed - llmLastBytes) / diff;
                    speedEl.textContent = formatBytes(speed) + "/s";
                    if (speed > 0) {
                        etaEl.textContent = formatTime((total - completed) / speed);
                    }
                    llmLastBytes = completed;
                    llmLastTime = now;
                }
                statusEl.textContent = "در حال دانلود...";
            }

            if (data.status && data.status !== "downloading") {
                logEl.innerHTML += "[" + new Date().toLocaleTimeString("fa-IR") + "] " + data.status + "\n";
                logEl.scrollTop = logEl.scrollHeight;
            }
        } catch (err) {}
    };

    llmEventSource.onerror = () => finishLlmPull();
}

function finishLlmPull() {
    if (llmEventSource) { llmEventSource.close(); llmEventSource = null; }
    const btn = document.getElementById("pullBtn");
    if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-download"></i> دانلود مدل';
    }
}

function cancelLlmPull() {
    if (!confirm("دانلود لغو شود؟")) return;
    finishLlmPull();
    document.getElementById("llmProgressBox").style.display = "none";
}

// ═══════════════════════════════════════════════════════════
//  LLM: انتخاب مدل
// ═══════════════════════════════════════════════════════════
function selectLlmModel(el) {
    document.querySelectorAll("#llmModelsGrid .model-card").forEach(c => c.classList.remove("active"));
    el.classList.add("active");
    el.querySelector("input[type=radio]").checked = true;
}

// ═══════════════════════════════════════════════════════════
//  STT: انتخاب مدل
// ═══════════════════════════════════════════════════════════
function selectSttModel(btn) {
    const card = btn.closest(".model-card");
    const radio = card.querySelector("input[type=radio]");
    if (radio) radio.checked = true;
    document.querySelectorAll("#sttModelsGrid .model-card").forEach(c => c.classList.remove("active"));
    card.classList.add("active");
}

// ═══════════════════════════════════════════════════════════
//  STT: دانلود مدل
// ═══════════════════════════════════════════════════════════
function downloadSttModel(modelName) {
    startDownload(modelName);
}

async function startDownload(modelName) {
    const progress = document.getElementById("progress-" + modelName);
    if (!progress) return;
    progress.style.display = "block";

    try {
        const fd = new FormData();
        fd.append("model", modelName);

        const r = await fetch("/ajax/ai_stt_pull_start.php", { method: "POST", body: fd });
        const data = await r.json();
        if (!data.ok) { alert("خطا: " + (data.error || "نامشخص")); return; }
        pollDownload(modelName);
    } catch (e) { alert("خطا: " + e.message); }
}

function pollDownload(modelName) {
    const progressEl = document.getElementById("progress-" + modelName);
    if (!progressEl) return;

    const fill = progressEl.querySelector(".progress-fill");
    const percentEl = progressEl.querySelector(".progress-percent");
    const sizeEl = progressEl.querySelector(".progress-size");
    const speedEl = progressEl.querySelector(".progress-speed");
    const etaEl = progressEl.querySelector(".progress-eta");
    const sourceEl = progressEl.querySelector(".progress-source");
    const statusEl = progressEl.querySelector(".progress-status");
    const logEl = progressEl.querySelector(".progress-log");

    let lastBytes = 0;
    let lastTime = Date.now();

    const log = (msg) => {
        const t = new Date().toLocaleTimeString("fa-IR");
        logEl.innerHTML += "[" + t + "] " + msg + "\n";
        logEl.scrollTop = logEl.scrollHeight;
    };

    log("شروع دانلود");

    const interval = setInterval(async () => {
        try {
            const r = await fetch("/ajax/ai_stt_pull_status.php?model=" + encodeURIComponent(modelName));
            const data = await r.json();

            if (!data.ok) {
                statusEl.textContent = "⏳ انتظار پردازش...";
                return;
            }

            const s = data.status;
            const pct = s.percent || 0;
            const dl = s.downloaded || 0;
            const tot = s.total || 0;

            fill.style.width = pct + "%";
            percentEl.textContent = pct + "%";
            fill.textContent = pct > 5 ? pct + "%" : "";

            sizeEl.textContent = formatBytes(dl) + " / " + formatBytes(tot);

            const now = Date.now();
            const diff = (now - lastTime) / 1000;
            if (diff > 0.5 && dl > 0) {
                const speed = (dl - lastBytes) / diff;
                speedEl.textContent = formatBytes(speed) + "/s";
                if (speed > 0 && tot > 0) {
                    etaEl.textContent = formatTime((tot - dl) / speed);
                }
                lastBytes = dl;
                lastTime = now;
            }

            if (s.source) sourceEl.textContent = s.source;

            if (s.status === "checking") {
                statusEl.textContent = "در حال انتخاب منبع...";
                log("تست منابع دانلود");
            } else if (s.status === "downloading") {
                statusEl.textContent = "در حال دانلود";
            } else if (s.status === "success") {
                fill.style.width = "100%";
                percentEl.textContent = "100%";
                fill.textContent = "✓";
                statusEl.textContent = "✅ تکمیل شد";
                statusEl.style.color = "#10b981";
                log("✅ دانلود کامل شد (" + formatBytes(s.size || dl) + ")");
                clearInterval(interval);
                setTimeout(() => location.reload(), 2000);
            } else if (s.status === "error") {
                fill.style.background = "linear-gradient(90deg,#ef4444,#dc2626)";
                statusEl.textContent = "❌ خطا";
                statusEl.style.color = "#dc2626";
                log("❌ " + (s.error || "خطای ناشناخته"));
                clearInterval(interval);
            }
        } catch (e) {
            log("⚠️ خطای شبکه");
        }
    }, 1000);
}

// ═══════════════════════════════════════════════════════════
//  ابزارها
// ═══════════════════════════════════════════════════════════
function formatBytes(b) {
    if (b < 1024) return b + " B";
    if (b < 1048576) return (b/1024).toFixed(1) + " KB";
    if (b < 1073741824) return (b/1048576).toFixed(1) + " MB";
    return (b/1073741824).toFixed(2) + " GB";
}

function formatTime(seconds) {
    if (seconds < 60) return Math.round(seconds) + " ثانیه";
    if (seconds < 3600) return Math.round(seconds / 60) + " دقیقه";
    return Math.round(seconds / 3600) + " ساعت";
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
