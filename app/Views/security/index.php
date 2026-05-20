<?php
$stats = $dashboard['stats'] ?? [];
$fail2ban = $dashboard['fail2ban'] ?? [];
$firewall = $dashboard['firewall'] ?? [];
$attackers = $dashboard['attackers'] ?? [];
$extensions = $dashboard['extensions'] ?? [];
$events = $dashboard['events'] ?? [];
$bans = $dashboard['bans'] ?? [];
$whitelist = $dashboard['whitelist'] ?? [];
$blacklist = $dashboard['blacklist'] ?? [];
$settings = $dashboard['settings'] ?? [];
$policy = $dashboard['policy'] ?? [];
$section = $section ?? 'overview';
$sections = [
    'overview' => ['label' => 'Seguridad', 'path' => '/security'],
    'attacks' => ['label' => 'Ataques SIP', 'path' => '/security/attacks'],
    'bans' => ['label' => 'IPs bloqueadas', 'path' => '/security/bans'],
    'fail2ban' => ['label' => 'Fail2Ban', 'path' => '/security/fail2ban'],
    'firewall' => ['label' => 'Firewall', 'path' => '/security/firewall'],
    'whitelist' => ['label' => 'Whitelist', 'path' => '/security/whitelist'],
    'blacklist' => ['label' => 'Blacklist', 'path' => '/security/blacklist'],
];
?>

<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>
<?php if (! empty($error)): ?>
    <div class="alert danger"><?= e($error) ?></div>
<?php endif; ?>

<section class="page-panel">
    <div class="panel-header">
        <div>
            <span class="eyebrow">Security Center</span>
            <h2>PBX / SIP Firewall</h2>
            <p class="muted">Monitoreo de ataques SIP, administracion de Fail2Ban y reglas UC200 sobre iptables.</p>
        </div>
        <div class="stack-inline">
            <span class="badge <?= ($fail2ban['available'] ?? false) ? 'on' : '' ?>"><?= ($fail2ban['available'] ?? false) ? 'Fail2Ban listo' : 'Fail2Ban no disponible' ?></span>
            <span class="badge <?= ($firewall['available'] ?? false) ? 'on' : '' ?>">iptables</span>
        </div>
    </div>

    <div class="section-tabs compact-tabs">
        <?php foreach ($sections as $slug => $tab): ?>
            <a class="button <?= $section === $slug ? 'primary' : 'secondary' ?> xs" href="<?= e($tab['path']) ?>"><?= e($tab['label']) ?></a>
        <?php endforeach; ?>
    </div>
</section>

<section class="stats-grid">
    <article class="metric-card">
        <span>Intentos fallidos 5m</span>
        <strong><?= e((string) ($stats['failed_last_5m'] ?? 0)) ?></strong>
    </article>
    <article class="metric-card">
        <span>IPs atacantes</span>
        <strong><?= e((string) ($stats['attackers_last_5m'] ?? 0)) ?></strong>
    </article>
    <article class="metric-card">
        <span>Extensiones atacadas</span>
        <strong><?= e((string) ($stats['extensions_last_5m'] ?? 0)) ?></strong>
    </article>
    <article class="metric-card">
        <span>IPs baneadas</span>
        <strong><?= e((string) ($stats['active_bans'] ?? 0)) ?></strong>
    </article>
</section>

<section class="surface">
    <div class="surface-header">
        <h3>Politica activa</h3>
        <span class="badge">Proteccion PBX</span>
    </div>
    <div class="meta-grid">
        <div><dt>Ventana monitoreo</dt><dd><?= e((string) ($policy['window_minutes'] ?? 5)) ?> minutos</dd></div>
        <div><dt>Intentos maximos</dt><dd><?= e((string) ($policy['maxretry'] ?? 10)) ?></dd></div>
        <div><dt>Findtime</dt><dd><?= e((string) ($policy['findtime_human'] ?? '5m')) ?></dd></div>
        <div><dt>Bantime</dt><dd><?= e((string) ($policy['bantime_human'] ?? '1h')) ?></dd></div>
        <div><dt>Flood threshold</dt><dd><?= e((string) ($policy['register_flood_threshold'] ?? 10)) ?> REGISTER</dd></div>
        <div><dt>Probe threshold</dt><dd><?= e((string) ($policy['multi_extension_threshold'] ?? 3)) ?> extensiones</dd></div>
        <div><dt>Retencion eventos</dt><dd><?= e((string) ($policy['event_retention_days'] ?? 7)) ?> dias</dd></div>
        <div><dt>Jail</dt><dd><?= e((string) ($fail2ban['jail_name'] ?? 'uc200-asterisk')) ?></dd></div>
    </div>
</section>

<?php if ($section === 'overview' || $section === 'attacks'): ?>
    <section class="grid two">
        <article class="surface">
            <div class="surface-header">
                <h3>IPs atacantes</h3>
                <span class="badge">Ultimos <?= e((string) ($policy['window_minutes'] ?? 5)) ?> minutos</span>
            </div>
            <table class="data-table dense">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Intentos</th>
                        <th>Criticos</th>
                        <th>Extensiones</th>
                        <th>Ultimo evento</th>
                        <?php if (! empty($canManage)): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attackers as $row): ?>
                        <tr>
                            <td><?= e((string) $row['label']) ?></td>
                            <td><?= e((string) $row['total']) ?></td>
                            <td><?= e((string) ($row['critical_total'] ?? 0)) ?></td>
                            <td><?= e((string) ($row['extensions_targeted'] ?? 0)) ?></td>
                            <td><?= e((string) ($row['last_seen'] ?? '-')) ?></td>
                            <?php if (! empty($canManage)): ?>
                                <td class="actions-cell">
                                    <form method="post" action="/security/ban">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="ip" value="<?= e((string) $row['label']) ?>">
                                        <input type="hidden" name="reason" value="Top attacker auto review">
                                        <button class="button danger xs" type="submit">Ban</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($attackers === []): ?>
                        <tr><td colspan="<?= ! empty($canManage) ? '6' : '5' ?>">Sin atacantes recientes.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>

        <article class="surface">
            <div class="surface-header">
                <h3>Extensiones atacadas</h3>
                <span class="badge">Ultimos <?= e((string) ($policy['window_minutes'] ?? 5)) ?> minutos</span>
            </div>
            <table class="data-table dense">
                <thead>
                    <tr>
                    <th>Extension</th>
                    <th>Intentos</th>
                </tr>
                </thead>
                <tbody>
                    <?php foreach ($extensions as $row): ?>
                        <tr>
                            <td><?= e((string) $row['label']) ?></td>
                            <td><?= e((string) $row['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($extensions === []): ?>
                        <tr><td colspan="2">Sin extensiones atacadas recientemente.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>

    <section class="surface">
        <div class="surface-header">
            <h3>Eventos SIP recientes</h3>
            <span class="badge">Motor de eventos SIP</span>
        </div>
        <table class="data-table dense">
            <thead>
                <tr>
                    <th>Momento</th>
                    <th>IP</th>
                    <th>Evento</th>
                    <th>Extension</th>
                    <th>Pais</th>
                    <th>Detalle</th>
                    <?php if (! empty($canManage)): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= e((string) $event['detected_at']) ?></td>
                        <td><?= e((string) $event['ip']) ?></td>
                        <td><span class="badge <?= ($event['severity'] ?? '') === 'critical' ? 'danger' : '' ?>"><?= e((string) $event['event_type']) ?></span></td>
                        <td><?= e((string) ($event['extension_hint'] ?? '-')) ?></td>
                        <td><?= e(trim(((string) ($event['country_code'] ?? '')) . ' ' . ((string) ($event['country_name'] ?? '')))) ?></td>
                        <td><?= e((string) (($event['details']['message'] ?? '') ?: 'N/D')) ?></td>
                        <?php if (! empty($canManage)): ?>
                            <td class="actions-cell">
                                <form method="post" action="/security/ban">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ip" value="<?= e((string) $event['ip']) ?>">
                                    <input type="hidden" name="reason" value="<?= e((string) $event['event_type']) ?>">
                                    <button class="button danger xs" type="submit">Bloquear</button>
                                </form>
                                <form method="post" action="/security/whitelist/store">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ip" value="<?= e((string) $event['ip']) ?>">
                                    <input type="hidden" name="reason" value="Trusted after review">
                                    <button class="button secondary xs" type="submit">Whitelist</button>
                                </form>
                                <form method="post" action="/security/blacklist/store">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="ip" value="<?= e((string) $event['ip']) ?>">
                                    <input type="hidden" name="reason" value="<?= e((string) $event['event_type']) ?>">
                                    <button class="button secondary xs" type="submit">Blacklist</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($events === []): ?>
                    <tr><td colspan="<?= ! empty($canManage) ? '7' : '6' ?>">Sin eventos recientes.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if ($section === 'overview' || $section === 'bans'): ?>
    <section class="grid two">
        <article class="surface">
            <div class="surface-header">
                <h3>Ban manual</h3>
                <span class="badge">uc200-asterisk</span>
            </div>
            <form method="post" action="/security/ban" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-grid compact">
                    <label class="form-group">
                        <span>IP</span>
                        <input type="text" name="ip" placeholder="203.0.113.10">
                    </label>
                    <label class="form-group">
                        <span>Motivo</span>
                        <input type="text" name="reason" placeholder="Failed auth flood">
                    </label>
                </div>
                <div class="stack-inline">
                    <button class="button danger sm" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Banear IP</button>
                </div>
            </form>
        </article>

        <article class="surface">
            <div class="surface-header">
                <h3>Desbanear IP</h3>
                <span class="badge">Fail2Ban</span>
            </div>
            <form method="post" action="/security/unban" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-grid compact">
                    <label class="form-group">
                        <span>IP</span>
                        <input type="text" name="ip" placeholder="203.0.113.10">
                    </label>
                    <label class="form-group">
                        <span>Motivo</span>
                        <input type="text" name="reason" placeholder="Revision operativa">
                    </label>
                </div>
                <div class="stack-inline">
                    <button class="button secondary sm" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Desbanear</button>
                </div>
            </form>
        </article>
    </section>

    <section class="surface">
        <div class="surface-header">
            <h3>Historial de bans y acciones</h3>
            <span class="badge"><?= e((string) count($bans)) ?> registros</span>
        </div>
        <table class="data-table dense">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>IP</th>
                    <th>Accion</th>
                    <th>Jail</th>
                    <th>Motivo</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bans as $ban): ?>
                    <tr>
                        <td><?= e((string) $ban['created_at']) ?></td>
                        <td><?= e((string) $ban['ip']) ?></td>
                        <td><?= e((string) $ban['action']) ?></td>
                        <td><?= e((string) $ban['jail_name']) ?></td>
                        <td><?= e((string) ($ban['reason'] ?? '')) ?></td>
                        <td><?= e((string) ($ban['created_by_name'] ?? 'Sistema')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($bans === []): ?>
                    <tr><td colspan="6">Sin acciones registradas todavia.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if ($section === 'overview' || $section === 'fail2ban'): ?>
    <section class="grid two">
        <article class="surface">
            <div class="surface-header">
                <h3>Estado Fail2Ban</h3>
                <span class="badge"><?= e((string) ($fail2ban['service'] ?? 'unknown')) ?></span>
            </div>
            <dl class="meta-grid">
                <div><dt>Jail</dt><dd><?= e((string) ($fail2ban['jail_name'] ?? 'uc200-asterisk')) ?></dd></div>
                <div><dt>Jails activos</dt><dd><?= e(implode(', ', $fail2ban['jails'] ?? [])) ?></dd></div>
                <div><dt>Failed ahora</dt><dd><?= e((string) (($fail2ban['summary']['currently_failed'] ?? 0))) ?></dd></div>
                <div><dt>Baneados ahora</dt><dd><?= e((string) (($fail2ban['summary']['currently_banned'] ?? 0))) ?></dd></div>
                <div><dt>Total baneados</dt><dd><?= e((string) (($fail2ban['summary']['total_banned'] ?? 0))) ?></dd></div>
                <div><dt>IPs baneadas</dt><dd><?= e(implode(', ', $fail2ban['banned_ips'] ?? [])) ?></dd></div>
            </dl>
            <div class="stack-inline">
                <form method="post" action="/security/fail2ban/reload"><?= csrf_field() ?><button class="button secondary xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Recargar</button></form>
                <form method="post" action="/security/fail2ban/restart"><?= csrf_field() ?><button class="button danger xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Reiniciar</button></form>
            </div>
        </article>

        <article class="surface">
            <div class="surface-header">
                <h3>Configuracion automatizacion</h3>
                <span class="badge">Jail.d</span>
            </div>
            <form method="post" action="/security/settings" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-grid compact">
                    <label class="form-group">
                        <span>Jail</span>
                        <input type="text" name="jail_name" value="<?= e((string) ($settings['security.fail2ban.jail_name'] ?? 'uc200-asterisk')) ?>">
                    </label>
                    <label class="form-group">
                        <span>Maxretry</span>
                        <input type="number" min="1" max="100" name="maxretry" value="<?= e((string) ($settings['security.fail2ban.maxretry'] ?? '10')) ?>">
                    </label>
                    <label class="form-group">
                        <span>Findtime</span>
                        <input type="number" min="60" name="findtime" value="<?= e((string) ($settings['security.fail2ban.findtime'] ?? '300')) ?>">
                    </label>
                    <label class="form-group">
                        <span>Bantime</span>
                        <input type="number" min="60" name="bantime" value="<?= e((string) ($settings['security.fail2ban.bantime'] ?? '3600')) ?>">
                    </label>
                    <label class="form-group full">
                        <span>Logpath</span>
                        <input type="text" name="logpath" value="<?= e((string) ($settings['security.fail2ban.logpath'] ?? '/var/log/asterisk/messages')) ?>">
                    </label>
                    <label class="form-group">
                        <span>Ventana monitoreo (min)</span>
                        <input type="number" min="1" max="1440" name="window_minutes" value="<?= e((string) ($settings['security.monitor.window_minutes'] ?? '5')) ?>">
                    </label>
                    <label class="form-group">
                        <span>REGISTER flood threshold</span>
                        <input type="number" min="3" max="1000" name="register_flood_threshold" value="<?= e((string) ($settings['security.monitor.register_flood_threshold'] ?? '10')) ?>">
                    </label>
                    <label class="form-group">
                        <span>Probe threshold</span>
                        <input type="number" min="2" max="100" name="multi_extension_threshold" value="<?= e((string) ($settings['security.monitor.multi_extension_threshold'] ?? '3')) ?>">
                    </label>
                    <label class="form-group">
                        <span>Retencion eventos (dias)</span>
                        <input type="number" min="1" max="365" name="event_retention_days" value="<?= e((string) ($settings['security.monitor.event_retention_days'] ?? '7')) ?>">
                    </label>
                    <label class="form-group checkbox">
                        <input type="checkbox" name="enabled" value="1" <?= (($settings['security.fail2ban.enabled'] ?? '1') === '1') ? 'checked' : '' ?>>
                        <span>Habilitado</span>
                    </label>
                </div>
                <button class="button primary sm" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Guardar configuracion</button>
            </form>
        </article>
    </section>

    <section class="surface">
        <div class="surface-header">
            <h3>Logs recientes Fail2Ban</h3>
            <span class="badge">journalctl</span>
        </div>
        <pre class="terminal-log"><?= e(implode("\n", array_slice($fail2ban['recent_logs'] ?? [], 0, 40))) ?></pre>
    </section>
<?php endif; ?>

<?php if ($section === 'overview' || $section === 'firewall'): ?>
    <section class="grid two">
        <article class="surface">
            <div class="surface-header">
                <h3>Firewall UC200</h3>
                <span class="badge"><?= e((string) ($firewall['chain'] ?? 'UC200_SECURITY')) ?></span>
            </div>
            <form method="post" action="/security/firewall/block" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-grid compact">
                    <label class="form-group">
                        <span>IP</span>
                        <input type="text" name="ip" placeholder="198.51.100.20">
                    </label>
                    <label class="form-group">
                        <span>Motivo</span>
                        <input type="text" name="reason" placeholder="Scanner SIP">
                    </label>
                </div>
                <div class="stack-inline">
                    <button class="button danger xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Bloquear en firewall</button>
                    <button formaction="/security/firewall/unblock" class="button secondary xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Desbloquear</button>
                </div>
            </form>
            <form method="post" action="/security/firewall/persist" class="stack-inline">
                <?= csrf_field() ?>
                <button class="button secondary xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Persistir reglas</button>
            </form>
        </article>

        <article class="surface">
            <div class="surface-header">
                <h3>Reglas activas UC200</h3>
                <span class="badge">iptables</span>
            </div>
            <pre class="terminal-log"><?= e(implode("\n", $firewall['rules'] ?? [])) ?></pre>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'overview' || $section === 'whitelist'): ?>
    <section class="grid two">
        <article class="surface">
            <div class="surface-header">
                <h3>Agregar whitelist</h3>
                <span class="badge">Trusted IPs</span>
            </div>
            <form method="post" action="/security/whitelist/store" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-grid compact">
                    <label class="form-group">
                        <span>IP</span>
                        <input type="text" name="ip" placeholder="203.0.113.5">
                    </label>
                    <label class="form-group">
                        <span>Motivo</span>
                        <input type="text" name="reason" placeholder="Sucursal principal">
                    </label>
                </div>
                <button class="button primary xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Agregar</button>
            </form>
        </article>
        <article class="surface">
            <div class="surface-header">
                <h3>Whitelist activa</h3>
                <span class="badge"><?= e((string) count($whitelist)) ?> IPs</span>
            </div>
            <table class="data-table dense">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Motivo</th>
                        <th>Creado por</th>
                        <?php if (! empty($canManage)): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($whitelist as $row): ?>
                        <tr>
                            <td><?= e((string) $row['ip']) ?></td>
                            <td><?= e((string) ($row['reason'] ?? '')) ?></td>
                            <td><?= e((string) ($row['created_by_name'] ?? 'Sistema')) ?></td>
                            <?php if (! empty($canManage)): ?>
                                <td>
                                    <form method="post" action="/security/whitelist/delete">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $row['uuid']) ?>">
                                        <button class="button secondary xs" type="submit">Quitar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($whitelist === []): ?>
                        <tr><td colspan="<?= ! empty($canManage) ? '4' : '3' ?>">Sin IPs en whitelist.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
<?php endif; ?>

<?php if ($section === 'overview' || $section === 'blacklist'): ?>
    <section class="grid two">
        <article class="surface">
            <div class="surface-header">
                <h3>Agregar blacklist</h3>
                <span class="badge">Block list</span>
            </div>
            <form method="post" action="/security/blacklist/store" class="stack-form">
                <?= csrf_field() ?>
                <div class="form-grid compact">
                    <label class="form-group">
                        <span>IP</span>
                        <input type="text" name="ip" placeholder="198.51.100.44">
                    </label>
                    <label class="form-group">
                        <span>Motivo</span>
                        <input type="text" name="reason" placeholder="SIP scanner persistente">
                    </label>
                </div>
                <button class="button danger xs" type="submit"<?= empty($canManage) ? ' disabled' : '' ?>>Agregar y bloquear</button>
            </form>
        </article>
        <article class="surface">
            <div class="surface-header">
                <h3>Blacklist activa</h3>
                <span class="badge"><?= e((string) count($blacklist)) ?> IPs</span>
            </div>
            <table class="data-table dense">
                <thead>
                    <tr>
                        <th>IP</th>
                        <th>Motivo</th>
                        <th>Creado por</th>
                        <?php if (! empty($canManage)): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($blacklist as $row): ?>
                        <tr>
                            <td><?= e((string) $row['ip']) ?></td>
                            <td><?= e((string) ($row['reason'] ?? '')) ?></td>
                            <td><?= e((string) ($row['created_by_name'] ?? 'Sistema')) ?></td>
                            <?php if (! empty($canManage)): ?>
                                <td>
                                    <form method="post" action="/security/blacklist/delete">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= e((string) $row['uuid']) ?>">
                                        <button class="button secondary xs" type="submit">Quitar</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($blacklist === []): ?>
                        <tr><td colspan="<?= ! empty($canManage) ? '4' : '3' ?>">Sin IPs en blacklist.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </article>
    </section>
<?php endif; ?>
