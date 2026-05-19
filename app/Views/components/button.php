<?php $tag = ! empty($href) ? 'a' : 'button'; ?>
<?php if ($tag === 'a'): ?>
    <a class="ds-button <?= e($variant ?? 'secondary') ?> <?= e($size ?? 'sm') ?>" href="<?= e((string) $href) ?>"><?= e((string) ($label ?? '')) ?></a>
<?php else: ?>
    <button class="ds-button <?= e($variant ?? 'secondary') ?> <?= e($size ?? 'sm') ?>" type="<?= e($type ?? 'button') ?>"><?= e((string) ($label ?? '')) ?></button>
<?php endif; ?>
