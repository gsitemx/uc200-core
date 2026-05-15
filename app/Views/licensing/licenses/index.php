<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Licensing</span>
        <h2>Licencias</h2>
        <p>Asigna planes, fechas de expiracion y limites por empresa.</p>
    </div>
    <a class="button primary" href="/licensing/licenses/create">Nueva licencia</a>
</section>

<section class="module-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Plan</th>
                    <th>Llave</th>
                    <th>Estado</th>
                    <th>Vigencia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><strong><?= e($license['company_name']) ?></strong></td>
                        <td><?= e($license['plan_name']) ?></td>
                        <td><?= e($license['license_key']) ?></td>
                        <td><span class="badge <?= $license['status'] === 'active' ? 'on' : '' ?>"><?= e($license['status']) ?></span></td>
                        <td><?= e(substr((string) $license['starts_at'], 0, 10)) ?> - <?= e($license['expires_at'] ? substr((string) $license['expires_at'], 0, 10) : 'Sin vencimiento') ?></td>
                        <td class="actions-cell">
                            <a class="button secondary" href="/licensing/licenses/edit?id=<?= e($license['uuid']) ?>">Editar</a>
                            <form method="post" action="/licensing/licenses/delete" onsubmit="return confirm('Eliminar licencia?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($license['uuid']) ?>">
                                <button class="button danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
