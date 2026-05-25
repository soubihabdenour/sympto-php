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
    render('settings', ['doctor' => $d]);
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
    $c = ensure_case_access($cid, (int) $d['id']);
    if (!$c) not_found();
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
    ]);
});

route('POST', '/cases/{id}/patient', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    $fields = ['age_years', 'sex', 'symptoms', 'medical_history', 'medications', 'allergies', 'vital_signs', 'lab_values', 'imaging_summary', 'initial_diagnosis', 'clinical_question'];
    $set = [];
    $vals = [];
    foreach ($fields as $f) {
        $v = $_POST[$f] ?? '';
        if ($f === 'age_years') {
            $v = $v === '' ? null : (int) $v;
        } else {
            $v = trim((string) $v);
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
    $usageByDoctor = aggregate_token_usage_by_doctor();
    foreach ($doctors as &$row) {
        $u = $usageByDoctor[(int) $row['id']] ?? ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0];
        $row['input_tokens'] = (int) $u['input_tokens'];
        $row['output_tokens'] = (int) $u['output_tokens'];
        $row['llm_calls'] = (int) $u['calls'];
    }
    unset($row);
    render('admin_index', ['doctor' => $admin, 'doctors' => $doctors]);
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
    $usage = aggregate_token_usage_by_doctor()[$did]
        ?? ['input_tokens' => 0, 'output_tokens' => 0, 'calls' => 0, 'by_model' => []];
    render('admin_doctor', [
        'doctor' => $admin,
        'target' => $target,
        'cases' => $cases,
        'logs' => $logs,
        'usage' => $usage,
    ]);
});

route('POST', '/cases/{id}/report', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
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
