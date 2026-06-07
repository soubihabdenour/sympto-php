<?php
declare(strict_types=1);

// ---------------- Paths ----------------
define('APP_ROOT', dirname(__DIR__));
define('STORAGE_DIR', APP_ROOT . '/storage');
define('UPLOADS_DIR', STORAGE_DIR . '/uploads');
define('LANG_DIR', APP_ROOT . '/lang');
define('TEMPLATES_DIR', APP_ROOT . '/templates');

// ---------------- .env loader (tiny) ----------------
function load_env(string $path): void {
    if (!is_file($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        // Strip surrounding quotes
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}
load_env(APP_ROOT . '/.env');

function env(string $key, ?string $default = null): ?string {
    $v = getenv($key);
    if ($v === false || $v === '') return $default;
    return $v;
}

// ---------------- Sessions ----------------
session_name('medagent_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// ---------------- Core requires ----------------
require __DIR__ . '/db.php';
require __DIR__ . '/router.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/csrf.php';
require __DIR__ . '/audit.php';
require __DIR__ . '/specialties.php';
require __DIR__ . '/llm/index.php';
require __DIR__ . '/research/search.php';
require __DIR__ . '/documents/parse.php';
require __DIR__ . '/agents/prompts.php';
require __DIR__ . '/agents/orchestrator.php';
require __DIR__ . '/push.php';

// ---------------- Helpers ----------------
function h(?string $s): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function render(string $template, array $vars = []): void {
    extract($vars, EXTR_SKIP);
    require TEMPLATES_DIR . '/' . $template . '.php';
}

function json_response($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function not_found(): void {
    http_response_code(404);
    echo '<h1>404 — Not found</h1>';
    exit;
}

function bad_request(string $msg = 'Bad request'): void {
    http_response_code(400);
    echo h($msg);
    exit;
}

// Initialize DB and seed demo doctor on first request
db_init();
push_migrate();
