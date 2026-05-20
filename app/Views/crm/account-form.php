<?php
$data = array_merge($account, $old ?? []);
$value = static fn (string $key, string $default = ''): string => (string) ($data[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?><input type="hidden" name="id" value="<?= e($account['uuid']) ?>"><?php endif; ?>
    <section class="hero-panel">
        <div><span class="eyebrow">CRM</span><h2><?= e($title) ?></h2><p>Cuenta empresarial para agrupar contactos, historial y actividad.</p></div>
        <div class="module-meta"><a class="button secondary sm" href="/crm">Volver</a><button class="button primary sm" type="submit">Guardar</button></div>
    </section>
    <section class="module-panel">
        <div class="form-grid">
            <label class="field">Empresa
                <select name="company_id" <?= $mode === 'edit' ? 'disabled' : '' ?>>
                    <?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id', (string) ($companies[0]['id'] ?? 0)) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?>
                </select>
                <?php if ($mode === 'edit'): ?><input type="hidden" name="company_id" value="<?= e((string) $account['company_id']) ?>"><?php endif; ?>
            </label>
            <label class="field">Nombre comercial
                <input name="trade_name" value="<?= e($value('trade_name')) ?>" required>
                <?php if (! empty($errors['trade_name'])): ?><small class="field-error"><?= e($errors['trade_name']) ?></small><?php endif; ?>
            </label>
            <label class="field">Razon social
                <input name="legal_name" value="<?= e($value('legal_name')) ?>">
            </label>
            <label class="field">RFC / Tax ID
                <input name="tax_id" value="<?= e($value('tax_id')) ?>">
            </label>
            <label class="field">Email principal
                <input type="email" name="primary_email" value="<?= e($value('primary_email')) ?>">
                <?php if (! empty($errors['primary_email'])): ?><small class="field-error"><?= e($errors['primary_email']) ?></small><?php endif; ?>
            </label>
            <label class="field">Telefono principal
                <input name="primary_phone" value="<?= e($value('primary_phone')) ?>">
            </label>
            <label class="field">Sitio web
                <input name="website" value="<?= e($value('website')) ?>" placeholder="https://">
            </label>
            <label class="field">Origen
                <select name="external_provider">
                    <?php foreach ($sources as $source): ?><option value="<?= e($source) ?>" <?= $value('external_provider', 'manual') === $source ? 'selected' : '' ?>><?= e($source) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field">External ID
                <input name="external_id" value="<?= e($value('external_id')) ?>">
            </label>
            <label class="field">Sync enabled
                <select name="sync_enabled"><option value="0">No</option><option value="1" <?= $value('sync_enabled') === '1' ? 'selected' : '' ?>>Si</option></select>
            </label>
            <label class="field">Estado
                <select name="status"><?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
            </label>
        </div>
        <label class="field">Direccion
            <textarea name="address" rows="3"><?= e($value('address')) ?></textarea>
        </label>
        <label class="field">Notas
            <textarea name="notes" rows="4"><?= e($value('notes')) ?></textarea>
        </label>
    </section>
</form>
