<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">WebRTC Softphone</span>
        <h2>Telefonia web enterprise</h2>
        <p>Registro SIP por WSS, DTLS/SRTP, controles de llamada, presencia, favoritos y seleccion de audio.</p>
    </div>
    <div class="module-meta">
        <span class="badge">WSS</span>
        <span class="badge">DTLS</span>
        <span class="badge">ICE</span>
        <span class="badge">SIP.js</span>
    </div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Extensiones</span><strong><?= count($extensions) ?></strong><p>Disponibles para el tenant.</p></article>
    <article class="metric-card"><span>WebRTC ready</span><strong><?= count(array_filter($extensions, static fn ($row) => ($row['webrtc'] ?? 'no') === 'yes')) ?></strong><p>Endpoints con WSS/DTLS.</p></article>
    <article class="metric-card"><span>Favoritos</span><strong><?= count($favorites) ?></strong><p>BLF/presencia rápida.</p></article>
    <article class="metric-card"><span>WSS</span><strong><?= e(parse_url((string) $settings['wss_url'], PHP_URL_PORT) ?: '8089') ?></strong><p><?= e($settings['wss_url']) ?></p></article>
</section>

<section class="softphone-grid" data-softphone-root>
    <article class="module-panel softphone-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Dialer</span>
                <h3>Floating console</h3>
            </div>
            <div class="softphone-toolbar">
                <button class="button secondary xs" type="button" data-softphone-dock>Dock</button>
                <button class="button secondary xs" type="button" data-softphone-minimize>Min</button>
                <button class="button secondary xs" type="button" data-softphone-restore hidden>Open</button>
                <span class="badge" data-phone-state>offline</span>
            </div>
        </div>

        <div class="softphone-screen">
            <select data-extension-select>
                <?php foreach ($extensions as $extension): ?>
                    <option value="<?= e($extension['uuid']) ?>" <?= (($preferences['extension_uuid'] ?? '') === $extension['uuid']) ? 'selected' : '' ?>>
                        <?= e($extension['extension_number']) ?> / <?= e($extension['company_name']) ?><?= ($extension['webrtc'] ?? 'no') === 'yes' ? '' : ' / enable WebRTC' ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input data-dial-target placeholder="Numero o extension" inputmode="tel">
            <div class="call-timer" data-call-timer>00:00</div>
        </div>

        <div class="dialpad">
            <?php foreach (['1','2','3','4','5','6','7','8','9','*','0','#'] as $digit): ?>
                <button class="button secondary sm" type="button" data-dtmf="<?= e($digit) ?>"><?= e($digit) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="softphone-actions">
            <button class="button primary sm" type="button" data-phone-register>Register</button>
            <button class="button secondary sm" type="button" data-phone-unregister>Unregister</button>
            <button class="button primary sm" type="button" data-call-start>Call</button>
            <button class="button danger sm" type="button" data-call-hangup>Hangup</button>
            <button class="button secondary sm" type="button" data-call-answer>Answer</button>
            <button class="button secondary sm" type="button" data-call-mute>Mute</button>
            <button class="button secondary sm" type="button" data-call-hold>Hold</button>
        </div>

        <div class="softphone-transfer">
            <input data-transfer-target placeholder="Transfer target">
            <button class="button secondary xs" type="button" data-transfer-blind>Blind</button>
            <button class="button secondary xs" type="button" data-transfer-attended>Attended</button>
        </div>
    </article>

    <article class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Presence</span>
                <h3>Estado y BLF</h3>
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
                    <span class="badge <?= in_array($row['status'], ['available', 'online'], true) ? 'on' : '' ?>"><?= e($row['status']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Audio</span>
                <h3>Devices</h3>
            </div>
            <button class="button secondary xs" type="button" data-audio-test>Test</button>
        </div>
        <div class="form-stack">
            <div class="field"><label>Microphone</label><select data-audio-input></select></div>
            <div class="field"><label>Speaker</label><select data-audio-output></select></div>
            <div class="field"><label>Ringtone volume</label><input data-ringtone-volume type="range" min="0" max="100" value="<?= e((string) ($preferences['ringtone_volume'] ?? 70)) ?>"></div>
            <div class="field"><label>Output volume</label><input data-output-volume type="range" min="0" max="100" value="100"></div>
            <label class="softphone-check"><input data-notifications-toggle type="checkbox" <?= (($preferences['notifications_enabled'] ?? 'yes') === 'yes') ? 'checked' : '' ?>> Browser notifications</label>
        </div>
    </article>

    <article class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Recent calls</span>
                <h3>Historial</h3>
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
            <span class="eyebrow">Endpoints</span>
            <h3>Preparacion WebRTC</h3>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Extension</th><th>Transport</th><th>WebRTC</th><th>Codecs</th><th></th></tr></thead>
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
                                    <button class="button secondary xs" type="submit">Enable WebRTC</button>
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
    <strong data-incoming-number>Incoming call</strong>
    <div>
        <button class="button primary xs" type="button" data-call-answer>Answer</button>
        <button class="button danger xs" type="button" data-call-hangup>Reject</button>
    </div>
</div>

<audio data-remote-audio autoplay></audio>
<audio data-ring-audio loop></audio>
<script src="https://cdn.jsdelivr.net/npm/sip.js@0.21.2/dist/sip.min.js"></script>
<script>
if (!window.SIP) {
    document.write('<script src="https://unpkg.com/sip.js@0.21.2/dist/sip.min.js"><\/script>');
}
</script>
<script src="/assets/js/softphone.js"></script>
