<?php
$source = $old !== [] ? $old : $phonebook;
$value = static fn (string $key, string $default = ''): string => (string) ($source[$key] ?? $default);
?>

<form class="form-stack wide-form" method="post" action="<?= e($action) ?>">
    <?= csrf_field() ?>
    <?php if ($mode === 'edit'): ?>
        <input type="hidden" name="id" value="<?= e($phonebook['uuid']) ?>">
    <?php endif; ?>

    <section class="module-panel">
        <div class="section-heading">
            <div>
                <span class="eyebrow">Phonebook</span>
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
            <label class="field">Estado
                <select name="status">
                    <?php foreach (['active', 'inactive'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $value('status', 'active') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">Entries JSON
                <textarea name="entries_json" rows="14"><?= e($value('entries_json', '[{"name":"Soporte","number":"1001"}]')) ?></textarea>
            </label>
        </div>
    </section>
</form>
