<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\JwtService;
use App\Services\MailService;

class AuthController extends BaseController
{
    public function register(): void
    {
        $data = $this->body();

        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            $this->json(['error' => 'Name, email, and password are required'], 422);
            return;
        }

        $users = new UserModel($this->db);
        // Duplicate emails are a fast track to account chaos.
        if ($users->findByEmail($data['email'])) {
            $this->json(['error' => 'Email already registered'], 409);
            return;
        }

        // Create user first, verify later: bureaucracy but secure.
        $userId = $users->create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'timezone' => $data['timezone'] ?? 'UTC',
            'role' => $data['role'] ?? 'team_member',
        ]);

        // Raw token goes to "mail" log; DB stores only hash.
        $token = bin2hex(random_bytes(16));
        $tokenHash = hash('sha256', $token);

        $stmt = $this->db->prepare(
              'INSERT INTO tms_email_verifications (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 24 HOUR), NOW())'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
        ]);

        (new MailService($this->config))->send(
            strtolower(trim($data['email'])),
            'Verify your email',
            'Use this token to verify your account: ' . $token
        );

        $this->json([
            'message' => 'Registered successfully. Check mail log for verification token.',
            'user_id' => $userId,
        ], 201);
    }

    public function verifyEmail(): void
    {
        $data = $this->body();
        $token = trim((string) ($data['token'] ?? ''));

        if ($token === '') {
            $this->json(['error' => 'Token is required'], 422);
            return;
        }

        // Hash incoming token and compare server-side, never plain token storage.
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare(
            'SELECT * FROM tms_email_verifications WHERE token_hash = :token_hash AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['token_hash' => $tokenHash]);
        $verification = $stmt->fetch();

        if (!$verification) {
            $this->json(['error' => 'Invalid or expired token'], 400);
            return;
        }

        $users = new UserModel($this->db);
        $users->markVerified((int) $verification['user_id']);

        $cleanup = $this->db->prepare('DELETE FROM tms_email_verifications WHERE id = :id');
        $cleanup->execute(['id' => $verification['id']]);

        $this->json(['message' => 'Email verified. You are now officially real.']);
    }

    public function login(): void
    {
        $data = $this->body();

        if (empty($data['email']) || empty($data['password'])) {
            $this->json(['error' => 'Email and password are required'], 422);
            return;
        }

        $users = new UserModel($this->db);
        $user = $users->findByEmail(strtolower(trim($data['email'])));

        // If password hash says no, we say no.
        if (!$user || !password_verify($data['password'], $user['password_hash'])) {
            $this->json(['error' => 'Invalid credentials'], 401);
            return;
        }

        if (empty($user['email_verified_at'])) {
            $this->json(['error' => 'Please verify your email before login'], 403);
            return;
        }

        // JWT keeps API stateless and our sessions lightweight.
        $jwt = new JwtService($this->config);
        $token = $jwt->encode([
            'sub' => (int) $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'name' => $user['name'],
        ]);

        $this->json([
            'token' => $token,
            'user' => [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'timezone' => $user['timezone'],
            ],
        ]);
    }

    public function requestPasswordReset(): void
    {
        $data = $this->body();
        $email = strtolower(trim((string) ($data['email'] ?? '')));

        if ($email === '') {
            $this->json(['error' => 'Email is required'], 422);
            return;
        }

        $users = new UserModel($this->db);
        $user = $users->findByEmail($email);

        // Return generic response to avoid leaking which emails exist.
        if ($user) {
            $token = bin2hex(random_bytes(16));
            $users->storePasswordResetToken((int) $user['id'], hash('sha256', $token));
            (new MailService($this->config))->send(
                $email,
                'Password reset request',
                'Use this token to reset your password: ' . $token
            );
        }

        $this->json(['message' => 'If that email exists, a reset token was sent to the mail log.']);
    }

    public function resetPassword(): void
    {
        $data = $this->body();
        $token = trim((string) ($data['token'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($token === '' || $password === '') {
            $this->json(['error' => 'Token and new password are required'], 422);
            return;
        }

        $users = new UserModel($this->db);
        $record = $users->findPasswordResetToken(hash('sha256', $token));

        if (!$record) {
            $this->json(['error' => 'Invalid or expired reset token'], 400);
            return;
        }

        $users->updatePassword((int) $record['user_id'], password_hash($password, PASSWORD_DEFAULT));
        $users->clearResetToken((int) $record['id']);

        $this->json(['message' => 'Password updated successfully']);
    }
}
