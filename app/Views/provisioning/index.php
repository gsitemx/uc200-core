<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Provisioning Engine</span>
        <h2>Telefonos y auto provisioning</h2>
        <p>Selecciona marca, modelo y extension. UC200 genera automaticamente URL segura, token, archivo de configuracion, BLF y phonebook cuando aplica.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="/provisioning/phonebooks/create">Nuevo phonebook</a>
        <?php if (has_role('super-admin')): ?>
            <a class="button secondary sm" href="<?= e($advancedTemplates ? '/provisioning' : '/provisioning?advanced=1') ?>"><?= e($advancedTemplates ? 'Ocultar modo avanzado' : 'Modo avanzado') ?></a>
            <?php if ($advancedTemplates): ?>
                <a class="button secondary sm" href="/provisioning/templates/create">Nueva plantilla</a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="button primary sm" href="<?= e($advancedTemplates ? '/provisioning/devices/create?advanced=1' : '/provisioning/devices/create') ?>">Nuevo telefono</a>
    </div>
</section>

<section class="card-grid">
    <article class="metric-card"><span>Telefonos</span><strong><?= count($devices) ?></strong><p>Inventario aprovisionable.</p></article>
    <article class="metric-card"><span>Modelos soportados</span><strong><?= array_sum(array_map(static fn (array $brand): int => count($brand['models'] ?? []), $brands)) ?></strong><p>Catalogo precargado por marca.</p></article>
    <article class="metric-card"><span>Phonebooks</span><strong><?= count($phonebooks) ?></strong><p>Directorios por tenant.</p></article>
    <article class="metric-card"><span>Descargas recientes</span><strong><?= count($downloadLogs) ?></strong><p>Provisioning servido con trazabilidad.</p></article>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Catalogo</span>
            <h3>Modelos precargados</h3>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Marca</th>
                    <th>Modelo</th>
                    <th>BLF</th>
                    <th>Phonebook</th>
                    <th>Reboot</th>
                    <th>TLS</th>
                    <th>Archivos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($brands as $vendor => $brand): ?>
                    <?php foreach (($brand['models'] ?? []) as $model => $profile): ?>
                        <tr>
                            <td><strong><?= e($brand['label'] ?? ucfirst($vendor)) ?></strong></td>
                            <td><?= e($model) ?><span><?= e((string) ($profile['notes'] ?? '')) ?></span></td>
                            <td><span class="badge <?= ! empty($profile['supports_blf']) ? 'on' : '' ?>"><?= ! empty($profile['supports_blf']) ? 'BLF' : 'No' ?></span></td>
                            <td><span class="badge <?= ! empty($profile['supports_phonebook']) ? 'on' : '' ?>"><?= ! empty($profile['supports_phonebook']) ? 'Phonebook' : 'No' ?></span></td>
                            <td><span class="badge <?= ! empty($profile['supports_remote_reboot']) ? 'on' : '' ?>"><?= ! empty($profile['supports_remote_reboot']) ? 'Reboot' : 'No' ?></span></td>
                            <td><span class="badge <?= ! empty($profile['supports_tls']) ? 'on' : '' ?>"><?= ! empty($profile['supports_tls']) ? 'TLS listo' : 'Legacy' ?></span></td>
                            <td><?= e(implode(', ', (array) ($profile['filenames'] ?? []))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
                    <th>Marca / modelo</th>
                    <th>Extension</th>
                    <th>Empresa</th>
                    <th>Provision</th>
                    <th>URL</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($devices as $device): ?>
                    <?php $url = '/provisioning/' . rawurlencode($device['company_uuid']) . '/' . rawurlencode($device['mac_address']) . '?token=' . rawurlencode($device['provisioning_secret']); ?>
                    <tr>
                        <td><strong><?= e($device['mac_address']) ?></strong><span><?= e($device['generated_filename'] ?? $device['firmware_version'] ?? '') ?></span></td>
                        <td><?= e(ucfirst($device['vendor'])) ?><span><?= e($device['model']) ?></span></td>
                        <td><?= e($device['extension_number'] ?? '-') ?></td>
                        <td><?= e($device['company_name']) ?></td>
                        <td><strong><?= e($device['last_provisioned_at'] ?? '-') ?></strong><span><?= e($device['last_ip'] ?? '') ?></span></td>
                        <td>
                            <div class="stack-inline">
                                <input class="inline-url" readonly value="<?= e($url) ?>" data-copy-value>
                                <button class="button secondary xs" type="button" data-copy-target="previous">Copiar URL</button>
                            </div>
                        </td>
                        <td><span class="badge <?= $device['status'] === 'active' ? 'on' : '' ?>"><?= e($device['status']) ?></span></td>
                        <td class="actions-cell">
                            <a class="button secondary xs" href="/provisioning/devices/config?id=<?= e($device['uuid']) ?>" target="_blank" rel="noopener">Descargar config</a>
                            <a class="button secondary xs" href="<?= e($advancedTemplates ? '/provisioning/devices/edit?id=' . $device['uuid'] . '&advanced=1' : '/provisioning/devices/edit?id=' . $device['uuid']) ?>">Editar</a>
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
            <span class="eyebrow">Asistente</span>
            <h3>Instrucciones por marca</h3>
        </div>
    </div>
    <div class="card-grid">
        <?php foreach ($brands as $vendor => $brand): ?>
            <article class="module-panel nested-panel">
                <div class="section-heading">
                    <div>
                        <span class="eyebrow"><?= e($brand['label'] ?? ucfirst($vendor)) ?></span>
                        <h3><?= count($brand['models'] ?? []) ?> modelos</h3>
                    </div>
                </div>
                <ol class="compact-list">
                    <?php foreach (($brand['instructions'] ?? []) as $instruction): ?>
                        <li><?= e($instruction) ?></li>
                    <?php endforeach; ?>
                </ol>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Logs</span>
            <h3>Descargas de provisioning</h3>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Telefono</th>
                    <th>Tenant</th>
                    <th>IP</th>
                    <th>User-Agent</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($downloadLogs as $log): ?>
                    <tr>
                        <td><strong><?= e($log['created_at']) ?></strong><span><?= e($log['token_fragment'] ?? '') ?></span></td>
                        <td><?= e($log['vendor'] . ' / ' . $log['model']) ?><span><?= e($log['mac_address']) ?></span></td>
                        <td><?= e($log['company_name']) ?></td>
                        <td><?= e($log['ip_address'] ?? '-') ?></td>
                        <td><?= e($log['user_agent'] ?? '-') ?></td>
                        <td><span class="badge <?= (int) $log['status_code'] === 200 ? 'on' : 'danger' ?>"><?= e((string) $log['status_code']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($downloadLogs === []): ?>
                    <tr><td colspan="6">Sin descargas recientes.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($advancedTemplates): ?>
    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Modo avanzado</span>
                <h3>Plantillas personalizadas</h3>
                <p>Solo usuarios avanzados. El flujo normal usa plantillas internas precargadas.</p>
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
                <p class="empty-copy">Sin plantillas personalizadas.</p>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>
