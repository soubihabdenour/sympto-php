<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Settings.title');
$provider = llm_provider();
$providerLabel = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini'][$provider];
$llmConfigured = llm_enabled();
$model = llm_model();
$webSearch = strtolower((string) env('ENABLE_WEB_SEARCH', 'false')) === 'true';

$rows = [
    ['icon' => 'user',    'label' => t('Settings.name'),       'value' => $doctor['full_name']],
    ['icon' => 'mail',    'label' => t('Settings.email'),      'value' => $doctor['email']],
    ['icon' => 'id-card', 'label' => t('Settings.licenseId'),  'value' => $doctor['license_id'] ?? '—'],
    ['icon' => 'stethoscope', 'label' => t('Settings.specialty'), 'value' => $doctor['specialty'] ?? '—'],
    ['icon' => 'shield',  'label' => t('Settings.role'),       'value' => $doctor['role']],
];
ob_start();
?>
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('settings', 'w-5 h-5 sm:w-6 sm:h-6 text-brand-700 shrink-0') ?>
            <?= h(t('Settings.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1"><?= h(t('Settings.sub')) ?></p>
    </div>

    <div class="card p-5">
        <h2 class="section-title mb-4">
            <?= icon('user', 'w-4 h-4 text-brand-700') ?>
            <?= h(t('Settings.account')) ?>
        </h2>
        <dl class="divide-y divide-ink-100">
            <?php foreach ($rows as $r): ?>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-3 py-2.5 sm:items-center text-sm">
                    <dt class="text-ink-500 flex items-center gap-2">
                        <?= icon($r['icon'], 'w-4 h-4 text-ink-400') ?>
                        <?= h($r['label']) ?>
                    </dt>
                    <dd class="sm:col-span-2 text-ink-900 font-medium break-words"><?= h($r['value']) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </div>

    <div class="card p-5">
        <h2 class="section-title mb-4">
            <?= icon('cpu', 'w-4 h-4 text-brand-700') ?>
            <?= h(t('Settings.system')) ?>
        </h2>
        <dl class="divide-y divide-ink-100">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-3 py-2.5 sm:items-center text-sm">
                <dt class="text-ink-500 flex items-center gap-2">
                    <?= icon('sparkles', 'w-4 h-4 text-ink-400') ?>
                    <?= h(t('Settings.llmProvider')) ?>
                </dt>
                <dd class="sm:col-span-2">
                    <?php if ($llmConfigured): ?>
                        <span class="pill bg-vital-50 text-vital-700">
                            <?= icon('check-circle', 'w-3 h-3') ?>
                            <?= h(t('Settings.configured', ['provider' => $providerLabel, 'model' => $model])) ?>
                        </span>
                    <?php else: ?>
                        <span class="pill bg-amber-50 text-amber-700">
                            <?= icon('alert', 'w-3 h-3') ?>
                            <?= h(t('Settings.notConfigured', ['provider' => $providerLabel])) ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-3 py-2.5 sm:items-center text-sm">
                <dt class="text-ink-500 flex items-center gap-2">
                    <?= icon('search', 'w-4 h-4 text-ink-400') ?>
                    <?= h(t('Settings.webSearch')) ?>
                </dt>
                <dd class="sm:col-span-2">
                    <?php if ($webSearch): ?>
                        <span class="pill bg-vital-50 text-vital-700">
                            <?= icon('check-circle', 'w-3 h-3') ?>
                            <?= h(t('Settings.enabled')) ?>
                        </span>
                    <?php else: ?>
                        <span class="pill bg-ink-100 text-ink-600">
                            <?= icon('info', 'w-3 h-3') ?>
                            <?= h(t('Settings.disabledNote')) ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
        <p class="text-xs text-ink-500 mt-4 leading-relaxed flex items-start gap-2 bg-ink-50 rounded-lg p-3 border border-ink-100">
            <?= icon('info', 'w-4 h-4 mt-0.5 shrink-0 text-ink-400') ?>
            <span><?= h(t('Settings.envHint')) ?></span>
        </p>
    </div>

    <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
