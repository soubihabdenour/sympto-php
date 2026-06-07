<?php
declare(strict_types=1);

/**
 * Medication reminders.
 *
 * Doctors create reminders attached to a case. The cron endpoint at
 * /api/cron/reminders runs every minute, finds reminders whose next_due_at
 * has elapsed, sends a push notification to the owning doctor's subscribed
 * devices via OneSignal, then advances next_due_at by repeat_interval_minutes
 * (or marks the reminder DONE if the interval is 0 or repeat_until has been
 * crossed).
 *
 * Privacy: the notification body is built from the doctor-set patient_label
 * (e.g. "Bed 12", "Mr. S") plus the medication / dosage. We DO NOT include
 * patient demographics or anything from patient_data — see push_send().
 *
 * All times stored in UTC (ISO-8601, "YYYY-MM-DD HH:MM:SS").
 */

const REMINDER_STATUSES = ['active', 'paused', 'done'];
const REMINDER_INTERVAL_PRESETS = [0, 240, 360, 480, 720, 1440];   // minutes; 0 = one-shot
const REMINDER_MAX_INTERVAL = 10080;                                 // 1 week
const REMINDER_MAX_PER_CASE = 50;
const REMINDER_BATCH = 100;       // max notifications dispatched per cron tick

function reminders_migrate(): void {
    db()->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS medication_reminders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            case_id INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
            doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
            medication TEXT NOT NULL,
            dosage TEXT,
            notes TEXT,
            patient_label TEXT,
            next_due_at TEXT NOT NULL,
            repeat_interval_minutes INTEGER NOT NULL DEFAULT 0,
            repeat_until TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            last_sent_at TEXT,
            last_error TEXT,
            sent_count INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE INDEX IF NOT EXISTS idx_reminders_due
            ON medication_reminders(status, next_due_at);
        CREATE INDEX IF NOT EXISTS idx_reminders_case
            ON medication_reminders(case_id);
SQL);
}

/**
 * Validate + parse a user-supplied "datetime-local" string into a UTC SQL
 * datetime. The browser sends local time without timezone; we offset using
 * the caller-supplied IANA tz name (or UTC if unknown).
 */
function reminder_parse_local(string $local, ?string $tz): string {
    $local = trim($local);
    if ($local === '') {
        throw new InvalidArgumentException('start_at required');
    }
    $tzName = $tz !== null && $tz !== '' ? $tz : 'UTC';
    try {
        $tzObj = new DateTimeZone($tzName);
    } catch (Exception $_) {
        $tzObj = new DateTimeZone('UTC');
    }
    try {
        $dt = new DateTime($local, $tzObj);
    } catch (Exception $e) {
        throw new InvalidArgumentException('invalid datetime: ' . $e->getMessage());
    }
    $dt->setTimezone(new DateTimeZone('UTC'));
    return $dt->format('Y-m-d H:i:s');
}

function reminder_now_utc(): string {
    return gmdate('Y-m-d H:i:s');
}

function reminder_add_minutes(string $sqlUtc, int $minutes): string {
    $ts = strtotime($sqlUtc . ' UTC');
    if ($ts === false) return $sqlUtc;
    return gmdate('Y-m-d H:i:s', $ts + $minutes * 60);
}

function reminder_is_past(string $sqlUtc, ?string $referenceUtc = null): bool {
    $ref = strtotime(($referenceUtc ?? reminder_now_utc()) . ' UTC');
    $when = strtotime($sqlUtc . ' UTC');
    return $when !== false && $ref !== false && $when <= $ref;
}

function reminders_for_case(int $caseId): array {
    return db_all(
        'SELECT * FROM medication_reminders
         WHERE case_id = ?
         ORDER BY CASE status WHEN \'active\' THEN 0 WHEN \'paused\' THEN 1 ELSE 2 END,
                  next_due_at ASC',
        [$caseId]
    );
}

function reminder_fetch(int $id, int $caseId): ?array {
    return db_fetch(
        'SELECT * FROM medication_reminders WHERE id = ? AND case_id = ?',
        [$id, $caseId]
    );
}

/**
 * Create a reminder. Caller is responsible for verifying case ownership.
 */
function reminder_create(int $caseId, int $doctorId, array $in, ?string $tz): int {
    $count = (int) (db_fetch(
        'SELECT COUNT(*) AS n FROM medication_reminders WHERE case_id = ?',
        [$caseId]
    )['n'] ?? 0);
    if ($count >= REMINDER_MAX_PER_CASE) {
        throw new RuntimeException('too many reminders for this case');
    }

    $medication = trim((string) ($in['medication'] ?? ''));
    if ($medication === '' || mb_strlen($medication) > 120) {
        throw new InvalidArgumentException('medication required (≤120 chars)');
    }
    $dosage = trim((string) ($in['dosage'] ?? ''));
    if (mb_strlen($dosage) > 120) {
        throw new InvalidArgumentException('dosage too long');
    }
    $notes = trim((string) ($in['notes'] ?? ''));
    if (mb_strlen($notes) > 500) {
        throw new InvalidArgumentException('notes too long');
    }
    $patient = trim((string) ($in['patient_label'] ?? ''));
    if (mb_strlen($patient) > 80) {
        throw new InvalidArgumentException('patient label too long');
    }

    $nextDue = reminder_parse_local((string) ($in['start_at'] ?? ''), $tz);

    $interval = (int) ($in['repeat_interval_minutes'] ?? 0);
    if ($interval < 0 || $interval > REMINDER_MAX_INTERVAL) {
        throw new InvalidArgumentException('invalid interval');
    }
    $repeatUntil = null;
    $repeatUntilRaw = trim((string) ($in['repeat_until'] ?? ''));
    if ($interval > 0 && $repeatUntilRaw !== '') {
        $repeatUntil = reminder_parse_local($repeatUntilRaw, $tz);
        if (strtotime($repeatUntil . ' UTC') <= strtotime($nextDue . ' UTC')) {
            throw new InvalidArgumentException('repeat_until must be after start_at');
        }
    }

    return db_insert(
        'INSERT INTO medication_reminders
            (case_id, doctor_id, medication, dosage, notes, patient_label,
             next_due_at, repeat_interval_minutes, repeat_until, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'active\')',
        [
            $caseId, $doctorId,
            $medication,
            $dosage !== '' ? $dosage : null,
            $notes !== '' ? $notes : null,
            $patient !== '' ? $patient : null,
            $nextDue,
            $interval,
            $repeatUntil,
        ]
    );
}

function reminder_delete(int $id, int $caseId): void {
    db_exec('DELETE FROM medication_reminders WHERE id = ? AND case_id = ?', [$id, $caseId]);
}

function reminder_set_status(int $id, int $caseId, string $status): void {
    if (!in_array($status, REMINDER_STATUSES, true)) {
        throw new InvalidArgumentException('invalid status');
    }
    db_exec(
        "UPDATE medication_reminders
         SET status = ?, updated_at = datetime('now')
         WHERE id = ? AND case_id = ?",
        [$status, $id, $caseId]
    );
}

/**
 * Push next_due_at forward by N minutes (does not change repeat schedule).
 * Clamps to 5 minutes minimum, 24h maximum to keep accidental fat-fingers
 * from disabling reminders.
 */
function reminder_snooze(int $id, int $caseId, int $minutes): void {
    $minutes = max(5, min(24 * 60, $minutes));
    $r = reminder_fetch($id, $caseId);
    if (!$r) return;
    $base = reminder_is_past($r['next_due_at']) ? reminder_now_utc() : $r['next_due_at'];
    $newDue = reminder_add_minutes($base, $minutes);
    db_exec(
        "UPDATE medication_reminders
         SET next_due_at = ?, status = 'active', updated_at = datetime('now')
         WHERE id = ? AND case_id = ?",
        [$newDue, $id, $caseId]
    );
}

/**
 * Find every active reminder whose next_due_at has elapsed.
 * Limited per cron tick to avoid runaway batches.
 */
function reminders_due_now(int $limit = REMINDER_BATCH): array {
    $limit = max(1, min(1000, $limit));
    return db_all(
        "SELECT r.*, c.title AS case_title
         FROM medication_reminders r
         JOIN cases c ON c.id = r.case_id
         WHERE r.status = 'active' AND r.next_due_at <= datetime('now')
         ORDER BY r.next_due_at ASC
         LIMIT $limit"
    );
}

/**
 * Build the (PHI-light) notification payload for one reminder.
 */
function reminder_payload(array $r): array {
    $title = 'Medication due: ' . $r['medication'];
    $bodyParts = [];
    if (!empty($r['patient_label'])) $bodyParts[] = $r['patient_label'];
    if (!empty($r['dosage']))        $bodyParts[] = $r['dosage'];
    if (!empty($r['notes']) && mb_strlen($r['notes']) <= 80) {
        $bodyParts[] = $r['notes'];
    }
    $body = $bodyParts ? implode(' · ', $bodyParts) : 'Tap to view the case.';
    return [
        'title' => mb_substr($title, 0, 120),
        'body'  => mb_substr($body, 0, 500),
    ];
}

/**
 * Dispatch one reminder: send push, then advance next_due_at (or mark done).
 * Returns the push_send() result.
 */
function reminder_dispatch(array $r): array {
    $payload = reminder_payload($r);
    $publicUrl = rtrim((string) env('APP_PUBLIC_URL', ''), '/');
    $opts = [
        'audience'  => 'doctor',
        'doctor_id' => (int) $r['doctor_id'],
        'url'       => $publicUrl !== ''
            ? $publicUrl . '/cases/' . (int) $r['case_id'] . '#reminders'
            : null,
    ];
    $result = push_send($payload['title'], $payload['body'], $opts);

    // Advance schedule regardless of success — we don't want a transient
    // OneSignal hiccup to retry forever on the same minute and create a
    // notification storm. Errors are recorded on the row.
    [$nextDue, $newStatus] = reminder_compute_next($r);

    db_exec(
        "UPDATE medication_reminders
         SET next_due_at = ?, status = ?, last_sent_at = datetime('now'),
             last_error = ?, sent_count = sent_count + 1,
             updated_at = datetime('now')
         WHERE id = ?",
        [
            $nextDue,
            $newStatus,
            $result['ok'] ? null : mb_substr((string) ($result['error'] ?? 'unknown'), 0, 240),
            (int) $r['id'],
        ]
    );

    if (function_exists('audit')) {
        audit(
            'reminder.fire',
            (int) $r['doctor_id'],
            (int) $r['case_id'],
            [
                'reminder_id' => (int) $r['id'],
                'medication'  => $r['medication'],
                'ok'          => (bool) $result['ok'],
                'recipients'  => (int) ($result['recipients'] ?? 0),
                'status'      => $newStatus,
            ]
        );
    }
    return $result;
}

/**
 * Decide the next due time after dispatching the current one.
 * Returns [next_due_at_sql_utc, new_status].
 */
function reminder_compute_next(array $r): array {
    $interval = (int) ($r['repeat_interval_minutes'] ?? 0);
    if ($interval === 0) {
        return [$r['next_due_at'], 'done'];
    }
    $now = reminder_now_utc();
    // Catch up if the worker was offline for a while — skip to the next
    // future occurrence rather than firing once for every missed slot.
    $candidate = reminder_add_minutes($r['next_due_at'], $interval);
    while (reminder_is_past($candidate, $now)) {
        $candidate = reminder_add_minutes($candidate, $interval);
    }
    if (!empty($r['repeat_until']) && reminder_is_past($r['repeat_until'], $candidate)) {
        return [$candidate, 'done'];
    }
    return [$candidate, 'active'];
}

/**
 * Drive one cron tick. Returns a summary suitable for JSON output.
 */
function reminders_run_cron(int $limit = REMINDER_BATCH): array {
    $due = reminders_due_now($limit);
    $sent = 0; $failed = 0; $skipped = 0;
    $errors = [];
    foreach ($due as $r) {
        if (!push_is_configured()) {
            // No push backend configured -> still advance, but mark skipped
            // so we don't sit on stuck rows forever.
            [$nextDue, $newStatus] = reminder_compute_next($r);
            db_exec(
                "UPDATE medication_reminders
                 SET next_due_at = ?, status = ?, last_error = ?, updated_at = datetime('now')
                 WHERE id = ?",
                [$nextDue, $newStatus, 'onesignal_not_configured', (int) $r['id']]
            );
            $skipped++;
            continue;
        }
        $res = reminder_dispatch($r);
        if ($res['ok']) {
            $sent++;
        } else {
            $failed++;
            $errors[] = ['reminder_id' => (int) $r['id'], 'error' => (string) ($res['error'] ?? '')];
        }
    }
    return [
        'considered' => count($due),
        'sent'       => $sent,
        'failed'     => $failed,
        'skipped'    => $skipped,
        'errors'     => $errors,
        'ran_at'     => reminder_now_utc(),
    ];
}

/**
 * Format a UTC SQL datetime into "YYYY-MM-DDTHH:MM" suitable for a
 * <input type="datetime-local"> value, shifted into the supplied tz.
 */
function reminder_to_local_input(string $sqlUtc, ?string $tz): string {
    try {
        $dt = new DateTime($sqlUtc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($tz !== null && $tz !== '' ? $tz : 'UTC'));
        return $dt->format('Y-m-d\\TH:i');
    } catch (Exception $_) {
        return $sqlUtc;
    }
}
