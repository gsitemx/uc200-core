<?php
$source = $old !== [] ? $old : $license;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
$limit = static fn (string $key): string => (string) ($limits[$key] ?? 0);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($license['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Licencia</span>
                <h3>Asignacion</h3>
            </div>
            <button class="button primary" type="submit">Guardar</button>
        </div>
        <div class="form-grid">
            <label class="field">Empresa
                <select name="company_id" required>
                    <option value="0">Selecciona empresa</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id', '0') === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (! empty($errors['company_id'])): ?><small class="field-error"><?= e($errors['company_id']) ?></small><?php endif; ?>
            </label>
            <label class="field">Plan
                <select name="plan_id" required>
                    <option value="0">Selecciona plan</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= e((string) $plan['id']) ?>" <?= (int) $value('plan_id', '0') === (int) $plan['id'] ? 'selected' : '' ?>><?= e($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (! empty($errors['plan_id'])): ?><small class="field-error"><?= e($errors['plan_id']) ?></small><?php endif; ?>
            </label>
            <label class="field">License key
                <input name="license_key" value="<?= e($value('license_key')) ?>" placeholder="Se genera automaticamente">
            </label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activa', 'inactive' => 'Inactiva', 'suspended' => 'Suspendida', 'expired' => 'Expirada', 'cancelled' => 'Cancelada'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Inicio
                <input type="date" name="starts_at" value="<?= e(substr($value('starts_at', date('Y-m-d')), 0, 10)) ?>" required>
            </label>
            <label class="field">Expira
                <input type="date" name="expires_at" value="<?= e(substr($value('expires_at'), 0, 10)) ?>">
            </label>
        </div>
    </section>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Limites</span>
                <h3>Uso permitido</h3>
            </div>
        </div>
        <div class="form-grid">
            <label class="field">Usuarios
                <input type="number" min="0" name="limit_users" value="<?= e($limit('users')) ?>">
            </label>
            <label class="field">Extensiones
                <input type="number" min="0" name="limit_extensions" value="<?= e($limit('extensions')) ?>">
            </label>
            <label class="field">Llamadas concurrentes
                <input type="number" min="0" name="limit_concurrent_calls" value="<?= e($limit('concurrent_calls')) ?>">
            </label>
        </div>
    </section>
</form>
