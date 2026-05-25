<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Admin.title');

$totalCases = 0; $totalMsgs = 0; $totalReports = 0; $totalIn = 0; $totalOut = 0;
foreach ($doctors as $row) {
    if (($row['role'] ?? '') === 'ADMIN') continue;
    $totalCases += (int) $row['cases_count'];
    $totalMsgs += (int) $row['msgs_count'];
    $totalReports += (int) $row['reports_count'];
    $totalIn += (int) $row['input_tokens'];
    $totalOut += (int) $row['output_tokens'];
}

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
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
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
    </div>

    <div class="card overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 flex items-center gap-2">
            <?= icon('users', 'w-4 h-4 text-ink-500') ?>
            <h2 class="section-title"><?= h(t('Admin.doctorsTitle')) ?></h2>
        </div>
        <?php if (!$doctors): ?>
            <div class="p-6 text-sm text-ink-500"><?= h(t('Admin.emptyDoctors')) ?></div>
        <?php else: ?>
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
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.tokensIn')) ?></th>
                            <th class="text-right font-semibold px-4 py-2.5"><?= h(t('Admin.col.tokensOut')) ?></th>
                            <th class="text-left font-semibold px-4 py-2.5"><?= h(t('Admin.col.lastActive')) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($doctors as $d): ?>
                            <?php $isAdmin = ($d['role'] ?? '') === 'ADMIN'; ?>
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
                                <td class="px-4 py-2.5 text-ink-600">
                                    <?= h($d['specialty'] ?? '—') ?>
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['cases_count'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['msgs_count'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['reports_count'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= (int) $d['llm_calls'] ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format((int) $d['input_tokens']) ?></td>
                                <td class="px-4 py-2.5 text-right font-mono text-ink-800"><?= number_format((int) $d['output_tokens']) ?></td>
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
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
