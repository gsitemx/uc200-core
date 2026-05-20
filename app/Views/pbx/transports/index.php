<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Modo avanzado</span>
        <h2>Metodos de conexion SIP</h2>
        <p>Configuracion tecnica del motor de comunicaciones. Solo para superadmin/engineer.</p>
    </div>
    <a class="button primary sm" href="/pbx/transports/create"><?= e(__('actions.new_transport')) ?></a>
</section>

<section class="module-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Empresa</th>
                    <th>Protocolo</th>
                    <th>Bind</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transports as $transport): ?>
                    <tr>
                        <td><strong><?= e($transport['id']) ?></strong></td>
                        <td><?= e($transport['company_name'] ?? 'Global') ?></td>
                        <td><?= e($transport['protocol']) ?></td>
                        <td><?= e($transport['bind']) ?></td>
                        <td><span class="badge <?= $transport['status'] === 'active' ? 'on' : '' ?>"><?= e($transport['status']) ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary xs" href="/pbx/transports/edit?id=<?= e($transport['uuid']) ?>"><?= e(__('actions.edit')) ?></a>
                            <form method="post" action="/pbx/transports/delete" onsubmit="return confirm('Eliminar metodo de conexion?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($transport['uuid']) ?>">
                                <button class="button danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
