<?php
/**
 * PartoCMS — Team Module
 * Domain Entity: TeamMember
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Team\Domain\Entity;

use InvalidArgumentException;

final class TeamMember
{
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_HIDDEN    = 'hidden';

    public const VALID_STATUSES = [
        self::STATUS_PUBLISHED,
        self::STATUS_HIDDEN,
    ];

    private ?int    $id;
    private string  $name;
    private ?string $position;
    private ?string $department;
    private ?string $bio;
    private ?string $photoUrl;
    private ?string $email;
    private ?string $phone;
    private ?string $linkedin;
    private ?string $twitter;
    private ?string $instagram;
    private string  $status;
    private int     $sortOrder;
    private string  $language;
    private bool    $isFeatured;
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        ?int    $id,
        string  $name,
        ?string $position,
        ?string $department,
        ?string $bio,
        ?string $photoUrl,
        ?string $email,
        ?string $phone,
        ?string $linkedin,
        ?string $twitter,
        ?string $instagram,
        string  $status,
        int     $sortOrder,
        string  $language,
        bool    $isFeatured,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->setName($name);
        $this->setEmail($email);
        $this->setPhotoUrl($photoUrl);
        $this->setStatus($status);
        $this->setLanguage($language);

        $this->id          = $id;
        $this->position    = $position;
        $this->department  = $department;
        $this->bio         = $bio;
        $this->phone       = $phone;
        $this->linkedin    = $linkedin;
        $this->twitter     = $twitter;
        $this->instagram   = $instagram;
        $this->sortOrder   = $sortOrder;
        $this->isFeatured  = $isFeatured;
        $this->createdAt   = $createdAt;
        $this->updatedAt   = $updatedAt;
    }

    public static function fromArray(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            (string) ($row['name'] ?? ''),
            $row['position']    ?? null,
            $row['department']  ?? null,
            $row['bio']         ?? null,
            $row['photo_url']   ?? null,
            $row['email']       ?? null,
            $row['phone']       ?? null,
            $row['linkedin']    ?? null,
            $row['twitter']     ?? null,
            $row['instagram']   ?? null,
            (string) ($row['status'] ?? self::STATUS_PUBLISHED),
            (int) ($row['sort_order'] ?? 0),
            (string) ($row['language'] ?? 'fa-IR'),
            (bool) ($row['is_featured'] ?? false),
            $row['created_at']  ?? null,
            $row['updated_at']  ?? null
        );
    }

    public function toArray(bool $includeId = false): array
    {
        $data = [
            'name'         => $this->name,
            'position'     => $this->position,
            'department'   => $this->department,
            'bio'          => $this->bio,
            'photo_url'    => $this->photoUrl,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'linkedin'     => $this->linkedin,
            'twitter'      => $this->twitter,
            'instagram'    => $this->instagram,
            'status'       => $this->status,
            'sort_order'   => $this->sortOrder,
            'language'     => $this->language,
            'is_featured'  => $this->isFeatured ? 1 : 0,
        ];

        if ($includeId && $this->id !== null) {
            $data['id'] = $this->id;
        }

        return $data;
    }

    // Getters
    public function getId(): ?int             { return $this->id; }
    public function getName(): string         { return $this->name; }
    public function getPosition(): ?string    { return $this->position; }
    public function getDepartment(): ?string  { return $this->department; }
    public function getBio(): ?string         { return $this->bio; }
    public function getPhotoUrl(): ?string    { return $this->photoUrl; }
    public function getEmail(): ?string       { return $this->email; }
    public function getPhone(): ?string       { return $this->phone; }
    public function getLinkedin(): ?string    { return $this->linkedin; }
    public function getTwitter(): ?string     { return $this->twitter; }
    public function getInstagram(): ?string   { return $this->instagram; }
    public function getStatus(): string       { return $this->status; }
    public function getSortOrder(): int       { return $this->sortOrder; }
    public function getLanguage(): string     { return $this->language; }
    public function isFeatured(): bool        { return $this->isFeatured; }
    public function getCreatedAt(): ?string   { return $this->createdAt; }
    public function getUpdatedAt(): ?string   { return $this->updatedAt; }

    // Setters with validation
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

    public function setPhotoUrl(?string $url): void
    {
        if ($url !== null && $url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('URL عکس نامعتبر است');
            }
            $this->photoUrl = $url;
            return;
        }
        $this->photoUrl = null;
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

    public function setPosition(?string $position): void   { $this->position = $position !== null ? trim($position) : null; }
    public function setDepartment(?string $department): void { $this->department = $department !== null ? trim($department) : null; }
    public function setBio(?string $bio): void             { $this->bio = $bio !== null ? trim($bio) : null; }
    public function setPhone(?string $phone): void         { $this->phone = $phone !== null ? trim($phone) : null; }
    public function setLinkedin(?string $link): void       { $this->linkedin = $link !== null ? trim($link) : null; }
    public function setTwitter(?string $link): void        { $this->twitter = $link !== null ? trim($link) : null; }
    public function setInstagram(?string $link): void      { $this->instagram = $link !== null ? trim($link) : null; }
    public function setSortOrder(int $order): void         { $this->sortOrder = $order; }

    // Domain methods
    public function publish(): void    { $this->status = self::STATUS_PUBLISHED; }
    public function hide(): void       { $this->status = self::STATUS_HIDDEN; }
    public function toggleFeatured(): void { $this->isFeatured = !$this->isFeatured; }
    public function isPublished(): bool { return $this->status === self::STATUS_PUBLISHED; }
    public function isHidden(): bool   { return $this->status === self::STATUS_HIDDEN; }
    public function isNew(): bool      { return $this->id === null; }
}
