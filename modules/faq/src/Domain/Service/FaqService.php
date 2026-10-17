<?php
/**
 * PartoCMS - FAQ Service
 *
 * Application service for FAQ management (Use Cases).
 *
 * @package PartoCMS\Faq\Domain\Service
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Domain\Service;

use PartoCMS\Faq\Domain\Entity\FaqItem;
use PartoCMS\Faq\Domain\Exception\FaqException;
use PartoCMS\Faq\Domain\Repository\FaqRepositoryInterface;

final class FaqService
{
    private FaqRepositoryInterface $repo;

    public function __construct(FaqRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * ایجاد یک سوال جدید
     */
    public function create(
        string $question,
        string $answer,
        ?string $category = null,
        int $sortOrder = 0,
        bool $isActive = true
    ): FaqItem {
        $item = new FaqItem(
            id: null,
            question: trim($question),
            answer: trim($answer),
            category: $category ? trim($category) : null,
            sortOrder: $sortOrder,
            isActive: $isActive
        );

        return $this->repo->save($item);
    }

    /**
     * ویرایش یک سوال موجود
     */
    public function update(
        int $id,
        string $question,
        string $answer,
        ?string $category = null,
        int $sortOrder = 0
    ): FaqItem {
        $item = $this->findOrFail($id);

        $item->updateContent(
            question: trim($question),
            answer: trim($answer),
            category: $category ? trim($category) : null,
            sortOrder: $sortOrder
        );

        return $this->repo->save($item);
    }

    /**
     * تغییر وضعیت (فعال / غیرفعال)
     */
    public function toggleStatus(int $id): FaqItem
    {
        $item = $this->findOrFail($id);

        if ($item->isActive()) {
            $item->deactivate();
        } else {
            $item->activate();
        }

        return $this->repo->save($item);
    }

    /**
     * حذف یک سوال
     */
    public function delete(int $id): void
    {
        $item = $this->findOrFail($id);
        $this->repo->delete($item);
    }

    /**
     * دریافت یک سوال با شناسه
     */
    public function getById(int $id): FaqItem
    {
        return $this->findOrFail($id);
    }

    /**
     * لیست سوالات (صفحه‌بندی شده) برای پنل ادمین
     */
    public function getPaginated(int $page, int $perPage, array $filters = []): array
    {
        return $this->repo->findPaginated($page, $perPage, $filters);
    }

    /**
     * لیست تمام سوالات فعال (برای فرانت‌اند)
     *
     * @return FaqItem[]
     */
    public function getActiveList(?string $category = null): array
    {
        $filters = ['is_active' => true];
        if ($category) {
            $filters['category'] = $category;
        }
        return $this->repo->findAll($filters);
    }

    /**
     * دریافت لیست دسته‌بندی‌های موجود
     */
    public function getCategories(): array
    {
        $items = $this->repo->findAll();
        $categories = [];
        foreach ($items as $item) {
            if ($item->category() !== null && !in_array($item->category(), $categories, true)) {
                $categories[] = $item->category();
            }
        }
        sort($categories);
        return $categories;
    }

    private function findOrFail(int $id): FaqItem
    {
        $item = $this->repo->findById($id);
        if ($item === null) {
            throw FaqException::notFound($id);
        }
        return $item;
    }
}
