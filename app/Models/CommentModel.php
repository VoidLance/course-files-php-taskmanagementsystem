<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class CommentModel
{
    public function __construct(private PDO $db)
    {
    }

    public function create(int $taskId, int $userId, string $body): int
    {
        // Append comment to task thread.
        $stmt = $this->db->prepare(
              'INSERT INTO tms_comments (task_id, user_id, body, created_at, updated_at)
             VALUES (:task_id, :user_id, :body, NOW(), NOW())'
        );

        $stmt->execute([
            'task_id' => $taskId,
            'user_id' => $userId,
            'body' => $body,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function allByTask(int $taskId): array
    {
        // Return oldest-first so the conversation reads like normal humans talk.
        $stmt = $this->db->prepare(
            'SELECT c.*, u.name AS author_name
               FROM tms_comments c
               JOIN tms_users u ON u.id = c.user_id
             WHERE c.task_id = :task_id
             ORDER BY c.created_at ASC'
        );

        $stmt->execute(['task_id' => $taskId]);
        return $stmt->fetchAll();
    }
}
