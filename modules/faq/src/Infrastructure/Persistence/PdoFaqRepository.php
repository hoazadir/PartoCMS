<?php
/**
 * PartoCMS - FAQ PDO Repository
 *
 * پیاده‌سازی دیتابیس برای سوالات متداول
 *
 * @package PartoCMS\Faq\Infrastructure\Persistence
 */

declare(strict_types=1);

namespace PartoCMS\Faq\Infrastructure\Persistence;

use PDO;
use PartoCMS\Faq\Domain\Entity\FaqItem;
use PartoCMS\Faq\Domain\Repository\FaqRepositoryInterface;

final class PdoFaqRepository implements FaqRepositoryInterface
{
    private PDO $pdo;
    private string $table = 'faqs';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(int $id): ?FaqItem
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? FaqItem::fromArray($row) : null;
    }

    public function save(FaqItem $item): FaqItem
    {
        if ($item->isNew()) {
            return $this->insert($item);
        }
        return $this->update($item);
    }

    private function insert(FaqItem $item): FaqItem
    {
        $data = $item->toArray();
        unset($data['created_at'], $data['updated_at']); // DB handles timestamps

        $sql = "INSERT INTO {$this->table} 
                (question, answer, category, sort_order, is_active, created_at, updated_at) 
                VALUES 
                (:question, :answer, :category, :sort_order, :is_active, NOW(), NOW())";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return new FaqItem(
            id: (int) $this->pdo->lastInsertId(),
            question: $item->question(),
            answer: $item->answer(),
            category: $item->category(),
            sortOrder: $item->sortOrder(),
            isActive: $item->isActive(),
            createdAt: $item->createdAt(),
            updatedAt: $item->updatedAt()
        );
    }

    private function update(FaqItem $item): FaqItem
    {
        $sql = "UPDATE {$this->table} 
                SET question = :question, 
                    answer = :answer, 
                    category = :category, 
                    sort_order = :sort_order, 
                    is_active = :is_active, 
                    updated_at = NOW() 
                WHERE id = :id";

        $data = $item->toArray();
        $data['id'] = $item->id();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return $item;
    }

    public function delete(FaqItem $item): void
    {
        if ($item->id() === null) {
            return;
        }
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $item->id()]);
    }

    public function findAll(array $filters = []): array
    {
        $sql = "SELECT * FROM {$this->table} ";
        $params = [];
        $sql .= $this->buildWhereClause($filters, $params);
        $sql .= " ORDER BY sort_order ASC, id DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = FaqItem::fromArray($row);
        }

        return $items;
    }

    public function findPaginated(int $page, int $perPage, array $filters = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $offset = ($page - 1) * $perPage;

        $params = [];
        $where = $this->buildWhereClause($filters, $params);

        // Count total
        $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch items
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY sort_order ASC, id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $items = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = FaqItem::fromArray($row);
        }

        return [
            'items'        => $items,
            'total'        => $total,
            'pages'        => (int) ceil($total / $perPage),
            'current_page' => $page,
        ];
    }

    public function count(array $filters = []): int
    {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} {$where}");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * ساخت شرط WHERE به صورت داینامیک
     */
    private function buildWhereClause(array $filters, array &$params): string
    {
        $conditions = [];

        if (isset($filters['is_active'])) {
            $conditions[] = "is_active = :is_active";
            $params[':is_active'] = $filters['is_active'] ? 1 : 0;
        }

        if (!empty($filters['category'])) {
            $conditions[] = "category = :category";
            $params[':category'] = $filters['category'];
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(question LIKE :search OR answer LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        return empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
    }
}
