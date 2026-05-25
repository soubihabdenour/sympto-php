<?php
require_once TEMPLATES_DIR . '/components/icons.php';
// $spec, $selected (bool), $select_form_name (string|null)
$selected = $selected ?? false;
$selectFormName = $select_form_name ?? null;

// Tint per specialty group — purely cosmetic, keeps the grid visually scannable.
$tints = [
    'general'       => ['bg' => 'bg-brand-50',    'fg' => 'text-brand-700'],
    'cardiology'    => ['bg' => 'bg-rose-50',     'fg' => 'text-rose-700'],
    'neurology'     => ['bg' => 'bg-violet-50',   'fg' => 'text-violet-700'],
    'dermatology'   => ['bg' => 'bg-amber-50',    'fg' => 'text-amber-700'],
    'pediatrics'    => ['bg' => 'bg-sky-50',      'fg' => 'text-sky-700'],
    'oncology'      => ['bg' => 'bg-fuchsia-50',  'fg' => 'text-fuchsia-700'],
    'radiology'     => ['bg' => 'bg-indigo-50',   'fg' => 'text-indigo-700'],
    'emergency'     => ['bg' => 'bg-red-50',      'fg' => 'text-red-700'],
    'infectious'    => ['bg' => 'bg-lime-50',     'fg' => 'text-lime-700'],
    'psychiatry'    => ['bg' => 'bg-purple-50',   'fg' => 'text-purple-700'],
    'endocrinology' => ['bg' => 'bg-teal-50',     'fg' => 'text-teal-700'],
    'gastro'        => ['bg' => 'bg-orange-50',   'fg' => 'text-orange-700'],
    'pulmonology'   => ['bg' => 'bg-cyan-50',     'fg' => 'text-cyan-700'],
    'nephrology'    => ['bg' => 'bg-blue-50',     'fg' => 'text-blue-700'],
    'obgyn'         => ['bg' => 'bg-pink-50',     'fg' => 'text-pink-700'],
];
$t = $tints[$spec['id']] ?? ['bg' => 'bg-brand-50', 'fg' => 'text-brand-700'];
$ring = $selected ? 'ring-2 ring-brand-500 border-brand-300' : '';
?>
<?php if ($selectFormName): ?>
    <label class="card card-hover cursor-pointer block <?= $ring ?> relative">
        <input type="radio" name="<?= h($selectFormName) ?>" value="<?= h($spec['id']) ?>" class="sr-only peer" <?= $selected ? 'checked' : '' ?>>
        <div class="p-4">
            <div class="flex items-start gap-3">
                <div class="w-11 h-11 rounded-lg <?= $t['bg'] ?> <?= $t['fg'] ?> grid place-items-center shrink-0">
                    <?= icon($spec['icon'] ?? 'stethoscope', 'w-6 h-6') ?>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold leading-tight text-ink-900"><?= h($spec['name']) ?></div>
                    <div class="text-xs text-ink-500 mt-0.5"><?= h($spec['specialty']) ?></div>
                </div>
                <?php if ($selected): ?>
                    <span class="text-brand-700 shrink-0"><?= icon('check-circle', 'w-5 h-5') ?></span>
                <?php endif; ?>
            </div>
            <p class="text-sm text-ink-600 mt-3 leading-relaxed"><?= h($spec['description']) ?></p>
        </div>
    </label>
<?php else: ?>
    <div class="card <?= $ring ?>">
        <div class="p-4">
            <div class="flex items-start gap-3">
                <div class="w-11 h-11 rounded-lg <?= $t['bg'] ?> <?= $t['fg'] ?> grid place-items-center shrink-0">
                    <?= icon($spec['icon'] ?? 'stethoscope', 'w-6 h-6') ?>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold leading-tight text-ink-900"><?= h($spec['name']) ?></div>
                    <div class="text-xs text-ink-500 mt-0.5"><?= h($spec['specialty']) ?></div>
                </div>
            </div>
            <p class="text-sm text-ink-600 mt-3 leading-relaxed"><?= h($spec['description']) ?></p>
        </div>
    </div>
<?php endif; ?>
