<?php
// $spec, $selected (bool), $select_form_name (string|null)
$selected = $selected ?? false;
$ring = $selected ? 'ring-2 ring-brand-500 border-brand-300' : '';
$selectFormName = $select_form_name ?? null;
?>
<?php if ($selectFormName): ?>
    <label class="text-left card p-4 transition-all hover:shadow-md hover:border-brand-200 cursor-pointer block <?= $ring ?>">
        <input type="radio" name="<?= h($selectFormName) ?>" value="<?= h($spec['id']) ?>" class="sr-only peer" <?= $selected ? 'checked' : '' ?>>
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-brand-50 text-brand-700 grid place-items-center shrink-0 text-xs font-semibold uppercase"><?= h(substr($spec['specialty'], 0, 3)) ?></div>
            <div class="min-w-0">
                <div class="font-semibold leading-tight"><?= h($spec['name']) ?></div>
                <div class="text-xs text-ink-500 mt-0.5"><?= h($spec['specialty']) ?></div>
                <p class="text-sm text-ink-700 mt-2 leading-snug"><?= h($spec['description']) ?></p>
            </div>
        </div>
    </label>
<?php else: ?>
    <div class="card p-4 <?= $ring ?>">
        <div class="flex items-start gap-3">
            <div class="w-10 h-10 rounded-lg bg-brand-50 text-brand-700 grid place-items-center shrink-0 text-xs font-semibold uppercase"><?= h(substr($spec['specialty'], 0, 3)) ?></div>
            <div class="min-w-0">
                <div class="font-semibold leading-tight"><?= h($spec['name']) ?></div>
                <div class="text-xs text-ink-500 mt-0.5"><?= h($spec['specialty']) ?></div>
                <p class="text-sm text-ink-700 mt-2 leading-snug"><?= h($spec['description']) ?></p>
            </div>
        </div>
    </div>
<?php endif; ?>
