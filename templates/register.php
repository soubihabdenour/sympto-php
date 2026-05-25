<?php $title = t('Register.title'); $error ??= null; ob_start(); ?>
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <a href="/" class="flex items-center justify-center gap-2 mb-6">
            <div class="w-9 h-9 rounded-lg bg-brand-600 text-white grid place-items-center font-bold">M</div>
            <div>
                <div class="font-semibold"><?= h(t('Nav.appName')) ?></div>
                <div class="text-xs text-ink-500"><?= h(t('Nav.tagline')) ?></div>
            </div>
        </a>
        <div class="card p-6">
            <h1 class="text-lg font-semibold"><?= h(t('Register.title')) ?></h1>
            <p class="text-sm text-ink-500 mt-1"><?= h(t('Register.blurb')) ?></p>
            <form method="post" action="/register" class="mt-5 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="label"><?= h(t('Register.fullName')) ?></label>
                    <input name="full_name" required class="input">
                </div>
                <div>
                    <label class="label"><?= h(t('Register.email')) ?></label>
                    <input name="email" type="email" required class="input">
                </div>
                <div>
                    <label class="label"><?= h(t('Register.password')) ?></label>
                    <input name="password" type="password" required minlength="8" class="input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label"><?= h(t('Register.licenseId')) ?></label>
                        <input name="license_id" class="input">
                    </div>
                    <div>
                        <label class="label"><?= h(t('Register.specialty')) ?></label>
                        <input name="specialty" class="input">
                    </div>
                </div>
                <?php if ($error): ?>
                    <div class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-md p-2"><?= h($error) ?></div>
                <?php endif; ?>
                <button type="submit" class="btn-primary w-full"><?= h(t('Register.submit')) ?></button>
            </form>
            <div class="text-xs text-ink-500 mt-4 text-center">
                <?= h(t('Register.haveAccount')) ?>
                <a class="text-brand-700 hover:underline" href="/login"><?= h(t('Register.signIn')) ?></a>
            </div>
        </div>
        <div class="mt-4">
            <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
        </div>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
