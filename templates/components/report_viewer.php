<?php
// $report (associative array matching schema)
$pillForLikelihood = [
    'high' => 'bg-red-50 text-red-700 border-red-200',
    'medium' => 'bg-amber-50 text-amber-700 border-amber-200',
    'low' => 'bg-ink-100 text-ink-700 border-ink-200',
];
$labelForLikelihood = [
    'high' => t('Report.likelihoodHigh'),
    'medium' => t('Report.likelihoodMedium'),
    'low' => t('Report.likelihoodLow'),
];
$empty = t('Report.empty');
$section = function (string $title, callable $body, string $tone = 'default') {
    $cls = match ($tone) {
        'danger' => 'border-red-200 bg-red-50',
        'info' => 'border-brand-200 bg-brand-50',
        'muted' => 'border-ink-100 bg-ink-50',
        default => '',
    };
    echo '<section class="card p-4 ' . $cls . '">';
    echo '<h3 class="text-sm font-semibold mb-2">' . h($title) . '</h3><div>';
    $body();
    echo '</div></section>';
};
$bullets = function (array $items, bool $small = false) use ($empty) {
    if (!$items) { echo '<p class="text-sm text-ink-500">' . h($empty) . '</p>'; return; }
    $size = $small ? 'text-xs' : 'text-sm';
    echo '<ul class="list-disc pl-5 space-y-1 ' . $size . '">';
    foreach ($items as $it) echo '<li>' . h((string) $it) . '</li>';
    echo '</ul>';
};
?>
<div class="space-y-4">
    <?php if (!empty($report['demoMode'])): ?>
        <div class="card bg-amber-50 border-amber-200 p-3 text-xs text-amber-900"><?= h(t('Report.demoBanner')) ?></div>
    <?php endif; ?>

    <?php if (!empty($report['needsFollowUp']) && !empty($report['followUpQuestions'])):
        $section(t('Report.followUpTitle'), function () use ($bullets, $report) { $bullets($report['followUpQuestions']); }, 'info');
    endif; ?>

    <?php if (!empty($report['redFlags'])):
        $section(t('Report.redFlagsTitle'), function () use ($bullets, $report) { $bullets($report['redFlags']); }, 'danger');
    endif; ?>

    <?php $section(t('Report.s1'), function () use ($report, $empty) {
        echo '<p class="text-sm leading-relaxed whitespace-pre-wrap">' . h($report['caseSummary'] !== '' ? $report['caseSummary'] : $empty) . '</p>';
    }); ?>

    <?php $section(t('Report.s2'), function () use ($bullets, $report) { $bullets($report['keyFindings'] ?? []); }); ?>
    <?php $section(t('Report.s3'), function () use ($bullets, $report) { $bullets($report['missingInformation'] ?? []); }); ?>

    <?php $section(t('Report.s4'), function () use ($bullets, $report, $empty, $pillForLikelihood, $labelForLikelihood) {
        $dx = $report['differentialDiagnosis'] ?? [];
        if (!$dx) { echo '<p class="text-sm text-ink-500">' . h($empty) . '</p>'; return; }
        echo '<ol class="space-y-3">';
        foreach ($dx as $i => $d):
            $like = $d['likelihood'] ?? 'low';
            $pill = $pillForLikelihood[$like] ?? '';
            $label = $labelForLikelihood[$like] ?? $like;
        ?>
            <li class="border border-ink-100 rounded-lg p-3">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-medium text-sm"><?= ($i + 1) ?>. <?= h((string) ($d['diagnosis'] ?? '')) ?></div>
                    <span class="pill border <?= $pill ?>"><?= h($label) ?></span>
                </div>
                <?php if (!empty($d['supportingEvidence'])): ?>
                    <div class="mt-2">
                        <div class="text-[11px] uppercase tracking-wide text-ink-500"><?= h(t('Report.supportingEvidence')) ?></div>
                        <?php $bullets($d['supportingEvidence'], true); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($d['evidenceAgainst'])): ?>
                    <div class="mt-2">
                        <div class="text-[11px] uppercase tracking-wide text-ink-500"><?= h(t('Report.evidenceAgainst')) ?></div>
                        <?php $bullets($d['evidenceAgainst'], true); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($d['recommendedNextStep'])): ?>
                    <div class="mt-2 text-sm">
                        <span class="text-ink-500 text-xs uppercase tracking-wide"><?= h(t('Report.nextStep')) ?> </span>
                        <?= h($d['recommendedNextStep']) ?>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach;
        echo '</ol>';
    }); ?>

    <?php $section(t('Report.s5'), function () use ($bullets, $report) { $bullets($report['redFlags'] ?? []); }); ?>
    <?php $section(t('Report.s6'), function () use ($bullets, $report) { $bullets($report['recommendedTests'] ?? []); }); ?>
    <?php $section(t('Report.s7'), function () use ($bullets, $report) { $bullets($report['treatmentConsiderations'] ?? []); }); ?>
    <?php $section(t('Report.s8'), function () use ($bullets, $report) { $bullets($report['specialistReferrals'] ?? []); }); ?>

    <?php $section(t('Report.s9'), function () use ($report, $empty) {
        echo '<p class="text-sm leading-relaxed whitespace-pre-wrap">' . h(($report['evidenceSummary'] ?? '') !== '' ? $report['evidenceSummary'] : $empty) . '</p>';
        if (!empty($report['citations'])):
            echo '<ol class="mt-3 space-y-1 text-sm">';
            foreach ($report['citations'] as $c) {
                echo '<li class="flex items-start gap-2"><span><span class="font-medium">' . h($c['title']) . '</span>';
                if (!empty($c['source'])) echo '<span class="text-ink-500"> — ' . h($c['source']) . '</span>';
                if (!empty($c['url'])) echo ' <a href="' . h($c['url']) . '" target="_blank" rel="noreferrer" class="text-brand-700 hover:underline">' . h(t('Report.link')) . '</a>';
                echo '</span></li>';
            }
            echo '</ol>';
        endif;
    }); ?>

    <?php $section(t('Report.s10'), function () use ($report, $empty, $labelForLikelihood) {
        echo '<p class="text-sm leading-relaxed whitespace-pre-wrap">' . h(($report['finalRecommendation'] ?? '') !== '' ? $report['finalRecommendation'] : $empty) . '</p>';
        $u = $report['uncertainty'] ?? 'high';
        echo '<div class="mt-3 inline-flex items-center gap-2 text-xs text-ink-500">' . h(t('Report.uncertainty')) . ' <span class="font-medium">' . h($labelForLikelihood[$u] ?? $u) . '</span></div>';
    }); ?>

    <?php $section(t('Report.s11'), function () use ($report) {
        echo '<p class="text-xs leading-relaxed text-ink-700">' . h($report['safetyDisclaimer'] ?? '') . '</p>';
        if (!empty($report['generatedAt'])) {
            $when = (new DateTime($report['generatedAt']))->format('Y-m-d H:i');
            $model = !empty($report['model']) ? ' · ' . $report['model'] : '';
            echo '<p class="text-[11px] text-ink-400 mt-2">' . h(t('Report.generated', ['when' => $when])) . h($model) . '</p>';
        }
    }, 'muted'); ?>
</div>
