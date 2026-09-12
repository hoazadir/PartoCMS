</div><!-- /.main-content -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// نمایش پیام‌های فلش
function showAlert(msg, type = 'success', duration = 3000) {
    const div = document.createElement('div');
    div.className = 'alert alert-' + type + ' position-fixed';
    div.style.cssText = 'top: 20px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2);';
    div.textContent = msg;
    document.body.appendChild(div);
    setTimeout(() => div.remove(), duration);
}
</script>
</body>
</html>
