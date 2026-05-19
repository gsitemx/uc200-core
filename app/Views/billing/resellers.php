<?php if (! empty($flash)): ?>
    <div class="alert success"><?= e($flash) ?></div>
<?php endif; ?>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Resellers</span>
            <h3>Crear canal</h3>
        </div>
    </div>
    <form class="form-grid" method="post" action="/billing/resellers/store">
        <?= csrf_field() ?>
        <div class="field"><label>Nombre</label><input name="name" required></div>
        <div class="field"><label>Slug</label><input name="slug" required></div>
        <div class="field">
            <label>Empresa asociada</label>
            <select name="company_id">
                <option value="">Sin empresa</option>
                <?php foreach ($companies as $company): ?>
                    <option value="<?= e((string) $company['id']) ?>"><?= e($company['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>Parent reseller</label>
            <select name="parent_reseller_id">
                <option value="">Root</option>
                <?php foreach ($resellers as $reseller): ?>
                    <option value="<?= e((string) $reseller['id']) ?>"><?= e($reseller['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>Dominio personalizado</label><input name="custom_domain" placeholder="pbx.partner.com"></div>
        <div class="field">
            <label>Estado</label>
            <select name="status"><option value="active">active</option><option value="inactive">inactive</option><option value="suspended">suspended</option></select>
        </div>
        <div class="field"><label>Branding JSON</label><textarea name="branding_json" rows="3">{}</textarea></div>
        <div class="field"><label>Limits JSON</label><textarea name="limits_json" rows="3">{}</textarea></div>
        <div><button class="button primary sm" type="submit">Crear reseller</button></div>
    </form>
</section>

<section class="module-panel">
    <div class="section-heading">
        <div>
            <span class="eyebrow">Canales</span>
            <h3><?= count($resellers) ?> resellers</h3>
        </div>
        <div class="table-search"><input data-table-search="#resellers-table" placeholder="Buscar"></div>
    </div>
    <div class="table-wrap">
        <table id="resellers-table">
            <thead><tr><th>Reseller</th><th>Parent</th><th>Empresa</th><th>Dominio</th><th>Estado</th></tr></thead>
            <tbody>
                <?php foreach ($resellers as $reseller): ?>
                    <tr>
                        <td><strong><?= e($reseller['name']) ?></strong><span><?= e($reseller['slug']) ?></span></td>
                        <td><?= e($reseller['parent_name'] ?? '-') ?></td>
                        <td><?= e($reseller['company_name'] ?? '-') ?></td>
                        <td><?= e($reseller['custom_domain'] ?? '-') ?></td>
                        <td><span class="badge <?= $reseller['status'] === 'active' ? 'on' : '' ?>"><?= e($reseller['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($resellers === []): ?>
                    <tr><td colspan="5">Sin resellers.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
