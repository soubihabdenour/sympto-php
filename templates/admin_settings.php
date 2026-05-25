<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('AdminSettings.title');

$providers = [
    'openai' => 'OpenAI',
    'anthropic' => 'Anthropic',
    'gemini' => 'Google Gemini',
];

$source = 'fallback';
foreach ($models as $m) { if ($m['source'] === 'api') { $source = 'api'; break; } }

$flashKindCls = [
    'ok' => 'bg-vital-50 text-vital-700 border-vital-500/30',
    'warn' => 'bg-amber-50 text-amber-800 border-amber-500/30',
];
$flashLabel = [
    'saved' => t('AdminSettings.flash.saved'),
    'saved_unknown' => t('AdminSettings.flash.savedUnknown'),
    'reset' => t('AdminSettings.flash.reset'),
];

ob_start();
?>
<div class="page-shell max-w-4xl">
    <div class="mb-4">
        <a href="/admin" class="btn-ghost"><?= icon('arrow-left', 'w-4 h-4') ?> <?= h(t('Admin.backToList')) ?></a>
    </div>

    <h1 class="text-xl sm:text-2xl font-bold text-ink-900 flex items-center gap-2 mb-1">
        <?= icon('settings', 'w-6 h-6 text-brand-700') ?>
        <?= h(t('AdminSettings.title')) ?>
    </h1>
    <p class="text-sm text-ink-500 mb-5"><?= h(t('AdminSettings.sub')) ?></p>

    <?php if ($flash && isset($flashLabel[$flash['message']])): ?>
        <div class="border rounded-lg px-3 py-2 text-sm mb-4 <?= $flashKindCls[$flash['kind']] ?? $flashKindCls['ok'] ?>">
            <?= h($flashLabel[$flash['message']]) ?>
        </div>
    <?php endif; ?>

    <div class="card p-4 mb-5">
        <div class="flex flex-wrap items-center gap-3">
            <div class="text-sm text-ink-600 mr-1"><?= h(t('AdminSettings.provider')) ?></div>
            <?php foreach ($providers as $pid => $plabel):
                $active = $pid === $selected_provider;
                $isCurrentRuntime = $pid === $active_provider;
                $cls = $active ? 'bg-brand-700 text-white border-brand-700' : 'bg-white text-ink-700 border-ink-200 hover:bg-ink-50';
            ?>
                <a href="/admin/settings?provider=<?= h($pid) ?>"
                   class="inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors <?= $cls ?>">
                    <?= h($plabel) ?>
                    <?php if ($isCurrentRuntime): ?>
                        <span class="pill <?= $active ? 'bg-white/20 text-white' : 'bg-brand-50 text-brand-700' ?>">
                            <?= h(t('AdminSettings.activeProvider')) ?>
                        </span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <p class="text-[12px] text-ink-500 mt-3">
            <?= h(t('AdminSettings.providerHint')) ?>
        </p>
    </div>

    <div class="card p-4 mb-5">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <h2 class="section-title">
                    <?= icon('cpu', 'w-4 h-4 text-ink-500') ?>
                    <?= h(t('AdminSettings.modelPickerTitle', ['provider' => $providers[$selected_provider]])) ?>
                </h2>
                <p class="text-[12px] text-ink-500 mt-1">
                    <?= h(t('AdminSettings.modelPickerSub')) ?>
                </p>
            </div>
            <div class="text-right text-[11px] text-ink-500 shrink-0">
                <div><?= h(t('AdminSettings.currentModel')) ?></div>
                <div class="font-mono text-ink-800"><?= h($current_model) ?></div>
                <?php if ($is_overridden): ?>
                    <span class="pill bg-brand-50 text-brand-700 mt-1"><?= h(t('AdminSettings.overridden')) ?></span>
                <?php else: ?>
                    <span class="pill bg-ink-100 text-ink-600 mt-1"><?= h(t('AdminSettings.fromEnv')) ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-[11px] text-ink-500 mb-2 inline-flex items-center gap-1">
            <?php if ($source === 'api'): ?>
                <?= icon('check-circle', 'w-3.5 h-3.5 text-vital-600') ?>
                <?= h(t('AdminSettings.sourceApi', ['n' => count($models)])) ?>
            <?php else: ?>
                <?= icon('info', 'w-3.5 h-3.5 text-ink-500') ?>
                <?= h(t('AdminSettings.sourceFallback', ['n' => count($models)])) ?>
            <?php endif; ?>
        </div>

        <form method="post" action="/admin/settings" class="space-y-3">
            <?= csrf_field() ?>
            <input type="hidden" name="provider" value="<?= h($selected_provider) ?>">

            <div>
                <label class="label" for="model"><?= h(t('AdminSettings.modelLabel')) ?></label>
                <select name="model" id="model" class="input font-mono">
                    <?php foreach ($models as $m): ?>
                        <option value="<?= h($m['id']) ?>" <?= $m['id'] === $current_model ? 'selected' : '' ?>>
                            <?= h($m['label'] ?: $m['id']) ?><?php if ($m['id'] !== $m['label']): ?> — <?= h($m['id']) ?><?php endif; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php
            // Show the description of the currently-selected model.
            $selectedDescription = null;
            foreach ($models as $m) { if ($m['id'] === $current_model) { $selectedDescription = $m['description']; break; } }
            ?>
            <?php if ($selectedDescription): ?>
                <div class="text-[12px] text-ink-500"><?= h($selectedDescription) ?></div>
            <?php endif; ?>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <button type="submit" name="_action" value="save" class="btn-primary">
                    <?= icon('check', 'w-4 h-4') ?> <?= h(t('AdminSettings.save')) ?>
                </button>
                <?php if ($is_overridden): ?>
                    <button type="submit" name="_action" value="reset" class="btn-secondary"
                            onclick="return confirm('<?= h(t('AdminSettings.resetConfirm')) ?>');">
                        <?= icon('refresh', 'w-4 h-4') ?> <?= h(t('AdminSettings.reset')) ?>
                    </button>
                <?php endif; ?>
            </div>
        </form>

        <details class="mt-5">
            <summary class="text-[12px] text-ink-500 cursor-pointer hover:text-ink-700"><?= h(t('AdminSettings.fullCatalog')) ?></summary>
            <div class="mt-2 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-ink-50 text-ink-500 text-[11px] uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminSettings.col.id')) ?></th>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminSettings.col.label')) ?></th>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminSettings.col.description')) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($models as $m): ?>
                            <tr<?= $m['id'] === $current_model ? ' class="bg-brand-50/40"' : '' ?>>
                                <td class="px-3 py-2 font-mono text-[12px] text-ink-800"><?= h($m['id']) ?></td>
                                <td class="px-3 py-2 text-ink-700"><?= h($m['label']) ?></td>
                                <td class="px-3 py-2 text-ink-500 text-[12px]"><?= h($m['description'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    <div class="card p-4 text-[12px] text-ink-500">
        <p><?= h(t('AdminSettings.note')) ?></p>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
