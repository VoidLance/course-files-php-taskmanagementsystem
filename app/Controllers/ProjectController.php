<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ProjectModel;
use App\Models\UserModel;

class ProjectController extends BaseController
{
    public function index(): void
    {
        // Return projects this user belongs to, newest first.
        $user = $this->requireUser();
        $projects = (new ProjectModel($this->db))->allForUser((int) $user['sub']);
        $this->json(['projects' => $projects]);
    }

    public function create(): void
    {
        // Only admins/project managers can spin up new projects.
        $user = $this->requireRole(['admin', 'project_manager']);
        $data = $this->body();

        if (empty($data['name'])) {
            $this->json(['error' => 'Project name is required'], 422);
            return;
        }

        $projectId = (new ProjectModel($this->db))->create((int) $user['sub'], $data);
        $this->json(['message' => 'Project created', 'project_id' => $projectId], 201);
    }

    public function update(array $params): void
    {
        $user = $this->requireUser();
        $projectId = (int) $params['id'];
        $projects = new ProjectModel($this->db);

        // Project manager or admin required for edits.
        $role = $projects->userRoleInProject($projectId, (int) $user['sub']);
        if (!in_array($role, ['project_manager'], true) && $user['role'] !== 'admin') {
            $this->json(['error' => 'You cannot edit this project'], 403);
            return;
        }

        $projects->update($projectId, $this->body());
        $this->json(['message' => 'Project updated']);
    }

    public function archive(array $params): void
    {
        $user = $this->requireUser();
        $projectId = (int) $params['id'];
        $projects = new ProjectModel($this->db);

        // Same permission rule as update; archives are still power moves.
        $role = $projects->userRoleInProject($projectId, (int) $user['sub']);
        if (!in_array($role, ['project_manager'], true) && $user['role'] !== 'admin') {
            $this->json(['error' => 'You cannot archive this project'], 403);
            return;
        }

        $projects->archive($projectId);
        $this->json(['message' => 'Project archived']);
    }

    public function invite(array $params): void
    {
        $user = $this->requireUser();
        $projectId = (int) $params['id'];
        $projects = new ProjectModel($this->db);

        // Prevent random teammates from inviting random strangers.
        $role = $projects->userRoleInProject($projectId, (int) $user['sub']);
        if (!in_array($role, ['project_manager'], true) && $user['role'] !== 'admin') {
            $this->json(['error' => 'Only managers/admins can invite users'], 403);
            return;
        }

        $data = $this->body();
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            $this->json(['error' => 'Email is required'], 422);
            return;
        }

        $target = (new UserModel($this->db))->findByEmail($email);
        if (!$target) {
            $this->json(['error' => 'User not found'], 404);
            return;
        }

        $projects->inviteUser($projectId, (int) $target['id'], $data['role'] ?? 'team_member');
        $this->json(['message' => 'User invited to project']);
    }
}
