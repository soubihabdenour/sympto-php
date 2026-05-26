<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$disclaimer_variant ??= 'card';
$text = t('Disclaimer.text');
?>
<?php if ($disclaimer_variant === 'banner'): ?>
    <div class="bg-ink-50 border-b border-ink-200 text-ink-600 px-5 py-1.5 flex items-center gap-2 text-[11px] leading-snug no-print">
        <?= icon('shield', 'w-3.5 h-3.5 shrink-0 text-ink-400') ?>
        <span><?= h($text) ?></span>
    </div>
<?php elseif ($disclaimer_variant === 'inline'): ?>
    <p class="text-xs text-ink-500 leading-relaxed flex items-start gap-2">
        <?= icon('info', 'w-4 h-4 shrink-0 mt-0.5 text-ink-400') ?>
        <span><?= h($text) ?></span>
    </p>
<?php else: ?>
    <div class="card border-ink-200 bg-ink-50 p-3 text-xs leading-relaxed text-ink-600 flex items-start gap-2.5">
        <?= icon('shield', 'w-4 h-4 shrink-0 mt-0.5 text-ink-400') ?>
        <span><?= h($text) ?></span>
    </div>
<?php endif; ?>
