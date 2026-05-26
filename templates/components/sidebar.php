<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$items = [
    ['href' => '/dashboard',  'label' => t('Nav.dashboard'), 'icon' => 'dashboard'],
    ['href' => '/cases/new',  'label' => t('Nav.newCase'),   'icon' => 'plus-circle'],
    ['href' => '/agents',     'label' => t('Nav.agents'),    'icon' => 'agents'],
    ['href' => '/settings',   'label' => t('Nav.settings'),  'icon' => 'settings'],
];
if (is_admin()) {
    $items[] = ['href' => '/admin', 'label' => t('Nav.admin'), 'icon' => 'shield-check'];
}
?>
<aside id="sidebar"
       class="w-64 shrink-0 bg-white border-r border-ink-200 flex flex-col h-screen no-print
              fixed inset-y-0 left-0 z-50 -translate-x-full transition-transform duration-200 ease-out
              lg:sticky lg:top-0 lg:translate-x-0">
    <div class="px-5 py-4 border-b border-ink-200 flex items-center justify-between gap-2">
        <a href="/dashboard" class="flex items-center gap-3 min-w-0">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white grid place-items-center shadow-sm shrink-0">
                <?= icon('medagent-mark', 'w-6 h-6') ?>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-bold leading-tight text-ink-900 truncate"><?= h(t('Nav.appName')) ?></span>
                <span class="block text-[11px] text-ink-500 leading-tight mt-0.5 truncate"><?= h(t('Nav.tagline')) ?></span>
            </span>
        </a>
        <button type="button" data-drawer-close class="lg:hidden icon-btn -mr-2" aria-label="Close menu">
            <?= icon('x', 'w-5 h-5') ?>
        </button>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <?php foreach ($items as $it):
            $active = $path === $it['href'] || ($it['href'] !== '/dashboard' && str_starts_with($path, $it['href']));
            $cls = $active ? 'nav-item-active' : 'nav-item-idle';
            $iconCls = $active ? 'w-[18px] h-[18px] text-brand-700' : 'w-[18px] h-[18px] text-ink-500';
        ?>
            <a href="<?= h($it['href']) ?>" class="nav-item <?= $cls ?>">
                <?= icon($it['icon'], $iconCls) ?>
                <span><?= h($it['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="border-t border-ink-200 px-3 py-3 space-y-3">
        <div class="flex items-center gap-2 px-2 text-ink-600">
            <?= icon('globe', 'w-4 h-4 text-ink-500') ?>
            <?php require TEMPLATES_DIR . '/components/locale_switcher.php'; ?>
        </div>
        <?php $d = current_doctor(); if ($d):
            $sbStatus = doctor_token_limits_status((int) $d['id']);
            $sbTier = $d['tier'] ?? tier_default();
            $sbTierBadge = [
                'free' => 'bg-ink-100 text-ink-700',
                'plus' => 'bg-brand-50 text-brand-800',
                'pro'  => 'bg-vital-50 text-vital-700',
                'max'  => 'bg-gradient-to-r from-brand-700 to-brand-900 text-white',
            ][$sbTier] ?? '';
            // Pick the tightest (lowest remaining_pct) active window for the battery.
            $sbTightest = null; $sbWindow = null;
            foreach ($sbStatus as $w => $s) {
                if ($s['remaining_pct'] === null) continue;
                if ($sbTightest === null || $s['remaining_pct'] < $sbTightest['remaining_pct']) {
                    $sbTightest = $s;
                    $sbWindow = $w;
                }
            }
            if ($sbTightest !== null) {
                $remPct = $sbTightest['remaining_pct'];
                $battCls = $remPct >= 50 ? 'bg-vital-500' : ($remPct >= 20 ? 'bg-amber-500' : 'bg-red-500');
            } else {
                $remPct = null; $battCls = '';
            }
        ?>
            <div class="rounded-lg bg-ink-50 border border-ink-200 px-3 py-2.5">
                <a href="/settings" class="block group" title="<?= h(t('Nav.profileTitle')) ?>">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-800 grid place-items-center text-sm font-semibold shrink-0">
                            <?= h(strtoupper(substr($d['full_name'] ?? '?', 0, 1))) ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-ink-900 truncate group-hover:text-brand-800"><?= h($d['full_name']) ?></div>
                            <div class="text-[11px] text-ink-500 truncate"><?= h($d['email']) ?></div>
                        </div>
                    </div>
                    <div class="mt-2.5">
                        <div class="flex items-center justify-between text-[10px] text-ink-500 mb-1 uppercase tracking-wide font-medium">
                            <span class="flex items-center gap-1.5">
                                <span class="pill px-1.5 py-0 text-[9px] <?= $sbTierBadge ?>"><?= h(t('Tier.' . $sbTier)) ?></span>
                                <span><?= h($sbWindow === null ? '' : t('Nav.limitWindow.' . $sbWindow)) ?></span>
                            </span>
                            <span class="tabular-nums text-ink-700 font-semibold">
                                <?= $remPct === null ? '∞' : $remPct . '%' ?>
                            </span>
                        </div>
                        <?php if ($remPct === null): ?>
                            <div class="battery">
                                <div class="battery-fill bg-vital-500" style="width: 100%"></div>
                            </div>
                        <?php else: ?>
                            <div class="battery">
                                <div class="battery-fill <?= $battCls ?>" style="width: <?= $remPct ?>%"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </a>
                <form method="post" action="/logout" class="mt-2">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-ghost w-full justify-center text-ink-600 hover:text-ink-900">
                        <?= icon('logout', 'w-4 h-4') ?>
                        <?= h(t('Nav.signOut')) ?>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <a href="/login" class="btn-secondary w-full justify-center">
                <?= icon('login', 'w-4 h-4') ?>
                <?= h(t('Nav.signIn')) ?>
            </a>
        <?php endif; ?>
    </div>
</aside>
<style>
.battery {
    position: relative;
    width: 100%;
    height: 14px;
    border: 1.5px solid #cbd5e1;
    border-radius: 3px;
    background: #fff;
    padding: 1px;
    box-sizing: border-box;
}
.battery::after {
    content: '';
    position: absolute;
    right: -4px;
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 6px;
    background: #cbd5e1;
    border-radius: 0 1px 1px 0;
}
.battery-fill {
    height: 100%;
    border-radius: 1.5px;
    transition: width 0.3s ease, background-color 0.3s ease;
}
</style>
