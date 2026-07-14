<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class ProjectModel
{
    public function __construct(private PDO $db)
    {
    }

    public function create(int $ownerId, array $data): int
    {
        // Create project first; membership row comes right after.
        $stmt = $this->db->prepare(
              'INSERT INTO tms_projects (name, description, visibility, owner_id, is_archived, created_at, updated_at)
             VALUES (:name, :description, :visibility, :owner_id, 0, NOW(), NOW())'
        );

        $stmt->execute([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
            'owner_id' => $ownerId,
        ]);

        $projectId = (int) $this->db->lastInsertId();

        // Owner is auto-added as project manager, because manual steps are pain.
        $membership = $this->db->prepare(
              'INSERT INTO tms_project_members (project_id, user_id, role, created_at)
             VALUES (:project_id, :user_id, :role, NOW())'
        );
        $membership->execute([
            'project_id' => $projectId,
            'user_id' => $ownerId,
            'role' => 'project_manager',
        ]);

        return $projectId;
    }

    public function allForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*
               FROM tms_projects p
               JOIN tms_project_members pm ON pm.project_id = p.id
             WHERE pm.user_id = :user_id AND p.is_archived = 0
             ORDER BY p.updated_at DESC'
        );

        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function findById(int $projectId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tms_projects WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function userRoleInProject(int $projectId, int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT role FROM tms_project_members WHERE project_id = :project_id AND user_id = :user_id LIMIT 1');
        $stmt->execute([
            'project_id' => $projectId,
            'user_id' => $userId,
        ]);

        $row = $stmt->fetch();
        return $row['role'] ?? null;
    }

    public function update(int $projectId, array $data): void
    {
        $stmt = $this->db->prepare(
              'UPDATE tms_projects
             SET name = :name, description = :description, visibility = :visibility, updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $projectId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? 'private',
        ]);
    }

    public function archive(int $projectId): void
    {
        $stmt = $this->db->prepare('UPDATE tms_projects SET is_archived = 1, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $projectId]);
    }

    public function inviteUser(int $projectId, int $userId, string $role): void
    {
        // Upsert keeps invites idempotent: no duplicate rows, no drama.
        $stmt = $this->db->prepare(
              'INSERT INTO tms_project_members (project_id, user_id, role, created_at)
             VALUES (:project_id, :user_id, :role, NOW())
             ON DUPLICATE KEY UPDATE role = VALUES(role)'
        );

        $stmt->execute([
            'project_id' => $projectId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }
}
