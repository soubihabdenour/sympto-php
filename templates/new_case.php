<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('NewCase.title');
$error ??= null;
ob_start();
?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-5 sm:py-6">
    <div class="mb-5 sm:mb-6">
        <a href="/dashboard" class="inline-flex items-center gap-1 text-xs text-ink-500 hover:text-ink-800 transition-colors mb-2">
            <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
            <?= h(t('Case.back')) ?>
        </a>
        <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink-900 flex items-center gap-2">
            <?= icon('plus-circle', 'w-5 h-5 sm:w-6 sm:h-6 text-brand-700 shrink-0') ?>
            <?= h(t('NewCase.title')) ?>
        </h1>
        <p class="text-sm text-ink-500 mt-1"><?= h(t('NewCase.sub')) ?></p>
    </div>
    <form method="post" action="/cases">
        <?= csrf_field() ?>
        <div class="card p-5 mb-6">
            <label class="label" for="title"><?= h(t('NewCase.caseTitle')) ?></label>
            <input id="title" name="title" class="input" placeholder="<?= h(t('NewCase.placeholder')) ?>" value="<?= h($_POST['title'] ?? '') ?>">
            <p class="text-xs text-ink-500 mt-2 flex items-start gap-1.5">
                <?= icon('shield', 'w-3.5 h-3.5 mt-0.5 shrink-0 text-ink-400') ?>
                <span><?= h(t('NewCase.avoidIdentifiers')) ?></span>
            </p>
        </div>

        <div class="mb-3 flex items-center justify-between">
            <h2 class="section-title">
                <?= icon('agents', 'w-4 h-4 text-brand-700') ?>
                <?= h(t('NewCase.chooseAgent')) ?>
            </h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php
            $select_form_name = 'specialty_id';
            // Pre-select priority: POST (form re-render after error) > GET (deep-link from /agents).
            $selectedId = $_POST['specialty_id'] ?? $_GET['specialty_id'] ?? null;
            if ($selectedId !== null && !get_specialty($selectedId)) $selectedId = null;
            foreach (specialties() as $spec):
                $selected = $spec['id'] === $selectedId;
                require TEMPLATES_DIR . '/components/agent_card.php';
            endforeach; ?>
        </div>
        <?php if ($error): ?>
            <div class="mt-4 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-3 flex items-start gap-2">
                <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
                <span><?= h($error) ?></span>
            </div>
        <?php endif; ?>
        <div class="mt-6 flex justify-end">
            <button type="submit" class="btn-primary w-full sm:w-auto">
                <?= icon('plus', 'w-4 h-4') ?>
                <?= h(t('NewCase.submit')) ?>
            </button>
        </div>
    </form>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
