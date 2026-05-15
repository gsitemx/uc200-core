<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Licensing</span>
        <h2>Planes</h2>
        <p>Define paquetes comerciales y activa features por plan.</p>
    </div>
    <a class="button primary" href="/licensing/plans/create">Nuevo plan</a>
</section>

<section class="module-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Plan</th>
                    <th>Precio</th>
                    <th>Periodo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($plans as $plan): ?>
                    <tr>
                        <td><strong><?= e($plan['name']) ?></strong><span><?= e($plan['slug']) ?></span></td>
                        <td>$<?= e(number_format((float) $plan['price'], 2)) ?></td>
                        <td><?= e($plan['billing_period']) ?></td>
                        <td><span class="badge <?= (int) $plan['is_active'] === 1 ? 'on' : '' ?>"><?= (int) $plan['is_active'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary" href="/licensing/plans/edit?id=<?= e($plan['uuid']) ?>">Editar</a>
                            <form method="post" action="/licensing/plans/delete" onsubmit="return confirm('Eliminar plan?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($plan['uuid']) ?>">
                                <button class="button danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
