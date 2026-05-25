<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('AdminTenants.title');
$tenants ??= [];
$totals ??= ['tenants' => 0, 'doctors' => 0, 'cases' => 0, 'reports' => 0];
$statusPill = [
    'active' => 'bg-vital-50 text-vital-700',
    'suspended' => 'bg-red-50 text-red-700',
];
$subPill = [
    'active' => 'bg-vital-50 text-vital-700',
    'trial' => 'bg-amber-50 text-amber-700',
    'past_due' => 'bg-red-50 text-red-700',
    'canceled' => 'bg-ink-100 text-ink-500',
];
ob_start();
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('shield-check', 'w-5 h-5 sm:w-6 sm:h-6 text-fuchsia-700 shrink-0') ?>
            <?= h(t('AdminTenants.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1"><?= h(t('AdminTenants.sub')) ?></p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="kpi"><div class="kpi-icon bg-fuchsia-50 text-fuchsia-700"><?= icon('folder', 'w-5 h-5') ?></div>
            <div><div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('AdminTenants.kpiTenants')) ?></div><div class="text-xl font-bold text-ink-900"><?= (int) $totals['tenants'] ?></div></div>
        </div>
        <div class="kpi"><div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('users', 'w-5 h-5') ?></div>
            <div><div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('AdminTenants.kpiDoctors')) ?></div><div class="text-xl font-bold text-ink-900"><?= (int) $totals['doctors'] ?></div></div>
        </div>
        <div class="kpi"><div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('clipboard', 'w-5 h-5') ?></div>
            <div><div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('AdminTenants.kpiCases')) ?></div><div class="text-xl font-bold text-ink-900"><?= (int) $totals['cases'] ?></div></div>
        </div>
        <div class="kpi"><div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('sparkles', 'w-5 h-5') ?></div>
            <div><div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h(t('AdminTenants.kpiReports')) ?></div><div class="text-xl font-bold text-ink-900"><?= (int) $totals['reports'] ?></div></div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-3 border-b border-ink-200">
            <h2 class="section-title">
                <?= icon('folder', 'w-4 h-4 text-fuchsia-700') ?>
                <?= h(t('AdminTenants.allTenants')) ?>
            </h2>
        </div>
        <?php if (!$tenants): ?>
            <div class="p-10 text-center text-sm text-ink-500"><?= h(t('AdminTenants.empty')) ?></div>
        <?php else: ?>
            <ul class="divide-y divide-ink-100">
                <?php foreach ($tenants as $t): ?>
                    <li class="px-5 py-3 flex flex-wrap items-center gap-3 justify-between">
                        <div class="min-w-0">
                            <a href="/admin/tenants/<?= (int) $t['id'] ?>" class="text-sm font-semibold text-ink-900 hover:text-brand-700 inline-flex items-center gap-1.5">
                                <?= h($t['name']) ?>
                                <?= icon('chevron-right', 'w-3.5 h-3.5 text-ink-400') ?>
                            </a>
                            <div class="text-xs text-ink-500 mt-0.5">
                                <span class="font-mono"><?= h($t['slug']) ?></span> ·
                                <?= h(t('AdminTenants.created')) ?> <?= h((new DateTime($t['created_at']))->format('Y-m-d')) ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="pill <?= $statusPill[$t['status']] ?? 'bg-ink-100 text-ink-700' ?>"><?= h($t['status']) ?></span>
                            <?php if (!empty($t['plan_id'])): ?>
                                <span class="pill <?= $subPill[$t['sub_status']] ?? 'bg-ink-100 text-ink-700' ?>"><?= h($t['plan_id']) ?> · <?= h($t['sub_status']) ?></span>
                            <?php else: ?>
                                <span class="pill bg-red-50 text-red-700"><?= h(t('AdminTenants.noSubscription')) ?></span>
                            <?php endif; ?>
                            <span class="text-xs text-ink-500 inline-flex items-center gap-1"><?= icon('users', 'w-3.5 h-3.5') ?><?= (int) $t['doctor_count'] ?></span>
                            <span class="text-xs text-ink-500 inline-flex items-center gap-1"><?= icon('clipboard', 'w-3.5 h-3.5') ?><?= (int) $t['case_count'] ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
