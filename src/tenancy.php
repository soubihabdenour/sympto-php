<?php
declare(strict_types=1);

// ---------- Role helpers ----------

function is_super_admin(?array $doctor = null): bool {
    $d = $doctor ?? current_doctor();
    return $d !== null && ($d['role'] ?? '') === 'SUPER_ADMIN';
}

function is_tenant_admin(?array $doctor = null): bool {
    $d = $doctor ?? current_doctor();
    if (!$d) return false;
    return in_array($d['role'] ?? '', ['ADMIN', 'SUPER_ADMIN'], true);
}

function require_admin(): array {
    $d = require_doctor();
    if (!is_tenant_admin($d)) {
        http_response_code(403);
        echo '<h1>403 — Forbidden</h1><p>Admin role required.</p>';
        exit;
    }
    return $d;
}

function require_super_admin(): array {
    $d = require_doctor();
    if (!is_super_admin($d)) {
        http_response_code(403);
        echo '<h1>403 — Forbidden</h1><p>Platform staff only.</p>';
        exit;
    }
    return $d;
}

// ---------- Tenant + subscription ----------

function current_tenant(?array $doctor = null): ?array {
    $d = $doctor ?? current_doctor();
    if (!$d || empty($d['tenant_id'])) return null;
    static $cache = [];
    $tid = (int) $d['tenant_id'];
    if (isset($cache[$tid])) return $cache[$tid];
    $cache[$tid] = db_fetch('SELECT * FROM tenants WHERE id = ?', [$tid]);
    return $cache[$tid];
}

function get_tenant(int $id): ?array {
    return db_fetch('SELECT * FROM tenants WHERE id = ?', [$id]);
}

function current_subscription(?array $doctor = null): ?array {
    $t = current_tenant($doctor);
    if (!$t) return null;
    return get_subscription_for_tenant((int) $t['id']);
}

function get_subscription_for_tenant(int $tenantId): ?array {
    return db_fetch(
        'SELECT s.*, p.name AS plan_name, p.price_cents, p.currency, p.interval, p.max_doctors,
                p.max_reports_per_month, p.is_trial, p.trial_days, p.features_json
         FROM subscriptions s JOIN plans p ON p.id = s.plan_id
         WHERE s.tenant_id = ?',
        [$tenantId]
    );
}

function get_plan(string $id): ?array {
    return db_fetch('SELECT * FROM plans WHERE id = ?', [$id]);
}

function all_plans(): array {
    return db_all('SELECT * FROM plans ORDER BY sort_order ASC, price_cents ASC');
}

// ---------- Usage counters ----------

function tenant_doctor_count(int $tenantId): int {
    $r = db_fetch('SELECT COUNT(*) AS n FROM doctors WHERE tenant_id = ? AND active = 1', [$tenantId]);
    return (int) ($r['n'] ?? 0);
}

function tenant_reports_this_period(int $tenantId): int {
    // "This period" = current month based on subscription.current_period_start.
    // Fallback to month-to-date if no subscription.
    $sub = get_subscription_for_tenant($tenantId);
    $since = $sub && !empty($sub['current_period_start'])
        ? (string) $sub['current_period_start']
        : gmdate('Y-m-01 00:00:00');
    $r = db_fetch(
        "SELECT COUNT(*) AS n
         FROM diagnosis_reports r
         JOIN cases c ON c.id = r.case_id
         JOIN doctors d ON d.id = c.doctor_id
         WHERE d.tenant_id = ? AND r.created_at >= ?",
        [$tenantId, $since]
    );
    return (int) ($r['n'] ?? 0);
}

function tenant_cases_total(int $tenantId): int {
    $r = db_fetch(
        'SELECT COUNT(*) AS n FROM cases c JOIN doctors d ON d.id = c.doctor_id WHERE d.tenant_id = ?',
        [$tenantId]
    );
    return (int) ($r['n'] ?? 0);
}

// ---------- Plan enforcement ----------

/**
 * Returns null when within limits, or a translation key + values when blocked.
 *
 * @return array{message:string}|null
 */
function check_can_add_doctor(int $tenantId): ?array {
    $sub = get_subscription_for_tenant($tenantId);
    if (!$sub) return ['message' => 'No active subscription'];
    if (!in_array($sub['status'], ['active', 'trial'], true)) {
        return ['message' => 'Subscription is ' . $sub['status']];
    }
    $cap = $sub['max_doctors'];
    if ($cap === null) return null;
    $used = tenant_doctor_count($tenantId);
    if ($used >= (int) $cap) {
        return ['message' => "Plan limit reached: {$used}/{$cap} doctors. Upgrade to add more."];
    }
    return null;
}

function check_can_generate_report(int $tenantId): ?array {
    $sub = get_subscription_for_tenant($tenantId);
    if (!$sub) return ['message' => 'No active subscription'];
    if (!in_array($sub['status'], ['active', 'trial'], true)) {
        return ['message' => 'Subscription is ' . $sub['status']];
    }
    $cap = $sub['max_reports_per_month'];
    if ($cap === null) return null;
    $used = tenant_reports_this_period($tenantId);
    if ($used >= (int) $cap) {
        return ['message' => "Plan limit reached: {$used}/{$cap} reports this period. Upgrade for unlimited reports."];
    }
    return null;
}

function subscription_is_blocking(?array $sub): bool {
    if (!$sub) return true;
    if (!in_array($sub['status'], ['active', 'trial'], true)) return true;
    // Trial expired?
    if (($sub['status'] === 'trial') && !empty($sub['trial_ends_at']) && strtotime((string) $sub['trial_ends_at']) < time()) {
        return true;
    }
    return false;
}

function subscription_trial_days_remaining(?array $sub): ?int {
    if (!$sub || $sub['status'] !== 'trial' || empty($sub['trial_ends_at'])) return null;
    $diff = strtotime((string) $sub['trial_ends_at']) - time();
    return max(0, (int) ceil($diff / 86400));
}

// ---------- Tenant lifecycle ----------

function slugify(string $s): string {
    $s = strtolower(trim($s));
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s) ?? '';
    $s = trim($s, '-');
    return $s !== '' ? $s : 'org';
}

function generate_unique_slug(string $base): string {
    $slug = substr(slugify($base), 0, 40);
    if ($slug === '') $slug = 'org';
    $i = 0;
    while (db_fetch('SELECT id FROM tenants WHERE slug = ?', [$slug])) {
        $i++;
        $slug = substr($slug, 0, 36) . '-' . $i;
    }
    return $slug;
}

function create_tenant_with_admin(string $orgName, array $adminFields): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $slug = generate_unique_slug($orgName);
        $stmt = $pdo->prepare('INSERT INTO tenants (name, slug, status) VALUES (?, ?, ?)');
        $stmt->execute([$orgName, $slug, 'active']);
        $tenantId = (int) $pdo->lastInsertId();

        // Start everyone on the Trial plan unless DEFAULT_SIGNUP_PLAN is set.
        $planId = (string) env('DEFAULT_SIGNUP_PLAN', 'trial');
        $plan = $pdo->query('SELECT * FROM plans WHERE id = ' . $pdo->quote($planId))->fetch(PDO::FETCH_ASSOC);
        if (!$plan) {
            $plan = $pdo->query("SELECT * FROM plans WHERE id = 'trial'")->fetch(PDO::FETCH_ASSOC);
            $planId = 'trial';
        }
        $status = ($plan && (int) $plan['is_trial'] === 1) ? 'trial' : 'active';
        $trialEnds = ($plan && (int) ($plan['trial_days'] ?? 0) > 0)
            ? gmdate('Y-m-d H:i:s', time() + 86400 * (int) $plan['trial_days'])
            : null;
        $periodEnd = gmdate('Y-m-d H:i:s', time() + 86400 * 30);

        $pdo->prepare(
            'INSERT INTO subscriptions (tenant_id, plan_id, status, trial_ends_at, current_period_end) VALUES (?, ?, ?, ?, ?)'
        )->execute([$tenantId, $planId, $status, $trialEnds, $periodEnd]);

        $pdo->prepare(
            'INSERT INTO doctors (tenant_id, email, full_name, password_hash, license_id, specialty, role, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
        )->execute([
            $tenantId,
            $adminFields['email'],
            $adminFields['full_name'],
            password_hash($adminFields['password'], PASSWORD_DEFAULT),
            $adminFields['license_id'] ?? null,
            $adminFields['specialty'] ?? null,
            'ADMIN',
        ]);
        $adminId = (int) $pdo->lastInsertId();

        $pdo->commit();
        return ['tenant_id' => $tenantId, 'doctor_id' => $adminId];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ---------- Invitations ----------

function create_invitation(int $tenantId, int $invitedById, string $email, string $role): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Invalid email address.');
    }
    if (!in_array($role, ['DOCTOR', 'ADMIN'], true)) $role = 'DOCTOR';
    $existing = db_fetch('SELECT * FROM doctors WHERE email = ?', [$email]);
    if ($existing) {
        // If a pending invite for the same tenant exists, refresh the token; otherwise reject.
        if ((int) ($existing['tenant_id'] ?? 0) === $tenantId
            && (int) ($existing['active'] ?? 0) === 0
            && !empty($existing['invite_token'])) {
            $token = bin2hex(random_bytes(24));
            db_exec(
                'UPDATE doctors SET invite_token = ?, invite_expires_at = datetime(\'now\', \'+7 days\'), role = ? WHERE id = ?',
                [$token, $role, (int) $existing['id']]
            );
            return ['doctor_id' => (int) $existing['id'], 'token' => $token, 'email' => $email];
        }
        throw new RuntimeException('An account with this email already exists.');
    }
    $token = bin2hex(random_bytes(24));
    $id = db_insert(
        'INSERT INTO doctors (tenant_id, email, full_name, password_hash, role, active, invite_token, invite_expires_at)
         VALUES (?, ?, ?, ?, ?, 0, ?, datetime(\'now\', \'+7 days\'))',
        [$tenantId, $email, '(pending)', '', $role, $token]
    );
    return ['doctor_id' => $id, 'token' => $token, 'email' => $email];
}

function find_pending_invite(string $token): ?array {
    $r = db_fetch(
        "SELECT * FROM doctors
         WHERE invite_token = ? AND active = 0
         AND (invite_expires_at IS NULL OR invite_expires_at > datetime('now'))",
        [$token]
    );
    return $r ?: null;
}
