<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PJSIP</span>
        <h2>Extensiones SIP</h2>
        <p>Cada extension mantiene endpoint, auth y AOR listos para Asterisk Realtime.</p>
    </div>
    <a class="button primary" href="/pbx/extensions/create">Nueva extension</a>
</section>

<section class="module-panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Extension</th>
                    <th>Empresa</th>
                    <th>Transporte</th>
                    <th>Codecs</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extensions as $extension): ?>
                    <tr>
                        <td><strong><?= e($extension['username']) ?></strong><span><?= e($extension['id']) ?></span></td>
                        <td><?= e($extension['company_name']) ?></td>
                        <td><?= e($extension['transport'] ?? 'default') ?></td>
                        <td><?= e($extension['allow']) ?></td>
                        <td><span class="badge <?= $extension['status'] === 'active' ? 'on' : '' ?>"><?= e($extension['status']) ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary" href="/pbx/extensions/edit?id=<?= e($extension['uuid']) ?>">Editar</a>
                            <form method="post" action="/pbx/extensions/delete" onsubmit="return confirm('Eliminar extension?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($extension['uuid']) ?>">
                                <button class="button danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
