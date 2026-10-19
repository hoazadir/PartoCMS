<?php
/**
 * PartoCMS — Services Module
 * Infrastructure: PdoServiceRepository
 */

declare(strict_types=1);

namespace PartoCMS\Services\Infrastructure\Persistence;

use PDO;
use PDOException;
use PartoCMS\Services\Domain\Entity\ServiceItem;
use PartoCMS\Services\Domain\Exception\ServiceException;
use PartoCMS\Services\Domain\Repository\ServiceRepositoryInterface;

final class PdoServiceRepository implements ServiceRepositoryInterface
{
    private const TABLE = 'services';

    public function __construct(private readonly PDO $pdo) {}

    public function create(ServiceItem $service): int
    {
        try {
            $sql = "INSERT INTO " . self::TABLE . "
                    (title, slug, description, content, icon, image_url, price, price_unit, features, category, status, is_featured, sort_order, language)
                    VALUES (:title, :slug, :description, :content, :icon, :image_url, :price, :price_unit, :features, :category, :status, :is_featured, :sort_order, :language)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($service->toArray());
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw ServiceException::databaseError('create', $e);
        }
    }

    public function update(ServiceItem $service): bool
    {
        if ($service->getId() === null) {
            throw ServiceException::invalidInput('id', 'ID is required for update');
        }
        try {
            $data = $service->toArray();
            unset($data['slug']); // معمولاً slug بعد از ایجاد تغییر نمی‌کند، اگر خواستید نگه دارید
            $data['id'] = $service->getId();
            $sql = "UPDATE " . self::TABLE . " SET
                    title = :title, description = :description, content = :content,
                    icon = :icon, image_url = :image_url, price = :price,
                    price_unit = :price_unit, features = :features, category = :category,
                    status = :status, is_featured = :is_featured, sort_order = :sort_order,
                    language = :language
                    WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($data);
            return $stmt->rowCount() >= 0;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('update', $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('delete', $e);
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, ServiceItem::VALID_STATUSES, true)) {
            throw ServiceException::invalidStatus($status);
        }
        try {
            $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('updateStatus', $e);
        }
    }

    public function toggleFeatured(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET is_featured = NOT is_featured WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('toggleFeatured', $e);
        }
    }

    public function updateSortOrder(int $id, int $order): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET sort_order = :order WHERE id = :id");
            $stmt->execute([':id' => $id, ':order' => $order]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('updateSortOrder', $e);
        }
    }

    public function findById(int $id): ?ServiceItem
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? ServiceItem::fromArray($row) : null;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('findById', $e);
        }
    }

    public function findBySlug(string $slug): ?ServiceItem
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? ServiceItem::fromArray($row) : null;
        } catch (PDOException $e) {
            throw ServiceException::databaseError('findBySlug', $e);
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
            return array_map(fn($row) => ServiceItem::fromArray($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (PDOException $e) {
            throw ServiceException::databaseError('findAll', $e);
        }
    }

    public function findPublished(array $filters = []): array
    {
        $filters['status'] = ServiceItem::STATUS_PUBLISHED;
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
            throw ServiceException::databaseError('count', $e);
        }
    }

    public function getStats(): array
    {
        try {
            $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'hidden' THEN 1 ELSE 0 END) AS hidden,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured
                FROM " . self::TABLE;
            $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
            return [
                'total'     => (int) ($row['total'] ?? 0),
                'published' => (int) ($row['published'] ?? 0),
                'hidden'    => (int) ($row['hidden'] ?? 0),
                'featured'  => (int) ($row['featured'] ?? 0),
            ];
        } catch (PDOException $e) {
            throw ServiceException::databaseError('getStats', $e);
        }
    }

    public function getCategories(): array
    {
        try {
            $sql = "SELECT DISTINCT category FROM " . self::TABLE . " WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            throw ServiceException::databaseError('getCategories', $e);
        }
    }

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (!in_array($status, ServiceItem::VALID_STATUSES, true)) {
            throw ServiceException::invalidStatus($status);
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
            throw ServiceException::databaseError('bulkUpdateStatus', $e);
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
            throw ServiceException::databaseError('bulkDelete', $e);
        }
    }

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], ServiceItem::VALID_STATUSES, true)) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        if (isset($filters['is_featured'])) {
            $where[] = 'is_featured = :is_featured';
            $params[':is_featured'] = $filters['is_featured'] ? 1 : 0;
        }
        if (!empty($filters['language'])) {
            $where[] = 'language = :language';
            $params[':language'] = $filters['language'];
        }
        if (!empty($filters['category'])) {
            $where[] = 'category = :category';
            $params[':category'] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(title LIKE :search OR description LIKE :search OR content LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereSql, $params];
    }

    private function buildOrderBy(array $filters): string
    {
        $allowed = ['id', 'title', 'price', 'sort_order', 'created_at'];
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
