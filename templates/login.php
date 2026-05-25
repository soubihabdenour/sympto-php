<?php $title = t('Login.title'); $error ??= null; ob_start(); ?>
<div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <a href="/" class="flex items-center justify-center gap-2 mb-6">
            <div class="w-9 h-9 rounded-lg bg-brand-600 text-white grid place-items-center font-bold">M</div>
            <div>
                <div class="font-semibold"><?= h(t('Nav.appName')) ?></div>
                <div class="text-xs text-ink-500"><?= h(t('Nav.tagline')) ?></div>
            </div>
        </a>
        <div class="card p-6">
            <h1 class="text-lg font-semibold"><?= h(t('Login.title')) ?></h1>
            <p class="text-sm text-ink-500 mt-1"><?= h(t('Login.blurb')) ?></p>
            <form method="post" action="/login" class="mt-5 space-y-4">
                <?= csrf_field() ?>
                <div>
                    <label class="label" for="email"><?= h(t('Login.email')) ?></label>
                    <input id="email" name="email" type="email" required autocomplete="email" class="input">
                </div>
                <div>
                    <label class="label" for="password"><?= h(t('Login.password')) ?></label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="input">
                </div>
                <?php if ($error): ?>
                    <div class="text-sm text-red-600 bg-red-50 border border-red-100 rounded-md p-2"><?= h($error) ?></div>
                <?php endif; ?>
                <button type="submit" class="btn-primary w-full"><?= h(t('Login.submit')) ?></button>
            </form>
            <div class="text-xs text-ink-500 mt-4 text-center">
                <?= h(t('Login.noAccount')) ?>
                <a class="text-brand-700 hover:underline" href="/register"><?= h(t('Login.createOne')) ?></a>
            </div>
            <div class="mt-4 text-[11px] text-ink-400 text-center">
                <?= h(t('Login.demoSeed', ['email' => 'doctor@medagent.local', 'password' => 'medagent123'])) ?>
            </div>
        </div>
        <div class="mt-4">
            <?php $disclaimer_variant = 'card'; require TEMPLATES_DIR . '/components/disclaimer.php'; ?>
        </div>
    </div>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_auth.php'; ?>
