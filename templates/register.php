<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Register.title');
$error ??= null;
ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <a href="/" class="flex items-center justify-center gap-3 mb-7">
            <span class="brand-logo">
                <?= icon('medagent-mark', 'w-6 h-6') ?>
            </span>
            <span>
                <span class="block font-bold text-ink-900 leading-tight"><?= h(t('Nav.appName')) ?></span>
                <span class="block text-xs text-ink-500 leading-tight"><?= h(t('Nav.tagline')) ?></span>
            </span>
        </a>
        <div class="card p-6 shadow-lift">
            <h1 class="text-xl font-bold text-ink-900"><?= h(t('Register.title')) ?></h1>
            <p class="text-sm text-ink-500 mt-1.5"><?= h(t('Register.blurb')) ?></p>
            <form method="post" action="/register" class="mt-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="label"><?= h(t('Register.fullName')) ?></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                            <?= icon('user', 'w-4 h-4') ?>
                        </span>
                        <input name="full_name" required class="input input-with-icon">
                    </div>
                </div>
                <div>
                    <label class="label"><?= h(t('Register.email')) ?></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                            <?= icon('mail', 'w-4 h-4') ?>
                        </span>
                        <input name="email" type="email" required class="input input-with-icon">
                    </div>
                </div>
                <div>
                    <label class="label"><?= h(t('Register.password')) ?></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                            <?= icon('lock', 'w-4 h-4') ?>
                        </span>
                        <input name="password" type="password" required minlength="8" class="input input-with-icon">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label"><?= h(t('Register.licenseId')) ?></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                                <?= icon('id-card', 'w-4 h-4') ?>
                            </span>
                            <input name="license_id" class="input input-with-icon">
                        </div>
                    </div>
                    <div>
                        <label class="label"><?= h(t('Register.specialty')) ?></label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                                <?= icon('stethoscope', 'w-4 h-4') ?>
                            </span>
                            <input name="specialty" class="input input-with-icon">
                        </div>
                    </div>
                </div>
                <?php if ($error): ?>
                    <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3 flex items-start gap-2">
                        <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
                        <span><?= h($error) ?></span>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn-primary w-full">
                    <?= icon('plus', 'w-4 h-4') ?>
                    <?= h(t('Register.submit')) ?>
                </button>
            </form>
            <div class="text-xs text-ink-500 mt-5 text-center">
                <?= h(t('Register.haveAccount')) ?>
                <a class="text-brand-700 hover:text-brand-800 font-semibold hover:underline" href="/login"><?= h(t('Register.signIn')) ?></a>
            </div>
        </div>
        <div class="mt-4">
            <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
        </div>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
