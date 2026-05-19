<?php
$source = $old !== [] ? $old : $company;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<?php if (! empty($errors['general'])): ?>
    <div class="alert error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="form-stack wide-form">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($company['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Empresa</span>
                <h3>Datos generales</h3>
            </div>
            <button class="button primary sm" type="submit"><?= e(__('actions.save')) ?></button>
        </div>

        <div class="form-grid">
            <label class="field">
                Nombre comercial
                <input name="name" value="<?= e($value('name')) ?>" required>
                <?php if (! empty($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?>
            </label>

            <label class="field">
                Razon social
                <input name="legal_name" value="<?= e($value('legal_name')) ?>">
            </label>

            <label class="field">
                RFC / Tax ID
                <input name="tax_id" value="<?= e($value('tax_id')) ?>">
                <?php if (! empty($errors['tax_id'])): ?><small class="field-error"><?= e($errors['tax_id']) ?></small><?php endif; ?>
            </label>

            <label class="field">
                Estado
                <select name="status">
                    <?php foreach (['active' => 'Activa', 'inactive' => 'Inactiva', 'suspended' => 'Suspendida'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                <?= e(__('fields.language')) ?>
                <select name="locale">
                    <?php foreach (['es' => 'Espanol', 'en' => 'English'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('locale', 'es') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (! empty($errors['locale'])): ?><small class="field-error"><?= e($errors['locale']) ?></small><?php endif; ?>
            </label>
        </div>
    </section>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Licencia</span>
                <h3>Plan asignado</h3>
            </div>
        </div>

        <div class="form-grid">
            <label class="field">
                Plan
                <select name="plan_id">
                    <option value="0">Sin plan</option>
                    <?php foreach ($plans as $plan): ?>
                        <option value="<?= e((string) $plan['id']) ?>" <?= (int) $value('plan_id', '0') === (int) $plan['id'] ? 'selected' : '' ?>>
                            <?= e($plan['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">
                Vence
                <input type="date" name="expires_at" value="<?= e(substr($value('expires_at'), 0, 10)) ?>">
            </label>
        </div>
    </section>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Settings</span>
                <h3>Configuracion de empresa</h3>
            </div>
        </div>

        <div class="form-grid">
            <label class="field">
                Correo de contacto
                <input type="email" name="contact_email" value="<?= e($value('contact_email', $company['settings']['company.contact_email'] ?? '')) ?>">
                <?php if (! empty($errors['contact_email'])): ?><small class="field-error"><?= e($errors['contact_email']) ?></small><?php endif; ?>
            </label>

            <label class="field">
                Zona horaria
                <input name="timezone" value="<?= e($value('timezone', $company['settings']['company.timezone'] ?? 'America/Mazatlan')) ?>">
            </label>
        </div>
    </section>

    <?php if ($mode === 'create'): ?>
        <section class="module-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">ADMIN_EMPRESA</span>
                    <h3>Administrador inicial</h3>
                </div>
            </div>

            <div class="form-grid">
                <label class="field">
                    Nombre
                    <input name="admin_name" value="<?= e($value('admin_name')) ?>" required>
                    <?php if (! empty($errors['admin_name'])): ?><small class="field-error"><?= e($errors['admin_name']) ?></small><?php endif; ?>
                </label>

                <label class="field">
                    Email
                    <input type="email" name="admin_email" value="<?= e($value('admin_email')) ?>" required>
                    <?php if (! empty($errors['admin_email'])): ?><small class="field-error"><?= e($errors['admin_email']) ?></small><?php endif; ?>
                </label>

                <label class="field">
                    Contrasena temporal
                    <input type="password" name="admin_password" minlength="8" required>
                    <?php if (! empty($errors['admin_password'])): ?><small class="field-error"><?= e($errors['admin_password']) ?></small><?php endif; ?>
                </label>
            </div>
        </section>
    <?php endif; ?>
</form>
