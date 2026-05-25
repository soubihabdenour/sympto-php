<?php
declare(strict_types=1);

function current_doctor(): ?array {
    if (empty($_SESSION['doctor_id'])) return null;
    static $cache = null;
    if ($cache !== null && (int) $cache['id'] === (int) $_SESSION['doctor_id']) return $cache;
    $cache = db_fetch('SELECT * FROM doctors WHERE id = ?', [$_SESSION['doctor_id']]);
    // Auto-logout if the account was deactivated or its tenant was suspended.
    if ($cache) {
        if ((int) ($cache['active'] ?? 1) === 0) {
            logout_doctor();
            $cache = null;
            return null;
        }
        if (!empty($cache['tenant_id'])) {
            $tenant = db_fetch('SELECT status FROM tenants WHERE id = ?', [$cache['tenant_id']]);
            if ($tenant && ($tenant['status'] ?? 'active') !== 'active' && ($cache['role'] ?? '') !== 'SUPER_ADMIN') {
                logout_doctor();
                $cache = null;
                return null;
            }
        }
    }
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
