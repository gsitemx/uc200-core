<?php $data = array_merge($queue, $old ?? []); ?>
<?php $strategies = ['ringall', 'leastrecent', 'fewestcalls', 'random', 'rrmemory', 'linear']; ?>
<?php $destinations = ['extension', 'ringgroup', 'ivr', 'voicemail', 'queue', 'hangup']; ?>

<?php if (! empty($errors)): ?>
    <div class="alert danger"><?= e(implode(' ', array_values($errors))) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Call Center</span>
        <h2><?= e($title) ?></h2>
        <p>Define estrategia, tiempos, anuncios, overflow y failover para ruteo empresarial.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/call-center">Volver</a>
    </div>
</section>

<form class="module-panel" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($queue['uuid']) ?>">
    <?php endif; ?>

    <div class="form-grid">
        <div class="field">
            <label>Tenant</label>
            <select name="company_id" <?= $mode === 'edit' ? 'disabled' : '' ?>>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e((string) $company['id']) ?>" <?= (int) ($data['company_id'] ?? 0) === (int) $company['id'] ? 'selected' : '' ?>>
                        <?= e($company['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Name</label><input name="name" required value="<?= e($data['name'] ?? '') ?>"></div>
        <div class="field"><label>Extension</label><input name="extension" required inputmode="numeric" value="<?= e($data['extension'] ?? '') ?>"></div>
        <div class="field">
            <label>Strategy</label>
            <select name="strategy">
                <?php foreach ($strategies as $strategy): ?>
                    <option value="<?= e($strategy) ?>" <?= ($data['strategy'] ?? 'ringall') === $strategy ? 'selected' : '' ?>><?= e($strategy) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Timeout seconds</label><input name="timeout_seconds" type="number" min="5" max="300" value="<?= e((string) ($data['timeout_seconds'] ?? 20)) ?>"></div>
        <div class="field"><label>Retry seconds</label><input name="retry_seconds" type="number" min="1" max="120" value="<?= e((string) ($data['retry_seconds'] ?? 5)) ?>"></div>
        <div class="field"><label>Wrapup seconds</label><input name="wrapup_seconds" type="number" min="0" max="600" value="<?= e((string) ($data['wrapup_seconds'] ?? 0)) ?>"></div>
        <div class="field"><label>Max callers</label><input name="max_callers" type="number" min="0" max="1000" value="<?= e((string) ($data['max_callers'] ?? 0)) ?>"></div>
        <div class="field"><label>Music on hold</label><input name="music_on_hold" value="<?= e($data['music_on_hold'] ?? '') ?>" placeholder="default"></div>
        <div class="field"><label>Service level seconds</label><input name="service_level_seconds" type="number" min="5" max="3600" value="<?= e((string) ($data['service_level_seconds'] ?? 60)) ?>"></div>
        <div class="field">
            <label>Announce position</label>
            <select name="announce_position">
                <option value="yes" <?= ($data['announce_position'] ?? 'yes') === 'yes' ? 'selected' : '' ?>>yes</option>
                <option value="no" <?= ($data['announce_position'] ?? 'yes') === 'no' ? 'selected' : '' ?>>no</option>
            </select>
        </div>
        <div class="field">
            <label>Announce hold time</label>
            <select name="announce_hold_time">
                <option value="no" <?= ($data['announce_hold_time'] ?? 'no') === 'no' ? 'selected' : '' ?>>no</option>
                <option value="yes" <?= ($data['announce_hold_time'] ?? 'no') === 'yes' ? 'selected' : '' ?>>yes</option>
            </select>
        </div>
        <div class="field">
            <label>Overflow type</label>
            <select name="overflow_destination_type">
                <?php foreach ($destinations as $destination): ?>
                    <option value="<?= e($destination) ?>" <?= ($data['overflow_destination_type'] ?? 'hangup') === $destination ? 'selected' : '' ?>><?= e($destination) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Overflow target</label><input name="overflow_destination_id" value="<?= e($data['overflow_destination_id'] ?? '') ?>"></div>
        <div class="field">
            <label>Failover type</label>
            <select name="failover_destination_type">
                <?php foreach ($destinations as $destination): ?>
                    <option value="<?= e($destination) ?>" <?= ($data['failover_destination_type'] ?? 'hangup') === $destination ? 'selected' : '' ?>><?= e($destination) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Failover target</label><input name="failover_destination_id" value="<?= e($data['failover_destination_id'] ?? '') ?>"></div>
        <div class="field">
            <label>Recording</label>
            <select name="recording_enabled">
                <option value="no" <?= ($data['recording_enabled'] ?? 'no') === 'no' ? 'selected' : '' ?>>no</option>
                <option value="yes" <?= ($data['recording_enabled'] ?? 'no') === 'yes' ? 'selected' : '' ?>>yes</option>
            </select>
        </div>
        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="active" <?= ($data['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>active</option>
                <option value="inactive" <?= ($data['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>inactive</option>
            </select>
        </div>
    </div>

    <div class="form-actions">
        <button class="button primary sm" type="submit">Guardar</button>
        <a class="button secondary sm" href="/call-center">Cancelar</a>
    </div>
</form>
