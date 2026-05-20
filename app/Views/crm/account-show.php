<?php if (! empty($flash)): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Cuenta CRM</span>
        <h2><?= e($account['trade_name']) ?></h2>
        <p><?= e($account['legal_name'] ?? '') ?></p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/crm/accounts/edit?id=<?= e($account['uuid']) ?>">Editar</a>
        <a class="button secondary sm" href="/crm/contacts/create">Nuevo contacto</a>
    </div>
</section>

<section class="credential-grid">
    <div class="credential-box"><span>Email</span><strong><?= e($account['primary_email'] ?? '') ?></strong></div>
    <div class="credential-box"><span>Telefono</span><strong><?= e($account['primary_phone'] ?? '') ?></strong></div>
    <div class="credential-box"><span>Website</span><strong><?= e($account['website'] ?? '') ?></strong></div>
    <div class="credential-box"><span>RFC / Tax ID</span><strong><?= e($account['tax_id'] ?? '') ?></strong></div>
</section>

<section class="module-panel">
    <div class="section-heading"><div><span class="eyebrow">Contactos</span><h3><?= count($contacts) ?> relacionados</h3></div></div>
    <table>
        <thead><tr><th>Nombre</th><th>Email</th><th>Movil</th><th>Estado</th><th>Puesto</th><th>Acciones</th></tr></thead>
        <tbody>
            <?php foreach ($contacts as $contact): ?>
                <tr><td><a class="table-link" href="/crm/contacts/show?id=<?= e($contact['uuid']) ?>"><?= e($contact['full_name']) ?></a></td><td><?= e($contact['email'] ?? '') ?></td><td><?= e($contact['mobile_phone'] ?? '') ?></td><td><span class="badge"><?= e($contact['linked_status'] ?? 'Offline') ?></span></td><td><?= e($contact['job_title'] ?? '') ?></td><td><a class="button secondary xs" href="/crm/contacts/edit?id=<?= e($contact['uuid']) ?>">Editar</a></td></tr>
            <?php endforeach; ?>
            <?php if ($contacts === []): ?><tr><td colspan="6">Sin contactos relacionados.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<section class="module-panel">
    <div class="section-heading"><div><span class="eyebrow">Actividad</span><h3>Notas</h3></div></div>
    <form class="form-stack" method="post" action="/crm/activity/note">
        <?= csrf_field() ?>
        <input type="hidden" name="account_id" value="<?= e($account['uuid']) ?>">
        <div class="form-grid">
            <label class="field">Asunto<input name="subject" value="Nota"></label>
            <label class="field">Nota<input name="body"></label>
        </div>
        <button class="button secondary sm" type="submit">Agregar nota</button>
    </form>
    <table>
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Asunto</th><th>Detalle</th></tr></thead>
        <tbody>
            <?php foreach ($activities as $activity): ?>
                <tr><td><?= e((string) $activity['occurred_at']) ?></td><td><?= e($activity['activity_type']) ?></td><td><?= e($activity['subject']) ?></td><td><?= e($activity['body'] ?? '') ?></td></tr>
            <?php endforeach; ?>
            <?php if ($activities === []): ?><tr><td colspan="4">Sin actividad registrada.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
