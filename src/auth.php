<?php
declare(strict_types=1);

function current_doctor(): ?array {
    if (empty($_SESSION['doctor_id'])) return null;
    static $cache = null;
    if ($cache !== null && $cache['id'] === $_SESSION['doctor_id']) return $cache;
    $cache = db_fetch('SELECT * FROM doctors WHERE id = ?', [$_SESSION['doctor_id']]);
    return $cache;
}

function require_doctor(): array {
    $d = current_doctor();
    if (!$d) redirect('/login');
    return $d;
}

function require_doctor_json(): array {
    $d = current_doctor();
    if (!$d) json_response(['error' => 'unauthorized'], 401);
    return $d;
}

function is_admin(?array $doctor = null): bool {
    $d = $doctor ?? current_doctor();
    return $d !== null && ($d['role'] ?? '') === 'ADMIN';
}

function require_admin(): array {
    $d = require_doctor();
    if (!is_admin($d)) {
        http_response_code(403);
        echo '<h1>403 — Forbidden</h1>';
        exit;
    }
    return $d;
}

function login_doctor(int $doctorId): void {
    session_regenerate_id(true);
    $_SESSION['doctor_id'] = $doctorId;
}

function logout_doctor(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function ensure_case_access(int $caseId, int $doctorId): ?array {
    $c = db_fetch('SELECT * FROM cases WHERE id = ?', [$caseId]);
    if (!$c) return null;
    if ((int) $c['doctor_id'] !== $doctorId) return null;
    return $c;
}
