<?php
declare(strict_types=1);

/**
 * Aggregate LLM token usage per doctor by scanning audit_logs where the
 * detail JSON contains a `usage` payload (set by message.reply / report.generate).
 *
 * @return array<int, array{input_tokens:int, output_tokens:int, calls:int, by_model: array<string, array{input_tokens:int, output_tokens:int, calls:int}>}>
 */
function aggregate_token_usage_by_doctor(): array {
    $rows = db_all(
        "SELECT doctor_id, detail FROM audit_logs
         WHERE detail IS NOT NULL AND doctor_id IS NOT NULL
           AND action IN ('message.reply', 'report.generate')"
    );
    $out = [];
    foreach ($rows as $r) {
        $did = (int) $r['doctor_id'];
        $d = json_decode((string) $r['detail'], true);
        if (!is_array($d) || empty($d['usage']) || !is_array($d['usage'])) continue;
        $u = $d['usage'];
        $in = (int) ($u['input_tokens'] ?? 0);
        $outTokens = (int) ($u['output_tokens'] ?? 0);
        $model = trim(((string) ($u['provider'] ?? '?')) . ':' . ((string) ($u['model'] ?? '?')));
        if (!isset($out[$did])) {
            $out[$did] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'by_model' => []];
        }
        $out[$did]['input_tokens'] += $in;
        $out[$did]['output_tokens'] += $outTokens;
        $out[$did]['calls'] += 1;
        if (!isset($out[$did]['by_model'][$model])) {
            $out[$did]['by_model'][$model] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
        }
        $out[$did]['by_model'][$model]['input_tokens'] += $in;
        $out[$did]['by_model'][$model]['output_tokens'] += $outTokens;
        $out[$did]['by_model'][$model]['calls'] += 1;
    }
    return $out;
}

function audit(string $action, ?int $doctorId = null, ?int $caseId = null, $detail = null): void {
    try {
        db_exec(
            'INSERT INTO audit_logs (doctor_id, case_id, action, detail, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $doctorId,
                $caseId,
                $action,
                $detail !== null ? json_encode($detail) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );
    } catch (Throwable $e) {
        // never fail a request because of audit
        error_log('audit failed: ' . $e->getMessage());
    }
}
