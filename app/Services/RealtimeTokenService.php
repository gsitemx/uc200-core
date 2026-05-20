<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class RealtimeTokenService
{
    public function __construct(
        private readonly PDO $db,
        private readonly RealtimeConfigService $configService
    ) {
    }

    public function issue(array $claims, int $ttlSeconds = 900): array
    {
        $ttlSeconds = max(60, $ttlSeconds);
        $now = time();
        $expiresAt = $now + $ttlSeconds;

        $payload = array_merge([
            'iss' => rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/'),
            'aud' => 'uc200-realtime',
            'iat' => $now,
            'nbf' => $now - 5,
            'exp' => $expiresAt,
            'jti' => uuid(),
        ], $claims);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
        ];
        $signature = hash_hmac('sha256', implode('.', $segments), $this->configService->jwtSecret(), true);
        $token = implode('.', [...$segments, $this->base64UrlEncode($signature)]);

        return [
            'token' => $token,
            'expires_at' => gmdate('Y-m-d H:i:s', $expiresAt),
            'expires_in' => $ttlSeconds,
            'claims' => $payload,
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
