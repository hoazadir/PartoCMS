/**
 * Custom Confirmation Modal
 */
function showConfirm(message, onConfirm) {
    let overlay = document.getElementById('customModal');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'customModal';
        overlay.className = 'custom-modal-overlay';
        overlay.innerHTML = `
            <div class="custom-modal-box">
                <div class="custom-modal-header">
                    <span>⚠️ تایید عملیات</span>
                </div>
                <div class="custom-modal-body" id="customModalMessage"></div>
                <div class="custom-modal-footer">
                    <button id="customModalCancel" class="btn-cancel">لغو</button>
                    <button id="customModalConfirm" class="btn-confirm">تایید</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);
    }

    document.getElementById('customModalMessage').innerText = message;
    overlay.style.display = 'flex';

    const oldConfirm = document.getElementById('customModalConfirm');
    const oldCancel = document.getElementById('customModalCancel');
    const newConfirm = oldConfirm.cloneNode(true);
    const newCancel = oldCancel.cloneNode(true);
    oldConfirm.parentNode.replaceChild(newConfirm, oldConfirm);
    oldCancel.parentNode.replaceChild(newCancel, oldCancel);

    newConfirm.addEventListener('click', function() {
        overlay.style.display = 'none';
        if (typeof onConfirm === 'function') onConfirm();
    });

    newCancel.addEventListener('click', function() {
        overlay.style.display = 'none';
    });
}
