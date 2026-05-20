<?php if (! empty($flash)): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">CRM</span>
        <h2>Contactos empresariales</h2>
        <p>Directorio unificado con estado PBX, ultima interaccion y acciones de llamada.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/crm/activity">Actividad</a>
        <a class="button primary sm" href="/crm/contacts/create">Nuevo contacto</a>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Contactos</span>
            <h3><?= count($contacts) ?> encontrados</h3>
        </div>
        <form class="table-search" method="get" action="/crm/contacts">
            <input name="q" value="<?= e($query) ?>" placeholder="Buscar contacto, email, telefono, DID o tag">
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Cuenta</th>
                    <th>Telefonia</th>
                    <th>Estado</th>
                    <th>Ultima interaccion</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td>
                            <a class="table-link" href="/crm/contacts/show?id=<?= e($contact['uuid']) ?>">
                                <strong><?= e($contact['full_name']) ?></strong>
                            </a>
                            <span><?= e($contact['job_title'] ?? '') ?></span>
                        </td>
                        <td><?= e($contact['account_name'] ?? $contact['organization'] ?? '') ?></td>
                        <td>
                            <strong><?= e($contact['mobile_phone'] ?? $contact['office_phone'] ?? $contact['related_did'] ?? '') ?></strong>
                            <span><?= e(($contact['extension_number'] ?? '') !== '' ? 'Ext. ' . $contact['extension_number'] : (string) ($contact['related_did'] ?? '')) ?></span>
                        </td>
                        <td><span class="badge"><?= e($contact['linked_status'] ?? 'Offline') ?></span></td>
                        <td><?= e((string) ($contact['last_interaction_at'] ?? 'Sin actividad')) ?></td>
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
                <?php if ($contacts === []): ?><tr><td colspan="6">Sin contactos.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
