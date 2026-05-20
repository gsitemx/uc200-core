<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class RealtimeConfigService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function settings(?int $companyId = null): array
    {
        $path = trim((string) $this->setting($companyId, 'realtime.socket_path', (string) env('REALTIME_SOCKET_PATH', '/socket.io')));
        if ($path === '') {
            $path = '/socket.io';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $publicUrl = rtrim((string) $this->setting($companyId, 'realtime.public_url', (string) env('REALTIME_PUBLIC_URL', '')), '/');
        $internalUrl = rtrim((string) $this->setting($companyId, 'realtime.internal_url', (string) env('REALTIME_INTERNAL_URL', 'http://127.0.0.1:3100')), '/');
        $ttl = max(60, (int) $this->setting($companyId, 'realtime.jwt_ttl_seconds', (int) env('REALTIME_JWT_TTL_SECONDS', 900)));

        return [
            'enabled' => $this->toBool($this->setting($companyId, 'realtime.enabled', env('REALTIME_ENABLED', true))),
            'public_url' => $publicUrl,
            'internal_url' => $internalUrl,
            'socket_path' => $path,
            'socket_script' => ($publicUrl !== '' ? $publicUrl : '') . rtrim($path, '/') . '/socket.io.js',
            'jwt_ttl_seconds' => $ttl,
            'allow_origins' => (string) $this->setting($companyId, 'realtime.allow_origins', (string) env('REALTIME_ALLOW_ORIGINS', '*')),
        ];
    }

    public function jwtSecret(): string
    {
        $secret = trim((string) env('REALTIME_JWT_SECRET', ''));
        if ($secret !== '') {
            return $secret;
        }

        $appKey = trim((string) env('APP_KEY', ''));
        if ($appKey !== '') {
            return hash('sha256', 'uc200-realtime|' . $appKey);
        }

        return hash('sha256', 'uc200-realtime|fallback-secret');
    }

    public function health(?int $companyId = null): array
    {
        $settings = $this->settings($companyId);
        $url = $settings['internal_url'] !== '' ? $settings['internal_url'] . '/health' : '';
        if ($url === '') {
            return ['reachable' => false, 'message' => 'Realtime internal URL not configured.'];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1.5,
                'ignore_errors' => true,
            ],
        ]);

        try {
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                return ['reachable' => false, 'message' => 'Realtime service unreachable.'];
            }

            $decoded = json_decode($response, true);

            return [
                'reachable' => true,
                'message' => (string) (($decoded['status'] ?? 'ok')),
                'payload' => is_array($decoded) ? $decoded : [],
            ];
        } catch (\Throwable) {
            return ['reachable' => false, 'message' => 'Realtime service unreachable.'];
        }
    }

    private function setting(?int $companyId, string $key, mixed $default = null): mixed
    {
        $statement = $this->db->prepare(
            'SELECT setting_value
             FROM settings
             WHERE setting_key = :setting_key
               AND deleted_at IS NULL
               AND (company_id = :company_id OR company_id IS NULL)
             ORDER BY company_id DESC
             LIMIT 1'
        );
        $statement->execute([
            'setting_key' => $key,
            'company_id' => $companyId,
        ]);

        $value = $statement->fetchColumn();
        if ($value === false || $value === null) {
            return $default;
        }

        return $this->decodeScalar((string) $value, $default);
    }

    private function decodeScalar(string $value, mixed $default): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return $default;
        }

        try {
            $decoded = json_decode($trimmed, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($decoded) && ! is_object($decoded)) {
                return $decoded;
            }
        } catch (\Throwable) {
        }

        return $trimmed;
    }

    private function toBool(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }
}
