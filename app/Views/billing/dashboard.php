<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Billing Engine</span>
        <h2>Reseller platform</h2>
        <p>Suscripciones, consumo, facturas, licenciamiento y automatizacion por reseller y tenant.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/billing/tenants">Tenants</a>
        <a class="button secondary sm" href="/billing/invoices">Invoices</a>
        <a class="button primary sm" href="/billing/subscriptions">Subscriptions</a>
    </div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Resellers</span><strong><?= e((string) ($metrics['resellers'] ?? 0)) ?></strong><p>Canales activos o configurados.</p></article>
    <article class="metric-card"><span>Subscriptions</span><strong><?= e((string) ($metrics['subscriptions'] ?? 0)) ?></strong><p>Planes y renovaciones.</p></article>
    <article class="metric-card"><span>Open invoices</span><strong><?= e((string) ($metrics['open_invoices'] ?? 0)) ?></strong><p>Cobranza pendiente.</p></article>
    <article class="metric-card"><span>MRR</span><strong><?= e((string) ($metrics['mrr'] ?? '0.00')) ?></strong><p>Ingreso mensual recurrente.</p></article>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Subscriptions</span>
            <h3>Ultimas 50</h3>
        </div>
        <a class="button secondary xs" href="/billing/subscriptions">Ver todo</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tenant</th><th>Reseller</th><th>Plan</th><th>Periodo</th><th>Estado</th><th>Renovacion</th></tr></thead>
            <tbody>
                <?php foreach ($subscriptions as $subscription): ?>
                    <tr>
                        <td><strong><?= e($subscription['company_name']) ?></strong><span><?= e($subscription['currency']) ?> <?= e((string) $subscription['amount']) ?></span></td>
                        <td><?= e($subscription['reseller_name'] ?? '-') ?></td>
                        <td><?= e($subscription['plan_name'] ?? '-') ?></td>
                        <td><?= e($subscription['billing_period']) ?></td>
                        <td><span class="badge <?= $subscription['status'] === 'active' ? 'on' : '' ?>"><?= e($subscription['status']) ?></span></td>
                        <td><?= e((string) $subscription['current_period_end']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($subscriptions === []): ?>
                    <tr><td colspan="6">Sin suscripciones.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Invoices</span>
            <h3>Facturas recientes</h3>
        </div>
        <a class="button secondary xs" href="/billing/invoices">Ver facturas</a>
    </div>
    <div class="module-list">
        <?php foreach ($invoices as $invoice): ?>
            <a class="module-row" href="/billing/invoices">
                <div>
                    <strong><?= e($invoice['invoice_number']) ?></strong>
                    <p><?= e($invoice['company_name']) ?> / <?= e($invoice['currency']) ?> <?= e((string) $invoice['total']) ?></p>
                </div>
                <span class="badge <?= $invoice['status'] === 'paid' ? 'on' : '' ?>"><?= e($invoice['status']) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($invoices === []): ?>
            <p class="empty-copy">Sin facturas.</p>
        <?php endif; ?>
    </div>
</section>
