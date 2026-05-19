<?php
$source = $old !== [] ? $old : $extension;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
$extensionNumber = $value('extension_number', $value('username', $value('extension')));
$authUser = $value('auth_username', $value('auth_user', $value('username')));
$authPassword = $value('auth_password', $value('auth_secret', $value('password')));
$sipUsername = $value('internal_endpoint_id', $value('id'));
$aor = $value('internal_aor_id', $value('aors', $value('id')));
$email = $value('email', $value('contact_email'));
$server = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? env('APP_URL', '')));
$transportValue = $value('transport', $value('device_type') === 'webrtc' ? 'transport-wss' : '');
?>

<?php if (! empty($errors['general'])): ?>
    <div class="alert error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form class="form-stack wide-form extension-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
    <?php endif; ?>

    <section class="hero-panel">
        <div>
            <span class="eyebrow">Extension</span>
            <h2><?= e($mode === 'edit' ? 'Editar extension' : 'Nueva extension') ?></h2>
            <p>Captura lo esencial. UC200 genera usuario SIP, Auth User, password, AOR, NAT y codecs automaticamente.</p>
        </div>
        <div class="module-meta">
            <a class="button secondary sm" href="/pbx/extensions">Volver</a>
            <button class="button primary sm" type="submit"><?= e(__('actions.save')) ?></button>
        </div>
    </section>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Basico</span>
                <h3>Datos visibles para el usuario</h3>
            </div>
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
            <label class="field">Numero de extension
                <input name="extension_number" value="<?= e($extensionNumber) ?>" inputmode="numeric" required>
                <?php if (! empty($errors['extension'])): ?><small class="field-error"><?= e($errors['extension']) ?></small><?php endif; ?>
            </label>
            <label class="field">Nombre / etiqueta
                <input name="display_name" value="<?= e($value('display_name')) ?>" placeholder="Recepcion, Soporte, Juan Perez">
            </label>
            <label class="field">Email
                <input name="email" type="email" value="<?= e($email) ?>" placeholder="usuario@empresa.com">
                <?php if (! empty($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?>
            </label>
            <label class="field">Caller ID visible
                <input name="callerid" value="<?= e($value('callerid')) ?>" placeholder="&quot;1001&quot; &lt;1001&gt;">
            </label>
            <label class="field">Tipo de dispositivo
                <select name="device_type" data-device-type>
                    <?php foreach (['external_softphone' => 'Softphone externo', 'webrtc' => 'WebRTC', 'ip_phone' => 'Telefono IP'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('device_type', 'external_softphone') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Grabacion habilitada
                <select name="recording_enabled">
                    <?php foreach (['no' => 'No', 'yes' => 'Si'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('recording_enabled', 'no') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Voicemail habilitado
                <select name="voicemail_enabled" data-voicemail-toggle>
                    <?php foreach (['no' => 'No', 'yes' => 'Si'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('voicemail_enabled', $value('mailboxes') !== '' ? 'yes' : 'no') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'Activa', 'inactive' => 'Inactiva'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('status', 'active') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>

    <?php if ($mode === 'edit'): ?>
        <section class="module-panel">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Softphone</span>
                    <h3>Configuracion para Zoiper, Linphone o MicroSIP</h3>
                </div>
                <button class="button secondary sm" type="button" data-modal-open="softphone-config">Configurar Softphone</button>
            </div>
            <div class="credential-grid">
                <div class="credential-box"><span>Servidor</span><strong><?= e($server) ?></strong></div>
                <div class="credential-box"><span>Extension</span><strong><?= e($extensionNumber) ?></strong></div>
                <div class="credential-box"><span>Auth User</span><strong><?= e($authUser) ?></strong></div>
                <div class="credential-box"><span>Password</span><input type="password" readonly value="<?= e($authPassword) ?>" data-secret-field></div>
            </div>
        </section>
    <?php endif; ?>

    <details class="module-panel advanced-panel">
        <summary>
            <span>
                <strong>Opciones avanzadas</strong>
                <small>SIP, AOR, NAT, codecs e identificacion segura.</small>
            </span>
        </summary>
        <div class="form-grid">
            <label class="field">SIP Username / Endpoint
                <input value="<?= e($sipUsername !== '' ? $sipUsername : 'Se genera automaticamente') ?>" readonly>
            </label>
            <label class="field">Auth User
                <div class="input-action">
                    <input value="<?= e($authUser !== '' ? $authUser : 'Se genera automaticamente') ?>" readonly data-copy-value>
                    <button class="button secondary xs" type="button" data-copy-target="previous">Copiar</button>
                </div>
            </label>
            <label class="field">Auth Password
                <div class="input-action">
                    <input type="password" value="<?= e($authPassword !== '' ? $authPassword : 'Se genera automaticamente') ?>" readonly data-secret-field data-copy-value>
                    <button class="button secondary xs" type="button" data-secret-toggle>Mostrar</button>
                    <button class="button secondary xs" type="button" data-copy-target="previous">Copiar</button>
                </div>
            </label>
            <label class="field">AOR / Endpoint
                <input value="<?= e($aor !== '' ? $aor : 'Se genera automaticamente') ?>" readonly>
            </label>
            <label class="field">Contexto
                <input name="context" value="<?= e($value('context', 'contexto_principal')) ?>" required>
                <?php if (! empty($errors['context'])): ?><small class="field-error"><?= e($errors['context']) ?></small><?php endif; ?>
            </label>
            <label class="field">Transport
                <select name="transport" data-transport-select>
                    <option value="">Default UDP</option>
                    <?php foreach ($transports as $transport): ?>
                        <option value="<?= e($transport['id']) ?>" <?= $transportValue === $transport['id'] ? 'selected' : '' ?>><?= e($transport['id']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Allow / Codecs
                <input name="allow" data-codecs-input value="<?= e($value('allow', $value('device_type') === 'webrtc' ? 'opus,ulaw,alaw' : 'ulaw,alaw')) ?>">
            </label>
            <label class="field">Disallow
                <input name="disallow" value="<?= e($value('disallow', 'all')) ?>">
            </label>
            <label class="field">Direct media
                <select name="direct_media">
                    <?php foreach (['no' => 'No', 'yes' => 'Si'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('direct_media', 'no') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Force rport
                <select name="force_rport">
                    <?php foreach (['yes' => 'Si', 'no' => 'No'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('force_rport', 'yes') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Rewrite contact
                <select name="rewrite_contact">
                    <?php foreach (['yes' => 'Si', 'no' => 'No'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('rewrite_contact', 'yes') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">RTP symmetric
                <select name="rtp_symmetric">
                    <?php foreach (['yes' => 'Si', 'no' => 'No'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('rtp_symmetric', 'yes') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Disable direct media on NAT
                <select name="disable_direct_media_on_nat">
                    <?php foreach (['yes' => 'Si', 'no' => 'No'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('disable_direct_media_on_nat', 'yes') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Identify by
                <input value="<?= e($value('identify_by', 'auth_username')) ?>" readonly>
            </label>
            <label class="field">Max contacts
                <input type="number" min="1" name="max_contacts" value="<?= e($value('max_contacts', '1')) ?>">
            </label>
            <label class="field">Qualify frequency
                <input type="number" min="0" name="qualify_frequency" value="<?= e($value('qualify_frequency', '60')) ?>">
            </label>
            <label class="field">Mailbox
                <input name="mailboxes" data-mailbox-input value="<?= e($value('mailboxes', $extensionNumber !== '' ? $extensionNumber . '@default' : '')) ?>">
            </label>
        </div>
        <?php if ($mode === 'edit'): ?>
            <div class="form-actions">
                <button class="button secondary sm" type="submit" form="regen-credentials" onclick="return confirm('Regenerar credenciales SIP? El dispositivo actual dejara de registrar hasta actualizar password.');">Regenerar credenciales</button>
            </div>
        <?php endif; ?>
    </details>
</form>

<?php if ($mode === 'edit'): ?>
    <form id="regen-credentials" method="post" action="/pbx/extensions/regenerate-credentials">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
    </form>

    <div class="modal-backdrop" data-modal="softphone-config" hidden style="display: none;">
        <section class="modal-card">
            <div class="section-heading">
                <div>
                    <span class="eyebrow">Softphone externo</span>
                    <h3><?= e($extensionNumber) ?> / <?= e($value('display_name', 'Extension')) ?></h3>
                </div>
                <button class="icon-button" type="button" data-modal-close>Cerrar</button>
            </div>
            <div class="credential-grid">
                <?php
                $configRows = [
                    'Servidor' => $server,
                    'Puerto' => str_contains($transportValue, 'wss') ? '8089' : '5060',
                    'Transporte' => $transportValue !== '' ? $transportValue : 'UDP',
                    'Auth User' => $authUser,
                    'Auth Password' => $authPassword,
                    'Display name' => $value('display_name', $extensionNumber),
                    'Extension number' => $extensionNumber,
                    'Email' => $email,
                ];
                ?>
                <?php foreach ($configRows as $label => $configValue): ?>
                    <div class="credential-box">
                        <span><?= e($label) ?></span>
                        <strong><?= e($configValue) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="qr-placeholder">QR provisioning preparado</div>
            <textarea class="config-copy" readonly data-config-copy><?php foreach ($configRows as $label => $configValue): ?><?= e($label . ': ' . $configValue . PHP_EOL) ?><?php endforeach; ?></textarea>
            <div class="form-actions">
                <button class="button primary sm" type="button" data-copy-config>Copiar configuracion</button>
                <button class="button secondary sm" type="button" data-modal-close>Cerrar</button>
            </div>
        </section>
    </div>
<?php endif; ?>
