<?php
/**
 * PartoCMS - Newsletter PDO SendLog Repository
 *
 * @package PartoCMS\Newsletter\Infrastructure\Persistence
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Infrastructure\Persistence;

use PDO;
use PartoCMS\Newsletter\Domain\Entity\SendLog;
use PartoCMS\Newsletter\Domain\Repository\SendLogRepositoryInterface;
use PartoCMS\Newsletter\Domain\ValueObject\SendStatus;

final class PdoSendLogRepository implements SendLogRepositoryInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table = 'newsletter_sends')
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    public function findById(int $id): ?SendLog
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? SendLog::fromArray($row) : null;
    }

    public function findByCampaignAndSubscriber(int $campaignId, int $subscriberId): ?SendLog
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE campaign_id = :cid AND subscriber_id = :sid
            LIMIT 1
        ");
        $stmt->execute([':cid' => $campaignId, ':sid' => $subscriberId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? SendLog::fromArray($row) : null;
    }

    public function existsForCampaign(int $campaignId, int $subscriberId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT 1 FROM {$this->table}
            WHERE campaign_id = :cid AND subscriber_id = :sid
            LIMIT 1
        ");
        $stmt->execute([':cid' => $campaignId, ':sid' => $subscriberId]);
        return (bool) $stmt->fetchColumn();
    }

    public function save(SendLog $log): SendLog
    {
        return $log->isNew()
            ? $this->insert($log)
            : $this->update($log);
    }

    private function insert(SendLog $l): SendLog
    {
        $d = $l->toArray();
        unset($d['id']);

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table}
                (campaign_id, subscriber_id, status, message_id, error_message,
                 sent_at, opened_at, clicked_at, created_at)
            VALUES
                (:campaign_id, :subscriber_id, :status, :message_id, :error_message,
                 :sent_at, :opened_at, :clicked_at, :created_at)
        ");

        $stmt->execute([
            ':campaign_id'   => $d['campaign_id'],
            ':subscriber_id' => $d['subscriber_id'],
            ':status'        => $d['status'],
            ':message_id'    => $d['message_id'],
            ':error_message' => $d['error_message'],
            ':sent_at'       => $d['sent_at'],
            ':opened_at'     => $d['opened_at'],
            ':clicked_at'    => $d['clicked_at'],
            ':created_at'    => $d['created_at'],
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? $l;
    }

    private function update(SendLog $l): SendLog
    {
        $d = $l->toArray();

        $stmt = $this->pdo->prepare("
            UPDATE {$this->table} SET
                status        = :status,
                message_id    = :message_id,
                error_message = :error_message,
                sent_at       = :sent_at,
                opened_at     = :opened_at,
                clicked_at    = :clicked_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':id'            => $d['id'],
            ':status'        => $d['status'],
            ':message_id'    => $d['message_id'],
            ':error_message' => $d['error_message'],
            ':sent_at'       => $d['sent_at'],
            ':opened_at'     => $d['opened_at'],
            ':clicked_at'    => $d['clicked_at'],
        ]);

        return $l;
    }

    /**
     * Bulk insert — wrapped in a transaction for performance.
     *
     * @param SendLog[] $logs
     */
    public function saveMany(array $logs): void
    {
        if (empty($logs)) return;

        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO {$this->table}
                    (campaign_id, subscriber_id, status, message_id, error_message,
                     sent_at, opened_at, clicked_at, created_at)
                VALUES
                    (:campaign_id, :subscriber_id, :status, :message_id, :error_message,
                     :sent_at, :opened_at, :clicked_at, :created_at)
            ");

            foreach ($logs as $log) {
                $d = $log->toArray();
                unset($d['id']);
                $stmt->execute([
                    ':campaign_id'   => $d['campaign_id'],
                    ':subscriber_id' => $d['subscriber_id'],
                    ':status'        => $d['status'],
                    ':message_id'    => $d['message_id'],
                    ':error_message' => $d['error_message'],
                    ':sent_at'       => $d['sent_at'],
                    ':opened_at'     => $d['opened_at'],
                    ':clicked_at'    => $d['clicked_at'],
                    ':created_at'    => $d['created_at'],
                ]);
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function delete(SendLog $log): void
    {
        if ($log->id() === null) return;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $log->id()]);
    }

    public function deleteByCampaign(int $campaignId): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE campaign_id = :cid");
        $stmt->execute([':cid' => $campaignId]);
    }

    public function count(int $campaignId, ?SendStatus $status = null): int
    {
        if ($status === null) {
            $stmt = $this->pdo->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE campaign_id = :cid"
            );
            $stmt->execute([':cid' => $campaignId]);
            return (int) $stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM {$this->table}
            WHERE campaign_id = :cid AND status = :status
        ");
        $stmt->execute([':cid' => $campaignId, ':status' => $status->value]);
        return (int) $stmt->fetchColumn();
    }

    public function fetchQueued(int $campaignId, int $limit = 50): array
    {
        $limit = max(1, min(500, $limit));

        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE campaign_id = :cid AND status = :status
            ORDER BY id ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':cid',    $campaignId, PDO::PARAM_INT);
        $stmt->bindValue(':status', SendStatus::QUEUED->value, PDO::PARAM_STR);
        $stmt->bindValue(':limit',  $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $r) => SendLog::fromArray($r), $rows);
    }

    public function getStatusBreakdown(int $campaignId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT status, COUNT(*) AS cnt
            FROM {$this->table}
            WHERE campaign_id = :cid
            GROUP BY status
        ");
        $stmt->execute([':cid' => $campaignId]);

        $result = array_fill_keys(SendStatus::all(), 0);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[$row['status']] = (int) $row['cnt'];
        }
        return $result;
    }
}
