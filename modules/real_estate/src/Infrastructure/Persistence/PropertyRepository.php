<?php
declare(strict_types=1);

namespace PartoCMS\RealEstate\Infrastructure\Persistence;

use PDO;
use PartoCMS\RealEstate\Domain\Entity\Property;

class PropertyRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * دریافت تمام املاک با فیلترهای اختیاری
     */
    public function findAll(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM real_estate_properties WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['property_type'])) {
            $sql .= " AND property_type = ?";
            $params[] = $filters['property_type'];
        }
        if (!empty($filters['listing_type'])) {
            $sql .= " AND listing_type = ?";
            $params[] = $filters['listing_type'];
        }
        if (!empty($filters['city'])) {
            $sql .= " AND city LIKE ?";
            $params[] = "%{$filters['city']}%";
        }
        if (!empty($filters['language'])) {
            $sql .= " AND language = ?";
            $params[] = $filters['language'];
        }
        if (isset($filters['is_featured'])) {
            $sql .= " AND is_featured = ?";
            $params[] = (int)$filters['is_featured'];
        }

        $sql .= " ORDER BY sort_order ASC, created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([Property::class, 'fromArray'], $rows);
    }

    /**
     * دریافت یک ملک با ID
     */
    public function findById(int $id): ?Property
    {
        $stmt = $this->db->prepare("SELECT * FROM real_estate_properties WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? Property::fromArray($row) : null;
    }

    /**
     * دریافت یک ملک با Slug
     */
    public function findBySlug(string $slug): ?Property
    {
        $stmt = $this->db->prepare("SELECT * FROM real_estate_properties WHERE slug = ? AND status = 'published'");
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? Property::fromArray($row) : null;
    }

    /**
     * ذخیره (ایجاد یا ویرایش)
     */
    public function save(Property $property): bool
    {
        $data = $property->toArray();
        
        if ($property->getId()) {
            // ویرایش
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
            $sql = "UPDATE real_estate_properties SET $set WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $params = array_values($data);
            $params[] = $property->getId();
            return $stmt->execute($params);
        } else {
            // ایجاد
            $keys = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO real_estate_properties ($keys) VALUES ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute(array_values($data));
            
            if ($result) {
                $property->setId((int)$this->db->lastInsertId());
            }
            return $result;
        }
    }

    /**
     * حذف ملک
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM real_estate_properties WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * شمارش کل املاک
     */
    public function count(array $filters = []): int
    {
        $sql = "SELECT COUNT(*) FROM real_estate_properties WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
