<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class UserModel
{
    public function __construct(private PDO $db)
    {
    }

    public function create(array $data): int
    {
        // Insert user with safe defaults; verification timestamp starts null.
        $stmt = $this->db->prepare(
              'INSERT INTO tms_users (name, email, password_hash, role, timezone, avatar_url, email_verified_at, created_at, updated_at)
             VALUES (:name, :email, :password_hash, :role, :timezone, :avatar_url, NULL, NOW(), NOW())'
        );

        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $data['password_hash'],
            'role' => $data['role'] ?? 'team_member',
            'timezone' => $data['timezone'] ?? 'UTC',
            'avatar_url' => $data['avatar_url'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findByEmail(string $email): ?array
    {
        // Used by login/register/invite checks.
        $stmt = $this->db->prepare('SELECT * FROM tms_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, role, timezone, avatar_url, email_verified_at, created_at FROM tms_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markVerified(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE tms_users SET email_verified_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function updateProfile(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
              'UPDATE tms_users
             SET name = :name, timezone = :timezone, avatar_url = :avatar_url, updated_at = NOW()
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'avatar_url' => $data['avatar_url'],
        ]);
    }

    public function storePasswordResetToken(int $id, string $tokenHash): void
    {
        // One-hour reset token window keeps the blast radius smaller.
        $stmt = $this->db->prepare(
              'INSERT INTO tms_password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())'
        );

        $stmt->execute([
            'user_id' => $id,
            'token_hash' => $tokenHash,
        ]);
    }

    public function findPasswordResetToken(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT pr.*, u.email
               FROM tms_password_resets pr
               JOIN tms_users u ON u.id = pr.user_id
             WHERE pr.token_hash = :token_hash AND pr.expires_at > NOW()
             LIMIT 1'
        );

        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE tms_users SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'password_hash' => $passwordHash,
        ]);
    }

    public function clearResetToken(int $tokenId): void
    {
        // Token is single-use by design.
        $stmt = $this->db->prepare('DELETE FROM tms_password_resets WHERE id = :id');
        $stmt->execute(['id' => $tokenId]);
    }
}
