<?php
$source = $old !== [] ? $old : $row;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<?php if (! empty($errors['general'])): ?>
    <div class="alert error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($row['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow"><?= e($config['title']) ?></span>
                <h3><?= e($title) ?></h3>
            </div>
            <button class="button primary sm" type="submit"><?= e(__('actions.save')) ?></button>
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
                    <?php if (! empty($errors['company_id'])): ?><small class="field-error"><?= e($errors['company_id']) ?></small><?php endif; ?>
                </label>
            <?php endif; ?>

            <label class="field">Nombre
                <input name="name" value="<?= e($value('name')) ?>" required>
                <?php if (! empty($errors['name'])): ?><small class="field-error"><?= e($errors['name']) ?></small><?php endif; ?>
            </label>

            <?php foreach ($config['fields'] as $field => $meta): ?>
                <label class="field"><?= e($meta['label']) ?>
                    <?php if (isset($meta['options'])): ?>
                        <select name="<?= e($field) ?>">
                            <?php foreach ($meta['options'] as $option): ?>
                                <option value="<?= e($option) ?>" <?= $value($field, (string) ($meta['default'] ?? '')) === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif (($field === 'options_json') || str_ends_with($field, '_sequence') || $field === 'members'): ?>
                        <textarea name="<?= e($field) ?>" rows="4"><?= e($value($field, (string) ($meta['default'] ?? ''))) ?></textarea>
                    <?php else: ?>
                        <input name="<?= e($field) ?>" value="<?= e($value($field, (string) ($meta['default'] ?? ''))) ?>">
                    <?php endif; ?>
                    <?php if (! empty($errors[$field])): ?><small class="field-error"><?= e($errors[$field]) ?></small><?php endif; ?>
                </label>
            <?php endforeach; ?>

            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active' => 'active', 'inactive' => 'inactive'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $value('status', 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
    </section>
</form>
