<?php
$source = $old !== [] ? $old : $transport;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($transport['uuid']) ?>">
    <?php endif; ?>
    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Transport</span>
                <h3>Configuracion SIP</h3>
            </div>
            <button class="button primary" type="submit">Guardar</button>
        </div>
        <div class="form-grid">
            <label class="field">ID
                <input name="id" value="<?= e($value('id')) ?>" <?= $mode === 'edit' ? 'readonly' : '' ?> required>
                <?php if (! empty($errors['id'])): ?><small class="field-error"><?= e($errors['id']) ?></small><?php endif; ?>
            </label>
            <label class="field">Empresa
                <select name="company_id">
                    <option value="0">Global</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id', '0') === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Protocolo
                <select name="protocol">
                    <?php foreach (['udp', 'tcp', 'tls', 'ws', 'wss'] as $protocol): ?>
                        <option value="<?= e($protocol) ?>" <?= $value('protocol', 'udp') === $protocol ? 'selected' : '' ?>><?= e(strtoupper($protocol)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Bind
                <input name="bind" value="<?= e($value('bind', '0.0.0.0:5060')) ?>" required>
            </label>
            <label class="field">Local net
                <input name="local_net" value="<?= e($value('local_net')) ?>">
            </label>
            <label class="field">External media address
                <input name="external_media_address" value="<?= e($value('external_media_address')) ?>">
            </label>
            <label class="field">External signaling address
                <input name="external_signaling_address" value="<?= e($value('external_signaling_address')) ?>">
            </label>
            <label class="field">Allow reload
                <select name="allow_reload">
                    <option value="1" <?= $value('allow_reload', '1') === '1' ? 'selected' : '' ?>>Si</option>
                    <option value="0" <?= $value('allow_reload', '1') === '0' ? 'selected' : '' ?>>No</option>
                </select>
            </label>
            <label class="field">Estado
                <select name="status">
                    <option value="active" <?= $value('status', 'active') === 'active' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactive" <?= $value('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </label>
        </div>
    </section>
</form>
