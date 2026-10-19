<?php
/**
 * PartoCMS — Testimonials Module
 * Config: defaults and constants
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

return [
    // ─────────────────────────────────────────────────────────
    // اطلاعات ماژول
    // ─────────────────────────────────────────────────────────
    'name'        => 'نظرات مشتریان',
    'slug'        => 'testimonials',
    'version'     => '1.0.0',
    'description' => 'سیستم مدیریت نظرات مشتریان با پشتیبانی از آواتار، امتیاز و فرم فرانت‌اند.',

    // ─────────────────────────────────────────────────────────
    // تنظیمات پیش‌فرض (در جدول settings با پیشوند testimonials_ ذخیره می‌شوند)
    // ─────────────────────────────────────────────────────────
    'defaults' => [
        'auto_approve'      => false,  // تأیید خودکار نظرات فرانت
        'require_email'     => false,  // ایمیل اجباری
        'items_per_page'    => 20,     // تعداد در صفحه admin
        'allow_guest'       => true,   // اجازه ثبت به مهمان
        'default_status'    => 'pending',
        'show_avatar'       => true,   // نمایش آواتار در ویجت
        'show_company'      => true,   // نمایش شرکت
        'show_position'     => true,   // نمایش سمت
        'show_rating'       => true,   // نمایش امتیاز ستاره‌ای
        'spam_cooldown'     => 5,      // دقیقه — فاصله بین دو نظر از یک IP
        'spam_daily_limit'  => 10,     // حداکثر نظر روزانه از یک IP
        'min_content_length'=> 10,     // حداقل طول متن
        'max_content_length'=> 5000,   // حداکثر طول متن
    ],

    // ─────────────────────────────────────────────────────────
    // تنظیمات ویجت (پیش‌فرض‌ها برای شورت‌کد)
    // ─────────────────────────────────────────────────────────
    'widget' => [
        'limit'    => 6,
        'style'    => 'grid',       // grid | slider | carousel
        'columns'  => 3,
        'show_rating' => true,
        'show_avatar' => true,
    ],

    // ─────────────────────────────────────────────────────────
    // مجوزها
    // ─────────────────────────────────────────────────────────
    'permissions' => [
        'testimonials.view',
        'testimonials.create',
        'testimonials.edit',
        'testimonials.delete',
        'testimonials.approve',
        'testimonials.settings',
    ],

    // ─────────────────────────────────────────────────────────
    // ترجمه‌های پیش‌فرض (اگر در DB نبود)
    // ─────────────────────────────────────────────────────────
    'i18n' => [
        'fa-IR' => [
            'title'         => 'نظرات مشتریان',
            'submit'        => 'ثبت نظر',
            'name'          => 'نام',
            'email'         => 'ایمیل',
            'company'       => 'شرکت',
            'position'      => 'سمت',
            'content'       => 'متن نظر',
            'rating'        => 'امتیاز',
            'submit_button' => 'ارسال نظر',
            'thanks'        => 'ممنون از نظر شما!',
            'pending_msg'   => 'نظر شما پس از تأیید نمایش داده می‌شود.',
        ],
        'en-US' => [
            'title'         => 'Testimonials',
            'submit'        => 'Submit Review',
            'name'          => 'Name',
            'email'         => 'Email',
            'company'       => 'Company',
            'position'      => 'Position',
            'content'       => 'Review',
            'rating'        => 'Rating',
            'submit_button' => 'Submit',
            'thanks'        => 'Thank you for your review!',
            'pending_msg'   => 'Your review will be visible after approval.',
        ],
    ],
];
