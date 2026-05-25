<?php
declare(strict_types=1);

// Tiny pattern-based router.
//
// route('GET', '/cases/{id}', function($id) { ... });
// dispatch_request();

$GLOBALS['__routes'] = [];

function route(string $method, string $pattern, callable $handler): void {
    $GLOBALS['__routes'][] = [strtoupper($method), $pattern, $handler];
}

function dispatch_request(): void {
    $method = $_SERVER['REQUEST_METHOD'];
    // Method override for browsers (HTML forms only POST/GET)
    if ($method === 'POST' && !empty($_POST['_method'])) {
        $method = strtoupper($_POST['_method']);
    }
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    // Strip script-name prefix if we're not at the doc root (e.g. subfolder hosting)
    $script = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script !== '' && $script !== '/' && str_starts_with($uri, $script)) {
        $uri = substr($uri, strlen($script));
    }
    if ($uri === '') $uri = '/';

    foreach ($GLOBALS['__routes'] as [$m, $pattern, $handler]) {
        if ($m !== $method && !($method === 'HEAD' && $m === 'GET')) continue;
        $regex = pattern_to_regex($pattern);
        if (preg_match($regex, $uri, $m_)) {
            // Pass only named captures, in declared order, as positional args.
            $args = [];
            foreach ($m_ as $k => $v) {
                if (is_string($k)) $args[] = $v;
            }
            try {
                $handler(...$args);
            } catch (Throwable $e) {
                http_response_code(500);
                error_log('Handler error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                echo '<h1>500 — Server error</h1><pre>' . h($e->getMessage()) . '</pre>';
            }
            return;
        }
    }
    not_found();
}

function pattern_to_regex(string $pattern): string {
    $regex = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', function ($m) {
        return '(?P<' . $m[1] . '>[^/]+)';
    }, $pattern);
    return '#^' . $regex . '/?$#';
}
