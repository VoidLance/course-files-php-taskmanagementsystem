<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;

class UserController extends BaseController
{
    public function me(): void
    {
        // Return a sanitized profile payload for current JWT user.
        $user = $this->requireUser();
        $record = (new UserModel($this->db))->findById((int) $user['sub']);
        $this->json(['user' => $record]);
    }

    public function updateProfile(): void
    {
        // Basic profile update: name, timezone, and avatar URL.
        $user = $this->requireUser();
        $data = $this->body();

        $name = trim((string) ($data['name'] ?? ''));
        $timezone = trim((string) ($data['timezone'] ?? 'UTC'));
        $avatarUrl = trim((string) ($data['avatar_url'] ?? ''));

        if ($name === '') {
            $this->json(['error' => 'Name is required'], 422);
            return;
        }

        (new UserModel($this->db))->updateProfile((int) $user['sub'], [
            'name' => $name,
            'timezone' => $timezone,
            'avatar_url' => $avatarUrl,
        ]);

        $this->json(['message' => 'Profile updated']);
    }
}
