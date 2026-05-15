<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PJSIP</span>
        <h2>SIP Transports</h2>
        <p>Transports preparados para Realtime. WebRTC queda fuera de este alcance.</p>
    </div>
    <a class="button primary" href="/pbx/transports/create">Nuevo transport</a>
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
                            <a class="button secondary" href="/pbx/transports/edit?id=<?= e($transport['uuid']) ?>">Editar</a>
                            <form method="post" action="/pbx/transports/delete" onsubmit="return confirm('Eliminar transport?');">
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
