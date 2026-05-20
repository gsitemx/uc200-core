<?php if (! empty($flash)): ?><div class="alert success"><?= e($flash) ?></div><?php endif; ?>
<?php if (! empty($error)): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Contacto</span>
        <h2><?= e($contact['full_name']) ?></h2>
        <p><?= e(trim(($contact['job_title'] ?? '') . ' - ' . ($contact['organization'] ?? $contact['account_name'] ?? ''), ' -')) ?></p>
    </div>
    <div class="module-meta">
        <form method="post" action="/crm/contacts/call">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($contact['uuid']) ?>">
            <button class="button primary sm" type="submit">Llamar</button>
        </form>
        <a class="button secondary sm" href="mailto:<?= e($contact['email'] ?? '') ?>">Email</a>
        <button class="button secondary sm" type="button" disabled>WhatsApp futuro</button>
        <a class="button secondary sm" href="/crm/contacts/edit?id=<?= e($contact['uuid']) ?>">Editar</a>
    </div>
</section>

<section class="credential-grid">
    <div class="credential-box"><span>Email</span><strong><?= e($contact['email'] ?? '') ?></strong></div>
    <div class="credential-box"><span>Movil</span><strong><?= e($contact['mobile_phone'] ?? '') ?></strong></div>
    <div class="credential-box"><span>Oficina</span><strong><?= e($contact['office_phone'] ?? '') ?></strong></div>
    <div class="credential-box"><span>DID</span><strong><?= e($contact['related_did'] ?? '') ?></strong></div>
    <div class="credential-box"><span>Extension</span><strong><?= e(($contact['extension_number'] ?? '') !== '' ? $contact['extension_number'] : 'Sin vincular') ?></strong></div>
    <div class="credential-box"><span>Estado PBX</span><strong><?= e($contact['linked_status'] ?? 'Offline') ?></strong></div>
    <div class="credential-box"><span>Ultima interaccion</span><strong><?= e((string) ($contact['last_interaction_at'] ?? 'Sin actividad')) ?></strong></div>
    <div class="credential-box"><span>Tags</span><strong><?= e($contact['tags'] ?? '') ?></strong></div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Total llamadas</span><strong><?= e((string) ($callSummary['total_calls'] ?? 0)) ?></strong><p>Historial asociado al contacto.</p></article>
    <article class="metric-card"><span>Entrantes</span><strong><?= e((string) ($callSummary['inbound_calls'] ?? 0)) ?></strong><p>Llamadas recibidas.</p></article>
    <article class="metric-card"><span>Salientes</span><strong><?= e((string) ($callSummary['outbound_calls'] ?? 0)) ?></strong><p>Llamadas originadas.</p></article>
    <article class="metric-card"><span>Perdidas</span><strong><?= e((string) ($callSummary['missed_calls'] ?? 0)) ?></strong><p>No answer, busy o failed.</p></article>
</section>

<section class="module-panel">
    <div class="section-heading"><div><span class="eyebrow">Actividad</span><h3>Notas e historial</h3></div></div>
    <form class="form-stack" method="post" action="/crm/activity/note">
        <?= csrf_field() ?>
        <input type="hidden" name="contact_id" value="<?= e($contact['uuid']) ?>">
        <div class="form-grid">
            <label class="field">Asunto<input name="subject" value="Nota"></label>
            <label class="field">Nota<input name="body" placeholder="Seguimiento, requerimiento, compromiso..."></label>
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

<section class="module-panel">
    <div class="section-heading"><div><span class="eyebrow">Llamadas CRM</span><h3>Historial integrado</h3></div></div>
    <table>
        <thead><tr><th>Inicio</th><th>Direccion</th><th>Origen</th><th>Destino</th><th>Duracion</th><th>Resultado</th></tr></thead>
        <tbody>
            <?php foreach (($callLogs ?? []) as $call): ?>
                <tr>
                    <td><?= e((string) $call['start_time']) ?></td>
                    <td><?= e($call['direction']) ?></td>
                    <td><?= e($call['src']) ?></td>
                    <td><?= e($call['dst']) ?></td>
                    <td><?= e((string) $call['billsec']) ?>s</td>
                    <td><?= e($call['disposition']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($callLogs ?? []) === []): ?><tr><td colspan="6">Sin historial de llamadas aun.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>

<section class="module-panel">
    <div class="section-heading"><div><span class="eyebrow">Llamadas</span><h3>Grabaciones relacionadas</h3></div></div>
    <table>
        <thead><tr><th>Inicio</th><th>Direccion</th><th>Caller</th><th>Callee</th><th>Duracion</th></tr></thead>
        <tbody>
            <?php foreach ($recordings as $recording): ?>
                <tr><td><?= e((string) $recording['started_at']) ?></td><td><?= e($recording['direction']) ?></td><td><?= e($recording['caller']) ?></td><td><?= e($recording['callee']) ?></td><td><?= e((string) $recording['duration_seconds']) ?>s</td></tr>
            <?php endforeach; ?>
            <?php if ($recordings === []): ?><tr><td colspan="5">Sin llamadas relacionadas.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
