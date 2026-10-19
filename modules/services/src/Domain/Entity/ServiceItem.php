<?php
/**
 * PartoCMS — Services Module
 * Domain Entity: ServiceItem
 */

declare(strict_types=1);

namespace PartoCMS\Services\Domain\Entity;

use InvalidArgumentException;

final class ServiceItem
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';
    public const VALID_STATUSES   = [self::STATUS_PUBLISHED, self::STATUS_HIDDEN];

    private ?int    $id;
    private string  $title;
    private ?string $slug;
    private ?string $description;
    private ?string $content;
    private ?string $icon;
    private ?string $imageUrl;
    private ?float  $price;
    private ?string $priceUnit;
    private array   $features;
    private ?string $category;
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
        ?string $description = null,
        ?string $content = null,
        ?string $icon = null,
        ?string $imageUrl = null,
        ?float  $price = null,
        ?string $priceUnit = null,
        array   $features = [],
        ?string $category = null,
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
        $this->setPrice($price);

        $this->id          = $id;
        $this->slug        = $slug;
        $this->description = $description;
        $this->content     = $content;
        $this->icon        = $icon;
        $this->imageUrl    = $imageUrl;
        $this->priceUnit   = $priceUnit;
        $this->features    = $features;
        $this->category    = $category;
        $this->isFeatured  = $isFeatured;
        $this->sortOrder   = $sortOrder;
        $this->createdAt   = $createdAt;
        $this->updatedAt   = $updatedAt;
    }

    public static function fromArray(array $row): self
    {
        $features = [];
        if (!empty($row['features'])) {
            $decoded = json_decode((string) $row['features'], true);
            $features = is_array($decoded) ? $decoded : [];
        }

        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['title'] ?? ''),
            $row['slug']        ?? null,
            $row['description'] ?? null,
            $row['content']     ?? null,
            $row['icon']        ?? null,
            $row['image_url']   ?? null,
            isset($row['price']) && $row['price'] !== null ? (float) $row['price'] : null,
            $row['price_unit']  ?? null,
            $features,
            $row['category']    ?? null,
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            (bool) ($row['is_featured'] ?? false),
            (int) ($row['sort_order'] ?? 0),
            (string) ($row['language'] ?? 'fa-IR'),
            $row['created_at']  ?? null,
            $row['updated_at']  ?? null
        );
    }

    public function toArray(bool $includeId = false): array
    {
        $data = [
            'title'       => $this->title,
            'slug'        => $this->slug,
            'description' => $this->description,
            'content'     => $this->content,
            'icon'        => $this->icon,
            'image_url'   => $this->imageUrl,
            'price'       => $this->price,
            'price_unit'  => $this->priceUnit,
            'features'    => !empty($this->features) ? json_encode($this->features, JSON_UNESCAPED_UNICODE) : null,
            'category'    => $this->category,
            'status'      => $this->status,
            'is_featured' => $this->isFeatured ? 1 : 0,
            'sort_order'  => $this->sortOrder,
            'language'    => $this->language,
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
    public function getDescription(): ?string { return $this->description; }
    public function getContent(): ?string     { return $this->content; }
    public function getIcon(): ?string        { return $this->icon; }
    public function getImageUrl(): ?string    { return $this->imageUrl; }
    public function getPrice(): ?float        { return $this->price; }
    public function getPriceUnit(): ?string   { return $this->priceUnit; }
    public function getFeatures(): array      { return $this->features; }
    public function getCategory(): ?string    { return $this->category; }
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
        if (mb_strlen($title) > 150) throw new InvalidArgumentException('عنوان حداکثر ۱۵۰ کاراکتر');
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

    public function setPrice(?float $price): void
    {
        if ($price !== null && $price < 0) {
            throw new InvalidArgumentException('قیمت نمی‌تواند منفی باشد');
        }
        $this->price = $price;
    }

    public function setSlug(?string $slug): void       { $this->slug = $slug; }
    public function setDescription(?string $d): void   { $this->description = $d; }
    public function setContent(?string $c): void       { $this->content = $c; }
    public function setIcon(?string $i): void          { $this->icon = $i; }
    public function setImageUrl(?string $u): void      { $this->imageUrl = $u; }
    public function setPriceUnit(?string $u): void     { $this->priceUnit = $u; }
    public function setFeatures(array $f): void        { $this->features = $f; }
    public function setCategory(?string $c): void      { $this->category = $c; }
    public function setSortOrder(int $s): void         { $this->sortOrder = $s; }

    public function publish(): void         { $this->status = self::STATUS_PUBLISHED; }
    public function hide(): void            { $this->status = self::STATUS_HIDDEN; }
    public function toggleFeatured(): void  { $this->isFeatured = !$this->isFeatured; }
    public function isPublished(): bool     { return $this->status === self::STATUS_PUBLISHED; }
    public function isHidden(): bool        { return $this->status === self::STATUS_HIDDEN; }
    public function isNew(): bool           { return $this->id === null; }
    public function hasPrice(): bool        { return $this->price !== null; }
}
