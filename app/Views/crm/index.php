<?php if (! empty($flash)): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">CRM</span>
        <h2>Contactos empresariales</h2>
        <p>Directorio tenant-aware preparado para Microsoft 365, Google Workspace, WhatsApp y click-to-call.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/crm/accounts/create">Nueva cuenta</a>
        <a class="button primary sm" href="/crm/contacts/create">Nuevo contacto</a>
    </div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Contactos</span><strong><?= e((string) $stats['contacts']) ?></strong><p>Personas activas en CRM.</p></article>
    <article class="metric-card"><span>Cuentas</span><strong><?= e((string) $stats['accounts']) ?></strong><p>Empresas y clientes.</p></article>
    <article class="metric-card"><span>Integraciones</span><strong>Ready</strong><p>Microsoft, Google, WhatsApp y API.</p></article>
    <article class="metric-card"><span>Click-to-call</span><strong>AMI</strong><p>Gateway base listo para originate real.</p></article>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Contactos</span>
            <h3><?= count($contacts) ?> encontrados</h3>
        </div>
        <form class="table-search" method="get" action="/crm">
            <input name="q" value="<?= e($query) ?>" placeholder="Buscar contacto, email, telefono o tag">
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Organizacion</th>
                    <th>Email</th>
                    <th>Telefonos</th>
                    <th>Tags</th>
                    <th>Origen</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><a class="table-link" href="/crm/contacts/show?id=<?= e($contact['uuid']) ?>"><strong><?= e($contact['full_name']) ?></strong></a><span><?= e($contact['job_title'] ?? '') ?></span></td>
                        <td><?= e($contact['account_name'] ?? $contact['organization'] ?? '') ?></td>
                        <td><a href="mailto:<?= e($contact['email'] ?? '') ?>"><?= e($contact['email'] ?? '') ?></a></td>
                        <td><?= e(trim(($contact['mobile_phone'] ?? '') . ' ' . ($contact['office_phone'] ?? ''))) ?></td>
                        <td><?= e($contact['tags'] ?? '') ?></td>
                        <td><span class="badge"><?= e($contact['source']) ?></span></td>
                        <td class="actions-cell">
                            <form method="post" action="/crm/contacts/call">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($contact['uuid']) ?>">
                                <button class="button secondary xs" type="submit">Llamar</button>
                            </form>
                            <a class="button secondary xs" href="/crm/contacts/edit?id=<?= e($contact['uuid']) ?>">Editar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($contacts === []): ?><tr><td colspan="7">Sin contactos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Actividad PBX</span>
            <h3>Llamadas recientes</h3>
        </div>
        <a class="button secondary sm" href="/crm/activity">Ver actividad</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Inicio</th>
                    <th>Contacto</th>
                    <th>Direccion</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Duracion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (($activity ?? []) as $call): ?>
                    <tr>
                        <td><?= e((string) $call['start_time']) ?></td>
                        <td>
                            <?php if (! empty($call['contact_uuid'])): ?>
                                <a class="table-link" href="/crm/contacts/show?id=<?= e($call['contact_uuid']) ?>"><?= e($call['contact_name'] ?? '') ?></a>
                            <?php else: ?>
                                <span>Sin coincidencia</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge"><?= e($call['direction']) ?></span></td>
                        <td><?= e($call['src']) ?></td>
                        <td><?= e($call['dst']) ?></td>
                        <td><?= e((string) $call['billsec']) ?>s</td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($activity ?? []) === []): ?><tr><td colspan="6">Sin llamadas registradas aun.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Cuentas CRM</span>
            <h3><?= count($accounts) ?> cuentas</h3>
        </div>
        <a class="button secondary sm" href="/crm/accounts/create">Nueva cuenta</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Nombre comercial</th><th>Razon social</th><th>Email</th><th>Telefono</th><th>Origen</th><th>Acciones</th></tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><a class="table-link" href="/crm/accounts/show?id=<?= e($account['uuid']) ?>"><strong><?= e($account['trade_name']) ?></strong></a></td>
                        <td><?= e($account['legal_name'] ?? '') ?></td>
                        <td><?= e($account['primary_email'] ?? '') ?></td>
                        <td><?= e($account['primary_phone'] ?? '') ?></td>
                        <td><span class="badge"><?= e($account['external_provider']) ?></span></td>
                        <td><a class="button secondary xs" href="/crm/accounts/edit?id=<?= e($account['uuid']) ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($accounts === []): ?><tr><td colspan="6">Sin cuentas CRM.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
