<?php
/**
 * PartoCMS - Newsletter PDO Campaign Repository
 *
 * @package PartoCMS\Newsletter\Infrastructure\Persistence
 */

declare(strict_types=1);

namespace PartoCMS\Newsletter\Infrastructure\Persistence;

use DateTimeImmutable;
use PDO;
use PartoCMS\Newsletter\Domain\Entity\Campaign;
use PartoCMS\Newsletter\Domain\Repository\CampaignRepositoryInterface;
use PartoCMS\Newsletter\Domain\ValueObject\CampaignStatus;

final class PdoCampaignRepository implements CampaignRepositoryInterface
{
    private PDO $pdo;
    private string $table;

    public function __construct(PDO $pdo, string $table = 'newsletter_campaigns')
    {
        $this->pdo   = $pdo;
        $this->table = $table;
    }

    public function findById(int $id): ?Campaign
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? Campaign::fromArray($row) : null;
    }

    public function save(Campaign $campaign): Campaign
    {
        return $campaign->isNew()
            ? $this->insert($campaign)
            : $this->update($campaign);
    }

    private function insert(Campaign $c): Campaign
    {
        $d = $c->toArray();
        unset($d['id']);

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->table}
                (title, subject, preheader, body_html, body_text, status,
                 scheduled_at, started_at, completed_at,
                 sent_count, failed_count, open_count, click_count,
                 created_by, created_at, updated_at)
            VALUES
                (:title, :subject, :preheader, :body_html, :body_text, :status,
                 :scheduled_at, :started_at, :completed_at,
                 :sent_count, :failed_count, :open_count, :click_count,
                 :created_by, :created_at, :updated_at)
        ");

        $stmt->execute([
            ':title'        => $d['title'],
            ':subject'      => $d['subject'],
            ':preheader'    => $d['preheader'],
            ':body_html'    => $d['body_html'],
            ':body_text'    => $d['body_text'],
            ':status'       => $d['status'],
            ':scheduled_at' => $d['scheduled_at'],
            ':started_at'   => $d['started_at'],
            ':completed_at' => $d['completed_at'],
            ':sent_count'   => $d['sent_count'],
            ':failed_count' => $d['failed_count'],
            ':open_count'   => $d['open_count'],
            ':click_count'  => $d['click_count'],
            ':created_by'   => $d['created_by'],
            ':created_at'   => $d['created_at'],
            ':updated_at'   => $d['updated_at'],
        ]);

        return $this->findById((int) $this->pdo->lastInsertId()) ?? $c;
    }

    private function update(Campaign $c): Campaign
    {
        $d = $c->toArray();

        $stmt = $this->pdo->prepare("
            UPDATE {$this->table} SET
                title        = :title,
                subject      = :subject,
                preheader    = :preheader,
                body_html    = :body_html,
                body_text    = :body_text,
                status       = :status,
                scheduled_at = :scheduled_at,
                started_at   = :started_at,
                completed_at = :completed_at,
                sent_count   = :sent_count,
                failed_count = :failed_count,
                open_count   = :open_count,
                click_count  = :click_count,
                updated_at   = :updated_at
            WHERE id = :id
        ");

        $stmt->execute([
            ':id'           => $d['id'],
            ':title'        => $d['title'],
            ':subject'      => $d['subject'],
            ':preheader'    => $d['preheader'],
            ':body_html'    => $d['body_html'],
            ':body_text'    => $d['body_text'],
            ':status'       => $d['status'],
            ':scheduled_at' => $d['scheduled_at'],
            ':started_at'   => $d['started_at'],
            ':completed_at' => $d['completed_at'],
            ':sent_count'   => $d['sent_count'],
            ':failed_count' => $d['failed_count'],
            ':open_count'   => $d['open_count'],
            ':click_count'  => $d['click_count'],
            ':updated_at'   => $d['updated_at'],
        ]);

        return $c;
    }

    public function delete(Campaign $campaign): void
    {
        if ($campaign->id() === null) return;
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute([':id' => $campaign->id()]);
    }

    public function count(?CampaignStatus $status = null): int
    {
        if ($status === null) {
            return (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
        }
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = :s");
        $stmt->execute([':s' => $status->value]);
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
            $params[':status'] = $filters['status'] instanceof CampaignStatus
                ? $filters['status']->value
                : (string) $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[]                 = '(title LIKE :s_title OR subject LIKE :s_subject)';
            $params[':s_title']      = '%' . $filters['search'] . '%';
            $params[':s_subject']    = '%' . $filters['search'] . '%';
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
        return array_map(fn(array $r) => Campaign::fromArray($r), $rows);
    }

    public function findDueForSending(DateTimeImmutable $now): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE status = :scheduled
              AND scheduled_at IS NOT NULL
              AND scheduled_at <= :now
            ORDER BY scheduled_at ASC
        ");
        $stmt->execute([
            ':scheduled' => CampaignStatus::SCHEDULED->value,
            ':now'       => $now->format('Y-m-d H:i:s'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $r) => Campaign::fromArray($r), $rows);
    }

    public function findStuck(DateTimeImmutable $before): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->table}
            WHERE status = :sending
              AND started_at IS NOT NULL
              AND started_at < :before
            ORDER BY started_at ASC
        ");
        $stmt->execute([
            ':sending' => CampaignStatus::SENDING->value,
            ':before'  => $before->format('Y-m-d H:i:s'),
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn(array $r) => Campaign::fromArray($r), $rows);
    }

    public function getGlobalStats(): array
    {
        $sql = "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = :sent THEN 1 ELSE 0 END) AS sent,
            COALESCE(SUM(sent_count),   0) AS total_sent_emails,
            COALESCE(SUM(failed_count), 0) AS total_failed,
            COALESCE(SUM(open_count),   0) AS total_opened,
            COALESCE(SUM(click_count),  0) AS total_clicked
        FROM {$this->table}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':sent' => CampaignStatus::SENT->value]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total'             => (int) ($row['total']             ?? 0),
            'sent'              => (int) ($row['sent']              ?? 0),
            'total_sent_emails' => (int) ($row['total_sent_emails'] ?? 0),
            'total_failed'      => (int) ($row['total_failed']      ?? 0),
            'total_opened'      => (int) ($row['total_opened']      ?? 0),
            'total_clicked'     => (int) ($row['total_clicked']     ?? 0),
        ];
    }
}
