<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Agents.title');

// Tints reused on this page (kept in sync with agent_card.php).
$tints = [
    'general'=>['bg'=>'bg-brand-50','fg'=>'text-brand-700'],
    'cardiology'=>['bg'=>'bg-rose-50','fg'=>'text-rose-700'],
    'neurology'=>['bg'=>'bg-violet-50','fg'=>'text-violet-700'],
    'dermatology'=>['bg'=>'bg-amber-50','fg'=>'text-amber-700'],
    'pediatrics'=>['bg'=>'bg-sky-50','fg'=>'text-sky-700'],
    'oncology'=>['bg'=>'bg-fuchsia-50','fg'=>'text-fuchsia-700'],
    'radiology'=>['bg'=>'bg-indigo-50','fg'=>'text-indigo-700'],
    'emergency'=>['bg'=>'bg-red-50','fg'=>'text-red-700'],
    'infectious'=>['bg'=>'bg-lime-50','fg'=>'text-lime-700'],
    'psychiatry'=>['bg'=>'bg-purple-50','fg'=>'text-purple-700'],
    'endocrinology'=>['bg'=>'bg-teal-50','fg'=>'text-teal-700'],
    'gastro'=>['bg'=>'bg-orange-50','fg'=>'text-orange-700'],
    'pulmonology'=>['bg'=>'bg-cyan-50','fg'=>'text-cyan-700'],
    'nephrology'=>['bg'=>'bg-blue-50','fg'=>'text-blue-700'],
    'obgyn'=>['bg'=>'bg-pink-50','fg'=>'text-pink-700'],
];

// Build searchable haystack server-side so the JS filter doesn't need to parse PHP.
$all = specialties();
$totalCount = count($all);

ob_start();
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-6" x-data="agentsGrid()">
    <div class="mb-5 sm:mb-6">
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('agents', 'w-5 h-5 sm:w-6 sm:h-6 text-brand-700 shrink-0') ?>
            <?= h(t('Agents.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1 max-w-2xl"><?= h(t('Agents.sub')) ?></p>
    </div>

    <div class="mb-5 flex flex-col sm:flex-row sm:items-center gap-3">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                <?= icon('search', 'w-4 h-4') ?>
            </span>
            <input type="search" x-model="q" @input="filter()" class="input input-with-icon" placeholder="<?= h(t('Agents.searchPlaceholder')) ?>">
        </div>
        <div class="text-xs text-ink-500 sm:text-right shrink-0">
            <span x-text="visible"></span> / <?= $totalCount ?>
        </div>
    </div>

    <div id="agents-grid" class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($all as $spec):
            $t = $tints[$spec['id']] ?? ['bg'=>'bg-brand-50','fg'=>'text-brand-700'];
            // Haystack: searchable text concatenated and lowercased on the client.
            $haystack = strtolower(implode(' ', array_filter([
                $spec['name'], $spec['specialty'], $spec['description'],
                implode(' ', $spec['required_context'] ?? []),
                implode(' ', $spec['common_red_flags'] ?? []),
                implode(' ', $spec['validated_tools'] ?? []),
            ])));
            $startHref = '/cases/new?specialty_id=' . urlencode($spec['id']);
        ?>
            <article class="agent-card card flex flex-col" data-haystack="<?= h($haystack) ?>">
                <div class="p-4 flex items-start gap-3 border-b border-ink-100">
                    <div class="w-11 h-11 rounded-lg <?= $t['bg'] ?> <?= $t['fg'] ?> grid place-items-center shrink-0">
                        <?= icon($spec['icon'] ?? 'stethoscope', 'w-6 h-6') ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-semibold leading-tight text-ink-900"><?= h($spec['name']) ?></div>
                        <div class="text-xs text-ink-500 mt-0.5"><?= h($spec['specialty']) ?></div>
                    </div>
                </div>

                <div class="p-4 flex-1 space-y-4">
                    <p class="text-sm text-ink-700 leading-relaxed"><?= h($spec['description']) ?></p>

                    <?php if (!empty($spec['required_context'])): ?>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider font-semibold text-ink-500 flex items-center gap-1.5 mb-1.5">
                            <?= icon('clipboard', 'w-3 h-3') ?>
                            <?= h(t('Agents.checklist')) ?>
                        </div>
                        <ul class="space-y-1 text-xs text-ink-700">
                            <?php foreach (array_slice($spec['required_context'], 0, 4) as $r): ?>
                                <li class="flex items-start gap-1.5">
                                    <span class="text-brand-600 shrink-0 mt-0.5"><?= icon('check', 'w-3 h-3') ?></span>
                                    <span><?= h($r) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($spec['required_context']) > 4): ?>
                                <li class="text-[11px] text-ink-400 pl-4">+<?= count($spec['required_context']) - 4 ?> more</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($spec['common_red_flags'])): ?>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider font-semibold text-red-700 flex items-center gap-1.5 mb-1.5">
                            <?= icon('flag', 'w-3 h-3') ?>
                            <?= h(t('Agents.redFlags')) ?>
                        </div>
                        <ul class="space-y-1 text-xs text-ink-700">
                            <?php foreach (array_slice($spec['common_red_flags'], 0, 3) as $r): ?>
                                <li class="flex items-start gap-1.5">
                                    <span class="w-1 h-1 rounded-full bg-red-500 shrink-0 mt-1.5"></span>
                                    <span><?= h($r) ?></span>
                                </li>
                            <?php endforeach; ?>
                            <?php if (count($spec['common_red_flags']) > 3): ?>
                                <li class="text-[11px] text-ink-400 pl-3">+<?= count($spec['common_red_flags']) - 3 ?> more</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($spec['validated_tools'])): ?>
                    <div>
                        <div class="text-[10px] uppercase tracking-wider font-semibold text-ink-500 flex items-center gap-1.5 mb-1.5">
                            <?= icon('flask', 'w-3 h-3') ?>
                            <?= h(t('Agents.tools')) ?>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice($spec['validated_tools'], 0, 4) as $tool): ?>
                                <span class="pill bg-ink-100 text-ink-700"><?= h($tool) ?></span>
                            <?php endforeach; ?>
                            <?php if (count($spec['validated_tools']) > 4): ?>
                                <span class="pill bg-ink-50 text-ink-500">+<?= count($spec['validated_tools']) - 4 ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="p-3 border-t border-ink-100 bg-ink-50/40 rounded-b-xl">
                    <a href="<?= h($startHref) ?>" class="btn-primary w-full text-sm">
                        <?= icon('plus', 'w-4 h-4') ?>
                        <?= h(t('Agents.startCase')) ?>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div x-show="visible === 0" x-cloak class="card p-10 text-center text-sm text-ink-500 mt-3">
        <div class="w-12 h-12 mx-auto rounded-xl bg-ink-100 text-ink-400 grid place-items-center">
            <?= icon('search', 'w-6 h-6') ?>
        </div>
        <div class="mt-3"><?= h(t('Agents.noResults')) ?></div>
    </div>

    <div class="mt-6">
        <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function agentsGrid() {
    return {
        q: '',
        visible: <?= $totalCount ?>,
        filter() {
            const needle = this.q.trim().toLowerCase();
            const cards = document.querySelectorAll('.agent-card');
            let n = 0;
            cards.forEach(c => {
                const hay = c.getAttribute('data-haystack') || '';
                const match = needle === '' || hay.indexOf(needle) !== -1;
                c.style.display = match ? '' : 'none';
                if (match) n++;
            });
            this.visible = n;
        },
    };
}
</script>
<style>[x-cloak]{display:none!important}</style>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
