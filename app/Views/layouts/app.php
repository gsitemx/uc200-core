<?php
$user = auth_user();
$sidebarModules = [
    ['label' => __('menu.dashboard'), 'path' => '/dashboard', 'icon' => 'grid'],
    ['label' => __('menu.companies'), 'path' => '/companies', 'icon' => 'building', 'roles' => ['super-admin']],
    ['label' => __('menu.licensing'), 'path' => '/licensing', 'icon' => 'license', 'roles' => ['super-admin']],
    ['label' => 'Billing', 'path' => '/billing', 'icon' => 'billing', 'roles' => ['super-admin', 'reseller', 'admin-empresa']],
    ['label' => __('menu.pbx'), 'path' => '/pbx', 'icon' => 'sip', 'roles' => ['super-admin', 'admin-empresa']],
    ['label' => 'Call Center', 'path' => '/call-center', 'icon' => 'queue', 'roles' => ['super-admin', 'admin-empresa']],
    ['label' => 'Softphone', 'path' => '/softphone', 'icon' => 'call', 'roles' => ['super-admin', 'admin-empresa']],
    ['label' => 'Provisioning', 'path' => '/provisioning', 'icon' => 'phone', 'roles' => ['super-admin', 'admin-empresa']],
    ['label' => 'API', 'path' => '/settings/api-tokens', 'icon' => 'api', 'roles' => ['super-admin', 'admin-empresa']],
    ['label' => __('menu.my_company'), 'path' => '/companies/dashboard', 'icon' => 'tenant', 'roles' => ['admin-empresa']],
    ['label' => __('menu.users'), 'path' => '#', 'icon' => 'users'],
    ['label' => __('menu.roles'), 'path' => '#', 'icon' => 'shield'],
    ['label' => __('menu.modules'), 'path' => '#', 'icon' => 'blocks'],
    ['label' => __('menu.settings'), 'path' => '/settings/profile', 'icon' => 'settings'],
];
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/dashboard', PHP_URL_PATH) ?: '/dashboard';
$segments = array_values(array_filter(explode('/', trim($currentPath, '/'))));
$icon = static function (string $name): string {
    $attrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $paths = match ($name) {
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="14" width="7" height="7" rx="1.5"></rect><rect x="3" y="14" width="7" height="7" rx="1.5"></rect>',
        'building', 'tenant' => '<path d="M4 21V5a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v16"></path><path d="M9 21v-5h3v5"></path><path d="M8 7h1"></path><path d="M12 7h1"></path><path d="M8 11h1"></path><path d="M12 11h1"></path><path d="M3 21h18"></path>',
        'license' => '<path d="M15 7a4 4 0 1 0-3.2 3.9L4 18.7V21h2.3l1.2-1.2H10v-2.3l1.2-1.2v-2.5l3.9-3.9A4 4 0 0 0 15 7Z"></path><path d="M16 7h.01"></path>',
        'billing' => '<path d="M6 2h12v20l-3-2-3 2-3-2-3 2V2Z"></path><path d="M9 7h6"></path><path d="M9 11h6"></path><path d="M9 15h3"></path>',
        'sip', 'call' => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.6a16 16 0 0 0 6.3 6.3l1.2-1.2a2 2 0 0 1 2.1-.5c.8.3 1.7.6 2.6.7a2 2 0 0 1 1.7 2Z"></path>',
        'queue' => '<path d="M4 13v-2a8 8 0 0 1 16 0v2"></path><path d="M18 19a3 3 0 0 0 3-3v-3h-4v6h1Z"></path><path d="M6 19a3 3 0 0 1-3-3v-3h4v6H6Z"></path><path d="M12 19v2"></path><path d="M9 21h6"></path>',
        'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"></rect><path d="M11 18h2"></path>',
        'api' => '<path d="m8 9-3 3 3 3"></path><path d="m16 9 3 3-3 3"></path><path d="m14 5-4 14"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.9"></path><path d="M16 3.1a4 4 0 0 1 0 7.8"></path>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"></path><path d="m9 12 2 2 4-4"></path>',
        'blocks' => '<rect x="3" y="3" width="7" height="7" rx="1.5"></rect><rect x="14" y="3" width="7" height="7" rx="1.5"></rect><rect x="8.5" y="14" width="7" height="7" rx="1.5"></rect>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.6V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.6h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.6 1h.1a2 2 0 1 1 0 4H21a1.7 1.7 0 0 0-1.6 1Z"></path>',
        default => '<circle cx="12" cy="12" r="8"></circle>',
    };

    return '<svg ' . $attrs . '>' . $paths . '</svg>';
};
?>
<!doctype html>
<html lang="<?= e(app_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('app.name')) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>
        const savedTheme = localStorage.getItem('uc200-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.dataset.theme = savedTheme || (prefersDark ? 'dark' : 'light');
    </script>
</head>
<body>
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-mark">UC</div>
                <div>
                    <strong>UC200 Core</strong>
                    <span><?= e(__('app.panel')) ?></span>
                </div>
            </div>

            <nav class="nav" aria-label="<?= e(__('menu.modules')) ?>">
                <?php foreach ($sidebarModules as $item): ?>
                    <?php if (! empty($item['roles']) && count(array_intersect($item['roles'], $user['roles'] ?? [])) === 0): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="nav-link <?= is_active_path($item['path']) ? 'active' : '' ?>" href="<?= e($item['path']) ?>">
                        <span class="nav-icon"><?= $icon($item['icon']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-card">
                <span class="eyebrow"><?= e(__('app.modular')) ?></span>
                <p><?= e(__('app.module_hint')) ?></p>
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <button class="icon-button mobile-only" type="button" data-sidebar-toggle aria-label="<?= e(__('actions.open_menu')) ?>">M</button>
                <div>
                    <nav class="breadcrumbs" aria-label="Breadcrumbs">
                        <a href="/dashboard"><?= e(__('menu.dashboard')) ?></a>
                        <?php foreach ($segments as $segment): ?>
                            <span>/</span>
                            <span><?= e(ucwords(str_replace(['-', '_'], ' ', $segment))) ?></span>
                        <?php endforeach; ?>
                    </nav>
                    <span class="eyebrow"><?= e(__('app.system')) ?></span>
                    <h1><?= e($title ?? __('menu.dashboard')) ?></h1>
                </div>
                <div class="topbar-actions">
                    <div class="quick-actions">
                        <?php if (str_starts_with($currentPath, '/pbx')): ?>
                            <a class="button secondary xs" href="/pbx/extensions/create"><?= e(__('actions.new_extension')) ?></a>
                            <a class="button secondary xs" href="/pbx/recordings"><?= e(__('menu.recordings')) ?></a>
                        <?php elseif (str_starts_with($currentPath, '/call-center')): ?>
                            <a class="button secondary xs" href="/call-center/queues/create">Nueva queue</a>
                            <a class="button secondary xs" href="/call-center#wallboard">Wallboard</a>
                        <?php elseif (str_starts_with($currentPath, '/companies')): ?>
                            <a class="button secondary xs" href="/companies/create"><?= e(__('companies.new')) ?></a>
                        <?php elseif (str_starts_with($currentPath, '/licensing')): ?>
                            <a class="button secondary xs" href="/licensing/licenses/create"><?= e(__('licensing.new_license')) ?></a>
                        <?php elseif (str_starts_with($currentPath, '/billing')): ?>
                            <a class="button secondary xs" href="/billing/subscriptions">Subscriptions</a>
                            <a class="button secondary xs" href="/billing/invoices">Invoices</a>
                        <?php endif; ?>
                    </div>
                    <button class="icon-button" type="button" data-theme-toggle aria-label="<?= e(__('actions.toggle_theme')) ?>">D</button>
                    <form class="locale-switcher" method="post" action="/settings/language">
                        <?= csrf_field() ?>
                        <label class="sr-only" for="locale-select"><?= e(__('fields.language')) ?></label>
                        <select id="locale-select" name="locale" onchange="this.form.submit()">
                            <?php foreach (['es' => 'ES', 'en' => 'EN'] as $locale => $label): ?>
                                <option value="<?= e($locale) ?>" <?= app_locale() === $locale ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <div class="user-chip">
                        <span><?= e($user['name'] ?? 'Usuario') ?></span>
                        <small><?= e(strtoupper(app_locale())) ?> - <?= e(implode(', ', $user['roles'] ?? [])) ?></small>
                    </div>
                    <form method="post" action="/logout">
                        <?= csrf_field() ?>
                        <button class="button secondary sm" type="submit"><?= e(__('actions.logout')) ?></button>
                    </form>
                </div>
            </header>

            <main class="content">
                <?= $content ?>
            </main>
        </div>
    </div>
    <script src="/assets/js/app.js"></script>
</body>
</html>
