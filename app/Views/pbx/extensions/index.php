<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PBX</span>
        <h2>Identidades de comunicacion UC200</h2>
        <p>Las extensiones representan identidades de comunicacion. Los dispositivos y metodos de conexion se gestionan como capas vinculadas, no como la identidad principal.</p>
    </div>
    <div class="module-meta">
        <span class="badge <?= ($ami['status']['connected'] ?? false) ? 'on' : '' ?>">AMI <?= e(($ami['status']['connected'] ?? false) ? 'connected' : 'offline') ?></span>
        <a class="button primary sm" href="/pbx/extensions/create"><?= e(__('actions.new_extension')) ?></a>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Directory</span>
            <h3>Identidades y experiencias vinculadas</h3>
        </div>
        <div class="table-search"><input data-table-search="#extensions-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="extensions-table">
            <thead>
                <tr>
                    <th><?= e(__('fields.extension')) ?></th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th><?= e(__('fields.caller_id')) ?></th>
                    <th><?= e(__('fields.auth_user')) ?></th>
                    <th><?= e(__('fields.company')) ?></th>
                    <th><?= e(__('fields.status')) ?></th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extensions as $extension): ?>
                    <?php
                    $editUrl = '/pbx/extensions/edit?id=' . e($extension['uuid']);
                    $server = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? env('APP_URL', '')));
                    $transport = $extension['transport'] ?: 'UDP';
                    $email = $extension['email'] ?? $extension['contact_email'] ?? '';
                    $deviceLabel = match ((string) ($extension['device_type'] ?? 'external_softphone')) {
                        'webrtc' => 'UC200 Softphone',
                        'ip_phone' => 'Telefono IP',
                        default => 'Softphone externo temporal/pruebas',
                    };
                    $configRows = [
                        'Servidor' => $server,
                        'Puerto' => str_contains((string) $transport, 'wss') ? '8089' : '5060',
                        'Metodo de conexion' => $transport,
                        'Auth User' => $extension['auth_username'] ?? $extension['auth_user'] ?? $extension['username'],
                        'Auth Password' => $extension['auth_password'] ?? $extension['auth_secret'] ?? $extension['password'],
                        'Nombre visible' => $extension['display_name'] ?? $extension['extension_number'],
                        'Numero de extension' => $extension['extension_number'] ?? $extension['username'],
                        'Email' => $email,
                    ];
                    ?>
                    <tr>
                        <td>
                            <a class="table-link" href="<?= $editUrl ?>">
                                <strong><?= e($extension['extension_number'] ?? $extension['username']) ?></strong>
                            </a>
                            <span><?= e($extension['display_name'] ?? 'Identidad UC200') ?></span>
                        </td>
                        <td><?= e($extension['display_name'] ?? '') ?><span><?= e($email) ?></span></td>
                        <td><?= e($deviceLabel) ?></td>
                        <td><?= e($extension['callerid'] ?? '') ?></td>
                        <td><strong><?= e($configRows['Auth User']) ?></strong></td>
                        <td><?= e($extension['company_name']) ?></td>
                        <?php $liveStatus = (string) ($extension['presence_status'] ?: ($extension['sip_status'] ?: $extension['status'])); ?>
                        <td><span class="badge <?= in_array($liveStatus, ['available', 'online', 'registered'], true) ? 'on' : '' ?>" data-realtime-presence data-endpoint-id="<?= e((string) ($extension['internal_endpoint_id'] ?? $extension['id'])) ?>"><?= e($liveStatus) ?></span></td>
                        <td class="actions-cell">
                            <form method="post" action="/pbx/originate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="destination" value="<?= e($extension['extension_number'] ?? '') ?>">
                                <input type="hidden" name="company_id" value="<?= e((string) ($extension['company_id'] ?? '')) ?>">
                                <input type="hidden" name="redirect_to" value="/pbx/extensions">
                                <button class="button secondary xs" type="submit">Llamar</button>
                            </form>
                            <a class="button primary xs" href="/softphone">Abrir Web Client</a>
                            <a class="button secondary xs" href="/provisioning/devices/create?company_id=<?= e((string) ($extension['company_id'] ?? '')) ?>&extension_uuid=<?= e((string) ($extension['uuid'] ?? '')) ?>">Asignar telefono</a>
                            <button class="button secondary xs" type="button" data-modal-open="softphone-<?= e($extension['uuid']) ?>">Softphone externo</button>
                            <a class="button secondary xs" href="<?= $editUrl ?>"><?= e(__('actions.edit')) ?></a>
                            <form method="post" action="/pbx/extensions/regenerate-credentials" onsubmit="return confirm('Regenerar credenciales SIP?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
                                <button class="button secondary xs" type="submit"><?= e(__('actions.regenerate')) ?></button>
                            </form>
                            <form method="post" action="/pbx/extensions/delete" onsubmit="return confirm('Eliminar extension?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
                                <button class="button danger xs" type="submit"><?= e(__('actions.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                    <tr class="modal-row">
                        <td colspan="8">
                            <div class="modal-backdrop" data-modal="softphone-<?= e($extension['uuid']) ?>" hidden style="display: none;">
                                <section class="modal-card">
                                    <div class="section-heading">
                                        <div>
                                            <span class="eyebrow">Compatibilidad</span>
                                            <h3>Softphone externo temporal/pruebas</h3>
                                        </div>
                                        <button class="icon-button" type="button" data-modal-close>Cerrar</button>
                                    </div>
                                    <div class="credential-grid">
                                        <?php foreach ($configRows as $label => $configValue): ?>
                                            <div class="credential-box">
                                                <span><?= e($label) ?></span>
                                                <?php if ($label === 'Auth Password'): ?>
                                                    <input type="password" readonly value="<?= e($configValue) ?>" data-secret-field>
                                                <?php else: ?>
                                                    <strong><?= e($configValue) ?></strong>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="qr-placeholder">QR provisioning preparado</div>
                                    <textarea class="config-copy" readonly data-config-copy><?php foreach ($configRows as $label => $configValue): ?><?= e($label . ': ' . $configValue . PHP_EOL) ?><?php endforeach; ?></textarea>
                                    <div class="form-actions">
                                        <button class="button secondary sm" type="button" data-secret-toggle>Mostrar/Ocultar password</button>
                                        <button class="button primary sm" type="button" data-copy-config>Copiar configuracion</button>
                                        <button class="button secondary sm" type="button" data-modal-close>Cerrar</button>
                                    </div>
                                </section>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
