<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PBX</span>
        <h2><?= e(__('modules.extensions')) ?></h2>
        <p>Extensiones visibles para usuarios, con credenciales SIP seguras generadas automaticamente.</p>
    </div>
    <a class="button primary sm" href="/pbx/extensions/create"><?= e(__('actions.new_extension')) ?></a>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Directory</span>
            <h3>Usuarios y dispositivos</h3>
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
                    $configRows = [
                        'Servidor' => $server,
                        'Puerto' => str_contains((string) $transport, 'wss') ? '8089' : '5060',
                        'Transporte' => $transport,
                        'Auth User' => $extension['auth_username'] ?? $extension['auth_user'] ?? $extension['username'],
                        'Auth Password' => $extension['auth_password'] ?? $extension['auth_secret'] ?? $extension['password'],
                        'Display name' => $extension['display_name'] ?? $extension['extension_number'],
                        'Extension number' => $extension['extension_number'] ?? $extension['username'],
                        'Email' => $email,
                    ];
                    ?>
                    <tr>
                        <td>
                            <a class="table-link" href="<?= $editUrl ?>">
                                <strong><?= e($extension['extension_number'] ?? $extension['username']) ?></strong>
                            </a>
                            <span><?= e($extension['internal_endpoint_id'] ?? $extension['id']) ?></span>
                        </td>
                        <td><?= e($extension['display_name'] ?? '') ?><span><?= e($email) ?></span></td>
                        <td><?= e(str_replace('_', ' ', $extension['device_type'] ?? 'external_softphone')) ?></td>
                        <td><?= e($extension['callerid'] ?? '') ?></td>
                        <td><strong><?= e($configRows['Auth User']) ?></strong></td>
                        <td><?= e($extension['company_name']) ?></td>
                        <td><span class="badge <?= $extension['status'] === 'active' ? 'on' : '' ?>"><?= e($extension['status']) ?></span></td>
                        <td class="actions-cell">
                            <button class="button secondary xs" type="button" data-modal-open="softphone-<?= e($extension['uuid']) ?>">Configurar</button>
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
                                            <span class="eyebrow">Softphone externo</span>
                                            <h3><?= e($configRows['Extension number']) ?> / <?= e($configRows['Display name']) ?></h3>
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
