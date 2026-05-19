<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<?php if (! empty($plainToken)): ?>
    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Nuevo token</span>
                <h3>Token visible una sola vez</h3>
            </div>
        </div>
        <input class="token-display" readonly value="<?= e($plainToken) ?>">
    </section>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">API v1</span>
        <h2>Personal Access Tokens</h2>
        <p>Administra tokens bearer con expiracion, scopes e IPs permitidas para integraciones enterprise.</p>
    </div>
    <a class="button secondary sm" href="/docs/openapi.yaml">OpenAPI</a>
</section>

<form class="module-panel form-stack" method="post" action="/settings/api-tokens/store">
    <?= csrf_field() ?>
    <div class="section-heading">
        <div>
            <span class="eyebrow">Crear</span>
            <h3>Nuevo token</h3>
        </div>
        <button class="button primary sm" type="submit">Crear token</button>
    </div>
    <div class="form-grid">
        <label class="field">Nombre
            <input name="name" value="Integracion API">
        </label>
        <label class="field">Expira en
            <input type="datetime-local" name="expires_at">
        </label>
        <label class="field">IPs permitidas
            <input name="allowed_ips" placeholder="203.0.113.10, 198.51.100.5">
        </label>
        <label class="field">Scopes
            <select name="scopes[]" multiple size="6">
                <?php foreach ($scopes as $scope => $label): ?>
                    <option value="<?= e($scope) ?>" <?= $scope === '*' ? 'selected' : '' ?>><?= e($scope . ' - ' . $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
</form>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Tokens</span>
            <h3><?= count($tokens) ?> activos</h3>
        </div>
        <div class="table-search">
            <input data-table-search="#api-token-table" aria-label="Buscar" placeholder="Buscar">
        </div>
    </div>
    <div class="table-wrap">
        <table id="api-token-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Empresa</th>
                    <th>Scopes</th>
                    <th>Expira</th>
                    <th>Ultimo uso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $token): ?>
                    <tr>
                        <td><strong><?= e($token['name']) ?></strong><span><?= e($token['token_prefix'] . '...' . $token['last_four']) ?></span></td>
                        <td><?= e($token['user_email']) ?></td>
                        <td><?= e($token['company_name'] ?? 'Global') ?></td>
                        <td><?= e(implode(', ', json_decode((string) $token['scopes'], true) ?: [])) ?></td>
                        <td><?= e($token['expires_at'] ?? 'Sin expiracion') ?></td>
                        <td><?= e($token['last_used_at'] ?? 'Nunca') ?></td>
                        <td class="actions-cell">
                            <form method="post" action="/settings/api-tokens/revoke" onsubmit="return confirm('Revocar token?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($token['uuid']) ?>">
                                <button class="button danger xs" type="submit">Revocar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tokens === []): ?>
                    <tr><td colspan="7">Sin tokens.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
