<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Licensing Core</span>
        <h2>Licencias y capacidades</h2>
        <p>Administra planes, features, limites y licencias activas para cada empresa.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary" href="/licensing/plans">Planes</a>
        <a class="button secondary" href="/licensing/features">Features</a>
        <a class="button primary" href="/licensing/licenses/create">Nueva licencia</a>
    </div>
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
            <span class="eyebrow">Recientes</span>
            <h3>Licencias</h3>
        </div>
        <a class="button secondary" href="/licensing/licenses">Ver todas</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Plan</th>
                    <th>Llave</th>
                    <th>Estado</th>
                    <th>Expira</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($licenses as $license): ?>
                    <tr>
                        <td><strong><?= e($license['company_name']) ?></strong></td>
                        <td><?= e($license['plan_name']) ?></td>
                        <td><?= e($license['license_key']) ?></td>
                        <td><span class="badge <?= $license['status'] === 'active' ? 'on' : '' ?>"><?= e($license['status']) ?></span></td>
                        <td><?= e($license['expires_at'] ? substr((string) $license['expires_at'], 0, 10) : 'Sin vencimiento') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
