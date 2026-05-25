<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Team.title');
$tenant ??= null;
$subscription ??= null;
$members ??= [];
$invite ??= null;
$error ??= null;
$capDoctors = $subscription['max_doctors'] ?? null;
$usedDoctors = (int) tenant_doctor_count((int) ($tenant['id'] ?? 0));
$rolePill = [
    'ADMIN' => 'bg-brand-50 text-brand-800',
    'DOCTOR' => 'bg-ink-100 text-ink-700',
    'SUPER_ADMIN' => 'bg-fuchsia-50 text-fuchsia-700',
];
ob_start();
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5">
    <div>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('users', 'w-5 h-5 sm:w-6 sm:h-6 text-brand-700 shrink-0') ?>
            <?= h(t('Team.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1"><?= h(t('Team.sub', ['org' => $tenant['name'] ?? '—'])) ?></p>
    </div>

    <?php if ($error): ?>
        <div class="card border-red-200 bg-red-50 p-3 text-sm text-red-700 flex items-start gap-2">
            <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
            <span><?= h($error) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($invite): ?>
        <div class="card border-vital-200 bg-vital-50 p-4">
            <div class="flex items-start gap-2 text-sm text-vital-700">
                <?= icon('mail', 'w-5 h-5 shrink-0 mt-0.5') ?>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold"><?= h(t('Team.inviteCreated', ['email' => $invite['email']])) ?></div>
                    <p class="text-xs text-vital-700/80 mt-1"><?= h(t('Team.inviteShare')) ?></p>
                    <div class="mt-3 flex flex-col sm:flex-row gap-2">
                        <input readonly value="<?= h($invite['url']) ?>" class="input flex-1 font-mono text-xs" onclick="this.select()">
                        <button type="button" class="btn-secondary shrink-0" onclick="navigator.clipboard.writeText('<?= h($invite['url']) ?>').then(()=>{this.innerText='<?= h(t('Team.copied')) ?>'})">
                            <?= icon('paperclip', 'w-4 h-4') ?> <?= h(t('Team.copyLink')) ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card p-5">
        <h2 class="section-title mb-3">
            <?= icon('plus-circle', 'w-4 h-4 text-brand-700') ?>
            <?= h(t('Team.inviteTitle')) ?>
        </h2>
        <?php if ($capDoctors !== null): ?>
            <div class="text-xs text-ink-500 mb-3 inline-flex items-center gap-1.5">
                <?= icon('users', 'w-3.5 h-3.5') ?>
                <?= h(t('Team.seatsUsed', ['used' => $usedDoctors, 'cap' => (int) $capDoctors])) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="/team/invite" class="grid sm:grid-cols-[1fr_auto_auto] gap-3 items-end">
            <?= csrf_field() ?>
            <div>
                <label class="label"><?= h(t('Team.inviteEmail')) ?></label>
                <input name="email" type="email" required class="input">
            </div>
            <div>
                <label class="label"><?= h(t('Team.role')) ?></label>
                <select name="role" class="input">
                    <option value="DOCTOR"><?= h(t('Team.roleDoctor')) ?></option>
                    <option value="ADMIN"><?= h(t('Team.roleAdmin')) ?></option>
                </select>
            </div>
            <button type="submit" class="btn-primary">
                <?= icon('send', 'w-4 h-4') ?>
                <?= h(t('Team.sendInvite')) ?>
            </button>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-3 border-b border-ink-200 flex items-center justify-between">
            <h2 class="section-title">
                <?= icon('users', 'w-4 h-4 text-brand-700') ?>
                <?= h(t('Team.members')) ?>
                <span class="text-ink-500 font-medium">(<?= count($members) ?>)</span>
            </h2>
        </div>
        <ul class="divide-y divide-ink-100">
            <?php foreach ($members as $m):
                $isSelf = (int) $m['id'] === (int) $doctor['id'];
                $isPending = !empty($m['invite_token']);
                $statusPill = !$m['active']
                    ? 'bg-ink-100 text-ink-500'
                    : ($isPending ? 'bg-amber-50 text-amber-700' : 'bg-vital-50 text-vital-700');
                $statusLabel = !$m['active']
                    ? t('Team.statusDisabled')
                    : ($isPending ? t('Team.statusPending') : t('Team.statusActive'));
                $statusIcon = !$m['active'] ? 'x' : ($isPending ? 'clock' : 'check-circle');
            ?>
                <li class="px-5 py-3 flex flex-wrap items-center gap-3 justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-800 grid place-items-center text-sm font-semibold shrink-0">
                            <?= h(strtoupper(substr($m['full_name'] === '(pending)' ? $m['email'] : $m['full_name'], 0, 1))) ?>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-ink-900 truncate flex items-center gap-2">
                                <?= h($m['full_name'] === '(pending)' ? $m['email'] : $m['full_name']) ?>
                                <?php if ($isSelf): ?><span class="pill bg-brand-50 text-brand-700"><?= h(t('Team.you')) ?></span><?php endif; ?>
                            </div>
                            <div class="text-xs text-ink-500 truncate"><?= h($m['email']) ?></div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="pill <?= $rolePill[$m['role']] ?? 'bg-ink-100 text-ink-700' ?>">
                            <?= h($m['role']) ?>
                        </span>
                        <span class="pill <?= $statusPill ?>">
                            <?= icon($statusIcon, 'w-3 h-3') ?>
                            <?= h($statusLabel) ?>
                        </span>
                        <?php if (!$isSelf && !$isPending && $m['active']): ?>
                            <form method="post" action="/team/<?= (int) $m['id'] ?>/role" class="inline-flex">
                                <?= csrf_field() ?>
                                <input type="hidden" name="role" value="<?= $m['role'] === 'ADMIN' ? 'DOCTOR' : 'ADMIN' ?>">
                                <button type="submit" class="btn-ghost text-xs"><?= h(t('Team.makeRole', ['role' => $m['role'] === 'ADMIN' ? t('Team.roleDoctor') : t('Team.roleAdmin')])) ?></button>
                            </form>
                            <form method="post" action="/team/<?= (int) $m['id'] ?>/deactivate" class="inline-flex" onsubmit="return confirm(<?= json_encode(t('Team.confirmDeactivate')) ?>)">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-danger-ghost"><?= icon('x', 'w-4 h-4') ?></button>
                            </form>
                        <?php elseif (!$m['active']): ?>
                            <form method="post" action="/team/<?= (int) $m['id'] ?>/reactivate" class="inline-flex">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-ghost text-xs text-vital-700"><?= h(t('Team.reactivate')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
