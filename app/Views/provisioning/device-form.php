<?php
$source = $old !== [] ? $old : $device;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
$expiry = $value('token_expires_at');
if ($expiry !== '') {
    $expiry = str_replace(' ', 'T', substr($expiry, 0, 16));
}
$selectedVendor = $value('vendor', $vendors[0] ?? 'yealink');
$selectedModel = $value('model');
$profile = $currentProfile ?? [];
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($device['uuid']) ?>">
        <input type="hidden" name="provisioning_secret" value="<?= e($device['provisioning_secret']) ?>">
    <?php endif; ?>

    <section class="hero-panel">
        <div>
            <span class="eyebrow">Provisioning</span>
            <h2><?= e($title) ?></h2>
            <p>Selecciona marca, modelo y extension. UC200 genera automaticamente la URL, token seguro y archivo de configuracion.</p>
        </div>
        <div class="module-meta">
            <button class="button primary sm" type="submit">Guardar telefono</button>
        </div>
    </section>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Flujo simple</span>
                <h3>Datos principales</h3>
            </div>
        </div>

        <div class="form-grid">
            <?php if (has_role('super-admin')): ?>
                <label class="field">Empresa
                    <select name="company_id" data-provisioning-company>
                        <option value="0">Selecciona</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id') === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (! empty($errors['company_id'])): ?><small class="field-error"><?= e($errors['company_id']) ?></small><?php endif; ?>
                </label>
            <?php endif; ?>

            <label class="field">Marca
                <select name="vendor" data-provisioning-brand required>
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= e($vendor) ?>" <?= $selectedVendor === $vendor ? 'selected' : '' ?>><?= e($brands[$vendor]['label'] ?? ucfirst($vendor)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (! empty($errors['vendor'])): ?><small class="field-error"><?= e($errors['vendor']) ?></small><?php endif; ?>
            </label>

            <label class="field">Modelo
                <select name="model" data-provisioning-model required>
                    <option value="<?= e($selectedModel) ?>" selected><?= e($selectedModel !== '' ? $selectedModel : 'Selecciona un modelo') ?></option>
                </select>
                <?php if (! empty($errors['model'])): ?><small class="field-error"><?= e($errors['model']) ?></small><?php endif; ?>
            </label>

            <label class="field">Extension asignada
                <select name="extension_uuid" data-provisioning-extension>
                    <option value="">Sin extension</option>
                    <?php foreach ((has_role('super-admin') ? $allExtensions : $extensions) as $extension): ?>
                        <option value="<?= e($extension['uuid']) ?>" data-company-id="<?= e((string) ($extension['company_id'] ?? ($value('company_id') ?: ''))) ?>" <?= $value('extension_uuid') === $extension['uuid'] ? 'selected' : '' ?>><?= e($extension['extension_number'] . ' / ' . $extension['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">MAC address
                <input name="mac_address" value="<?= e($value('mac_address')) ?>" placeholder="805EC0123456" required>
                <?php if (! empty($errors['mac_address'])): ?><small class="field-error"><?= e($errors['mac_address']) ?></small><?php endif; ?>
            </label>

            <label class="field">Nombre visible
                <input name="display_name" value="<?= e($value('display_name')) ?>" placeholder="Recepcion, Direccion, Caja 1">
            </label>

            <label class="field">Phonebook
                <select name="phonebook_id">
                    <option value="0">Sin phonebook</option>
                    <?php foreach ($phonebooks as $phonebook): ?>
                        <option value="<?= e((string) $phonebook['id']) ?>" <?= (int) $value('phonebook_id') === (int) $phonebook['id'] ? 'selected' : '' ?>><?= e($phonebook['name'] . ' / ' . $phonebook['company_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="card-grid provisioning-capabilities">
            <article class="metric-card">
                <span>Provision integrado</span>
                <strong><?= e($configFilename ?? 'Automatico') ?></strong>
                <p>UC200 genera el archivo correcto para el modelo seleccionado.</p>
            </article>
            <article class="metric-card">
                <span>Servidor SIP</span>
                <strong><?= e((string) ($defaults['sip_server'] ?? '127.0.0.1')) ?></strong>
                <p><?= e((string) ($defaults['transport'] ?? 'UDP')) ?> / <?= e(implode(', ', (array) (($profile['codecs'] ?? []) !== [] ? $profile['codecs'] : ($defaults['default_codecs'] ?? [])))) ?></p>
            </article>
            <article class="metric-card">
                <span>BLF</span>
                <strong><?= ! empty($profile['supports_blf']) ? 'Si' : 'No' ?></strong>
                <p>Phonebook: <?= ! empty($profile['supports_phonebook']) ? 'Si' : 'No' ?> / Reboot: <?= ! empty($profile['supports_remote_reboot']) ? 'Si' : 'No' ?></p>
            </article>
            <article class="metric-card">
                <span>Default operativo</span>
                <strong><?= e((string) ($defaults['timezone'] ?? 'America/Mazatlan')) ?></strong>
                <p><?= e((string) ($defaults['language'] ?? 'es')) ?> / NTP <?= e((string) ($defaults['ntp_server'] ?? 'pool.ntp.org')) ?></p>
            </article>
        </div>

        <div class="module-panel nested-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Instrucciones</span>
                    <h3>Instalacion rapida por marca</h3>
                </div>
            </div>
            <ol class="compact-list" data-provisioning-instructions>
                <?php foreach ($vendorInstructions as $instruction): ?>
                    <li><?= e($instruction) ?></li>
                <?php endforeach; ?>
            </ol>
            <?php if (! empty($profile['notes'])): ?>
                <p class="inline-help" data-provisioning-notes><?= e((string) $profile['notes']) ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="module-panel">
        <details <?= $mode === 'edit' ? 'open' : '' ?>>
            <summary>Opciones avanzadas <?= $advancedTemplates ? '/ superadmin' : '' ?></summary>
            <div class="form-grid">
                <label class="field">Firmware
                    <input name="firmware_version" value="<?= e($value('firmware_version')) ?>">
                </label>

                <label class="field">Token expira en
                    <input type="datetime-local" name="token_expires_at" value="<?= e($expiry) ?>">
                    <?php if (! empty($errors['token_expires_at'])): ?><small class="field-error"><?= e($errors['token_expires_at']) ?></small><?php endif; ?>
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
                    <small>Si se deja vacio, UC200 intentara generar BLF automatico con extensiones activas.</small>
                    <?php if (! empty($errors['blf_json'])): ?><small class="field-error"><?= e($errors['blf_json']) ?></small><?php endif; ?>
                </label>

                <?php if ($advancedTemplates): ?>
                    <label class="field">Plantilla personalizada
                        <select name="template_id">
                            <option value="0">Usar plantilla interna recomendada</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= e((string) $template['id']) ?>" <?= (int) $value('template_id') === (int) $template['id'] ? 'selected' : '' ?>><?= e($template['name'] . ' / ' . $template['vendor']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small>Solo usuarios avanzados. La plantilla interna cubre el flujo normal.</small>
                    </label>
                <?php endif; ?>
            </div>
        </details>
    </section>

    <?php if ($mode === 'edit' && ! empty($device['provisioning_secret']) && ! empty($provisioningUrl)): ?>
        <section class="module-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Provisioning URL</span>
                    <h3><?= e($device['mac_address']) ?> / <?= e($brands[$selectedVendor]['label'] ?? ucfirst($selectedVendor)) ?></h3>
                </div>
            </div>

            <div class="quick-panel">
                <div>
                    <strong><?= e($configFilename ?? 'config') ?></strong>
                    <p>Token seguro, aislamiento por tenant y trazabilidad de descargas.</p>
                </div>
                <div class="quick-panel-actions">
                    <input class="inline-url" readonly value="<?= e($provisioningUrl) ?>" data-copy-value>
                    <button class="button secondary xs" type="button" data-copy-target="previous">Copiar URL provisioning</button>
                    <a class="button secondary xs" href="/provisioning/devices/config?id=<?= e($device['uuid']) ?>" target="_blank" rel="noopener">Descargar archivo config</a>
                </div>
            </div>

            <div class="form-actions">
                <button class="button secondary sm" type="submit" form="regen-token" onclick="return confirm('Regenerar token de provisioning? La URL anterior dejara de funcionar.');">Regenerar token</button>
                <button class="button secondary sm" type="button" disabled>Reiniciar telefono (preparado)</button>
            </div>
        </section>
    <?php endif; ?>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="regen-token" method="post" action="/provisioning/devices/regenerate-token">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($device['uuid']) ?>">
        <input type="hidden" name="token_expires_at" value="<?= e((string) ($device['token_expires_at'] ?? '')) ?>">
    </form>
<?php endif; ?>

<script>
window.uc200ProvisioningCatalog = <?= json_encode($modelCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.uc200ProvisioningBrands = <?= json_encode($brands, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.uc200ProvisioningSelected = <?= json_encode(['vendor' => $selectedVendor, 'model' => $selectedModel], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
