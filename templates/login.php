<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('Login.title');
$error ??= null;
ob_start();
?>
<div class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <a href="/" class="flex items-center justify-center gap-3 mb-7">
            <span class="brand-logo">
                <?= icon('stethoscope', 'w-5 h-5') ?>
            </span>
            <span>
                <span class="block font-bold text-ink-900 leading-tight"><?= h(t('Nav.appName')) ?></span>
                <span class="block text-xs text-ink-500 leading-tight"><?= h(t('Nav.tagline')) ?></span>
            </span>
        </a>
        <div class="card p-6 shadow-lift">
            <h1 class="text-xl font-bold text-ink-900"><?= h(t('Login.title')) ?></h1>
            <p class="text-sm text-ink-500 mt-1.5"><?= h(t('Login.blurb')) ?></p>
            <form method="post" action="/login" class="mt-6 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="email"><?= h(t('Login.email')) ?></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                            <?= icon('mail', 'w-4 h-4') ?>
                        </span>
                        <input id="email" name="email" type="email" required autocomplete="email" class="input input-with-icon">
                    </div>
                </div>
                <div>
                    <label class="label" for="password"><?= h(t('Login.password')) ?></label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
                            <?= icon('lock', 'w-4 h-4') ?>
                        </span>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="input input-with-icon">
                    </div>
                </div>
                <?php if ($error): ?>
                    <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg p-3 flex items-start gap-2">
                        <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
                        <span><?= h($error) ?></span>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn-primary w-full">
                    <?= icon('login', 'w-4 h-4') ?>
                    <?= h(t('Login.submit')) ?>
                </button>
            </form>
            <div class="text-xs text-ink-500 mt-5 text-center">
                <?= h(t('Login.noAccount')) ?>
                <a class="text-brand-700 hover:text-brand-800 font-semibold hover:underline" href="/register"><?= h(t('Login.createOne')) ?></a>
            </div>
            <div class="mt-4 pt-4 border-t border-ink-100 text-[11px] text-ink-400 text-center font-mono">
                <?= h(t('Login.demoSeed', ['email' => 'doctor@medagent.local', 'password' => 'medagent123'])) ?>
            </div>
        </div>
        <div class="mt-4">
            <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
        </div>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
