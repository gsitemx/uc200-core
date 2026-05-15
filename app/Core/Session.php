<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $path = (string) ($config['path'] ?? '');

        if ($path !== '') {
            if (! is_dir($path) && ! mkdir($path, 0750, true) && ! is_dir($path)) {
                throw new \RuntimeException('Unable to create session directory: ' . $path);
            }

            if (! is_readable($path) || ! is_writable($path)) {
                throw new \RuntimeException('Session directory must be readable and writable by the web server: ' . $path);
            }

            session_save_path($path);
        }

        session_name((string) ($config['name'] ?? 'uc200_session'));
        session_set_cookie_params([
            'lifetime' => (int) ($config['lifetime'] ?? 7200),
            'path' => '/',
            'domain' => '',
            'secure' => (bool) ($config['secure'] ?? true),
            'httponly' => true,
            'samesite' => (string) ($config['samesite'] ?? 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return $message;
    }
}
