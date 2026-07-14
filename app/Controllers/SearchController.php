<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\TaskModel;

class SearchController extends BaseController
{
    public function tasks(): void
    {
        // Pull optional query params and pass to model search builder.
        $user = $this->requireUser();

        $filters = [
            'query' => $_GET['query'] ?? null,
            'status' => $_GET['status'] ?? null,
            'priority' => $_GET['priority'] ?? null,
            'assignee_id' => $_GET['assignee_id'] ?? null,
        ];

        $tasks = (new TaskModel($this->db))->search((int) $user['sub'], $filters);
        $this->json(['tasks' => $tasks]);
    }
}
