<?php
declare(strict_types=1);

/**
 * Approximate per-million-token pricing in USD, keyed by case-insensitive
 * substring match against "provider:model". Used only for rough budgeting
 * in the admin UI — returns null if the model isn't recognised.
 *
 * @return array{in: float, out: float}|null
 */
function llm_pricing_for(string $providerModel): ?array {
    static $rates = [
        // OpenAI
        'openai:gpt-4o-mini'         => ['in' => 0.15,  'out' => 0.60],
        'openai:gpt-4o'              => ['in' => 2.50,  'out' => 10.00],
        'openai:gpt-4.1-mini'        => ['in' => 0.40,  'out' => 1.60],
        'openai:gpt-4.1'             => ['in' => 2.00,  'out' => 8.00],
        'openai:o3-mini'             => ['in' => 1.10,  'out' => 4.40],
        'openai:o3'                  => ['in' => 2.00,  'out' => 8.00],
        // Anthropic
        'anthropic:claude-haiku-4'   => ['in' => 1.00,  'out' => 5.00],
        'anthropic:claude-sonnet-4'  => ['in' => 3.00,  'out' => 15.00],
        'anthropic:claude-opus-4'    => ['in' => 15.00, 'out' => 75.00],
        // Google
        'gemini:gemini-2.5-flash'    => ['in' => 0.30,  'out' => 2.50],
        'gemini:gemini-2.5-pro'      => ['in' => 1.25,  'out' => 10.00],
        'gemini:gemini-2.0-flash'    => ['in' => 0.10,  'out' => 0.40],
    ];
    $key = strtolower(trim($providerModel));
    if (isset($rates[$key])) return $rates[$key];
    foreach ($rates as $needle => $r) {
        if (str_starts_with($key, $needle)) return $r;
    }
    return null;
}

function llm_estimate_cost_usd(int $inputTokens, int $outputTokens, string $providerModel): ?float {
    $rate = llm_pricing_for($providerModel);
    if ($rate === null) return null;
    return ($inputTokens / 1_000_000.0) * $rate['in']
         + ($outputTokens / 1_000_000.0) * $rate['out'];
}

/**
 * Aggregate LLM token usage across the audit log. Single pass over the
 * relevant rows, returning totals plus per-day / per-action / per-model /
 * per-doctor breakdowns. Restrict to one doctor with $doctorId, or omit
 * for the global view.
 *
 * by_day is keyed by YYYY-MM-DD and is padded with zero-buckets for the
 * last $days days so charts have a continuous x-axis.
 *
 * @return array{
 *   totals: array{input_tokens:int, output_tokens:int, calls:int, est_cost_usd: float, est_cost_known: bool},
 *   by_doctor: array<int, array{input_tokens:int, output_tokens:int, calls:int, est_cost_usd: float}>,
 *   by_model:  array<string, array{input_tokens:int, output_tokens:int, calls:int, est_cost_usd: float, est_cost_known: bool}>,
 *   by_action: array<string, array{input_tokens:int, output_tokens:int, calls:int}>,
 *   by_day:    array<string, array{input_tokens:int, output_tokens:int, calls:int}>,
 *   day_labels: string[]
 * }
 */
function aggregate_token_usage(?int $doctorId = null, int $days = 30): array {
    $sql = "SELECT doctor_id, action, detail, created_at FROM audit_logs
            WHERE detail IS NOT NULL AND doctor_id IS NOT NULL
              AND action IN ('message.reply', 'report.generate')";
    $params = [];
    if ($doctorId !== null) {
        $sql .= ' AND doctor_id = ?';
        $params[] = $doctorId;
    }
    $rows = db_all($sql, $params);

    // Pre-build day buckets for the last $days days (oldest -> newest).
    $dayLabels = [];
    $byDay = [];
    $today = new DateTimeImmutable('today');
    for ($i = $days - 1; $i >= 0; $i--) {
        $key = $today->modify("-$i day")->format('Y-m-d');
        $dayLabels[] = $key;
        $byDay[$key] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
    }

    $totals = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
    $byDoctor = [];
    $byModel = [];
    $byAction = [];
    $costKnownAll = true;
    $costTotal = 0.0;

    foreach ($rows as $r) {
        $d = json_decode((string) $r['detail'], true);
        if (!is_array($d) || empty($d['usage']) || !is_array($d['usage'])) continue;
        $u = $d['usage'];
        $in = (int) ($u['input_tokens'] ?? 0);
        $outTok = (int) ($u['output_tokens'] ?? 0);
        $model = trim(((string) ($u['provider'] ?? '?')) . ':' . ((string) ($u['model'] ?? '?')));
        $did = (int) $r['doctor_id'];
        $action = (string) $r['action'];
        $cost = llm_estimate_cost_usd($in, $outTok, $model);
        if ($cost === null) { $costKnownAll = false; $cost = 0.0; }

        $totals['input_tokens'] += $in;
        $totals['output_tokens'] += $outTok;
        $totals['calls'] += 1;
        $costTotal += $cost;

        if (!isset($byDoctor[$did])) {
            $byDoctor[$did] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'est_cost_usd' => 0.0];
        }
        $byDoctor[$did]['input_tokens'] += $in;
        $byDoctor[$did]['output_tokens'] += $outTok;
        $byDoctor[$did]['calls'] += 1;
        $byDoctor[$did]['est_cost_usd'] += $cost;

        if (!isset($byModel[$model])) {
            $byModel[$model] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'est_cost_usd' => 0.0, 'est_cost_known' => llm_pricing_for($model) !== null];
        }
        $byModel[$model]['input_tokens'] += $in;
        $byModel[$model]['output_tokens'] += $outTok;
        $byModel[$model]['calls'] += 1;
        $byModel[$model]['est_cost_usd'] += $cost;

        if (!isset($byAction[$action])) {
            $byAction[$action] = ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
        }
        $byAction[$action]['input_tokens'] += $in;
        $byAction[$action]['output_tokens'] += $outTok;
        $byAction[$action]['calls'] += 1;

        $dayKey = substr((string) $r['created_at'], 0, 10);
        if (isset($byDay[$dayKey])) {
            $byDay[$dayKey]['input_tokens'] += $in;
            $byDay[$dayKey]['output_tokens'] += $outTok;
            $byDay[$dayKey]['calls'] += 1;
        }
    }

    // Stable orderings.
    uasort($byModel, fn($a, $b) => ($b['input_tokens'] + $b['output_tokens']) <=> ($a['input_tokens'] + $a['output_tokens']));
    uasort($byAction, fn($a, $b) => ($b['input_tokens'] + $b['output_tokens']) <=> ($a['input_tokens'] + $a['output_tokens']));

    return [
        'totals' => $totals + ['est_cost_usd' => $costTotal, 'est_cost_known' => $costKnownAll],
        'by_doctor' => $byDoctor,
        'by_model' => $byModel,
        'by_action' => $byAction,
        'by_day' => $byDay,
        'day_labels' => $dayLabels,
    ];
}

/**
 * Backwards-compat wrapper: returns per-doctor totals keyed by doctor id,
 * with an inner by_model breakdown — matches the older shape used by the
 * admin doctor list.
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

/** Rolling-window token-limit configuration. */
const TOKEN_LIMIT_WINDOWS = [
    'minute' => ['column' => 'tokens_per_minute_limit', 'sqlite_offset' => '-1 minute'],
    'day'    => ['column' => 'tokens_per_day_limit',    'sqlite_offset' => '-1 day'],
    'week'   => ['column' => 'tokens_per_week_limit',   'sqlite_offset' => '-7 days'],
];

/**
 * Plan tiers and their token allowances per rolling window.
 * NULL in a slot = no cap for that window on that tier.
 * Adjust the numbers freely; 'max' is intentionally unlimited.
 */
const TIER_LIMITS = [
    'free' => ['minute' =>   5000, 'day' =>   50000, 'week' =>   200000],
    'plus' => ['minute' =>  25000, 'day' =>  300000, 'week' =>  1500000],
    'pro'  => ['minute' => 100000, 'day' => 1500000, 'week' => 10000000],
    'max'  => ['minute' =>   null, 'day' =>    null, 'week' =>     null],
];
const TIERS = ['free', 'plus', 'pro', 'max'];

function tier_default(): string { return 'free'; }
function tier_is_valid(string $t): bool { return in_array($t, TIERS, true); }
function tier_limits(string $tier): array {
    return TIER_LIMITS[$tier] ?? TIER_LIMITS['free'];
}

/**
 * Effective per-window cap for a doctor. Reads tier first; falls back to
 * the legacy per-doctor override column when the tier slot is null (allowing
 * 'max' to remain unlimited even if a legacy value lingers — pass-through).
 */
function doctor_effective_limit(array $doctorRow, string $window): ?int {
    $tier = $doctorRow['tier'] ?? tier_default();
    $tl = tier_limits(is_string($tier) ? $tier : tier_default());
    return $tl[$window] ?? null;
}

/**
 * Sum (input + output) tokens consumed by a doctor since an absolute SQLite
 * datetime modifier (e.g. '-1 minute'). UTC.
 */
function doctor_token_usage_window(int $doctorId, string $sqliteOffset): int {
    $rows = db_all(
        "SELECT detail FROM audit_logs
         WHERE doctor_id = ? AND detail IS NOT NULL
           AND action IN ('message.reply', 'report.generate')
           AND created_at >= datetime('now', ?)",
        [$doctorId, $sqliteOffset]
    );
    $total = 0;
    foreach ($rows as $r) {
        $d = json_decode((string) $r['detail'], true);
        if (!is_array($d) || empty($d['usage']) || !is_array($d['usage'])) continue;
        $u = $d['usage'];
        $total += (int) ($u['input_tokens'] ?? 0) + (int) ($u['output_tokens'] ?? 0);
    }
    return $total;
}

/**
 * Returns one row of limits + live usage for a doctor, keyed by window name.
 * Each entry: ['limit' => ?int, 'used' => int, 'used_pct' => ?int, 'remaining_pct' => ?int].
 * 'limit' is null when no cap is set; 'used_pct' and 'remaining_pct' are null in that case.
 */
function doctor_token_limits_status(int $doctorId): array {
    $row = db_fetch('SELECT tier FROM doctors WHERE id = ?', [$doctorId]) ?? [];
    $out = [];
    foreach (TOKEN_LIMIT_WINDOWS as $name => $cfg) {
        $limit = doctor_effective_limit($row, $name);
        $used  = doctor_token_usage_window($doctorId, $cfg['sqlite_offset']);
        $usedPct = ($limit !== null && $limit > 0) ? min(100, (int) round(($used / $limit) * 100)) : null;
        $remPct  = $usedPct === null ? null : max(0, 100 - $usedPct);
        $out[$name] = [
            'limit' => $limit,
            'used'  => $used,
            'used_pct' => $usedPct,
            'remaining_pct' => $remPct,
        ];
    }
    return $out;
}

/**
 * Returns true if any of the doctor's configured rate-limit windows is at/over capacity.
 * Throws TokenLimitExceeded carrying the offending window name.
 * Soft check — does not reserve quota for the upcoming call.
 */
function assert_doctor_within_token_limit(int $doctorId): void {
    $status = doctor_token_limits_status($doctorId);
    foreach ($status as $window => $s) {
        $limit = $s['limit'];
        if ($limit === null || $limit <= 0) continue;
        if ($s['used'] >= $limit) {
            throw new TokenLimitExceeded($window, $s['used'], $limit);
        }
    }
}

class TokenLimitExceeded extends RuntimeException {
    public string $window;
    public int $used;
    public int $limit;
    public function __construct(string $window, int $used, int $limit) {
        $this->window = $window;
        $this->used = $used;
        $this->limit = $limit;
        parent::__construct("Token limit reached for {$window} window ({$used} of {$limit})");
    }
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
