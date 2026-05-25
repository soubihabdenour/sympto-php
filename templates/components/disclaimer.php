<?php $disclaimer_variant ??= 'card'; $text = t('Disclaimer.text'); ?>
<?php if ($disclaimer_variant === 'banner'): ?>
    <div class="bg-amber-50 border-b border-amber-200 text-amber-900 text-xs px-4 py-2"><?= h($text) ?></div>
<?php elseif ($disclaimer_variant === 'inline'): ?>
    <p class="text-xs text-ink-500 leading-relaxed"><?= h($text) ?></p>
<?php else: ?>
    <div class="card border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900"><?= h($text) ?></div>
<?php endif; ?>
