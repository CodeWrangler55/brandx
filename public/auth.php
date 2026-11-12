<?php
require_once dirname(__DIR__) . '/src/bootstrap.php';
start_app_session();
$action = (string) ($_POST['action'] ?? '');
$token = (string) ($_POST['csrf_token'] ?? '');
if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token)) {
    http_response_code(403);
    exit('Page security token expired. Refresh and try again.');
}
if ($action === 'logout') {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: ' . base_url(''));
    exit;
}
if ($action !== 'login') {
    http_response_code(405);
    exit('Method not allowed.');
}
$expected = trim((string) config('APP_ADMIN_PASSWORD', ''));
if ($expected === '' && strtolower((string) config('APP_ENV', 'local')) === 'local') {
    $expected = 'demo';
}
if ($expected === '') {
    $_SESSION['login_error'] = 'Admin password is not configured.';
    header('Location: ' . base_url(''));
    exit;
}
// A small persistent delay protects public demos from rapid password guessing.
$limits = JsonStorage::read('rate-limits.json', []);
$now = time();
$loginWindow = (int) ($limits['login_window'] ?? 0);
$loginAttempts = (int) ($limits['login_attempts'] ?? 0);
if ($loginWindow > 0 && $now - $loginWindow < 900 && $loginAttempts >= 10) {
    $_SESSION['login_error'] = 'Too many attempts. Wait 15 minutes, then try again.';
    header('Location: ' . base_url(''));
    exit;
}
if (!hash_equals($expected, (string) ($_POST['password'] ?? ''))) {
    JsonStorage::update('rate-limits.json', function (array $state) use ($now): array {
        if (($state['login_window'] ?? 0) < $now - 900) {
            $state['login_window'] = $now;
            $state['login_attempts'] = 0;
        }
        $state['login_attempts'] = (int) ($state['login_attempts'] ?? 0) + 1;
        return $state;
    }, []);
    $_SESSION['login_error'] = 'That password did not match. Try again.';
    header('Location: ' . base_url(''));
    exit;
}
JsonStorage::update('rate-limits.json', function (array $state): array {
    unset($state['login_attempts'], $state['login_window']);
    return $state;
}, []);
session_regenerate_id(true);
$_SESSION['is_admin'] = true;
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
header('Location: ' . base_url(''));
exit;
