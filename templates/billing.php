<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Billing.title');
$tenant ??= null;
$subscription ??= null;
$plans ??= [];
$usage ??= ['doctors' => 0, 'reports' => 0, 'cases' => 0];
$flash ??= null;
$trialDaysLeft = subscription_trial_days_remaining($subscription);

$priceFmt = function (array $p): string {
    if ((int) $p['price_cents'] === 0) return 'Free';
    return '$' . number_format($p['price_cents'] / 100, 0) . '/mo';
};
$pct = function (int $used, $cap): int {
    if ($cap === null || (int) $cap === 0) return 0;
    return min(100, (int) round(($used / (int) $cap) * 100));
};
ob_start();
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('clipboard', 'w-5 h-5 sm:w-6 sm:h-6 text-brand-700 shrink-0') ?>
            <?= h(t('Billing.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1"><?= h(t('Billing.sub', ['org' => $tenant['name'] ?? '—'])) ?></p>
    </div>

    <?php if ($flash): ?>
        <div class="card border-vital-200 bg-vital-50 p-3 text-sm text-vital-700 flex items-start gap-2">
            <?= icon('check-circle', 'w-4 h-4 mt-0.5 shrink-0') ?>
            <span><?= h($flash) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($trialDaysLeft !== null): ?>
        <div class="card border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 flex items-start gap-2">
            <?= icon('clock', 'w-4 h-4 mt-0.5 shrink-0 text-amber-700') ?>
            <span><?= h(t('Billing.trialBanner', ['days' => $trialDaysLeft])) ?></span>
        </div>
    <?php endif; ?>

    <div class="card p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.currentPlan')) ?></div>
                <div class="mt-1 text-2xl font-bold text-ink-900"><?= h($subscription['plan_name'] ?? '—') ?></div>
                <div class="text-sm text-ink-500 mt-0.5">
                    <?= h(t('Billing.status')) ?>:
                    <span class="font-medium text-ink-700"><?= h($subscription['status'] ?? '—') ?></span>
                    <?php if (!empty($subscription['current_period_end'])): ?>
                        · <?= h(t('Billing.renews', ['when' => (new DateTime($subscription['current_period_end']))->format('Y-m-d')])) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($subscription && (int) $subscription['price_cents'] > 0): ?>
                <div class="text-right">
                    <div class="text-2xl font-bold text-ink-900">$<?= number_format($subscription['price_cents'] / 100, 0) ?></div>
                    <div class="text-xs text-ink-500"><?= h(t('Billing.perMonth')) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid sm:grid-cols-3 gap-3">
        <?php $capD = $subscription['max_doctors'] ?? null; ?>
        <div class="card p-4">
            <div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.usageDoctors')) ?></div>
            <div class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $usage['doctors'] ?><?= $capD !== null ? ' <span class="text-base font-normal text-ink-400">/ ' . (int) $capD . '</span>' : '' ?></div>
            <?php if ($capD !== null): ?>
                <div class="mt-2 h-1.5 bg-ink-100 rounded-full overflow-hidden">
                    <div class="h-full bg-brand-500" style="width: <?= $pct((int) $usage['doctors'], $capD) ?>%"></div>
                </div>
            <?php else: ?>
                <div class="mt-2 text-xs text-vital-700 inline-flex items-center gap-1"><?= icon('check', 'w-3 h-3') ?><?= h(t('Billing.unlimited')) ?></div>
            <?php endif; ?>
        </div>
        <?php $capR = $subscription['max_reports_per_month'] ?? null; ?>
        <div class="card p-4">
            <div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.usageReports')) ?></div>
            <div class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $usage['reports'] ?><?= $capR !== null ? ' <span class="text-base font-normal text-ink-400">/ ' . (int) $capR . '</span>' : '' ?></div>
            <?php if ($capR !== null): ?>
                <div class="mt-2 h-1.5 bg-ink-100 rounded-full overflow-hidden">
                    <div class="h-full bg-brand-500" style="width: <?= $pct((int) $usage['reports'], $capR) ?>%"></div>
                </div>
            <?php else: ?>
                <div class="mt-2 text-xs text-vital-700 inline-flex items-center gap-1"><?= icon('check', 'w-3 h-3') ?><?= h(t('Billing.unlimited')) ?></div>
            <?php endif; ?>
        </div>
        <div class="card p-4">
            <div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.usageCases')) ?></div>
            <div class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $usage['cases'] ?></div>
            <div class="mt-2 text-xs text-ink-500"><?= h(t('Billing.totalAllTime')) ?></div>
        </div>
    </div>

    <div>
        <h2 class="section-title mb-3">
            <?= icon('sparkles', 'w-4 h-4 text-brand-700') ?>
            <?= h(t('Billing.choosePlan')) ?>
        </h2>
        <p class="text-xs text-ink-500 mb-3 inline-flex items-center gap-1.5">
            <?= icon('info', 'w-3.5 h-3.5') ?>
            <?= h(t('Billing.stubNotice')) ?>
        </p>
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-3">
            <?php foreach ($plans as $p):
                $features = json_decode((string) $p['features_json'], true) ?: [];
                $isCurrent = ($subscription['plan_id'] ?? null) === $p['id'];
            ?>
                <div class="card p-4 flex flex-col <?= $isCurrent ? 'ring-2 ring-brand-500 border-brand-300' : '' ?>">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-semibold text-ink-900"><?= h($p['name']) ?></div>
                            <div class="text-2xl font-bold text-ink-900 mt-1">
                                <?= h($priceFmt($p)) ?>
                            </div>
                        </div>
                        <?php if ($isCurrent): ?>
                            <span class="pill bg-brand-50 text-brand-700"><?= h(t('Billing.current')) ?></span>
                        <?php endif; ?>
                    </div>
                    <ul class="mt-3 space-y-1.5 text-xs text-ink-700 flex-1">
                        <?php foreach ($features as $f): ?>
                            <li class="flex items-start gap-1.5">
                                <span class="text-brand-600 mt-0.5 shrink-0"><?= icon('check', 'w-3 h-3') ?></span>
                                <span><?= h($f) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (!$isCurrent): ?>
                        <form method="post" action="/billing/plan" class="mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="plan_id" value="<?= h($p['id']) ?>">
                            <button type="submit" class="btn-secondary w-full text-sm"><?= h(t('Billing.switch')) ?></button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
