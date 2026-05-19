<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Multiempresa</span>
        <h2><?= e(__('companies.title')) ?></h2>
        <p><?= e(__('companies.copy')) ?></p>
    </div>
    <a class="button primary sm" href="/companies/create"><?= e(__('companies.new')) ?></a>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?= e(__('companies.directory')) ?></span>
            <h3><?= e(__('companies.registered', ['count' => count($companies)])) ?></h3>
        </div>
        <div class="table-search">
            <input data-table-search="#companies-table" aria-label="<?= e(__('actions.search')) ?>" placeholder="<?= e(__('actions.search')) ?>">
        </div>
    </div>

    <div class="table-wrap">
        <table id="companies-table">
            <thead>
                <tr>
                    <th><?= e(__('fields.company')) ?></th>
                    <th>Licencia</th>
                    <th><?= e(__('fields.status')) ?></th>
                    <th><?= e(__('fields.language')) ?></th>
                    <th>Expira</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $company): ?>
                    <tr>
                        <td>
                            <strong><?= e($company['name']) ?></strong>
                            <span><?= e($company['tax_id'] ?? 'Sin RFC/Tax ID') ?></span>
                        </td>
                        <td><?= e($company['plan_name'] ?? 'Sin plan') ?></td>
                        <td><span class="badge <?= $company['status'] === 'active' ? 'on' : '' ?>"><?= e($company['status']) ?></span></td>
                        <td><?= e(strtoupper($company['locale'] ?? 'es')) ?></td>
                        <td><?= e($company['expires_at'] ? substr((string) $company['expires_at'], 0, 10) : 'Sin vencimiento') ?></td>
                        <td class="actions-cell">
                            <a class="button secondary xs" href="/companies/show?id=<?= e($company['uuid']) ?>">Ver</a>
                            <a class="button secondary xs" href="/companies/edit?id=<?= e($company['uuid']) ?>"><?= e(__('actions.edit')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span><?= e(__('companies.registered', ['count' => count($companies)])) ?></span>
        <nav class="pagination" aria-label="Pagination"><span class="active">1</span></nav>
    </div>
</section>
