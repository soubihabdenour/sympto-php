<?php
declare(strict_types=1);

/**
 * MedAgent AI — Push notifications via OneSignal.
 *
 * Responsibilities:
 *   - Schema migration for `push_subscriptions` (player_id <-> doctor_id).
 *   - subscribe / unsubscribe / list helpers used by routes.php.
 *   - OneSignal REST client to broadcast or target individual users.
 *
 * Environment variables (all read from .env via the existing env() helper):
 *   ONESIGNAL_APP_ID         – the OneSignal application UUID
 *   ONESIGNAL_REST_API_KEY   – the REST API key (keep server-side only)
 *   APP_PUBLIC_URL           – optional; used as default redirect for clicks
 *
 * If either of the first two is missing, broadcasts return a configuration
 * error rather than silently failing. The /api/push/subscribe endpoints work
 * regardless (they just record local subscriptions for future use).
 */

const PUSH_PLATFORMS = ['ios', 'android', 'macos', 'windows', 'web'];

function push_migrate(): void {
    db()->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            doctor_id INTEGER NOT NULL REFERENCES doctors(id) ON DELETE CASCADE,
            player_id TEXT NOT NULL,
            platform TEXT,
            user_agent TEXT,
            ip TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            last_seen_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(doctor_id, player_id)
        );
        CREATE INDEX IF NOT EXISTS idx_push_doctor ON push_subscriptions(doctor_id);
        CREATE INDEX IF NOT EXISTS idx_push_player ON push_subscriptions(player_id);

        CREATE TABLE IF NOT EXISTS push_broadcasts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            admin_id INTEGER REFERENCES doctors(id) ON DELETE SET NULL,
            title TEXT NOT NULL,
            body TEXT NOT NULL,
            url TEXT,
            audience TEXT NOT NULL DEFAULT 'all',
            recipients INTEGER NOT NULL DEFAULT 0,
            onesignal_id TEXT,
            error TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
SQL);
}

function push_is_configured(): bool {
    return (string) env('ONESIGNAL_APP_ID', '') !== ''
        && (string) env('ONESIGNAL_REST_API_KEY', '') !== '';
}

function push_subscribe(int $doctorId, string $playerId, ?string $platform = null): void {
    $playerId = trim($playerId);
    if (!preg_match('/^[A-Za-z0-9_\-]{8,128}$/', $playerId)) {
        throw new InvalidArgumentException('invalid player_id');
    }
    if ($platform !== null && !in_array($platform, PUSH_PLATFORMS, true)) {
        $platform = null;
    }
    db_exec(
        "INSERT INTO push_subscriptions (doctor_id, player_id, platform, user_agent, ip)
         VALUES (?, ?, ?, ?, ?)
         ON CONFLICT(doctor_id, player_id) DO UPDATE SET
            platform = COALESCE(excluded.platform, push_subscriptions.platform),
            user_agent = excluded.user_agent,
            ip = excluded.ip,
            last_seen_at = datetime('now')",
        [
            $doctorId,
            $playerId,
            $platform,
            mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]
    );
}

function push_unsubscribe(int $doctorId, string $playerId): void {
    db_exec(
        'DELETE FROM push_subscriptions WHERE doctor_id = ? AND player_id = ?',
        [$doctorId, $playerId]
    );
}

function push_unsubscribe_all_for_doctor(int $doctorId): void {
    db_exec('DELETE FROM push_subscriptions WHERE doctor_id = ?', [$doctorId]);
}

function push_player_ids_for_doctor(int $doctorId): array {
    $rows = db_all(
        'SELECT player_id FROM push_subscriptions WHERE doctor_id = ?',
        [$doctorId]
    );
    return array_column($rows, 'player_id');
}

function push_subscribed_doctor_count(): int {
    $row = db_fetch('SELECT COUNT(DISTINCT doctor_id) AS n FROM push_subscriptions');
    return (int) ($row['n'] ?? 0);
}

/**
 * Send a notification via OneSignal REST API.
 *
 * @param string      $title    Notification title.
 * @param string      $body     Body / message.
 * @param array       $opts     {
 *     audience?: 'all'|'doctors'|'admins'|'doctor',
 *     doctor_id?: int,                 // required when audience='doctor'
 *     url?: string,                    // open URL on click
 *     player_ids?: string[],           // explicit override
 * }
 *
 * @return array { ok: bool, recipients?: int, onesignal_id?: string, error?: string }
 */
function push_send(string $title, string $body, array $opts = []): array {
    if (!push_is_configured()) {
        return ['ok' => false, 'error' => 'OneSignal is not configured (set ONESIGNAL_APP_ID and ONESIGNAL_REST_API_KEY).'];
    }
    $title = trim($title);
    $body  = trim($body);
    if ($title === '' || $body === '') {
        return ['ok' => false, 'error' => 'Title and body are required.'];
    }
    if (mb_strlen($title) > 120 || mb_strlen($body) > 500) {
        return ['ok' => false, 'error' => 'Title or body too long.'];
    }

    $appId = (string) env('ONESIGNAL_APP_ID');
    $apiKey = (string) env('ONESIGNAL_REST_API_KEY');
    $url = isset($opts['url']) && $opts['url'] !== '' ? (string) $opts['url'] : null;
    if ($url !== null && !preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'error' => 'URL must start with http(s)://'];
    }

    $payload = [
        'app_id'    => $appId,
        'headings'  => ['en' => $title],
        'contents'  => ['en' => $body],
        'chrome_web_icon' => (string) env('APP_PUBLIC_URL', '') . '/icons/icon-192.png',
        'chrome_web_badge' => (string) env('APP_PUBLIC_URL', '') . '/icons/icon-96.png',
    ];
    if ($url !== null) {
        $payload['url'] = $url;
        $payload['web_url'] = $url;
    }

    $audience = (string) ($opts['audience'] ?? 'all');
    $recipients = 0;

    if (!empty($opts['player_ids']) && is_array($opts['player_ids'])) {
        $payload['include_subscription_ids'] = array_values(array_filter(array_map('strval', $opts['player_ids'])));
        $recipients = count($payload['include_subscription_ids']);
    } elseif ($audience === 'doctor') {
        $did = (int) ($opts['doctor_id'] ?? 0);
        if ($did <= 0) return ['ok' => false, 'error' => 'doctor_id required for audience=doctor'];
        $ids = push_player_ids_for_doctor($did);
        if (empty($ids)) return ['ok' => false, 'error' => 'Doctor has no push subscriptions.'];
        $payload['include_subscription_ids'] = $ids;
        $recipients = count($ids);
    } elseif ($audience === 'admins') {
        $rows = db_all(
            "SELECT ps.player_id FROM push_subscriptions ps
             JOIN doctors d ON d.id = ps.doctor_id WHERE d.role = 'ADMIN'"
        );
        $ids = array_column($rows, 'player_id');
        if (empty($ids)) return ['ok' => false, 'error' => 'No admins are subscribed yet.'];
        $payload['include_subscription_ids'] = $ids;
        $recipients = count($ids);
    } elseif ($audience === 'doctors' || $audience === 'all') {
        // Send to everyone subscribed to the app.
        $payload['included_segments'] = ['Subscribed Users'];
        $recipients = push_subscribed_doctor_count();   // best-effort estimate
    } else {
        return ['ok' => false, 'error' => 'Unknown audience: ' . $audience];
    }

    [$status, $resp] = push_http_post(
        'https://api.onesignal.com/notifications',
        $payload,
        ['Authorization: Basic ' . $apiKey, 'Content-Type: application/json; charset=utf-8']
    );

    $data = json_decode((string) $resp, true) ?: [];
    if ($status >= 200 && $status < 300 && empty($data['errors'])) {
        return [
            'ok' => true,
            'recipients' => (int) ($data['recipients'] ?? $recipients),
            'onesignal_id' => (string) ($data['id'] ?? ''),
        ];
    }
    $err = is_array($data['errors'] ?? null)
        ? implode('; ', array_map('strval', $data['errors']))
        : (is_string($data['errors'] ?? null) ? $data['errors'] : ('HTTP ' . $status));
    return ['ok' => false, 'error' => $err];
}

/**
 * Record a broadcast attempt (success or failure).
 */
function push_log_broadcast(int $adminId, string $title, string $body, ?string $url, string $audience, array $result): int {
    return db_insert(
        'INSERT INTO push_broadcasts (admin_id, title, body, url, audience, recipients, onesignal_id, error)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $adminId,
            $title,
            $body,
            $url,
            $audience,
            (int) ($result['recipients'] ?? 0),
            (string) ($result['onesignal_id'] ?? ''),
            $result['ok'] ? null : (string) ($result['error'] ?? ''),
        ]
    );
}

function push_recent_broadcasts(int $limit = 25): array {
    $limit = max(1, min(200, $limit));
    return db_all(
        'SELECT b.*, d.full_name AS admin_name, d.email AS admin_email
         FROM push_broadcasts b LEFT JOIN doctors d ON d.id = b.admin_id
         ORDER BY b.id DESC LIMIT ' . $limit
    );
}

/**
 * Tiny cURL wrapper. Returns [http_status, body].
 */
function push_http_post(string $url, array $jsonBody, array $headers): array {
    if (!function_exists('curl_init')) {
        return [0, json_encode(['errors' => 'php-curl extension is required'])];
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        $err = curl_error($ch) ?: 'unknown curl error';
        curl_close($ch);
        return [0, json_encode(['errors' => $err])];
    }
    curl_close($ch);
    return [$status, (string) $body];
}
