<?php
$title = t('Dashboard.title');
$statusLabels = [
    'OPEN' => t('Dashboard.statusOpen'),
    'IN_PROGRESS' => t('Dashboard.statusInProgress'),
    'REPORTED' => t('Dashboard.statusReported'),
    'CLOSED' => t('Dashboard.statusClosed'),
];
$statusColors = [
    'OPEN' => 'bg-ink-100 text-ink-700',
    'IN_PROGRESS' => 'bg-brand-50 text-brand-700',
    'REPORTED' => 'bg-emerald-50 text-emerald-700',
    'CLOSED' => 'bg-ink-100 text-ink-500',
];
ob_start();
?>
<div class="max-w-6xl mx-auto px-6 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold"><?= h(t('Dashboard.title')) ?></h1>
            <p class="text-sm text-ink-500 mt-0.5"><?= h(t('Dashboard.welcome', ['name' => $doctor['full_name']])) ?></p>
        </div>
        <a href="/cases/new" class="btn-primary"><?= h(t('Dashboard.newCase')) ?></a>
    </div>

    <?php if (!$cases): ?>
        <div class="card p-10 text-center">
            <h2 class="font-medium"><?= h(t('Dashboard.emptyTitle')) ?></h2>
            <p class="text-sm text-ink-500 mt-1"><?= h(t('Dashboard.emptyBody')) ?></p>
            <a href="/cases/new" class="btn-primary mt-4 inline-flex"><?= h(t('Dashboard.createCase')) ?></a>
        </div>
    <?php else: ?>
        <div class="grid md:grid-cols-2 gap-3">
            <?php foreach ($cases as $c): $spec = get_specialty($c['specialty_id']); ?>
                <a href="/cases/<?= (int) $c['id'] ?>" class="card p-4 hover:border-brand-200 hover:shadow-md transition-all block">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm text-ink-500"><?= h($spec['specialty'] ?? $c['specialty_id']) ?></div>
                            <div class="font-semibold mt-0.5"><?= h($c['title']) ?></div>
                        </div>
                        <span class="pill <?= $statusColors[$c['status']] ?? 'bg-ink-100 text-ink-700' ?>">
                            <?= h($statusLabels[$c['status']] ?? strtolower(str_replace('_', ' ', $c['status']))) ?>
                        </span>
                    </div>
                    <div class="mt-3 text-xs text-ink-500 flex flex-wrap gap-x-4 gap-y-1">
                        <span><?= h(t('Dashboard.docsCount', ['count' => (int) $c['docs_count']])) ?></span>
                        <span><?= h(t('Dashboard.msgsCount', ['count' => (int) $c['msgs_count']])) ?></span>
                        <span><?= h(t('Dashboard.reportsCount', ['count' => (int) $c['reports_count']])) ?></span>
                    </div>
                    <div class="mt-2 text-[11px] text-ink-400">
                        <?= h(t('Dashboard.updated', ['when' => (new DateTime($c['updated_at']))->format('Y-m-d H:i')])) ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
