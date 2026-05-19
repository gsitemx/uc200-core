<!doctype html>
<html lang="<?= e(app_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? __('auth.access')) ?> - UC200 Core</title>
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
                    <span><?= e(__('app.panel')) ?></span>
                </div>
            </div>
            <h1><?= e(__('auth.hero_title')) ?></h1>
            <p><?= e(__('auth.hero_copy')) ?></p>
        </section>
        <?= $content ?>
    </main>
    <button class="theme-float" type="button" data-theme-toggle aria-label="<?= e(__('actions.toggle_theme')) ?>">D</button>
    <script src="/assets/js/app.js"></script>
</body>
</html>
