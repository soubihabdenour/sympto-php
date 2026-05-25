<?php
declare(strict_types=1);

function openai_adapter_enabled(): bool {
    return (string) env('OPENAI_API_KEY', '') !== '';
}

function openai_adapter_model(): string {
    return (string) env('OPENAI_MODEL', 'gpt-4o');
}

function openai_adapter_complete(array $opts): string {
    if (!openai_adapter_enabled()) {
        throw new LLMUnavailableError('OPENAI_API_KEY is not configured. The app is running in demo mode.');
    }
    $key = (string) env('OPENAI_API_KEY');
    $model = openai_adapter_model();
    $messages = [['role' => 'system', 'content' => $opts['system']]];
    foreach ($opts['messages'] as $m) {
        $messages[] = ['role' => $m['role'], 'content' => $m['content']];
    }
    $body = [
        'model' => $model,
        'temperature' => $opts['temperature'] ?? 0.2,
        'max_tokens' => $opts['max_tokens'] ?? 2048,
        'messages' => $messages,
    ];
    if (!empty($opts['json_mode'])) {
        $body['response_format'] = ['type' => 'json_object'];
    }
    $res = http_post_json(
        'https://api.openai.com/v1/chat/completions',
        $body,
        ['Authorization: Bearer ' . $key]
    );
    return trim((string) ($res['choices'][0]['message']['content'] ?? ''));
}

function openai_adapter_web_search(array $opts): ?array {
    if (!openai_adapter_enabled()) return null;
    $key = (string) env('OPENAI_API_KEY');
    $model = openai_adapter_model();
    $max = $opts['max_results'] ?? 5;
    $input = "You are a medical research assistant. Find up to {$max} relevant guideline / peer-reviewed sources for the following clinical question in {$opts['specialty']}.\n\nQuestion: {$opts['query']}\n\nUse only the web_search tool with the allowed biomedical domains. Do not invent any source. If you find nothing relevant, return an empty list.\n\nReply with a JSON array only — no markdown — where each item is:\n{\"title\": string, \"source\": string, \"url\": string, \"snippet\": string}";
    try {
        $res = http_post_json(
            'https://api.openai.com/v1/responses',
            [
                'model' => $model,
                'tools' => [[
                    'type' => 'web_search',
                    'filters' => ['allowed_domains' => $opts['allowed_domains']],
                ]],
                'tool_choice' => 'auto',
                'input' => $input,
            ],
            ['Authorization: Bearer ' . $key]
        );
        $text = trim((string) ($res['output_text'] ?? ''));
        $parsed = parse_json_array_loose($text);
        if ($parsed === null) return [];
        return openai_normalize_results($parsed, $max);
    } catch (Throwable $e) {
        error_log('openai web_search failed: ' . $e->getMessage());
        return null;
    }
}

function openai_normalize_results(array $raw, int $max): array {
    $out = [];
    foreach (array_slice($raw, 0, $max) as $r) {
        if (!is_array($r) || !is_string($r['title'] ?? null) || !is_string($r['source'] ?? null)) continue;
        $out[] = [
            'title' => $r['title'],
            'source' => $r['source'],
            'url' => is_string($r['url'] ?? null) ? $r['url'] : null,
            'snippet' => is_string($r['snippet'] ?? null) ? $r['snippet'] : null,
            'is_mock' => false,
        ];
    }
    return $out;
}
