<?php

declare(strict_types=1);

namespace App\Services;

class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct(array $config)
    {
        $this->secret = $config['app']['jwt_secret'];
        $this->ttl = (int) $config['app']['jwt_ttl'];
    }

    public function encode(array $claims): string
    {
        // Minimal JWT builder: header + payload + HMAC signature.
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = array_merge($claims, [
            'iat' => $now,
            'exp' => $now + $this->ttl,
        ]);

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES) ?: '{}'),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '{}'),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $this->secret, true);
        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public function decode(string $token): ?array
    {
        // Validate shape, signature, and expiration in that order.
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$header64, $payload64, $signature64] = $parts;
        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header64 . '.' . $payload64, $this->secret, true));

        if (!hash_equals($expected, $signature64)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($payload64), true);
        if (!is_array($payload)) {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string $input): string
    {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $input): string
    {
        // Base64url needs padding put back before classic base64_decode.
        $padding = strlen($input) % 4;
        if ($padding > 0) {
            $input .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($input, '-_', '+/')) ?: '';
    }
}
