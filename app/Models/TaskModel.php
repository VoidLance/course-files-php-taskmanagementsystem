<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class TaskModel
{
    public function __construct(private PDO $db)
    {
    }

    public function allByProject(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.name AS assignee_name
               FROM tms_tasks t
               LEFT JOIN tms_users u ON u.id = t.assignee_id
             WHERE t.project_id = :project_id
             ORDER BY t.column_name, t.position ASC, t.updated_at DESC'
        );

        $stmt->execute(['project_id' => $projectId]);
        return $stmt->fetchAll();
    }

    public function create(int $projectId, int $creatorId, array $data): int
    {
        // Starter defaults keep new tasks valid even with minimal form input.
        $stmt = $this->db->prepare(
            'INSERT INTO tms_tasks (
                project_id, creator_id, assignee_id, title, description, column_name, status,
                priority, due_date, labels, position, estimated_minutes, tracked_minutes,
                created_at, updated_at
             ) VALUES (
                :project_id, :creator_id, :assignee_id, :title, :description, :column_name, :status,
                :priority, :due_date, :labels, :position, :estimated_minutes, 0,
                NOW(), NOW()
             )'
        );

        $stmt->execute([
            'project_id' => $projectId,
            'creator_id' => $creatorId,
            'assignee_id' => $data['assignee_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'column_name' => $data['column_name'] ?? 'todo',
            'status' => $data['status'] ?? 'not_started',
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['due_date'] ?? null,
            'labels' => isset($data['labels']) ? json_encode($data['labels']) : json_encode([]),
            'position' => (int) ($data['position'] ?? 999),
            'estimated_minutes' => (int) ($data['estimated_minutes'] ?? 0),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $taskId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tms_tasks WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $taskId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function update(int $taskId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE tms_tasks
             SET assignee_id = :assignee_id,
                 title = :title,
                 description = :description,
                 column_name = :column_name,
                 status = :status,
                 priority = :priority,
                 due_date = :due_date,
                 labels = :labels,
                 estimated_minutes = :estimated_minutes,
                 tracked_minutes = :tracked_minutes,
                 updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $taskId,
            'assignee_id' => $data['assignee_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'column_name' => $data['column_name'] ?? 'todo',
            'status' => $data['status'] ?? 'not_started',
            'priority' => $data['priority'] ?? 'medium',
            'due_date' => $data['due_date'] ?? null,
            'labels' => isset($data['labels']) ? json_encode($data['labels']) : json_encode([]),
            'estimated_minutes' => (int) ($data['estimated_minutes'] ?? 0),
            'tracked_minutes' => (int) ($data['tracked_minutes'] ?? 0),
        ]);
    }

    public function delete(int $taskId): void
    {
        $stmt = $this->db->prepare('DELETE FROM tms_tasks WHERE id = :id');
        $stmt->execute(['id' => $taskId]);
    }

    public function move(int $taskId, string $columnName, int $position): void
    {
        $stmt = $this->db->prepare(
              'UPDATE tms_tasks
             SET column_name = :column_name, position = :position, updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $taskId,
            'column_name' => $columnName,
            'position' => $position,
        ]);
    }

    public function search(int $userId, array $filters): array
    {
        // Build query gradually so optional filters stay optional.
        $query = 'SELECT t.*
              FROM tms_tasks t
              JOIN tms_project_members pm ON pm.project_id = t.project_id
                  WHERE pm.user_id = :user_id';

        $params = ['user_id' => $userId];

        if (!empty($filters['query'])) {
            $query .= ' AND (t.title LIKE :q OR t.description LIKE :q)';
            $params['q'] = '%' . $filters['query'] . '%';
        }

        if (!empty($filters['status'])) {
            $query .= ' AND t.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $query .= ' AND t.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['assignee_id'])) {
            $query .= ' AND t.assignee_id = :assignee_id';
            $params['assignee_id'] = (int) $filters['assignee_id'];
        }

        $query .= ' ORDER BY t.updated_at DESC LIMIT 100';

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
