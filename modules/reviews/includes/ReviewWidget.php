<?php
/**
 * PartoCMS - Reviews - ReviewWidget
 */
require_once __DIR__ . '/ReviewManager.php';
require_once __DIR__ . '/ReviewRenderer.php';

class ReviewWidget
{
    private static ?ReviewManager $manager = null;
    private static ?ReviewRenderer $renderer = null;

    private static function init(): void
    {
        if (self::$manager !== null && self::$renderer !== null) return;
        if (!function_exists('getDB')) throw new RuntimeException('no getDB');
        $pdo = getDB();
        self::$manager  = new ReviewManager($pdo);
        self::$renderer = new ReviewRenderer(self::$manager);
    }

    public static function render(string $t, int $id, array $o = []): string
    {
        try {
            self::init();
            $o = array_merge(['show_summary'=>true,'show_form'=>true,'show_list'=>true,'class'=>'reviews-widget'], $o);
            $s = self::$manager->getSettings($t);
            if (empty($s['enabled'])) return '';
            if (!self::$manager->isValidEntity($t, $id)) return '';
            $h  = '<div class="' . htmlspecialchars($o['class']) . '" data-entity-type="' . htmlspecialchars($t) . '" data-entity-id="' . $id . '">';
            if ($o['show_summary']) $h .= self::$renderer->renderSummary($t, $id);
            if ($o['show_form'])    $h .= self::$renderer->renderForm($t, $id, $o);
            if ($o['show_list'])    $h .= self::$renderer->renderList($t, $id, $o);
            return $h . '</div>';
        } catch (Throwable $e) { error_log('RW render: ' . $e->getMessage()); return ''; }
    }

    public static function summary(string $t, int $id): string
    {
        try { self::init(); return self::$renderer->renderSummary($t, $id); }
        catch (Throwable $e) { error_log('RW summary: ' . $e->getMessage()); return ''; }
    }

    public static function form(string $t, int $id, array $o = []): string
    {
        try { self::init(); return self::$renderer->renderForm($t, $id, $o); }
        catch (Throwable $e) { error_log('RW form: ' . $e->getMessage()); return ''; }
    }

    public static function list(string $t, int $id, array $o = []): string
    {
        try { self::init(); return self::$renderer->renderList($t, $id, $o); }
        catch (Throwable $e) { error_log('RW list: ' . $e->getMessage()); return ''; }
    }

    public static function rating(string $t, int $id): string
    {
        try {
            self::init();
            $avg = self::$manager->getAverageRating($t, $id);
            $st  = self::$manager->getStats($t, $id);
            $c   = $st['total'] ?? 0;
            $f   = (int) floor($avg);
            $h   = ($avg - $f) >= 0.5;
            $e   = 5 - $f - ($h ? 1 : 0);
            $r   = '<span class="review-rating-inline" title="' . number_format($avg,1) . '">';
            $r  .= str_repeat('⭐', max(0,$f));
            if ($h) $r .= '✨';
            $r  .= str_repeat('☆', max(0,$e));
            $r  .= ' <small>(' . (int)$c . ')</small></span>';
            return $r;
        } catch (Throwable $e) { error_log('RW rating: ' . $e->getMessage()); return ''; }
    }
}
