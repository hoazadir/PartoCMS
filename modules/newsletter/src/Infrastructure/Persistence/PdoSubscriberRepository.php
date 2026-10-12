<?php
/**
 * PartoCMS - Newsletter PDO Subscriber Repository
 *
 * Concrete implementation of SubscriberRepositoryInterface
 * using PDO. All queries use prepared statements.
 *
 * @package PartoCMS\Newsletter\Infrastructure\Persistence
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Infrastructure\Persistence;

use PDO;
use PartoCMS\Newsletter\Domain\Entity\Subscriber;
use PartoCMS\Newsletter\Domain\Repository\SubscriberRepositoryInterface;
use PartoCMS\Newsletter\Domain\ValueObject\Email;
use PartoCMS\Newsletter\Domain\ValueObject\SubscriberStatus;

final class PdoSubscriberRepository implements SubscriberRepositoryInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table = 'newsletter_subscribers')
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    public function findById(int $id): ?Subscriber
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Subscriber::fromArray($row) : null;
    }

    public function findByEmail(Email $email): ?Subscriber
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email->value()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Subscriber::fromArray($row) : null;
    }

    public function findByToken(string $token): ?Subscriber
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE token = :token LIMIT 1"
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Subscriber::fromArray($row) : null;
    }

    public function exists(Email $email): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM {$this->table} WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email->value()]);
        return (bool) $stmt->fetchColumn();
    }

    public function save(Subscriber $subscriber): Subscriber
    {
        if ($subscriber->isNew()) {
            return $this->insert($subscriber);
        }
        return $this->update($subscriber);
    }

    private function insert(Subscriber $s): Subscriber
    {
        $data = $s->toArray();
        unset($data['id']);

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table}
                (email, name, status, token, confirmed_at, unsubscribed_at,
                 source, ip, user_agent, metadata, created_at, updated_at)
            VALUES
                (:email, :name, :status, :token, :confirmed_at, :unsubscribed_at,
                 :source, :ip, :user_agent, :metadata, :created_at, :updated_at)
        ");

        $stmt->execute([
            ':email'           => $data['email'],
            ':name'            => $data['name'],
            ':status'          => $data['status'],
            ':token'           => $data['token'],
            ':confirmed_at'    => $data['confirmed_at'],
            ':unsubscribed_at' => $data['unsubscribed_at'],
            ':source'          => $data['source'],
            ':ip'              => $data['ip'],
            ':user_agent'      => $data['user_agent'],
            ':metadata'        => $data['metadata'],
            ':created_at'      => $data['created_at'],
            ':updated_at'      => $data['updated_at'],
        ]);

        $newId = (int) $this->pdo->lastInsertId();

        // Reload to get DB-populated fields (and consistent state)
        return $this->findById($newId) ?? $s;
    }

    private function update(Subscriber $s): Subscriber
    {
        $data = $s->toArray();

        $stmt = $this->pdo->prepare("
            UPDATE {$this->table} SET
                email           = :email,
                name            = :name,
                status          = :status,
                token           = :token,
                confirmed_at    = :confirmed_at,
                unsubscribed_at = :unsubscribed_at,
                source          = :source,
                ip              = :ip,
                user_agent      = :user_agent,
                metadata        = :metadata,
                updated_at      = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':id'              => $data['id'],
            ':email'           => $data['email'],
            ':name'            => $data['name'],
            ':status'          => $data['status'],
            ':token'           => $data['token'],
            ':confirmed_at'    => $data['confirmed_at'],
            ':unsubscribed_at' => $data['unsubscribed_at'],
            ':source'          => $data['source'],
            ':ip'              => $data['ip'],
            ':user_agent'      => $data['user_agent'],
            ':metadata'        => $data['metadata'],
            ':updated_at'      => $data['updated_at'],
        ]);

        return $s;
    }

    public function delete(Subscriber $subscriber): void
    {
        if ($subscriber->id() === null) {
            return;
        }
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->table} WHERE id = :id"
        );
        $stmt->execute([':id' => $subscriber->id()]);
    }

    public function count(?SubscriberStatus $status = null): int
    {
        if ($status === null) {
            return (int) $this->pdo->query(
                "SELECT COUNT(*) FROM {$this->table}"
            )->fetchColumn();
        }

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = :status"
        );
        $stmt->execute([':status' => $status->value]);
        return (int) $stmt->fetchColumn();
    }

    public function findPaginated(int $page, int $perPage, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $where  = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[]           = 'status = :status';
            $params[':status'] = $filters['status'] instanceof SubscriberStatus
                ? $filters['status']->value
                : (string) $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]                  = '(email LIKE :search_email OR name LIKE :search_name)';
            $params[':search_email']  = '%' . $filters['search'] . '%';
            $params[':search_name']   = '%' . $filters['search'] . '%';
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            {$whereSql}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $r) => Subscriber::fromArray($r), $rows);
    }

    public function findAllActive(): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM {$this->table} WHERE status = :status ORDER BY id ASC"
        );
        $stmt->execute([':status' => SubscriberStatus::ACTIVE->value]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $r) => Subscriber::fromArray($r), $rows);
    }
}
