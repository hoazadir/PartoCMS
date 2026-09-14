<?php
/**
 * PartoCMS - Language Switcher with Modal
 */
$i18n = function_exists('getI18n') ? getI18n() : null;
if (!$i18n) return;

$currentLang = $i18n->getCurrentInfo();
$allLanguages = $i18n->getLanguages();

if (count($allLanguages) <= 1) return;

$labelText = __t('menu_language', [], 'زبان');
$currentPath = $_SERVER['PHP_SELF'] ?? '/admin/index.php';
$currentCode = $i18n->getCurrent();
?>
<!-- Language Switcher Button -->
<div class="language-switcher">
    <button type="button" class="switcher-header" onclick="openLangModal()">
        <span class="switcher-icon">🌍</span>
        <span class="switcher-title"><?= htmlspecialchars($labelText) ?></span>
        <span class="switcher-current">
            <?= htmlspecialchars($currentLang['flag'] ?? '🌐') ?>
            <?= htmlspecialchars($currentLang['native_name'] ?? '?') ?>
        </span>
    </button>
</div>

<!-- Language Modal -->
<div class="lang-modal-overlay" id="langModal" onclick="closeLangModal(event)">
    <div class="lang-modal" onclick="event.stopPropagation()">
        <div class="lang-modal-header">
            <h3>🌍 <?= htmlspecialchars($labelText) ?></h3>
            <button type="button" class="lang-modal-close" onclick="closeLangModal()">✕</button>
        </div>
        <div class="lang-modal-body">
            <?php foreach ($allLanguages as $lang): ?>
                <?php $isActive = $lang['code'] === $currentCode; ?>
                <a href="<?= htmlspecialchars($currentPath) ?>?set_lang=<?= urlencode($lang['code']) ?>"
                   class="lang-item <?= $isActive ? 'lang-active' : '' ?>">
                    <span class="lang-item-flag"><?= htmlspecialchars($lang['flag'] ?? '🌐') ?></span>
                    <span class="lang-item-info">
                        <span class="lang-item-native"><?= htmlspecialchars($lang['native_name']) ?></span>
                        <span class="lang-item-code"><?= htmlspecialchars($lang['code']) ?></span>
                    </span>
                    <?php if ($isActive): ?>
                        <span class="lang-item-check">✓</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
/* Language Switcher Button */
.language-switcher {
    background: #0f172a;
    border-bottom: 1px solid #334155;
    padding: 10px 20px;
}
.language-switcher .switcher-header {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 13px;
    color: #cbd5e1;
    padding: 8px 12px;
    border-radius: 8px;
    transition: background 0.2s;
    background: transparent;
    border: none;
    width: 100%;
    font-family: inherit;
    text-align: right;
}
.language-switcher .switcher-header:hover {
    background: #334155;
    color: #fff;
}
.language-switcher .switcher-icon { font-size: 18px; }
.language-switcher .switcher-title { color: #94a3b8; font-size: 11px; }
.language-switcher .switcher-current {
    margin-right: auto;
    color: #fff;
    font-weight: bold;
    display: flex;
    align-items: center;
    gap: 5px;
}
.language-switcher .switcher-current::after {
    content: '▼';
    font-size: 8px;
    color: #64748b;
    margin-right: 5px;
}

/* Modal Overlay */
.lang-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 99999;
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    animation: fadeIn 0.2s ease;
}
.lang-modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Modal Content */
.lang-modal {
    background: #1e293b;
    border-radius: 20px;
    width: 100%;
    max-width: 480px;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
    border: 1px solid #475569;
    animation: slideUp 0.3s ease;
    overflow: hidden;
}

@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.lang-modal-header {
    padding: 20px 25px;
    border-bottom: 1px solid #334155;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
    background: #0f172a;
}
.lang-modal-header h3 {
    margin: 0;
    color: #fff;
    font-size: 18px;
    font-weight: bold;
}
.lang-modal-close {
    background: #334155;
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}
.lang-modal-close:hover {
    background: #ef4444;
}

/* Modal Body - Scrollable Grid */
.lang-modal-body {
    padding: 12px;
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    flex: 1;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    align-content: start;
}

/* Custom Scrollbar */
.lang-modal-body::-webkit-scrollbar {
    width: 6px;
}
.lang-modal-body::-webkit-scrollbar-thumb {
    background: #475569;
    border-radius: 3px;
}
.lang-modal-body::-webkit-scrollbar-track {
    background: #0f172a;
}

/* Language Item */
.lang-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #0f172a;
    border-radius: 12px;
    text-decoration: none;
    color: #cbd5e1;
    transition: all 0.2s;
    border: 2px solid transparent;
    min-height: 60px;
}
.lang-item:hover {
    background: #334155;
    color: #fff;
    transform: translateY(-2px);
    border-color: #3b82f6;
}
.lang-item.lang-active {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    color: #fff;
    border-color: #60a5fa;
    cursor: default;
}
.lang-item.lang-active:hover {
    transform: none;
}
.lang-item-flag {
    font-size: 24px;
    flex-shrink: 0;
}
.lang-item-info {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.lang-item-native {
    font-weight: bold;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.lang-item-code {
    font-size: 10px;
    color: #64748b;
    font-family: monospace;
}
.lang-item.lang-active .lang-item-code {
    color: #dbeafe;
}
.lang-item-check {
    color: #fff;
    font-size: 16px;
    font-weight: bold;
    flex-shrink: 0;
}

/* Mobile */
@media (max-width: 600px) {
    .lang-modal {
        max-height: 90vh;
        border-radius: 16px;
    }
    .lang-modal-body {
        grid-template-columns: 1fr;
    }
    .lang-item {
        padding: 14px 16px;
    }
}
</style>

<script>
function openLangModal() {
    const modal = document.getElementById('langModal');
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeLangModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('langModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// بستن با Esc
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLangModal();
    }
});
</script>
