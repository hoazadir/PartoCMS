<?php
if (!function_exists('render_installer_layout')) {
    function render_installer_layout(int $currentStep, string $pageTitle, string $content) {
        $steps = [1 => 'پیش‌نیازها', 2 => 'دیتابیس', 3 => 'ادمین', 4 => 'نوع سایت', 5 => 'نصب'];
        ?>
        <!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>نصب PartoCMS — <?= htmlspecialchars($pageTitle) ?></title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
            <link rel="stylesheet" href="assets/style.css">
        </head>
        <body>
            <div class="installer-container">
                <div class="installer-card">
                    <div class="installer-header">
                        <h1>🚀 نصب PartoCMS</h1>
                        <p>سیستم مدیریت محتوای حرفه‌ای</p>
                    </div>
                    <div class="progress-steps">
                        <?php foreach ($steps as $num => $label): 
                            $state = ($num == $currentStep) ? 'active' : ($num < $currentStep ? 'done' : '');
                        ?>
                            <div class="step-wrapper <?= $state ?>">
                                <div class="step-indicator <?= $state ?>"><?= $num < $currentStep ? '✓' : $num ?></div>
                                <div class="step-label"><?= $label ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="installer-body">
                        <?= $content ?>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}
