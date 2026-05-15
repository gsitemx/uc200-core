<?php
$user = auth_user();
$sidebarModules = [
    ['label' => 'Dashboard', 'path' => '/dashboard', 'icon' => 'grid'],
    ['label' => 'Empresas', 'path' => '/companies', 'icon' => 'building', 'roles' => ['super-admin']],
    ['label' => 'Licencias', 'path' => '/licensing', 'icon' => 'license', 'roles' => ['super-admin']],
    ['label' => 'PBX', 'path' => '/pbx', 'icon' => 'sip', 'roles' => ['super-admin', 'admin-empresa']],
    ['label' => 'Mi empresa', 'path' => '/companies/dashboard', 'icon' => 'tenant', 'roles' => ['admin-empresa']],
    ['label' => 'Usuarios', 'path' => '#', 'icon' => 'users'],
    ['label' => 'Roles', 'path' => '#', 'icon' => 'shield'],
    ['label' => 'Modulos', 'path' => '#', 'icon' => 'blocks'],
    ['label' => 'Settings', 'path' => '#', 'icon' => 'settings'],
];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'UC200 Core') ?></title>
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
                    <span>Panel MVP</span>
                </div>
            </div>

            <nav class="nav" aria-label="Modulos">
                <?php foreach ($sidebarModules as $item): ?>
                    <?php if (! empty($item['roles']) && count(array_intersect($item['roles'], $user['roles'] ?? [])) === 0): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a class="nav-link <?= is_active_path($item['path']) ? 'active' : '' ?>" href="<?= e($item['path']) ?>">
                        <span class="nav-icon"><?= e($item['icon']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-card">
                <span class="eyebrow">Modular</span>
                <p>Listo para conectar nuevos modulos desde `modules/*/routes.php`.</p>
            </div>
        </aside>

        <div class="workspace">
            <header class="topbar">
                <button class="icon-button mobile-only" type="button" data-sidebar-toggle aria-label="Abrir menu">M</button>
                <div>
                    <span class="eyebrow">Sistema</span>
                    <h1><?= e($title ?? 'Dashboard') ?></h1>
                </div>
                <div class="topbar-actions">
                    <button class="icon-button" type="button" data-theme-toggle aria-label="Cambiar tema">D</button>
                    <div class="user-chip">
                        <span><?= e($user['name'] ?? 'Usuario') ?></span>
                        <small><?= e(implode(', ', $user['roles'] ?? [])) ?></small>
                    </div>
                    <form method="post" action="/logout">
                        <?= csrf_field() ?>
                        <button class="button secondary" type="submit">Salir</button>
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
