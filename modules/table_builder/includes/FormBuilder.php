<?php
/**
 * PartoCMS - Form Builder (TailwindCSS)
 * تولید فرم‌های HTML از خروجی ModuleAnalyzer
 *
 * @version 1.0
 * @date 2026-09-19
 */
class FormBuilder {

    private $tb;
    private $action = '';
    private $method = 'POST';

    public function __construct($tb) {
        $this->tb = $tb;
    }

    public function setAction(string $action): self {
        $this->action = $action;
        return $this;
    }

    public function setMethod(string $method): self {
        $this->method = strtoupper($method);
        return $this;
    }

    /**
     * رندر کل فرم
     */
    public function renderForm(array $analysis, array $values = [], array $options = []): string {
        $formFields = $analysis['form_fields'] ?? [];
        $action = $options['action'] ?? $this->action;
        $method = $options['method'] ?? $this->method;
        $submitLabel = $options['submit_label'] ?? '💾 ذخیره';
        $cancelUrl = $options['cancel_url'] ?? '';
        $errors = $options['errors'] ?? [];

        $html = '<form method="' . htmlspecialchars($method) . '" action="' . htmlspecialchars($action) . '" class="space-y-6">' . "\n";

        // CSRF
        if (function_exists('csrf_token')) {
            $html .= '  <input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">' . "\n";
        }

        // گروه‌بندی فیلدها: ۲ ستونی برای فیلدهای کوتاه، تمام‌عرض برای textarea
        $html .= '  <div class="grid grid-cols-1 md:grid-cols-2 gap-5">' . "\n";

        $fullWidthTypes = ['textarea', 'json', 'image', 'file'];

        foreach ($formFields as $field) {
            $value = $values[$field['name']] ?? $field['default'] ?? null;
            $fieldHtml = $this->renderField($field, $value, $errors[$field['name']] ?? null);
            $colSpan = in_array($field['html_type'], $fullWidthTypes, true) ? ' md:col-span-2' : '';
            $html .= '    <div class="' . trim($colSpan) . '">' . "\n";
            $html .= $fieldHtml;
            $html .= '    </div>' . "\n";
        }

        $html .= '  </div>' . "\n";

        // دکمه‌ها
        $html .= '  <div class="flex items-center gap-3 pt-4 border-t border-gray-200">' . "\n";
        $html .= '    <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-l from-indigo-600 to-purple-600 text-white font-medium rounded-lg shadow-md hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">' . "\n";
        $html .= '      ' . htmlspecialchars($submitLabel) . "\n";
        $html .= '    </button>' . "\n";
        if ($cancelUrl) {
            $html .= '    <a href="' . htmlspecialchars($cancelUrl) . '" class="inline-flex items-center gap-2 px-5 py-2.5 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition">' . "\n";
            $html .= '      ✖ انصراف' . "\n";
            $html .= '    </a>' . "\n";
        }
        $html .= '  </div>' . "\n";

        $html .= '</form>' . "\n";
        return $html;
    }

    /**
     * رندر یک فیلد بر اساس نوع
     */
    public function renderField(array $field, $value = null, ?string $error = null): string {
        $html = $this->renderLabel($field);
        $html .= $this->renderInput($field, $value);
        $html .= $this->renderHelp($field);
        if ($error) {
            $html .= '<p class="mt-1 text-sm text-red-600">⚠ ' . htmlspecialchars($error) . '</p>' . "\n";
        }
        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // Label
    // ═══════════════════════════════════════════════════════════
    private function renderLabel(array $field): string {
        $icon = $field['icon'] ?? '📋';
        $label = $field['label'] ?? $field['name'];
        $required = !empty($field['required']) ? ' <span class="text-red-500">*</span>' : '';
        return '<label for="f_' . htmlspecialchars($field['name']) . '" class="block text-sm font-medium text-gray-700 mb-1.5">' . "\n"
             . '  ' . $icon . ' ' . htmlspecialchars($label) . $required . "\n"
             . '</label>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Help Text
    // ═══════════════════════════════════════════════════════════
    private function renderHelp(array $field): string {
        $helps = [];
        if (!empty($field['unique'])) $helps[] = '🔑 باید یکتا باشد';
        if (!empty($field['max_length'])) $helps[] = 'حداکثر ' . $field['max_length'] . ' کاراکتر';
        if (!empty($field['foreign_key'])) {
            $ref = $field['foreign_key']['ref_table'] ?? '';
            if ($ref) $helps[] = '🔗 مرتبط با ' . $ref;
        }
        if (empty($helps)) return '';
        return '<p class="mt-1 text-xs text-gray-500">' . implode(' • ', $helps) . '</p>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Input Dispatcher
    // ═══════════════════════════════════════════════════════════
    private function renderInput(array $field, $value): string {
        $type = $field['html_type'] ?? 'text';
        switch ($type) {
            case 'textarea':    return $this->renderTextarea($field, $value);
            case 'email':       return $this->renderEmail($field, $value);
            case 'tel':         return $this->renderTel($field, $value);
            case 'url':         return $this->renderUrl($field, $value);
            case 'number':      return $this->renderNumber($field, $value);
            case 'decimal':     return $this->renderDecimal($field, $value);
            case 'date':        return $this->renderDate($field, $value);
            case 'datetime-local': return $this->renderDateTime($field, $value);
            case 'time':        return $this->renderTime($field, $value);
            case 'checkbox':    return $this->renderCheckbox($field, $value);
            case 'select':      return $this->renderSelect($field, $value);
            case 'fk_select':   return $this->renderFKSelect($field, $value);
            case 'password':    return $this->renderPassword($field, $value);
            case 'color':       return $this->renderColor($field, $value);
            case 'image':       return $this->renderFile($field, $value, 'image/*');
            case 'file':        return $this->renderFile($field, $value);
            case 'json':        return $this->renderJson($field, $value);
            default:            return $this->renderText($field, $value);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Base Classes
    // ═══════════════════════════════════════════════════════════
    private function inputClasses(): string {
        return 'w-full px-3.5 py-2.5 rounded-lg border border-gray-300 bg-white text-gray-900 text-sm '
             . 'focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none transition '
             . 'placeholder:text-gray-400';
    }

    private function baseAttrs(array $field): string {
        $attrs = '';
        $attrs .= ' name="' . htmlspecialchars($field['name']) . '"';
        $attrs .= ' id="f_' . htmlspecialchars($field['name']) . '"';
        if (!empty($field['placeholder'])) {
            $attrs .= ' placeholder="' . htmlspecialchars($field['placeholder']) . '"';
        }
        if (!empty($field['required']))  $attrs .= ' required';
        if (!empty($field['max_length'])) $attrs .= ' maxlength="' . (int)$field['max_length'] . '"';
        return $attrs;
    }

    // ═══════════════════════════════════════════════════════════
    // Text
    // ═══════════════════════════════════════════════════════════
    private function renderText(array $field, $value): string {
        return '<input type="text" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Email
    // ═══════════════════════════════════════════════════════════
    private function renderEmail(array $field, $value): string {
        return '<input type="email" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Tel
    // ═══════════════════════════════════════════════════════════
    private function renderTel(array $field, $value): string {
        return '<input type="tel" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // URL
    // ═══════════════════════════════════════════════════════════
    private function renderUrl(array $field, $value): string {
        return '<input type="url" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Number
    // ═══════════════════════════════════════════════════════════
    private function renderNumber(array $field, $value): string {
        return '<input type="number" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Decimal
    // ═══════════════════════════════════════════════════════════
    private function renderDecimal(array $field, $value): string {
        $step = $field['step'] ?? '0.01';
        return '<input type="number" step="' . htmlspecialchars($step) . '" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Date
    // ═══════════════════════════════════════════════════════════
    private function renderDate(array $field, $value): string {
        $v = $value ? date('Y-m-d', strtotime((string)$value)) : '';
        return '<input type="date" value="' . htmlspecialchars($v) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // DateTime
    // ═══════════════════════════════════════════════════════════
    private function renderDateTime(array $field, $value): string {
        $v = $value ? date('Y-m-d\TH:i', strtotime((string)$value)) : '';
        return '<input type="datetime-local" value="' . htmlspecialchars($v) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Time
    // ═══════════════════════════════════════════════════════════
    private function renderTime(array $field, $value): string {
        return '<input type="time" value="' . htmlspecialchars((string)$value) . '"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Textarea
    // ═══════════════════════════════════════════════════════════
    private function renderTextarea(array $field, $value): string {
        return '<textarea rows="4"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . ' resize-y">'
             . htmlspecialchars((string)$value)
             . '</textarea>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Checkbox
    // ═══════════════════════════════════════════════════════════
    private function renderCheckbox(array $field, $value): string {
        $checked = (!empty($value) && $value != '0') ? ' checked' : '';
        $name = htmlspecialchars($field['name']);
        $id = 'f_' . $name;
        return '<div class="flex items-center pt-6">' . "\n"
             . '  <input type="hidden" name="' . $name . '" value="0">' . "\n"
             . '  <input type="checkbox" name="' . $name . '" id="' . $id . '" value="1"' . $checked . "\n"
             . '         class="w-5 h-5 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">' . "\n"
             . '  <label for="' . $id . '" class="mr-2 text-sm text-gray-700 cursor-pointer">' . "\n"
             . '    ' . htmlspecialchars($field['label']) . "\n"
             . '  </label>' . "\n"
             . '</div>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Select (ENUM)
    // ═══════════════════════════════════════════════════════════
    private function renderSelect(array $field, $value): string {
        $html = '<select' . $this->baseAttrs($field) . ' class="' . $this->inputClasses() . '">' . "\n";
        $html .= '  <option value="">— انتخاب کنید —</option>' . "\n";
        foreach (($field['options'] ?? []) as $opt) {
            $sel = ((string)$value === (string)$opt) ? ' selected' : '';
            $html .= '  <option value="' . htmlspecialchars($opt) . '"' . $sel . '>' . htmlspecialchars($opt) . '</option>' . "\n";
        }
        $html .= '</select>' . "\n";
        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // FK Select
    // ═══════════════════════════════════════════════════════════
    private function renderFKSelect(array $field, $value): string {
        $fk = $field['foreign_key'] ?? [];
        $refTable = $fk['ref_table'] ?? '';
        $refCol = $fk['ref_column'] ?? 'id';

        $options = [];
        if ($refTable && $this->tb) {
            try {
                // ستون نمایش: name یا title یا اولین varchar
                $displayCol = $this->detectDisplayColumn($refTable);
                $options = $this->tb->getForeignKeyValues($refTable, $refCol, $displayCol);
            } catch (Throwable $e) {
                $options = [];
            }
        }

        $html = '<select' . $this->baseAttrs($field) . ' class="' . $this->inputClasses() . '">' . "\n";
        $html .= '  <option value="">— انتخاب کنید —</option>' . "\n";
        foreach ($options as $opt) {
            $optId = $opt['value'] ?? $opt['id'] ?? '';
            $optLabel = $opt['label'] ?? $opt['name'] ?? $opt['title'] ?? ('#' . $optId);
            $sel = ((string)$value === (string)$optId) ? ' selected' : '';
            $html .= '  <option value="' . htmlspecialchars($optId) . '"' . $sel . '>' . htmlspecialchars($optLabel) . '</option>' . "\n";
        }
        $html .= '</select>' . "\n";
        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // Password
    // ═══════════════════════════════════════════════════════════
    private function renderPassword(array $field, $value): string {
        return '<input type="password" value=""'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . '" dir="ltr" autocomplete="new-password">' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // Color
    // ═══════════════════════════════════════════════════════════
    private function renderColor(array $field, $value): string {
        $v = $value ?: '#3b82f6';
        return '<div class="flex items-center gap-2">' . "\n"
             . '  <input type="color" name="' . htmlspecialchars($field['name']) . '" id="f_' . htmlspecialchars($field['name']) . '" value="' . htmlspecialchars($v) . '"'
             . ' class="w-12 h-10 rounded-lg border border-gray-300 cursor-pointer">' . "\n"
             . '  <span class="text-xs text-gray-500">انتخاب رنگ</span>' . "\n"
             . '</div>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // File/Image
    // ═══════════════════════════════════════════════════════════
    private function renderFile(array $field, $value, string $accept = ''): string {
        $name = htmlspecialchars($field['name']);
        $html = '<input type="file" name="' . $name . '" id="f_' . $name . '"';
        if ($accept) $html .= ' accept="' . htmlspecialchars($accept) . '"';
        if (!empty($field['required'])) $html .= ' required';
        $html .= ' class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">' . "\n";
        if ($value) {
            if (strpos($accept, 'image') !== false) {
                $html .= '<div class="mt-2"><img src="' . htmlspecialchars((string)$value) . '" class="max-w-[150px] rounded-lg border"></div>' . "\n";
            } else {
                $html .= '<p class="mt-1 text-xs text-gray-500">فایل فعلی: ' . htmlspecialchars((string)$value) . '</p>' . "\n";
            }
        }
        return $html;
    }

    // ═══════════════════════════════════════════════════════════
    // JSON
    // ═══════════════════════════════════════════════════════════
    private function renderJson(array $field, $value): string {
        $v = '';
        if ($value) {
            if (is_string($value)) $v = $value;
            elseif (is_array($value)) $v = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return '<textarea rows="6"'
             . $this->baseAttrs($field)
             . ' class="' . $this->inputClasses() . ' font-mono text-xs" dir="ltr">'
             . htmlspecialchars($v)
             . '</textarea>' . "\n";
    }

    // ═══════════════════════════════════════════════════════════
    // تشخیص ستون نمایش FK
    // ═══════════════════════════════════════════════════════════
    private function detectDisplayColumn(string $table): string {
        try {
            $cols = $this->tb->getColumns($table);
            $names = array_map(fn($c) => strtolower($c['Field']), $cols);
            foreach (['name', 'title', 'label', 'subject'] as $candidate) {
                if (in_array($candidate, $names, true)) return $candidate;
            }
            foreach ($cols as $c) {
                if (strpos(strtolower($c['Type']), 'varchar') !== false) return $c['Field'];
            }
        } catch (Throwable $e) {}
        return 'id';
    }
}
