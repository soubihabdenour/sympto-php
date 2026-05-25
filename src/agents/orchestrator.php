<?php
declare(strict_types=1);

function format_context(array $ctx): string {
    $parts = [];
    $push = function (string $label, $value) use (&$parts) {
        if ($value === null || $value === '') return;
        $s = is_string($value) ? trim($value) : (string) $value;
        if ($s === '') return;
        $parts[] = "- {$label}: {$s}";
    };
    $push('Age (years)', $ctx['age_years'] ?? null);
    $push('Sex', $ctx['sex'] ?? null);
    $push('Symptoms', $ctx['symptoms'] ?? null);
    $push('Medical history', $ctx['medical_history'] ?? null);
    $push('Medications', $ctx['medications'] ?? null);
    $push('Allergies', $ctx['allergies'] ?? null);
    $push('Vital signs', $ctx['vital_signs'] ?? null);
    $push('Lab values', $ctx['lab_values'] ?? null);
    $push('Imaging summary', $ctx['imaging_summary'] ?? null);
    $push("Doctor's initial diagnosis / suspected condition", $ctx['initial_diagnosis'] ?? null);
    $push('Clinical question', $ctx['clinical_question'] ?? null);
    return $parts ? implode("\n", $parts) : '(No structured patient data was entered.)';
}

function format_docs(array $docs): string {
    if (!$docs) return '(No supporting documents uploaded.)';
    $out = [];
    foreach ($docs as $i => $d) {
        $n = $i + 1;
        $excerpt = substr((string) ($d['excerpt'] ?? ''), 0, 4000);
        $out[] = "Document #{$n} — {$d['filename']} ({$d['kind']})\n----\n{$excerpt}";
    }
    return implode("\n\n", $out);
}

function format_research(array $results): string {
    if (!$results) return '(No external research sources retrieved.)';
    $out = [];
    foreach ($results as $i => $r) {
        $n = $i + 1;
        $line = "[{$n}] {$r['title']}\nSource: {$r['source']}";
        if (!empty($r['url'])) $line .= "\nURL: {$r['url']}";
        if (!empty($r['is_mock'])) $line .= "\n(Note: this is a placeholder source — web search disabled.)";
        if (!empty($r['snippet'])) $line .= "\n{$r['snippet']}";
        $out[] = $line;
    }
    return implode("\n\n", $out);
}

function build_case_user_message(array $opts): string {
    $context = format_context($opts['ctx']);
    $docs = format_docs($opts['docs']);
    $research = format_research($opts['research']);

    if (($opts['task'] ?? 'report') === 'report') {
        $schema = REPORT_JSON_SCHEMA_DESCRIPTION;
        $safety = $opts['safety_disclaimer'];
        $allowedSources = build_allowed_sources_block($opts['research']);
        return "You will produce a structured clinical decision-support report.\n\nPATIENT / CLINICAL CONTEXT\n{$context}\n\nUPLOADED DOCUMENTS (extracted text excerpts)\n{$docs}\n\nRESEARCH SOURCES PROVIDED TO YOU\n{$research}\n\nINSTRUCTIONS\n1. First decide whether you have enough information for a confident report.\n   - If important context is missing for this specialty, set \"needsFollowUp\": true and populate \"followUpQuestions\" with specific items. You may still fill out partial sections, but mark \"uncertainty\" accordingly.\n   - Apply the validated clinical decision rules listed in your system prompt when their inputs are present. When you reference a score, name it and give the numeric or categorical result.\n2. Output ONLY a single JSON object that matches this schema. The first character of your reply MUST be '{' and the last MUST be '}'. No surrounding prose, no markdown, no code fences, no commentary.\n{$schema}\n3. The \"safetyDisclaimer\" field must be exactly:\n\"{$safety}\"\n4. For \"citations\", include only sources from this allowed list (match the title exactly):\n{$allowedSources}\nDo not invent any source. If the allowed list is empty, return an empty \"citations\" array and explicitly say so in \"evidenceSummary\".\n5. \"differentialDiagnosis\" must be ordered by likelihood (high → medium → low). \"evidenceAgainst\" must be filled when low likelihood is chosen.\n6. \"recommendedTests\" must be specific (modality, body region, with/without contrast, what you are looking for) — not generic.";
    }

    $doctorMsg = $opts['doctor_message'] ?? '';
    return "You are continuing a case discussion with the doctor. The current case context is below for grounding. Reply concisely and clinically.\n\nPATIENT / CLINICAL CONTEXT\n{$context}\n\nUPLOADED DOCUMENTS (extracted text excerpts)\n{$docs}\n\nRESEARCH SOURCES PROVIDED TO YOU\n{$research}\n\nDOCTOR'S MESSAGE\n{$doctorMsg}";
}

function build_allowed_sources_block(array $research): string {
    if (!$research) return '(no allowed sources — citations must be an empty array)';
    $lines = [];
    foreach ($research as $i => $r) {
        $n = $i + 1;
        $lines[] = "[{$n}] title=\"{$r['title']}\" source=\"{$r['source']}\"" . (!empty($r['url']) ? " url=\"{$r['url']}\"" : '');
    }
    return implode("\n", $lines);
}

/**
 * @return array  Diagnosis report content (see schema)
 */
function generate_report(array $opts): array {
    $spec = get_specialty($opts['specialty_id']);
    if (!$spec) throw new RuntimeException('Unknown specialty: ' . $opts['specialty_id']);
    $locale = $opts['locale'] ?? 'en';
    $safety = get_safety_disclaimer($locale);

    $research = !empty($opts['enable_research'])
        ? search_medical_sources([
            'specialty' => $spec['specialty'],
            'query' => $opts['doctor_query']
                ?? ($opts['ctx']['clinical_question'] ?? '')
                ?: ($opts['ctx']['initial_diagnosis'] ?? '')
                ?: ($opts['ctx']['symptoms'] ?? '')
                ?: $spec['specialty'],
            'max_results' => 5,
        ])
        : [];

    if (!llm_enabled()) {
        return demo_report($spec, $opts['ctx'], $research, $locale);
    }

    $system = build_specialist_system_prompt($spec, $locale);
    $userMsg = build_case_user_message([
        'ctx' => $opts['ctx'],
        'docs' => $opts['docs'],
        'research' => $research,
        'task' => 'report',
        'safety_disclaimer' => $safety,
    ]);

    llm_clear_last_usage();
    $usageTotal = ['input_tokens' => 0, 'output_tokens' => 0, 'attempts' => 0];
    try {
        $raw = llm_complete([
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $userMsg]],
            'max_tokens' => 3500,
            'temperature' => 0.2,
            'json_mode' => true,
        ]);
        $usageTotal = accumulate_usage($usageTotal);
    } catch (LLMUnavailableError $e) {
        return demo_report($spec, $opts['ctx'], $research, $locale);
    }

    $parsed = safe_parse_report($raw);
    // Retry once with a stricter "fix the JSON" instruction if first parse fails.
    if ($parsed === null) {
        try {
            $raw2 = llm_complete([
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $userMsg],
                    ['role' => 'assistant', 'content' => $raw],
                    ['role' => 'user', 'content' => "Your previous reply could not be parsed as JSON. Re-emit the SAME analysis as a single JSON object that matches the schema. First character must be '{', last must be '}'. No prose. No markdown. No code fences."],
                ],
                'max_tokens' => 3500,
                'temperature' => 0.1,
                'json_mode' => true,
            ]);
            $usageTotal = accumulate_usage($usageTotal);
            $parsed = safe_parse_report($raw2);
            if ($parsed !== null) $raw = $raw2;
        } catch (Throwable $e) {
            error_log('orchestrator JSON retry failed: ' . $e->getMessage());
        }
    }

    // Make total usage from this call (across retries) the value llm_last_usage() returns,
    // so the route handler can audit a single combined number.
    llm_set_last_usage(
        (string) (llm_last_usage()['provider'] ?? llm_provider()),
        (string) (llm_last_usage()['model'] ?? llm_model()),
        $usageTotal['input_tokens'],
        $usageTotal['output_tokens']
    );

    if ($parsed === null) {
        $u = unstructured_fallback($locale);
        return [
            'needsFollowUp' => false,
            'followUpQuestions' => [],
            'caseSummary' => $u['caseSummary'],
            'keyFindings' => [],
            'missingInformation' => [],
            'differentialDiagnosis' => [],
            'redFlags' => [],
            'recommendedTests' => [],
            'treatmentConsiderations' => [substr($raw, 0, 4000)],
            'specialistReferrals' => [],
            'evidenceSummary' => count($research) === 0 ? $u['noResearch'] : $u['withResearch'](count($research)),
            'citations' => array_map(fn($r) => [
                'title' => $r['title'], 'source' => $r['source'], 'url' => $r['url'] ?? null,
            ], $research),
            'uncertainty' => 'high',
            'finalRecommendation' => $u['finalRecommendation'],
            'safetyDisclaimer' => $safety,
            'generatedAt' => gmdate('c'),
            'specialtyId' => $spec['id'],
            'model' => llm_model_label(),
        ];
    }

    // Enforce: any citation the model returned must match one of the research
    // sources we actually gave it. Drop hallucinated citations rather than
    // letting them slip through with a "looks legitimate" title.
    $parsed['citations'] = filter_citations_against_allowed($parsed['citations'] ?? [], $research);
    $parsed['safetyDisclaimer'] = $safety;
    $parsed['generatedAt'] = gmdate('c');
    $parsed['specialtyId'] = $spec['id'];
    $parsed['model'] = llm_model_label();
    return $parsed;
}

function filter_citations_against_allowed(array $citations, array $allowed): array {
    if (!$citations) return [];
    if (!$allowed) return []; // nothing was provided → no citations allowed
    $norm = fn(string $s) => trim(strtolower(preg_replace('/\s+/', ' ', $s) ?? ''));
    $allowedIndex = [];
    foreach ($allowed as $a) {
        $allowedIndex[$norm((string) ($a['title'] ?? ''))] = $a;
    }
    $out = [];
    foreach ($citations as $c) {
        $title = (string) ($c['title'] ?? '');
        if ($title === '') continue;
        $key = $norm($title);
        if (!isset($allowedIndex[$key])) continue; // hallucinated → drop
        $match = $allowedIndex[$key];
        $out[] = [
            'title' => $match['title'],
            'source' => $match['source'] ?? ($c['source'] ?? ''),
            'url' => $match['url'] ?? ($c['url'] ?? null),
        ];
    }
    return $out;
}

function chat_with_agent(array $opts): string {
    $spec = get_specialty($opts['specialty_id']);
    if (!$spec) throw new RuntimeException('Unknown specialty: ' . $opts['specialty_id']);
    $locale = $opts['locale'] ?? 'en';

    if (!llm_enabled()) {
        return demo_chat_reply($spec, $opts['doctor_message'], $locale);
    }

    $system = build_specialist_system_prompt($spec, $locale);
    $prior = [];
    foreach ($opts['history'] ?? [] as $turn) {
        if ($turn['role'] === 'doctor') $prior[] = ['role' => 'user', 'content' => $turn['content']];
        elseif ($turn['role'] === 'agent') $prior[] = ['role' => 'assistant', 'content' => $turn['content']];
    }
    $grounded = build_case_user_message([
        'ctx' => $opts['ctx'],
        'docs' => $opts['docs'],
        'research' => [],
        'task' => 'chat',
        'doctor_message' => $opts['doctor_message'],
        'safety_disclaimer' => get_safety_disclaimer($locale),
    ]);
    $messages = array_merge($prior, [['role' => 'user', 'content' => $grounded]]);

    try {
        return llm_complete([
            'system' => $system,
            'messages' => $messages,
            'max_tokens' => 1500,
            'temperature' => 0.3,
        ]);
    } catch (LLMUnavailableError $e) {
        return demo_chat_reply($spec, $opts['doctor_message'], $locale);
    }
}

function accumulate_usage(array $total): array {
    $last = llm_last_usage();
    if ($last) {
        $total['input_tokens']  += (int) ($last['input_tokens'] ?? 0);
        $total['output_tokens'] += (int) ($last['output_tokens'] ?? 0);
    }
    $total['attempts'] = ($total['attempts'] ?? 0) + 1;
    return $total;
}

function safe_parse_report(string $raw): ?array {
    $cleaned = trim(preg_replace('/^```(?:json)?\s*/i', '', $raw) ?? '');
    $cleaned = trim(preg_replace('/```\s*$/i', '', $cleaned) ?? '');
    $obj = json_decode($cleaned, true);
    if (!is_array($obj)) {
        $start = strpos($cleaned, '{');
        $end = strrpos($cleaned, '}');
        if ($start === false || $end === false || $end <= $start) return null;
        $obj = json_decode(substr($cleaned, $start, $end - $start + 1), true);
        if (!is_array($obj)) return null;
    }
    return normalize_report($obj);
}

function normalize_report(array $o): array {
    $arr = function ($v): array {
        if (!is_array($v)) return [];
        $out = [];
        foreach ($v as $x) if (is_string($x)) $out[] = $x;
        return $out;
    };
    $likelihoodOk = ['high', 'medium', 'low'];
    $uncertaintyOk = ['low', 'medium', 'high'];
    $dx = [];
    if (is_array($o['differentialDiagnosis'] ?? null)) {
        foreach ($o['differentialDiagnosis'] as $d) {
            if (!is_array($d)) continue;
            $like = $d['likelihood'] ?? 'low';
            if (!in_array($like, $likelihoodOk, true)) $like = 'low';
            $dx[] = [
                'diagnosis' => (string) ($d['diagnosis'] ?? ''),
                'likelihood' => $like,
                'supportingEvidence' => $arr($d['supportingEvidence'] ?? []),
                'evidenceAgainst' => $arr($d['evidenceAgainst'] ?? []),
                'recommendedNextStep' => (string) ($d['recommendedNextStep'] ?? ''),
            ];
        }
    }
    $citations = [];
    if (is_array($o['citations'] ?? null)) {
        foreach ($o['citations'] as $c) {
            if (!is_array($c)) continue;
            $title = (string) ($c['title'] ?? '');
            if ($title === '') continue;
            $citations[] = [
                'title' => $title,
                'source' => (string) ($c['source'] ?? ''),
                'url' => is_string($c['url'] ?? null) ? $c['url'] : null,
            ];
        }
    }
    $u = $o['uncertainty'] ?? 'high';
    if (!in_array($u, $uncertaintyOk, true)) $u = 'high';
    return [
        'needsFollowUp' => (bool) ($o['needsFollowUp'] ?? false),
        'followUpQuestions' => $arr($o['followUpQuestions'] ?? []),
        'caseSummary' => (string) ($o['caseSummary'] ?? ''),
        'keyFindings' => $arr($o['keyFindings'] ?? []),
        'missingInformation' => $arr($o['missingInformation'] ?? []),
        'differentialDiagnosis' => $dx,
        'redFlags' => $arr($o['redFlags'] ?? []),
        'recommendedTests' => $arr($o['recommendedTests'] ?? []),
        'treatmentConsiderations' => $arr($o['treatmentConsiderations'] ?? []),
        'specialistReferrals' => $arr($o['specialistReferrals'] ?? []),
        'evidenceSummary' => (string) ($o['evidenceSummary'] ?? ''),
        'citations' => $citations,
        'uncertainty' => $u,
        'finalRecommendation' => (string) ($o['finalRecommendation'] ?? ''),
        'safetyDisclaimer' => SAFETY_DISCLAIMER_EN, // overwritten in caller
    ];
}

// ---------- Localized strings for demo + unstructured fallback ----------

function demo_strings(string $locale): array {
    $map = [
        'en' => [
            'summary_prefix' => fn($n) => "Demo-mode response from the {$n}. ",
            'summary_body' => 'No LLM provider API key is configured, so no LLM analysis was performed and no clinical content has been generated. The structure below shows where real specialist output will appear once the LLM is configured.',
            'provide' => fn($m) => "Please provide: {$m}",
            'provided' => fn($m) => "Provided: {$m}",
            'no_research' => 'No external research sources were retrieved (web search disabled or API key missing).',
            'with_research' => fn($n) => "Retrieved {$n} placeholder research source(s).",
            'final_rec' => 'Configure an LLM provider in .env (OPENAI_API_KEY, ANTHROPIC_API_KEY, or GEMINI_API_KEY) and set LLM_PROVIDER to enable real specialist analysis. Until then, the system will not produce clinical recommendations.',
            'chat_header' => '**Demo mode**',
            'chat_body' => fn($n) => "The {$n} cannot generate clinical content because no LLM provider API key is configured. Set `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, or `GEMINI_API_KEY` in `.env` and pick a provider via `LLM_PROVIDER`.",
            'chat_you_sent' => 'You sent:',
            'chat_footer' => 'Once a key is added, this agent will respond with specialist-level reasoning, follow-up questions for missing context, and structured differential diagnoses.',
        ],
        'fr' => [
            'summary_prefix' => fn($n) => "Réponse en mode démo du {$n}. ",
            'summary_body' => "Aucune clé API de fournisseur LLM n'est configurée, aucune analyse LLM n'a donc été effectuée et aucun contenu clinique n'a été généré. La structure ci-dessous indique où apparaîtra la sortie spécialisée réelle une fois le LLM configuré.",
            'provide' => fn($m) => "Veuillez fournir : {$m}",
            'provided' => fn($m) => "Fourni : {$m}",
            'no_research' => "Aucune source de recherche externe n'a été récupérée (recherche web désactivée ou clé API manquante).",
            'with_research' => fn($n) => "{$n} source(s) de recherche fictive(s) récupérée(s).",
            'final_rec' => "Configurez un fournisseur LLM dans .env (OPENAI_API_KEY, ANTHROPIC_API_KEY ou GEMINI_API_KEY) et définissez LLM_PROVIDER pour activer l'analyse spécialisée réelle. Jusque-là, le système ne produira pas de recommandations cliniques.",
            'chat_header' => '**Mode démo**',
            'chat_body' => fn($n) => "Le {$n} ne peut pas générer de contenu clinique car aucune clé API de fournisseur LLM n'est configurée. Définissez `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` ou `GEMINI_API_KEY` dans `.env` et choisissez un fournisseur via `LLM_PROVIDER`.",
            'chat_you_sent' => 'Vous avez envoyé :',
            'chat_footer' => "Une fois la clé ajoutée, cet agent répondra avec un raisonnement de niveau spécialiste, des questions de suivi pour le contexte manquant et des diagnostics différentiels structurés.",
        ],
        'de' => [
            'summary_prefix' => fn($n) => "Antwort im Demo-Modus von {$n}. ",
            'summary_body' => 'Es ist kein API-Schlüssel eines LLM-Anbieters konfiguriert, daher wurde keine LLM-Analyse durchgeführt und es wurde kein klinischer Inhalt erzeugt. Die folgende Struktur zeigt, wo nach Konfiguration des LLM die echte fachliche Ausgabe erscheinen wird.',
            'provide' => fn($m) => "Bitte angeben: {$m}",
            'provided' => fn($m) => "Angegeben: {$m}",
            'no_research' => 'Es wurden keine externen Recherchequellen abgerufen (Websuche deaktiviert oder API-Schlüssel fehlt).',
            'with_research' => fn($n) => "{$n} Platzhalter-Recherchequelle(n) abgerufen.",
            'final_rec' => 'Konfigurieren Sie in .env einen LLM-Anbieter (OPENAI_API_KEY, ANTHROPIC_API_KEY oder GEMINI_API_KEY) und setzen Sie LLM_PROVIDER, um die echte fachliche Analyse zu aktivieren. Bis dahin liefert das System keine klinischen Empfehlungen.',
            'chat_header' => '**Demo-Modus**',
            'chat_body' => fn($n) => "{$n} kann keinen klinischen Inhalt erzeugen, da kein API-Schlüssel eines LLM-Anbieters konfiguriert ist. Setzen Sie `OPENAI_API_KEY`, `ANTHROPIC_API_KEY` oder `GEMINI_API_KEY` in `.env` und wählen Sie einen Anbieter über `LLM_PROVIDER`.",
            'chat_you_sent' => 'Sie haben gesendet:',
            'chat_footer' => 'Sobald ein Schlüssel hinzugefügt ist, antwortet dieser Agent mit fachärztlicher Argumentation, Rückfragen zu fehlendem Kontext und strukturierten Differenzialdiagnosen.',
        ],
    ];
    return $map[$locale] ?? $map['en'];
}

function unstructured_fallback(string $locale): array {
    $map = [
        'en' => [
            'caseSummary' => 'The AI returned an unstructured response. The raw output is included below for the doctor to review.',
            'noResearch' => 'No external research sources were retrieved.',
            'withResearch' => fn($n) => "Retrieved {$n} research source(s).",
            'finalRecommendation' => 'Review the raw output and consider re-running the analysis with more context.',
        ],
        'fr' => [
            'caseSummary' => "L'IA a renvoyé une réponse non structurée. La sortie brute est incluse ci-dessous pour relecture par le médecin.",
            'noResearch' => "Aucune source de recherche externe n'a été récupérée.",
            'withResearch' => fn($n) => "{$n} source(s) de recherche récupérée(s).",
            'finalRecommendation' => "Examinez la sortie brute et envisagez de relancer l'analyse avec davantage de contexte.",
        ],
        'de' => [
            'caseSummary' => 'Die KI hat eine unstrukturierte Antwort zurückgegeben. Die Rohausgabe ist unten zur ärztlichen Prüfung enthalten.',
            'noResearch' => 'Es wurden keine externen Recherchequellen abgerufen.',
            'withResearch' => fn($n) => "{$n} Recherchequelle(n) abgerufen.",
            'finalRecommendation' => 'Überprüfen Sie die Rohausgabe und ziehen Sie in Betracht, die Analyse mit mehr Kontext erneut auszuführen.',
        ],
    ];
    return $map[$locale] ?? $map['en'];
}

function demo_report(array $spec, array $ctx, array $research, string $locale): array {
    $s = demo_strings($locale);
    $present = [];
    $missing = $spec['required_context'];
    $remove = function (string $label, bool $has) use (&$present, &$missing) {
        if (!$has) return;
        foreach ($missing as $i => $m) {
            if (str_contains(strtolower($m), $label)) {
                $present[] = $m;
                array_splice($missing, $i, 1);
                return;
            }
        }
    };
    $remove('age', isset($ctx['age_years']) && $ctx['age_years'] !== null);
    $remove('sex', !empty($ctx['sex']));
    $remove('vital', !empty($ctx['vital_signs']));
    $remove('medication', !empty($ctx['medications']));
    $remove('history', !empty($ctx['medical_history']));
    $remove('symptom', !empty($ctx['symptoms']));
    $remove('lab', !empty($ctx['lab_values']));

    return [
        'needsFollowUp' => count($missing) > 0,
        'followUpQuestions' => array_map($s['provide'], $missing),
        'caseSummary' => $s['summary_prefix']($spec['name']) . $s['summary_body'],
        'keyFindings' => array_map($s['provided'], $present),
        'missingInformation' => array_values($missing),
        'differentialDiagnosis' => [],
        'redFlags' => [],
        'recommendedTests' => [],
        'treatmentConsiderations' => [],
        'specialistReferrals' => [],
        'evidenceSummary' => count($research) === 0 ? $s['no_research'] : $s['with_research'](count($research)),
        'citations' => array_map(fn($r) => ['title' => $r['title'], 'source' => $r['source'], 'url' => $r['url'] ?? null], $research),
        'uncertainty' => 'high',
        'finalRecommendation' => $s['final_rec'],
        'safetyDisclaimer' => get_safety_disclaimer($locale),
        'generatedAt' => gmdate('c'),
        'specialtyId' => $spec['id'],
        'model' => 'demo',
        'demoMode' => true,
    ];
}

function demo_chat_reply(array $spec, string $doctorMessage, string $locale): string {
    $s = demo_strings($locale);
    $you = $s['chat_you_sent'];
    $excerpt = substr($doctorMessage, 0, 500);
    return "{$s['chat_header']}\n\n{$s['chat_body']($spec['name'])}\n\n{$you}\n> {$excerpt}\n\n{$s['chat_footer']}";
}
