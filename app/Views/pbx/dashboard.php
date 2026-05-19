<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">PBX Core</span>
        <h2><?= e(__('pbx.dashboard_title')) ?></h2>
        <p><?= e(__('pbx.dashboard_copy')) ?></p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/pbx/ring-groups">Ring Groups</a>
        <a class="button secondary sm" href="/pbx/ivrs">IVR</a>
        <a class="button secondary sm" href="/pbx/trunks">Trunks</a>
        <a class="button secondary sm" href="/pbx/transports"><?= e(__('modules.transports')) ?></a>
        <a class="button secondary sm" href="/pbx/recordings"><?= e(__('modules.recordings')) ?></a>
        <a class="button primary sm" href="/pbx/extensions/create"><?= e(__('actions.new_extension')) ?></a>
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
            <span class="eyebrow">Call flow</span>
            <h3>Flujo empresarial</h3>
        </div>
        <a class="button secondary sm" href="/pbx/outbound-routes/create">Nueva ruta</a>
    </div>
    <div class="module-list">
        <?php foreach ($flowModules as $module): ?>
            <a class="module-row" href="<?= e($module['href']) ?>">
                <div>
                    <strong><?= e($module['label']) ?></strong>
                    <p><?= e($module['hint']) ?></p>
                </div>
                <span class="badge">Config</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow"><?= e(__('modules.extensions')) ?></span>
            <h3><?= e(__('pbx.sip_status')) ?></h3>
        </div>
        <a class="button secondary sm" href="/pbx/extensions"><?= e(__('actions.view_all')) ?></a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('fields.endpoint')) ?></th>
                    <th><?= e(__('fields.auth_user')) ?></th>
                    <th><?= e(__('fields.company')) ?></th>
                    <th><?= e(__('fields.context')) ?></th>
                    <th>SIP</th>
                    <th>Presencia</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($extensions as $extension): ?>
                    <?php $editUrl = '/pbx/extensions/edit?id=' . e($extension['uuid']); ?>
                    <tr>
                        <td>
                            <a class="table-link" href="<?= $editUrl ?>">
                                <strong><?= e($extension['extension_number'] ?? $extension['username']) ?></strong>
                            </a>
                            <span><?= e($extension['internal_endpoint_id'] ?? $extension['id']) ?></span>
                        </td>
                        <td><?= e($extension['auth_username'] ?? $extension['auth_user'] ?? $extension['username']) ?></td>
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
