<?php
declare(strict_types=1);

const SUPPORTED_LOCALES = ['en', 'fr', 'de'];
const DEFAULT_LOCALE = 'en';
const LOCALE_COOKIE = 'LOCALE';

function current_locale(): string {
    $v = $_COOKIE[LOCALE_COOKIE] ?? '';
    return in_array($v, SUPPORTED_LOCALES, true) ? $v : DEFAULT_LOCALE;
}

function set_locale_cookie(string $locale): void {
    if (!in_array($locale, SUPPORTED_LOCALES, true)) return;
    setcookie(LOCALE_COOKIE, $locale, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'samesite' => 'Lax',
    ]);
    $_COOKIE[LOCALE_COOKIE] = $locale;
}

function messages(?string $locale = null): array {
    static $cache = [];
    $locale = $locale ?? current_locale();
    if (isset($cache[$locale])) return $cache[$locale];
    $path = LANG_DIR . '/' . $locale . '.json';
    if (!is_file($path)) $path = LANG_DIR . '/' . DEFAULT_LOCALE . '.json';
    $cache[$locale] = json_decode((string) file_get_contents($path), true) ?? [];
    return $cache[$locale];
}

/**
 * Translate by dotted path. Supports {placeholder} interpolation and a
 * simple {count, plural, one {...} other {...}} form.
 *
 * t('Dashboard.welcome', ['name' => 'Alice'])
 * t('Dashboard.docsCount', ['count' => 3])
 */
function t(string $key, array $vars = [], ?string $locale = null): string {
    $msgs = messages($locale);
    $parts = explode('.', $key);
    $cur = $msgs;
    foreach ($parts as $p) {
        if (is_array($cur) && array_key_exists($p, $cur)) {
            $cur = $cur[$p];
        } else {
            return $key;
        }
    }
    if (!is_string($cur)) return $key;
    return interpolate($cur, $vars);
}

function interpolate(string $template, array $vars): string {
    // Plural form: {count, plural, one {...} other {...}}
    $template = preg_replace_callback(
        '/\{(\w+),\s*plural,\s*one\s*\{([^{}]*)\}\s*other\s*\{([^{}]*)\}\s*\}/u',
        function ($m) use ($vars) {
            $count = (int) ($vars[$m[1]] ?? 0);
            $branch = $count === 1 ? $m[2] : $m[3];
            // Replace # with the count
            return str_replace('#', (string) $count, $branch);
        },
        $template
    ) ?? $template;
    // Simple {key} replacement
    return preg_replace_callback('/\{(\w+)\}/u', function ($m) use ($vars) {
        return isset($vars[$m[1]]) ? (string) $vars[$m[1]] : $m[0];
    }, $template) ?? $template;
}
