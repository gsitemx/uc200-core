<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Bienvenido</span>
        <h2><?= e($user['name'] ?? 'SUPERADMIN') ?></h2>
        <p>Este dashboard queda como punto inicial para operar el core, activar modulos y conectar futuras funciones del MVP.</p>
    </div>
    <div class="status-pill"><?= e(has_role('super-admin') ? 'SUPERADMIN' : ($user['company_name'] ?? 'EMPRESA')) ?></div>
</section>

<section class="card-grid">
    <?php foreach ($cards as $card): ?>
        <article class="metric-card">
            <span><?= e($card['label']) ?></span>
            <strong><?= e((string) $card['value']) ?></strong>
            <p><?= e($card['hint']) ?></p>
        </article>
    <?php endforeach; ?>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Modulos</span>
            <h3>Estructura preparada</h3>
        </div>
        <span class="muted"><?= count($modules) ?> registrados</span>
    </div>

    <div class="module-list">
        <?php foreach ($modules as $module): ?>
            <article class="module-row">
                <div>
                    <strong><?= e($module['name']) ?></strong>
                    <p><?= e($module['description'] ?? 'Sin descripcion') ?></p>
                </div>
                <div class="module-meta">
                    <span>v<?= e($module['version']) ?></span>
                    <span class="badge <?= (int) $module['is_enabled'] === 1 ? 'on' : '' ?>">
                        <?= (int) $module['is_enabled'] === 1 ? 'Activo' : 'Inactivo' ?>
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
