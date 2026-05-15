<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PBX Core</span>
        <h2>Realtime PJSIP</h2>
        <p>Base multiempresa para extensiones SIP, transports y objetos AOR/Auth/Endpoint compatibles con Asterisk Realtime.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary" href="/pbx/transports">Transports</a>
        <a class="button primary" href="/pbx/extensions/create">Nueva extension</a>
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
            <span class="eyebrow">Extensiones</span>
            <h3>Estado SIP preparado</h3>
        </div>
        <a class="button secondary" href="/pbx/extensions">Ver todas</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Endpoint</th>
                    <th>Empresa</th>
                    <th>Contexto</th>
                    <th>SIP</th>
                    <th>Presencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extensions as $extension): ?>
                    <tr>
                        <td><strong><?= e($extension['username']) ?></strong><span><?= e($extension['id']) ?></span></td>
                        <td><?= e($extension['company_name']) ?></td>
                        <td><?= e($extension['context']) ?></td>
                        <td><span class="badge <?= $extension['sip_status'] === 'registered' ? 'on' : '' ?>"><?= e($extension['sip_status']) ?></span></td>
                        <td><?= e($extension['presence_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
