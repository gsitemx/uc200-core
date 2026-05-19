<section class="auth-card">
    <span class="eyebrow"><?= e(__('auth.secure_access')) ?></span>
    <h2><?= e(__('auth.login')) ?></h2>

    <?php if (! empty($error)): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="form-stack">
        <?= csrf_field() ?>
        <div class="field">
            <label for="identifier"><?= e(__('auth.identifier')) ?></label>
            <input id="identifier" name="identifier" type="text" autocomplete="username" placeholder="Email o extension" required autofocus>
        </div>
        <div class="field">
            <label for="password"><?= e(__('auth.password')) ?></label>
            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="<?= e(__('auth.password_placeholder')) ?>" required>
        </div>
        <button class="button primary full" type="submit"><?= e(__('auth.submit')) ?></button>
    </form>

    <p class="muted"><?= e(__('auth.initial_user')) ?>: <strong>superadmin@uc200.local</strong> / <strong>password</strong></p>
</section>
