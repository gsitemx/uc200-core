<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return '/' . trim($uri, '/');
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->json();

        return $_POST[$key] ?? $_GET[$key] ?? $json[$key] ?? $default;
    }

    public function only(array $keys): array
    {
        $data = [];

        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }

        return $data;
    }

    public function ip(): ?string
    {
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function userAgent(): ?string
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        return is_string($agent) ? substr($agent, 0, 255) : null;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        if (isset($_SERVER[$key])) {
            return (string) $_SERVER[$key];
        }

        if (strtolower($name) === 'authorization' && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        return $default;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization', '');

        if (! is_string($authorization) || ! str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return trim(substr($authorization, 7));
    }

    public function json(): array
    {
        static $payload = null;

        if ($payload !== null) {
            return $payload;
        }

        $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        if (! str_contains(strtolower($contentType), 'application/json')) {
            $payload = [];
            return $payload;
        }

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $payload = is_array($decoded) ? $decoded : [];

        return $payload;
    }
}
