<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$disclaimer_variant ??= 'card';
$text = t('Disclaimer.text');
?>
<?php if ($disclaimer_variant === 'banner'): ?>
    <div class="bg-amber-50 border-b border-amber-200 text-amber-900 px-5 py-2 flex items-center gap-2 text-[12px] leading-snug">
        <?= icon('shield', 'w-4 h-4 shrink-0 text-amber-700') ?>
        <span><?= h($text) ?></span>
    </div>
<?php elseif ($disclaimer_variant === 'inline'): ?>
    <p class="text-xs text-ink-500 leading-relaxed flex items-start gap-2">
        <?= icon('info', 'w-4 h-4 shrink-0 mt-0.5 text-ink-400') ?>
        <span><?= h($text) ?></span>
    </p>
<?php else: ?>
    <div class="card border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900 flex items-start gap-2.5">
        <?= icon('shield', 'w-5 h-5 shrink-0 mt-0.5 text-amber-700') ?>
        <span><?= h($text) ?></span>
    </div>
<?php endif; ?>
