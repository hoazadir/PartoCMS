<?php
/**
 * PartoCMS — Properties Module
 * Domain Entity: Property
 */

declare(strict_types=1);

namespace PartoCMS\Properties\Domain\Entity;

use InvalidArgumentException;

final class Property
{
    // Status
    public const STATUS_PENDING   = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';
    public const STATUS_SOLD      = 'sold';
    public const STATUS_RENTED    = 'rented';
    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PUBLISHED,
        self::STATUS_HIDDEN,
        self::STATUS_SOLD,
        self::STATUS_RENTED,
    ];

    // Property Types
    public const VALID_PROPERTY_TYPES = [
        'apartment', 'villa', 'land', 'office', 'shop', 'warehouse',
    ];

    // Deal Types
    public const VALID_DEAL_TYPES = ['sale', 'rent', 'mortgage'];

    // === Fields ===
    private ?int    $id;
    private string  $title;
    private ?string $slug;
    private ?string $description;
    private ?string $content;
    private string  $propertyType;
    private string  $dealType;
    private ?int    $area;
    private ?int    $bedrooms;
    private ?int    $bathrooms;
    private ?int    $floor;
    private ?int    $totalFloors;
    private ?int    $yearBuilt;
    private ?string $documentType;
    private ?float  $price;
    private ?float  $rentPrice;
    private ?float  $deposit;
    private ?string $address;
    private ?string $city;
    private ?string $region;
    private ?float  $latitude;
    private ?float  $longitude;
    private ?string $thumbnailUrl;
    private array   $gallery;
    private array   $amenities;
    private ?int    $agentId;
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
        string  $title,
        ?string $slug = null,
        ?string $description = null,
        ?string $content = null,
        string  $propertyType = 'apartment',
        string  $dealType = 'sale',
        ?int    $area = null,
        ?int    $bedrooms = null,
        ?int    $bathrooms = null,
        ?int    $floor = null,
        ?int    $totalFloors = null,
        ?int    $yearBuilt = null,
        ?string $documentType = null,
        ?float  $price = null,
        ?float  $rentPrice = null,
        ?float  $deposit = null,
        ?string $address = null,
        ?string $city = null,
        ?string $region = null,
        ?float  $latitude = null,
        ?float  $longitude = null,
        ?string $thumbnailUrl = null,
        array   $gallery = [],
        array   $amenities = [],
        ?int    $agentId = null,
        string  $status = self::STATUS_PENDING,
        bool    $isFeatured = false,
        int     $sortOrder = 0,
        string  $language = 'fa-IR',
        ?string $submittedIp = null,
        ?string $createdAt = null,
        ?string $updatedAt = null,
        ?string $publishedAt = null
    ) {
        $this->setTitle($title);
        $this->setPropertyType($propertyType);
        $this->setDealType($dealType);
        $this->setStatus($status);
        $this->setLanguage($language);

        $this->id            = $id;
        $this->slug          = $slug;
        $this->description   = $description;
        $this->content       = $content;
        $this->area          = $area;
        $this->bedrooms      = $bedrooms;
        $this->bathrooms     = $bathrooms;
        $this->floor         = $floor;
        $this->totalFloors   = $totalFloors;
        $this->yearBuilt     = $yearBuilt;
        $this->documentType  = $documentType;
        $this->price         = $price;
        $this->rentPrice     = $rentPrice;
        $this->deposit       = $deposit;
        $this->address       = $address;
        $this->city          = $city;
        $this->region        = $region;
        $this->latitude      = $latitude;
        $this->longitude     = $longitude;
        $this->thumbnailUrl  = $thumbnailUrl;
        $this->gallery       = $gallery;
        $this->amenities     = $amenities;
        $this->agentId       = $agentId;
        $this->isFeatured    = $isFeatured;
        $this->sortOrder     = $sortOrder;
        $this->submittedIp   = $submittedIp;
        $this->createdAt     = $createdAt;
        $this->updatedAt     = $updatedAt;
        $this->publishedAt   = $publishedAt;
    }

    public static function fromArray(array $row): self
    {
        $gallery = [];
        if (!empty($row['gallery'])) {
            $d = json_decode((string) $row['gallery'], true);
            $gallery = is_array($d) ? $d : [];
        }
        $amenities = [];
        if (!empty($row['amenities'])) {
            $d = json_decode((string) $row['amenities'], true);
            $amenities = is_array($d) ? $d : [];
        }

        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['title'] ?? ''),
            $row['slug']            ?? null,
            $row['description']     ?? null,
            $row['content']         ?? null,
            (string) ($row['property_type'] ?? 'apartment'),
            (string) ($row['deal_type'] ?? 'sale'),
            isset($row['area']) && $row['area'] !== null ? (int) $row['area'] : null,
            isset($row['bedrooms']) && $row['bedrooms'] !== null ? (int) $row['bedrooms'] : null,
            isset($row['bathrooms']) && $row['bathrooms'] !== null ? (int) $row['bathrooms'] : null,
            isset($row['floor']) && $row['floor'] !== null ? (int) $row['floor'] : null,
            isset($row['total_floors']) && $row['total_floors'] !== null ? (int) $row['total_floors'] : null,
            isset($row['year_built']) && $row['year_built'] !== null ? (int) $row['year_built'] : null,
            $row['document_type']   ?? null,
            isset($row['price']) && $row['price'] !== null ? (float) $row['price'] : null,
            isset($row['rent_price']) && $row['rent_price'] !== null ? (float) $row['rent_price'] : null,
            isset($row['deposit']) && $row['deposit'] !== null ? (float) $row['deposit'] : null,
            $row['address']         ?? null,
            $row['city']            ?? null,
            $row['region']          ?? null,
            isset($row['latitude']) && $row['latitude'] !== null ? (float) $row['latitude'] : null,
            isset($row['longitude']) && $row['longitude'] !== null ? (float) $row['longitude'] : null,
            $row['thumbnail_url']   ?? null,
            $gallery,
            $amenities,
            isset($row['agent_id']) && $row['agent_id'] !== null ? (int) $row['agent_id'] : null,
            (string) ($row['status'] ?? self::STATUS_PENDING),
            (bool) ($row['is_featured'] ?? false),
            (int) ($row['sort_order'] ?? 0),
            (string) ($row['language'] ?? 'fa-IR'),
            $row['submitted_ip']    ?? null,
            $row['created_at']      ?? null,
            $row['updated_at']      ?? null,
            $row['published_at']    ?? null
        );
    }

    public function toArray(bool $includeId = false): array
    {
        $data = [
            'title'         => $this->title,
            'slug'          => $this->slug,
            'description'   => $this->description,
            'content'       => $this->content,
            'property_type' => $this->propertyType,
            'deal_type'     => $this->dealType,
            'area'          => $this->area,
            'bedrooms'      => $this->bedrooms,
            'bathrooms'     => $this->bathrooms,
            'floor'         => $this->floor,
            'total_floors'  => $this->totalFloors,
            'year_built'    => $this->yearBuilt,
            'document_type' => $this->documentType,
            'price'         => $this->price,
            'rent_price'    => $this->rentPrice,
            'deposit'       => $this->deposit,
            'address'       => $this->address,
            'city'          => $this->city,
            'region'        => $this->region,
            'latitude'      => $this->latitude,
            'longitude'     => $this->longitude,
            'thumbnail_url' => $this->thumbnailUrl,
            'gallery'       => !empty($this->gallery) ? json_encode($this->gallery, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'amenities'     => !empty($this->amenities) ? json_encode($this->amenities, JSON_UNESCAPED_UNICODE) : null,
            'agent_id'      => $this->agentId,
            'status'        => $this->status,
            'is_featured'   => $this->isFeatured ? 1 : 0,
            'sort_order'    => $this->sortOrder,
            'language'      => $this->language,
            'submitted_ip'  => $this->submittedIp,
        ];

        if ($includeId && $this->id !== null) {
            $data['id'] = $this->id;
        }
        return $data;
    }

    // Getters
    public function getId(): ?int              { return $this->id; }
    public function getTitle(): string         { return $this->title; }
    public function getSlug(): ?string         { return $this->slug; }
    public function getDescription(): ?string  { return $this->description; }
    public function getContent(): ?string      { return $this->content; }
    public function getPropertyType(): string  { return $this->propertyType; }
    public function getDealType(): string      { return $this->dealType; }
    public function getArea(): ?int            { return $this->area; }
    public function getBedrooms(): ?int        { return $this->bedrooms; }
    public function getBathrooms(): ?int       { return $this->bathrooms; }
    public function getFloor(): ?int           { return $this->floor; }
    public function getTotalFloors(): ?int     { return $this->totalFloors; }
    public function getYearBuilt(): ?int       { return $this->yearBuilt; }
    public function getDocumentType(): ?string { return $this->documentType; }
    public function getPrice(): ?float         { return $this->price; }
    public function getRentPrice(): ?float     { return $this->rentPrice; }
    public function getDeposit(): ?float       { return $this->deposit; }
    public function getAddress(): ?string      { return $this->address; }
    public function getCity(): ?string         { return $this->city; }
    public function getRegion(): ?string       { return $this->region; }
    public function getLatitude(): ?float      { return $this->latitude; }
    public function getLongitude(): ?float     { return $this->longitude; }
    public function getThumbnailUrl(): ?string { return $this->thumbnailUrl; }
    public function getGallery(): array        { return $this->gallery; }
    public function getAmenities(): array      { return $this->amenities; }
    public function getAgentId(): ?int         { return $this->agentId; }
    public function getStatus(): string        { return $this->status; }
    public function isFeatured(): bool         { return $this->isFeatured; }
    public function getSortOrder(): int        { return $this->sortOrder; }
    public function getLanguage(): string      { return $this->language; }
    public function getSubmittedIp(): ?string  { return $this->submittedIp; }
    public function getCreatedAt(): ?string    { return $this->createdAt; }
    public function getUpdatedAt(): ?string    { return $this->updatedAt; }
    public function getPublishedAt(): ?string  { return $this->publishedAt; }

    // Setters with validation
    public function setTitle(string $title): void
    {
        $title = trim($title);
        if ($title === '') throw new InvalidArgumentException('عنوان نمی‌تواند خالی باشد');
        if (mb_strlen($title) > 200) throw new InvalidArgumentException('عنوان حداکثر ۲۰۰ کاراکتر');
        $this->title = $title;
    }

    public function setPropertyType(string $type): void
    {
        if (!in_array($type, self::VALID_PROPERTY_TYPES, true)) {
            throw new InvalidArgumentException('نوع ملک نامعتبر: ' . $type);
        }
        $this->propertyType = $type;
    }

    public function setDealType(string $type): void
    {
        if (!in_array($type, self::VALID_DEAL_TYPES, true)) {
            throw new InvalidArgumentException('نوع معامله نامعتبر: ' . $type);
        }
        $this->dealType = $type;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException('وضعیت نامعتبر: ' . $status);
        }
        $this->status = $status;
    }

    public function setLanguage(string $lang): void
    {
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $lang)) {
            throw new InvalidArgumentException('کد زبان نامعتبر');
        }
        $this->language = $lang;
    }

    public function setSlug(?string $s): void          { $this->slug = $s; }
    public function setDescription(?string $d): void   { $this->description = $d; }
    public function setContent(?string $c): void       { $this->content = $c; }
    public function setArea(?int $a): void             { $this->area = $a; }
    public function setBedrooms(?int $b): void         { $this->bedrooms = $b; }
    public function setBathrooms(?int $b): void        { $this->bathrooms = $b; }
    public function setFloor(?int $f): void            { $this->floor = $f; }
    public function setTotalFloors(?int $t): void      { $this->totalFloors = $t; }
    public function setYearBuilt(?int $y): void        { $this->yearBuilt = $y; }
    public function setDocumentType(?string $d): void  { $this->documentType = $d; }
    public function setPrice(?float $p): void          { $this->price = $p; }
    public function setRentPrice(?float $p): void      { $this->rentPrice = $p; }
    public function setDeposit(?float $d): void        { $this->deposit = $d; }
    public function setAddress(?string $a): void       { $this->address = $a; }
    public function setCity(?string $c): void          { $this->city = $c; }
    public function setRegion(?string $r): void        { $this->region = $r; }
    public function setLatitude(?float $lat): void     { $this->latitude = $lat; }
    public function setLongitude(?float $lng): void    { $this->longitude = $lng; }
    public function setThumbnailUrl(?string $u): void  { $this->thumbnailUrl = $u; }
    public function setGallery(array $g): void         { $this->gallery = $g; }
    public function setAmenities(array $a): void       { $this->amenities = $a; }
    public function setAgentId(?int $id): void         { $this->agentId = $id; }
    public function setSortOrder(int $s): void         { $this->sortOrder = $s; }
    public function setSubmittedIp(?string $ip): void  { $this->submittedIp = $ip; }

    public function publish(): void         { $this->status = self::STATUS_PUBLISHED; }
    public function hide(): void            { $this->status = self::STATUS_HIDDEN; }
    public function markAsSold(): void      { $this->status = self::STATUS_SOLD; }
    public function markAsRented(): void    { $this->status = self::STATUS_RENTED; }
    public function markAsPending(): void   { $this->status = self::STATUS_PENDING; }
    public function toggleFeatured(): void  { $this->isFeatured = !$this->isFeatured; }
    public function isPublished(): bool     { return $this->status === self::STATUS_PUBLISHED; }
    public function isPending(): bool       { return $this->status === self::STATUS_PENDING; }
    public function getPropertyTypeLabel(): string
    {
        return match ($this->propertyType) {
            'apartment' => 'آپارتمان',
            'villa'     => 'ویلا',
            'land'      => 'زمین',
            'office'    => 'اداری',
            'shop'      => 'مغازه',
            'warehouse' => 'انبار',
            default     => $this->propertyType,
        };
    }

    public function getDealTypeLabel(): string
    {
        return match ($this->dealType) {
            'sale'     => 'فروش',
            'rent'     => 'اجاره',
            'mortgage' => 'رهن',
            default    => $this->dealType,
        };
    }

    public function isNew(): bool           { return $this->id === null; }
}
