<section class="auth-card">
    <span class="eyebrow">Acceso seguro</span>
    <h2>Iniciar sesion</h2>

    <?php if (! empty($error)): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="form-stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="email">Correo</label>
            <input id="email" name="email" type="email" autocomplete="email" placeholder="superadmin@uc200.local" required autofocus>
        </div>
        <div class="field">
            <label for="password">Contrasena</label>
            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Tu contrasena" required>
        </div>
        <button class="button primary full" type="submit">Entrar</button>
    </form>

    <p class="muted">Usuario inicial: <strong>superadmin@uc200.local</strong> / <strong>password</strong></p>
</section>
