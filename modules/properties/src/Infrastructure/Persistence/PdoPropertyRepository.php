<?php
/**
 * PartoCMS — Properties Module
 * Infrastructure: PdoPropertyRepository
 */

declare(strict_types=1);

namespace PartoCMS\Properties\Infrastructure\Persistence;

use PDO;
use PDOException;
use PartoCMS\Properties\Domain\Entity\Property;
use PartoCMS\Properties\Domain\Exception\PropertyException;
use PartoCMS\Properties\Domain\Repository\PropertyRepositoryInterface;

final class PdoPropertyRepository implements PropertyRepositoryInterface
{
    private const TABLE = 'properties';

    public function __construct(private readonly PDO $pdo) {}

    public function create(Property $property): int
    {
        try {
            $data = $property->toArray();
            $data['published_at'] = $property->isPublished() ? date('Y-m-d H:i:s') : null;

            $cols = array_keys($data);
            $placeholders = array_map(fn($c) => ':' . $c, $cols);
            $sql = "INSERT INTO " . self::TABLE . " (" . implode(',', $cols) . ")
                    VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw PropertyException::databaseError('create', $e);
        }
    }

    public function update(Property $property): bool
    {
        if ($property->getId() === null) {
            throw PropertyException::invalidInput('id', 'ID is required for update');
        }
        try {
            $data = $property->toArray();
            $data['id'] = $property->getId();

            if ($property->isPublished()) {
                $data['published_at'] = date('Y-m-d H:i:s');
            }

            $setParts = [];
            foreach (array_keys($data) as $col) {
                if ($col !== 'id') {
                    $setParts[] = "{$col} = :{$col}";
                }
            }
            $sql = "UPDATE " . self::TABLE . " SET " . implode(', ', $setParts) . " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $stmt->rowCount() >= 0;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('update', $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('delete', $e);
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, Property::VALID_STATUSES, true)) {
            throw PropertyException::invalidStatus($status);
        }
        try {
            $sql = "UPDATE " . self::TABLE . " SET status = :status";
            $params = [':status' => $status, ':id' => $id];
            if ($status === Property::STATUS_PUBLISHED) {
                $sql .= ", published_at = NOW()";
            }
            $sql .= " WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('updateStatus', $e);
        }
    }

    public function toggleFeatured(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET is_featured = NOT is_featured WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('toggleFeatured', $e);
        }
    }

    public function updateSortOrder(int $id, int $order): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET sort_order = :order WHERE id = :id");
            $stmt->execute([':id' => $id, ':order' => $order]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('updateSortOrder', $e);
        }
    }

    public function findById(int $id): ?Property
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? Property::fromArray($row) : null;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('findById', $e);
        }
    }

    public function findBySlug(string $slug): ?Property
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? Property::fromArray($row) : null;
        } catch (PDOException $e) {
            throw PropertyException::databaseError('findBySlug', $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            [$where, $params] = $this->buildWhere($filters);
            $orderBy = $this->buildOrderBy($filters);
            $limitSql = $this->buildLimit($filters);
            $sql = "SELECT * FROM " . self::TABLE . " {$where} {$orderBy} {$limitSql}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return array_map(fn($row) => Property::fromArray($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            throw PropertyException::databaseError('findAll', $e);
        }
    }

    public function findPublished(array $filters = []): array
    {
        $filters['status'] = Property::STATUS_PUBLISHED;
        $filters['order_by'] = $filters['order_by'] ?? 'sort_order';
        $filters['order_dir'] = $filters['order_dir'] ?? 'ASC';
        return $this->findAll($filters);
    }

    public function count(array $filters = []): int
    {
        try {
            [$where, $params] = $this->buildWhere($filters);
            $sql = "SELECT COUNT(*) FROM " . self::TABLE . " {$where}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw PropertyException::databaseError('count', $e);
        }
    }

    public function getStats(): array
    {
        try {
            $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) AS sold,
                SUM(CASE WHEN status = 'rented' THEN 1 ELSE 0 END) AS rented,
                SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) AS hidden,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured
                FROM " . self::TABLE;
            $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            return [
                'total'     => (int) ($row['total'] ?? 0),
                'published' => (int) ($row['published'] ?? 0),
                'pending'   => (int) ($row['pending'] ?? 0),
                'sold'      => (int) ($row['sold'] ?? 0),
                'rented'    => (int) ($row['rented'] ?? 0),
                'hidden'    => (int) ($row['hidden'] ?? 0),
                'featured'  => (int) ($row['featured'] ?? 0),
            ];
        } catch (PDOException $e) {
            throw PropertyException::databaseError('getStats', $e);
        }
    }

    public function getCities(): array
    {
        try {
            $sql = "SELECT DISTINCT city FROM " . self::TABLE . " WHERE city IS NOT NULL AND city != '' ORDER BY city ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw PropertyException::databaseError('getCities', $e);
        }
    }

    public function getRegions(?string $city = null): array
    {
        try {
            if ($city !== null) {
                $stmt = $this->pdo->prepare("SELECT DISTINCT region FROM " . self::TABLE . " WHERE city = :city AND region IS NOT NULL AND region != '' ORDER BY region ASC");
                $stmt->execute([':city' => $city]);
                return $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            $sql = "SELECT DISTINCT region FROM " . self::TABLE . " WHERE region IS NOT NULL AND region != '' ORDER BY region ASC";
            return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw PropertyException::databaseError('getRegions', $e);
        }
    }

    public function getAmenitiesList(): array
    {
        try {
            $stmt = $this->pdo->query("SELECT amenities FROM " . self::TABLE . " WHERE amenities IS NOT NULL AND amenities != ''");
            $all = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $decoded = json_decode((string) $row['amenities'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $a) {
                        $all[] = $a;
                    }
                }
            }
            return array_values(array_unique($all));
        } catch (PDOException $e) {
            throw PropertyException::databaseError('getAmenitiesList', $e);
        }
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (!in_array($status, Property::VALID_STATUSES, true)) {
            throw PropertyException::invalidStatus($status);
        }
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($ids)) return 0;
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$status], $ids);
            $sql = "UPDATE " . self::TABLE . " SET status = ? WHERE id IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw PropertyException::databaseError('bulkUpdateStatus', $e);
        }
    }

    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($ids)) return 0;
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM " . self::TABLE . " WHERE id IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($ids);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw PropertyException::databaseError('bulkDelete', $e);
        }
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], Property::VALID_STATUSES, true)) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (!empty($filters['property_type']) && in_array($filters['property_type'], Property::VALID_PROPERTY_TYPES, true)) {
            $where[] = 'property_type = :property_type';
            $params[':property_type'] = $filters['property_type'];
        }
        if (!empty($filters['deal_type']) && in_array($filters['deal_type'], Property::VALID_DEAL_TYPES, true)) {
            $where[] = 'deal_type = :deal_type';
            $params[':deal_type'] = $filters['deal_type'];
        }
        if (!empty($filters['city'])) {
            $where[] = 'city = :city';
            $params[':city'] = $filters['city'];
        }
        if (!empty($filters['region'])) {
            $where[] = 'region = :region';
            $params[':region'] = $filters['region'];
        }
        if (!empty($filters['agent_id'])) {
            $where[] = 'agent_id = :agent_id';
            $params[':agent_id'] = (int) $filters['agent_id'];
        }
        if (isset($filters['is_featured'])) {
            $where[] = 'is_featured = :is_featured';
            $params[':is_featured'] = $filters['is_featured'] ? 1 : 0;
        }
        if (!empty($filters['language'])) {
            $where[] = 'language = :language';
            $params[':language'] = $filters['language'];
        }
        if (!empty($filters['area_min'])) {
            $where[] = 'area >= :area_min';
            $params[':area_min'] = (int) $filters['area_min'];
        }
        if (!empty($filters['area_max'])) {
            $where[] = 'area <= :area_max';
            $params[':area_max'] = (int) $filters['area_max'];
        }
        if (!empty($filters['bedrooms'])) {
            $where[] = 'bedrooms >= :bedrooms';
            $params[':bedrooms'] = (int) $filters['bedrooms'];
        }
        if (!empty($filters['price_min'])) {
            $where[] = 'price >= :price_min';
            $params[':price_min'] = (float) $filters['price_min'];
        }
        if (!empty($filters['price_max'])) {
            $where[] = 'price <= :price_max';
            $params[':price_max'] = (float) $filters['price_max'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(title LIKE :search OR description LIKE :search OR address LIKE :search OR city LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereSql, $params];
    }

    private function buildOrderBy(array $filters): string
    {
        $allowed = ['id', 'title', 'price', 'area', 'sort_order', 'created_at', 'published_at'];
        $orderBy = in_array($filters['order_by'] ?? '', $allowed, true) ? $filters['order_by'] : 'sort_order';
        $orderDir = strtoupper($filters['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
        return "ORDER BY {$orderBy} {$orderDir}, id ASC";
    }

    private function buildLimit(array $filters): string
    {
        if (empty($filters['limit'])) return '';
        $limit = max(1, (int) $filters['limit']);
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        return "LIMIT {$limit} OFFSET {$offset}";
    }
}
