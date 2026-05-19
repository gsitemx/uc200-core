<div class="ds-modal <?= e($class ?? '') ?>" role="dialog" aria-modal="true" aria-label="<?= e((string) ($title ?? 'Modal')) ?>">
    <?php if (! empty($title)): ?>
        <div class="section-heading">
            <h3><?= e((string) $title) ?></h3>
        </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
</div>
