<?php
$source = $old !== [] ? $old : $template;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($template['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Provisioning template</span>
                <h3><?= e($title) ?></h3>
            </div>
            <button class="button primary sm" type="submit">Guardar</button>
        </div>

        <div class="form-grid">
            <?php if (has_role('super-admin')): ?>
                <label class="field">Empresa
                    <select name="company_id">
                        <option value="0">Selecciona</option>
                        <?php foreach ($companies as $company): ?>
                            <option value="<?= e((string) $company['id']) ?>" <?= (int) $value('company_id') === (int) $company['id'] ? 'selected' : '' ?>><?= e($company['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>

            <label class="field">Nombre
                <input name="name" value="<?= e($value('name')) ?>" required>
            </label>

            <label class="field">Vendor
                <select name="vendor">
                    <?php foreach ($vendors as $vendor): ?>
                        <option value="<?= e($vendor) ?>" <?= $value('vendor', 'yealink') === $vendor ? 'selected' : '' ?>><?= e($vendor) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">Modelo
                <input name="model" value="<?= e($value('model')) ?>" placeholder="T54W, GRP2614, V65...">
            </label>

            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active', 'inactive'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $value('status', 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="field">Contenido
                <textarea name="content" rows="18" spellcheck="false"><?= e($value('content')) ?></textarea>
                <small>Variables: {{sip_server}}, {{sip_username}}, {{auth_username}}, {{auth_password}}, {{extension}}, {{display_name}}, {{mac}}, {{blf_json}}</small>
            </label>
        </div>
    </section>
</form>
