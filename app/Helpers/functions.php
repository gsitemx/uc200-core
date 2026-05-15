<?php

declare(strict_types=1);

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return match (strtolower((string) $value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (! function_exists('view')) {
    function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return \App\Core\View::render($template, $data, $layout);
    }
}

if (! function_exists('redirect')) {
    function redirect(string $path): void
    {
        header('Location: ' . $path, true, 302);
        exit;
    }
}

if (! function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return \App\Core\Csrf::field();
    }
}

if (! function_exists('uuid')) {
    function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

if (! function_exists('auth_user')) {
    function auth_user(): array
    {
        return [
            'id' => \App\Core\Session::get('user_id'),
            'company_id' => \App\Core\Session::get('company_id'),
            'company_uuid' => \App\Core\Session::get('company_uuid'),
            'company_name' => \App\Core\Session::get('company_name'),
            'company_status' => \App\Core\Session::get('company_status'),
            'name' => \App\Core\Session::get('user_name'),
            'email' => \App\Core\Session::get('user_email'),
            'roles' => \App\Core\Session::get('user_roles', []),
        ];
    }
}

if (! function_exists('currentCompany')) {
    function currentCompany(): ?array
    {
        $companyId = \App\Core\Session::get('company_id');

        if ($companyId === null) {
            return null;
        }

        return [
            'id' => $companyId,
            'uuid' => \App\Core\Session::get('company_uuid'),
            'name' => \App\Core\Session::get('company_name'),
            'status' => \App\Core\Session::get('company_status'),
        ];
    }
}

if (! function_exists('has_role')) {
    function has_role(string $role): bool
    {
        return in_array($role, auth_user()['roles'] ?? [], true);
    }
}

if (! function_exists('hasFeature')) {
    function hasFeature(string $featureSlug, ?int $companyId = null): bool
    {
        if (has_role('super-admin')) {
            return true;
        }

        $companyId ??= \App\Core\Session::get('company_id');

        if ($companyId === null) {
            return false;
        }

        $config = new \App\Core\Config(require base_path('config/app.php'));
        $service = new \App\Services\LicenseService(\App\Core\Database::connect($config->get('database')));

        return $service->hasFeature((int) $companyId, $featureSlug);
    }
}

if (! function_exists('licenseLimit')) {
    function licenseLimit(string $limitKey, ?int $companyId = null): ?int
    {
        $companyId ??= \App\Core\Session::get('company_id');

        if ($companyId === null) {
            return null;
        }

        $config = new \App\Core\Config(require base_path('config/app.php'));
        $service = new \App\Services\LicenseService(\App\Core\Database::connect($config->get('database')));

        return $service->limit((int) $companyId, $limitKey);
    }
}

if (! function_exists('is_active_path')) {
    function is_active_path(string $path): bool
    {
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $target = rtrim($path, '/');

        if ($target === '' || $target === '#') {
            return false;
        }

        return rtrim($current, '/') === $target || str_starts_with($current, $target . '/');
    }
}
