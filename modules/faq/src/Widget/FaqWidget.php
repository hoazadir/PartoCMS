<?php
/**
 * PartoCMS - FAQ Widget Renderer
 * رندر کردن سوالات متداول در قالب آکاردئون
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Widget;

use PartoCMS\Faq\Domain\Entity\FaqItem;

final class FaqWidget
{
    /**
     * رندر لیست سوالات به صورت آکاردئون
     *
     * @param FaqItem[] $items
     */
    public static function renderAccordion(array $items): string
    {
        if (empty($items)) {
            return ''; // اگر سوالی نبود، چیزی نمایش نده
        }

        // شناسه یکتا برای جلوگیری از تداخل اگر چند بار در یک صفحه استفاده شود
        $accordionId = 'faqAccordion_' . substr(md5(uniqid('', true)), 0, 8);

        $html = '<div class="accordion faq-accordion" id="' . $accordionId . '" style="margin: 20px 0;">';

        foreach ($items as $index => $item) {
            $itemId = $accordionId . '_item_' . $index;
            $isFirst = ($index === 0);
            $showClass = $isFirst ? 'show' : '';
            $collapsedClass = $isFirst ? '' : 'collapsed';
            $ariaExpanded = $isFirst ? 'true' : 'false';

            // سوال
            $html .= '<div class="accordion-item" style="margin-bottom: 10px; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">';
            $html .= '<h2 class="accordion-header" id="heading_' . $itemId . '">';
            $html .= '<button class="accordion-button ' . $collapsedClass . '" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_' . $itemId . '" aria-expanded="' . $ariaExpanded . '" aria-controls="collapse_' . $itemId . '" style="font-weight: bold; font-family: inherit; background-color: #f8fafc; color: #1e293b; box-shadow: none;">';
            $html .= htmlspecialchars($item->question());
            $html .= '</button>';
            $html .= '</h2>';

            // پاسخ
            $html .= '<div id="collapse_' . $itemId . '" class="accordion-collapse collapse ' . $showClass . '" aria-labelledby="heading_' . $itemId . '" data-bs-parent="#' . $accordionId . '">';
            $html .= '<div class="accordion-body" style="font-family: inherit; color: #475569; line-height: 1.8; background-color: #fff;">';
            // پاسخ ممکن است HTML باشد (چون از TinyMCE ذخیره شده)
            $html .= $item->answer(); 
            $html .= '</div>';
            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }
}
