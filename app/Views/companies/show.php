<section class="hero-panel">
    <div>
        <span class="eyebrow">Empresa</span>
        <h2><?= e($company['name']) ?></h2>
        <p><?= e($company['legal_name'] ?? 'Sin razon social') ?></p>
    </div>
    <div class="module-meta">
        <span class="badge <?= $company['status'] === 'active' ? 'on' : '' ?>"><?= e($company['status']) ?></span>
        <a class="button secondary" href="/companies/dashboard?id=<?= e($company['uuid']) ?>">Dashboard</a>
        <a class="button secondary" href="/companies/edit?id=<?= e($company['uuid']) ?>">Editar</a>
    </div>
</section>

<?php require __DIR__ . '/partials/company_summary.php'; ?>

<section class="module-panel danger-zone">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Soft delete</span>
            <h3>Eliminar empresa</h3>
        </div>
        <form method="post" action="/companies/delete" onsubmit="return confirm('Eliminar esta empresa?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e($company['uuid']) ?>">
            <button class="button danger" type="submit">Eliminar</button>
        </form>
    </div>
</section>
