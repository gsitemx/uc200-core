<?php
$source = $old !== [] ? $old : $extension;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<?php if (! empty($errors['general'])): ?>
    <div class="alert error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Extension</span>
                <h3>Identidad SIP</h3>
            </div>
            <button class="button primary" type="submit">Guardar</button>
        </div>
        <div class="form-grid">
            <label class="field">Empresa
                <select name="company_id" <?= $mode === 'edit' ? 'disabled' : '' ?>>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id', (string) ($companies[0]['id'] ?? 0)) === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($mode === 'edit'): ?><input type="hidden" name="company_id" value="<?= e((string) $extension['company_id']) ?>"><?php endif; ?>
                <?php if (! empty($errors['company_id'])): ?><small class="field-error"><?= e($errors['company_id']) ?></small><?php endif; ?>
            </label>
            <label class="field">Extension
                <input name="extension" value="<?= e($value('username', $value('extension'))) ?>" <?= $mode === 'edit' ? 'readonly' : '' ?> required>
                <?php if (! empty($errors['extension'])): ?><small class="field-error"><?= e($errors['extension']) ?></small><?php endif; ?>
            </label>
            <label class="field">Password SIP
                <input name="sip_password" value="" placeholder="<?= $mode === 'edit' ? 'Dejar vacio para conservar' : 'Se genera automaticamente' ?>">
                <?php if (! empty($errors['sip_password'])): ?><small class="field-error"><?= e($errors['sip_password']) ?></small><?php endif; ?>
            </label>
            <label class="field">Transport
                <select name="transport">
                    <option value="">Default</option>
                    <?php foreach ($transports as $transport): ?>
                        <option value="<?= e($transport['id']) ?>" <?= $value('transport') === $transport['id'] ? 'selected' : '' ?>><?= e($transport['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Contexto
                <input name="context" value="<?= e($value('context')) ?>" placeholder="tenant_1" required>
                <?php if (! empty($errors['context'])): ?><small class="field-error"><?= e($errors['context']) ?></small><?php endif; ?>
            </label>
            <label class="field">Caller ID
                <input name="callerid" value="<?= e($value('callerid')) ?>" placeholder="Nombre <1001>">
            </label>
            <label class="field">Disallow
                <input name="disallow" value="<?= e($value('disallow', 'all')) ?>">
            </label>
            <label class="field">Allow
                <input name="allow" value="<?= e($value('allow', 'ulaw,alaw')) ?>">
            </label>
            <label class="field">Max contacts
                <input type="number" min="1" name="max_contacts" value="<?= e($value('max_contacts', '1')) ?>">
            </label>
            <label class="field">Qualify frequency
                <input type="number" min="0" name="qualify_frequency" value="<?= e($value('qualify_frequency', '60')) ?>">
            </label>
            <label class="field">Mailbox
                <input name="mailboxes" value="<?= e($value('mailboxes')) ?>">
            </label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activa', 'inactive' => 'Inactiva', 'suspended' => 'Suspendida'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>
</form>
