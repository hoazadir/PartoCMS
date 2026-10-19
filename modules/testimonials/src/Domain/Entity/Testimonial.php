<?php
/**
 * PartoCMS — Testimonials Module
 * Domain Entity: Testimonial
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Domain\Entity;

use InvalidArgumentException;

final class Testimonial
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED  = 'rejected';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PUBLISHED,
        self::STATUS_REJECTED,
    ];

    public const MIN_RATING = 1;
    public const MAX_RATING = 5;

    private ?int    $id;
    private string  $name;
    private ?string $email;
    private ?string $company;
    private ?string $position;
    private string  $content;
    private ?int    $rating;
    private ?string $avatarUrl;
    private string  $status;
    private bool    $isFeatured;
    private int     $sortOrder;
    private string  $language;
    private ?string $submittedIp;
    private ?string $createdAt;
    private ?string $updatedAt;
    private ?string $publishedAt;

    public function __construct(
        ?int    $id,
        string  $name,
        ?string $email,
        ?string $company,
        ?string $position,
        string  $content,
        ?int    $rating,
        ?string $avatarUrl,
        string  $status,
        bool    $isFeatured,
        int     $sortOrder,
        string  $language,
        ?string $submittedIp = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $publishedAt = null
    ) {
        $this->setName($name);
        $this->setContent($content);
        $this->setRating($rating);
        $this->setStatus($status);
        $this->setLanguage($language);
        $this->setEmail($email);
        $this->setAvatarUrl($avatarUrl);

        $this->id          = $id;
        $this->company     = $company;
        $this->position    = $position;
        $this->isFeatured  = $isFeatured;
        $this->sortOrder   = $sortOrder;
        $this->submittedIp = $submittedIp;
        $this->createdAt   = $createdAt;
        $this->updatedAt   = $updatedAt;
        $this->publishedAt = $publishedAt;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['name'] ?? ''),
            $row['email']       ?? null,
            $row['company']     ?? null,
            $row['position']    ?? null,
            (string) ($row['content'] ?? ''),
            isset($row['rating']) ? (int) $row['rating'] : null,
            $row['avatar_url']  ?? null,
            (string) ($row['status'] ?? self::STATUS_PENDING),
            (bool) ($row['is_featured'] ?? false),
            (int) ($row['sort_order'] ?? 0),
            (string) ($row['language'] ?? 'fa-IR'),
            $row['submitted_ip'] ?? null,
            $row['created_at']   ?? null,
            $row['updated_at']   ?? null,
            $row['published_at'] ?? null
        );
    }

    public function toArray(bool $includeId = false): array
    {
        $data = [
            'name'         => $this->name,
            'email'        => $this->email,
            'company'      => $this->company,
            'position'     => $this->position,
            'content'      => $this->content,
            'rating'       => $this->rating,
            'avatar_url'   => $this->avatarUrl,
            'status'       => $this->status,
            'is_featured'  => $this->isFeatured ? 1 : 0,
            'sort_order'   => $this->sortOrder,
            'language'     => $this->language,
            'submitted_ip' => $this->submittedIp,
        ];

        if ($includeId && $this->id !== null) {
            $data['id'] = $this->id;
        }

        return $data;
    }

    public function getId(): ?int             { return $this->id; }
    public function getName(): string         { return $this->name; }
    public function getEmail(): ?string       { return $this->email; }
    public function getCompany(): ?string     { return $this->company; }
    public function getPosition(): ?string    { return $this->position; }
    public function getContent(): string      { return $this->content; }
    public function getRating(): ?int         { return $this->rating; }
    public function getAvatarUrl(): ?string   { return $this->avatarUrl; }
    public function getStatus(): string       { return $this->status; }
    public function isFeatured(): bool        { return $this->isFeatured; }
    public function getSortOrder(): int       { return $this->sortOrder; }
    public function getLanguage(): string     { return $this->language; }
    public function getSubmittedIp(): ?string { return $this->submittedIp; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }
    public function getUpdatedAt(): ?string   { return $this->updatedAt; }
    public function getPublishedAt(): ?string { return $this->publishedAt; }

    public function setName(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException('نام نمی‌تواند خالی باشد');
        }
        if (mb_strlen($name) > 100) {
            throw new InvalidArgumentException('نام حداکثر ۱۰۰ کاراکتر');
        }
        $this->name = $name;
    }

    public function setContent(string $content): void
    {
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('متن نظر نمی‌تواند خالی باشد');
        }
        $this->content = $content;
    }

    public function setRating(?int $rating): void
    {
        if ($rating !== null && ($rating < self::MIN_RATING || $rating > self::MAX_RATING)) {
            throw new InvalidArgumentException(
                sprintf('امتیاز باید بین %d و %d باشد', self::MIN_RATING, self::MAX_RATING)
            );
        }
        $this->rating = $rating;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('وضعیت نامعتبر است: ' . $status);
        }
        $this->status = $status;
    }

    public function setLanguage(string $language): void
    {
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $language)) {
            throw new InvalidArgumentException('کد زبان نامعتبر است: ' . $language);
        }
        $this->language = $language;
    }

    public function setEmail(?string $email): void
    {
        if ($email !== null && $email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('ایمیل نامعتبر است');
            }
            $this->email = $email;
            return;
        }
        $this->email = null;
    }

    public function setAvatarUrl(?string $url): void
    {
        if ($url !== null && $url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('URL آواتار نامعتبر است');
            }
            if (mb_strlen($url) > 255) {
                throw new InvalidArgumentException('URL آواتار حداکثر ۲۵۵ کاراکتر');
            }
            $this->avatarUrl = $url;
            return;
        }
        $this->avatarUrl = null;
    }

    public function setCompany(?string $company): void
    {
        $this->company = $company !== null ? trim($company) : null;
    }

    public function setPosition(?string $position): void
    {
        $this->position = $position !== null ? trim($position) : null;
    }

    public function setSortOrder(int $order): void
    {
        $this->sortOrder = $order;
    }

    public function setSubmittedIp(?string $ip): void
    {
        $this->submittedIp = $ip;
    }

    public function publish(): void
    {
        $this->status = self::STATUS_PUBLISHED;
    }

    public function reject(): void
    {
        $this->status = self::STATUS_REJECTED;
    }

    public function markAsPending(): void
    {
        $this->status = self::STATUS_PENDING;
    }

    public function toggleFeatured(): void
    {
        $this->isFeatured = !$this->isFeatured;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function hasRating(): bool
    {
        return $this->rating !== null;
    }

    public function isNew(): bool
    {
        return $this->id === null;
    }
}
