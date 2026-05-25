<?php
declare(strict_types=1);

const TRUSTED_DOMAINS = [
    'pubmed.ncbi.nlm.nih.gov',
    'www.ncbi.nlm.nih.gov',
    'www.who.int',
    'www.cdc.gov',
    'www.nice.org.uk',
    'www.acc.org',
    'www.heart.org',
    'www.uptodate.com',
    'www.cochranelibrary.com',
];

function search_medical_sources(array $opts): array {
    $enabled = strtolower((string) env('ENABLE_WEB_SEARCH', 'false')) === 'true';
    if (!$enabled || !llm_enabled()) {
        return mock_research_results($opts);
    }
    $results = llm_web_search([
        'specialty' => $opts['specialty'],
        'query' => $opts['query'],
        'max_results' => $opts['max_results'] ?? 5,
        'allowed_domains' => TRUSTED_DOMAINS,
    ]);
    if ($results === null) return mock_research_results($opts);
    return $results;
}

function mock_research_results(array $opts): array {
    $q = mb_substr($opts['query'], 0, 120);
    $spec = $opts['specialty'];
    return array_slice([
        [
            'title' => "[Placeholder] PubMed search hint for \"{$q}\" in {$spec}",
            'source' => 'PubMed (placeholder)',
            'url' => 'https://pubmed.ncbi.nlm.nih.gov/?term=' . urlencode($q),
            'snippet' => 'Web search is disabled or unavailable. Use this link to perform the literature search manually. The agent will not fabricate citations.',
            'is_mock' => true,
        ],
        [
            'title' => "[Placeholder] NICE guidance hint for \"{$q}\"",
            'source' => 'NICE (placeholder)',
            'url' => 'https://www.nice.org.uk/search?q=' . urlencode($q),
            'snippet' => 'Manual lookup link. Enable ENABLE_WEB_SEARCH=true with a configured LLM provider to retrieve real sources.',
            'is_mock' => true,
        ],
    ], 0, $opts['max_results'] ?? 2);
}
