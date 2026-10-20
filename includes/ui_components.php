<?php
/**
 * PartoCMS — UI Components
 * توابع کمکی برای رندر کامپوننت‌های مشترک در پنل ادمین
 */

if (!function_exists('render_create_page_button')) {
    /**
     * رندر دکمه «ایجاد صفحه جدید در ویرایشگر»
     * 
     * @param string $text متن دکمه
     * @param string $url مسیر ویرایشگر
     * @param bool $show_help نمایش متن راهنما زیر دکمه
     * @return string
     */
    function render_create_page_button(string $text = '➕ ایجاد صفحه جدید در ویرایشگر', string $url = '/admin/editor.php', bool $show_help = true): string {
        $html = '<div style="margin-top: 10px;">';
        $html .= '<a href="' . htmlspecialchars($url) . '" target="_blank" style="display:inline-flex; align-items:center; gap:5px; padding:8px 15px; background:#6d28d9; color:#fff; border-radius:6px; text-decoration:none; font-size:13px; font-weight:bold;">';
        $html .= htmlspecialchars($text);
        $html .= '</a>';
        
        if ($show_help) {
            $html .= '<span style="display:block; margin-top:5px; font-size:11px; color:#888;">پس از ساخت صفحه، به این صفحه بازگردید تا صفحه جدید در لیست بالا ظاهر شود.</span>';
        }
        
        $html .= '</div>';
        return $html;
    }
}
