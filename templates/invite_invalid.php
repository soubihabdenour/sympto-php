<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Invite.invalidTitle');
ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <a href="/" class="flex items-center justify-center gap-3 mb-7">
            <span class="brand-logo"><?= icon('stethoscope', 'w-5 h-5') ?></span>
            <span class="block font-bold text-ink-900 leading-tight"><?= h(t('Nav.appName')) ?></span>
        </a>
        <div class="card p-6 shadow-lift text-center">
            <div class="w-12 h-12 mx-auto rounded-2xl bg-amber-50 text-amber-700 grid place-items-center">
                <?= icon('alert', 'w-6 h-6') ?>
            </div>
            <h1 class="text-lg font-bold text-ink-900 mt-3"><?= h(t('Invite.invalidTitle')) ?></h1>
            <p class="text-sm text-ink-500 mt-2"><?= h(t('Invite.invalidBody')) ?></p>
            <a href="/login" class="btn-secondary mt-5 inline-flex"><?= h(t('Nav.signIn')) ?></a>
        </div>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
