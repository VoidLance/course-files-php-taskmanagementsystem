<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

class ActivityService
{
    public function __construct(private PDO $db)
    {
    }

    public function log(int $projectId, int $taskId, int $userId, string $action, string $details = ''): void
    {
        // One-liner activity helper used after task/comment mutations.
        $stmt = $this->db->prepare(
              'INSERT INTO tms_activity_logs (project_id, task_id, user_id, action, details, created_at)
             VALUES (:project_id, :task_id, :user_id, :action, :details, NOW())'
        );

        $stmt->execute([
            'project_id' => $projectId,
            'task_id' => $taskId,
            'user_id' => $userId,
            'action' => $action,
            'details' => $details,
        ]);
    }
}
