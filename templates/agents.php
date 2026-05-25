<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Agents.title');
ob_start();
?>
<div class="max-w-6xl mx-auto px-6 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('agents', 'w-6 h-6 text-brand-700') ?>
            <?= h(t('Agents.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1 max-w-2xl"><?= h(t('Agents.sub')) ?></p>
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
