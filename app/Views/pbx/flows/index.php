<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PBX Flow</span>
        <h2><?= e($config['title']) ?></h2>
        <p>Flujo multi-tenant preparado para dialplan dinamico, failover y ownership por empresa.</p>
    </div>
    <a class="button primary sm" href="<?= e($config['base']) ?>/create">Nuevo</a>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Directorio</span>
            <h3><?= count($rows) ?> registros</h3>
        </div>
        <div class="table-search">
            <input data-table-search="#flow-table" aria-label="<?= e(__('actions.search')) ?>" placeholder="<?= e(__('actions.search')) ?>">
        </div>
    </div>

    <div class="table-wrap">
        <table id="flow-table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <?php foreach ($config['summary'] as $column): ?>
                        <th><?= e($config['fields'][$column]['label'] ?? $column) ?></th>
                    <?php endforeach; ?>
                    <th>Empresa</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><strong><?= e($row['name']) ?></strong><span><?= e($row['uuid']) ?></span></td>
                        <?php foreach ($config['summary'] as $column): ?>
                            <td><?= e((string) ($row[$column] ?? '')) ?></td>
                        <?php endforeach; ?>
                        <td><?= e($row['company_name']) ?></td>
                        <td><span class="badge <?= $row['status'] === 'active' ? 'on' : '' ?>"><?= e($row['status']) ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary xs" href="<?= e($config['base']) ?>/edit?id=<?= e($row['uuid']) ?>"><?= e(__('actions.edit')) ?></a>
                            <form method="post" action="<?= e($config['base']) ?>/delete" onsubmit="return confirm('Eliminar registro?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($row['uuid']) ?>">
                                <button class="button danger xs" type="submit"><?= e(__('actions.delete')) ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?>
                    <tr><td colspan="<?= e((string) (count($config['summary']) + 4)) ?>">Sin registros.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span><?= count($rows) ?> registros</span>
        <nav class="pagination" aria-label="Pagination"><span class="active">1</span></nav>
    </div>
</section>
