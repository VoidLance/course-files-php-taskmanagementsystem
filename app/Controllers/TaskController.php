<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CommentModel;
use App\Models\ProjectModel;
use App\Models\TaskModel;
use App\Services\ActivityService;

class TaskController extends BaseController
{
    private function canAccessProject(int $projectId, int $userId): bool
    {
        // Membership check: no membership, no peeking.
        return (new ProjectModel($this->db))->userRoleInProject($projectId, $userId) !== null;
    }

    public function listByProject(array $params): void
    {
        // Board load endpoint: all tasks for one project.
        $user = $this->requireUser();
        $projectId = (int) $params['projectId'];

        if (!$this->canAccessProject($projectId, (int) $user['sub'])) {
            $this->json(['error' => 'Project access denied'], 403);
            return;
        }

        $tasks = (new TaskModel($this->db))->allByProject($projectId);
        $this->json(['tasks' => $tasks]);
    }

    public function create(array $params): void
    {
        // Create task in project and log action for timeline.
        $user = $this->requireUser();
        $projectId = (int) $params['projectId'];

        if (!$this->canAccessProject($projectId, (int) $user['sub'])) {
            $this->json(['error' => 'Project access denied'], 403);
            return;
        }

        $data = $this->body();
        if (empty($data['title'])) {
            $this->json(['error' => 'Task title is required'], 422);
            return;
        }

        $tasks = new TaskModel($this->db);
        $taskId = $tasks->create($projectId, (int) $user['sub'], $data);

        (new ActivityService($this->db))->log($projectId, $taskId, (int) $user['sub'], 'task_created', $data['title']);

        $this->json(['message' => 'Task created', 'task_id' => $taskId], 201);
    }

    public function update(array $params): void
    {
        // Generic task update endpoint (status, assignee, times, etc.).
        $user = $this->requireUser();
        $taskId = (int) $params['id'];
        $tasks = new TaskModel($this->db);
        $task = $tasks->findById($taskId);

        if (!$task || !$this->canAccessProject((int) $task['project_id'], (int) $user['sub'])) {
            $this->json(['error' => 'Task not found or access denied'], 404);
            return;
        }

        $tasks->update($taskId, $this->body());

        (new ActivityService($this->db))->log((int) $task['project_id'], $taskId, (int) $user['sub'], 'task_updated', 'Task fields updated');

        $this->json(['message' => 'Task updated']);
    }

    public function move(array $params): void
    {
        // Dedicated move endpoint used by drag-and-drop board UI.
        $user = $this->requireUser();
        $taskId = (int) $params['id'];
        $tasks = new TaskModel($this->db);
        $task = $tasks->findById($taskId);

        if (!$task || !$this->canAccessProject((int) $task['project_id'], (int) $user['sub'])) {
            $this->json(['error' => 'Task not found or access denied'], 404);
            return;
        }

        $data = $this->body();
        $columnName = (string) ($data['column_name'] ?? 'todo');
        $position = (int) ($data['position'] ?? 0);

        $tasks->move($taskId, $columnName, $position);

        (new ActivityService($this->db))->log((int) $task['project_id'], $taskId, (int) $user['sub'], 'task_moved', 'Moved to ' . $columnName);

        $this->json(['message' => 'Task moved']);
    }

    public function delete(array $params): void
    {
        // Delete task and leave an activity breadcrumb.
        $user = $this->requireUser();
        $taskId = (int) $params['id'];
        $tasks = new TaskModel($this->db);
        $task = $tasks->findById($taskId);

        if (!$task || !$this->canAccessProject((int) $task['project_id'], (int) $user['sub'])) {
            $this->json(['error' => 'Task not found or access denied'], 404);
            return;
        }

        $tasks->delete($taskId);

        (new ActivityService($this->db))->log((int) $task['project_id'], $taskId, (int) $user['sub'], 'task_deleted', 'Task deleted');

        $this->json(['message' => 'Task deleted']);
    }

    public function addComment(array $params): void
    {
        // Add comment, parse @mentions, and log the event.
        $user = $this->requireUser();
        $taskId = (int) $params['id'];
        $tasks = new TaskModel($this->db);
        $task = $tasks->findById($taskId);

        if (!$task || !$this->canAccessProject((int) $task['project_id'], (int) $user['sub'])) {
            $this->json(['error' => 'Task not found or access denied'], 404);
            return;
        }

        $body = trim((string) (($this->body())['body'] ?? ''));
        if ($body === '') {
            $this->json(['error' => 'Comment body is required'], 422);
            return;
        }

        $commentId = (new CommentModel($this->db))->create($taskId, (int) $user['sub'], $body);

        preg_match_all('/@([a-zA-Z0-9_]+)/', $body, $matches);
        $mentions = $matches[1] ?? [];

        (new ActivityService($this->db))->log((int) $task['project_id'], $taskId, (int) $user['sub'], 'comment_added', json_encode($mentions) ?: '[]');

        $this->json(['message' => 'Comment added', 'comment_id' => $commentId, 'mentions' => $mentions], 201);
    }

    public function comments(array $params): void
    {
        // Fetch ordered comment thread for task details panel.
        $user = $this->requireUser();
        $taskId = (int) $params['id'];
        $tasks = new TaskModel($this->db);
        $task = $tasks->findById($taskId);

        if (!$task || !$this->canAccessProject((int) $task['project_id'], (int) $user['sub'])) {
            $this->json(['error' => 'Task not found or access denied'], 404);
            return;
        }

        $comments = (new CommentModel($this->db))->allByTask($taskId);
        $this->json(['comments' => $comments]);
    }

    public function activity(array $params): void
    {
        // Recent activity feed for a single task.
        $user = $this->requireUser();
        $taskId = (int) $params['id'];
        $tasks = new TaskModel($this->db);
        $task = $tasks->findById($taskId);

        if (!$task || !$this->canAccessProject((int) $task['project_id'], (int) $user['sub'])) {
            $this->json(['error' => 'Task not found or access denied'], 404);
            return;
        }

        $stmt = $this->db->prepare(
              'SELECT al.*, u.name AS actor_name
               FROM tms_activity_logs al
               JOIN tms_users u ON u.id = al.user_id
             WHERE al.task_id = :task_id
             ORDER BY al.created_at DESC LIMIT 200'
        );
        $stmt->execute(['task_id' => $taskId]);

        $this->json(['activity' => $stmt->fetchAll()]);
    }
}
