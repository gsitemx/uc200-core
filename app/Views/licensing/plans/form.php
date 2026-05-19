<?php
$source = $old !== [] ? $old : $plan;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($plan['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Plan</span>
                <h3>Datos generales</h3>
            </div>
            <button class="button primary sm" type="submit"><?= e(__('actions.save')) ?></button>
        </div>
        <div class="form-grid">
            <label class="field">Nombre
                <input name="name" value="<?= e($value('name')) ?>" required>
                <?php if (! empty($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?>
            </label>
            <label class="field">Slug
                <input name="slug" value="<?= e($value('slug')) ?>">
            </label>
            <label class="field">Precio
                <input type="number" step="0.01" min="0" name="price" value="<?= e($value('price', '0')) ?>">
            </label>
            <label class="field">Periodo
                <select name="billing_period">
                    <?php foreach (['monthly' => 'Mensual', 'yearly' => 'Anual', 'custom' => 'Personalizado'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('billing_period', 'monthly') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Descripcion
                <input name="description" value="<?= e($value('description')) ?>">
            </label>
            <label class="field">Estado
                <select name="is_active">
                    <option value="1" <?= $value('is_active', '1') === '1' ? 'selected' : '' ?>>Activo</option>
                    <option value="0" <?= $value('is_active', '1') === '0' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </label>
        </div>
    </section>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Features</span>
                <h3>Activacion por plan</h3>
            </div>
        </div>
        <div class="check-grid">
            <?php foreach ($features as $feature): ?>
                <label class="check-row">
                    <input type="checkbox" name="features[]" value="<?= e((string) $feature['id']) ?>" <?= in_array((int) $feature['id'], $enabledFeatures, true) ? 'checked' : '' ?>>
                    <span><strong><?= e($feature['name']) ?></strong><small><?= e($feature['slug']) ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>
</form>
