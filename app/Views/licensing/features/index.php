<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Licensing</span>
        <h2>Features</h2>
        <p>Catalogo de capacidades que pueden activarse por plan y validarse por middleware.</p>
    </div>
    <a class="button primary" href="/licensing/features/create">Nueva feature</a>
</section>

<section class="module-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Modulo</th>
                    <th>Grupo</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($features as $feature): ?>
                    <tr>
                        <td><strong><?= e($feature['name']) ?></strong><span><?= e($feature['slug']) ?></span></td>
                        <td><?= e($feature['module']) ?></td>
                        <td><?= e($feature['group_name'] ?? 'Sin grupo') ?></td>
                        <td><span class="badge <?= (int) $feature['is_active'] === 1 ? 'on' : '' ?>"><?= (int) $feature['is_active'] === 1 ? 'Activa' : 'Inactiva' ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary" href="/licensing/features/edit?id=<?= e($feature['uuid']) ?>">Editar</a>
                            <form method="post" action="/licensing/features/delete" onsubmit="return confirm('Eliminar feature?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($feature['uuid']) ?>">
                                <button class="button danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
