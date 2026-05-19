<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Usage charts</span>
            <h3>Top metrics</h3>
        </div>
    </div>
    <div class="module-list">
        <?php $maxUsage = max(array_map(static fn ($row) => (float) $row['total'], $usageMetrics ?: [['total' => 0]])); ?>
        <?php foreach ($usageMetrics as $metric): ?>
            <?php $width = $maxUsage > 0 ? max(4, ((float) $metric['total'] / $maxUsage) * 100) : 0; ?>
            <div class="module-row">
                <div>
                    <strong><?= e($metric['metric']) ?></strong>
                    <p><?= e((string) $metric['total']) ?> units</p>
                </div>
                <span class="badge on" style="display: inline-block; width: <?= e((string) $width) ?>%; max-width: 220px;">&nbsp;</span>
            </div>
        <?php endforeach; ?>
        <?php if ($usageMetrics === []): ?>
            <p class="empty-copy">Sin datos agregados.</p>
        <?php endif; ?>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Usage tracking</span>
            <h3><?= count($usage) ?> mediciones</h3>
        </div>
        <div class="table-search"><input data-table-search="#usage-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="usage-table">
            <thead><tr><th>Tenant</th><th>Reseller</th><th>Metric</th><th>Quantity</th><th>Unit</th><th>Periodo</th><th>Source</th></tr></thead>
            <tbody>
                <?php foreach ($usage as $record): ?>
                    <tr>
                        <td><strong><?= e($record['company_name']) ?></strong><span><?= e($record['uuid']) ?></span></td>
                        <td><?= e($record['reseller_name'] ?? '-') ?></td>
                        <td><?= e($record['metric']) ?></td>
                        <td><?= e((string) $record['quantity']) ?></td>
                        <td><?= e($record['unit'] ?? 'unit') ?></td>
                        <td><?= e((string) $record['period_start']) ?> / <?= e((string) $record['period_end']) ?></td>
                        <td><?= e($record['source'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($usage === []): ?>
                    <tr><td colspan="7">Sin mediciones de consumo.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
