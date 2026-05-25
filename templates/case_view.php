<?php
require_once TEMPLATES_DIR . '/components/icons.php';
$title = $case['title'] . ' — ' . t('Case.tabs.patient');
$spec = get_specialty($case['specialty_id']);
$cid = (int) $case['id'];

// Completeness
$score = 0;
foreach (['age_years', 'sex', 'symptoms', 'vital_signs', 'medical_history', 'medications', 'allergies', 'lab_values', 'clinical_question'] as $f) {
    if (!empty($patient[$f])) $score++;
}
$completeness = (int) round(($score / 9) * 100);
$ringClass = $completeness >= 75 ? 'text-vital-600' : ($completeness >= 50 ? 'text-brand-600' : 'text-amber-500');

// Latest report
$latestReport = null;
if (!empty($reports)) {
    $latestReport = json_decode((string) $reports[0]['content_json'], true);
}

$statusLabels = [
    'OPEN'        => t('Dashboard.statusOpen'),
    'IN_PROGRESS' => t('Dashboard.statusInProgress'),
    'REPORTED'    => t('Dashboard.statusReported'),
    'CLOSED'      => t('Dashboard.statusClosed'),
];
$statusPill = [
    'OPEN'        => 'bg-ink-100 text-ink-700',
    'IN_PROGRESS' => 'bg-brand-50 text-brand-800',
    'REPORTED'    => 'bg-vital-50 text-vital-700',
    'CLOSED'      => 'bg-ink-100 text-ink-500',
];

$tabsMeta = [
    'patient'   => 'user',
    'documents' => 'file-text',
    'chat'      => 'message',
    'report'    => 'clipboard',
];
ob_start();
?>
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-5 sm:py-6 space-y-5" x-data="caseView(<?= (int) $cid ?>)">

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <a href="/dashboard" class="inline-flex items-center gap-1 text-xs text-ink-500 hover:text-ink-800 transition-colors">
                <?= icon('arrow-left', 'w-3.5 h-3.5') ?>
                <?= h(t('Case.back')) ?>
            </a>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight mt-1 text-ink-900 break-words"><?= h($case['title']) ?></h1>
            <div class="text-sm text-ink-500 mt-1 flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1">
                    <?= icon($spec['icon'] ?? 'stethoscope', 'w-4 h-4 text-ink-400') ?>
                    <?= h($spec['specialty'] ?? '') ?>
                </span>
                <span class="text-ink-300">·</span>
                <span class="pill <?= $statusPill[$case['status']] ?? 'bg-ink-100 text-ink-700' ?>">
                    <?= h($statusLabels[$case['status']] ?? strtolower(str_replace('_', ' ', $case['status']))) ?>
                </span>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto">
            <div class="card px-3 py-2 flex items-center gap-2 text-sm min-w-0 flex-1 sm:flex-initial">
                <span class="w-7 h-7 rounded-md bg-brand-50 text-brand-700 grid place-items-center shrink-0">
                    <?= icon($spec['icon'] ?? 'stethoscope', 'w-4 h-4') ?>
                </span>
                <span class="font-medium text-ink-800 truncate"><?= h($spec['name'] ?? t('Case.agentLabel')) ?></span>
                <button type="button" @click="changingAgent = true" class="text-xs text-brand-700 hover:underline ml-auto shrink-0"><?= h(t('Case.agentChange')) ?></button>
            </div>
            <button type="button" @click="generateReport()" :disabled="busy === 'report'" class="btn-primary w-full sm:w-auto">
                <?= icon('sparkles', 'w-4 h-4') ?>
                <span x-show="busy !== 'report'"><?= h(t('Case.generateReport')) ?></span>
                <span x-show="busy === 'report'"><?= h(t('Common.generating')) ?></span>
            </button>
        </div>
    </div>

    <template x-if="error">
        <div class="card border-red-200 bg-red-50 p-3 text-sm text-red-700 flex items-start gap-2">
            <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0') ?>
            <span x-text="error"></span>
        </div>
    </template>

    <?php if ($completeness < 50): ?>
        <div class="card border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 flex items-start gap-2.5">
            <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0 text-amber-700') ?>
            <span><?= h(t('Case.sparseData', ['percent' => $completeness])) ?></span>
        </div>
    <?php endif; ?>

    <!-- Change agent modal -->
    <div x-show="changingAgent" x-cloak class="fixed inset-0 z-50 bg-ink-900/40 backdrop-blur-sm grid place-items-center p-4">
        <div class="card w-full max-w-3xl p-5 max-h-[80vh] overflow-y-auto shadow-lift">
            <div class="flex items-center justify-between mb-4">
                <h2 class="font-semibold text-lg flex items-center gap-2 text-ink-900">
                    <?= icon('agents', 'w-5 h-5 text-brand-700') ?>
                    <?= h(t('Case.changeAgentTitle')) ?>
                </h2>
                <button type="button" @click="changingAgent = false" class="btn-ghost">
                    <?= icon('x', 'w-4 h-4') ?>
                </button>
            </div>
            <form method="post" action="/cases/<?= $cid ?>/specialty" class="grid sm:grid-cols-2 gap-3">
                <?= csrf_field() ?>
                <?php foreach (specialties() as $s):
                    $isCur = $s['id'] === $case['specialty_id'];
                ?>
                    <button type="submit" name="specialty_id" value="<?= h($s['id']) ?>"
                            class="text-left card card-hover p-3 <?= $isCur ? 'ring-2 ring-brand-500 border-brand-300' : '' ?>">
                        <div class="flex items-start gap-2.5">
                            <span class="w-9 h-9 rounded-md bg-brand-50 text-brand-700 grid place-items-center shrink-0">
                                <?= icon($s['icon'] ?? 'stethoscope', 'w-5 h-5') ?>
                            </span>
                            <div class="min-w-0">
                                <div class="font-medium text-sm text-ink-900"><?= h($s['name']) ?></div>
                                <div class="text-xs text-ink-500 mt-0.5"><?= h($s['specialty']) ?></div>
                            </div>
                        </div>
                    </button>
                <?php endforeach; ?>
            </form>
        </div>
    </div>

    <!-- Tabs -->
    <div class="border-b border-ink-200 flex gap-5 overflow-x-auto">
        <?php foreach ($tabsMeta as $tk => $tabIcon): ?>
            <button type="button" @click="tab = '<?= $tk ?>'; location.hash = '<?= $tk ?>'"
                    :class="tab === '<?= $tk ?>' ? 'tab-active' : 'tab-idle'"
                    class="tab-btn whitespace-nowrap">
                <?= icon($tabIcon, 'w-4 h-4') ?>
                <?= h(t("Case.tabs.$tk")) ?>
                <?php if ($tk === 'documents' && count($documents) > 0): ?>
                    <span class="ml-0.5 text-[11px] bg-ink-100 text-ink-600 rounded-full px-1.5 py-0.5 font-semibold"><?= count($documents) ?></span>
                <?php endif; ?>
                <?php if ($tk === 'report' && count($reports) > 0): ?>
                    <span class="ml-0.5 text-[11px] bg-ink-100 text-ink-600 rounded-full px-1.5 py-0.5 font-semibold"><?= count($reports) ?></span>
                <?php endif; ?>
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Patient panel -->
    <div x-show="tab === 'patient'" class="grid lg:grid-cols-3 gap-4">
        <form method="post" action="/cases/<?= $cid ?>/patient" class="lg:col-span-2 card p-5">
            <?= csrf_field() ?>
            <div class="flex items-center gap-2 mb-4 pb-3 border-b border-ink-100">
                <?= icon('user', 'w-5 h-5 text-brand-700') ?>
                <h2 class="section-title">Patient details</h2>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
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
                        <textarea name="<?= $col ?>" class="input min-h-[80px]" placeholder="<?= h($ph) ?>"><?= h((string) $val) ?></textarea>
                    <?php else: ?>
                        <input name="<?= $col ?>" value="<?= h((string) $val) ?>" placeholder="<?= h($ph) ?>" class="input">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="submit" class="btn-primary">
                    <?= icon('check', 'w-4 h-4') ?>
                    <?= h(t('Case.patient.save')) ?>
                </button>
            </div>
        </form>
        <div class="space-y-3">
            <div class="card p-5">
                <div class="text-[11px] text-ink-500 uppercase tracking-wider font-semibold flex items-center gap-1.5">
                    <?= icon('pulse', 'w-3.5 h-3.5') ?>
                    <?= h(t('Case.patient.completeness')) ?>
                </div>
                <div class="mt-3 flex items-center gap-4">
                    <div class="relative w-20 h-20 shrink-0">
                        <svg viewBox="0 0 36 36" class="w-20 h-20 -rotate-90">
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3" class="text-ink-100"/>
                            <circle cx="18" cy="18" r="15.915" fill="none" stroke="currentColor" stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-dasharray="<?= $completeness ?>, 100"
                                    class="<?= $ringClass ?>"/>
                        </svg>
                        <div class="absolute inset-0 grid place-items-center text-base font-bold text-ink-900"><?= $completeness ?>%</div>
                    </div>
                    <div class="text-xs text-ink-500 leading-relaxed"><?= h(t('Case.patient.commonFields')) ?></div>
                </div>
            </div>
            <div class="card p-5">
                <div class="text-[11px] text-ink-500 uppercase tracking-wider font-semibold mb-3 flex items-center gap-1.5">
                    <?= icon('clipboard', 'w-3.5 h-3.5') ?>
                    <?= h(t('Case.patient.checklist')) ?>
                </div>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($spec['required_context'] as $r): ?>
                        <li class="flex items-start gap-2 text-ink-700">
                            <span class="text-brand-600 mt-0.5 shrink-0"><?= icon('check', 'w-3.5 h-3.5') ?></span>
                            <span><?= h($r) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if (!empty($spec['common_red_flags'])): ?>
            <div class="card border-red-200 bg-red-50/50 p-5">
                <div class="text-[11px] text-red-700 uppercase tracking-wider font-semibold mb-3 flex items-center gap-1.5">
                    <?= icon('flag', 'w-3.5 h-3.5') ?>
                    Red flags
                </div>
                <ul class="space-y-2 text-sm">
                    <?php foreach ($spec['common_red_flags'] as $r): ?>
                        <li class="flex items-start gap-2 text-red-900">
                            <span class="text-red-600 mt-1.5 shrink-0">
                                <span class="block w-1 h-1 rounded-full bg-red-600"></span>
                            </span>
                            <span><?= h($r) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Documents panel -->
    <div x-show="tab === 'documents'" class="grid lg:grid-cols-3 gap-4">
        <form method="post" action="/cases/<?= $cid ?>/documents" enctype="multipart/form-data" class="lg:col-span-1">
            <?= csrf_field() ?>
            <div class="card p-5 text-center">
                <div class="w-12 h-12 mx-auto rounded-xl bg-brand-50 text-brand-700 grid place-items-center">
                    <?= icon('upload', 'w-6 h-6') ?>
                </div>
                <div class="text-sm font-semibold mt-3 text-ink-900"><?= h(t('Case.documents.upload')) ?></div>
                <div class="text-xs text-ink-500 mt-1"><?= h(t('Case.documents.uploadHint')) ?></div>
                <input type="file" name="file[]" multiple class="mt-4 mx-auto text-xs file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:bg-ink-100 file:text-ink-700 file:font-medium hover:file:bg-ink-200 cursor-pointer" accept=".pdf,.docx,.txt,image/*">
                <button type="submit" class="btn-primary mt-4 w-full">
                    <?= icon('upload', 'w-4 h-4') ?>
                    <?= h(t('Case.documents.upload')) ?>
                </button>
            </div>
            <div class="card p-3 mt-3 text-xs text-ink-600 flex items-start gap-2">
                <?= icon('info', 'w-4 h-4 mt-0.5 shrink-0 text-ink-400') ?>
                <span><?= h(t('Case.documents.tip')) ?></span>
            </div>
        </form>
        <div class="lg:col-span-2 space-y-3">
            <?php if (!$documents): ?>
                <div class="card p-10 text-sm text-ink-500 text-center">
                    <div class="w-12 h-12 mx-auto rounded-xl bg-ink-100 text-ink-400 grid place-items-center">
                        <?= icon('folder', 'w-6 h-6') ?>
                    </div>
                    <div class="mt-3"><?= h(t('Case.documents.empty')) ?></div>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $d): ?>
                    <div class="card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex items-start gap-3">
                                <span class="w-9 h-9 rounded-md bg-ink-100 text-ink-600 grid place-items-center shrink-0">
                                    <?= icon('file-text', 'w-5 h-5') ?>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold truncate text-ink-900"><?= h($d['filename']) ?></div>
                                    <div class="text-xs text-ink-500 mt-0.5">
                                        <?= h($d['kind']) ?> · <?= number_format($d['size_bytes'] / 1024, 1) ?> KB ·
                                        <?= h((new DateTime($d['uploaded_at']))->format('Y-m-d H:i')) ?>
                                    </div>
                                </div>
                            </div>
                            <form method="post" action="/cases/<?= $cid ?>/documents/<?= (int) $d['id'] ?>/delete"
                                  onsubmit="return confirm(<?= json_encode(t('Case.documents.deleteConfirm')) ?>);">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-danger-ghost" title="<?= h(t('Case.documents.deleteTitle')) ?>">
                                    <?= icon('trash', 'w-4 h-4') ?>
                                </button>
                            </form>
                        </div>
                        <div class="mt-3 text-xs text-ink-700 leading-relaxed bg-ink-50 border border-ink-100 rounded-lg p-3 max-h-44 overflow-y-auto whitespace-pre-wrap font-mono">
                            <?= h(($d['extracted_text'] ?? '') !== '' ? $d['extracted_text'] : t('Case.documents.noText')) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Chat panel -->
    <div x-show="tab === 'chat'" class="card flex flex-col h-[70vh] overflow-hidden">
        <div class="px-4 py-3 border-b border-ink-200 text-sm flex items-center gap-2 bg-ink-50/50">
            <span class="w-7 h-7 rounded-md bg-brand-50 text-brand-700 grid place-items-center">
                <?= icon($spec['icon'] ?? 'stethoscope', 'w-4 h-4') ?>
            </span>
            <span class="font-medium text-ink-800"><?= h(t('Case.chat.activeAgent', ['name' => $spec['name'] ?? ''])) ?></span>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-3" x-ref="msgs">
            <?php if (!$messages): ?>
                <div class="text-sm text-ink-500 text-center py-10 flex flex-col items-center gap-2" x-show="!chatMsgs.length">
                    <span class="w-10 h-10 rounded-full bg-ink-100 text-ink-400 grid place-items-center">
                        <?= icon('message', 'w-5 h-5') ?>
                    </span>
                    <?= h(t('Case.chat.empty')) ?>
                </div>
            <?php endif; ?>
            <?php foreach ($messages as $m):
                $isDoc = $m['role'] === 'doctor';
                $roleLabel = $isDoc ? t('Case.chat.you') : ($m['role'] === 'agent' ? t('Case.chat.agent') : t('Case.chat.system'));
            ?>
                <div class="flex <?= $isDoc ? 'justify-end' : 'justify-start' ?>">
                    <div class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm whitespace-pre-wrap shadow-sm <?= $isDoc ? 'bg-brand-700 text-white rounded-br-md' : 'bg-ink-100 text-ink-900 rounded-bl-md' ?>">
                        <div class="text-[11px] opacity-70 mb-1 font-medium"><?= h($roleLabel) ?> · <?= h((new DateTime($m['created_at']))->format('H:i')) ?></div>
                        <?= h($m['content']) ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <template x-for="m in chatMsgs" :key="m.id">
                <div :class="m.role === 'doctor' ? 'flex justify-end' : 'flex justify-start'">
                    <div :class="m.role === 'doctor' ? 'max-w-[80%] rounded-2xl rounded-br-md px-4 py-2.5 text-sm whitespace-pre-wrap shadow-sm bg-brand-700 text-white' : 'max-w-[80%] rounded-2xl rounded-bl-md px-4 py-2.5 text-sm whitespace-pre-wrap shadow-sm bg-ink-100 text-ink-900'">
                        <div class="text-[11px] opacity-70 mb-1 font-medium" x-text="m.label"></div>
                        <span x-text="m.content"></span>
                    </div>
                </div>
            </template>
            <div x-show="busy === 'chat'" class="text-xs text-ink-500 flex items-center gap-2 pl-2">
                <span class="inline-flex gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500 animate-pulse"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500 animate-pulse" style="animation-delay:.15s"></span>
                    <span class="w-1.5 h-1.5 rounded-full bg-brand-500 animate-pulse" style="animation-delay:.3s"></span>
                </span>
                <?= h(t('Case.chat.thinking', ['name' => $spec['name'] ?? ''])) ?>
            </div>
        </div>
        <form @submit.prevent="sendMessage()" class="border-t border-ink-200 p-3 flex gap-2 bg-white">
            <input x-model="chatDraft" :disabled="busy === 'chat'"
                   class="input flex-1" placeholder="<?= h(t('Case.chat.placeholder')) ?>">
            <button type="submit" class="btn-primary" :disabled="busy === 'chat'">
                <?= icon('send', 'w-4 h-4') ?>
                <span class="hidden sm:inline"><?= h(t('Case.chat.send')) ?></span>
            </button>
        </form>
    </div>

    <!-- Report panel -->
    <div x-show="tab === 'report'">
        <template x-if="reportData">
            <div class="space-y-4">
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <button type="button" @click="generateReport()" class="btn-secondary" :disabled="busy === 'report'">
                        <?= icon('refresh', 'w-4 h-4') ?>
                        <?= h(t('Case.report.regenerate')) ?>
                    </button>
                    <button type="button" onclick="window.print()" class="btn-primary">
                        <?= icon('printer', 'w-4 h-4') ?>
                        <?= h(t('Case.report.export')) ?>
                    </button>
                </div>
                <div id="reportSlot"></div>
            </div>
        </template>
        <template x-if="!reportData">
            <div class="card p-12 text-center">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-brand-50 text-brand-700 grid place-items-center">
                    <?= icon('clipboard', 'w-7 h-7') ?>
                </div>
                <h2 class="font-semibold text-lg mt-4 text-ink-900"><?= h(t('Case.report.noTitle')) ?></h2>
                <p class="text-sm text-ink-500 mt-1 max-w-md mx-auto"><?= h(t('Case.report.noBody')) ?></p>
                <button type="button" @click="generateReport()" class="btn-primary mt-5" :disabled="busy === 'report'">
                    <?= icon('sparkles', 'w-4 h-4') ?>
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
