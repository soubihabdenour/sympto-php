<?php
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$items = [
    ['href' => '/dashboard', 'label' => t('Nav.dashboard')],
    ['href' => '/cases/new', 'label' => t('Nav.newCase')],
    ['href' => '/agents', 'label' => t('Nav.agents')],
    ['href' => '/settings', 'label' => t('Nav.settings')],
];
?>
<aside class="w-64 shrink-0 bg-white border-r border-ink-100 flex flex-col h-screen sticky top-0 no-print">
    <div class="px-5 py-5 border-b border-ink-100">
        <a href="/dashboard" class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-brand-600 text-white grid place-items-center font-bold">M</div>
            <div>
                <div class="text-sm font-semibold leading-tight"><?= h(t('Nav.appName')) ?></div>
                <div class="text-[11px] text-ink-500 leading-tight"><?= h(t('Nav.tagline')) ?></div>
            </div>
        </a>
    </div>
    <nav class="flex-1 px-2 py-3 space-y-1">
        <?php foreach ($items as $it):
            $active = $path === $it['href'] || ($it['href'] !== '/dashboard' && str_starts_with($path, $it['href']));
            $cls = $active ? 'bg-brand-50 text-brand-700 font-medium' : 'text-ink-700 hover:bg-ink-50';
        ?>
            <a href="<?= h($it['href']) ?>" class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors <?= $cls ?>">
                <?= h($it['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="border-t border-ink-100 px-3 py-3 space-y-3">
        <div class="px-2">
            <?php require TEMPLATES_DIR . '/components/locale_switcher.php'; ?>
        </div>
        <?php $d = current_doctor(); if ($d): ?>
            <div class="space-y-2">
                <div class="px-2">
                    <div class="text-sm font-medium truncate"><?= h($d['full_name']) ?></div>
                    <div class="text-xs text-ink-500 truncate"><?= h($d['email']) ?></div>
                </div>
                <form method="post" action="/logout" class="px-1">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-ghost w-full justify-start"><?= h(t('Nav.signOut')) ?></button>
                </form>
            </div>
        <?php else: ?>
            <a href="/login" class="btn-secondary w-full justify-center"><?= h(t('Nav.signIn')) ?></a>
        <?php endif; ?>
    </div>
</aside>
