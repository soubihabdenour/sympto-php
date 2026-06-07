<?php
/**
 * Medication reminders panel.
 *
 * Required in scope:
 *   $cid     int    Case id.
 *   $case    array  Case row.
 *   $doctor  array  Current doctor (owner).
 */
$reminders = reminders_for_case($cid);
$now = reminder_now_utc();
$flash = $_SESSION['reminder_flash'] ?? null;
unset($_SESSION['reminder_flash']);

$intervalChoices = [
    0    => t('Reminders.intervalOneShot'),
    240  => t('Reminders.intervalQ4h'),
    360  => t('Reminders.intervalQ6h'),
    480  => t('Reminders.intervalQ8h'),
    720  => t('Reminders.intervalQ12h'),
    1440 => t('Reminders.intervalDaily'),
];

$pushConfigured = function_exists('push_is_configured') && push_is_configured();
?>
<section id="reminders" class="card p-4 sm:p-5 no-print"
         x-data="medReminders('<?= h(t('Reminders.confirmDelete')) ?>')">
    <header class="flex flex-wrap items-start justify-between gap-2 mb-3">
        <div>
            <h2 class="section-title text-base">
                <?= icon('pill', 'w-5 h-5 text-brand-700') ?>
                <?= h(t('Reminders.title')) ?>
            </h2>
            <p class="text-[12px] text-ink-500 mt-0.5"><?= h(t('Reminders.sub')) ?></p>
        </div>
        <button type="button" @click="opening = !opening"
                class="btn-secondary"
                :aria-expanded="opening ? 'true' : 'false'">
            <?= icon('plus', 'w-4 h-4') ?>
            <span x-text="opening ? '<?= h(t('Common.cancel')) ?>' : '<?= h(t('Reminders.addBtn')) ?>'"></span>
        </button>
    </header>

    <?php if (!$pushConfigured): ?>
        <div class="rounded-lg border border-amber-300 bg-amber-50 text-amber-800 px-3 py-2 mb-3 text-[13px] flex items-start gap-2">
            <?= icon('alert-triangle', 'w-4 h-4 mt-0.5 shrink-0 text-amber-600') ?>
            <span><?= h(t('Reminders.pushDisabled')) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <?php if ($flash['kind'] === 'ok'): ?>
            <div class="rounded-lg border border-vital-500/30 bg-vital-50 text-vital-700 px-3 py-2 mb-3 text-[13px]">
                <?= h(t('Reminders.flash.created')) ?>
            </div>
        <?php else: ?>
            <div class="rounded-lg border border-red-500/30 bg-red-50 text-red-700 px-3 py-2 mb-3 text-[13px]">
                <?= h($flash['message']) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Add reminder form (collapsed by default) -->
    <form method="post" action="/cases/<?= $cid ?>/reminders" x-show="opening" x-cloak
          x-transition.duration.150ms class="grid sm:grid-cols-12 gap-3 mb-4 p-3 sm:p-4 rounded-lg bg-ink-50 border border-ink-200"
          @submit="tz.value = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'">
        <?= csrf_field() ?>
        <input type="hidden" name="tz" x-ref="tz">

        <div class="sm:col-span-7">
            <label class="label" for="r_medication"><?= h(t('Reminders.medication')) ?> *</label>
            <input type="text" name="medication" id="r_medication" class="input" required maxlength="120"
                   placeholder="<?= h(t('Reminders.medicationPh')) ?>">
        </div>
        <div class="sm:col-span-5">
            <label class="label" for="r_dosage"><?= h(t('Reminders.dosage')) ?></label>
            <input type="text" name="dosage" id="r_dosage" class="input" maxlength="120"
                   placeholder="<?= h(t('Reminders.dosagePh')) ?>">
        </div>

        <div class="sm:col-span-5">
            <label class="label" for="r_patient_label"><?= h(t('Reminders.patientLabel')) ?></label>
            <input type="text" name="patient_label" id="r_patient_label" class="input" maxlength="80"
                   placeholder="<?= h(t('Reminders.patientLabelPh')) ?>">
            <div class="text-[11px] text-ink-500 mt-1"><?= h(t('Reminders.patientLabelHint')) ?></div>
        </div>
        <div class="sm:col-span-4">
            <label class="label" for="r_start_at"><?= h(t('Reminders.startAt')) ?> *</label>
            <input type="datetime-local" name="start_at" id="r_start_at" class="input" required
                   x-init="$el.value = new Date(Date.now() + 5*60*1000).toISOString().slice(0,16)">
        </div>
        <div class="sm:col-span-3">
            <label class="label" for="r_interval"><?= h(t('Reminders.repeat')) ?></label>
            <select name="repeat_interval_minutes" id="r_interval" class="input"
                    x-model="interval">
                <?php foreach ($intervalChoices as $mins => $label): ?>
                    <option value="<?= (int) $mins ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="sm:col-span-6" x-show="interval !== '0'" x-cloak>
            <label class="label" for="r_until"><?= h(t('Reminders.repeatUntil')) ?></label>
            <input type="datetime-local" name="repeat_until" id="r_until" class="input">
            <div class="text-[11px] text-ink-500 mt-1"><?= h(t('Reminders.repeatUntilHint')) ?></div>
        </div>
        <div class="sm:col-span-12">
            <label class="label" for="r_notes"><?= h(t('Reminders.notes')) ?></label>
            <textarea name="notes" id="r_notes" class="input" maxlength="500" rows="2"
                      placeholder="<?= h(t('Reminders.notesPh')) ?>"></textarea>
        </div>

        <div class="sm:col-span-12 flex flex-wrap items-center gap-2">
            <button type="submit" class="btn-primary">
                <?= icon('bell', 'w-4 h-4') ?>
                <?= h(t('Reminders.create')) ?>
            </button>
            <span class="text-[12px] text-ink-500"><?= h(t('Reminders.privacyNote')) ?></span>
        </div>
    </form>

    <!-- List -->
    <?php if (empty($reminders)): ?>
        <div class="text-sm text-ink-500 italic py-2"><?= h(t('Reminders.empty')) ?></div>
    <?php else: ?>
        <ul class="divide-y divide-ink-100">
            <?php foreach ($reminders as $r):
                $isActive = $r['status'] === 'active';
                $isPaused = $r['status'] === 'paused';
                $isDone   = $r['status'] === 'done';
                $overdue  = $isActive && reminder_is_past($r['next_due_at'], $now);
                $statusPill = $isActive
                    ? ($overdue ? 'bg-red-50 text-red-700' : 'bg-vital-50 text-vital-700')
                    : ($isPaused ? 'bg-amber-50 text-amber-700' : 'bg-ink-100 text-ink-500');
                $statusLabel = $isActive
                    ? ($overdue ? t('Reminders.statusOverdue') : t('Reminders.statusActive'))
                    : ($isPaused ? t('Reminders.statusPaused') : t('Reminders.statusDone'));
                $repeatLabel = (int) $r['repeat_interval_minutes'] > 0
                    ? ($intervalChoices[(int) $r['repeat_interval_minutes']] ?? t('Reminders.intervalCustom'))
                    : t('Reminders.intervalOneShot');
            ?>
                <li class="py-3 flex flex-wrap items-start gap-3">
                    <div class="w-10 h-10 rounded-lg grid place-items-center shrink-0
                                <?= $isActive ? ($overdue ? 'bg-red-50 text-red-700' : 'bg-brand-50 text-brand-700') : 'bg-ink-100 text-ink-500' ?>">
                        <?= icon('pill', 'w-5 h-5') ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-semibold text-ink-900 truncate"><?= h($r['medication']) ?></div>
                            <span class="pill <?= $statusPill ?>"><?= h($statusLabel) ?></span>
                            <span class="pill bg-ink-100 text-ink-600"><?= h($repeatLabel) ?></span>
                            <?php if (!empty($r['patient_label'])): ?>
                                <span class="text-[12px] text-ink-500">· <?= h($r['patient_label']) ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($r['dosage']) || !empty($r['notes'])): ?>
                            <div class="text-[13px] text-ink-700 mt-0.5">
                                <?php if (!empty($r['dosage'])): ?><span class="font-medium"><?= h($r['dosage']) ?></span><?php endif; ?>
                                <?php if (!empty($r['notes'])): ?>
                                    <span class="text-ink-500">
                                        <?= !empty($r['dosage']) ? '— ' : '' ?><?= h($r['notes']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-[12px] text-ink-500 mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="inline-flex items-center gap-1">
                                <?= icon('clock', 'w-3 h-3') ?>
                                <span><?= h(t('Reminders.nextDue')) ?>:</span>
                                <time class="tabular-nums"
                                      datetime="<?= h(str_replace(' ', 'T', $r['next_due_at']) . 'Z') ?>"
                                      data-utc="<?= h($r['next_due_at']) ?>">
                                    <?= h($r['next_due_at']) ?> UTC
                                </time>
                            </span>
                            <?php if (!empty($r['repeat_until'])): ?>
                                <span class="inline-flex items-center gap-1">
                                    <?= h(t('Reminders.until')) ?>:
                                    <time class="tabular-nums"
                                          datetime="<?= h(str_replace(' ', 'T', $r['repeat_until']) . 'Z') ?>"
                                          data-utc="<?= h($r['repeat_until']) ?>">
                                        <?= h($r['repeat_until']) ?> UTC
                                    </time>
                                </span>
                            <?php endif; ?>
                            <?php if ((int) $r['sent_count'] > 0): ?>
                                <span class="inline-flex items-center gap-1">
                                    <?= icon('check', 'w-3 h-3 text-vital-600') ?>
                                    <?= h(t('Reminders.sentN', ['n' => (int) $r['sent_count']])) ?>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($r['last_error'])): ?>
                                <span class="inline-flex items-center gap-1 text-red-600"
                                      title="<?= h($r['last_error']) ?>">
                                    <?= icon('alert', 'w-3 h-3') ?>
                                    <?= h(t('Reminders.lastError')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-1 shrink-0">
                        <?php if ($isActive): ?>
                            <form method="post" action="/cases/<?= $cid ?>/reminders/<?= (int) $r['id'] ?>/snooze" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="minutes" value="15">
                                <button type="submit" class="btn-ghost" title="<?= h(t('Reminders.snooze15')) ?>">
                                    <?= icon('clock', 'w-4 h-4') ?>
                                    <span class="hidden sm:inline">15m</span>
                                </button>
                            </form>
                            <form method="post" action="/cases/<?= $cid ?>/reminders/<?= (int) $r['id'] ?>/status" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="paused">
                                <button type="submit" class="btn-ghost" title="<?= h(t('Reminders.pause')) ?>">
                                    <?= icon('clock', 'w-4 h-4') ?>
                                    <span class="hidden sm:inline"><?= h(t('Reminders.pause')) ?></span>
                                </button>
                            </form>
                        <?php elseif ($isPaused): ?>
                            <form method="post" action="/cases/<?= $cid ?>/reminders/<?= (int) $r['id'] ?>/status" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="status" value="active">
                                <button type="submit" class="btn-ghost" title="<?= h(t('Reminders.resume')) ?>">
                                    <?= icon('check', 'w-4 h-4 text-vital-600') ?>
                                    <span class="hidden sm:inline"><?= h(t('Reminders.resume')) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <form method="post" action="/cases/<?= $cid ?>/reminders/<?= (int) $r['id'] ?>/delete" class="inline"
                              @submit.prevent="if (confirm(deleteMsg)) $el.submit();">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn-danger-ghost" title="<?= h(t('Common.delete')) ?>">
                                <?= icon('trash', 'w-4 h-4') ?>
                            </button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script>
(function () {
    if (window.__medRemindersBound) return;
    window.__medRemindersBound = true;

    // Format UTC timestamps as local time once on load.
    function fmt() {
        var fmt = new Intl.DateTimeFormat(undefined, {
            year: 'numeric', month: 'short', day: '2-digit',
            hour: '2-digit', minute: '2-digit'
        });
        document.querySelectorAll('time[data-utc]').forEach(function (el) {
            var utc = el.getAttribute('data-utc');
            if (!utc) return;
            var d = new Date(utc.replace(' ', 'T') + 'Z');
            if (!isNaN(d.getTime())) el.textContent = fmt.format(d);
        });
    }
    fmt();

    // Provide an Alpine component if Alpine is loaded.
    function provide() {
        if (!window.Alpine) return setTimeout(provide, 50);
        window.Alpine.data('medReminders', function (deleteMsg) {
            return {
                opening: false,
                interval: '0',
                deleteMsg: deleteMsg,
            };
        });
    }
    provide();
})();
</script>
