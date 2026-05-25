<?php
declare(strict_types=1);

function gemini_adapter_enabled(): bool {
    return (string) env('GEMINI_API_KEY', '') !== '';
}

function gemini_adapter_model(): string {
    return (string) env('GEMINI_MODEL', 'gemini-2.5-flash');
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
