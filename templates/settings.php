<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Settings.title');
$provider = llm_provider();
$providerLabel = ['openai' => 'OpenAI', 'anthropic' => 'Anthropic', 'gemini' => 'Google Gemini'][$provider];
$llmConfigured = llm_enabled();
$model = llm_model();
$webSearch = strtolower((string) env('ENABLE_WEB_SEARCH', 'false')) === 'true';

$myTier = $doctor['tier'] ?? tier_default();
$tierBadgeCls = [
    'free' => 'bg-ink-100 text-ink-700',
    'plus' => 'bg-brand-50 text-brand-800',
    'pro'  => 'bg-vital-50 text-vital-700',
    'max'  => 'bg-gradient-to-r from-brand-700 to-brand-900 text-white',
];

$rows = [
    ['icon' => 'user',    'label' => t('Settings.name'),       'value' => $doctor['full_name']],
    ['icon' => 'mail',    'label' => t('Settings.email'),      'value' => $doctor['email']],
    ['icon' => 'id-card', 'label' => t('Settings.licenseId'),  'value' => $doctor['license_id'] ?? '—'],
    ['icon' => 'stethoscope', 'label' => t('Settings.specialty'), 'value' => $doctor['specialty'] ?? '—'],
    ['icon' => 'shield',  'label' => t('Settings.role'),       'value' => $doctor['role']],
];

$usage ??= ['totals' => ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'est_cost_usd' => 0.0, 'est_cost_known' => true], 'by_model' => [], 'by_action' => [], 'by_day' => [], 'day_labels' => []];
$uTotals = $usage['totals'];
$uIn = (int) $uTotals['input_tokens'];
$uOut = (int) $uTotals['output_tokens'];
$uCalls = (int) $uTotals['calls'];
$uAvg = $uCalls > 0 ? (int) round(($uIn + $uOut) / $uCalls) : 0;
$uCost = (float) $uTotals['est_cost_usd'];
$uCostKnown = (bool) $uTotals['est_cost_known'];

$myLimitStatus = doctor_token_limits_status((int) $doctor['id']);
$myHasLimit = false;
foreach ($myLimitStatus as $s) { if ($s['limit'] !== null) { $myHasLimit = true; break; } }
$limitBarCls = function (?int $pct): string {
    if ($pct === null) return 'bg-ink-300';
    return $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-amber-500' : 'bg-brand-600');
};

$dayLabels = $usage['day_labels'];
$dayIn = []; $dayOut = [];
foreach ($dayLabels as $day) {
    $dayIn[] = (int) ($usage['by_day'][$day]['input_tokens'] ?? 0);
    $dayOut[] = (int) ($usage['by_day'][$day]['output_tokens'] ?? 0);
}
$shortDayLabels = array_map(fn($d) => substr($d, 5), $dayLabels);

$actionLabel = function (string $a): string {
    return match ($a) {
        'message.reply' => t('Admin.action.chat'),
        'report.generate' => t('Admin.action.report'),
        default => $a,
    };
};
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

    <!-- Token usage (current doctor, last 30 days) -->
    <div class="card p-4">
        <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
            <h2 class="section-title">
                <?= icon('activity', 'w-4 h-4 text-brand-700') ?>
                <?= h(t('Settings.usageTitle')) ?>
                <span class="pill ml-1 <?= $tierBadgeCls[$myTier] ?? '' ?>"><?= h(t('Tier.' . $myTier)) ?></span>
            </h2>
            <span class="text-[11px] text-ink-500"><?= h(t('Settings.usageRange', ['days' => 30])) ?></span>
        </div>

        <!-- Rate-limit windows (per minute / day / week) -->
        <div class="rounded-lg border border-ink-200 bg-ink-50 p-3 mb-3">
            <?php if (!$myHasLimit): ?>
                <div class="flex items-center gap-2 text-xs text-ink-600">
                    <?= icon('shield', 'w-3.5 h-3.5 text-ink-400') ?>
                    <span><?= h(t('Settings.limit.none')) ?></span>
                </div>
            <?php else: ?>
                <div class="grid sm:grid-cols-3 gap-3">
                    <?php foreach (['minute', 'day', 'week'] as $win):
                        $s = $myLimitStatus[$win];
                        $limit = $s['limit']; $used = $s['used']; $pct = $s['used_pct'];
                    ?>
                        <div>
                            <div class="flex items-center justify-between text-[10px] uppercase tracking-wide text-ink-500 font-semibold mb-1">
                                <span class="flex items-center gap-1">
                                    <?= icon('clock', 'w-3 h-3 text-ink-400') ?>
                                    <?= h(t('Admin.limit.windowLabel.' . $win)) ?>
                                </span>
                                <?php if ($limit !== null && $pct !== null && $pct >= 100): ?>
                                    <span class="text-red-700 normal-case font-medium"><?= h(t('Settings.limit.exceeded')) ?></span>
                                <?php elseif ($limit !== null && $pct !== null && $pct >= 80): ?>
                                    <span class="text-amber-700 normal-case font-medium"><?= h(t('Settings.limit.warn')) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($limit === null): ?>
                                <div class="text-[11px] text-ink-500"><?= h(t('Admin.limit.unset')) ?></div>
                            <?php else: ?>
                                <div class="text-[11px] text-ink-700 tabular-nums">
                                    <?= number_format($used) ?> / <?= number_format($limit) ?>
                                    <span class="text-ink-500">· <?= $pct ?>%</span>
                                </div>
                                <div class="mt-1 w-full h-1 rounded-full bg-ink-100 overflow-hidden">
                                    <div class="<?= $limitBarCls($pct) ?> h-full transition-all" style="width: <?= $pct ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
            <div class="kpi p-3">
                <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('cpu', 'w-4 h-4') ?></div>
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.llmCalls')) ?></div>
                    <div class="text-lg font-bold text-ink-900 tabular-nums"><?= number_format($uCalls) ?></div>
                </div>
            </div>
            <div class="kpi p-3">
                <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('activity', 'w-4 h-4') ?></div>
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.tokensIn')) ?></div>
                    <div class="text-lg font-bold text-ink-900 tabular-nums"><?= number_format($uIn) ?></div>
                </div>
            </div>
            <div class="kpi p-3">
                <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('sparkles-ai', 'w-4 h-4') ?></div>
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.tokensOut')) ?></div>
                    <div class="text-lg font-bold text-ink-900 tabular-nums"><?= number_format($uOut) ?></div>
                </div>
            </div>
            <div class="kpi p-3">
                <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('activity', 'w-4 h-4') ?></div>
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.avgPerCall')) ?></div>
                    <div class="text-lg font-bold text-ink-900 tabular-nums"><?= number_format($uAvg) ?></div>
                </div>
            </div>
            <div class="kpi p-3">
                <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('sparkles-ai', 'w-4 h-4') ?></div>
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.estCost')) ?></div>
                    <div class="text-lg font-bold text-ink-900 tabular-nums"><?= $uCalls === 0 ? '—' : '$' . number_format($uCost, 4) ?></div>
                    <div class="text-[10px] text-ink-500"><?= h($uCostKnown ? t('Admin.kpi.costApprox') : t('Admin.kpi.costPartial')) ?></div>
                </div>
            </div>
        </div>

        <?php if ($uCalls > 0): ?>
            <div class="relative h-44 mb-4">
                <canvas id="myDailyChart"></canvas>
            </div>

            <div class="grid lg:grid-cols-2 gap-3">
                <?php if (!empty($usage['by_model'])): ?>
                    <div class="card overflow-hidden">
                        <div class="px-3 py-2 border-b border-ink-200 flex items-center gap-1.5 bg-ink-50/50">
                            <?= icon('cpu', 'w-3.5 h-3.5 text-ink-500') ?>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-700"><?= h(t('Admin.byModelTitle')) ?></h3>
                        </div>
                        <table class="min-w-full text-xs">
                            <thead class="bg-ink-50 text-ink-500 text-[10px] uppercase tracking-wide">
                                <tr>
                                    <th class="text-left font-semibold px-3 py-1.5"><?= h(t('Admin.col.model')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.llmCalls')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.tokensIn')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.tokensOut')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.estCost')) ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                <?php foreach ($usage['by_model'] as $modelKey => $u): ?>
                                    <tr>
                                        <td class="px-3 py-1.5 font-mono text-[11px] text-ink-800"><?= h($modelKey) ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= (int) $u['calls'] ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= number_format((int) $u['input_tokens']) ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= number_format((int) $u['output_tokens']) ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= $u['est_cost_known'] ? '$' . number_format((float) $u['est_cost_usd'], 4) : '—' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if (!empty($usage['by_action'])): ?>
                    <div class="card overflow-hidden">
                        <div class="px-3 py-2 border-b border-ink-200 flex items-center gap-1.5 bg-ink-50/50">
                            <?= icon('pulse', 'w-3.5 h-3.5 text-ink-500') ?>
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-ink-700"><?= h(t('Admin.byActionTitle')) ?></h3>
                        </div>
                        <table class="min-w-full text-xs">
                            <thead class="bg-ink-50 text-ink-500 text-[10px] uppercase tracking-wide">
                                <tr>
                                    <th class="text-left font-semibold px-3 py-1.5"><?= h(t('Admin.col.action')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.llmCalls')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.tokensIn')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.tokensOut')) ?></th>
                                    <th class="text-right font-semibold px-3 py-1.5"><?= h(t('Admin.col.avgPerCall')) ?></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink-100">
                                <?php foreach ($usage['by_action'] as $a => $u):
                                    $avg = $u['calls'] > 0 ? (int) round(($u['input_tokens'] + $u['output_tokens']) / $u['calls']) : 0;
                                ?>
                                    <tr>
                                        <td class="px-3 py-1.5 text-ink-800"><?= h($actionLabel($a)) ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= (int) $u['calls'] ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= number_format((int) $u['input_tokens']) ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= number_format((int) $u['output_tokens']) ?></td>
                                        <td class="px-3 py-1.5 text-right font-mono text-ink-800 tabular-nums"><?= number_format($avg) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-ink-500"><?= h(t('Settings.usageEmpty')) ?></p>
        <?php endif; ?>
    </div>

    <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
</div>
<?php if ($uCalls > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  (function () {
    var ctx = document.getElementById('myDailyChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($shortDayLabels) ?>,
        datasets: [
          { label: <?= json_encode(t('Admin.chart.input')) ?>,  data: <?= json_encode($dayIn) ?>,  backgroundColor: '#0e7490', stack: 'tokens', borderRadius: 3 },
          { label: <?= json_encode(t('Admin.chart.output')) ?>, data: <?= json_encode($dayOut) ?>, backgroundColor: '#22d3ee', stack: 'tokens', borderRadius: 3 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } } },
        scales: {
          x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
          y: { stacked: true, beginAtZero: true, ticks: { font: { size: 10 }, callback: function (v) { return v.toLocaleString(); } }, grid: { color: '#e2e8f0' } }
        }
      }
    });
  })();
</script>
<?php endif; ?>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
