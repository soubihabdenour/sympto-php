<?php
declare(strict_types=1);

function audit(string $action, ?int $doctorId = null, ?int $caseId = null, $detail = null): void {
    try {
        db_exec(
            'INSERT INTO audit_logs (doctor_id, case_id, action, detail, ip, user_agent) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $doctorId,
                $caseId,
                $action,
                $detail !== null ? json_encode($detail) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]
        );
    } catch (Throwable $e) {
        // never fail a request because of audit
        error_log('audit failed: ' . $e->getMessage());
    }
}
