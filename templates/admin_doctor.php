<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Admin.doctorTitle', ['name' => $target['full_name']]);

$statusLabels = [
    'OPEN' => t('Dashboard.statusOpen'),
    'IN_PROGRESS' => t('Dashboard.statusInProgress'),
    'REPORTED' => t('Dashboard.statusReported'),
    'CLOSED' => t('Dashboard.statusClosed'),
];

$totals = $usage['totals'];
$totalIn = (int) $totals['input_tokens'];
$totalOut = (int) $totals['output_tokens'];
$totalCalls = (int) $totals['calls'];
$avgPerCall = $totalCalls > 0 ? (int) round(($totalIn + $totalOut) / $totalCalls) : 0;
$costKnown = (bool) $totals['est_cost_known'];
$estCost = (float) $totals['est_cost_usd'];

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
<div class="page-shell">
    <div class="mb-4">
        <a href="/admin" class="btn-ghost"><?= icon('arrow-left', 'w-4 h-4') ?> <?= h(t('Admin.backToList')) ?></a>
    </div>

    <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-12 h-12 rounded-full bg-brand-100 text-brand-800 grid place-items-center text-base font-semibold shrink-0">
                <?= h(strtoupper(substr($target['full_name'] ?? '?', 0, 1))) ?>
            </div>
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-ink-900 truncate flex items-center gap-2">
                    <?= h($target['full_name']) ?>
                    <?php if (($target['role'] ?? '') === 'ADMIN'): ?>
                        <span class="pill bg-brand-50 text-brand-800"><?= icon('shield', 'w-3 h-3') ?> ADMIN</span>
                    <?php endif; ?>
                </h1>
                <div class="text-sm text-ink-500 truncate"><?= h($target['email']) ?></div>
                <div class="text-[12px] text-ink-500 mt-0.5">
                    <?php if (!empty($target['specialty'])): ?><?= h($target['specialty']) ?> · <?php endif; ?>
                    <?= h(t('Admin.joined', ['when' => (new DateTime($target['created_at']))->format('Y-m-d')])) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="kpi">
            <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('folder', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.cases')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= count($cases) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('cpu', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.llmCalls')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= $totalCalls ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('activity', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.tokensIn')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= number_format($totalIn) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('sparkles-ai', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.tokensOut')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= number_format($totalOut) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('activity', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.avgPerCall')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= number_format($avgPerCall) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('sparkles-ai', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.estCost')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= $totalCalls === 0 ? '—' : '$' . number_format($estCost, 4) ?></div>
                <div class="text-[11px] text-ink-500 mt-0.5">
                    <?= h($costKnown ? t('Admin.kpi.costApprox') : t('Admin.kpi.costPartial')) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-4 mb-6">
        <h2 class="section-title mb-3">
            <?= icon('activity', 'w-4 h-4 text-ink-500') ?>
            <?= h(t('Admin.chart.dailyTitle', ['days' => 30])) ?>
        </h2>
        <div class="relative h-56">
            <canvas id="doctorDailyChart"></canvas>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <?php if (!empty($usage['by_model'])): ?>
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
                    <?= icon('cpu', 'w-4 h-4 text-ink-500') ?>
                    <h2 class="section-title"><?= h(t('Admin.byModelTitle')) ?></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-ink-50 text-ink-500 text-[11px] uppercase tracking-wide">
                            <tr>
                                <th class="text-left font-semibold px-4 py-2.5"><?= h(t('Admin.col.model')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.llmCalls')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.tokensIn')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.tokensOut')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.estCost')) ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <?php foreach ($usage['by_model'] as $model => $u): ?>
                                <tr>
                                    <td class="px-4 py-2.5 font-mono text-[12px] text-ink-800"><?= h($model) ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $u['calls'] ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format((int) $u['input_tokens']) ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format((int) $u['output_tokens']) ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800">
                                        <?= $u['est_cost_known'] ? '$' . number_format((float) $u['est_cost_usd'], 4) : '—' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($usage['by_action'])): ?>
            <div class="card overflow-hidden">
                <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
                    <?= icon('pulse', 'w-4 h-4 text-ink-500') ?>
                    <h2 class="section-title"><?= h(t('Admin.byActionTitle')) ?></h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-ink-50 text-ink-500 text-[11px] uppercase tracking-wide">
                            <tr>
                                <th class="text-left font-semibold px-4 py-2.5"><?= h(t('Admin.col.action')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.llmCalls')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.tokensIn')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.tokensOut')) ?></th>
                                <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.avgPerCall')) ?></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            <?php foreach ($usage['by_action'] as $action => $u):
                                $avg = $u['calls'] > 0 ? (int) round(($u['input_tokens'] + $u['output_tokens']) / $u['calls']) : 0;
                            ?>
                                <tr>
                                    <td class="px-4 py-2.5 text-ink-800"><?= h($actionLabel($action)) ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $u['calls'] ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format((int) $u['input_tokens']) ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format((int) $u['output_tokens']) ?></td>
                                    <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format($avg) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-4">
        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
                <?= icon('folder', 'w-4 h-4 text-ink-500') ?>
                <h2 class="section-title"><?= h(t('Admin.casesTitle')) ?></h2>
            </div>
            <?php if (!$cases): ?>
                <div class="p-6 text-sm text-ink-500"><?= h(t('Admin.emptyCases')) ?></div>
            <?php else: ?>
                <ul class="divide-y divide-ink-100">
                    <?php foreach ($cases as $c):
                        $spec = localized_specialty($c['specialty_id']);
                    ?>
                        <li class="px-4 py-3 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-1.5 text-[11px] text-ink-500">
                                    <?= icon($spec['icon'] ?? 'stethoscope', 'w-3.5 h-3.5') ?>
                                    <span class="truncate"><?= h($spec['specialty'] ?? $c['specialty_id']) ?></span>
                                </div>
                                <div class="font-semibold text-ink-900 mt-0.5 truncate"><?= h($c['title']) ?></div>
                                <div class="text-[11px] text-ink-500 mt-1 flex flex-wrap gap-x-3 gap-y-1">
                                    <span><?= h(t('Dashboard.docsCount', ['count' => (int) $c['docs_count']])) ?></span>
                                    <span><?= h(t('Dashboard.msgsCount', ['count' => (int) $c['msgs_count']])) ?></span>
                                    <span><?= h(t('Dashboard.reportsCount', ['count' => (int) $c['reports_count']])) ?></span>
                                    <span><?= h((new DateTime($c['updated_at']))->format('Y-m-d H:i')) ?></span>
                                </div>
                            </div>
                            <span class="pill bg-ink-100 text-ink-700 shrink-0"><?= h($statusLabels[$c['status']] ?? $c['status']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
                <?= icon('activity', 'w-4 h-4 text-ink-500') ?>
                <h2 class="section-title"><?= h(t('Admin.activityTitle')) ?></h2>
            </div>
            <?php if (!$logs): ?>
                <div class="p-6 text-sm text-ink-500"><?= h(t('Admin.emptyActivity')) ?></div>
            <?php else: ?>
                <ul class="divide-y divide-ink-100 max-h-[640px] overflow-y-auto">
                    <?php foreach ($logs as $l):
                        $detail = $l['detail'] ? (json_decode((string) $l['detail'], true) ?: null) : null;
                        $usageRow = is_array($detail) && !empty($detail['usage']) && is_array($detail['usage']) ? $detail['usage'] : null;
                    ?>
                        <li class="px-4 py-2.5 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-mono text-[12px] text-ink-800 truncate"><?= h($l['action']) ?></div>
                                <div class="text-[11px] text-ink-500 whitespace-nowrap"><?= h((new DateTime($l['created_at']))->format('Y-m-d H:i')) ?></div>
                            </div>
                            <?php if ($usageRow): ?>
                                <div class="text-[11px] text-ink-500 mt-0.5">
                                    <?= h(($usageRow['provider'] ?? '?') . ':' . ($usageRow['model'] ?? '?')) ?>
                                    · in <?= number_format((int) ($usageRow['input_tokens'] ?? 0)) ?>
                                    · out <?= number_format((int) ($usageRow['output_tokens'] ?? 0)) ?>
                                </div>
                            <?php elseif ($l['case_id']): ?>
                                <div class="text-[11px] text-ink-500 mt-0.5">case #<?= (int) $l['case_id'] ?></div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  (function () {
    var ctx = document.getElementById('doctorDailyChart');
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
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
