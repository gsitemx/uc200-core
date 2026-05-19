<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Provisioning Engine</span>
        <h2>Telefonos y auto provisioning</h2>
        <p>Gestiona dispositivos, plantillas, BLF, phonebooks y URLs de provisionamiento por tenant.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/provisioning/phonebooks/create">Nuevo phonebook</a>
        <a class="button secondary sm" href="/provisioning/templates/create">Nueva plantilla</a>
        <a class="button primary sm" href="/provisioning/devices/create">Nuevo telefono</a>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Phonebooks</span>
            <h3><?= count($phonebooks) ?> directorios</h3>
        </div>
    </div>
    <div class="module-list">
        <?php foreach ($phonebooks as $phonebook): ?>
            <a class="module-row" href="/provisioning/phonebooks/edit?id=<?= e($phonebook['uuid']) ?>">
                <div>
                    <strong><?= e($phonebook['name']) ?></strong>
                    <p><?= e($phonebook['company_name']) ?></p>
                </div>
                <span class="badge <?= $phonebook['status'] === 'active' ? 'on' : '' ?>"><?= e($phonebook['status']) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($phonebooks === []): ?>
            <p class="empty-copy">Sin phonebooks.</p>
        <?php endif; ?>
    </div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Telefonos</span><strong><?= count($devices) ?></strong><p>Inventario aprovisionable.</p></article>
    <article class="metric-card"><span>Plantillas</span><strong><?= count($templates) ?></strong><p>Yealink, Grandstream, Fanvil, Poly y Cisco.</p></article>
    <article class="metric-card"><span>Phonebooks</span><strong><?= count($phonebooks) ?></strong><p>Directorios por tenant.</p></article>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Devices</span>
            <h3><?= count($devices) ?> telefonos</h3>
        </div>
        <div class="table-search"><input data-table-search="#devices-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="devices-table">
            <thead>
                <tr>
                    <th>MAC</th>
                    <th>Vendor</th>
                    <th>Modelo</th>
                    <th>Extension</th>
                    <th>Empresa</th>
                    <th>Provisioning URL</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $device): ?>
                    <?php $url = '/provisioning/config?mac=' . rawurlencode($device['mac_address']) . '&secret=' . rawurlencode($device['provisioning_secret']); ?>
                    <tr>
                        <td><strong><?= e($device['mac_address']) ?></strong><span><?= e($device['firmware_version'] ?? '') ?></span></td>
                        <td><?= e($device['vendor']) ?></td>
                        <td><?= e($device['model']) ?></td>
                        <td><?= e($device['extension_number'] ?? '-') ?></td>
                        <td><?= e($device['company_name']) ?></td>
                        <td><input class="inline-url" readonly value="<?= e($url) ?>"></td>
                        <td><span class="badge <?= $device['status'] === 'active' ? 'on' : '' ?>"><?= e($device['status']) ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary xs" href="/provisioning/devices/edit?id=<?= e($device['uuid']) ?>">Editar</a>
                            <form method="post" action="/provisioning/devices/reboot">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($device['uuid']) ?>">
                                <button class="button secondary xs" type="submit">Reboot</button>
                            </form>
                            <form method="post" action="/provisioning/devices/delete" onsubmit="return confirm('Eliminar telefono?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($device['uuid']) ?>">
                                <button class="button danger xs" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($devices === []): ?>
                    <tr><td colspan="8">Sin telefonos.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Templates</span>
            <h3><?= count($templates) ?> plantillas</h3>
        </div>
    </div>
    <div class="module-list">
        <?php foreach ($templates as $template): ?>
            <a class="module-row" href="/provisioning/templates/edit?id=<?= e($template['uuid']) ?>">
                <div>
                    <strong><?= e($template['name']) ?></strong>
                    <p><?= e($template['vendor']) ?> / <?= e($template['model'] ?? 'generic') ?> / <?= e($template['company_name']) ?></p>
                </div>
                <span class="badge <?= $template['status'] === 'active' ? 'on' : '' ?>"><?= e($template['status']) ?></span>
            </a>
        <?php endforeach; ?>
        <?php if ($templates === []): ?>
            <p class="empty-copy">Sin plantillas personalizadas. Se usaran defaults por vendor.</p>
        <?php endif; ?>
    </div>
</section>
