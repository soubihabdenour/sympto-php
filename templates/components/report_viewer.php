<?php
require_once TEMPLATES_DIR . '/components/icons.php';
// $report (associative array matching schema)
$pillForLikelihood = [
    'high' => 'bg-red-50 text-red-700 ring-1 ring-red-200',
    'medium' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    'low' => 'bg-ink-100 text-ink-700 ring-1 ring-ink-200',
];
$labelForLikelihood = [
    'high' => t('Report.likelihoodHigh'),
    'medium' => t('Report.likelihoodMedium'),
    'low' => t('Report.likelihoodLow'),
];
$empty = t('Report.empty');
$section = function (string $title, callable $body, string $tone = 'default', string $iconName = 'clipboard') {
    $cls = match ($tone) {
        'danger' => 'border-red-200 bg-red-50',
        'info'   => 'border-brand-200 bg-brand-50',
        'muted'  => 'border-ink-200 bg-ink-50',
        default  => '',
    };
    $iconTone = match ($tone) {
        'danger' => 'text-red-700',
        'info'   => 'text-brand-700',
        'muted'  => 'text-ink-500',
        default  => 'text-brand-700',
    };
    echo '<section class="card p-5 ' . $cls . '">';
    echo '<h3 class="text-sm font-semibold mb-3 flex items-center gap-2 text-ink-900"><span class="' . $iconTone . '">' . icon($iconName, 'w-4 h-4') . '</span><span>' . h($title) . '</span></h3><div>';
    $body();
    echo '</div></section>';
};
$bullets = function (array $items, bool $small = false) use ($empty) {
    if (!$items) { echo '<p class="text-sm text-ink-500">' . h($empty) . '</p>'; return; }
    $size = $small ? 'text-xs' : 'text-sm';
    echo '<ul class="space-y-1.5 ' . $size . '">';
    foreach ($items as $it) {
        echo '<li class="flex items-start gap-2"><span class="w-1 h-1 rounded-full bg-ink-400 mt-2 shrink-0"></span><span>' . h((string) $it) . '</span></li>';
    }
    echo '</ul>';
};
?>
<div class="space-y-4">
    <?php if (!empty($report['demoMode'])): ?>
        <div class="card bg-amber-50 border-amber-200 p-3 text-xs text-amber-900 flex items-start gap-2">
            <?= icon('alert', 'w-4 h-4 mt-0.5 shrink-0 text-amber-700') ?>
            <span><?= h(t('Report.demoBanner')) ?></span>
        </div>
    <?php endif; ?>

    <?php if (!empty($report['needsFollowUp']) && !empty($report['followUpQuestions'])):
        $section(t('Report.followUpTitle'), function () use ($bullets, $report) { $bullets($report['followUpQuestions']); }, 'info', 'message');
    endif; ?>

    <?php if (!empty($report['redFlags'])):
        $section(t('Report.redFlagsTitle'), function () use ($bullets, $report) { $bullets($report['redFlags']); }, 'danger', 'flag');
    endif; ?>

    <?php $section(t('Report.s1'), function () use ($report, $empty) {
        echo '<p class="text-sm leading-relaxed whitespace-pre-wrap text-ink-800">' . h($report['caseSummary'] !== '' ? $report['caseSummary'] : $empty) . '</p>';
    }, 'default', 'file-text'); ?>

    <?php $section(t('Report.s2'), function () use ($bullets, $report) { $bullets($report['keyFindings'] ?? []); }, 'default', 'check-circle'); ?>
    <?php $section(t('Report.s3'), function () use ($bullets, $report) { $bullets($report['missingInformation'] ?? []); }, 'default', 'info'); ?>

    <?php $section(t('Report.s4'), function () use ($bullets, $report, $empty, $pillForLikelihood, $labelForLikelihood) {
        $dx = $report['differentialDiagnosis'] ?? [];
        if (!$dx) { echo '<p class="text-sm text-ink-500">' . h($empty) . '</p>'; return; }
        echo '<ol class="space-y-3">';
        foreach ($dx as $i => $d):
            $like = $d['likelihood'] ?? 'low';
            $pill = $pillForLikelihood[$like] ?? '';
            $label = $labelForLikelihood[$like] ?? $like;
        ?>
            <li class="border border-ink-200 rounded-lg p-4 bg-white">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold text-sm text-ink-900">
                        <span class="inline-block w-6 h-6 rounded-full bg-brand-50 text-brand-700 text-xs grid place-items-center mr-1.5"><?= ($i + 1) ?></span>
                        <?= h((string) ($d['diagnosis'] ?? '')) ?>
                    </div>
                    <span class="pill <?= $pill ?>"><?= h($label) ?></span>
                </div>
                <?php if (!empty($d['supportingEvidence'])): ?>
                    <div class="mt-3">
                        <div class="text-[11px] uppercase tracking-wide text-vital-700 font-semibold flex items-center gap-1 mb-1.5">
                            <?= icon('check', 'w-3 h-3') ?>
                            <?= h(t('Report.supportingEvidence')) ?>
                        </div>
                        <?php $bullets($d['supportingEvidence'], true); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($d['evidenceAgainst'])): ?>
                    <div class="mt-3">
                        <div class="text-[11px] uppercase tracking-wide text-red-700 font-semibold flex items-center gap-1 mb-1.5">
                            <?= icon('x', 'w-3 h-3') ?>
                            <?= h(t('Report.evidenceAgainst')) ?>
                        </div>
                        <?php $bullets($d['evidenceAgainst'], true); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($d['recommendedNextStep'])): ?>
                    <div class="mt-3 text-sm bg-brand-50 border border-brand-100 rounded-md p-2.5">
                        <span class="text-brand-700 text-[11px] uppercase tracking-wide font-semibold inline-flex items-center gap-1 mr-1">
                            <?= icon('arrow-right', 'w-3 h-3') ?>
                            <?= h(t('Report.nextStep')) ?>
                        </span>
                        <span class="text-ink-800"><?= h($d['recommendedNextStep']) ?></span>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach;
        echo '</ol>';
    }, 'default', 'sparkles'); ?>

    <?php $section(t('Report.s5'), function () use ($bullets, $report) { $bullets($report['redFlags'] ?? []); }, 'default', 'flag'); ?>
    <?php $section(t('Report.s6'), function () use ($bullets, $report) { $bullets($report['recommendedTests'] ?? []); }, 'default', 'flask'); ?>
    <?php $section(t('Report.s7'), function () use ($bullets, $report) { $bullets($report['treatmentConsiderations'] ?? []); }, 'default', 'pulse'); ?>
    <?php $section(t('Report.s8'), function () use ($bullets, $report) { $bullets($report['specialistReferrals'] ?? []); }, 'default', 'users'); ?>

    <?php $section(t('Report.s9'), function () use ($report, $empty) {
        echo '<p class="text-sm leading-relaxed whitespace-pre-wrap text-ink-800">' . h(($report['evidenceSummary'] ?? '') !== '' ? $report['evidenceSummary'] : $empty) . '</p>';
        if (!empty($report['citations'])):
            echo '<ol class="mt-3 space-y-1.5 text-sm">';
            foreach ($report['citations'] as $c) {
                echo '<li class="flex items-start gap-2"><span class="text-ink-400 mt-0.5">' . icon('paperclip', 'w-3.5 h-3.5') . '</span><span><span class="font-medium text-ink-900">' . h($c['title']) . '</span>';
                if (!empty($c['source'])) echo '<span class="text-ink-500"> — ' . h($c['source']) . '</span>';
                if (!empty($c['url'])) echo ' <a href="' . h($c['url']) . '" target="_blank" rel="noreferrer" class="text-brand-700 hover:underline font-medium">' . h(t('Report.link')) . '</a>';
                echo '</span></li>';
            }
            echo '</ol>';
        endif;
    }, 'default', 'search'); ?>

    <?php $section(t('Report.s10'), function () use ($report, $empty, $labelForLikelihood) {
        echo '<p class="text-sm leading-relaxed whitespace-pre-wrap text-ink-800">' . h(($report['finalRecommendation'] ?? '') !== '' ? $report['finalRecommendation'] : $empty) . '</p>';
        $u = $report['uncertainty'] ?? 'high';
        echo '<div class="mt-3 inline-flex items-center gap-2 text-xs text-ink-600 bg-ink-50 border border-ink-200 rounded-md px-2.5 py-1">' . icon('info', 'w-3.5 h-3.5 text-ink-500') . '<span>' . h(t('Report.uncertainty')) . '</span><span class="font-semibold text-ink-900">' . h($labelForLikelihood[$u] ?? $u) . '</span></div>';
    }, 'info', 'check-circle'); ?>

    <?php $section(t('Report.s11'), function () use ($report) {
        echo '<p class="text-xs leading-relaxed text-ink-700">' . h($report['safetyDisclaimer'] ?? '') . '</p>';
        if (!empty($report['generatedAt'])) {
            $when = (new DateTime($report['generatedAt']))->format('Y-m-d H:i');
            $model = !empty($report['model']) ? ' · ' . $report['model'] : '';
            echo '<p class="text-[11px] text-ink-500 mt-2 flex items-center gap-1">' . icon('clock', 'w-3 h-3') . '<span>' . h(t('Report.generated', ['when' => $when])) . h($model) . '</span></p>';
        }
    }, 'muted', 'shield'); ?>
</div>
