<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Admin.title');

$totals = $usage['totals'];
$totalIn = (int) $totals['input_tokens'];
$totalOut = (int) $totals['output_tokens'];
$totalCalls = (int) $totals['calls'];
$avgPerCall = $totalCalls > 0 ? (int) round(($totalIn + $totalOut) / $totalCalls) : 0;
$costKnown = (bool) $totals['est_cost_known'];
$estCost = (float) $totals['est_cost_usd'];

$totalCases = 0; $totalMsgs = 0; $totalReports = 0;
foreach ($doctors as $row) {
    if (($row['role'] ?? '') === 'ADMIN') continue;
    $totalCases += (int) $row['cases_count'];
    $totalMsgs += (int) $row['msgs_count'];
    $totalReports += (int) $row['reports_count'];
}

$dayLabels = $usage['day_labels'];
$dayIn = []; $dayOut = [];
foreach ($dayLabels as $day) {
    $dayIn[] = (int) ($usage['by_day'][$day]['input_tokens'] ?? 0);
    $dayOut[] = (int) ($usage['by_day'][$day]['output_tokens'] ?? 0);
}
$shortDayLabels = array_map(fn($d) => substr($d, 5), $dayLabels); // MM-DD
$peakDay = 0; $peakDate = null;
foreach ($dayLabels as $i => $day) {
    $sum = $dayIn[$i] + $dayOut[$i];
    if ($sum > $peakDay) { $peakDay = $sum; $peakDate = $day; }
}

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
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5 sm:mb-6">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
                <?= icon('shield-check', 'w-6 h-6 text-brand-700') ?>
                <?= h(t('Admin.title')) ?>
            </h1>
            <p class="text-sm text-ink-500 mt-1"><?= h(t('Admin.sub')) ?></p>
        </div>
        <a href="/admin/settings" class="btn-secondary">
            <?= icon('settings', 'w-4 h-4') ?>
            <?= h(t('AdminSettings.linkTitle')) ?>
        </a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <div class="kpi">
            <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('users', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.doctors')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= count(array_filter($doctors, fn($d) => ($d['role'] ?? '') !== 'ADMIN')) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('folder', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.cases')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= $totalCases ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('message', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.messages')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= $totalMsgs ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('clipboard', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.reports')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= $totalReports ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('cpu', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.tokens')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= number_format($totalIn + $totalOut) ?></div>
                <div class="text-[11px] text-ink-500 mt-0.5">
                    <?= h(t('Admin.tokenSplit', ['in' => number_format($totalIn), 'out' => number_format($totalOut)])) ?>
                </div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('activity', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.avgPerCall')) ?></div>
                <div class="text-xl font-bold text-ink-900"><?= number_format($avgPerCall) ?></div>
                <div class="text-[11px] text-ink-500 mt-0.5"><?= h(t('Admin.kpi.over', ['n' => number_format($totalCalls)])) ?></div>
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('sparkles-ai', 'w-5 h-5') ?></div>
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('Admin.kpi.estCost')) ?></div>
                <div class="text-xl font-bold text-ink-900">
                    <?= $totalCalls === 0 ? '—' : '$' . number_format($estCost, 2) ?>
                </div>
                <div class="text-[11px] text-ink-500 mt-0.5">
                    <?= h($costKnown ? t('Admin.kpi.costApprox') : t('Admin.kpi.costPartial')) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-4 mb-6">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="section-title">
                <?= icon('activity', 'w-4 h-4 text-ink-500') ?>
                <?= h(t('Admin.chart.dailyTitle', ['days' => 30])) ?>
            </h2>
            <div class="text-[11px] text-ink-500">
                <?php if ($peakDate): ?>
                    <?= h(t('Admin.chart.peak', ['when' => $peakDate, 'tokens' => number_format($peakDay)])) ?>
                <?php else: ?>
                    <?= h(t('Admin.chart.noData')) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="relative h-64">
            <canvas id="dailyUsageChart"></canvas>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-4 mb-6">
        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
                <?= icon('cpu', 'w-4 h-4 text-ink-500') ?>
                <h2 class="section-title"><?= h(t('Admin.byModelTitle')) ?></h2>
            </div>
            <?php if (!$usage['by_model']): ?>
                <div class="p-6 text-sm text-ink-500"><?= h(t('Admin.emptyUsage')) ?></div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>

        <div class="card overflow-hidden">
            <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
                <?= icon('pulse', 'w-4 h-4 text-ink-500') ?>
                <h2 class="section-title"><?= h(t('Admin.byActionTitle')) ?></h2>
            </div>
            <?php if (!$usage['by_action']): ?>
                <div class="p-6 text-sm text-ink-500"><?= h(t('Admin.emptyUsage')) ?></div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
            <?= icon('users', 'w-4 h-4 text-ink-500') ?>
            <h2 class="section-title"><?= h(t('Admin.doctorsTitle')) ?></h2>
        </div>
        <?php if (!$doctors): ?>
            <div class="p-6 text-sm text-ink-500"><?= h(t('Admin.emptyDoctors')) ?></div>
        <?php else: ?>
            <?php
            $maxDoctorTokens = 0;
            foreach ($doctors as $d) {
                if (($d['role'] ?? '') === 'ADMIN') continue;
                $sum = (int) $d['input_tokens'] + (int) $d['output_tokens'];
                if ($sum > $maxDoctorTokens) $maxDoctorTokens = $sum;
            }
            ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-ink-50 text-ink-500 text-[11px] uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-semibold px-4 py-2.5"><?= h(t('Admin.col.doctor')) ?></th>
                            <th class="text-left font-semibold px-4 py-2.5"><?= h(t('Admin.col.specialty')) ?></th>
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.cases')) ?></th>
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.messages')) ?></th>
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.reports')) ?></th>
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.llmCalls')) ?></th>
                            <th class="text-left font-semibold px-4 py-2.5 min-w-[160px]"><?= h(t('Admin.col.tokenShare')) ?></th>
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.estCost')) ?></th>
                            <th class="text-left font-semibold px-4 py-2.5"><?= h(t('Admin.col.lastActive')) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($doctors as $d):
                            $isAdmin = ($d['role'] ?? '') === 'ADMIN';
                            $tokens = (int) $d['input_tokens'] + (int) $d['output_tokens'];
                            $pct = ($maxDoctorTokens > 0 && !$isAdmin) ? min(100, (int) round($tokens / $maxDoctorTokens * 100)) : 0;
                        ?>
                            <tr class="hover:bg-ink-50/60">
                                <td class="px-4 py-2.5">
                                    <a href="/admin/doctors/<?= (int) $d['id'] ?>" class="flex items-center gap-2.5 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-brand-100 text-brand-800 grid place-items-center text-xs font-semibold shrink-0">
                                            <?= h(strtoupper(substr($d['full_name'] ?? '?', 0, 1))) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="font-semibold text-ink-900 truncate flex items-center gap-2">
                                                <?= h($d['full_name']) ?>
                                                <?php if ($isAdmin): ?>
                                                    <span class="pill bg-brand-50 text-brand-800"><?= icon('shield', 'w-3 h-3') ?> ADMIN</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-[11px] text-ink-500 truncate"><?= h($d['email']) ?></div>
                                        </div>
                                    </a>
                                </td>
                                <td class="px-4 py-2.5 text-ink-600"><?= h($d['specialty'] ?? '—') ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['cases_count'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['msgs_count'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['reports_count'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['llm_calls'] ?></td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="flex-1 h-1.5 rounded-full bg-ink-100 overflow-hidden">
                                            <div class="h-full bg-brand-500" style="width: <?= $pct ?>%"></div>
                                        </div>
                                        <div class="text-[11px] font-mono text-ink-600 w-20 text-right shrink-0"><?= number_format($tokens) ?></div>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800">
                                    <?= $d['llm_calls'] > 0 ? '$' . number_format((float) $d['est_cost_usd'], 4) : '—' ?>
                                </td>
                                <td class="px-4 py-2.5 text-ink-500 text-[11px] whitespace-nowrap">
                                    <?= $d['last_active'] ? h((new DateTime($d['last_active']))->format('Y-m-d H:i')) : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
  (function () {
    var ctx = document.getElementById('dailyUsageChart');
    if (!ctx || typeof Chart === 'undefined') return;
    var labels = <?= json_encode($shortDayLabels) ?>;
    var inputData = <?= json_encode($dayIn) ?>;
    var outputData = <?= json_encode($dayOut) ?>;
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [
          { label: <?= json_encode(t('Admin.chart.input')) ?>,  data: inputData,  backgroundColor: '#0e7490', stack: 'tokens', borderRadius: 3 },
          { label: <?= json_encode(t('Admin.chart.output')) ?>, data: outputData, backgroundColor: '#22d3ee', stack: 'tokens', borderRadius: 3 }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'top', labels: { boxWidth: 10, font: { size: 11 } } },
          tooltip: {
            callbacks: {
              footer: function (items) {
                var total = items.reduce(function (s, i) { return s + i.parsed.y; }, 0);
                return <?= json_encode(t('Admin.chart.totalTooltip')) ?> + ': ' + total.toLocaleString();
              }
            }
          }
        },
        scales: {
          x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
          y: { stacked: true, beginAtZero: true, ticks: { font: { size: 10 }, callback: function (v) { return v.toLocaleString(); } }, grid: { color: '#e2e8f0' } }
        }
      }
    });
  })();
</script>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
