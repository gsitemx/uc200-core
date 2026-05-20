<?php
$data = array_merge($contact, $old ?? []);
$value = static fn (string $key, string $default = ''): string => (string) ($data[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?><input type="hidden" name="id" value="<?= e($contact['uuid']) ?>"><?php endif; ?>
    <section class="hero-panel">
        <div><span class="eyebrow">CRM</span><h2><?= e($title) ?></h2><p>Contacto empresarial con telefonia, tags e integraciones preparadas.</p></div>
        <div class="module-meta"><a class="button secondary sm" href="/crm">Volver</a><button class="button primary sm" type="submit">Guardar</button></div>
    </section>
    <section class="module-panel">
        <div class="form-grid">
            <label class="field">Empresa
                <select name="company_id" <?= $mode === 'edit' ? 'disabled' : '' ?>>
                    <?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id', (string) ($companies[0]['id'] ?? 0)) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option><?php endforeach; ?>
                </select>
                <?php if ($mode === 'edit'): ?><input type="hidden" name="company_id" value="<?= e((string) $contact['company_id']) ?>"><?php endif; ?>
            </label>
            <label class="field">Nombre
                <input name="full_name" value="<?= e($value('full_name')) ?>" required>
                <?php if (! empty($errors['full_name'])): ?><small class="field-error"><?= e($errors['full_name']) ?></small><?php endif; ?>
            </label>
            <label class="field">Cuenta / cliente
                <select name="account_id">
                    <option value="">Sin cuenta</option>
                    <?php foreach ($accounts as $account): ?><option value="<?= e((string) $account['id']) ?>" <?= (int) $value('account_id', '0') === (int) $account['id'] ? 'selected' : '' ?>><?= e($account['trade_name']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field">Organizacion
                <input name="organization" value="<?= e($value('organization')) ?>">
            </label>
            <label class="field">Puesto
                <input name="job_title" value="<?= e($value('job_title')) ?>">
            </label>
            <label class="field">Email
                <input type="email" name="email" value="<?= e($value('email')) ?>">
                <?php if (! empty($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?>
            </label>
            <label class="field">Telefono movil
                <input name="mobile_phone" value="<?= e($value('mobile_phone')) ?>">
            </label>
            <label class="field">Telefono oficina
                <input name="office_phone" value="<?= e($value('office_phone')) ?>">
            </label>
            <label class="field">Extension relacionada
                <select name="related_extension_id">
                    <option value="">Sin extension</option>
                    <?php foreach ($extensions as $extension): ?><option value="<?= e($extension['id']) ?>" <?= $value('related_extension_id') === $extension['id'] ? 'selected' : '' ?>><?= e($extension['extension_number'] . ' - ' . ($extension['display_name'] ?? $extension['id'])) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field">DID relacionado
                <input name="related_did" value="<?= e($value('related_did')) ?>" placeholder="+52... o DID directo">
            </label>
            <label class="field">Tags
                <input name="tags" value="<?= e($value('tags')) ?>" placeholder="vip, proveedor, soporte">
            </label>
            <label class="field">Origen
                <select name="source">
                    <?php foreach ($sources as $source): ?><option value="<?= e($source) ?>" <?= $value('source', 'manual') === $source ? 'selected' : '' ?>><?= e($source) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activo', 'inactive' => 'Inactivo'] as $key => $label): ?><option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label class="field">External ID
                <input name="external_id" value="<?= e($value('external_id')) ?>" placeholder="Futuro Microsoft/Google/WhatsApp/API">
            </label>
            <label class="field">Sync enabled
                <select name="sync_enabled"><option value="0">No</option><option value="1" <?= $value('sync_enabled') === '1' ? 'selected' : '' ?>>Si</option></select>
            </label>
        </div>
        <label class="field">Notas
            <textarea name="notes" rows="4"><?= e($value('notes')) ?></textarea>
        </label>
    </section>
</form>
