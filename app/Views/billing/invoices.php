<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Invoices</span>
            <h3><?= count($invoices) ?> facturas</h3>
        </div>
        <div class="table-search"><input data-table-search="#invoices-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="invoices-table">
            <thead><tr><th>Factura</th><th>Tenant</th><th>Reseller</th><th>Total</th><th>Balance</th><th>Estado</th><th>Vence</th></tr></thead>
            <tbody>
                <?php foreach ($invoices as $invoice): ?>
                    <tr>
                        <td><strong><?= e($invoice['invoice_number']) ?></strong><span><?= e($invoice['uuid']) ?></span></td>
                        <td><?= e($invoice['company_name']) ?></td>
                        <td><?= e($invoice['reseller_name'] ?? '-') ?></td>
                        <td><?= e($invoice['currency']) ?> <?= e((string) $invoice['total']) ?></td>
                        <td><?= e($invoice['currency']) ?> <?= e((string) $invoice['balance_due']) ?></td>
                        <td><span class="badge <?= $invoice['status'] === 'paid' ? 'on' : '' ?>"><?= e($invoice['status']) ?></span></td>
                        <td><?= e((string) ($invoice['due_date'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($invoices === []): ?>
                    <tr><td colspan="7">Sin facturas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
