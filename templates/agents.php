<?php $title = t('Agents.title'); ob_start(); ?>
<div class="max-w-6xl mx-auto px-6 py-6">
    <div class="mb-5">
        <h1 class="text-xl font-semibold"><?= h(t('Agents.title')) ?></h1>
        <p class="text-sm text-ink-500 mt-0.5"><?= h(t('Agents.sub')) ?></p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach (specialties() as $spec):
            require TEMPLATES_DIR . '/components/agent_card.php';
        endforeach; ?>
    </div>
    <div class="mt-6">
        <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
