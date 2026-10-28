<?php
declare(strict_types=1);

namespace PartoCMS\RealEstate\Domain\Entity;

class Property
{
    private ?int $id = null;
    private string $title = '';
    private string $slug = '';
    private ?string $description = null;
    private string $propertyType = 'apartment';
    private string $listingType = 'sale';
    private ?float $price = null;
    private ?float $rentPrice = null;
    private ?float $depositPrice = null;
    private ?int $area = null;
    private ?int $rooms = null;
    private ?int $bathrooms = null;
    private ?int $yearBuilt = null;
    private ?string $address = null;
    private ?string $city = null;
    private ?string $region = null;
    private ?float $latitude = null;
    private ?float $longitude = null;
    private ?string $featuredImage = null;
    private array $gallery = [];
    private array $features = [];
    private ?string $agentName = null;
    private ?string $agentPhone = null;
    private ?string $agentEmail = null;
    private string $status = 'published';
    private bool $isFeatured = false;
    private int $sortOrder = 0;
    private string $language = 'fa-IR';
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    // Getters و Setters
    public function getId(): ?int { return $this->id; }
    public function setId(?int $id): self { $this->id = $id; return $this; }
    
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): self { $this->slug = $slug; return $this; }
    
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    
    public function getPropertyType(): string { return $this->propertyType; }
    public function setPropertyType(string $type): self { $this->propertyType = $type; return $this; }
    
    public function getListingType(): string { return $this->listingType; }
    public function setListingType(string $type): self { $this->listingType = $type; return $this; }
    
    public function getPrice(): ?float { return $this->price; }
    public function setPrice(?float $price): self { $this->price = $price; return $this; }
    
    public function getRentPrice(): ?float { return $this->rentPrice; }
    public function setRentPrice(?float $price): self { $this->rentPrice = $price; return $this; }
    
    public function getDepositPrice(): ?float { return $this->depositPrice; }
    public function setDepositPrice(?float $price): self { $this->depositPrice = $price; return $this; }
    
    public function getArea(): ?int { return $this->area; }
    public function setArea(?int $area): self { $this->area = $area; return $this; }
    
    public function getRooms(): ?int { return $this->rooms; }
    public function setRooms(?int $rooms): self { $this->rooms = $rooms; return $this; }
    
    public function getBathrooms(): ?int { return $this->bathrooms; }
    public function setBathrooms(?int $bathrooms): self { $this->bathrooms = $bathrooms; return $this; }
    
    public function getYearBuilt(): ?int { return $this->yearBuilt; }
    public function setYearBuilt(?int $year): self { $this->yearBuilt = $year; return $this; }
    
    public function getAddress(): ?string { return $this->address; }
    public function setAddress(?string $address): self { $this->address = $address; return $this; }
    
    public function getCity(): ?string { return $this->city; }
    public function setCity(?string $city): self { $this->city = $city; return $this; }
    
    public function getRegion(): ?string { return $this->region; }
    public function setRegion(?string $region): self { $this->region = $region; return $this; }
    
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $lat): self { $this->latitude = $lat; return $this; }
    
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $lng): self { $this->longitude = $lng; return $this; }
    
    public function getFeaturedImage(): ?string { return $this->featuredImage; }
    public function setFeaturedImage(?string $img): self { $this->featuredImage = $img; return $this; }
    
    public function getGallery(): array { return $this->gallery; }
    public function setGallery(array $gallery): self { $this->gallery = $gallery; return $this; }
    
    public function getFeatures(): array { return $this->features; }
    public function setFeatures(array $features): self { $this->features = $features; return $this; }
    
    public function getAgentName(): ?string { return $this->agentName; }
    public function setAgentName(?string $name): self { $this->agentName = $name; return $this; }
    
    public function getAgentPhone(): ?string { return $this->agentPhone; }
    public function setAgentPhone(?string $phone): self { $this->agentPhone = $phone; return $this; }
    
    public function getAgentEmail(): ?string { return $this->agentEmail; }
    public function setAgentEmail(?string $email): self { $this->agentEmail = $email; return $this; }
    
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }
    
    public function isFeatured(): bool { return $this->isFeatured; }
    public function setIsFeatured(bool $featured): self { $this->isFeatured = $featured; return $this; }
    
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $order): self { $this->sortOrder = $order; return $this; }
    
    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $lang): self { $this->language = $lang; return $this; }
    
    public function getCreatedAt(): ?string { return $this->createdAt; }
    public function setCreatedAt(?string $date): self { $this->createdAt = $date; return $this; }
    
    public function getUpdatedAt(): ?string { return $this->updatedAt; }
    public function setUpdatedAt(?string $date): self { $this->updatedAt = $date; return $this; }

    /**
     * ساخت Entity از آرایه (برای خواندن از دیتابیس)
     */
    public static function fromArray(array $data): self
    {
        $property = new self();
        $property->setId(isset($data['id']) ? (int)$data['id'] : null);
        $property->setTitle($data['title'] ?? '');
        $property->setSlug($data['slug'] ?? '');
        $property->setDescription($data['description'] ?? null);
        $property->setPropertyType($data['property_type'] ?? 'apartment');
        $property->setListingType($data['listing_type'] ?? 'sale');
        $property->setPrice(isset($data['price']) ? (float)$data['price'] : null);
        $property->setRentPrice(isset($data['rent_price']) ? (float)$data['rent_price'] : null);
        $property->setDepositPrice(isset($data['deposit_price']) ? (float)$data['deposit_price'] : null);
        $property->setArea(isset($data['area']) ? (int)$data['area'] : null);
        $property->setRooms(isset($data['rooms']) ? (int)$data['rooms'] : null);
        $property->setBathrooms(isset($data['bathrooms']) ? (int)$data['bathrooms'] : null);
        $property->setYearBuilt(isset($data['year_built']) ? (int)$data['year_built'] : null);
        $property->setAddress($data['address'] ?? null);
        $property->setCity($data['city'] ?? null);
        $property->setRegion($data['region'] ?? null);
        $property->setLatitude(isset($data['latitude']) ? (float)$data['latitude'] : null);
        $property->setLongitude(isset($data['longitude']) ? (float)$data['longitude'] : null);
        $property->setFeaturedImage($data['featured_image'] ?? null);
        
        if (!empty($data['gallery'])) {
            $property->setGallery(json_decode($data['gallery'], true) ?: []);
        }
        if (!empty($data['features'])) {
            $property->setFeatures(json_decode($data['features'], true) ?: []);
        }
        
        $property->setAgentName($data['agent_name'] ?? null);
        $property->setAgentPhone($data['agent_phone'] ?? null);
        $property->setAgentEmail($data['agent_email'] ?? null);
        $property->setStatus($data['status'] ?? 'published');
        $property->setIsFeatured((bool)($data['is_featured'] ?? false));
        $property->setSortOrder((int)($data['sort_order'] ?? 0));
        $property->setLanguage($data['language'] ?? 'fa-IR');
        $property->setCreatedAt($data['created_at'] ?? null);
        $property->setUpdatedAt($data['updated_at'] ?? null);
        
        return $property;
    }

    /**
     * تبدیل Entity به آرایه (برای ذخیره در دیتابیس)
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'property_type' => $this->propertyType,
            'listing_type' => $this->listingType,
            'price' => $this->price,
            'rent_price' => $this->rentPrice,
            'deposit_price' => $this->depositPrice,
            'area' => $this->area,
            'rooms' => $this->rooms,
            'bathrooms' => $this->bathrooms,
            'year_built' => $this->yearBuilt,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'featured_image' => $this->featuredImage,
            'gallery' => !empty($this->gallery) ? json_encode($this->gallery, JSON_UNESCAPED_UNICODE) : null,
            'features' => !empty($this->features) ? json_encode($this->features, JSON_UNESCAPED_UNICODE) : null,
            'agent_name' => $this->agentName,
            'agent_phone' => $this->agentPhone,
            'agent_email' => $this->agentEmail,
            'status' => $this->status,
            'is_featured' => $this->isFeatured ? 1 : 0,
            'sort_order' => $this->sortOrder,
            'language' => $this->language,
        ];
    }
}
