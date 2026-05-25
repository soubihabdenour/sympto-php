<?php
$title = t('Settings.title');
$provider = llm_provider();
$providerLabel = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini'][$provider];
$llmConfigured = llm_enabled();
$model = llm_model();
$webSearch = strtolower((string) env('ENABLE_WEB_SEARCH', 'false')) === 'true';
ob_start();
?>
<div class="max-w-3xl mx-auto px-6 py-6 space-y-5">
    <div>
        <h1 class="text-xl font-semibold"><?= h(t('Settings.title')) ?></h1>
        <p class="text-sm text-ink-500 mt-0.5"><?= h(t('Settings.sub')) ?></p>
    </div>

    <div class="card p-4">
        <h2 class="section-title mb-3"><?= h(t('Settings.account')) ?></h2>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-ink-500"><?= h(t('Settings.name')) ?></dt><dd class="col-span-2"><?= h($doctor['full_name']) ?></dd>
            <dt class="text-ink-500"><?= h(t('Settings.email')) ?></dt><dd class="col-span-2"><?= h($doctor['email']) ?></dd>
            <dt class="text-ink-500"><?= h(t('Settings.licenseId')) ?></dt><dd class="col-span-2"><?= h($doctor['license_id'] ?? '—') ?></dd>
            <dt class="text-ink-500"><?= h(t('Settings.specialty')) ?></dt><dd class="col-span-2"><?= h($doctor['specialty'] ?? '—') ?></dd>
            <dt class="text-ink-500"><?= h(t('Settings.role')) ?></dt><dd class="col-span-2"><?= h($doctor['role']) ?></dd>
        </dl>
    </div>

    <div class="card p-4">
        <h2 class="section-title mb-3"><?= h(t('Settings.system')) ?></h2>
        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-ink-500"><?= h(t('Settings.llmProvider')) ?></dt>
            <dd class="col-span-2">
                <?php if ($llmConfigured): ?>
                    <span class="pill bg-emerald-50 text-emerald-700"><?= h(t('Settings.configured', ['provider' => $providerLabel, 'model' => $model])) ?></span>
                <?php else: ?>
                    <span class="pill bg-amber-50 text-amber-700"><?= h(t('Settings.notConfigured', ['provider' => $providerLabel])) ?></span>
                <?php endif; ?>
            </dd>
            <dt class="text-ink-500"><?= h(t('Settings.webSearch')) ?></dt>
            <dd class="col-span-2">
                <?php if ($webSearch): ?>
                    <span class="pill bg-emerald-50 text-emerald-700"><?= h(t('Settings.enabled')) ?></span>
                <?php else: ?>
                    <span class="pill bg-ink-100 text-ink-700"><?= h(t('Settings.disabledNote')) ?></span>
                <?php endif; ?>
            </dd>
        </dl>
        <p class="text-xs text-ink-500 mt-3"><?= h(t('Settings.envHint')) ?></p>
    </div>

    <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
