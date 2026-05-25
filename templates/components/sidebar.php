<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$d = current_doctor();
$tenant = $d ? current_tenant($d) : null;
$isAdmin = $d && is_tenant_admin($d);
$isSuper = $d && is_super_admin($d);

// Doctor / Admin items (hidden for super-admin which uses /admin instead)
$items = [];
if ($d && !$isSuper) {
    $items[] = ['href' => '/dashboard',  'label' => t('Nav.dashboard'), 'icon' => 'dashboard'];
    $items[] = ['href' => '/cases/new',  'label' => t('Nav.newCase'),   'icon' => 'plus-circle'];
    $items[] = ['href' => '/agents',     'label' => t('Nav.agents'),    'icon' => 'agents'];
}
if ($isAdmin && !$isSuper) {
    $items[] = ['href' => '/team',    'label' => t('Nav.team'),    'icon' => 'users'];
    $items[] = ['href' => '/billing', 'label' => t('Nav.billing'), 'icon' => 'clipboard'];
}
if ($isSuper) {
    $items[] = ['href' => '/admin',   'label' => t('Nav.adminTenants'), 'icon' => 'shield-check'];
}
if ($d) {
    $items[] = ['href' => '/settings', 'label' => t('Nav.settings'),  'icon' => 'settings'];
}
?>
<aside id="sidebar"
       class="w-64 shrink-0 bg-white border-r border-ink-200 flex flex-col h-screen no-print
              fixed inset-y-0 left-0 z-50 -translate-x-full transition-transform duration-200 ease-out
              lg:sticky lg:top-0 lg:translate-x-0">
    <div class="px-5 py-4 border-b border-ink-200 flex items-center justify-between gap-2">
        <a href="/dashboard" class="flex items-center gap-3 min-w-0">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white grid place-items-center shadow-sm shrink-0">
                <?= icon('stethoscope', 'w-5 h-5') ?>
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

    <?php if ($tenant): ?>
    <div class="px-5 py-3 border-b border-ink-200">
        <div class="text-[10px] uppercase tracking-wider font-semibold text-ink-500"><?= h(t('Nav.organization')) ?></div>
        <div class="mt-0.5 text-sm font-semibold text-ink-900 truncate inline-flex items-center gap-1.5">
            <?= icon('folder', 'w-3.5 h-3.5 text-ink-400') ?>
            <span class="truncate"><?= h($tenant['name']) ?></span>
        </div>
    </div>
    <?php elseif ($isSuper): ?>
    <div class="px-5 py-3 border-b border-ink-200">
        <div class="pill bg-fuchsia-50 text-fuchsia-700">
            <?= icon('shield-check', 'w-3 h-3') ?>
            <?= h(t('Nav.platform')) ?>
        </div>
    </div>
    <?php endif; ?>

    <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
        <?php foreach ($items as $it):
            $active = $path === $it['href'] || ($it['href'] !== '/dashboard' && $it['href'] !== '/admin' && str_starts_with($path, $it['href']));
            // /admin should also light up on /admin/tenants/* but not on /admin (already exact)
            if ($it['href'] === '/admin' && str_starts_with($path, '/admin')) $active = true;
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
        <?php if ($d): ?>
            <div class="rounded-lg bg-ink-50 border border-ink-200 px-3 py-2.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-800 grid place-items-center text-sm font-semibold shrink-0">
                        <?= h(strtoupper(substr($d['full_name'] ?? '?', 0, 1))) ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-ink-900 truncate"><?= h($d['full_name']) ?></div>
                        <div class="text-[11px] text-ink-500 truncate inline-flex items-center gap-1">
                            <?= h($d['role']) ?>
                        </div>
                    </div>
                </div>
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
