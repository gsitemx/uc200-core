<?php
$source = $old !== [] ? $old : $device;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($device['uuid']) ?>">
        <input type="hidden" name="provisioning_secret" value="<?= e($device['provisioning_secret']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Provisioning</span>
                <h3><?= e($title) ?></h3>
            </div>
            <button class="button primary sm" type="submit">Guardar</button>
        </div>

        <div class="form-grid">
            <?php if (has_role('super-admin')): ?>
                <label class="field">Empresa
                    <select name="company_id">
                        <option value="0">Selecciona</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id') === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($errors['company_id'])): ?><small class="field-error"><?= e($errors['company_id']) ?></small><?php endif; ?>
                </label>
            <?php endif; ?>

            <label class="field">MAC address
                <input name="mac_address" value="<?= e($value('mac_address')) ?>" placeholder="805EC0123456" required>
                <?php if (! empty($errors['mac_address'])): ?><small class="field-error"><?= e($errors['mac_address']) ?></small><?php endif; ?>
            </label>

            <label class="field">Vendor
                <select name="vendor">
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= e($vendor) ?>" <?= $value('vendor', 'yealink') === $vendor ? 'selected' : '' ?>><?= e($vendor) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">Modelo
                <input name="model" value="<?= e($value('model')) ?>" required>
                <?php if (! empty($errors['model'])): ?><small class="field-error"><?= e($errors['model']) ?></small><?php endif; ?>
            </label>

            <label class="field">Firmware
                <input name="firmware_version" value="<?= e($value('firmware_version')) ?>">
            </label>

            <label class="field">Extension
                <select name="extension_uuid">
                    <option value="">Sin extension</option>
                    <?php foreach ($extensions as $extension): ?>
                        <option value="<?= e($extension['uuid']) ?>" <?= $value('extension_uuid') === $extension['uuid'] ? 'selected' : '' ?>><?= e($extension['extension_number'] . ' / ' . $extension['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">Plantilla
                <select name="template_id">
                    <option value="0">Default vendor</option>
                    <?php foreach ($templates as $template): ?>
                        <option value="<?= e((string) $template['id']) ?>" <?= (int) $value('template_id') === (int) $template['id'] ? 'selected' : '' ?>><?= e($template['name'] . ' / ' . $template['vendor']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">Display name
                <input name="display_name" value="<?= e($value('display_name')) ?>">
            </label>

            <label class="field">RPS
                <select name="rps_enabled">
                    <?php foreach (['no', 'yes'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= $value('rps_enabled', 'no') === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active', 'inactive'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $value('status', 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">BLF JSON
                <textarea name="blf_json" rows="5"><?= e($value('blf_json', '[]')) ?></textarea>
                <?php if (! empty($errors['blf_json'])): ?><small class="field-error"><?= e($errors['blf_json']) ?></small><?php endif; ?>
            </label>
        </div>
    </section>
</form>
