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
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pw = (string) ($_POST['password'] ?? '');
    $doctor = db_fetch('SELECT * FROM doctors WHERE email = ?', [$email]);
    if (!$doctor || !password_verify($pw, (string) $doctor['password_hash'])) {
        render('login', ['error' => t('Login.failed')]);
        return;
    }
    if ((int) ($doctor['active'] ?? 1) === 0) {
        render('login', ['error' => t('Login.accountDisabled')]);
        return;
    }
    if (!empty($doctor['tenant_id'])) {
        $t = db_fetch('SELECT status FROM tenants WHERE id = ?', [$doctor['tenant_id']]);
        if ($t && ($t['status'] ?? 'active') !== 'active' && ($doctor['role'] ?? '') !== 'SUPER_ADMIN') {
            render('login', ['error' => t('Login.tenantSuspended')]);
            return;
        }
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
    $org = trim((string) ($_POST['organization'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    $license = trim((string) ($_POST['license_id'] ?? ''));
    $spec = trim((string) ($_POST['specialty'] ?? ''));
    if ($org === '' || $email === '' || $name === '' || strlen($pw) < 8) {
        render('register', ['error' => t('Register.failed')]);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        render('register', ['error' => t('Register.failed')]);
        return;
    }
    if (db_fetch('SELECT id FROM doctors WHERE email = ?', [$email])) {
        render('register', ['error' => t('Register.failed')]);
        return;
    }
    try {
        $r = create_tenant_with_admin($org, [
            'email' => $email, 'full_name' => $name, 'password' => $pw,
            'license_id' => $license ?: null, 'specialty' => $spec ?: null,
        ]);
    } catch (Throwable $e) {
        error_log('create_tenant_with_admin failed: ' . $e->getMessage());
        render('register', ['error' => t('Register.failed')]);
        return;
    }
    login_doctor($r['doctor_id']);
    audit('tenant.create', $r['doctor_id'], null, ['tenant_id' => $r['tenant_id'], 'org' => $org]);
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

// ---------------- Invitation acceptance ----------------

route('GET', '/invite/{token}', function (string $token) {
    $invite = find_pending_invite($token);
    if (!$invite) {
        render('invite_invalid');
        return;
    }
    $tenant = get_tenant((int) $invite['tenant_id']);
    render('invite', ['invite' => $invite, 'tenant' => $tenant, 'error' => null]);
});

route('POST', '/invite/{token}', function (string $token) {
    csrf_check();
    $invite = find_pending_invite($token);
    if (!$invite) {
        render('invite_invalid');
        return;
    }
    $tenant = get_tenant((int) $invite['tenant_id']);
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $pw = (string) ($_POST['password'] ?? '');
    $license = trim((string) ($_POST['license_id'] ?? ''));
    $spec = trim((string) ($_POST['specialty'] ?? ''));
    if ($name === '' || strlen($pw) < 8) {
        render('invite', ['invite' => $invite, 'tenant' => $tenant, 'error' => t('Register.failed')]);
        return;
    }
    db_exec(
        'UPDATE doctors SET full_name = ?, password_hash = ?, license_id = ?, specialty = ?, active = 1, invite_token = NULL, invite_expires_at = NULL WHERE id = ?',
        [$name, password_hash($pw, PASSWORD_DEFAULT), $license ?: null, $spec ?: null, (int) $invite['id']]
    );
    login_doctor((int) $invite['id']);
    audit('invite.accept', (int) $invite['id'], null, ['tenant_id' => $invite['tenant_id']]);
    redirect('/dashboard');
});

// ---------------- Authed pages ----------------

route('GET', '/dashboard', function () {
    $d = require_doctor();
    if (is_super_admin($d)) redirect('/admin');
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
    if (is_super_admin($d)) redirect('/admin');
    render('new_case', ['doctor' => $d, 'error' => null]);
});

route('POST', '/cases', function () {
    $d = require_doctor();
    csrf_check();
    if (is_super_admin($d)) { http_response_code(403); echo '<h1>403</h1>'; exit; }
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

route('POST', '/cases/{id}/report', function (string $id) {
    $d = require_doctor();
    csrf_check();
    $cid = (int) $id;
    if (!ensure_case_access($cid, (int) $d['id'])) not_found();
    // Plan enforcement.
    if (!empty($d['tenant_id'])) {
        $block = check_can_generate_report((int) $d['tenant_id']);
        if ($block) json_response(['error' => $block['message']], 402);
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
    audit('report.generate', (int) $d['id'], $cid, [
        'needsFollowUp' => $report['needsFollowUp'] ?? null,
        'model' => $report['model'] ?? null,
        'usage' => llm_last_usage(),
    ]);
    json_response(['ok' => true, 'report' => $report]);
});

// ---------------- Tenant admin: team management ----------------

route('GET', '/team', function () {
    $d = require_admin();
    if (is_super_admin($d) && empty($d['tenant_id'])) redirect('/admin');
    $tenantId = (int) $d['tenant_id'];
    $members = db_all(
        'SELECT id, email, full_name, role, active, invite_token, invite_expires_at, created_at
         FROM doctors WHERE tenant_id = ? ORDER BY active DESC, role ASC, full_name ASC',
        [$tenantId]
    );
    render('team', [
        'doctor' => $d,
        'tenant' => current_tenant($d),
        'subscription' => current_subscription($d),
        'members' => $members,
        'invite' => $_SESSION['flash_invite'] ?? null,
        'error' => $_SESSION['flash_error'] ?? null,
    ]);
    unset($_SESSION['flash_invite'], $_SESSION['flash_error']);
});

route('POST', '/team/invite', function () {
    $d = require_admin();
    csrf_check();
    if (empty($d['tenant_id'])) bad_request('admin has no tenant');
    $tenantId = (int) $d['tenant_id'];
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $role = (string) ($_POST['role'] ?? 'DOCTOR');
    $block = check_can_add_doctor($tenantId);
    if ($block) { $_SESSION['flash_error'] = $block['message']; redirect('/team'); }
    try {
        $r = create_invitation($tenantId, (int) $d['id'], $email, $role);
    } catch (Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        redirect('/team');
    }
    $base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $_SESSION['flash_invite'] = [
        'email' => $r['email'],
        'token' => $r['token'],
        'url' => $base . '/invite/' . $r['token'],
    ];
    audit('team.invite', (int) $d['id'], null, ['email' => $r['email'], 'role' => $role, 'tenant_id' => $tenantId]);
    redirect('/team');
});

route('POST', '/team/{id}/role', function (string $id) {
    $d = require_admin();
    csrf_check();
    $tid = (int) $d['tenant_id'];
    $target = db_fetch('SELECT * FROM doctors WHERE id = ? AND tenant_id = ?', [(int) $id, $tid]);
    if (!$target) not_found();
    if ((int) $target['id'] === (int) $d['id']) { $_SESSION['flash_error'] = 'You cannot change your own role.'; redirect('/team'); }
    $role = (string) ($_POST['role'] ?? 'DOCTOR');
    if (!in_array($role, ['ADMIN', 'DOCTOR'], true)) bad_request('invalid role');
    db_exec('UPDATE doctors SET role = ? WHERE id = ?', [$role, (int) $target['id']]);
    audit('team.role_change', (int) $d['id'], null, ['target' => $target['id'], 'role' => $role]);
    redirect('/team');
});

route('POST', '/team/{id}/deactivate', function (string $id) {
    $d = require_admin();
    csrf_check();
    $tid = (int) $d['tenant_id'];
    $target = db_fetch('SELECT * FROM doctors WHERE id = ? AND tenant_id = ?', [(int) $id, $tid]);
    if (!$target) not_found();
    if ((int) $target['id'] === (int) $d['id']) { $_SESSION['flash_error'] = 'You cannot deactivate yourself.'; redirect('/team'); }
    db_exec('UPDATE doctors SET active = 0 WHERE id = ?', [(int) $target['id']]);
    audit('team.deactivate', (int) $d['id'], null, ['target' => $target['id']]);
    redirect('/team');
});

route('POST', '/team/{id}/reactivate', function (string $id) {
    $d = require_admin();
    csrf_check();
    $tid = (int) $d['tenant_id'];
    $target = db_fetch('SELECT * FROM doctors WHERE id = ? AND tenant_id = ?', [(int) $id, $tid]);
    if (!$target) not_found();
    $block = check_can_add_doctor($tid);
    if ($block) { $_SESSION['flash_error'] = $block['message']; redirect('/team'); }
    db_exec('UPDATE doctors SET active = 1 WHERE id = ?', [(int) $target['id']]);
    audit('team.reactivate', (int) $d['id'], null, ['target' => $target['id']]);
    redirect('/team');
});

// ---------------- Tenant admin: billing ----------------

route('GET', '/billing', function () {
    $d = require_admin();
    if (is_super_admin($d) && empty($d['tenant_id'])) redirect('/admin');
    $tenant = current_tenant($d);
    $sub = current_subscription($d);
    render('billing', [
        'doctor' => $d,
        'tenant' => $tenant,
        'subscription' => $sub,
        'plans' => all_plans(),
        'usage' => [
            'doctors' => tenant_doctor_count((int) $tenant['id']),
            'reports' => tenant_reports_this_period((int) $tenant['id']),
            'cases' => tenant_cases_total((int) $tenant['id']),
        ],
        'flash' => $_SESSION['flash_billing'] ?? null,
    ]);
    unset($_SESSION['flash_billing']);
});

route('POST', '/billing/plan', function () {
    $d = require_admin();
    csrf_check();
    $tid = (int) $d['tenant_id'];
    $planId = (string) ($_POST['plan_id'] ?? '');
    $plan = get_plan($planId);
    if (!$plan) bad_request('invalid plan');
    $status = ((int) $plan['is_trial'] === 1) ? 'trial' : 'active';
    $trialEnds = ((int) ($plan['trial_days'] ?? 0) > 0)
        ? gmdate('Y-m-d H:i:s', time() + 86400 * (int) $plan['trial_days'])
        : null;
    db_exec(
        'UPDATE subscriptions SET plan_id = ?, status = ?, trial_ends_at = ?, current_period_start = datetime(\'now\'), current_period_end = datetime(\'now\', \'+30 days\'), updated_at = datetime(\'now\') WHERE tenant_id = ?',
        [$planId, $status, $trialEnds, $tid]
    );
    audit('billing.plan_change', (int) $d['id'], null, ['tenant_id' => $tid, 'plan' => $planId]);
    $_SESSION['flash_billing'] = 'Plan updated to ' . $plan['name'] . '.';
    redirect('/billing');
});

// ---------------- Super-admin: tenants & plans ----------------

route('GET', '/admin', function () {
    $d = require_super_admin();
    $tenants = db_all(
        'SELECT t.*, s.plan_id, s.status AS sub_status, s.trial_ends_at,
                (SELECT COUNT(*) FROM doctors WHERE tenant_id = t.id AND active = 1) AS doctor_count,
                (SELECT COUNT(*) FROM cases c JOIN doctors dr ON dr.id = c.doctor_id WHERE dr.tenant_id = t.id) AS case_count
         FROM tenants t LEFT JOIN subscriptions s ON s.tenant_id = t.id
         ORDER BY t.created_at DESC'
    );
    $totals = [
        'tenants' => count($tenants),
        'doctors' => (int) db_fetch('SELECT COUNT(*) AS n FROM doctors WHERE active = 1 AND role <> \'SUPER_ADMIN\'')['n'],
        'cases'   => (int) db_fetch('SELECT COUNT(*) AS n FROM cases')['n'],
        'reports' => (int) db_fetch('SELECT COUNT(*) AS n FROM diagnosis_reports')['n'],
    ];
    render('admin_tenants', ['doctor' => $d, 'tenants' => $tenants, 'totals' => $totals, 'plans' => all_plans()]);
});

route('GET', '/admin/tenants/{id}', function (string $id) {
    $d = require_super_admin();
    $tenant = get_tenant((int) $id);
    if (!$tenant) not_found();
    $sub = get_subscription_for_tenant((int) $id);
    $members = db_all(
        'SELECT id, email, full_name, role, active, created_at FROM doctors WHERE tenant_id = ? ORDER BY role ASC, full_name ASC',
        [(int) $id]
    );
    $usage = [
        'doctors' => tenant_doctor_count((int) $id),
        'reports' => tenant_reports_this_period((int) $id),
        'cases'   => tenant_cases_total((int) $id),
    ];
    render('admin_tenant_view', [
        'doctor' => $d,
        'tenant' => $tenant,
        'subscription' => $sub,
        'plans' => all_plans(),
        'members' => $members,
        'usage' => $usage,
    ]);
});

route('POST', '/admin/tenants/{id}/plan', function (string $id) {
    require_super_admin();
    csrf_check();
    $tid = (int) $id;
    if (!get_tenant($tid)) not_found();
    $planId = (string) ($_POST['plan_id'] ?? '');
    $plan = get_plan($planId);
    if (!$plan) bad_request('invalid plan');
    $status = ((int) $plan['is_trial'] === 1) ? 'trial' : 'active';
    $trialEnds = ((int) ($plan['trial_days'] ?? 0) > 0)
        ? gmdate('Y-m-d H:i:s', time() + 86400 * (int) $plan['trial_days'])
        : null;
    $existing = get_subscription_for_tenant($tid);
    if ($existing) {
        db_exec(
            'UPDATE subscriptions SET plan_id = ?, status = ?, trial_ends_at = ?, current_period_start = datetime(\'now\'), current_period_end = datetime(\'now\', \'+30 days\'), updated_at = datetime(\'now\') WHERE tenant_id = ?',
            [$planId, $status, $trialEnds, $tid]
        );
    } else {
        db_exec(
            'INSERT INTO subscriptions (tenant_id, plan_id, status, trial_ends_at, current_period_end) VALUES (?, ?, ?, ?, datetime(\'now\', \'+30 days\'))',
            [$tid, $planId, $status, $trialEnds]
        );
    }
    audit('admin.plan_change', (int) current_doctor()['id'], null, ['tenant_id' => $tid, 'plan' => $planId]);
    redirect('/admin/tenants/' . $tid);
});

route('POST', '/admin/tenants/{id}/status', function (string $id) {
    require_super_admin();
    csrf_check();
    $tid = (int) $id;
    $status = (string) ($_POST['status'] ?? 'active');
    if (!in_array($status, ['active', 'suspended'], true)) bad_request('invalid status');
    db_exec('UPDATE tenants SET status = ? WHERE id = ?', [$status, $tid]);
    audit('admin.tenant_status', (int) current_doctor()['id'], null, ['tenant_id' => $tid, 'status' => $status]);
    redirect('/admin/tenants/' . $tid);
});
