<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Services\JwtService;
use PDO;

abstract class BaseController
{
    // Shared DB handle and config for all child controllers.
    protected PDO $db;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->db = Database::connection($config);
        date_default_timezone_set($this->config['app']['timezone']);
    }

    protected function json(array $payload, int $status = 200): void
    {
        // Single JSON output helper so headers stay consistent everywhere.
        http_response_code($status);
        header('Content-Type: application/json');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        echo json_encode($payload);
    }

    protected function body(): array
    {
        // Parse JSON request body; return empty array instead of exploding.
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function currentUser(): ?array
    {
        // Read bearer token from Authorization header and decode JWT claims.
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        $service = new JwtService($this->config);
        return $service->decode($token);
    }

    protected function requireUser(): array
    {
        // Guard route: if no user, bail out early with 401.
        $user = $this->currentUser();
        if ($user === null) {
            $this->json(['error' => 'Unauthorized'], 401);
            exit;
        }
        return $user;
    }

    protected function requireRole(array $allowedRoles): array
    {
        // RBAC gate: easy check, clear error, no mixed signals.
        $user = $this->requireUser();
        if (!in_array($user['role'], $allowedRoles, true)) {
            $this->json(['error' => 'Forbidden'], 403);
            exit;
        }
        return $user;
    }
}
