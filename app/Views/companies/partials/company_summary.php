<section class="card-grid">
    <article class="metric-card">
        <span>Usuarios</span>
        <strong><?= e((string) ($stats['users'] ?? 0)) ?></strong>
        <p>Cuentas dentro de este tenant.</p>
    </article>
    <article class="metric-card">
        <span>Licencia</span>
        <strong><?= e($company['license_status'] ?? 'sin plan') ?></strong>
        <p><?= e($company['license_key'] ?? 'No hay licencia asignada') ?></p>
    </article>
    <article class="metric-card">
        <span>Features</span>
        <strong><?= e((string) ($stats['features'] ?? 0)) ?></strong>
        <p>Capacidades habilitadas por empresa.</p>
    </article>
    <article class="metric-card">
        <span>Auditoria</span>
        <strong><?= e((string) ($stats['audit_logs'] ?? 0)) ?></strong>
        <p>Eventos registrados para la empresa.</p>
    </article>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Settings</span>
            <h3>Configuracion</h3>
        </div>
    </div>
    <div class="module-list">
        <?php foreach ($settings as $key => $value): ?>
            <article class="module-row">
                <strong><?= e($key) ?></strong>
                <span class="muted"><?= e((string) $value) ?></span>
            </article>
        <?php endforeach; ?>
        <?php if ($settings === []): ?>
            <div class="empty-state"><p>Sin settings registrados para esta empresa.</p></div>
        <?php endif; ?>
    </div>
</section>
