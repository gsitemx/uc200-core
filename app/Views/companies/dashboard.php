<section class="hero-panel">
    <div>
        <span class="eyebrow">Tenant</span>
        <h2><?= e($company['name']) ?></h2>
        <p>Resumen operativo de la empresa activa, su licencia, usuarios y configuracion inicial.</p>
    </div>
    <span class="status-pill"><?= e($company['status'] ?? 'active') ?></span>
</section>

<?php require __DIR__ . '/partials/company_summary.php'; ?>
