<?php
$provider = (string) ($user['external_provider'] ?? 'none');
$linkedLabel = match ($provider) {
    'microsoft' => 'Vinculado con Microsoft 365',
    'google' => 'Vinculado con Google Workspace',
    default => 'No vinculado',
};
?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Identidad</span>
        <h2><?= e($user['name'] ?? 'Usuario') ?></h2>
        <p><?= e($user['email'] ?? '') ?><?= ! empty($user['company_name']) ? ' - ' . e($user['company_name']) : '' ?></p>
    </div>
    <span class="badge <?= $provider !== 'none' ? 'on' : '' ?>"><?= e($linkedLabel) ?></span>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Integraciones</span>
            <h3>Microsoft 365 / Google Workspace</h3>
        </div>
    </div>
    <div class="credential-grid">
        <div class="credential-box">
            <span>Microsoft 365</span>
            <strong><?= e(! empty($user['microsoft_user_id']) ? 'Vinculado' : 'No vinculado') ?></strong>
            <button class="button secondary sm" type="button" disabled>Conectar Microsoft 365</button>
        </div>
        <div class="credential-box">
            <span>Google Workspace</span>
            <strong><?= e(! empty($user['google_user_id']) ? 'Vinculado' : 'No vinculado') ?></strong>
            <button class="button secondary sm" type="button" disabled>Conectar Google Workspace</button>
        </div>
        <div class="credential-box">
            <span>Sincronizacion</span>
            <strong><?= (int) ($user['sync_enabled'] ?? 0) === 1 ? 'Activa' : 'Inactiva' ?></strong>
            <small>OAuth real se implementara en una fase posterior.</small>
        </div>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Cuentas externas</span>
            <h3><?= count($externalAccounts) ?> vinculadas</h3>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Proveedor</th>
                <th>Email</th>
                <th>Estado</th>
                <th>Ultima sincronizacion</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($externalAccounts as $account): ?>
                <tr>
                    <td><?= e($account['provider']) ?></td>
                    <td><?= e($account['provider_email'] ?? '') ?></td>
                    <td><span class="badge <?= $account['status'] === 'linked' ? 'on' : '' ?>"><?= e($account['status']) ?></span></td>
                    <td><?= e((string) ($account['last_synced_at'] ?? 'Pendiente')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($externalAccounts === []): ?>
                <tr><td colspan="4">Sin cuentas externas vinculadas.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</section>
