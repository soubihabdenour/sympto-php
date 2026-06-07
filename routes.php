<?php
declare(strict_types=1);

// ---------------- Landing / auth ----------------

route('GET', '/', function () {
    if (current_doctor()) redirect('/dashboard');
    render('landing');
});

route('GET', '/login', function () {
    if (current_doctor()) redirect('/dashboard');
    render('login', ['error' => null]);
});

route('POST', '/login', function () {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    $doctor = db_fetch('SELECT * FROM doctors WHERE email = ?', [$email]);
    if (!$doctor || !password_verify($pw, $doctor['password_hash'])) {
        render('login', ['error' => t('Login.failed')]);
        return;
    }
    login_doctor((int) $doctor['id']);
    audit('auth.login', (int) $doctor['id']);
    redirect('/dashboard');
});

route('GET', '/register', function () {
    if (current_doctor()) redirect('/dashboard');
    render('register', ['error' => null]);
});

route('POST', '/register', function () {
    csrf_check();
    $email = trim((string) ($_POST['email'] ?? ''));
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    $license = trim((string) ($_POST['license_id'] ?? ''));
    $spec = trim((string) ($_POST['specialty'] ?? ''));
    if ($email === '' || $name === '' || strlen($pw) < 8) {
        render('register', ['error' => t('Register.failed')]);
        return;
    }
    $exists = db_fetch('SELECT id FROM doctors WHERE email = ?', [$email]);
    if ($exists) {
        render('register', ['error' => t('Register.failed')]);
        return;
    }
    $id = db_insert(
        'INSERT INTO doctors (email, full_name, password_hash, license_id, specialty, role) VALUES (?, ?, ?, ?, ?, ?)',
        [$email, $name, password_hash($pw, PASSWORD_DEFAULT), $license ?: null, $spec ?: null, 'DOCTOR']
    );
    login_doctor($id);
    audit('auth.register', $id);
    redirect('/dashboard');
});

route('POST', '/logout', function () {
    csrf_check();
    $d = current_doctor();
    if ($d) audit('auth.logout', (int) $d['id']);
    logout_doctor();
    redirect('/login');
});

// ---------------- Locale ----------------

route('POST', '/api/locale', function () {
    csrf_check();
    $locale = (string) ($_POST['locale'] ?? '');
    if (!in_array($locale, SUPPORTED_LOCALES, true)) bad_request('invalid locale');
    set_locale_cookie($locale);
    json_response(['ok' => true]);
});

// ---------------- Authed pages ----------------

route('GET', '/dashboard', function () {
    $d = require_doctor();
    $cases = db_all(
        'SELECT c.*,
            (SELECT COUNT(*) FROM medical_documents WHERE case_id = c.id) AS docs_count,
            (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id) AS msgs_count,
            (SELECT COUNT(*) FROM diagnosis_reports WHERE case_id = c.id) AS reports_count
         FROM cases c WHERE doctor_id = ? ORDER BY updated_at DESC',
        [$d['id']]
    );
    render('dashboard', ['doctor' => $d, 'cases' => $cases]);
});

route('GET', '/agents', function () {
    $d = require_doctor();
    render('agents', ['doctor' => $d]);
});

route('GET', '/settings', function () {
    $d = require_doctor();
    $usage = aggregate_token_usage((int) $d['id'], 30);
    render('settings', ['doctor' => $d, 'usage' => $usage]);
});

route('GET', '/cases/new', function () {
    $d = require_doctor();
    render('new_case', ['doctor' => $d, 'error' => null]);
});

route('POST', '/cases', function () {
    $d = require_doctor();
    csrf_check();
    $title = trim((string) ($_POST['title'] ?? ''));
    $sid = (string) ($_POST['specialty_id'] ?? '');
    if ($title === '') { render('new_case', ['doctor' => $d, 'error' => t('NewCase.needTitle')]); return; }
    if (!get_specialty($sid)) { render('new_case', ['doctor' => $d, 'error' => t('NewCase.needSpecialty')]); return; }
    $cid = db_insert(
        'INSERT INTO cases (doctor_id, title, specialty_id, status) VALUES (?, ?, ?, ?)',
        [$d['id'], $title, $sid, 'OPEN']
    );
    db_exec('INSERT INTO patient_data (case_id) VALUES (?)', [$cid]);
    audit('case.create', (int) $d['id'], $cid, ['specialty' => $sid]);
    redirect("/cases/$cid");
});

route('GET', '/cases/{id}', function (string $id) {
    $d = require_doctor();
    $cid = (int) $id;
    $c = ensure_case_access($cid, (int) $d['id'], writable: false);
    if (!$c) not_found();
    $is_owner = (int) $c['doctor_id'] === (int) $d['id'];
    $patient = db_fetch('SELECT * FROM patient_data WHERE case_id = ?', [$cid]) ?? [];
    $docs = db_all(
        'SELECT m.*, e.text AS extracted_text FROM medical_documents m
         LEFT JOIN extracted_text e ON e.document_id = m.id
         WHERE m.case_id = ? ORDER BY uploaded_at ASC',
        [$cid]
    );
    $messages = db_all('SELECT * FROM case_messages WHERE case_id = ? ORDER BY created_at ASC', [$cid]);
    $reports = db_all('SELECT * FROM diagnosis_reports WHERE case_id = ? ORDER BY created_at DESC', [$cid]);
    render('case_view', [
        'doctor' => $d,
        'case' => $c,
        'patient' => $patient,
        'documents' => $docs,
        'messages' => $messages,
        'reports' => $reports,
        'is_owner' => $is_owner,
    ]);
});

route('POST', '/cases/{id}/patient', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    $fields = ['age_years', 'sex', 'symptoms', 'medical_history', 'medications', 'allergies', 'vital_signs', 'lab_values', 'imaging_summary', 'initial_diagnosis', 'clinical_question'];
    $customNames  = is_array($_POST['vs_custom_name']  ?? null) ? $_POST['vs_custom_name']  : [];
    $customValues = is_array($_POST['vs_custom_value'] ?? null) ? $_POST['vs_custom_value'] : [];
    $custom = [];
    foreach ($customNames as $i => $name) {
        $custom[] = [
            'name'  => (string) $name,
            'value' => (string) ($customValues[$i] ?? ''),
        ];
    }
    $vitalSignsJson = vital_signs_encode([
        'bp_systolic'  => $_POST['vs_bp_systolic']  ?? '',
        'bp_diastolic' => $_POST['vs_bp_diastolic'] ?? '',
        'hr'           => $_POST['vs_hr']           ?? '',
        'rr'           => $_POST['vs_rr']           ?? '',
        'spo2'         => $_POST['vs_spo2']         ?? '',
        'temp_c'       => $_POST['vs_temp_c']       ?? '',
        'gcs'          => $_POST['vs_gcs']          ?? '',
        'notes'        => $_POST['vs_notes']        ?? '',
        'custom'       => $custom,
    ]);
    $set = [];
    $vals = [];
    foreach ($fields as $f) {
        if ($f === 'vital_signs') {
            $v = $vitalSignsJson;
        } elseif ($f === 'age_years') {
            $v = ($_POST[$f] ?? '') === '' ? null : (int) $_POST[$f];
        } else {
            $v = trim((string) ($_POST[$f] ?? ''));
            $v = $v === '' ? null : $v;
        }
        $set[] = "$f = ?";
        $vals[] = $v;
    }
    $vals[] = $cid;
    db_exec('UPDATE patient_data SET ' . implode(', ', $set) . ' WHERE case_id = ?', $vals);
    db_exec('UPDATE cases SET updated_at = datetime(\'now\') WHERE id = ?', [$cid]);
    redirect("/cases/$cid");
});

route('POST', '/cases/{id}/status', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    $status = (string) ($_POST['status'] ?? '');
    $allowed = ['OPEN', 'IN_PROGRESS', 'REPORTED', 'CLOSED'];
    if (!in_array($status, $allowed, true)) bad_request('invalid status');
    db_exec(
        "UPDATE cases SET status = ?, updated_at = datetime('now') WHERE id = ?",
        [$status, $cid]
    );
    audit('case.status', (int) $d['id'], $cid, ['status' => $status]);
    redirect("/cases/$cid");
});

route('POST', '/cases/{id}/specialty', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    $sid = (string) ($_POST['specialty_id'] ?? '');
    if (!get_specialty($sid)) bad_request('invalid specialty');
    db_exec('UPDATE cases SET specialty_id = ?, updated_at = datetime(\'now\') WHERE id = ?', [$sid, $cid]);
    redirect("/cases/$cid");
});

route('POST', '/cases/{id}/documents', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    if (empty($_FILES['file']['name']) || !is_array($_FILES['file']['name'])) {
        redirect("/cases/$cid#documents");
    }
    $count = count($_FILES['file']['name']);
    $caseDir = UPLOADS_DIR . '/' . $cid;
    if (!is_dir($caseDir)) @mkdir($caseDir, 0775, true);
    for ($i = 0; $i < $count; $i++) {
        if (($_FILES['file']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $orig = basename((string) $_FILES['file']['name'][$i]);
        $size = (int) $_FILES['file']['size'][$i];
        $tmp = (string) $_FILES['file']['tmp_name'][$i];
        $mime = (string) ($_FILES['file']['type'][$i] ?? 'application/octet-stream');
        if ($size > 16 * 1024 * 1024) continue;
        $kind = classify_upload($orig, $mime);
        $stored = $caseDir . '/' . bin2hex(random_bytes(6)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $orig);
        if (!move_uploaded_file($tmp, $stored)) continue;
        $docId = db_insert(
            'INSERT INTO medical_documents (case_id, filename, stored_path, kind, mime_type, size_bytes) VALUES (?, ?, ?, ?, ?, ?)',
            [$cid, $orig, $stored, $kind, $mime, $size]
        );
        $parsed = parse_document($stored, $kind);
        db_exec(
            'INSERT INTO extracted_text (document_id, text, summary) VALUES (?, ?, ?)',
            [$docId, $parsed['text'] ?? '', $parsed['summary'] ?? null]
        );
        audit('document.upload', (int) $d['id'], $cid, ['filename' => $orig, 'kind' => $kind]);
    }
    db_exec('UPDATE cases SET updated_at = datetime(\'now\') WHERE id = ?', [$cid]);
    redirect("/cases/$cid#documents");
});

route('POST', '/cases/{id}/documents/{docId}/delete', function (string $id, string $docId) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    $doc = db_fetch('SELECT * FROM medical_documents WHERE id = ? AND case_id = ?', [(int) $docId, $cid]);
    if ($doc) {
        @unlink($doc['stored_path']);
        db_exec('DELETE FROM medical_documents WHERE id = ?', [$doc['id']]);
        audit('document.delete', (int) $d['id'], $cid, ['filename' => $doc['filename']]);
    }
    redirect("/cases/$cid#documents");
});

route('POST', '/cases/{id}/messages', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    try {
        assert_doctor_within_token_limit((int) $d['id']);
    } catch (TokenLimitExceeded $e) {
        json_response([
            'error' => t('Limit.exceeded', [
                'window' => t('Limit.window.' . $e->window),
                'used' => $e->used,
                'limit' => $e->limit,
            ]),
        ], 429);
    }
    $content = trim((string) ($_POST['content'] ?? ''));
    if ($content === '' || mb_strlen($content) > 8000) {
        json_response(['error' => 'invalid content'], 400);
    }
    db_insert('INSERT INTO case_messages (case_id, role, content) VALUES (?, ?, ?)', [$cid, 'doctor', $content]);
    audit('message.create', (int) $d['id'], $cid, ['role' => 'doctor']);

    $patient = db_fetch('SELECT * FROM patient_data WHERE case_id = ?', [$cid]) ?? [];
    $docs = db_all(
        'SELECT m.filename, m.kind, COALESCE(e.text, \'\') AS excerpt
         FROM medical_documents m LEFT JOIN extracted_text e ON e.document_id = m.id
         WHERE m.case_id = ?',
        [$cid]
    );
    $hist = db_all('SELECT role, content FROM case_messages WHERE case_id = ? ORDER BY created_at ASC', [$cid]);
    // Drop the just-inserted doctor turn (it's passed via doctor_message)
    if ($hist) array_pop($hist);
    $c = db_fetch('SELECT specialty_id FROM cases WHERE id = ?', [$cid]);
    $locale = current_locale();
    llm_clear_last_usage();
    try {
        $reply = chat_with_agent([
            'specialty_id' => $c['specialty_id'],
            'ctx' => $patient,
            'docs' => $docs,
            'history' => $hist,
            'doctor_message' => $content,
            'locale' => $locale,
        ]);
    } catch (Throwable $e) {
        error_log('chat_with_agent failed: ' . $e->getMessage());
        $reply = 'The agent failed to generate a response. Please check the server logs and verify your LLM provider configuration (LLM_PROVIDER and the matching API key).';
    }
    db_insert('INSERT INTO case_messages (case_id, role, content) VALUES (?, ?, ?)', [$cid, 'agent', $reply]);
    db_exec('UPDATE cases SET status = ?, updated_at = datetime(\'now\') WHERE id = ?', ['IN_PROGRESS', $cid]);
    audit('message.reply', (int) $d['id'], $cid, ['role' => 'agent', 'usage' => llm_last_usage()]);
    json_response(['ok' => true, 'reply' => $reply]);
});

// ---------------- Admin ----------------

route('GET', '/admin', function () {
    $admin = require_admin();
    $doctors = db_all(
        "SELECT d.id, d.email, d.full_name, d.specialty, d.role, d.created_at,
                (SELECT COUNT(*) FROM cases WHERE doctor_id = d.id) AS cases_count,
                (SELECT COUNT(*) FROM case_messages m JOIN cases c ON c.id = m.case_id WHERE c.doctor_id = d.id AND m.role = 'doctor') AS msgs_count,
                (SELECT COUNT(*) FROM diagnosis_reports r JOIN cases c ON c.id = r.case_id WHERE c.doctor_id = d.id) AS reports_count,
                (SELECT MAX(created_at) FROM audit_logs WHERE doctor_id = d.id) AS last_active
         FROM doctors d
         ORDER BY (d.role = 'ADMIN') ASC, d.full_name COLLATE NOCASE ASC"
    );
    $usage = aggregate_token_usage(null, 30);
    foreach ($doctors as &$row) {
        $u = $usage['by_doctor'][(int) $row['id']]
            ?? ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'est_cost_usd' => 0.0];
        $row['input_tokens'] = (int) $u['input_tokens'];
        $row['output_tokens'] = (int) $u['output_tokens'];
        $row['llm_calls'] = (int) $u['calls'];
        $row['est_cost_usd'] = (float) $u['est_cost_usd'];
    }
    unset($row);
    render('admin_index', ['doctor' => $admin, 'doctors' => $doctors, 'usage' => $usage]);
});

route('GET', '/admin/settings', function () {
    $admin = require_admin();
    $provider = (string) ($_GET['provider'] ?? llm_provider());
    if (!in_array($provider, ['openai', 'anthropic', 'gemini'], true)) {
        $provider = llm_provider();
    }
    $models = llm_list_models($provider);
    $current = match ($provider) {
        'openai' => openai_adapter_model(),
        'anthropic' => anthropic_adapter_model(),
        'gemini' => gemini_adapter_model(),
    };
    render('admin_settings', [
        'doctor' => $admin,
        'active_provider' => llm_provider(),
        'selected_provider' => $provider,
        'models' => $models,
        'current_model' => $current,
        'is_overridden' => llm_model_is_overridden($provider),
        'flash' => $_SESSION['admin_settings_flash'] ?? null,
    ]);
    unset($_SESSION['admin_settings_flash']);
});

route('POST', '/admin/settings', function () {
    require_admin();
    csrf_check();
    $provider = (string) ($_POST['provider'] ?? '');
    if (!in_array($provider, ['openai', 'anthropic', 'gemini'], true)) bad_request('invalid provider');
    $action = (string) ($_POST['_action'] ?? 'save');
    if ($action === 'reset') {
        setting_set(llm_model_setting_key($provider), null);
        $_SESSION['admin_settings_flash'] = ['kind' => 'ok', 'message' => 'reset'];
        redirect('/admin/settings?provider=' . urlencode($provider));
    }
    $model = trim((string) ($_POST['model'] ?? ''));
    if ($model === '' || strlen($model) > 80 || !preg_match('/^[A-Za-z0-9._:\\/-]+$/', $model)) {
        bad_request('invalid model id');
    }
    // Best-effort validation against the catalog; allow values from either
    // the live API list or the hardcoded fallback so an admin can paste a
    // newer Gemini ID that hasn't propagated to the curated list yet.
    $catalog = llm_list_models($provider);
    $known = array_column($catalog, 'id');
    if (!in_array($model, $known, true)) {
        // Soft-allow with a flag — store anyway so admins aren't blocked by a
        // stale catalog, but tell them on redirect.
        setting_set(llm_model_setting_key($provider), $model);
        $_SESSION['admin_settings_flash'] = ['kind' => 'warn', 'message' => 'saved_unknown'];
    } else {
        setting_set(llm_model_setting_key($provider), $model);
        $_SESSION['admin_settings_flash'] = ['kind' => 'ok', 'message' => 'saved'];
    }
    audit('admin.llm.model.set', (int) current_doctor()['id'], null, ['provider' => $provider, 'model' => $model]);
    redirect('/admin/settings?provider=' . urlencode($provider));
});

route('GET', '/admin/doctors/{id}', function (string $id) {
    $admin = require_admin();
    $did = (int) $id;
    $target = db_fetch('SELECT * FROM doctors WHERE id = ?', [$did]);
    if (!$target) not_found();
    $cases = db_all(
        'SELECT c.*,
            (SELECT COUNT(*) FROM medical_documents WHERE case_id = c.id) AS docs_count,
            (SELECT COUNT(*) FROM case_messages WHERE case_id = c.id) AS msgs_count,
            (SELECT COUNT(*) FROM diagnosis_reports WHERE case_id = c.id) AS reports_count
         FROM cases c WHERE doctor_id = ? ORDER BY updated_at DESC',
        [$did]
    );
    $logs = db_all(
        'SELECT * FROM audit_logs WHERE doctor_id = ? ORDER BY created_at DESC LIMIT 100',
        [$did]
    );
    $usage = aggregate_token_usage($did, 30);
    $limitsStatus = doctor_token_limits_status($did);
    $currentTier = (string) ($target['tier'] ?? tier_default());
    render('admin_doctor', [
        'doctor' => $admin,
        'target' => $target,
        'cases' => $cases,
        'logs' => $logs,
        'usage' => $usage,
        'limits_status' => $limitsStatus,
        'current_tier' => $currentTier,
        'limit_flash' => $_SESSION['admin_limit_flash'] ?? null,
    ]);
    unset($_SESSION['admin_limit_flash']);
});

route('POST', '/admin/doctors/{id}/tier', function (string $id) {
    $admin = require_admin();
    csrf_check();
    $did = (int) $id;
    $target = db_fetch('SELECT id FROM doctors WHERE id = ?', [$did]);
    if (!$target) not_found();
    $tier = (string) ($_POST['tier'] ?? '');
    if (!tier_is_valid($tier)) bad_request('invalid tier');
    db_exec('UPDATE doctors SET tier = ? WHERE id = ?', [$tier, $did]);
    audit('admin.doctor.tier.set', (int) $admin['id'], null, ['target_doctor_id' => $did, 'tier' => $tier]);
    $_SESSION['admin_limit_flash'] = ['kind' => 'ok', 'message' => 'tier_set', 'tier' => $tier];
    redirect('/admin/doctors/' . $did);
});

route('POST', '/cases/{id}/report', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    try {
        assert_doctor_within_token_limit((int) $d['id']);
    } catch (TokenLimitExceeded $e) {
        json_response([
            'error' => t('Limit.exceeded', [
                'window' => t('Limit.window.' . $e->window),
                'used' => $e->used,
                'limit' => $e->limit,
            ]),
        ], 429);
    }
    $c = db_fetch('SELECT * FROM cases WHERE id = ?', [$cid]);
    $patient = db_fetch('SELECT * FROM patient_data WHERE case_id = ?', [$cid]) ?? [];
    $docs = db_all(
        'SELECT m.filename, m.kind, COALESCE(e.text, \'\') AS excerpt
         FROM medical_documents m LEFT JOIN extracted_text e ON e.document_id = m.id
         WHERE m.case_id = ?',
        [$cid]
    );
    $locale = current_locale();
    try {
        $report = generate_report([
            'specialty_id' => $c['specialty_id'],
            'ctx' => $patient,
            'docs' => $docs,
            'enable_research' => true,
            'locale' => $locale,
        ]);
    } catch (Throwable $e) {
        error_log('generate_report failed: ' . $e->getMessage());
        json_response(['error' => $e->getMessage()], 500);
    }
    db_insert(
        'INSERT INTO diagnosis_reports (case_id, content_json, summary, uncertainty) VALUES (?, ?, ?, ?)',
        [$cid, json_encode($report), mb_substr((string) ($report['caseSummary'] ?? ''), 0, 500), $report['uncertainty'] ?? null]
    );
    db_exec(
        'UPDATE cases SET status = ?, updated_at = datetime(\'now\') WHERE id = ?',
        [!empty($report['needsFollowUp']) ? 'IN_PROGRESS' : 'REPORTED', $cid]
    );
    $usage = llm_last_usage();
    audit('report.generate', (int) $d['id'], $cid, [
        'needsFollowUp' => $report['needsFollowUp'] ?? null,
        'model' => $report['model'] ?? null,
        'usage' => $usage,
    ]);
    json_response(['ok' => true, 'report' => $report]);
});

// ---------------- PWA / push notifications ----------------

/**
 * Helper: read JSON body from a fetch() POST. Falls back to $_POST.
 */
function read_json_body(): array {
    $ct = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input') ?: '';
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

// Register a OneSignal player_id for the current doctor.
route('POST', '/api/push/subscribe', function () {
    csrf_check();
    $d = require_doctor_json();
    $data = read_json_body();
    $playerId = trim((string) ($data['player_id'] ?? ''));
    $platform = trim((string) ($data['platform'] ?? ''));
    if ($playerId === '') json_response(['error' => 'player_id required'], 400);
    try {
        push_subscribe((int) $d['id'], $playerId, $platform ?: null);
    } catch (InvalidArgumentException $e) {
        json_response(['error' => $e->getMessage()], 400);
    }
    audit('push.subscribe', (int) $d['id'], null, ['platform' => $platform]);
    json_response(['ok' => true]);
});

// Unregister a specific OneSignal player_id (e.g. user toggled push off in the browser).
route('POST', '/api/push/unsubscribe', function () {
    csrf_check();
    $d = require_doctor_json();
    $data = read_json_body();
    $playerId = trim((string) ($data['player_id'] ?? ''));
    if ($playerId === '') json_response(['error' => 'player_id required'], 400);
    push_unsubscribe((int) $d['id'], $playerId);
    audit('push.unsubscribe', (int) $d['id']);
    json_response(['ok' => true]);
});

// Push notifications status for the current doctor (used by the UI).
route('GET', '/api/push/status', function () {
    $d = require_doctor_json();
    $ids = push_player_ids_for_doctor((int) $d['id']);
    json_response([
        'configured' => push_is_configured(),
        'app_id' => push_is_configured() ? env('ONESIGNAL_APP_ID') : null,
        'subscribed_devices' => count($ids),
    ]);
});

// Admin: notifications composer (page).
route('GET', '/admin/notifications', function () {
    $admin = require_admin();
    render('admin_notifications', [
        'doctor' => $admin,
        'configured' => push_is_configured(),
        'subscriber_count' => push_subscribed_doctor_count(),
        'broadcasts' => push_recent_broadcasts(25),
        'flash' => $_SESSION['admin_notif_flash'] ?? null,
    ]);
    unset($_SESSION['admin_notif_flash']);
});

// Admin: send a broadcast (or send to a single doctor).
route('POST', '/admin/notifications', function () {
    $admin = require_admin();
    csrf_check();
    $title = trim((string) ($_POST['title'] ?? ''));
    $body  = trim((string) ($_POST['body']  ?? ''));
    $url   = trim((string) ($_POST['url']   ?? ''));
    $audience = (string) ($_POST['audience'] ?? 'all');
    if (!in_array($audience, ['all', 'doctors', 'admins', 'doctor'], true)) {
        $audience = 'all';
    }
    $opts = ['audience' => $audience, 'url' => $url ?: null];
    if ($audience === 'doctor') {
        $opts['doctor_id'] = (int) ($_POST['doctor_id'] ?? 0);
    }
    $result = push_send($title, $body, $opts);
    push_log_broadcast((int) $admin['id'], $title, $body, $url ?: null, $audience, $result);
    audit('push.broadcast', (int) $admin['id'], null, [
        'audience' => $audience,
        'ok' => $result['ok'],
        'recipients' => $result['recipients'] ?? null,
    ]);
    $_SESSION['admin_notif_flash'] = $result['ok']
        ? ['kind' => 'ok', 'message' => 'sent', 'recipients' => (int) ($result['recipients'] ?? 0)]
        : ['kind' => 'error', 'message' => (string) ($result['error'] ?? 'failed')];
    redirect('/admin/notifications');
});

// Public offline fallback (served directly from disk; this route only exists
// so that a hand-typed /offline URL still works behind the front controller).
route('GET', '/offline', function () {
    header('Content-Type: text/html; charset=utf-8');
    readfile(APP_ROOT . '/offline.html');
});
