<?php
declare(strict_types=1);

namespace PartoCMS\PageBuilder\Infrastructure;

use PDO;

class PageRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getAll(): array
    {
        return $this->db->query("SELECT * FROM custom_pages ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM custom_pages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM custom_pages WHERE slug = ? AND status = 'published'");
        $stmt->execute([$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function save(array $data): bool
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare("UPDATE custom_pages SET title=?, slug=?, layout_json=?, status=? WHERE id=?");
            return $stmt->execute([$data['title'], $data['slug'], $data['layout_json'], $data['status'], $data['id']]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO custom_pages (title, slug, layout_json, status) VALUES (?, ?, ?, ?)");
            return $stmt->execute([$data['title'], $data['slug'], $data['layout_json'], $data['status']]);
        }
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM custom_pages WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
