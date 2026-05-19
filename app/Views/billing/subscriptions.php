<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Subscriptions</span>
            <h3>Nueva suscripcion</h3>
        </div>
    </div>
    <form class="form-grid" method="post" action="/billing/subscriptions/store">
        <?= csrf_field() ?>
        <div class="field">
            <label>Tenant</label>
            <select name="company_id" required><?php foreach ($companies as $company): ?><option value="<?= e((string) $company['id']) ?>"><?= e($company['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
            <label>Plan</label>
            <select name="plan_id" required><?php foreach ($plans as $plan): ?><option value="<?= e((string) $plan['id']) ?>"><?= e($plan['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field">
            <label>Reseller</label>
            <select name="reseller_id"><option value="">Ninguno</option><?php foreach ($resellers as $reseller): ?><option value="<?= e((string) $reseller['id']) ?>"><?= e($reseller['name']) ?></option><?php endforeach; ?></select>
        </div>
        <div class="field"><label>Periodo</label><select name="billing_period"><option value="monthly">monthly</option><option value="yearly">yearly</option><option value="custom">custom</option></select></div>
        <div class="field"><label>Monto</label><input name="amount" type="number" min="0" step="0.01" value="0.00"></div>
        <div class="field"><label>Moneda</label><input name="currency" value="USD" maxlength="3"></div>
        <div class="field"><label>Inicio</label><input name="current_period_start" type="date" value="<?= e(date('Y-m-d')) ?>"></div>
        <div class="field"><label>Fin</label><input name="current_period_end" type="date" value="<?= e(date('Y-m-d', strtotime('+1 month'))) ?>"></div>
        <div class="field"><label>Grace until</label><input name="grace_until" type="date" value="<?= e(date('Y-m-d', strtotime('+7 days'))) ?>"></div>
        <div class="field"><label>Auto renew</label><select name="auto_renew"><option value="yes">yes</option><option value="no">no</option></select></div>
        <div class="field"><label>Estado</label><select name="status"><option value="active">active</option><option value="trialing">trialing</option><option value="past_due">past_due</option><option value="suspended">suspended</option><option value="cancelled">cancelled</option></select></div>
        <div><button class="button primary sm" type="submit">Crear suscripcion</button></div>
    </form>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Subscriptions</span>
            <h3><?= count($subscriptions) ?> registros</h3>
        </div>
        <div class="table-search"><input data-table-search="#subscriptions-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="subscriptions-table">
            <thead><tr><th>Tenant</th><th>Reseller</th><th>Plan</th><th>Monto</th><th>Periodo</th><th>Estado</th><th>Fin</th></tr></thead>
            <tbody>
                <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td><strong><?= e($subscription['company_name']) ?></strong><span><?= e($subscription['uuid']) ?></span></td>
                        <td><?= e($subscription['reseller_name'] ?? '-') ?></td>
                        <td><?= e($subscription['plan_name'] ?? '-') ?></td>
                        <td><?= e($subscription['currency']) ?> <?= e((string) $subscription['amount']) ?></td>
                        <td><?= e($subscription['billing_period']) ?></td>
                        <td><span class="badge <?= $subscription['status'] === 'active' ? 'on' : '' ?>"><?= e($subscription['status']) ?></span></td>
                        <td><?= e((string) $subscription['current_period_end']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($subscriptions === []): ?>
                    <tr><td colspan="7">Sin suscripciones.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
