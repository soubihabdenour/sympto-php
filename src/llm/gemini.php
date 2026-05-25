<?php
declare(strict_types=1);

function gemini_adapter_enabled(): bool {
    return (string) env('GEMINI_API_KEY', '') !== '';
}

function gemini_adapter_model(): string {
    $override = setting_get('llm.gemini.model');
    if ($override !== null && $override !== '') return $override;
    return (string) env('GEMINI_MODEL', 'gemini-2.5-flash');
}

/**
 * True when the model ID belongs to the set generally available for text
 * chat on the Google AI Studio *free* tier. We intentionally exclude
 * image-gen, TTS, audio, video, computer-use, deep-think, and embedding
 * variants — they're either a different product or only available to paid
 * customers / preview programmes.
 *
 * The matcher is pattern-based so newly-released snapshots within the
 * supported families (e.g. a future "gemini-2.5-flash-001") are accepted
 * without code changes.
 */
function gemini_is_free_aistudio_chat(string $id): bool {
    $id = strtolower($id);
    // Hard exclusions: specialty / non-chat / paid-only modalities.
    $excludePatterns = [
        '/image/i',           // gemini-2.5-flash-image, imagen-*
        '/(^|\W)tts(\W|$)/i', // -tts variants
        '/native-audio/i',
        '/-audio/i',
        '/video/i',           // veo-*, -video-
        '/computer-use/i',
        '/deep-think/i',
        '/embedding/i',
        '/-aqa/i',
        '/imagen/i',
        '/-veo/i',
    ];
    foreach ($excludePatterns as $rx) {
        if (preg_match($rx, $id)) return false;
    }
    // Allow patterns: Gemini chat families, Gemma open chat models, LearnLM.
    // Allow any trailing snapshot/preview/exp/date suffix — non-chat variants
    // (image, tts, computer-use, etc.) are filtered out above.
    $allowPatterns = [
        '/^gemini-2\.5-(pro|flash)(-lite)?(-.+)?$/',
        '/^gemini-2\.0-(pro|flash)(-lite)?(-.+)?$/',
        '/^gemini-1\.5-(pro|flash)(-8b)?(-.+)?$/',
        '/^gemma-3(n)?-.*-it$/',
        '/^learnlm-.*$/',
    ];
    foreach ($allowPatterns as $rx) {
        if (preg_match($rx, $id)) return true;
    }
    return false;
}

/**
 * Curated fallback list of Gemini chat models available on the Google
 * AI Studio free tier. Used when the live API can't be reached.
 *
 * @return array<int, array{id: string, label: string, description: ?string}>
 */
function gemini_fallback_models(): array {
    return [
        ['id' => 'gemini-2.5-pro',        'label' => 'Gemini 2.5 Pro',        'description' => 'Highest quality 2.5 model. Free tier with daily quota.'],
        ['id' => 'gemini-2.5-flash',      'label' => 'Gemini 2.5 Flash',      'description' => 'Balanced 2.5 model. Default for AI Studio free tier.'],
        ['id' => 'gemini-2.5-flash-lite', 'label' => 'Gemini 2.5 Flash-Lite', 'description' => 'Cheapest 2.5 model, highest free-tier rate limits.'],
        ['id' => 'gemini-2.0-flash',      'label' => 'Gemini 2.0 Flash',      'description' => 'Previous-gen fast model. Free tier.'],
        ['id' => 'gemini-2.0-flash-lite', 'label' => 'Gemini 2.0 Flash-Lite', 'description' => 'Previous-gen cheapest model. Free tier.'],
        ['id' => 'gemini-1.5-flash',      'label' => 'Gemini 1.5 Flash',      'description' => 'Legacy. Free tier, scheduled for deprecation.'],
        ['id' => 'gemini-1.5-flash-8b',   'label' => 'Gemini 1.5 Flash-8B',   'description' => 'Legacy 8B. Free tier, lowest cost when paid.'],
        ['id' => 'gemini-1.5-pro',        'label' => 'Gemini 1.5 Pro',        'description' => 'Legacy 1.5 Pro. Free tier, scheduled for deprecation.'],
    ];
}

/**
 * Fetch the live Gemini model catalog via the generative-language API.
 * Filters to models that support generateContent and strips the "models/"
 * prefix. Falls back to gemini_fallback_models() when the API is unreachable
 * or the key isn't configured.
 *
 * @return array<int, array{id: string, label: string, description: ?string, source: string}>
 */
function gemini_list_models(): array {
    if (!gemini_adapter_enabled()) {
        return array_map(fn($m) => $m + ['source' => 'fallback'], gemini_fallback_models());
    }
    $key = (string) env('GEMINI_API_KEY');
    try {
        $res = http_get_json('https://generativelanguage.googleapis.com/v1beta/models?pageSize=200&key=' . urlencode($key));
    } catch (Throwable $e) {
        error_log('gemini list_models failed: ' . $e->getMessage());
        return array_map(fn($m) => $m + ['source' => 'fallback'], gemini_fallback_models());
    }
    $out = [];
    foreach ((array) ($res['models'] ?? []) as $m) {
        $name = (string) ($m['name'] ?? '');
        if ($name === '') continue;
        $id = str_starts_with($name, 'models/') ? substr($name, 7) : $name;
        $methods = (array) ($m['supportedGenerationMethods'] ?? []);
        if (!in_array('generateContent', $methods, true)) continue;
        if (!gemini_is_free_aistudio_chat($id)) continue;
        $out[] = [
            'id' => $id,
            'label' => (string) ($m['displayName'] ?? $id),
            'description' => isset($m['description']) ? (string) $m['description'] : null,
            'source' => 'api',
        ];
    }
    if (!$out) {
        return array_map(fn($m) => $m + ['source' => 'fallback'], gemini_fallback_models());
    }
    usort($out, fn($a, $b) => strcmp($a['id'], $b['id']));
    return $out;
}

function gemini_adapter_complete(array $opts): string {
    if (!gemini_adapter_enabled()) {
        throw new LLMUnavailableError('GEMINI_API_KEY is not configured. The app is running in demo mode.');
    }
    $key = (string) env('GEMINI_API_KEY');
    $model = gemini_adapter_model();
    $contents = [];
    foreach ($opts['messages'] as $m) {
        $contents[] = [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ];
    }
    $config = [
        'systemInstruction' => ['parts' => [['text' => $opts['system']]]],
        'generationConfig' => [
            'temperature' => $opts['temperature'] ?? 0.2,
            'maxOutputTokens' => $opts['max_tokens'] ?? 2048,
        ],
    ];
    if (!empty($opts['json_mode'])) {
        $config['generationConfig']['responseMimeType'] = 'application/json';
    }
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);
    $res = http_post_json($url, array_merge(['contents' => $contents], $config));
    $text = '';
    foreach ((array) ($res['candidates'][0]['content']['parts'] ?? []) as $p) {
        if (isset($p['text'])) $text .= $p['text'];
    }
    $usage = $res['usageMetadata'] ?? [];
    llm_set_last_usage('gemini', $model, (int) ($usage['promptTokenCount'] ?? 0), (int) ($usage['candidatesTokenCount'] ?? 0));
    return trim($text);
}

function gemini_adapter_web_search(array $opts): ?array {
    if (!gemini_adapter_enabled()) return null;
    $key = (string) env('GEMINI_API_KEY');
    $model = gemini_adapter_model();
    $max = $opts['max_results'] ?? 5;
    $domains = implode(', ', $opts['allowed_domains']);
    $user = "You are a medical research assistant. Use Google Search to find up to {$max} relevant guideline / peer-reviewed sources for the following clinical question in {$opts['specialty']}.\n\nQuestion: {$opts['query']}\n\nOnly return sources from these biomedical domains: {$domains}.\nDo not invent any source. If you find nothing relevant, return an empty list.\n\nReply with a JSON array only — no markdown, no prose — where each item is:\n{\"title\": string, \"source\": string, \"url\": string, \"snippet\": string}";
    try {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . urlencode($key);
        $res = http_post_json($url, [
            'contents' => [['role' => 'user', 'parts' => [['text' => $user]]]],
            'tools' => [['google_search' => new stdClass()]],
        ]);
        $text = '';
        foreach ((array) ($res['candidates'][0]['content']['parts'] ?? []) as $p) {
            if (isset($p['text'])) $text .= $p['text'];
        }
        $parsed = parse_json_array_loose(trim($text));
        if ($parsed === null) return [];
        $allowed = array_map('strtolower', $opts['allowed_domains']);
        $out = [];
        foreach (array_slice($parsed, 0, $max) as $r) {
            if (!is_array($r) || !is_string($r['title'] ?? null) || !is_string($r['source'] ?? null)) continue;
            $urlStr = is_string($r['url'] ?? null) ? $r['url'] : null;
            // Gemini's google_search doesn't take an allowlist — post-filter by host.
            if ($urlStr !== null) {
                $host = strtolower((string) parse_url($urlStr, PHP_URL_HOST));
                $ok = false;
                foreach ($allowed as $d) {
                    if ($host === $d || str_ends_with($host, '.' . $d)) { $ok = true; break; }
                }
                if (!$ok) continue;
            }
            $out[] = [
                'title' => $r['title'],
                'source' => $r['source'],
                'url' => $urlStr,
                'snippet' => is_string($r['snippet'] ?? null) ? $r['snippet'] : null,
                'is_mock' => false,
            ];
        }
        return $out;
    } catch (Throwable $e) {
        error_log('gemini google_search failed: ' . $e->getMessage());
        return null;
    }
}
