<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Tenant management</span>
            <h3>Asignacion reseller</h3>
        </div>
    </div>
    <form class="form-grid" method="post" action="/billing/tenants/assign">
        <?= csrf_field() ?>
        <div class="field">
            <label>Tenant</label>
            <select name="company_id" required>
                <?php foreach ($tenants as $tenant): ?>
                    <option value="<?= e((string) $tenant['id']) ?>"><?= e($tenant['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Reseller</label>
            <select name="reseller_id" required>
                <?php foreach ($resellers as $reseller): ?>
                    <option value="<?= e((string) $reseller['id']) ?>"><?= e($reseller['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div><button class="button primary sm" type="submit">Asignar</button></div>
    </form>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Tenants</span>
            <h3><?= count($tenants) ?> empresas</h3>
        </div>
        <div class="table-search"><input data-table-search="#tenants-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="tenants-table">
            <thead><tr><th>Tenant</th><th>Reseller</th><th>Estado</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td><strong><?= e($tenant['name']) ?></strong><span><?= e($tenant['uuid'] ?? '') ?></span></td>
                        <td><?= e($tenant['reseller_name'] ?? '-') ?></td>
                        <td><span class="badge <?= $tenant['status'] === 'active' ? 'on' : '' ?>"><?= e($tenant['status']) ?></span></td>
                        <td class="actions-cell">
                            <form method="post" action="/billing/tenants/suspend">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $tenant['id']) ?>">
                                <button class="button danger xs" type="submit">Suspender</button>
                            </form>
                            <form method="post" action="/billing/tenants/reactivate">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e((string) $tenant['id']) ?>">
                                <button class="button secondary xs" type="submit">Reactivar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($tenants === []): ?>
                    <tr><td colspan="4">Sin tenants.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
