<?php
declare(strict_types=1);

// LLM router: picks an adapter based on LLM_PROVIDER.
//
// Each adapter exposes two functions:
//   xxx_adapter_enabled(): bool
//   xxx_adapter_model(): string
//   xxx_adapter_complete(array $opts): string
//   xxx_adapter_web_search(array $opts): ?array

require __DIR__ . '/openai.php';
require __DIR__ . '/anthropic.php';
require __DIR__ . '/gemini.php';

class LLMUnavailableError extends RuntimeException {}

/**
 * Token usage from the most recent llm_complete() call.
 * Adapters call llm_set_last_usage() after a successful response; the
 * orchestrator reads llm_last_usage() to log cost into the audit trail.
 *
 * @var array{provider:string, model:string, input_tokens:int, output_tokens:int}|null
 */
function llm_last_usage(): ?array {
    static $shared = null;
    $args = func_get_args();
    if ($args) { $shared = $args[0]; return null; }
    return $shared;
}

function llm_set_last_usage(string $provider, string $model, int $input, int $output): void {
    llm_last_usage([
        'provider' => $provider,
        'model' => $model,
        'input_tokens' => $input,
        'output_tokens' => $output,
    ]);
}

function llm_clear_last_usage(): void { llm_last_usage(null); }

function llm_provider(): string {
    $raw = strtolower(trim((string) env('LLM_PROVIDER', 'openai')));
    return in_array($raw, ['openai', 'anthropic', 'gemini'], true) ? $raw : 'openai';
}

function llm_enabled(): bool {
    return match (llm_provider()) {
        'openai' => openai_adapter_enabled(),
        'anthropic' => anthropic_adapter_enabled(),
        'gemini' => gemini_adapter_enabled(),
    };
}

function llm_model(): string {
    return match (llm_provider()) {
        'openai' => openai_adapter_model(),
        'anthropic' => anthropic_adapter_model(),
        'gemini' => gemini_adapter_model(),
    };
}

function llm_model_label(): string {
    return llm_provider() . ':' . llm_model();
}

/**
 * Models the admin can pick from, for a given provider (defaults to the
 * active provider). Returns a list of {id,label,description,source} with
 * 'source' = "api" when fetched live (Gemini) or "fallback" when hardcoded.
 *
 * @return array<int, array{id: string, label: string, description: ?string, source: string}>
 */
function llm_list_models(?string $provider = null): array {
    return match ($provider ?? llm_provider()) {
        'openai' => openai_list_models(),
        'anthropic' => anthropic_list_models(),
        'gemini' => gemini_list_models(),
        default => [],
    };
}

/** Setting-key used to override the model for the given provider. */
function llm_model_setting_key(string $provider): string {
    return 'llm.' . $provider . '.model';
}

/** True iff the active model came from the DB override rather than env/default. */
function llm_model_is_overridden(?string $provider = null): bool {
    $p = $provider ?? llm_provider();
    $v = setting_get(llm_model_setting_key($p));
    return $v !== null && $v !== '';
}

/**
 * @param array{system: string, messages: array<int, array{role: string, content: string}>, max_tokens?: int, temperature?: float, json_mode?: bool} $opts
 */
function llm_complete(array $opts): string {
    return match (llm_provider()) {
        'openai' => openai_adapter_complete($opts),
        'anthropic' => anthropic_adapter_complete($opts),
        'gemini' => gemini_adapter_complete($opts),
    };
}

/**
 * @param array{specialty: string, query: string, max_results?: int, allowed_domains: string[]} $opts
 * @return array<int, array{title: string, source: string, url: ?string, snippet?: string, is_mock: bool}>|null
 */
function llm_web_search(array $opts): ?array {
    return match (llm_provider()) {
        'openai' => openai_adapter_web_search($opts),
        'anthropic' => anthropic_adapter_web_search($opts),
        'gemini' => gemini_adapter_web_search($opts),
    };
}

// ---------------- Shared HTTP helper ----------------

/**
 * POST JSON via curl. Returns decoded JSON array on success, throws on HTTP error.
 *
 * @throws RuntimeException
 */
function http_post_json(string $url, array $body, array $headers = [], int $timeout = 120): array {
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl_init failed');
    $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) throw new RuntimeException('json_encode failed');
    $allHeaders = array_merge(['Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $allHeaders,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 30,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        throw new RuntimeException('curl error: ' . curl_error($ch));
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $decoded = json_decode((string) $resp, true);
    if ($status >= 400) {
        $msg = is_array($decoded) ? json_encode($decoded) : (string) $resp;
        throw new RuntimeException("HTTP $status from $url: " . substr((string) $msg, 0, 2000));
    }
    if (!is_array($decoded)) {
        throw new RuntimeException("invalid JSON response from $url");
    }
    return $decoded;
}

/**
 * GET JSON via curl. Returns decoded JSON array on success, throws on HTTP error.
 *
 * @throws RuntimeException
 */
function http_get_json(string $url, array $headers = [], int $timeout = 30): array {
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('curl_init failed');
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $resp = curl_exec($ch);
    if ($resp === false) {
        throw new RuntimeException('curl error: ' . curl_error($ch));
    }
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $decoded = json_decode((string) $resp, true);
    if ($status >= 400) {
        $msg = is_array($decoded) ? json_encode($decoded) : (string) $resp;
        throw new RuntimeException("HTTP $status from $url: " . substr((string) $msg, 0, 2000));
    }
    if (!is_array($decoded)) {
        throw new RuntimeException("invalid JSON response from $url");
    }
    return $decoded;
}

// ---------------- Shared JSON helpers ----------------

function parse_json_array_loose(string $text): ?array {
    $cleaned = trim(preg_replace('/^```(?:json)?\s*/i', '', $text) ?? '');
    $cleaned = trim(preg_replace('/```\s*$/i', '', $cleaned) ?? '');
    $v = json_decode($cleaned, true);
    if (is_array($v)) return $v;
    $start = strpos($cleaned, '[');
    $end = strrpos($cleaned, ']');
    if ($start !== false && $end !== false && $end > $start) {
        $v = json_decode(substr($cleaned, $start, $end - $start + 1), true);
        if (is_array($v)) return $v;
    }
    return null;
}
