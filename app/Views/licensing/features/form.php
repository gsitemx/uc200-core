<?php
$source = $old !== [] ? $old : $feature;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($feature['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Feature</span>
                <h3>Datos generales</h3>
            </div>
            <button class="button primary" type="submit">Guardar</button>
        </div>
        <div class="form-grid">
            <label class="field">Nombre
                <input name="name" value="<?= e($value('name')) ?>" required>
                <?php if (! empty($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?>
            </label>
            <label class="field">Slug
                <input name="slug" value="<?= e($value('slug')) ?>">
            </label>
            <label class="field">Modulo
                <input name="module" value="<?= e($value('module', 'core')) ?>" required>
            </label>
            <label class="field">Grupo
                <select name="feature_group_id">
                    <option value="0">Sin grupo</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= e((string) $group['id']) ?>" <?= (int) $value('feature_group_id', '0') === (int) $group['id'] ? 'selected' : '' ?>><?= e($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Descripcion
                <input name="description" value="<?= e($value('description')) ?>">
            </label>
            <label class="field">Estado
                <select name="is_active">
                    <option value="1" <?= $value('is_active', '1') === '1' ? 'selected' : '' ?>>Activa</option>
                    <option value="0" <?= $value('is_active', '1') === '0' ? 'selected' : '' ?>>Inactiva</option>
                </select>
            </label>
        </div>
    </section>
</form>
