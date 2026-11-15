/**
 * Properties Admin — Select2 برای استان/شهر
 */
document.addEventListener('DOMContentLoaded', function() {
    // بارگذاری استان‌ها
    fetch('/modules/properties/ajax/get-provinces.php')
        .then(r => r.json())
        .then(d => {
            const sel = document.getElementById('province-select');
            if (!sel) return;
            if (d.success && d.provinces) {
                d.provinces.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    sel.appendChild(opt);
                });
            }
        });

    // Select2 استان
    if (typeof jQuery !== 'undefined' && jQuery('#province-select').length) {
        jQuery('#province-select').select2({
            placeholder: 'جستجوی استان...',
            allowClear: true,
            width: '100%'
        });

        // Select2 شهر
        jQuery('#city-select').select2({
            placeholder: 'جستجوی شهر...',
            allowClear: true,
            width: '100%',
            ajax: {
                url: '/modules/properties/ajax/get-cities.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        province_id: jQuery('#province-select').val(),
                        q: params.term
                    };
                },
                processResults: function(data) {
                    if (!data.success) return { results: [] };
                    return {
                        results: data.cities.map(c => ({ id: c.id, text: c.name }))
                    };
                }
            }
        });

        // وقتی شهر انتخاب شد، نام آن را در فیلد مخفی city بگذار
        jQuery('#city-select').on('change', function() {
            const data = jQuery(this).select2('data');
            const text = data[0] ? data[0].text : '';
            jQuery('#city-name-input').val(text);
        });
    }
});

// ─── Select2 مشاور (agent) ─────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    if (typeof jQuery !== 'undefined' && jQuery('#agent-select').length) {
        jQuery('#agent-select').select2({
            placeholder: 'جستجوی مشاور (نام کاربری یا کد ملی)...',
            allowClear: true,
            width: '100%',
            ajax: {
                url: '/modules/properties/ajax/get-agents.php',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { q: params.term };
                },
                processResults: function(data) {
                    if (!data.success) return { results: [] };
                    return { results: data.agents };
                }
            }
        });
    }
});
