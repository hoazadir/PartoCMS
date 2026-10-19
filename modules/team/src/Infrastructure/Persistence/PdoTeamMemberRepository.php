<?php
/**
 * PartoCMS — Team Module
 * Infrastructure: PdoTeamMemberRepository
 *
 * @author Hooman Oliaei
 * @license Proprietary
 * @version 1.0.0
 * @date 2026-10-19
 */

declare(strict_types=1);

namespace PartoCMS\Team\Infrastructure\Persistence;

use PDO;
use PDOException;
use PartoCMS\Team\Domain\Entity\TeamMember;
use PartoCMS\Team\Domain\Exception\TeamException;
use PartoCMS\Team\Domain\Repository\TeamMemberRepositoryInterface;

final class PdoTeamMemberRepository implements TeamMemberRepositoryInterface
{
    private const TABLE = 'team_members';

    public function __construct(private readonly PDO $pdo) {}

    // ═══════════════════════════════════════════════════════════
    // Write Operations
    // ═══════════════════════════════════════════════════════════

    public function create(TeamMember $member): int
    {
        try {
            $sql = "INSERT INTO " . self::TABLE . "
                    (name, position, department, bio, photo_url, email, phone, linkedin, twitter, instagram, status, sort_order, language, is_featured)
                    VALUES
                    (:name, :position, :department, :bio, :photo_url, :email, :phone, :linkedin, :twitter, :instagram, :status, :sort_order, :language, :is_featured)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':name'        => $member->getName(),
                ':position'    => $member->getPosition(),
                ':department'  => $member->getDepartment(),
                ':bio'         => $member->getBio(),
                ':photo_url'   => $member->getPhotoUrl(),
                ':email'       => $member->getEmail(),
                ':phone'       => $member->getPhone(),
                ':linkedin'    => $member->getLinkedin(),
                ':twitter'     => $member->getTwitter(),
                ':instagram'   => $member->getInstagram(),
                ':status'      => $member->getStatus(),
                ':sort_order'  => $member->getSortOrder(),
                ':language'    => $member->getLanguage(),
                ':is_featured' => $member->isFeatured() ? 1 : 0,
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw TeamException::databaseError('create', $e);
        }
    }

    public function update(TeamMember $member): bool
    {
        if ($member->getId() === null) {
            throw TeamException::invalidInput('id', 'ID is required for update');
        }

        try {
            $sql = "UPDATE " . self::TABLE . " SET
                    name = :name,
                    position = :position,
                    department = :department,
                    bio = :bio,
                    photo_url = :photo_url,
                    email = :email,
                    phone = :phone,
                    linkedin = :linkedin,
                    twitter = :twitter,
                    instagram = :instagram,
                    status = :status,
                    sort_order = :sort_order,
                    language = :language,
                    is_featured = :is_featured
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':id'          => $member->getId(),
                ':name'        => $member->getName(),
                ':position'    => $member->getPosition(),
                ':department'  => $member->getDepartment(),
                ':bio'         => $member->getBio(),
                ':photo_url'   => $member->getPhotoUrl(),
                ':email'       => $member->getEmail(),
                ':phone'       => $member->getPhone(),
                ':linkedin'    => $member->getLinkedin(),
                ':twitter'     => $member->getTwitter(),
                ':instagram'   => $member->getInstagram(),
                ':status'      => $member->getStatus(),
                ':sort_order'  => $member->getSortOrder(),
                ':language'    => $member->getLanguage(),
                ':is_featured' => $member->isFeatured() ? 1 : 0,
            ]);

            return $stmt->rowCount() >= 0;
        } catch (PDOException $e) {
            throw TeamException::databaseError('update', $e);
        }
    }

    public function delete(int $id): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM " . self::TABLE . " WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw TeamException::databaseError('delete', $e);
        }
    }

    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, TeamMember::VALID_STATUSES, true)) {
            throw TeamException::invalidStatus($status);
        }

        try {
            $stmt = $this->pdo->prepare("UPDATE " . self::TABLE . " SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $id]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            throw TeamException::databaseError('updateStatus', $e);
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
            throw TeamException::databaseError('toggleFeatured', $e);
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
            throw TeamException::databaseError('updateSortOrder', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Read Operations
    // ═══════════════════════════════════════════════════════════

    public function findById(int $id): ?TeamMember
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM " . self::TABLE . " WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ? TeamMember::fromArray($row) : null;
        } catch (PDOException $e) {
            throw TeamException::databaseError('findById', $e);
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
                fn(array $row) => TeamMember::fromArray($row),
                $stmt->fetchAll(PDO::FETCH_ASSOC)
            );
        } catch (PDOException $e) {
            throw TeamException::databaseError('findAll', $e);
        }
    }

    public function findPublished(array $filters = []): array
    {
        $filters['status'] = TeamMember::STATUS_PUBLISHED;
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
            throw TeamException::databaseError('count', $e);
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
            throw TeamException::databaseError('getStats', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Bulk Operations
    // ═══════════════════════════════════════════════════════════

    public function bulkUpdateStatus(array $ids, string $status): int
    {
        if (!in_array($status, TeamMember::VALID_STATUSES, true)) {
            throw TeamException::invalidStatus($status);
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
            throw TeamException::databaseError('bulkUpdateStatus', $e);
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
            throw TeamException::databaseError('bulkDelete', $e);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // Internal Helpers
    // ═══════════════════════════════════════════════════════════

    private function buildWhere(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], TeamMember::VALID_STATUSES, true)) {
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

        if (!empty($filters['department'])) {
            $where[] = 'department = :department';
            $params[':department'] = $filters['department'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE :search OR position LIKE :search OR bio LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereSql, $params];
    }

    private function buildOrderBy(array $filters): string
    {
        $allowed = ['id', 'name', 'position', 'sort_order', 'created_at'];
        $orderBy = in_array($filters['order_by'] ?? '', $allowed, true) ? $filters['order_by'] : 'sort_order';
        $orderDir = strtoupper($filters['order_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        return "ORDER BY {$orderBy} {$orderDir}, id ASC";
    }

    private function buildLimit(array $filters): string
    {
        if (empty($filters['limit'])) return '';
        $limit  = max(1, (int) $filters['limit']);
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        return "LIMIT {$limit} OFFSET {$offset}";
    }
}
