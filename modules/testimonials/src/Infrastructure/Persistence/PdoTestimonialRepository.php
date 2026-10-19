<?php
/**
 * PartoCMS — Testimonials Module
 * Infrastructure: PdoTestimonialRepository
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Testimonials\Infrastructure\Persistence;

use PDO;
use PDOException;
use PartoCMS\Testimonials\Domain\Entity\Testimonial;
use PartoCMS\Testimonials\Domain\Exception\TestimonialException;
use PartoCMS\Testimonials\Domain\Repository\TestimonialRepositoryInterface;

final class PdoTestimonialRepository implements TestimonialRepositoryInterface
{
    private const TABLE = 'testimonials';

    public function __construct(private readonly PDO $pdo) {}

    // ═══════════════════════════════════════════════════════════
    // Write Operations
    // ═══════════════════════════════════════════════════════════

    public function create(Testimonial $testimonial): int
    {
        try {
            $sql = "INSERT INTO " . self::TABLE . "
                    (name, email, company, position, content, rating, avatar_url,
                     status, is_featured, sort_order, language, submitted_ip, published_at)
                    VALUES
                    (:name, :email, :company, :position, :content, :rating, :avatar_url,
                     :status, :is_featured, :sort_order, :language, :submitted_ip, :published_at)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':name'         => $testimonial->getName(),
                ':email'        => $testimonial->getEmail(),
                ':company'      => $testimonial->getCompany(),
                ':position'     => $testimonial->getPosition(),
                ':content'      => $testimonial->getContent(),
                ':rating'       => $testimonial->getRating(),
                ':avatar_url'   => $testimonial->getAvatarUrl(),
                ':status'       => $testimonial->getStatus(),
                ':is_featured'  => $testimonial->isFeatured() ? 1 : 0,
                ':sort_order'   => $testimonial->getSortOrder(),
                ':language'     => $testimonial->getLanguage(),
                ':submitted_ip' => $testimonial->getSubmittedIp(),
                ':published_at' => $testimonial->isPublished() ? date('Y-m-d H:i:s') : null,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('create', $e);
        }
    }

    public function update(Testimonial $testimonial): bool
    {
        if ($testimonial->getId() === null) {
            throw TestimonialException::invalidInput('id', 'ID is required for update');
        }

        try {
            $sql = "UPDATE " . self::TABLE . " SET
                    name = :name,
                    email = :email,
                    company = :company,
                    position = :position,
                    content = :content,
                    rating = :rating,
                    avatar_url = :avatar_url,
                    status = :status,
                    is_featured = :is_featured,
                    sort_order = :sort_order,
                    language = :language
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id'          => $testimonial->getId(),
                ':name'        => $testimonial->getName(),
                ':email'       => $testimonial->getEmail(),
                ':company'     => $testimonial->getCompany(),
                ':position'    => $testimonial->getPosition(),
                ':content'     => $testimonial->getContent(),
                ':rating'      => $testimonial->getRating(),
                ':avatar_url'  => $testimonial->getAvatarUrl(),
                ':status'      => $testimonial->getStatus(),
                ':is_featured' => $testimonial->isFeatured() ? 1 : 0,
                ':sort_order'  => $testimonial->getSortOrder(),
                ':language'    => $testimonial->getLanguage(),
            ]);

            return $stmt->rowCount() >= 0;
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('update', $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('delete', $e);
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, Testimonial::VALID_STATUSES, true)) {
            throw TestimonialException::invalidStatus($status);
        }

        try {
            $sql = "UPDATE " . self::TABLE . " SET status = :status";
            $params = [':status' => $status, ':id' => $id];

            if ($status === Testimonial::STATUS_PUBLISHED) {
                $sql .= ", published_at = NOW()";
            }

            $sql .= " WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('updateStatus', $e);
        }
    }

    public function toggleFeatured(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE " . self::TABLE . " SET is_featured = NOT is_featured WHERE id = :id"
            );
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('toggleFeatured', $e);
        }
    }

    public function updateSortOrder(int $id, int $order): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "UPDATE " . self::TABLE . " SET sort_order = :order WHERE id = :id"
            );
            $stmt->execute([':id' => $id, ':order' => $order]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('updateSortOrder', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Read Operations
    // ═══════════════════════════════════════════════════════════

    public function findById(int $id): ?Testimonial
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? Testimonial::fromArray($row) : null;
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('findById', $e);
        }
    }

    public function findAll(array $filters = []): array
    {
        try {
            [$where, $params] = $this->buildWhere($filters);
            $orderBy  = $this->buildOrderBy($filters);
            $limitSql = $this->buildLimit($filters);

            $sql = "SELECT * FROM " . self::TABLE . " {$where} {$orderBy} {$limitSql}";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return array_map(
                fn(array $row) => Testimonial::fromArray($row),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('findAll', $e);
        }
    }

    public function findPublished(array $filters = []): array
    {
        $filters['status'] = Testimonial::STATUS_PUBLISHED;
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
            throw TestimonialException::databaseError('count', $e);
        }
    }

    public function getStats(): array
    {
        try {
            $sql = "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN is_featured = 1 THEN 1 ELSE 0 END) AS featured,
                COALESCE(AVG(rating), 0) AS avg_rating
                FROM " . self::TABLE;

            $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

            return [
                'total'      => (int)   ($row['total'] ?? 0),
                'pending'    => (int)   ($row['pending'] ?? 0),
                'published'  => (int)   ($row['published'] ?? 0),
                'rejected'   => (int)   ($row['rejected'] ?? 0),
                'featured'   => (int)   ($row['featured'] ?? 0),
                'avg_rating' => round((float) ($row['avg_rating'] ?? 0), 2),
            ];
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('getStats', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Bulk Operations
    // ═══════════════════════════════════════════════════════════

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (!in_array($status, Testimonial::VALID_STATUSES, true)) {
            throw TestimonialException::invalidStatus($status);
        }

        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($ids)) {
            return 0;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge([$status], $ids);

            $sql = "UPDATE " . self::TABLE . " SET status = ? WHERE id IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('bulkUpdateStatus', $e);
        }
    }

    public function bulkDelete(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
        if (empty($ids)) {
            return 0;
        }

        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM " . self::TABLE . " WHERE id IN ({$placeholders})";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($ids);

            return $stmt->rowCount();
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('bulkDelete', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Spam Prevention
    // ═══════════════════════════════════════════════════════════

    public function hasRecentSubmission(string $ip, int $minutes = 60): bool
    {
        return $this->countByIp($ip, max(1, intdiv($minutes, 60))) > 0;
    }

    public function countByIp(string $ip, int $hours = 24): int
    {
        try {
            $sql = "SELECT COUNT(*) FROM " . self::TABLE . "
                    WHERE submitted_ip = :ip
                    AND created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
            $stmt->bindValue(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw TestimonialException::databaseError('countByIp', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Internal Helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], Testimonial::VALID_STATUSES, true)) {
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

        if (!empty($filters['min_rating'])) {
            $where[] = 'rating >= :min_rating';
            $params[':min_rating'] = (int) $filters['min_rating'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR company LIKE :search OR content LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        return [$whereSql, $params];
    }

    private function buildOrderBy(array $filters): string
    {
        $allowed = ['id', 'name', 'rating', 'sort_order', 'created_at', 'published_at'];
        $orderBy = in_array($filters['order_by'] ?? '', $allowed, true)
            ? $filters['order_by']
            : 'sort_order';
        $orderDir = strtoupper($filters['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        return "ORDER BY {$orderBy} {$orderDir}, id ASC";
    }

    private function buildLimit(array $filters): string
    {
        if (empty($filters['limit'])) {
            return '';
        }
        $limit  = max(1, (int) $filters['limit']);
        $offset = max(0, (int) ($filters['offset'] ?? 0));

        return "LIMIT {$limit} OFFSET {$offset}";
    }
}
