<?php
/**
 * PartoCMS - Invoice Helper
 * توابع کمکی برای فاکتور: اعداد فارسی، تاریخ شمسی
 *
 * @author Hooman Oliaei
 * @version 1.0.0
 */

class InvoiceHelper
{
    /**
     * تبدیل اعداد لاتین به فارسی
     */
    public static function toPersianDigits(string $text): string
    {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin   = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($latin, $persian, $text);
    }

    /**
     * تبدیل اعداد لاتین به عربی
     */
    public static function toArabicDigits(string $text): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin  = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        return str_replace($latin, $arabic, $text);
    }

    /**
     * تبدیل تاریخ میلادی به شمسی (بدون نیاز به intl)
     */
    public static function toJalali(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return $datetime;

        $gy = (int) date('Y', $timestamp);
        $gm = (int) date('n', $timestamp);
        $gd = (int) date('j', $timestamp);
        $time = date('H:i', $timestamp);

        list($jy, $jm, $jd) = self::gregorianToJalali($gy, $gm, $gd);
        return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time);
    }

    /**
     * تبدیل تاریخ میلادی به هجری قمری (تقریبی)
     */
    public static function toHijri(string $datetime): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return $datetime;

        $gy = (int) date('Y', $timestamp);
        $gm = (int) date('n', $timestamp);
        $gd = (int) date('j', $timestamp);
        $time = date('H:i', $timestamp);

        // الگوریتم تبدیل میلادی به هجری قمری
        $jd = self::gregorianToJD($gy, $gm, $gd);
        $hijri = self::jdToHijri($jd);

        return sprintf('%04d/%02d/%02d %s', $hijri[0], $hijri[1], $hijri[2], $time);
    }

    /**
     * تبدیل میلادی به شمسی — الگوریتم دقیق
     */
    private static function gregorianToJalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + ((int) (($gy2 + 3) / 4)) - ((int) (($gy2 + 99) / 100))
              + ((int) (($gy2 + 399) / 400)) + $gd + $g_d_m[$gm - 1];

        $jy = -1595 + (33 * ((int) ($days / 12053)));
        $days %= 12053;

        $jy += 4 * ((int) ($days / 1461));
        $days %= 1461;

        if ($days > 365) {
            $jy += (int) (($days - 1) / 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + (int) ($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int) (($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * تبدیل میلادی به Julian Day Number
     */
    private static function gregorianToJD(int $year, int $month, int $day): int
    {
        if ($month <= 2) {
            $year -= 1;
            $month += 12;
        }
        $a = (int) ($year / 100);
        $b = 2 - $a + (int) ($a / 4);

        return (int) ((365.25 * ($year + 4716)) + (30.6001 * ($month + 1)) + $day + $b - 1524.5);
    }

    /**
     * تبدیل Julian Day Number به هجری قمری
     */
    private static function jdToHijri(int $jd): array
    {
        $l = $jd - 1948440 + 10632;
        $n = (int) (($l - 1) / 10631);
        $l = $l - 10631 * $n + 354;

        $j = ((int) ((10985 - $l) / 5316)) * ((int) ((50 * $l) / 17719))
           + ((int) ($l / 5670)) * ((int) ((43 * $l) / 15238));
        $l = $l - ((int) ((30 - $j) / 15)) * ((int) ((17719 * $j) / 50))
           - ((int) ($j / 16)) * ((int) ((15238 * $j) / 43)) + 29;

        $month = (int) ((24 * $l) / 709);
        $day = $l - (int) ((709 * $month) / 24);
        $year = 30 * $n + $j - 30;

        return [$year, $month, $day];
    }

    /**
     * قالب‌بندی مبلغ بر اساس زبان
     * - fa: اعداد فارسی + جداکننده فارسی
     * - ar: اعداد عربی
     * - en: اعداد لاتین
     */
    public static function formatPrice(float $amount, string $lang = 'fa', string $currency = 'تومان'): string
    {
        $formatted = number_format($amount, 0, '.', ',');

        switch ($lang) {
            case 'fa':
            case 'fa-IR':
                $formatted = self::toPersianDigits($formatted);
                return $formatted . ' ' . $currency;

            case 'ar':
            case 'ar-SA':
                $formatted = self::toArabicDigits($formatted);
                return $formatted . ' ' . $currency;

            default:
                return $formatted . ' ' . $currency;
        }
    }

    /**
     * قالب‌بندی شماره سفارش — فقط اعداد به فارسی/عربی
     * حروف انگلیسی (TEST, ORD) دست‌نخورده می‌مانند
     */
    public static function formatOrderNumber(string $number, string $lang = 'fa'): string
    {
        switch ($lang) {
            case 'fa':
            case 'fa-IR':
                return self::toPersianDigits($number);
            case 'ar':
            case 'ar-SA':
                return self::toArabicDigits($number);
            default:
                return $number;
        }
    }

    /**
     * قالب‌بندی تاریخ بر اساس زبان
     * - fa: شمسی + اعداد فارسی
     * - ar: هجری قمری + اعداد عربی
     * - en: میلادی + اعداد لاتین
     */
    public static function formatDate(string $datetime, string $lang = 'fa'): string
    {
        $timestamp = strtotime($datetime);
        if (!$timestamp) return $datetime;

        switch ($lang) {
            case 'fa':
            case 'fa-IR':
                $jalali = self::toJalali($datetime);
                return self::toPersianDigits($jalali);

            case 'ar':
            case 'ar-SA':
                $hijri = self::toHijri($datetime);
                return self::toArabicDigits($hijri);

            default:
                return date('Y/m/d H:i', $timestamp);
        }
    }
}
