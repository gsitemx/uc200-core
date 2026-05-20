<?php
$user = auth_user();
$menuSections = uc200_menu();
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/dashboard', PHP_URL_PATH) ?: '/dashboard';
$segments = array_values(array_filter(explode('/', trim($currentPath, '/'))));
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
<body data-user-id="<?= e((string) ($user['id'] ?? '0')) ?>">
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="brand">
                <div class="brand-mark">UC</div>
                <div>
                    <strong>UC200 Core</strong>
                    <span><?= e(__('app.panel')) ?></span>
                </div>
            </div>

            <div class="sidebar-search">
                <label class="sr-only" for="menu-search"><?= e(__('menu.search_placeholder')) ?></label>
                <span class="sidebar-search-icon"><?= render_icon('search') ?></span>
                <input id="menu-search" type="search" data-menu-search placeholder="<?= e(__('menu.search_placeholder')) ?>" autocomplete="off">
            </div>

            <nav class="nav nav-sections" aria-label="<?= e(__('menu.modules')) ?>">
                <section class="nav-section nav-section-favorites" data-menu-favorites-section data-menu-section="favorites" data-default-open="true" hidden>
                    <button class="nav-section-toggle" type="button" data-menu-section-toggle aria-expanded="true">
                        <span class="nav-section-label">
                            <span class="nav-icon nav-icon-section"><?= render_icon('star') ?></span>
                            <span><?= e(__('menu.favorites')) ?></span>
                        </span>
                        <span class="nav-section-toggle-icon"><?= render_icon('chevron-down') ?></span>
                    </button>
                    <div class="nav-section-body" data-menu-section-body>
                        <div class="nav-favorites-list" data-menu-favorites-list></div>
                    </div>
                </section>

                <?php foreach ($menuSections as $section): ?>
                    <section
                        class="nav-section <?= ! empty($section['active']) ? 'active' : '' ?>"
                        data-menu-section="<?= e((string) $section['id']) ?>"
                        data-default-open="<?= ! empty($section['default_open']) ? 'true' : 'false' ?>"
                    >
                        <button class="nav-section-toggle" type="button" data-menu-section-toggle aria-expanded="<?= ! empty($section['active']) || ! empty($section['default_open']) ? 'true' : 'false' ?>">
                            <span class="nav-section-label">
                                <span class="nav-icon nav-icon-section"><?= render_icon((string) ($section['icon'] ?? 'grid')) ?></span>
                                <span><?= e((string) $section['label']) ?></span>
                            </span>
                            <span class="nav-section-actions">
                                <?php if (! empty($section['badge_value'])): ?>
                                    <span class="badge <?= e((string) ($section['badge_tone'] ?? '')) ?>"><?= e((string) $section['badge_value']) ?></span>
                                <?php endif; ?>
                                <span class="nav-section-toggle-icon"><?= render_icon('chevron-down') ?></span>
                            </span>
                        </button>

                        <div class="nav-section-body" data-menu-section-body>
                            <?php foreach ($section['items'] as $item): ?>
                                <div
                                    class="nav-item-row <?= ! empty($item['active']) ? 'active' : '' ?>"
                                    data-menu-item-row
                                    data-menu-item-id="<?= e((string) $item['id']) ?>"
                                    data-menu-item-source="1"
                                    data-menu-search-text="<?= e((string) $item['search']) ?>"
                                >
                                    <a class="nav-link <?= ! empty($item['active']) ? 'active' : '' ?>" href="<?= e((string) $item['path']) ?>" data-menu-link>
                                        <span class="nav-icon"><?= render_icon((string) ($item['icon'] ?? 'grid')) ?></span>
                                        <span class="nav-link-text"><?= e((string) $item['label']) ?></span>
                                    </a>
                                    <div class="nav-item-actions">
                                        <?php if (! empty($item['badge_value'])): ?>
                                            <span class="badge <?= e((string) ($item['badge_tone'] ?? '')) ?>"><?= e((string) $item['badge_value']) ?></span>
                                        <?php endif; ?>
                                        <button class="nav-favorite-toggle" type="button" data-menu-favorite-toggle aria-label="<?= e(__('menu.favorites')) ?>">
                                            <?= render_icon('star') ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
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
                        <?php elseif (str_starts_with($currentPath, '/crm')): ?>
                            <a class="button secondary xs" href="/crm/contacts/create">Nuevo contacto</a>
                            <a class="button secondary xs" href="/crm/accounts/create">Nueva cuenta</a>
                            <a class="button secondary xs" href="/crm/activity">Actividad</a>
                        <?php elseif (str_starts_with($currentPath, '/security')): ?>
                            <a class="button secondary xs" href="/security/bans">Bloquear IP</a>
                            <a class="button secondary xs" href="/security/fail2ban">Fail2Ban</a>
                            <a class="button secondary xs" href="/security/firewall">Firewall</a>
                        <?php endif; ?>
                    </div>
                    <span class="badge" data-realtime-connection>Realtime offline</span>
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
    <script>
        window.UC200RealtimeConfig = {
            tokenEndpoint: '/api/v1/realtime/token',
            statusEndpoint: '/api/v1/realtime/status'
        };
    </script>
    <script src="/assets/js/app.js"></script>
    <script src="/assets/js/realtime.js"></script>
</body>
</html>
