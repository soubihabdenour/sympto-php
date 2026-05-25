<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Nav.appName');
ob_start();
?>
<header class="px-4 sm:px-6 py-4 sm:py-5 flex items-center justify-between gap-2 max-w-6xl mx-auto">
    <a href="/" class="flex items-center gap-2 sm:gap-3 min-w-0">
        <span class="brand-logo shrink-0">
            <?= icon('stethoscope', 'w-5 h-5') ?>
        </span>
        <span class="min-w-0">
            <span class="block font-bold text-ink-900 leading-tight truncate"><?= h(t('Nav.appName')) ?></span>
            <span class="hidden sm:block text-[11px] text-ink-500 leading-tight"><?= h(t('Nav.tagline')) ?></span>
        </span>
    </a>
    <div class="flex items-center gap-2 shrink-0">
        <a href="/login" class="btn-secondary px-3 sm:px-4">
            <?= icon('login', 'w-4 h-4') ?>
            <span class="hidden sm:inline"><?= h(t('Nav.signIn')) ?></span>
        </a>
        <a href="/register" class="btn-primary px-3 sm:px-4" aria-label="<?= h(t('Landing.createAccount')) ?>">
            <?= icon('plus', 'w-4 h-4') ?>
            <span class="hidden sm:inline"><?= h(t('Landing.createAccount')) ?></span>
        </a>
    </div>
</header>

<section class="max-w-4xl mx-auto px-4 sm:px-6 pt-10 sm:pt-16 pb-10 sm:pb-12 text-center">
    <span class="pill bg-brand-50 text-brand-800 ring-1 ring-brand-100 mb-5">
        <?= icon('shield-check', 'w-3 h-3') ?>
        <?= h(t('Landing.audience')) ?>
    </span>
    <h1 class="text-3xl sm:text-4xl md:text-5xl font-bold tracking-tight text-ink-900 mt-3 leading-tight"><?= h(t('Landing.headline')) ?></h1>
    <p class="mt-5 sm:mt-6 text-ink-600 max-w-2xl mx-auto leading-relaxed"><?= h(t('Landing.sub')) ?></p>
    <div class="mt-7 sm:mt-8 flex items-center justify-center gap-3 flex-wrap">
        <a href="/register" class="btn-primary w-full sm:w-auto">
            <?= icon('arrow-right', 'w-4 h-4') ?>
            <?= h(t('Landing.getStarted')) ?>
        </a>
        <a href="/login" class="btn-secondary w-full sm:w-auto">
            <?= h(t('Landing.haveAccount')) ?>
        </a>
    </div>
</section>

<?php
$feats = [
    ['icon' => 'agents',     't' => 'feat1Title', 'b' => 'feat1Body', 'tint' => 'bg-brand-50 text-brand-700'],
    ['icon' => 'file-text',  't' => 'feat2Title', 'b' => 'feat2Body', 'tint' => 'bg-vital-50 text-vital-700'],
    ['icon' => 'clipboard',  't' => 'feat3Title', 'b' => 'feat3Body', 'tint' => 'bg-amber-50 text-amber-700'],
];
?>
<section class="max-w-5xl mx-auto px-6 grid md:grid-cols-3 gap-4 pb-14">
    <?php foreach ($feats as $f): ?>
        <div class="card p-5">
            <div class="w-10 h-10 rounded-lg <?= $f['tint'] ?> grid place-items-center">
                <?= icon($f['icon'], 'w-5 h-5') ?>
            </div>
            <h3 class="font-semibold mt-3 text-ink-900"><?= h(t('Landing.' . $f['t'])) ?></h3>
            <p class="text-sm text-ink-600 mt-1 leading-relaxed"><?= h(t('Landing.' . $f['b'])) ?></p>
        </div>
    <?php endforeach; ?>
</section>

<section class="max-w-4xl mx-auto px-6 pb-16">
    <?php require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
</section>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
