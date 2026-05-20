<?php if (! empty($flash)): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">CRM + PBX</span>
        <h2>Actividad de llamadas</h2>
        <p>Vista consolidada para seguimiento comercial y operativo, lista para popup softphone futuro.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/crm">Dashboard CRM</a>
        <a class="button secondary sm" href="/crm/contacts">Contactos</a>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Actividad</span>
            <h3><?= count($calls) ?> eventos recientes</h3>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Inicio</th>
                    <th>Contacto</th>
                    <th>Cuenta</th>
                    <th>Direccion</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Duracion</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($calls as $call): ?>
                    <tr>
                        <td><?= e((string) $call['start_time']) ?></td>
                        <td>
                            <?php if (! empty($call['contact_uuid'])): ?>
                                <a class="table-link" href="/crm/contacts/show?id=<?= e($call['contact_uuid']) ?>"><?= e($call['contact_name'] ?? '') ?></a>
                            <?php else: ?>
                                <span>Sin coincidencia</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($call['account_name'] ?? '') ?></td>
                        <td><span class="badge"><?= e($call['direction']) ?></span></td>
                        <td><?= e($call['src']) ?></td>
                        <td><?= e($call['dst']) ?></td>
                        <td><?= e((string) $call['billsec']) ?>s</td>
                        <td><?= e($call['disposition']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($calls === []): ?><tr><td colspan="8">Sin llamadas registradas aun.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
