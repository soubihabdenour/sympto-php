<?php
require_once TEMPLATES_DIR . '/components/icons.php';
// $report (associative array matching schema)
// $patient (associative array from outer case_view scope) — used for Table 1
$patient = $patient ?? [];

$pillForLikelihood = [
    'high'   => 'bg-red-50 text-red-700 ring-1 ring-red-200',
    'medium' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    'low'    => 'bg-ink-100 text-ink-700 ring-1 ring-ink-200',
];
$labelForLikelihood = [
    'high'   => t('Report.likelihoodHigh'),
    'medium' => t('Report.likelihoodMedium'),
    'low'    => t('Report.likelihoodLow'),
];
$empty = t('Report.empty');

$bullets = static function (array $items) use ($empty): string {
    if (!$items) return '<span class="text-ink-500">' . h($empty) . '</span>';
    $out = '<ul class="list-disc pl-4 space-y-0.5">';
    foreach ($items as $it) {
        $out .= '<li>' . h((string) $it) . '</li>';
    }
    return $out . '</ul>';
};

$profileRows = [
    ['ageYears', 'age_years'],
    ['sex', 'sex'],
    ['symptoms', 'symptoms'],
    ['vitals', 'vital_signs'],
    ['labs', 'lab_values'],
    ['imaging', 'imaging_summary'],
    ['history', 'medical_history'],
    ['medications', 'medications'],
    ['allergies', 'allergies'],
    ['initialDx', 'initial_diagnosis'],
    ['question', 'clinical_question'],
];
?>
<div class="report-view space-y-3">
    <?php if (!empty($report['needsFollowUp']) && !empty($report['followUpQuestions'])): ?>
        <section class="card border-brand-200 bg-brand-50 p-3">
            <h3 class="text-xs font-semibold mb-1.5 flex items-center gap-1.5 text-brand-900 uppercase tracking-wide">
                <?= icon('message', 'w-3.5 h-3.5 text-brand-700') ?>
                <span><?= h(t('Report.followUpTitle')) ?></span>
            </h3>
            <?= $bullets($report['followUpQuestions']) ?>
        </section>
    <?php endif; ?>

    <!-- Table 1: Patient profile & vital signs -->
    <section class="card overflow-hidden">
        <h3 class="px-3 py-2 border-b border-ink-200 text-xs font-semibold flex items-center gap-1.5 text-ink-900 uppercase tracking-wide bg-ink-50/50">
            <?= icon('user', 'w-3.5 h-3.5 text-brand-700') ?>
            <span><?= h(t('Report.t1')) ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="report-table">
                <thead>
                    <tr>
                        <th><?= h(t('Report.col_parameter')) ?></th>
                        <th><?= h(t('Report.col_value')) ?></th>
                        <th><?= h(t('Report.col_note')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profileRows as [$labelKey, $col]):
                        $v = $patient[$col] ?? null;
                        if ($v === null || $v === '') continue;
                        $display = $col === 'vital_signs' ? vital_signs_format((string) $v) : (is_string($v) ? $v : (string) $v);
                        if ($display === '') continue;
                    ?>
                        <tr>
                            <td class="font-medium text-ink-700"><?= h(t("Case.patient.$labelKey")) ?></td>
                            <td class="whitespace-pre-wrap"><?= h($display) ?></td>
                            <td class="text-ink-500">—</td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (array_filter($profileRows, fn($r) => !empty($patient[$r[1]])) === []): ?>
                        <tr><td colspan="3" class="text-ink-500 text-center"><?= h($empty) ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Table 2: Clinical presentation & red flags -->
    <section class="card overflow-hidden">
        <h3 class="px-3 py-2 border-b border-ink-200 text-xs font-semibold flex items-center gap-1.5 text-ink-900 uppercase tracking-wide bg-ink-50/50">
            <?= icon('clipboard', 'w-3.5 h-3.5 text-brand-700') ?>
            <span><?= h(t('Report.t2')) ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="report-table">
                <thead>
                    <tr>
                        <th><?= h(t('Report.col_category')) ?></th>
                        <th><?= h(t('Report.col_findings')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="font-medium text-ink-700"><?= h(t('Report.row_caseSummary')) ?></td>
                        <td class="whitespace-pre-wrap"><?= h(($report['caseSummary'] ?? '') !== '' ? $report['caseSummary'] : $empty) ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium text-ink-700"><?= h(t('Report.row_physicalExam')) ?></td>
                        <td><?= $bullets($report['keyFindings'] ?? []) ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium text-red-700">🚨 <?= h(t('Report.row_criticalRedFlags')) ?></td>
                        <td><?= $bullets($report['redFlags'] ?? []) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Table 3: Differential diagnosis matrix -->
    <section class="card overflow-hidden">
        <h3 class="px-3 py-2 border-b border-ink-200 text-xs font-semibold flex items-center gap-1.5 text-ink-900 uppercase tracking-wide bg-ink-50/50">
            <?= icon('sparkles', 'w-3.5 h-3.5 text-brand-700') ?>
            <span><?= h(t('Report.t3')) ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="report-table">
                <thead>
                    <tr>
                        <th><?= h(t('Report.col_rank')) ?></th>
                        <th><?= h(t('Report.col_condition')) ?></th>
                        <th><?= h(t('Report.col_probability')) ?></th>
                        <th>✓ <?= h(t('Report.supportingEvidence')) ?></th>
                        <th>✗ <?= h(t('Report.evidenceAgainst')) ?></th>
                        <th>→ <?= h(t('Report.nextStep')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $dx = $report['differentialDiagnosis'] ?? []; ?>
                    <?php if (!$dx): ?>
                        <tr><td colspan="6" class="text-ink-500 text-center"><?= h($empty) ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($dx as $i => $d):
                            $like = $d['likelihood'] ?? 'low';
                            $pill = $pillForLikelihood[$like] ?? '';
                            $label = $labelForLikelihood[$like] ?? $like;
                        ?>
                            <tr>
                                <td class="font-mono text-ink-600"><?= ($i + 1) ?></td>
                                <td class="font-medium text-ink-900"><?= h((string) ($d['diagnosis'] ?? '')) ?></td>
                                <td><span class="pill <?= $pill ?>"><?= h($label) ?></span></td>
                                <td><?= $bullets($d['supportingEvidence'] ?? []) ?></td>
                                <td><?= $bullets($d['evidenceAgainst'] ?? []) ?></td>
                                <td class="whitespace-pre-wrap"><?= h((string) ($d['recommendedNextStep'] ?? $empty)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Table 4: Clinical action plan -->
    <section class="card overflow-hidden">
        <h3 class="px-3 py-2 border-b border-ink-200 text-xs font-semibold flex items-center gap-1.5 text-ink-900 uppercase tracking-wide bg-ink-50/50">
            <?= icon('pulse', 'w-3.5 h-3.5 text-brand-700') ?>
            <span><?= h(t('Report.t4')) ?></span>
        </h3>
        <div class="overflow-x-auto">
            <table class="report-table">
                <thead>
                    <tr>
                        <th><?= h(t('Report.col_phase')) ?></th>
                        <th><?= h(t('Report.col_actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="font-medium text-ink-700"><?= h(t('Report.row_missingInfo')) ?></td>
                        <td><?= $bullets($report['missingInformation'] ?? []) ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium text-ink-700"><?= h(t('Report.row_workup')) ?></td>
                        <td><?= $bullets($report['recommendedTests'] ?? []) ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium text-ink-700"><?= h(t('Report.row_therapy')) ?></td>
                        <td><?= $bullets($report['treatmentConsiderations'] ?? []) ?></td>
                    </tr>
                    <tr>
                        <td class="font-medium text-ink-700"><?= h(t('Report.row_consults')) ?></td>
                        <td><?= $bullets($report['specialistReferrals'] ?? []) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Final recommendation -->
    <section class="card border-brand-200 bg-brand-50 p-3">
        <h3 class="text-xs font-semibold mb-1.5 flex items-center gap-1.5 text-brand-900 uppercase tracking-wide">
            <?= icon('check-circle', 'w-3.5 h-3.5 text-brand-700') ?>
            <span><?= h(t('Report.s10')) ?></span>
        </h3>
        <p class="text-sm leading-snug whitespace-pre-wrap text-ink-800"><?= h(($report['finalRecommendation'] ?? '') !== '' ? $report['finalRecommendation'] : $empty) ?></p>
        <?php $u = $report['uncertainty'] ?? 'high'; ?>
        <div class="mt-2 inline-flex items-center gap-1.5 text-[11px] text-ink-600 bg-white border border-ink-200 rounded-md px-2 py-0.5">
            <?= icon('info', 'w-3 h-3 text-ink-500') ?>
            <span><?= h(t('Report.uncertainty')) ?></span>
            <span class="font-semibold text-ink-900"><?= h($labelForLikelihood[$u] ?? $u) ?></span>
        </div>
    </section>

    <!-- Evidence summary + citations -->
    <?php if (($report['evidenceSummary'] ?? '') !== '' || !empty($report['citations'])): ?>
        <section class="card p-3">
            <h3 class="text-xs font-semibold mb-1.5 flex items-center gap-1.5 text-ink-900 uppercase tracking-wide">
                <?= icon('search', 'w-3.5 h-3.5 text-brand-700') ?>
                <span><?= h(t('Report.s9')) ?></span>
            </h3>
            <?php if (($report['evidenceSummary'] ?? '') !== ''): ?>
                <p class="text-sm leading-snug whitespace-pre-wrap text-ink-800"><?= h($report['evidenceSummary']) ?></p>
            <?php endif; ?>
            <?php if (!empty($report['citations'])): ?>
                <ol class="mt-2 space-y-1 text-xs">
                    <?php foreach ($report['citations'] as $c): ?>
                        <li class="flex items-start gap-1.5">
                            <span class="text-ink-400 mt-0.5"><?= icon('paperclip', 'w-3 h-3') ?></span>
                            <span>
                                <span class="font-medium text-ink-900"><?= h($c['title']) ?></span>
                                <?php if (!empty($c['source'])): ?><span class="text-ink-500"> — <?= h($c['source']) ?></span><?php endif; ?>
                                <?php if (!empty($c['url'])): ?> <a href="<?= h($c['url']) ?>" target="_blank" rel="noreferrer" class="text-brand-700 hover:underline font-medium"><?= h(t('Report.link')) ?></a><?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <!-- Disclaimer / metadata -->
    <section class="card border-ink-200 bg-ink-50 p-2.5">
        <h3 class="text-[11px] font-semibold mb-1 flex items-center gap-1.5 text-ink-700 uppercase tracking-wide">
            <?= icon('shield', 'w-3 h-3 text-ink-500') ?>
            <span><?= h(t('Report.s11')) ?></span>
        </h3>
        <p class="text-[11px] leading-snug text-ink-700"><?= h($report['safetyDisclaimer'] ?? '') ?></p>
        <?php if (!empty($report['generatedAt'])):
            $when = (new DateTime($report['generatedAt']))->format('Y-m-d H:i');
            $model = !empty($report['model']) ? ' · ' . $report['model'] : '';
        ?>
            <p class="text-[10px] text-ink-500 mt-1 flex items-center gap-1">
                <?= icon('clock', 'w-3 h-3') ?>
                <span><?= h(t('Report.generated', ['when' => $when])) . h($model) ?></span>
            </p>
        <?php endif; ?>
    </section>
</div>

<style>
.report-view .report-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; line-height: 1.35; }
.report-view .report-table th, .report-view .report-table td { border: 1px solid #e2e8f0; padding: 0.3rem 0.5rem; text-align: left; vertical-align: top; }
.report-view .report-table thead th { background: #f8fafc; font-weight: 600; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; }
.report-view .report-table tbody tr:hover { background: #fafafa; }
.report-view .report-table ul { margin: 0; padding-left: 0.9rem; list-style: disc outside; }
.report-view .report-table ul li { margin: 0; padding-left: 0.05em; line-height: 1.3; }
.report-view .report-table ul li::marker { color: rgba(15, 23, 42, 0.5); font-size: 0.7em; }
</style>
