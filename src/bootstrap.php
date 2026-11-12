<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '' || getenv($name) !== false) {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
    }
}

load_env_file(APP_ROOT . DIRECTORY_SEPARATOR . '.env');

function config(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function is_https_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $parts = parse_url($url);
    return is_array($parts) && strtolower((string) ($parts['scheme'] ?? '')) === 'https' && !empty($parts['host']);
}

function app_base_path(): string
{
    $base = trim((string) config('APP_BASE_URL', ''));
    if ($base === '') {
        return '';
    }
    $path = parse_url($base, PHP_URL_PATH);
    return rtrim((string) ($path ?? ''), '/');
}

function base_url(string $path = ''): string
{
    $prefix = app_base_path();
    return $prefix . '/' . ltrim($path, '/');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('brandx_demo');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

function is_admin(): bool
{
    start_app_session();
    return ($_SESSION['is_admin'] ?? false) === true;
}

function require_admin(): void
{
    if (!is_admin()) {
        json_response(['ok' => false, 'error' => 'Administrator sign-in required.'], 401);
    }
}

function csrf_token(): string
{
    start_app_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function require_csrf(): void
{
    start_app_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($token) || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
        json_response(['ok' => false, 'error' => 'Page security token expired. Refresh and try again.'], 403);
    }
}

require_once APP_ROOT . '/src/JsonStorage.php';
require_once APP_ROOT . '/src/Services.php';

