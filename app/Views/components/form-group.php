<label class="ds-form-group field">
    <span><?= e((string) ($label ?? '')) ?></span>
    <?= $control ?? '' ?>
    <?php if (! empty($error)): ?>
        <small class="field-error"><?= e((string) $error) ?></small>
    <?php endif; ?>
</label>
