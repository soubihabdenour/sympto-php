<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Dashboard.title');
$statusLabels = [
    'OPEN' => t('Dashboard.statusOpen'),
    'IN_PROGRESS' => t('Dashboard.statusInProgress'),
    'REPORTED' => t('Dashboard.statusReported'),
    'CLOSED' => t('Dashboard.statusClosed'),
];
$statusStyles = [
    'OPEN'        => ['pill' => 'bg-ink-100 text-ink-700',           'bar' => 'bg-ink-300',     'icon' => 'clock'],
    'IN_PROGRESS' => ['pill' => 'bg-brand-50 text-brand-800',        'bar' => 'bg-brand-500',   'icon' => 'pulse'],
    'REPORTED'    => ['pill' => 'bg-vital-50 text-vital-700',        'bar' => 'bg-vital-500',   'icon' => 'check-circle'],
    'CLOSED'      => ['pill' => 'bg-ink-100 text-ink-500',           'bar' => 'bg-ink-200',     'icon' => 'check'],
];

// KPI counts
$counts = ['OPEN' => 0, 'IN_PROGRESS' => 0, 'REPORTED' => 0, 'CLOSED' => 0];
foreach ($cases as $c) { $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1; }
$totalCases = count($cases);

ob_start();
?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-5 sm:py-6">
    <div class="flex flex-wrap items-start justify-between gap-3 mb-5 sm:mb-6">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900"><?= h(t('Dashboard.title')) ?></h1>
            <p class="text-sm text-ink-500 mt-1 flex items-center gap-1.5 min-w-0">
                <?= icon('user', 'w-4 h-4 text-ink-400 shrink-0') ?>
                <span class="truncate"><?= h(t('Dashboard.welcome', ['name' => $doctor['full_name']])) ?></span>
            </p>
        </div>
        <a href="/cases/new" class="btn-primary hidden sm:inline-flex">
            <?= icon('plus', 'w-4 h-4') ?>
            <?= h(t('Dashboard.newCase')) ?>
        </a>
    </div>

    <?php if ($cases): ?>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
            <div class="kpi">
                <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('folder', 'w-5 h-5') ?></div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-ink-500 font-medium">Total</div>
                    <div class="text-xl font-bold text-ink-900"><?= $totalCases ?></div>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-icon bg-ink-100 text-ink-700"><?= icon('clock', 'w-5 h-5') ?></div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h($statusLabels['OPEN']) ?></div>
                    <div class="text-xl font-bold text-ink-900"><?= (int) ($counts['OPEN'] ?? 0) ?></div>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-icon bg-brand-50 text-brand-700"><?= icon('pulse', 'w-5 h-5') ?></div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h($statusLabels['IN_PROGRESS']) ?></div>
                    <div class="text-xl font-bold text-ink-900"><?= (int) ($counts['IN_PROGRESS'] ?? 0) ?></div>
                </div>
            </div>
            <div class="kpi">
                <div class="kpi-icon bg-vital-50 text-vital-700"><?= icon('check-circle', 'w-5 h-5') ?></div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-ink-500 font-medium"><?= h($statusLabels['REPORTED']) ?></div>
                    <div class="text-xl font-bold text-ink-900"><?= (int) ($counts['REPORTED'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$cases): ?>
        <div class="card p-12 text-center">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-700 grid place-items-center">
                <?= icon('clipboard', 'w-7 h-7') ?>
            </div>
            <h2 class="font-semibold text-lg mt-4 text-ink-900"><?= h(t('Dashboard.emptyTitle')) ?></h2>
            <p class="text-sm text-ink-500 mt-1 max-w-md mx-auto"><?= h(t('Dashboard.emptyBody')) ?></p>
            <a href="/cases/new" class="btn-primary mt-5 inline-flex">
                <?= icon('plus', 'w-4 h-4') ?>
                <?= h(t('Dashboard.createCase')) ?>
            </a>
        </div>
    <?php else: ?>
        <div class="grid md:grid-cols-2 gap-3">
            <?php foreach ($cases as $c):
                $spec = get_specialty($c['specialty_id']);
                $st = $statusStyles[$c['status']] ?? $statusStyles['OPEN'];
            ?>
                <a href="/cases/<?= (int) $c['id'] ?>" class="card card-hover block relative overflow-hidden">
                    <span class="absolute left-0 top-0 bottom-0 w-1 <?= $st['bar'] ?>"></span>
                    <div class="p-4 pl-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 text-xs text-ink-500">
                                    <?= icon($spec['icon'] ?? 'stethoscope', 'w-3.5 h-3.5') ?>
                                    <span class="truncate"><?= h($spec['specialty'] ?? $c['specialty_id']) ?></span>
                                </div>
                                <div class="font-semibold mt-1 text-ink-900 leading-snug"><?= h($c['title']) ?></div>
                            </div>
                            <span class="pill <?= $st['pill'] ?> shrink-0">
                                <?= icon($st['icon'], 'w-3 h-3') ?>
                                <?= h($statusLabels[$c['status']] ?? strtolower(str_replace('_', ' ', $c['status']))) ?>
                            </span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-ink-500">
                            <span class="inline-flex items-center gap-1">
                                <?= icon('file-text', 'w-3.5 h-3.5') ?>
                                <?= h(t('Dashboard.docsCount', ['count' => (int) $c['docs_count']])) ?>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <?= icon('message', 'w-3.5 h-3.5') ?>
                                <?= h(t('Dashboard.msgsCount', ['count' => (int) $c['msgs_count']])) ?>
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <?= icon('clipboard', 'w-3.5 h-3.5') ?>
                                <?= h(t('Dashboard.reportsCount', ['count' => (int) $c['reports_count']])) ?>
                            </span>
                        </div>
                        <div class="mt-2 text-[11px] text-ink-400 inline-flex items-center gap-1">
                            <?= icon('clock', 'w-3 h-3') ?>
                            <?= h(t('Dashboard.updated', ['when' => (new DateTime($c['updated_at']))->format('Y-m-d H:i')])) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
