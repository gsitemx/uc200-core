<?php
$filterValue = static fn (string $key): string => (string) ($filters[$key] ?? '');
$directions = ['' => 'Todas', 'internal' => 'Interna', 'inbound' => 'Entrante', 'outbound' => 'Saliente'];
?>

<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="hero-panel">
    <div>
        <span class="eyebrow">MixMonitor</span>
        <h2><?= e(__('modules.recordings')) ?></h2>
        <p><?= e(__('pbx.recordings_copy')) ?></p>
    </div>
    <a class="button secondary sm" href="/pbx">PBX Core</a>
</section>

<section class="module-panel">
    <form class="filter-bar" method="get" action="/pbx/recordings">
        <label class="field"><?= e(__('actions.search')) ?>
            <input name="q" value="<?= e($filterValue('q')) ?>" placeholder="caller, callee o uniqueid">
        </label>
        <label class="field"><?= e(__('fields.direction')) ?>
            <select name="direction">
                <?php foreach ($directions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $filterValue('direction') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (has_role('super-admin')): ?>
            <label class="field"><?= e(__('fields.company')) ?>
                <select name="company_id">
                    <option value="0">Todas</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?= e((string) $company['id']) ?>" <?= (int) $filterValue('company_id') === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>
        <label class="field">Desde
            <input type="date" name="date_from" value="<?= e($filterValue('date_from')) ?>">
        </label>
        <label class="field">Hasta
            <input type="date" name="date_to" value="<?= e($filterValue('date_to')) ?>">
        </label>
        <button class="button primary sm" type="submit"><?= e(__('actions.filter')) ?></button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th><?= e(__('fields.date')) ?></th>
                    <th><?= e(__('fields.caller')) ?></th>
                    <th><?= e(__('fields.callee')) ?></th>
                    <th><?= e(__('fields.direction')) ?></th>
                    <th><?= e(__('fields.duration')) ?></th>
                    <th><?= e(__('fields.company')) ?></th>
                    <th>Audio</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recordings as $recording): ?>
                    <?php $playUrl = '/pbx/recordings/play?id=' . e($recording['uuid']); ?>
                    <tr>
                        <td>
                            <strong><?= e($recording['started_at'] ?? '') ?></strong>
                            <span><?= e($recording['uniqueid'] ?? '') ?></span>
                        </td>
                        <td><?= e($recording['caller'] ?? '') ?></td>
                        <td><?= e($recording['callee'] ?? '') ?></td>
                        <td><span class="badge"><?= e($recording['direction'] ?? '') ?></span></td>
                        <td><?= e((string) ($recording['duration_seconds'] ?? 0)) ?>s</td>
                        <td><?= e($recording['company_name'] ?? '') ?></td>
                        <td>
                            <audio controls preload="none" src="<?= $playUrl ?>"></audio>
                        </td>
                        <td>
                            <a class="button secondary xs" href="/pbx/recordings/download?id=<?= e($recording['uuid']) ?>"><?= e(__('actions.download')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($recordings === []): ?>
                    <tr>
                        <td colspan="8">No hay grabaciones para los filtros actuales.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="table-footer">
        <span><?= count($recordings) ?> registros</span>
        <nav class="pagination" aria-label="Pagination"><span class="active">1</span></nav>
    </div>
</section>
