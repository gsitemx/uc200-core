<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">UC200 Apps</span>
        <h2>UC200 Softphone</h2>
        <p>Cliente web integrado para llamadas, presencia, favoritos, audio y contexto CRM. La experiencia principal vive en UC200, sin depender de clientes externos.</p>
    </div>
    <div class="module-meta">
        <span class="badge <?= ($ami['connected'] ?? false) ? 'on' : '' ?>">AMI <?= e(($ami['connected'] ?? false) ? 'connected' : 'offline') ?></span>
        <span class="badge">WebRTC integrado</span>
        <span class="badge">DTLS</span>
        <span class="badge">ICE</span>
        <span class="badge">UC200 Web Client</span>
    </div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Extensiones</span><strong><?= count($extensions) ?></strong><p>Disponibles para el tenant.</p></article>
    <article class="metric-card"><span>Web client listo</span><strong><?= e((string) ($metrics['webrtc_ready'] ?? count(array_filter($extensions, static fn ($row) => ($row['webrtc'] ?? 'no') === 'yes')))) ?></strong><p>Identidades preparadas para el cliente UC200.</p></article>
    <article class="metric-card"><span>Online</span><strong><?= e((string) ($metrics['extensions_online'] ?? 0)) ?></strong><p>Extensiones registradas o reachables.</p></article>
    <article class="metric-card"><span>Sesiones web</span><strong><?= e((string) ($metrics['softphones_connected'] ?? 0)) ?></strong><p>Clientes UC200 activos por tenant.</p></article>
    <article class="metric-card"><span>Favoritos</span><strong><?= count($favorites) ?></strong><p>BLF/presencia rápida.</p></article>
    <article class="metric-card"><span>WebRTC integrado</span><strong><?= e(($settings['wss_mode'] ?? 'relative') === 'relative' ? 'Activo' : 'Custom') ?></strong><p><?= e(($settings['wss_mode'] ?? 'relative') === 'relative' ? 'Usa el mismo dominio del panel' : (string) $settings['wss_url']) ?></p></article>
    <article class="metric-card"><span>AMI</span><strong><?= e(($ami['connected'] ?? false) ? 'Online' : 'Offline') ?></strong><p><?= e((string) ($ami['message'] ?? 'Sin estado')) ?></p></article>
</section>

<section class="softphone-grid" data-softphone-root>
    <article class="module-panel softphone-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Dialer</span>
                <h3>UC200 Web Client</h3>
            </div>
            <div class="softphone-toolbar">
                <button class="button secondary xs" type="button" data-softphone-dock>Dock</button>
                <button class="button secondary xs" type="button" data-softphone-minimize>Min</button>
                <button class="button secondary xs" type="button" data-softphone-restore hidden>Open</button>
                <span class="badge" data-phone-state>desconectado</span>
                <span class="badge <?= ($ami['connected'] ?? false) ? 'on' : '' ?>" data-ami-state><?= e(($ami['connected'] ?? false) ? 'AMI connected' : 'AMI offline') ?></span>
            </div>
        </div>

        <div class="softphone-screen">
            <select data-extension-select>
                <?php foreach ($extensions as $extension): ?>
                    <option
                        value="<?= e($extension['uuid']) ?>"
                        data-endpoint-id="<?= e($extension['id']) ?>"
                        data-company-id="<?= e((string) $extension['company_id']) ?>"
                        <?= (($extension['webrtc'] ?? 'no') === 'yes') ? '' : 'disabled' ?>
                        <?= (($preferences['extension_uuid'] ?? '') === $extension['uuid']) ? 'selected' : '' ?>
                    >
                        <?= e($extension['extension_number']) ?> / <?= e($extension['company_name']) ?><?= ($extension['webrtc'] ?? 'no') === 'yes' ? '' : ' / activar Web Client' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input data-dial-target placeholder="Numero o extension" inputmode="tel">
            <div class="call-timer" data-call-timer>00:00</div>
            <div class="call-chip">
                <div class="call-chip-avatar" data-current-call-avatar>UC</div>
                <div class="call-chip-copy">
                    <strong data-current-call-name>Listo para llamar</strong>
                    <p data-current-call-meta>Selecciona una identidad UC200 y usa el cliente web como experiencia principal.</p>
                </div>
                <span class="badge" data-current-call-state>idle</span>
            </div>
        </div>

        <div class="dialpad">
            <?php foreach (['1','2','3','4','5','6','7','8','9','*','0','#'] as $digit): ?>
                <button class="button secondary sm" type="button" data-dtmf="<?= e($digit) ?>"><?= e($digit) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="softphone-actions">
            <button class="button primary sm" type="button" data-phone-register>Conectar</button>
            <button class="button secondary sm" type="button" data-phone-unregister>Desconectar</button>
            <button class="button primary sm" type="button" data-call-start>Llamar</button>
            <button class="button secondary sm" type="button" data-panel-call>Click to Call</button>
            <button class="button danger sm" type="button" data-call-hangup>Colgar</button>
            <button class="button secondary sm" type="button" data-call-answer>Contestar</button>
            <button class="button secondary sm" type="button" data-call-mute>Mute</button>
            <button class="button secondary sm" type="button" data-call-hold>Hold</button>
            <button class="button secondary sm" type="button" data-call-park>Park</button>
            <button class="button secondary sm" type="button" data-call-pickup>Pickup</button>
            <button class="button secondary sm" type="button" data-call-conference disabled title="Preparacion de conferencia en fase siguiente">Conference</button>
        </div>

        <div class="softphone-control-grid">
            <section class="softphone-section">
                <div class="softphone-section-head">
                    <strong>Transferencia</strong>
                    <span class="badge">Call control</span>
                </div>
                <div class="softphone-field-row">
                    <input data-transfer-target placeholder="Numero o extension destino">
                    <button class="button secondary xs" type="button" data-transfer-blind>Blind</button>
                    <button class="button secondary xs" type="button" data-transfer-attended>Attended</button>
                </div>
            </section>
            <section class="softphone-section">
                <div class="softphone-section-head">
                    <strong>Pickup / Park</strong>
                    <span class="badge">Supervision</span>
                </div>
                <div class="softphone-field-row">
                    <input data-pickup-target placeholder="Extension a recuperar">
                    <button class="button secondary xs" type="button" data-call-pickup-inline>Pickup</button>
                </div>
                <div class="softphone-field-row">
                    <input data-park-lot placeholder="Parking lot opcional">
                    <button class="button secondary xs" type="button" data-call-park-inline>Park</button>
                </div>
            </section>
        </div>
    </article>

    <article class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Presence</span>
                <h3>Estado y favoritos</h3>
            </div>
            <select data-presence-select>
                <?php foreach (['available', 'busy', 'away', 'dnd', 'offline'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= (($preferences['default_presence'] ?? 'available') === $status) ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="module-list">
            <?php foreach ($presence as $row): ?>
                <button class="module-row softphone-blf" type="button" data-call-number="<?= e($row['extension_number']) ?>">
                    <div>
                        <strong><?= e($row['extension_number']) ?></strong>
                        <p><?= e($row['endpoint_id']) ?></p>
                    </div>
                    <span class="badge <?= in_array($row['status'], ['available', 'online'], true) ? 'on' : '' ?>" data-realtime-presence data-endpoint-id="<?= e($row['endpoint_id']) ?>"><?= e($row['status']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Audio</span>
                <h3>Dispositivos</h3>
            </div>
            <button class="button secondary xs" type="button" data-audio-test>Test</button>
        </div>
        <div class="form-stack">
            <div class="field"><label>Microfono</label><select data-audio-input></select></div>
            <div class="field"><label>Altavoz</label><select data-audio-output></select></div>
            <div class="field"><label>Ringtone volume</label><input data-ringtone-volume type="range" min="0" max="100" value="<?= e((string) ($preferences['ringtone_volume'] ?? 70)) ?>"></div>
            <div class="field"><label>Output volume</label><input data-output-volume type="range" min="0" max="100" value="100"></div>
            <label class="softphone-check"><input data-notifications-toggle type="checkbox" <?= (($preferences['notifications_enabled'] ?? 'yes') === 'yes') ? 'checked' : '' ?>> Notificaciones del navegador</label>
        </div>
    </article>

    <article class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">CRM</span>
                <h3>Caller ID inteligente</h3>
            </div>
        </div>
        <div class="module-list" data-contact-context>
            <div class="module-row">
                <div>
                    <strong data-contact-name>Sin contacto seleccionado</strong>
                    <p data-contact-company>Cuando entre o marques una llamada mostraremos nombre, empresa y actividad reciente.</p>
                </div>
                <span class="badge" data-contact-status>CRM</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Interaccion</th><th>Sentido</th><th>Duracion</th></tr></thead>
                <tbody data-contact-history>
                    <tr><td colspan="3"><span class="empty-copy">Sin historial cargado.</span></td></tr>
                </tbody>
            </table>
        </div>
    </article>

    <article class="module-panel" id="sip-status">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Recent calls</span>
                <h3>Actividad reciente</h3>
            </div>
        </div>
        <div class="module-list" data-recent-calls>
            <?php foreach ($recentCalls as $call): ?>
                <button class="module-row" type="button" data-call-number="<?= e($call['remote_number']) ?>">
                    <div>
                        <strong><?= e($call['remote_number']) ?></strong>
                        <p><?= e($call['direction']) ?> / <?= e($call['disposition']) ?> / <?= e((string) $call['duration_seconds']) ?>s</p>
                    </div>
                    <span class="badge"><?= e((string) $call['started_at']) ?></span>
                </button>
            <?php endforeach; ?>
            <?php if ($recentCalls === []): ?>
                <p class="empty-copy">Sin llamadas recientes.</p>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Identidades</span>
                <h3>Preparacion del cliente UC200</h3>
            </div>
        </div>
        <div class="table-wrap">
            <table>
            <thead><tr><th>Extension</th><th>Metodo de conexion</th><th>Web Client</th><th>Calidad de audio</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($extensions as $extension): ?>
                    <tr>
                        <td><strong><?= e($extension['extension_number']) ?></strong><span><?= e($extension['id']) ?></span></td>
                        <td><?= e($extension['transport'] ?? '-') ?></td>
                        <td><span class="badge <?= ($extension['webrtc'] ?? 'no') === 'yes' ? 'on' : '' ?>"><?= e($extension['webrtc'] ?? 'no') ?></span></td>
                        <td><?= e($extension['allow']) ?></td>
                        <td>
                            <?php if (($extension['webrtc'] ?? 'no') !== 'yes'): ?>
                                <form method="post" action="/softphone/enable-endpoint">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
                                    <button class="button secondary xs" type="submit">Activar Web Client</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="incoming-call-popup" data-incoming-popup hidden>
    <strong data-incoming-number>Llamada entrante</strong>
    <div>
        <button class="button primary xs" type="button" data-call-answer>Answer</button>
        <button class="button danger xs" type="button" data-call-hangup>Reject</button>
    </div>
</div>

<audio data-remote-audio autoplay></audio>
<audio data-ring-audio loop></audio>
<script src="/assets/vendor/sip-0.21.2.min.js"></script>
<script src="/assets/js/softphone.js"></script>
