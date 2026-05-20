<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">UC200 Communications</span>
        <h2><?= e(__('pbx.dashboard_title')) ?></h2>
        <p><?= e(__('pbx.dashboard_copy')) ?></p>
    </div>
    <div class="module-meta">
        <span class="badge <?= ($ami['status']['connected'] ?? false) ? 'on' : '' ?>">AMI <?= e(($ami['status']['connected'] ?? false) ? 'connected' : 'offline') ?></span>
        <a class="button secondary sm" href="/pbx/ring-groups">Ring Groups</a>
        <a class="button secondary sm" href="/pbx/ivrs">IVR</a>
        <a class="button secondary sm" href="/pbx/trunks">Lineas SIP</a>
        <a class="button secondary sm" href="/pbx/transports"><?= e(__('modules.transports')) ?></a>
        <a class="button secondary sm" href="/pbx/recordings"><?= e(__('modules.recordings')) ?></a>
        <a class="button primary sm" href="/pbx/extensions/create"><?= e(__('actions.new_extension')) ?></a>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">UC200 Communications Engine</span>
            <h3>Automatizacion y control de llamadas</h3>
        </div>
        <span class="badge <?= ($ami['status']['connected'] ?? false) ? 'on' : '' ?>"><?= e(($ami['status']['connected'] ?? false) ? 'Conectado' : 'Desconectado') ?></span>
    </div>
    <div class="credential-grid">
        <div class="credential-box"><span>Host</span><strong><?= e((string) ($ami['status']['host'] ?? $ami['settings']['host'])) ?></strong></div>
        <div class="credential-box"><span>Port</span><strong><?= e((string) ($ami['status']['port'] ?? $ami['settings']['port'])) ?></strong></div>
        <div class="credential-box"><span>Usuario</span><strong><?= e((string) ($ami['status']['username'] ?? $ami['settings']['username'])) ?></strong></div>
        <div class="credential-box"><span>Status</span><strong><?= e((string) ($ami['status']['message'] ?? 'Sin estado')) ?></strong></div>
    </div>
    <form class="form-stack" method="post" action="/pbx/ami/settings">
        <?= csrf_field() ?>
        <div class="form-grid compact-grid">
            <label class="field">
                <span>Host</span>
                <input name="host" value="<?= e((string) $ami['settings']['host']) ?>" placeholder="127.0.0.1">
            </label>
            <label class="field">
                <span>Port</span>
                <input name="port" type="number" min="1" max="65535" value="<?= e((string) $ami['settings']['port']) ?>">
            </label>
            <label class="field">
                <span>Username</span>
                <input name="username" value="<?= e((string) $ami['settings']['username']) ?>" placeholder="admin">
            </label>
            <label class="field">
                <span>Password</span>
                <input name="password" type="password" placeholder="<?= ($ami['settings']['password_configured'] ?? false) ? 'Guardar actual' : 'Secret AMI' ?>">
            </label>
            <label class="field">
                <span>Connect timeout</span>
                <input name="connect_timeout" type="number" min="1" max="20" value="<?= e((string) $ami['settings']['connect_timeout']) ?>">
            </label>
            <label class="field">
                <span>Perfil de llamadas de salida</span>
                <input name="originate_context" value="<?= e((string) $ami['settings']['originate_context']) ?>" placeholder="Usar perfil de la identidad">
            </label>
        </div>
        <label class="softphone-check"><input type="checkbox" name="enabled" value="yes" <?= (($ami['settings']['enabled'] ?? 'no') === 'yes') ? 'checked' : '' ?>> Habilitar AMI</label>
        <?php if (($ami['settings']['secure_storage'] ?? true) !== true): ?>
            <div class="alert warning">APP_KEY no esta configurado. Las credenciales siguen protegidas, pero conviene definir una llave propia para endurecer el cifrado.</div>
        <?php endif; ?>
        <div class="form-actions">
            <button class="button primary sm" type="submit">Guardar gateway AMI</button>
        </div>
    </form>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">UC200 Apps</span>
            <h3>Web Client integrado y media segura</h3>
        </div>
        <span class="badge <?= (($webrtc['enable_webrtc'] ?? 'yes') === 'yes') ? 'on' : '' ?>"><?= e((($webrtc['enable_webrtc'] ?? 'yes') === 'yes') ? 'Activo' : 'Inactivo') ?></span>
    </div>
    <form class="form-stack" method="post" action="/softphone/settings">
        <?= csrf_field() ?>
        <div class="form-grid compact-grid">
            <label class="field">
                <span>Dominio UC200</span>
                <input name="sip_domain" value="<?= e((string) ($webrtc['sip_domain'] ?? '')) ?>" placeholder="pbx.midominio.com">
            </label>
            <label class="field">
                <span>STUN server</span>
                <input name="stun_server" value="<?= e((string) ($webrtc['stun_server'] ?? '')) ?>" placeholder="stun:stun.l.google.com:19302">
            </label>
            <label class="field">
                <span>TURN server</span>
                <input name="turn_server" value="<?= e((string) ($webrtc['turn_server'] ?? '')) ?>" placeholder="turn:turn.midominio.com:3478">
            </label>
            <label class="field">
                <span>Session timeout</span>
                <input name="session_timeout_minutes" type="number" min="5" max="1440" value="<?= e((string) ($webrtc['session_timeout_minutes'] ?? 480)) ?>">
            </label>
        </div>
        <details class="module-panel" style="padding:12px 14px;">
            <summary><strong>Opciones avanzadas Web Client</strong></summary>
            <div class="form-grid compact-grid" style="margin-top:12px;">
                <label class="field">
                    <span>WebSocket path</span>
                    <input name="websocket_path" value="<?= e((string) ($webrtc['websocket_path'] ?? '/ws')) ?>" placeholder="/ws">
                </label>
                <label class="field">
                    <span>WebSocket port</span>
                    <input name="websocket_port" type="number" min="1" max="65535" value="<?= e((string) ($webrtc['websocket_port'] ?? 8089)) ?>">
                </label>
                <label class="field">
                    <span>WebSocket URL override</span>
                    <input name="wss_url" value="<?= e((string) (($webrtc['wss_mode'] ?? 'relative') === 'absolute' ? ($webrtc['wss_url'] ?? '') : '')) ?>" placeholder="wss://pbx.midominio.com/ws">
                </label>
            </div>
            <p class="empty-copy" style="margin-top:8px;">Por defecto UC200 usa WebRTC integrado por el mismo dominio del panel: <strong>/ws</strong>. Solo cambia esto si usas una topologia especial.</p>
        </details>
        <div class="form-grid compact-grid">
            <label class="softphone-check"><input type="checkbox" name="enable_webrtc" value="yes" <?= (($webrtc['enable_webrtc'] ?? 'yes') === 'yes') ? 'checked' : '' ?>> Activar Web Client</label>
            <label class="softphone-check"><input type="checkbox" name="dtls_enabled" value="yes" <?= (($webrtc['dtls_enabled'] ?? 'yes') === 'yes') ? 'checked' : '' ?>> Activar DTLS</label>
            <label class="softphone-check"><input type="checkbox" name="ice_enabled" value="yes" <?= (($webrtc['ice_enabled'] ?? 'yes') === 'yes') ? 'checked' : '' ?>> Activar ICE</label>
            <label class="softphone-check"><input type="checkbox" name="notifications_enabled" value="yes" <?= (($webrtc['notifications_enabled'] ?? 'yes') === 'yes') ? 'checked' : '' ?>> Notificaciones del navegador</label>
        </div>
        <div class="credential-grid">
            <div class="credential-box"><span>WebRTC integrado</span><strong><?= e((string) (($webrtc['wss_mode'] ?? 'relative') === 'relative' ? 'wss://{dominio_actual}' . ($webrtc['websocket_path'] ?? '/ws') : ($webrtc['wss_url'] ?? ''))) ?></strong></div>
            <div class="credential-box"><span>STUN</span><strong><?= e((string) ($webrtc['stun_server'] ?? '-')) ?></strong></div>
            <div class="credential-box"><span>TURN</span><strong><?= e((string) ($webrtc['turn_server'] ?? '-')) ?></strong></div>
            <div class="credential-box"><span>Media</span><strong><?= e((($webrtc['dtls_enabled'] ?? 'yes') === 'yes') ? 'DTLS/SRTP' : 'Plain RTP') ?></strong></div>
        </div>
        <div class="form-actions">
            <button class="button primary sm" type="submit">Guardar WebRTC</button>
            <a class="button secondary sm" href="/softphone">Abrir Web Client</a>
        </div>
    </form>
</section>

<section class="card-grid">
    <?php foreach ($cards as $card): ?>
        <article class="metric-card"<?= isset($card['realtime_key']) ? ' data-realtime-stat-card="' . e((string) $card['realtime_key']) . '"' : '' ?>>
            <span><?= e($card['label']) ?></span>
            <strong<?= isset($card['realtime_key']) ? ' data-realtime-stat-value="' . e((string) $card['realtime_key']) . '"' : '' ?>><?= e((string) $card['value']) ?></strong>
            <p><?= e($card['hint']) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Reglas UC200</span>
            <h3>Flujo de llamadas</h3>
        </div>
        <a class="button secondary sm" href="/pbx/outbound-routes/create">Nueva ruta</a>
    </div>
    <div class="module-list">
        <?php foreach ($flowModules as $module): ?>
            <a class="module-row" href="<?= e($module['href']) ?>">
                <div>
                    <strong><?= e($module['label']) ?></strong>
                    <p><?= e($module['hint']) ?></p>
                </div>
                <span class="badge">Config</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?= e(__('modules.extensions')) ?></span>
            <h3><?= e(__('pbx.sip_status')) ?></h3>
        </div>
        <a class="button secondary sm" href="/pbx/extensions"><?= e(__('actions.view_all')) ?></a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('pbx.identity')) ?></th>
                    <th><?= e(__('fields.auth_user')) ?></th>
                    <th><?= e(__('fields.company')) ?></th>
                    <th><?= e(__('fields.context')) ?></th>
                    <th>Conexion</th>
                    <th>Presencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extensions as $extension): ?>
                    <?php $editUrl = '/pbx/extensions/edit?id=' . e($extension['uuid']); ?>
                    <tr>
                        <td>
                            <a class="table-link" href="<?= $editUrl ?>">
                                <strong><?= e($extension['extension_number'] ?? $extension['username']) ?></strong>
                            </a>
                            <span><?= e($extension['name'] ?? $extension['display_name'] ?? 'Identidad UC200') ?></span>
                        </td>
                        <td><?= e($extension['auth_username'] ?? $extension['auth_user'] ?? $extension['username']) ?></td>
                        <td><?= e($extension['company_name']) ?></td>
                        <td><?= e($extension['context']) ?></td>
                        <td><span class="badge <?= $extension['sip_status'] === 'registered' ? 'on' : '' ?>"><?= e($extension['sip_status']) ?></span></td>
                        <td><span class="badge <?= in_array($extension['presence_status'], ['available', 'online'], true) ? 'on' : '' ?>" data-realtime-presence data-endpoint-id="<?= e((string) ($extension['internal_endpoint_id'] ?? $extension['id'])) ?>"><?= e($extension['presence_status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
