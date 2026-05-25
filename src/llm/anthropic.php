<?php
declare(strict_types=1);

function anthropic_adapter_enabled(): bool {
    return (string) env('ANTHROPIC_API_KEY', '') !== '';
}

function anthropic_adapter_model(): string {
    $override = setting_get('llm.anthropic.model');
    if ($override !== null && $override !== '') return $override;
    return (string) env('ANTHROPIC_MODEL', 'claude-opus-4-7');
}

/**
 * Curated catalog of current Claude chat models. Anthropic does not expose
 * a list endpoint, so this is hardcoded.
 *
 * @return array<int, array{id: string, label: string, description: ?string, source: string}>
 */
function anthropic_list_models(): array {
    return [
        ['id' => 'claude-opus-4-7',    'label' => 'Claude Opus 4.7',    'description' => 'Highest-capability Claude tier.',           'source' => 'fallback'],
        ['id' => 'claude-sonnet-4-6',  'label' => 'Claude Sonnet 4.6',  'description' => 'Balanced quality/speed/cost tier.',         'source' => 'fallback'],
        ['id' => 'claude-haiku-4-5',   'label' => 'Claude Haiku 4.5',   'description' => 'Fastest, cheapest current Claude.',         'source' => 'fallback'],
        ['id' => 'claude-3-5-sonnet-latest', 'label' => 'Claude 3.5 Sonnet', 'description' => 'Legacy Claude 3.5 Sonnet snapshot.',  'source' => 'fallback'],
        ['id' => 'claude-3-5-haiku-latest',  'label' => 'Claude 3.5 Haiku',  'description' => 'Legacy Claude 3.5 Haiku snapshot.',   'source' => 'fallback'],
    ];
}

function anthropic_adapter_complete(array $opts): string {
    if (!anthropic_adapter_enabled()) {
        throw new LLMUnavailableError('ANTHROPIC_API_KEY is not configured. The app is running in demo mode.');
    }
    $key = (string) env('ANTHROPIC_API_KEY');
    $model = anthropic_adapter_model();
    // Opus 4.7 removed temperature / top_p / top_k / budget_tokens. We just
    // don't pass them — the orchestrator's temperature arg is silently ignored.
    $system = $opts['system'];
    if (!empty($opts['json_mode'])) {
        $system .= "\n\nRespond with a single JSON object only. No prose, no markdown, no code fences.";
    }
    $body = [
        'model' => $model,
        'max_tokens' => $opts['max_tokens'] ?? 2048,
        'system' => $system,
        'messages' => array_map(
            fn($m) => ['role' => $m['role'], 'content' => $m['content']],
            $opts['messages']
        ),
    ];
    $res = http_post_json(
        'https://api.anthropic.com/v1/messages',
        $body,
        [
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ]
    );
    $text = '';
    foreach ((array) ($res['content'] ?? []) as $block) {
        if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
            $text .= $block['text'];
        }
    }
    $usage = $res['usage'] ?? [];
    llm_set_last_usage('anthropic', $model, (int) ($usage['input_tokens'] ?? 0), (int) ($usage['output_tokens'] ?? 0));
    return trim($text);
}

function anthropic_adapter_web_search(array $opts): ?array {
    if (!anthropic_adapter_enabled()) return null;
    $key = (string) env('ANTHROPIC_API_KEY');
    $model = anthropic_adapter_model();
    $max = $opts['max_results'] ?? 5;
    $user = "You are a medical research assistant. Use the web_search tool to find up to {$max} relevant guideline / peer-reviewed sources for the following clinical question in {$opts['specialty']}.\n\nQuestion: {$opts['query']}\n\nOnly use sources from the allowed biomedical domains. Do not invent any source. If you find nothing relevant, return an empty list.\n\nAfter searching, reply with a JSON array only — no markdown, no prose — where each item is:\n{\"title\": string, \"source\": string, \"url\": string, \"snippet\": string}";
    try {
        $res = http_post_json(
            'https://api.anthropic.com/v1/messages',
            [
                'model' => $model,
                'max_tokens' => 2048,
                'tools' => [[
                    'type' => 'web_search_20260209',
                    'name' => 'web_search',
                    'allowed_domains' => $opts['allowed_domains'],
                    'max_uses' => 5,
                ]],
                'messages' => [['role' => 'user', 'content' => $user]],
            ],
            [
                'x-api-key: ' . $key,
                'anthropic-version: 2023-06-01',
            ]
        );
        $text = '';
        foreach ((array) ($res['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }
        $parsed = parse_json_array_loose(trim($text));
        if ($parsed === null) return [];
        $out = [];
        foreach (array_slice($parsed, 0, $max) as $r) {
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
    } catch (Throwable $e) {
        error_log('anthropic web_search failed: ' . $e->getMessage());
        return null;
    }
}
