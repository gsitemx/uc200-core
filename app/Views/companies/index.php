<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Multiempresa</span>
        <h2>Empresas</h2>
        <p>Administra clientes, licencias y el administrador inicial de cada tenant desde el core.</p>
    </div>
    <a class="button primary" href="/companies/create">Nueva empresa</a>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Directorio</span>
            <h3><?= count($companies) ?> empresas registradas</h3>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Licencia</th>
                    <th>Estado</th>
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
                        <td><?= e($company['expires_at'] ? substr((string) $company['expires_at'], 0, 10) : 'Sin vencimiento') ?></td>
                        <td class="actions-cell">
                            <a class="button secondary" href="/companies/show?id=<?= e($company['uuid']) ?>">Ver</a>
                            <a class="button secondary" href="/companies/edit?id=<?= e($company['uuid']) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
