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
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS doctors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            full_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            license_id TEXT,
            specialty TEXT,
            role TEXT NOT NULL DEFAULT 'DOCTOR',
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

    // Seed demo doctor if no doctors exist
    $count = (int) $pdo->query('SELECT COUNT(*) FROM doctors')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO doctors (email, full_name, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            'doctor@medagent.local',
            'Dr. Demo',
            password_hash('medagent123', PASSWORD_DEFAULT),
            'DOCTOR',
        ]);
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
