<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Response;

final class ApiResponse
{
    public static function success(array $data = [], array $meta = [], int $status = 200): Response
    {
        $payload = ['success' => true, 'data' => $data];

        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return self::json($payload, $status);
    }

    public static function error(string $code, string $message, int $status = 400, array $details = []): Response
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return self::json($payload, $status);
    }

    private static function json(array $payload, int $status): Response
    {
        return new Response(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json']
        );
    }
}
