<?php
$title = $case['title'] . ' — ' . t('Case.tabs.patient');
$spec = get_specialty($case['specialty_id']);
$cid = (int) $case['id'];

// Completeness
$score = 0;
foreach (['age_years', 'sex', 'symptoms', 'vital_signs', 'medical_history', 'medications', 'allergies', 'lab_values', 'clinical_question'] as $f) {
    if (!empty($patient[$f])) $score++;
}
$completeness = (int) round(($score / 9) * 100);

// Latest report
$latestReport = null;
if (!empty($reports)) {
    $latestReport = json_decode((string) $reports[0]['content_json'], true);
}
ob_start();
?>
<div class="max-w-7xl mx-auto px-6 py-6 space-y-4" x-data="caseView(<?= (int) $cid ?>)">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="/dashboard" class="text-xs text-ink-500 hover:text-ink-700"><?= h(t('Case.back')) ?></a>
            <h1 class="text-xl font-semibold mt-1"><?= h($case['title']) ?></h1>
            <div class="text-sm text-ink-500 mt-0.5">
                <?= h($spec['specialty'] ?? '') ?> · <?= h(t('Case.statusLabel')) ?>
                <span class="font-medium"><?= h($case['status']) ?></span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <div class="card px-3 py-1.5 flex items-center gap-2 text-sm">
                <span class="font-medium"><?= h($spec['name'] ?? t('Case.agentLabel')) ?></span>
                <button type="button" @click="changingAgent = true" class="text-xs text-brand-700 hover:underline ml-2"><?= h(t('Case.agentChange')) ?></button>
            </div>
            <button type="button" @click="generateReport()" :disabled="busy === 'report'" class="btn-primary">
                <span x-show="busy !== 'report'"><?= h(t('Case.generateReport')) ?></span>
                <span x-show="busy === 'report'"><?= h(t('Common.generating')) ?></span>
            </button>
        </div>
    </div>

    <template x-if="error">
        <div class="card border-red-200 bg-red-50 p-3 text-sm text-red-700" x-text="error"></div>
    </template>

    <?php if ($completeness < 50): ?>
        <div class="card border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
            <?= h(t('Case.sparseData', ['percent' => $completeness])) ?>
        </div>
    <?php endif; ?>

    <!-- Change agent modal -->
    <div x-show="changingAgent" x-cloak class="fixed inset-0 z-50 bg-black/30 grid place-items-center p-4">
        <div class="card w-full max-w-3xl p-5 max-h-[80vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold"><?= h(t('Case.changeAgentTitle')) ?></h2>
                <button type="button" @click="changingAgent = false" class="btn-ghost">✕</button>
            </div>
            <form method="post" action="/cases/<?= $cid ?>/specialty" class="grid sm:grid-cols-2 gap-2">
                <?= csrf_field() ?>
                <?php foreach (specialties() as $s): $isCur = $s['id'] === $case['specialty_id']; ?>
                    <button type="submit" name="specialty_id" value="<?= h($s['id']) ?>"
                            class="text-left card p-3 hover:border-brand-200 <?= $isCur ? 'ring-2 ring-brand-500' : '' ?>">
                        <div class="font-medium text-sm"><?= h($s['name']) ?></div>
                        <div class="text-xs text-ink-500 mt-1"><?= h($s['specialty']) ?></div>
                    </button>
                <?php endforeach; ?>
            </form>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-ink-100 flex gap-4 text-sm">
        <?php $tabs = ['patient', 'documents', 'chat', 'report']; foreach ($tabs as $tk): ?>
            <button type="button" @click="tab = '<?= $tk ?>'; location.hash = '<?= $tk ?>'"
                    :class="tab === '<?= $tk ?>' ? 'border-brand-600 text-brand-700 font-medium' : 'border-transparent text-ink-500 hover:text-ink-800'"
                    class="py-2 -mb-px border-b-2">
                <?= h(t("Case.tabs.$tk")) ?>
                <?php if ($tk === 'documents' && count($documents) > 0): ?><span class="ml-1 text-xs text-ink-400">(<?= count($documents) ?>)</span><?php endif; ?>
                <?php if ($tk === 'report' && count($reports) > 0): ?><span class="ml-1 text-xs text-ink-400">(<?= count($reports) ?>)</span><?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Patient panel -->
    <div x-show="tab === 'patient'" class="grid lg:grid-cols-3 gap-4">
        <form method="post" action="/cases/<?= $cid ?>/patient" class="lg:col-span-2 card p-4">
            <?= csrf_field() ?>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="label"><?= h(t('Case.patient.ageYears')) ?></label>
                    <input type="number" min="0" name="age_years" value="<?= h((string) ($patient['age_years'] ?? '')) ?>" class="input">
                </div>
                <div>
                    <label class="label"><?= h(t('Case.patient.sex')) ?></label>
                    <select name="sex" class="input">
                        <?php $sx = $patient['sex'] ?? ''; ?>
                        <option value="">—</option>
                        <option value="female" <?= $sx === 'female' ? 'selected' : '' ?>><?= h(t('Case.patient.sexFemale')) ?></option>
                        <option value="male" <?= $sx === 'male' ? 'selected' : '' ?>><?= h(t('Case.patient.sexMale')) ?></option>
                        <option value="other" <?= $sx === 'other' ? 'selected' : '' ?>><?= h(t('Case.patient.sexOther')) ?></option>
                        <option value="unknown" <?= $sx === 'unknown' ? 'selected' : '' ?>><?= h(t('Case.patient.sexUnknown')) ?></option>
                    </select>
                </div>
                <?php
                $fields = [
                    ['symptoms', 'symptoms', 'symptomsPh', true],
                    ['vital_signs', 'vitals', 'vitalsPh', true],
                    ['medical_history', 'history', null, true],
                    ['medications', 'medications', null, true],
                    ['allergies', 'allergies', null, true],
                    ['lab_values', 'labs', 'labsPh', true],
                    ['imaging_summary', 'imaging', 'imagingPh', true],
                    ['initial_diagnosis', 'initialDx', null, false],
                    ['clinical_question', 'question', 'questionPh', true],
                ];
                foreach ($fields as [$col, $labelKey, $phKey, $multi]):
                    $val = $patient[$col] ?? '';
                    $ph = $phKey ? t("Case.patient.$phKey") : '';
                ?>
                <div class="<?= $multi ? 'sm:col-span-2' : '' ?>">
                    <label class="label"><?= h(t("Case.patient.$labelKey")) ?></label>
                    <?php if ($multi): ?>
                        <textarea name="<?= $col ?>" class="input min-h-[72px]" placeholder="<?= h($ph) ?>"><?= h((string) $val) ?></textarea>
                    <?php else: ?>
                        <input name="<?= $col ?>" value="<?= h((string) $val) ?>" placeholder="<?= h($ph) ?>" class="input">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="btn-primary"><?= h(t('Case.patient.save')) ?></button>
            </div>
        </form>
        <div class="space-y-3">
            <div class="card p-4">
                <div class="text-xs text-ink-500 uppercase tracking-wide"><?= h(t('Case.patient.completeness')) ?></div>
                <div class="mt-1 flex items-baseline gap-2">
                    <div class="text-2xl font-semibold"><?= $completeness ?>%</div>
                    <div class="text-xs text-ink-500"><?= h(t('Case.patient.commonFields')) ?></div>
                </div>
                <div class="mt-2 h-2 bg-ink-100 rounded-full overflow-hidden">
                    <div class="h-full bg-brand-500" style="width: <?= $completeness ?>%"></div>
                </div>
            </div>
            <div class="card p-4">
                <div class="text-xs text-ink-500 uppercase tracking-wide mb-2"><?= h(t('Case.patient.checklist')) ?></div>
                <ul class="space-y-1.5 text-sm">
                    <?php foreach ($spec['required_context'] as $r): ?>
                        <li class="text-ink-700">• <?= h($r) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- Documents panel -->
    <div x-show="tab === 'documents'" class="grid lg:grid-cols-3 gap-4">
        <form method="post" action="/cases/<?= $cid ?>/documents" enctype="multipart/form-data" class="lg:col-span-1">
            <?= csrf_field() ?>
            <div class="card p-4 text-center">
                <div class="text-sm font-medium"><?= h(t('Case.documents.upload')) ?></div>
                <div class="text-xs text-ink-500 mt-1"><?= h(t('Case.documents.uploadHint')) ?></div>
                <input type="file" name="file[]" multiple class="mt-3 mx-auto text-xs" accept=".pdf,.docx,.txt,image/*">
                <button type="submit" class="btn-primary mt-3"><?= h(t('Case.documents.upload')) ?></button>
            </div>
            <div class="card p-3 mt-3 text-xs text-ink-600"><?= h(t('Case.documents.tip')) ?></div>
        </form>
        <div class="lg:col-span-2 space-y-3">
            <?php if (!$documents): ?>
                <div class="card p-6 text-sm text-ink-500 text-center"><?= h(t('Case.documents.empty')) ?></div>
            <?php else: ?>
                <?php foreach ($documents as $d): ?>
                    <div class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-medium truncate"><?= h($d['filename']) ?></div>
                                <div class="text-xs text-ink-500 mt-0.5">
                                    <?= h($d['kind']) ?> · <?= number_format($d['size_bytes'] / 1024, 1) ?> KB ·
                                    <?= h((new DateTime($d['uploaded_at']))->format('Y-m-d H:i')) ?>
                                </div>
                            </div>
                            <form method="post" action="/cases/<?= $cid ?>/documents/<?= (int) $d['id'] ?>/delete"
                                  onsubmit="return confirm(<?= json_encode(t('Case.documents.deleteConfirm')) ?>);">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-ghost text-red-600 hover:bg-red-50" title="<?= h(t('Case.documents.deleteTitle')) ?>">✕</button>
                            </form>
                        </div>
                        <div class="mt-3 text-xs text-ink-700 leading-relaxed bg-ink-50 rounded-md p-3 max-h-44 overflow-y-auto whitespace-pre-wrap">
                            <?= h(($d['extracted_text'] ?? '') !== '' ? $d['extracted_text'] : t('Case.documents.noText')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chat panel -->
    <div x-show="tab === 'chat'" class="card flex flex-col h-[70vh]">
        <div class="px-4 py-3 border-b border-ink-100 text-sm">
            <?= h(t('Case.chat.activeAgent', ['name' => $spec['name'] ?? ''])) ?>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="msgs">
            <?php if (!$messages): ?>
                <div class="text-sm text-ink-500 text-center py-10" x-show="!chatMsgs.length"><?= h(t('Case.chat.empty')) ?></div>
            <?php endif; ?>
            <?php foreach ($messages as $m):
                $isDoc = $m['role'] === 'doctor';
                $roleLabel = $isDoc ? t('Case.chat.you') : ($m['role'] === 'agent' ? t('Case.chat.agent') : t('Case.chat.system'));
            ?>
                <div class="flex <?= $isDoc ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap <?= $isDoc ? 'bg-brand-600 text-white' : 'bg-ink-100 text-ink-900' ?>">
                        <div class="text-[11px] opacity-70 mb-1"><?= h($roleLabel) ?> · <?= h((new DateTime($m['created_at']))->format('H:i')) ?></div>
                        <?= h($m['content']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <template x-for="m in chatMsgs" :key="m.id">
                <div :class="m.role === 'doctor' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'doctor' ? 'max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap bg-brand-600 text-white' : 'max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm whitespace-pre-wrap bg-ink-100 text-ink-900'">
                        <div class="text-[11px] opacity-70 mb-1" x-text="m.label"></div>
                        <span x-text="m.content"></span>
                    </div>
                </div>
            </template>
            <div x-show="busy === 'chat'" class="text-xs text-ink-500">
                <?= h(t('Case.chat.thinking', ['name' => $spec['name'] ?? ''])) ?>
            </div>
        </div>
        <form @submit.prevent="sendMessage()" class="border-t border-ink-100 p-3 flex gap-2">
            <input x-model="chatDraft" :disabled="busy === 'chat'"
                   class="input flex-1" placeholder="<?= h(t('Case.chat.placeholder')) ?>">
            <button type="submit" class="btn-primary" :disabled="busy === 'chat'"><?= h(t('Case.chat.send')) ?></button>
        </form>
    </div>

    <!-- Report panel -->
    <div x-show="tab === 'report'">
        <template x-if="reportData">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" @click="generateReport()" class="btn-secondary" :disabled="busy === 'report'">
                        <?= h(t('Case.report.regenerate')) ?>
                    </button>
                    <button type="button" onclick="window.print()" class="btn-primary"><?= h(t('Case.report.export')) ?></button>
                </div>
                <div id="reportSlot"></div>
            </div>
        </template>
        <template x-if="!reportData">
            <div class="card p-10 text-center">
                <h2 class="font-medium"><?= h(t('Case.report.noTitle')) ?></h2>
                <p class="text-sm text-ink-500 mt-1"><?= h(t('Case.report.noBody')) ?></p>
                <button type="button" @click="generateReport()" class="btn-primary mt-4" :disabled="busy === 'report'">
                    <?= h(t('Case.report.generate')) ?>
                </button>
            </div>
        </template>
        <?php if ($latestReport): ?>
            <div x-show="reportData" class="space-y-4 mt-4">
                <?php $report = $latestReport; require TEMPLATES_DIR . '/components/report_viewer.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function caseView(cid) {
    return {
        tab: location.hash.replace('#','') || 'patient',
        busy: null,
        error: null,
        changingAgent: false,
        chatMsgs: [],
        chatDraft: '',
        reportData: <?= $latestReport ? 'true' : 'null' ?>,
        get csrf() { return document.querySelector('meta[name=csrf-token]').content; },
        async sendMessage() {
            const text = this.chatDraft.trim();
            if (!text || this.busy) return;
            this.busy = 'chat'; this.error = null;
            const tmpId = 't' + Date.now();
            this.chatMsgs.push({ id: tmpId, role: 'doctor', label: '<?= h(t('Case.chat.you')) ?> · ' + new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}), content: text });
            this.chatDraft = '';
            const fd = new FormData();
            fd.append('content', text);
            fd.append('_csrf', this.csrf);
            try {
                const r = await fetch('/cases/' + cid + '/messages', { method: 'POST', body: fd });
                const j = await r.json();
                if (!r.ok) throw new Error(j.error || 'Message failed');
                this.chatMsgs.push({ id: 'r' + Date.now(), role: 'agent', label: '<?= h(t('Case.chat.agent')) ?> · ' + new Date().toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}), content: j.reply });
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = null;
                this.$nextTick(() => { this.$refs.msgs.scrollTop = this.$refs.msgs.scrollHeight; });
            }
        },
        async generateReport() {
            if (this.busy) return;
            this.busy = 'report'; this.error = null; this.tab = 'report';
            const fd = new FormData();
            fd.append('_csrf', this.csrf);
            try {
                const r = await fetch('/cases/' + cid + '/report', { method: 'POST', body: fd });
                const j = await r.json();
                if (!r.ok) throw new Error(j.error || 'Report failed');
                this.reportData = true;
                location.reload();
            } catch (e) {
                this.error = e.message;
            } finally {
                this.busy = null;
            }
        },
    };
}
</script>
<style>[x-cloak] { display: none !important; }</style>
<?php $page_content = ob_get_clean(); require TEMPLATES_DIR . '/layout_authed.php'; ?>
