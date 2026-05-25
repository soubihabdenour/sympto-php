<?php $title = t('Nav.appName'); ob_start(); ?>
<header class="px-6 py-5 flex items-center justify-between max-w-6xl mx-auto">
    <div class="flex items-center gap-2">
        <div class="w-8 h-8 rounded-lg bg-brand-600 text-white grid place-items-center font-bold">M</div>
        <div class="font-semibold"><?= h(t('Nav.appName')) ?></div>
    </div>
    <div class="flex items-center gap-2">
        <a href="/login" class="btn-secondary"><?= h(t('Nav.signIn')) ?></a>
        <a href="/register" class="btn-primary"><?= h(t('Landing.createAccount')) ?></a>
    </div>
</header>

<section class="max-w-4xl mx-auto px-6 pt-12 pb-10 text-center">
    <span class="pill bg-brand-50 text-brand-700 mb-4"><?= h(t('Landing.audience')) ?></span>
    <h1 class="text-4xl md:text-5xl font-semibold tracking-tight text-ink-900 mt-3"><?= h(t('Landing.headline')) ?></h1>
    <p class="mt-5 text-ink-600 max-w-2xl mx-auto"><?= h(t('Landing.sub')) ?></p>
    <div class="mt-7 flex items-center justify-center gap-3">
        <a href="/register" class="btn-primary"><?= h(t('Landing.getStarted')) ?></a>
        <a href="/login" class="btn-secondary"><?= h(t('Landing.haveAccount')) ?></a>
    </div>
</section>

<section class="max-w-5xl mx-auto px-6 grid md:grid-cols-3 gap-4 pb-12">
    <div class="card p-5">
        <h3 class="font-semibold mb-1"><?= h(t('Landing.feat1Title')) ?></h3>
        <p class="text-sm text-ink-600 leading-relaxed"><?= h(t('Landing.feat1Body')) ?></p>
    </div>
    <div class="card p-5">
        <h3 class="font-semibold mb-1"><?= h(t('Landing.feat2Title')) ?></h3>
        <p class="text-sm text-ink-600 leading-relaxed"><?= h(t('Landing.feat2Body')) ?></p>
    </div>
    <div class="card p-5">
        <h3 class="font-semibold mb-1"><?= h(t('Landing.feat3Title')) ?></h3>
        <p class="text-sm text-ink-600 leading-relaxed"><?= h(t('Landing.feat3Body')) ?></p>
    </div>
</section>

<section class="max-w-4xl mx-auto px-6 pb-16">
    <?php require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
</section>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
