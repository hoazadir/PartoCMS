<?php
/**
 * PartoCMS — Portfolio Module
 * Domain Entity: PortfolioItem
 */

declare(strict_types=1);

namespace PartoCMS\Portfolio\Domain\Entity;

use InvalidArgumentException;

final class PortfolioItem
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';
    public const VALID_STATUSES   = [self::STATUS_PUBLISHED, self::STATUS_HIDDEN];

    private ?int    $id;
    private string  $title;
    private ?string $slug;
    private ?string $clientName;
    private ?string $description;
    private ?string $content;
    private ?string $thumbnailUrl;
    private array   $gallery;
    private ?string $category;
    private array   $tags;
    private ?string $projectDate;
    private ?string $projectUrl;
    private string  $status;
    private bool    $isFeatured;
    private int     $sortOrder;
    private string  $language;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int    $id,
        string  $title,
        ?string $slug = null,
        ?string $clientName = null,
        ?string $description = null,
        ?string $content = null,
        ?string $thumbnailUrl = null,
        array   $gallery = [],
        ?string $category = null,
        array   $tags = [],
        ?string $projectDate = null,
        ?string $projectUrl = null,
        string  $status = self::STATUS_PUBLISHED,
        bool    $isFeatured = false,
        int     $sortOrder = 0,
        string  $language = 'fa-IR',
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->setTitle($title);
        $this->setStatus($status);
        $this->setLanguage($language);

        $this->id           = $id;
        $this->slug         = $slug;
        $this->clientName   = $clientName;
        $this->description  = $description;
        $this->content      = $content;
        $this->thumbnailUrl = $thumbnailUrl;
        $this->gallery      = $gallery;
        $this->category     = $category;
        $this->tags         = $tags;
        $this->projectDate  = $projectDate;
        $this->projectUrl   = $projectUrl;
        $this->isFeatured   = $isFeatured;
        $this->sortOrder    = $sortOrder;
        $this->createdAt    = $createdAt;
        $this->updatedAt    = $updatedAt;
    }

    public static function fromArray(array $row): self
    {
        $gallery = [];
        if (!empty($row['gallery'])) {
            $decoded = json_decode((string) $row['gallery'], true);
            $gallery = is_array($decoded) ? $decoded : [];
        }

        $tags = [];
        if (!empty($row['tags'])) {
            $decoded = json_decode((string) $row['tags'], true);
            $tags = is_array($decoded) ? $decoded : [];
        }

        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['title'] ?? ''),
            $row['slug']          ?? null,
            $row['client_name']   ?? null,
            $row['description']   ?? null,
            $row['content']       ?? null,
            $row['thumbnail_url'] ?? null,
            $gallery,
            $row['category']      ?? null,
            $tags,
            $row['project_date']  ?? null,
            $row['project_url']   ?? null,
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            (bool) ($row['is_featured'] ?? false),
            (int) ($row['sort_order'] ?? 0),
            (string) ($row['language'] ?? 'fa-IR'),
            $row['created_at']    ?? null,
            $row['updated_at']    ?? null
        );
    }

    public function toArray(bool $includeId = false): array
    {
        $data = [
            'title'         => $this->title,
            'slug'          => $this->slug,
            'client_name'   => $this->clientName,
            'description'   => $this->description,
            'content'       => $this->content,
            'thumbnail_url' => $this->thumbnailUrl,
            'gallery'       => !empty($this->gallery) ? json_encode($this->gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'category'      => $this->category,
            'tags'          => !empty($this->tags) ? json_encode($this->tags, JSON_UNESCAPED_UNICODE) : null,
            'project_date'  => $this->projectDate,
            'project_url'   => $this->projectUrl,
            'status'        => $this->status,
            'is_featured'   => $this->isFeatured ? 1 : 0,
            'sort_order'    => $this->sortOrder,
            'language'      => $this->language,
        ];

        if ($includeId && $this->id !== null) {
            $data['id'] = $this->id;
        }

        return $data;
    }

    // Getters
    public function getId(): ?int             { return $this->id; }
    public function getTitle(): string        { return $this->title; }
    public function getSlug(): ?string        { return $this->slug; }
    public function getClientName(): ?string  { return $this->clientName; }
    public function getDescription(): ?string { return $this->description; }
    public function getContent(): ?string     { return $this->content; }
    public function getThumbnailUrl(): ?string{ return $this->thumbnailUrl; }
    public function getGallery(): array       { return $this->gallery; }
    public function getCategory(): ?string    { return $this->category; }
    public function getTags(): array          { return $this->tags; }
    public function getProjectDate(): ?string { return $this->projectDate; }
    public function getProjectUrl(): ?string  { return $this->projectUrl; }
    public function getStatus(): string       { return $this->status; }
    public function isFeatured(): bool        { return $this->isFeatured; }
    public function getSortOrder(): int       { return $this->sortOrder; }
    public function getLanguage(): string     { return $this->language; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }
    public function getUpdatedAt(): ?string   { return $this->updatedAt; }

    // Setters
    public function setTitle(string $title): void
    {
        $title = trim($title);
        if ($title === '') throw new InvalidArgumentException('عنوان نمی‌تواند خالی باشد');
        if (mb_strlen($title) > 200) throw new InvalidArgumentException('عنوان حداکثر ۲۰۰ کاراکتر');
        $this->title = $title;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('وضعیت نامعتبر: ' . $status);
        }
        $this->status = $status;
    }

    public function setLanguage(string $language): void
    {
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $language)) {
            throw new InvalidArgumentException('کد زبان نامعتبر');
        }
        $this->language = $language;
    }

    public function setSlug(?string $slug): void            { $this->slug = $slug; }
    public function setClientName(?string $n): void         { $this->clientName = $n; }
    public function setDescription(?string $d): void        { $this->description = $d; }
    public function setContent(?string $c): void            { $this->content = $c; }
    public function setThumbnailUrl(?string $u): void       { $this->thumbnailUrl = $u; }
    public function setGallery(array $g): void              { $this->gallery = $g; }
    public function setCategory(?string $c): void           { $this->category = $c; }
    public function setTags(array $t): void                 { $this->tags = $t; }
    public function setProjectDate(?string $d): void        { $this->projectDate = $d; }
    public function setProjectUrl(?string $u): void         { $this->projectUrl = $u; }
    public function setSortOrder(int $s): void              { $this->sortOrder = $s; }

    public function publish(): void         { $this->status = self::STATUS_PUBLISHED; }
    public function hide(): void            { $this->status = self::STATUS_HIDDEN; }
    public function toggleFeatured(): void  { $this->isFeatured = !$this->isFeatured; }
    public function isPublished(): bool     { return $this->status === self::STATUS_PUBLISHED; }
    public function isHidden(): bool        { return $this->status === self::STATUS_HIDDEN; }
    public function isNew(): bool           { return $this->id === null; }
}
