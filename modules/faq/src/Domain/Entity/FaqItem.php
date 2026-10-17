<?php
/**
 * PartoCMS - FAQ Entity
 *
 * موجودیت سوال متداول (Pure Domain)
 *
 * @package PartoCMS\Faq\Domain\Entity
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Domain\Entity;

use DateTimeImmutable;

final class FaqItem
{
    private ?int $id;
    private string $question;
    private string $answer;
    private ?string $category;
    private int $sortOrder;
    private bool $isActive;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        string $question,
        string $answer,
        ?string $category = null,
        int $sortOrder = 0,
        bool $isActive = true,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id        = $id;
        $this->question  = $question;
        $this->answer    = $answer;
        $this->category  = $category;
        $this->sortOrder = $sortOrder;
        $this->isActive  = $isActive;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
    }

    /**
     * ساخت Entity از ردیف دیتابیس
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id:        isset($row['id']) ? (int) $row['id'] : null,
            question:  (string) $row['question'],
            answer:    (string) $row['answer'],
            category:  !empty($row['category']) ? (string) $row['category'] : null,
            sortOrder: (int) ($row['sort_order'] ?? 0),
            isActive:  (bool) ($row['is_active'] ?? true),
            createdAt: !empty($row['created_at']) ? new DateTimeImmutable($row['created_at']) : null,
            updatedAt: !empty($row['updated_at']) ? new DateTimeImmutable($row['updated_at']) : null
        );
    }

    /**
     * تبدیل Entity به آرایه برای ذخیره در دیتابیس
     */
    public function toArray(): array
    {
        return [
            'question'   => $this->question,
            'answer'     => $this->answer,
            'category'   => $this->category,
            'sort_order' => $this->sortOrder,
            'is_active'  => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * به‌روزرسانی محتوا (Use Case)
     */
    public function updateContent(string $question, string $answer, ?string $category, int $sortOrder): void
    {
        $this->question  = $question;
        $this->answer    = $answer;
        $this->category  = $category;
        $this->sortOrder = $sortOrder;
        $this->touch();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->touch();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    // ─── Getters ───

    public function id(): ?int { return $this->id; }
    public function question(): string { return $this->question; }
    public function answer(): string { return $this->answer; }
    public function category(): ?string { return $this->category; }
    public function sortOrder(): int { return $this->sortOrder; }
    public function isActive(): bool { return $this->isActive; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    
    public function isNew(): bool { return $this->id === null; }
}
