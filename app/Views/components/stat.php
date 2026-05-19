<article class="ds-stat">
    <span><?= e((string) ($label ?? '')) ?></span>
    <strong><?= e((string) ($value ?? '0')) ?></strong>
    <?php if (! empty($hint)): ?>
        <p><?= e((string) $hint) ?></p>
    <?php endif; ?>
</article>
