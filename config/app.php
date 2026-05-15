<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'UC200 Core'),
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL),
    'url' => rtrim((string) env('APP_URL', 'http://127.0.0.1:8080'), '/'),
    'key' => env('APP_KEY', ''),

    'database' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => env('DB_DATABASE', 'uc200_core'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],

    'session' => [
        'name' => env('SESSION_NAME', 'uc200_session'),
        'lifetime' => (int) env('SESSION_LIFETIME', 7200),
        'secure' => filter_var(env('SESSION_SECURE', false), FILTER_VALIDATE_BOOL),
        'samesite' => env('SESSION_SAMESITE', 'Lax'),
        'path' => BASE_PATH . '/storage/sessions',
    ],
];
