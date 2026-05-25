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
?>
<?php if ($selectFormName): ?>
    <label class="relative block cursor-pointer">
        <input type="radio" name="<?= h($selectFormName) ?>" value="<?= h($spec['id']) ?>" class="peer sr-only" <?= $selected ? 'checked' : '' ?>>
        <span class="pointer-events-none absolute top-3 right-3 z-10 w-6 h-6 rounded-full bg-brand-700 text-white grid place-items-center shadow-sm opacity-0 scale-50 transition-all duration-150 peer-checked:opacity-100 peer-checked:scale-100">
            <?= icon('check', 'w-3.5 h-3.5') ?>
        </span>
        <div class="card card-hover p-4 h-full
                    peer-checked:ring-2 peer-checked:ring-brand-500 peer-checked:border-brand-300 peer-checked:bg-brand-50/40
                    peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/60">
            <div class="flex items-start gap-3 pr-6">
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
    </label>
<?php else: ?>
    <div class="card">
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
