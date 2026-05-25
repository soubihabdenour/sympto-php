<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $relPath = env('DB_PATH', 'storage/db.sqlite');
    $absPath = str_starts_with($relPath, '/') ? $relPath : APP_ROOT . '/' . $relPath;
    $dir = dirname($absPath);
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $pdo = new PDO('sqlite:' . $absPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    return $pdo;
}

function db_init(): void {
    $pdo = db();
    // ---------------- Core schema (creates tables if missing) ----------------
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS tenants (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            status TEXT NOT NULL DEFAULT 'active',  -- active | suspended
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS plans (
            id TEXT PRIMARY KEY,                    -- 'trial' | 'solo' | 'practice' | 'clinic'
            name TEXT NOT NULL,
            price_cents INTEGER NOT NULL DEFAULT 0,
            currency TEXT NOT NULL DEFAULT 'USD',
            interval TEXT NOT NULL DEFAULT 'monthly', -- monthly | yearly
            max_doctors INTEGER,                    -- NULL = unlimited
            max_reports_per_month INTEGER,          -- NULL = unlimited
            is_trial INTEGER NOT NULL DEFAULT 0,
            trial_days INTEGER NOT NULL DEFAULT 0,
            sort_order INTEGER NOT NULL DEFAULT 0,
            features_json TEXT
        );
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER NOT NULL UNIQUE REFERENCES tenants(id) ON DELETE CASCADE,
            plan_id TEXT NOT NULL REFERENCES plans(id),
            status TEXT NOT NULL DEFAULT 'active',  -- active | trial | past_due | canceled
            trial_ends_at TEXT,
            current_period_start TEXT NOT NULL DEFAULT (datetime('now')),
            current_period_end TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tenant_id INTEGER REFERENCES tenants(id) ON DELETE CASCADE,
            email TEXT NOT NULL UNIQUE,
            full_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            license_id TEXT,
            specialty TEXT,
            role TEXT NOT NULL DEFAULT 'DOCTOR',     -- SUPER_ADMIN | ADMIN | DOCTOR
            active INTEGER NOT NULL DEFAULT 1,
            invite_token TEXT,
            invite_expires_at TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS cases (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            specialty_id TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'OPEN',
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_cases_doctor ON cases(doctor_id);
        CREATE TABLE IF NOT EXISTS patient_data (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL UNIQUE REFERENCES cases(id) ON DELETE CASCADE,
            age_years INTEGER,
            sex TEXT,
            symptoms TEXT,
            medical_history TEXT,
            medications TEXT,
            allergies TEXT,
            vital_signs TEXT,
            lab_values TEXT,
            imaging_summary TEXT,
            initial_diagnosis TEXT,
            clinical_question TEXT
        );
        CREATE TABLE IF NOT EXISTS medical_documents (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            filename TEXT NOT NULL,
            stored_path TEXT NOT NULL,
            kind TEXT NOT NULL,
            mime_type TEXT,
            size_bytes INTEGER NOT NULL,
            uploaded_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS extracted_text (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            document_id INTEGER NOT NULL UNIQUE REFERENCES medical_documents(id) ON DELETE CASCADE,
            text TEXT NOT NULL DEFAULT '',
            summary TEXT,
            edited INTEGER NOT NULL DEFAULT 0
        );
        CREATE TABLE IF NOT EXISTS case_messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            role TEXT NOT NULL,
            content TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_messages_case ON case_messages(case_id);
        CREATE TABLE IF NOT EXISTS diagnosis_reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            content_json TEXT NOT NULL,
            summary TEXT,
            uncertainty TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_reports_case ON diagnosis_reports(case_id);
        CREATE TABLE IF NOT EXISTS research_sources (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            title TEXT NOT NULL,
            source TEXT NOT NULL,
            url TEXT,
            is_mock INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS audit_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER REFERENCES doctors(id) ON DELETE SET NULL,
            case_id INTEGER REFERENCES cases(id) ON DELETE SET NULL,
            action TEXT NOT NULL,
            detail TEXT,
            ip TEXT,
            user_agent TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
    SQL);

    // ---------------- Idempotent column migrations for pre-tenancy DBs ----------------
    // These must run BEFORE creating indexes that reference the new columns,
    // because on a legacy single-tenant DB the `doctors` table already exists
    // (so CREATE TABLE IF NOT EXISTS doctors above is a no-op) and the new
    // columns aren't there yet.
    db_add_column_if_missing('doctors', 'tenant_id', 'INTEGER REFERENCES tenants(id) ON DELETE CASCADE');
    db_add_column_if_missing('doctors', 'active', 'INTEGER NOT NULL DEFAULT 1');
    db_add_column_if_missing('doctors', 'invite_token', 'TEXT');
    db_add_column_if_missing('doctors', 'invite_expires_at', 'TEXT');

    // Indexes on the columns above — safe to create now that the columns exist.
    $pdo->exec(
        "CREATE INDEX IF NOT EXISTS idx_doctors_tenant ON doctors(tenant_id);
         CREATE INDEX IF NOT EXISTS idx_doctors_invite ON doctors(invite_token);"
    );

    // ---------------- Seed plan catalog ----------------
    seed_plans($pdo);

    // ---------------- Seed demo tenant + accounts ----------------
    seed_demo_tenant($pdo);
}

function db_add_column_if_missing(string $table, string $column, string $ddl): void {
    $pdo = db();
    $cols = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        if (($c['name'] ?? null) === $column) return;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$ddl}");
}

function seed_plans(PDO $pdo): void {
    // Upsert plan catalog. Update name/limits in place; never delete subscriptions.
    $plans = [
        ['id'=>'trial',    'name'=>'Trial',           'price_cents'=>0,     'max_doctors'=>1,    'max_reports_per_month'=>5,    'is_trial'=>1, 'trial_days'=>14, 'sort_order'=>0,
            'features_json'=>json_encode(['14-day evaluation','1 doctor','5 AI reports / month','All 15 specialist agents'])],
        ['id'=>'solo',     'name'=>'Solo',            'price_cents'=>4900,  'max_doctors'=>1,    'max_reports_per_month'=>null, 'is_trial'=>0, 'trial_days'=>0,  'sort_order'=>1,
            'features_json'=>json_encode(['1 doctor','Unlimited AI reports','All 15 specialist agents','Document uploads'])],
        ['id'=>'practice', 'name'=>'Practice',        'price_cents'=>14900, 'max_doctors'=>10,   'max_reports_per_month'=>null, 'is_trial'=>0, 'trial_days'=>0,  'sort_order'=>2,
            'features_json'=>json_encode(['Up to 10 doctors','Unlimited AI reports','Admin team management','Priority support'])],
        ['id'=>'clinic',   'name'=>'Clinic',          'price_cents'=>39900, 'max_doctors'=>50,   'max_reports_per_month'=>null, 'is_trial'=>0, 'trial_days'=>0,  'sort_order'=>3,
            'features_json'=>json_encode(['Up to 50 doctors','Unlimited AI reports','Admin team management','Priority support','SSO (coming soon)'])],
    ];
    $stmt = $pdo->prepare(
        'INSERT INTO plans (id, name, price_cents, currency, interval, max_doctors, max_reports_per_month, is_trial, trial_days, sort_order, features_json)
         VALUES (:id, :name, :price_cents, "USD", "monthly", :max_doctors, :max_rpm, :is_trial, :trial_days, :sort_order, :features)
         ON CONFLICT(id) DO UPDATE SET
            name = excluded.name,
            price_cents = excluded.price_cents,
            max_doctors = excluded.max_doctors,
            max_reports_per_month = excluded.max_reports_per_month,
            is_trial = excluded.is_trial,
            trial_days = excluded.trial_days,
            sort_order = excluded.sort_order,
            features_json = excluded.features_json'
    );
    foreach ($plans as $p) {
        $stmt->execute([
            ':id'=>$p['id'], ':name'=>$p['name'], ':price_cents'=>$p['price_cents'],
            ':max_doctors'=>$p['max_doctors'], ':max_rpm'=>$p['max_reports_per_month'],
            ':is_trial'=>$p['is_trial'], ':trial_days'=>$p['trial_days'], ':sort_order'=>$p['sort_order'],
            ':features'=>$p['features_json'],
        ]);
    }
}

function seed_demo_tenant(PDO $pdo): void {
    $doctorCount = (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    $tenantCount = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();

    // First-run path: nothing exists yet → seed super-admin + demo tenant + admin + demo doctor.
    if ($doctorCount === 0 && $tenantCount === 0) {
        $tenantId = (int) $pdo->prepare('INSERT INTO tenants (name, slug, status) VALUES (?, ?, ?)')->execute(['Demo Clinic', 'demo-clinic', 'active']) ? (int) $pdo->lastInsertId() : 0;
        // Subscribe demo tenant to Practice plan
        $pdo->prepare('INSERT INTO subscriptions (tenant_id, plan_id, status, current_period_end) VALUES (?, ?, ?, datetime(\'now\', \'+30 days\'))')
            ->execute([$tenantId, 'practice', 'active']);

        // Super-admin platform account — not attached to a tenant
        $pdo->prepare('INSERT INTO doctors (tenant_id, email, full_name, password_hash, role, active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([null, 'super@medagent.local', 'Platform Super-Admin', password_hash('medagent123', PASSWORD_DEFAULT), 'SUPER_ADMIN']);

        // Tenant admin
        $pdo->prepare('INSERT INTO doctors (tenant_id, email, full_name, password_hash, role, active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$tenantId, 'admin@medagent.local', 'Demo Clinic Admin', password_hash('medagent123', PASSWORD_DEFAULT), 'ADMIN']);

        // Demo doctor — preserves the legacy seed credentials
        $pdo->prepare('INSERT INTO doctors (tenant_id, email, full_name, password_hash, role, active, specialty) VALUES (?, ?, ?, ?, ?, 1, ?)')
            ->execute([$tenantId, 'doctor@medagent.local', 'Dr. Demo', password_hash('medagent123', PASSWORD_DEFAULT), 'DOCTOR', 'General Medicine']);
        return;
    }

    // Migration path: existing doctors but no tenants yet → backfill into a single demo tenant
    // so old single-tenant deployments keep working.
    if ($tenantCount === 0 && $doctorCount > 0) {
        $pdo->prepare('INSERT INTO tenants (name, slug, status) VALUES (?, ?, ?)')->execute(['Demo Clinic', 'demo-clinic', 'active']);
        $tenantId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO subscriptions (tenant_id, plan_id, status, current_period_end) VALUES (?, ?, ?, datetime(\'now\', \'+30 days\'))')
            ->execute([$tenantId, 'practice', 'active']);
        $pdo->prepare('UPDATE doctors SET tenant_id = ? WHERE tenant_id IS NULL')->execute([$tenantId]);

        // Promote the first legacy doctor to ADMIN, leave rest as DOCTOR.
        $firstId = (int) $pdo->query('SELECT id FROM doctors ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($firstId > 0) {
            $pdo->prepare('UPDATE doctors SET role = ? WHERE id = ?')->execute(['ADMIN', $firstId]);
        }

        // Create a super-admin if SUPER_ADMIN_EMAIL is set in env, otherwise create the default one.
        $superEmail = (string) env('SUPER_ADMIN_EMAIL', 'super@medagent.local');
        $exists = $pdo->prepare('SELECT id FROM doctors WHERE email = ?');
        $exists->execute([$superEmail]);
        if (!$exists->fetch()) {
            $pdo->prepare('INSERT INTO doctors (tenant_id, email, full_name, password_hash, role, active) VALUES (?, ?, ?, ?, ?, 1)')
                ->execute([null, $superEmail, 'Platform Super-Admin', password_hash((string) env('SUPER_ADMIN_PASSWORD', 'medagent123'), PASSWORD_DEFAULT), 'SUPER_ADMIN']);
        }
    }
}

// Convenience query helpers
function db_fetch(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_exec(string $sql, array $params = []): void {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
}

function db_insert(string $sql, array $params = []): int {
    db_exec($sql, $params);
    return (int) db()->lastInsertId();
}
