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

if (! function_exists('__')) {
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return \App\Core\I18n::translate($key, $replace, $locale);
    }
}

if (! function_exists('lang')) {
    function lang(?string $key = null, array $replace = [], ?string $locale = null): string
    {
        if ($key === null || $key === '') {
            return \App\Core\I18n::locale();
        }

        return \App\Core\I18n::translate($key, $replace, $locale);
    }
}

if (! function_exists('app_locale')) {
    function app_locale(): string
    {
        return \App\Core\I18n::locale();
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
            'company_locale' => \App\Core\Session::get('company_locale'),
            'name' => \App\Core\Session::get('user_name'),
            'email' => \App\Core\Session::get('user_email'),
            'locale' => \App\Core\Session::get('user_locale'),
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
            'locale' => \App\Core\Session::get('company_locale'),
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
        $target = parse_url($path, PHP_URL_PATH) ?: $path;
        $target = rtrim($target, '/');

        if ($target === '' || $target === '#') {
            return false;
        }

        return rtrim($current, '/') === $target || str_starts_with($current, $target . '/');
    }
}

if (! function_exists('uc200_menu')) {
    function uc200_menu(): array
    {
        static $menu = null;

        if ($menu !== null) {
            return $menu;
        }

        $user = auth_user();
        if (($user['id'] ?? null) === null) {
            return $menu = [];
        }

        $config = new \App\Core\Config(require base_path('config/app.php'));
        $service = new \App\Services\MenuService(\App\Core\Database::connect($config->get('database')));

        return $menu = $service->build($user);
    }
}

if (! function_exists('render_icon')) {
    function render_icon(string $name): string
    {
        $attrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
        $paths = match ($name) {
            'home' => '<path d="M3 11.5 12 4l9 7.5"></path><path d="M5 10.5V20h14v-9.5"></path><path d="M10 20v-5h4v5"></path>',
            'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect>',
            'platform' => '<path d="M4 6h16"></path><path d="M4 12h16"></path><path d="M4 18h16"></path><path d="M7 4v16"></path>',
            'building', 'tenant' => '<path d="M4 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v16"></path><path d="M9 21v-5h3v5"></path><path d="M8 7h1"></path><path d="M12 7h1"></path><path d="M8 11h1"></path><path d="M12 11h1"></path><path d="M3 21h18"></path>',
            'license' => '<path d="M15 7a4 4 0 1 0-3.2 3.9L4 18.7V21h2.3l1.2-1.2H10v-2.3l1.2-1.2v-2.5l3.9-3.9A4 4 0 0 0 15 7Z"></path><path d="M16 7h.01"></path>',
            'billing' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"></path><path d="M9 7h6"></path><path d="M9 11h6"></path><path d="M9 15h3"></path>',
            'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"></path><path d="M8.5 8h7"></path><path d="M8.5 12h7"></path><path d="M8.5 16h4"></path>',
            'crm' => '<path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"></path><path d="M2 21v-2a6 6 0 0 1 12 0v2"></path><path d="M17 8h5"></path><path d="M17 12h5"></path><path d="M17 16h3"></path>',
            'messages' => '<path d="M4 6h16v10H8l-4 4V6Z"></path><path d="M8 10h8"></path><path d="M8 13h5"></path>',
            'sip', 'call', 'extension' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.6a16 16 0 0 0 6.3 6.3l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7a2 2 0 0 1 1.7 2Z"></path>',
            'trunk' => '<path d="M7 7h10v10H7z"></path><path d="M4 10h3"></path><path d="M17 10h3"></path><path d="M4 14h3"></path><path d="M17 14h3"></path>',
            'arrow-in' => '<path d="M20 4v6h-6"></path><path d="M20 10 9 21"></path><path d="M4 8v8a1 1 0 0 0 1 1h8"></path>',
            'arrow-out' => '<path d="M14 4h6v6"></path><path d="M20 4 9 15"></path><path d="M4 8v8a1 1 0 0 0 1 1h8"></path>',
            'queue' => '<path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M18 19a3 3 0 0 0 3-3v-3h-4v6h1Z"></path><path d="M6 19a3 3 0 0 1-3-3v-3h4v6H6Z"></path><path d="M12 19v2"></path><path d="M9 21h6"></path>',
            'agent' => '<circle cx="9" cy="8" r="4"></circle><path d="M3 21v-1a6 6 0 0 1 12 0v1"></path><path d="M16 8h5"></path><path d="M18.5 5.5v5"></path>',
            'wallboard' => '<rect x="3" y="4" width="18" height="14" rx="2"></rect><path d="M8 20h8"></path><path d="M12 18v2"></path><path d="M7 10h2"></path><path d="M11 8h2"></path><path d="M15 12h2"></path>',
            'security' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="M12 8v5"></path><path d="M12 16h.01"></path>',
            'alert' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"></path><path d="M12 9v4"></path><path d="M12 17h.01"></path>',
            'ban' => '<circle cx="12" cy="12" r="9"></circle><path d="m7 7 10 10"></path>',
            'firewall' => '<path d="M5 5h14v14H5z"></path><path d="M9 5v14"></path><path d="M15 5v14"></path><path d="M5 9h14"></path><path d="M5 15h14"></path>',
            'allow' => '<circle cx="12" cy="12" r="9"></circle><path d="m8.5 12 2.5 2.5L16 9.5"></path>',
            'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"></rect><path d="M11 18h2"></path>',
            'devices' => '<rect x="3" y="5" width="13" height="10" rx="2"></rect><path d="M8 19h3"></path><rect x="18" y="9" width="3" height="8" rx="1"></rect>',
            'template' => '<path d="M5 4h14v16H5z"></path><path d="M8 8h8"></path><path d="M8 12h8"></path><path d="M8 16h5"></path>',
            'phonebook' => '<path d="M6 4h11a2 2 0 0 1 2 2v14H8a2 2 0 0 0-2 2Z"></path><path d="M6 4a2 2 0 0 0-2 2v14"></path><path d="M10 8h5"></path><path d="M10 12h5"></path>',
            'api' => '<path d="m8 9-3 3 3 3"></path><path d="m16 9 3 3-3 3"></path><path d="m14 5-4 14"></path>',
            'integrations' => '<path d="M8 8h8"></path><path d="M8 16h8"></path><path d="M6 12h12"></path><path d="M12 6v12"></path><circle cx="12" cy="12" r="9"></circle>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.9"></path><path d="M16 3.1a4 4 0 0 1 0 7.8"></path>',
            'activity' => '<path d="M3 12h4l2-5 4 10 2-5h6"></path>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path>',
            'blocks' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="8.5" y="14" width="7" height="7" rx="1.5"></rect>',
            'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.6 1h.1a2 2 0 1 1 0 4H21a1.7 1.7 0 0 0-1.6 1Z"></path>',
            'server' => '<rect x="3" y="4" width="18" height="6" rx="2"></rect><rect x="3" y="14" width="18" height="6" rx="2"></rect><path d="M7 7h.01"></path><path d="M7 17h.01"></path>',
            'pulse' => '<path d="M3 12h4l2.4-5 4.2 10 2.4-5H21"></path>',
            'link' => '<path d="M10 13a5 5 0 0 1 0-7l1-1a5 5 0 0 1 7 7l-1 1"></path><path d="M14 11a5 5 0 0 1 0 7l-1 1a5 5 0 0 1-7-7l1-1"></path>',
            'search' => '<circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
            'star' => '<path d="m12 3.8 2.6 5.3 5.9.9-4.3 4.2 1 5.9L12 17.2 6.8 20l1-5.9L3.5 10l5.9-.9L12 3.8Z"></path>',
            'chart' => '<path d="M4 19h16"></path><path d="M7 16V9"></path><path d="M12 16V5"></path><path d="M17 16v-3"></path>',
            'ring-group' => '<path d="M6 8a6 6 0 0 1 12 0"></path><path d="M4 12a8 8 0 0 1 16 0"></path><path d="M12 12v9"></path>',
            'menu-tree' => '<path d="M4 5h6"></path><path d="M4 12h10"></path><path d="M4 19h6"></path><path d="M14 5h6v4h-6z"></path><path d="M14 17h6v4h-6z"></path>',
            'recordings' => '<path d="M9 6a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z"></path><path d="M14 8h6"></path><path d="M14 12h6"></path><path d="M5 16h14v4H5z"></path>',
            'reseller' => '<path d="M4 20V7l8-4 8 4v13"></path><path d="M8 20v-5h8v5"></path><path d="M8 9h.01"></path><path d="M12 9h.01"></path><path d="M16 9h.01"></path>',
            default => '<circle cx="12" cy="12" r="8"></circle>',
        };

        return '<svg ' . $attrs . '>' . $paths . '</svg>';
    }
}
