<?php
/**
 * PartoCMS - FAQ Repository Interface
 *
 * قرارداد ذخیره‌سازی و بازیابی سوالات متداول
 *
 * @package PartoCMS\Faq\Domain\Repository
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Domain\Repository;

use PartoCMS\Faq\Domain\Entity\FaqItem;

interface FaqRepositoryInterface
{
    /**
     * واکشی یک سوال با شناسه
     */
    public function findById(int $id): ?FaqItem;

    /**
     * ذخیره (ایجاد یا ویرایش) یک سوال
     */
    public function save(FaqItem $item): FaqItem;

    /**
     * حذف یک سوال
     */
    public function delete(FaqItem $item): void;

    /**
     * دریافت لیست تمام سوالات (با قابلیت فیلتر)
     *
     * @param array $filters Supported keys: is_active, category, search
     * @return FaqItem[]
     */
    public function findAll(array $filters = []): array;

    /**
     * دریافت لیست سوالات به صورت صفحه‌بندی شده
     *
     * @param int $page 1-based
     * @param int $perPage
     * @param array $filters Supported keys: is_active, category, search
     * @return array{items: FaqItem[], total: int, pages: int, current_page: int}
     */
    public function findPaginated(int $page, int $perPage, array $filters = []): array;

    /**
     * شمارش کل سوالات (با قابلیت فیلتر)
     */
    public function count(array $filters = []): int;
}
