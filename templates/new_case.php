<?php $title = t('NewCase.title'); $error ??= null; ob_start(); ?>
<div class="max-w-5xl mx-auto px-6 py-6">
    <div class="mb-5">
        <h1 class="text-xl font-semibold"><?= h(t('NewCase.title')) ?></h1>
        <p class="text-sm text-ink-500 mt-0.5"><?= h(t('NewCase.sub')) ?></p>
    </div>
    <form method="post" action="/cases">
        <?= csrf_field() ?>
        <div class="card p-4 mb-5">
            <label class="label" for="title"><?= h(t('NewCase.caseTitle')) ?></label>
            <input id="title" name="title" class="input" placeholder="<?= h(t('NewCase.placeholder')) ?>" value="<?= h($_POST['title'] ?? '') ?>">
            <p class="text-xs text-ink-500 mt-2"><?= h(t('NewCase.avoidIdentifiers')) ?></p>
        </div>
        <div class="mb-3 flex items-center justify-between">
            <h2 class="section-title"><?= h(t('NewCase.chooseAgent')) ?></h2>
        </div>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            <?php $select_form_name = 'specialty_id'; $selectedId = $_POST['specialty_id'] ?? null;
            foreach (specialties() as $spec):
                $selected = $spec['id'] === $selectedId;
                require TEMPLATES_DIR . '/components/agent_card.php';
            endforeach; ?>
        </div>
        <?php if ($error): ?>
            <div class="mt-4 text-sm text-red-600 bg-red-50 border border-red-100 rounded-md p-2"><?= h($error) ?></div>
        <?php endif; ?>
        <div class="mt-5 flex justify-end">
            <button type="submit" class="btn-primary"><?= h(t('NewCase.submit')) ?></button>
        </div>
    </form>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
