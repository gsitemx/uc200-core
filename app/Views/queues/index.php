<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<?php $errors = \App\Core\Session::flash('errors') ?? []; ?>
<?php if ($errors !== []): ?>
    <div class="alert danger"><?= e(implode(' ', array_values($errors))) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">Queue / Call Center</span>
        <h2>Operacion enterprise en tiempo real</h2>
        <p>Queues multi tenant, agentes dinamicos, wallboard, SLA, abandonos, overflow y preparacion para supervisor.</p>
    </div>
    <div class="module-meta">
        <a class="button secondary sm" href="#wallboard">Wallboard</a>
        <a class="button secondary sm" href="#agents">Agentes</a>
        <a class="button primary sm" href="/call-center/queues/create">Nueva queue</a>
    </div>
</section>

<section class="card-grid" id="wallboard">
    <?php
    $waiting = array_sum(array_map(static fn ($row) => (int) $row['waiting_calls'], $wallboard));
    $active = array_sum(array_map(static fn ($row) => (int) $row['active_calls'], $wallboard));
    $abandoned = array_sum(array_map(static fn ($row) => (int) $row['abandoned_calls'], $wallboard));
    $online = array_sum(array_map(static fn ($row) => (int) $row['agents_online'], $wallboard));
    ?>
    <article class="metric-card"><span>Waiting</span><strong data-queue-total="waiting"><?= e((string) $waiting) ?></strong><p>Llamadas en espera.</p></article>
    <article class="metric-card"><span>Active</span><strong data-queue-total="active"><?= e((string) $active) ?></strong><p>Llamadas activas.</p></article>
    <article class="metric-card"><span>Abandoned</span><strong data-queue-total="abandoned"><?= e((string) $abandoned) ?></strong><p>Abandonos del dia.</p></article>
    <article class="metric-card"><span>Agents online</span><strong data-queue-total="online"><?= e((string) $online) ?></strong><p>Agentes disponibles.</p></article>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Wallboard</span>
            <h3>Estado por queue</h3>
        </div>
        <button class="button secondary xs" type="button" data-queue-refresh>Refresh</button>
    </div>
    <div class="table-wrap">
        <table id="queue-wallboard">
            <thead>
                <tr>
                    <th>Queue</th>
                    <th>Strategy</th>
                    <th>Waiting</th>
                    <th>Active</th>
                    <th>Abandoned</th>
                    <th>SLA</th>
                    <th>Agents</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wallboard as $row): ?>
                    <tr>
                        <td><strong><?= e($row['extension']) ?></strong><span><?= e($row['name']) ?></span></td>
                        <td><?= e($row['strategy']) ?></td>
                        <td><?= e((string) $row['waiting_calls']) ?></td>
                        <td><?= e((string) $row['active_calls']) ?></td>
                        <td><?= e((string) $row['abandoned_calls']) ?></td>
                        <td><?= e((string) $row['service_level_percent']) ?>%</td>
                        <td><?= e((string) $row['agents_online']) ?> online / <?= e((string) $row['agents_paused']) ?> paused</td>
                        <td><span class="badge <?= $row['status'] === 'active' ? 'on' : '' ?>"><?= e($row['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($wallboard === []): ?>
                    <tr><td colspan="8">Sin queues configuradas.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Queues</span>
            <h3><?= count($queues) ?> configuradas</h3>
        </div>
        <div class="table-search"><input data-table-search="#queues-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="queues-table">
            <thead>
                <tr>
                    <th>Extension</th>
                    <th>Name</th>
                    <th>Members</th>
                    <th>Timeout</th>
                    <th>Overflow</th>
                    <th>Failover</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queues as $queue): ?>
                    <tr>
                        <td><strong><?= e($queue['extension']) ?></strong><span><?= e($queue['company_name']) ?></span></td>
                        <td><?= e($queue['name']) ?><span><?= e($queue['strategy']) ?></span></td>
                        <td><?= e((string) $queue['members_count']) ?></td>
                        <td><?= e((string) $queue['timeout_seconds']) ?>s / retry <?= e((string) $queue['retry_seconds']) ?>s</td>
                        <td><?= e($queue['overflow_destination_type']) ?> <?= e($queue['overflow_destination_id'] ?? '') ?></td>
                        <td><?= e($queue['failover_destination_type']) ?> <?= e($queue['failover_destination_id'] ?? '') ?></td>
                        <td class="actions-cell">
                            <a class="button secondary xs" href="/call-center/queues/edit?id=<?= e($queue['uuid']) ?>">Editar</a>
                            <form method="post" action="/call-center/queues/delete" onsubmit="return confirm('Eliminar queue?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e($queue['uuid']) ?>">
                                <button class="button danger xs" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel" id="agents">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Agents</span>
            <h3>Dinamicos y supervisables</h3>
        </div>
    </div>
    <form class="filter-bar" method="post" action="/call-center/agents/store">
        <?= csrf_field() ?>
        <select name="queue_id" required>
            <option value="">Queue</option>
            <?php foreach ($queues as $queue): ?>
                <option value="<?= e($queue['uuid']) ?>"><?= e($queue['extension']) ?> / <?= e($queue['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="endpoint_id" required>
            <option value="">Extension</option>
            <?php foreach ($extensions as $extension): ?>
                <option value="<?= e($extension['id']) ?>"><?= e($extension['extension_number']) ?> / <?= e($extension['id']) ?></option>
            <?php endforeach; ?>
        </select>
        <input name="member_name" placeholder="Alias">
        <input name="penalty" type="number" min="0" max="99" value="0" placeholder="Penalty">
        <button class="button primary sm" type="submit">Agregar agente</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Queue</th>
                    <th>Penalty</th>
                    <th>State</th>
                    <th>Pause reason</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agents as $agent): ?>
                    <tr>
                        <td><strong><?= e($agent['extension_number'] ?? $agent['endpoint_id']) ?></strong><span><?= e($agent['member_name'] ?? $agent['endpoint_id']) ?></span></td>
                        <td><?= e($agent['queue_extension']) ?> / <?= e($agent['queue_name']) ?></td>
                        <td><?= e((string) $agent['penalty']) ?></td>
                        <td><span class="badge <?= in_array($agent['state'], ['online', 'in_call'], true) ? 'on' : '' ?>"><?= e($agent['state']) ?></span></td>
                        <td><?= e($agent['current_pause_reason'] ?? '-') ?></td>
                        <td class="actions-cell">
                            <?php foreach (['login' => 'Login', 'pause' => 'Pause', 'unpause' => 'Unpause', 'logout' => 'Logout'] as $action => $label): ?>
                                <form method="post" action="/call-center/agents/action">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= e($agent['uuid']) ?>">
                                    <input type="hidden" name="action" value="<?= e($action) ?>">
                                    <?php if ($action === 'pause'): ?>
                                        <select name="reason">
                                            <?php foreach ($pauseReasons as $reason): ?>
                                                <?php if ((int) $reason['company_id'] !== (int) $agent['company_id']) continue; ?>
                                                <option value="<?= e($reason['name']) ?>"><?= e($reason['name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <button class="button secondary xs" type="submit"><?= e($label) ?></button>
                                </form>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($agents === []): ?>
                    <tr><td colspan="6">Sin agentes.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Reports</span>
            <h3>SLA ultimos 30 dias</h3>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Queue</th><th>Offered</th><th>Answered</th><th>Abandoned</th><th>SLA</th><th>Avg hold</th><th>Avg talk</th></tr></thead>
            <tbody>
                <?php foreach ($reports as $row): ?>
                    <tr>
                        <td><strong><?= e($row['extension']) ?></strong><span><?= e($row['name']) ?></span></td>
                        <td><?= e((string) $row['offered_calls']) ?></td>
                        <td><?= e((string) $row['answered_calls']) ?></td>
                        <td><?= e((string) $row['abandoned_calls']) ?></td>
                        <td><?= e((string) $row['sla']) ?>%</td>
                        <td><?= e((string) $row['avg_hold']) ?>s</td>
                        <td><?= e((string) $row['avg_talk']) ?>s</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.querySelector('[data-queue-refresh]')?.addEventListener('click', async () => {
    const response = await fetch('/call-center/wallboard', { credentials: 'same-origin' });
    if (!response.ok) return;
    window.location.reload();
});
</script>
