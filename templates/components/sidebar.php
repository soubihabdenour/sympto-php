<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$items = [
    ['href' => '/dashboard',  'label' => t('Nav.dashboard'), 'icon' => 'dashboard'],
    ['href' => '/cases/new',  'label' => t('Nav.newCase'),   'icon' => 'plus-circle'],
    ['href' => '/agents',     'label' => t('Nav.agents'),    'icon' => 'agents'],
    ['href' => '/settings',   'label' => t('Nav.settings'),  'icon' => 'settings'],
];
?>
<aside class="w-64 shrink-0 bg-white border-r border-ink-200 flex flex-col h-screen sticky top-0 no-print">
    <div class="px-5 py-5 border-b border-ink-200">
        <a href="/dashboard" class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white grid place-items-center shadow-sm">
                <?= icon('stethoscope', 'w-5 h-5') ?>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-bold leading-tight text-ink-900"><?= h(t('Nav.appName')) ?></span>
                <span class="block text-[11px] text-ink-500 leading-tight mt-0.5"><?= h(t('Nav.tagline')) ?></span>
            </span>
        </a>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1">
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
        <?php $d = current_doctor(); if ($d): ?>
            <div class="rounded-lg bg-ink-50 border border-ink-200 px-3 py-2.5">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-full bg-brand-100 text-brand-800 grid place-items-center text-sm font-semibold shrink-0">
                        <?= h(strtoupper(substr($d['full_name'] ?? '?', 0, 1))) ?>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-ink-900 truncate"><?= h($d['full_name']) ?></div>
                        <div class="text-[11px] text-ink-500 truncate"><?= h($d['email']) ?></div>
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
