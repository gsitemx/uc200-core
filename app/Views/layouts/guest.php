<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Acceso') ?> - UC200 Core</title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>
        const savedTheme = localStorage.getItem('uc200-theme');
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.documentElement.dataset.theme = savedTheme || (prefersDark ? 'dark' : 'light');
    </script>
</head>
<body>
    <main class="guest-shell">
        <section class="guest-copy">
            <div class="brand wide">
                <div class="brand-mark">UC</div>
                <div>
                    <strong>UC200 Core</strong>
                    <span>Base del MVP</span>
                </div>
            </div>
            <h1>Centro operativo para administrar empresas, licencias, modulos y permisos.</h1>
            <p>PHP puro, sesiones seguras, roles preparados y estructura modular para crecer sin framework pesado.</p>
        </section>
        <?= $content ?>
    </main>
    <button class="theme-float" type="button" data-theme-toggle aria-label="Cambiar tema">D</button>
    <script src="/assets/js/app.js"></script>
</body>
</html>
