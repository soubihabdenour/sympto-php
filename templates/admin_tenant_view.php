<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = $tenant['name'] . ' — ' . t('AdminTenants.detailTitle');
$tenant ??= null;
$subscription ??= null;
$plans ??= [];
$members ??= [];
$usage ??= ['doctors' => 0, 'reports' => 0, 'cases' => 0];
$rolePill = [
    'ADMIN' => 'bg-brand-50 text-brand-800',
    'DOCTOR' => 'bg-ink-100 text-ink-700',
];
ob_start();
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5">
    <div>
        <a href="/admin" class="inline-flex items-center gap-1 text-xs text-ink-500 hover:text-ink-800 transition-colors mb-2">
            <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
            <?= h(t('AdminTenants.backToAll')) ?>
        </a>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900"><?= h($tenant['name']) ?></h1>
                <div class="text-xs text-ink-500 mt-1 font-mono"><?= h($tenant['slug']) ?> · <?= h(t('AdminTenants.created')) ?> <?= h((new DateTime($tenant['created_at']))->format('Y-m-d H:i')) ?></div>
            </div>
            <form method="post" action="/admin/tenants/<?= (int) $tenant['id'] ?>/status" class="flex items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= $tenant['status'] === 'active' ? 'suspended' : 'active' ?>">
                <?php if ($tenant['status'] === 'active'): ?>
                    <button type="submit" class="btn-secondary text-red-700 border-red-200 hover:bg-red-50">
                        <?= icon('alert', 'w-4 h-4') ?>
                        <?= h(t('AdminTenants.suspend')) ?>
                    </button>
                <?php else: ?>
                    <button type="submit" class="btn-primary">
                        <?= icon('check', 'w-4 h-4') ?>
                        <?= h(t('AdminTenants.reactivate')) ?>
                    </button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="grid sm:grid-cols-3 gap-3">
        <div class="card p-4"><div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.usageDoctors')) ?></div><div class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $usage['doctors'] ?></div></div>
        <div class="card p-4"><div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.usageReports')) ?></div><div class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $usage['reports'] ?></div></div>
        <div class="card p-4"><div class="text-[11px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Billing.usageCases')) ?></div><div class="mt-1 text-2xl font-bold text-ink-900"><?= (int) $usage['cases'] ?></div></div>
    </div>

    <div class="card p-5">
        <h2 class="section-title mb-3"><?= icon('sparkles', 'w-4 h-4 text-brand-700') ?> <?= h(t('AdminTenants.subscription')) ?></h2>
        <?php if ($subscription): ?>
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <span class="pill bg-brand-50 text-brand-800"><?= h($subscription['plan_name']) ?></span>
                <span class="pill bg-ink-100 text-ink-700"><?= h($subscription['status']) ?></span>
                <?php if (!empty($subscription['current_period_end'])): ?>
                    <span class="text-xs text-ink-500"><?= h(t('Billing.renews', ['when' => (new DateTime($subscription['current_period_end']))->format('Y-m-d')])) ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-red-700"><?= h(t('AdminTenants.noSubscription')) ?></p>
        <?php endif; ?>
        <form method="post" action="/admin/tenants/<?= (int) $tenant['id'] ?>/plan" class="mt-4 grid sm:grid-cols-[1fr_auto] gap-3 items-end">
            <?= csrf_field() ?>
            <div>
                <label class="label"><?= h(t('AdminTenants.changePlan')) ?></label>
                <select name="plan_id" class="input">
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= h($p['id']) ?>" <?= ($subscription['plan_id'] ?? '') === $p['id'] ? 'selected' : '' ?>>
                            <?= h($p['name']) ?> — <?= (int) $p['price_cents'] === 0 ? 'Free' : '$' . number_format($p['price_cents'] / 100, 0) . '/mo' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-primary"><?= icon('check', 'w-4 h-4') ?> <?= h(t('AdminTenants.apply')) ?></button>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-3 border-b border-ink-200">
            <h2 class="section-title"><?= icon('users', 'w-4 h-4 text-brand-700') ?> <?= h(t('Team.members')) ?> (<?= count($members) ?>)</h2>
        </div>
        <ul class="divide-y divide-ink-100">
            <?php foreach ($members as $m): ?>
                <li class="px-5 py-3 flex flex-wrap items-center gap-3 justify-between">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-ink-900 truncate"><?= h($m['full_name'] === '(pending)' ? $m['email'] : $m['full_name']) ?></div>
                        <div class="text-xs text-ink-500 truncate"><?= h($m['email']) ?></div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="pill <?= $rolePill[$m['role']] ?? 'bg-ink-100 text-ink-700' ?>"><?= h($m['role']) ?></span>
                        <span class="pill <?= $m['active'] ? 'bg-vital-50 text-vital-700' : 'bg-ink-100 text-ink-500' ?>"><?= $m['active'] ? h(t('Team.statusActive')) : h(t('Team.statusDisabled')) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
