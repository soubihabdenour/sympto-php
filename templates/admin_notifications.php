<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = t('AdminNotif.title');

$flashStyles = [
    'ok'    => 'bg-vital-50 text-vital-700 border-vital-500/30',
    'error' => 'bg-red-50 text-red-700 border-red-500/30',
];

ob_start();
?>
<div class="page-shell max-w-4xl">
    <div class="mb-4">
        <a href="/admin" class="btn-ghost"><?= icon('arrow-left', 'w-4 h-4') ?> <?= h(t('Admin.backToList')) ?></a>
    </div>

    <h1 class="text-xl sm:text-2xl font-bold text-ink-900 flex items-center gap-2 mb-1">
        <?= icon('bell', 'w-6 h-6 text-brand-700') ?>
        <?= h(t('AdminNotif.title')) ?>
    </h1>
    <p class="text-sm text-ink-500 mb-5"><?= h(t('AdminNotif.sub')) ?></p>

    <?php if ($flash): ?>
        <?php $cls = $flashStyles[$flash['kind']] ?? $flashStyles['ok']; ?>
        <div class="border rounded-lg px-3 py-2 text-sm mb-4 <?= $cls ?>">
            <?php if ($flash['kind'] === 'ok'): ?>
                <?= h(t('AdminNotif.sentOk', ['n' => (int) ($flash['recipients'] ?? 0)])) ?>
            <?php else: ?>
                <?= h(t('AdminNotif.sentErr', ['err' => $flash['message']])) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$configured): ?>
        <div class="card p-4 mb-5 border-amber-300 bg-amber-50 text-amber-800">
            <div class="flex items-start gap-3">
                <?= icon('alert-triangle', 'w-5 h-5 mt-0.5 text-amber-600 shrink-0') ?>
                <div class="text-sm">
                    <div class="font-semibold mb-1"><?= h(t('AdminNotif.notConfiguredTitle')) ?></div>
                    <p class="leading-relaxed"><?= h(t('AdminNotif.notConfiguredBody')) ?></p>
                    <pre class="mt-3 bg-white/70 border border-amber-200 rounded p-2 text-xs text-amber-900 overflow-x-auto"
>ONESIGNAL_APP_ID="00000000-0000-0000-0000-000000000000"
ONESIGNAL_REST_API_KEY="your_rest_api_key"
APP_PUBLIC_URL="https://your-domain.tld"</pre>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card p-4 mb-5">
        <div class="flex items-start justify-between gap-3 mb-3">
            <div>
                <h2 class="section-title">
                    <?= icon('send', 'w-4 h-4 text-ink-500') ?>
                    <?= h(t('AdminNotif.composeTitle')) ?>
                </h2>
                <p class="text-[12px] text-ink-500 mt-1"><?= h(t('AdminNotif.composeSub')) ?></p>
            </div>
            <div class="text-right text-[11px] text-ink-500 shrink-0">
                <div><?= h(t('AdminNotif.subscribedCount')) ?></div>
                <div class="font-semibold text-ink-800 text-lg"><?= (int) $subscriber_count ?></div>
            </div>
        </div>

        <form method="post" action="/admin/notifications" class="space-y-3"
              onsubmit="return confirm('<?= h(t('AdminNotif.sendConfirm')) ?>');">
            <?= csrf_field() ?>

            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="label" for="audience"><?= h(t('AdminNotif.audience')) ?></label>
                    <select name="audience" id="audience" class="input">
                        <option value="all"><?= h(t('AdminNotif.audienceAll')) ?></option>
                        <option value="doctors"><?= h(t('AdminNotif.audienceDoctors')) ?></option>
                        <option value="admins"><?= h(t('AdminNotif.audienceAdmins')) ?></option>
                    </select>
                </div>
                <div>
                    <label class="label" for="url"><?= h(t('AdminNotif.url')) ?></label>
                    <input type="url" name="url" id="url" class="input" placeholder="https://example.com/path"
                           pattern="https?://.*" maxlength="500">
                </div>
            </div>

            <div>
                <label class="label" for="title"><?= h(t('AdminNotif.titleField')) ?></label>
                <input type="text" name="title" id="title" class="input" required maxlength="120"
                       placeholder="<?= h(t('AdminNotif.titlePlaceholder')) ?>">
                <div class="text-[11px] text-ink-500 mt-1"><?= h(t('AdminNotif.titleHint')) ?></div>
            </div>

            <div>
                <label class="label" for="body"><?= h(t('AdminNotif.body')) ?></label>
                <textarea name="body" id="body" rows="3" class="input" required maxlength="500"
                          placeholder="<?= h(t('AdminNotif.bodyPlaceholder')) ?>"></textarea>
                <div class="text-[11px] text-ink-500 mt-1"><?= h(t('AdminNotif.bodyHint')) ?></div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
                <button type="submit" class="btn-primary" <?= $configured ? '' : 'disabled' ?>>
                    <?= icon('send', 'w-4 h-4') ?> <?= h(t('AdminNotif.sendNow')) ?>
                </button>
                <span class="text-[12px] text-ink-500"><?= h(t('AdminNotif.sendNote')) ?></span>
            </div>
        </form>
    </div>

    <div class="card p-4">
        <h2 class="section-title mb-3">
            <?= icon('clock', 'w-4 h-4 text-ink-500') ?>
            <?= h(t('AdminNotif.recentTitle')) ?>
        </h2>
        <?php if (empty($broadcasts)): ?>
            <div class="text-sm text-ink-500 italic"><?= h(t('AdminNotif.noneYet')) ?></div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-ink-50 text-ink-500 text-[11px] uppercase tracking-wide">
                        <tr>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminNotif.col.when')) ?></th>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminNotif.col.audience')) ?></th>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminNotif.col.message')) ?></th>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminNotif.col.by')) ?></th>
                            <th class="text-right font-semibold px-3 py-2"><?= h(t('AdminNotif.col.recipients')) ?></th>
                            <th class="text-left font-semibold px-3 py-2"><?= h(t('AdminNotif.col.status')) ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-100">
                        <?php foreach ($broadcasts as $b): ?>
                            <tr>
                                <td class="px-3 py-2 text-ink-500 text-[12px] whitespace-nowrap font-mono"><?= h($b['created_at']) ?></td>
                                <td class="px-3 py-2 text-ink-700 text-[12px]"><?= h($b['audience']) ?></td>
                                <td class="px-3 py-2 text-ink-800">
                                    <div class="font-semibold"><?= h($b['title']) ?></div>
                                    <div class="text-ink-500 text-[12px] truncate max-w-md"><?= h($b['body']) ?></div>
                                </td>
                                <td class="px-3 py-2 text-ink-500 text-[12px]"><?= h($b['admin_name'] ?? '—') ?></td>
                                <td class="px-3 py-2 text-right tabular-nums"><?= (int) $b['recipients'] ?></td>
                                <td class="px-3 py-2">
                                    <?php if ($b['error']): ?>
                                        <span class="pill bg-red-50 text-red-700"><?= h(mb_substr($b['error'], 0, 60)) ?></span>
                                    <?php else: ?>
                                        <span class="pill bg-vital-50 text-vital-700"><?= h(t('AdminNotif.statusOk')) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <p class="text-[12px] text-ink-500 mt-4">
        <?= h(t('AdminNotif.privacyNote')) ?>
    </p>
</div>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
