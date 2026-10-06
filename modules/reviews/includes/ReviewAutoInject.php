<?php
/**
 * PartoCMS - Reviews - Auto Inject
 * رندر خودکار نظرات در entityهای فعال
 */

require_once __DIR__ . '/ReviewManager.php';
require_once __DIR__ . '/ReviewWidget.php';

class ReviewAutoInject
{
    private static ?ReviewManager $manager = null;

    private static function init(): void
    {
        if (self::$manager === null && function_exists('getDB')) {
            self::$manager = new ReviewManager(getDB());
        }
    }

    /**
     * رندر خودکار در صورتی که entity در تنظیمات فعال باشد
     */
    public static function maybeRender(string $entityType, int $entityId): string
    {
        try {
            self::init();
            if (self::$manager === null) return '';

            $settings = self::$manager->getSettings('global');

            // چک فعال بودن کل سیستم
            if (empty($settings['enabled'])) return '';

            // چک فعال بودن برای این entity
            $key = 'enable_on_' . $entityType;
            if (empty($settings[$key])) return '';

            // چک isValidEntity
            if (!self::$manager->isValidEntity($entityType, $entityId)) return '';

            // چک حداقل یک بخش فعال
            $showSummary = !empty($settings['show_summary']);
            $showForm    = !empty($settings['show_form']);
            $showList    = !empty($settings['show_list']);

            if (!$showSummary && !$showForm && !$showList) return '';

            return ReviewWidget::render($entityType, $entityId, [
                'show_summary' => $showSummary,
                'show_form'    => $showForm,
                'show_list'    => $showList,
            ]);
        } catch (Throwable $e) {
            error_log('ReviewAutoInject::maybeRender: ' . $e->getMessage());
            return '';
        }
    }
}
