<?php
declare(strict_types=1);

function auth_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function auth_email(): string
{
    return (string)(getenv('APP_LOGIN_EMAIL') ?: '');
}

function auth_password(): string
{
    return (string)(getenv('APP_LOGIN_PASSWORD') ?: '');
}

function auth_logged_in(): bool
{
    auth_session_start();
    return (bool)($_SESSION['auth_ok'] ?? false);
}

function auth_require_login(): void
{
    if (!auth_logged_in()) {
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php?next=' . rawurlencode($next));
        exit;
    }
}

function auth_csrf_token(): string
{
    auth_session_start();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return (string)$_SESSION['csrf'];
}

function auth_csrf_verify(string $token): bool
{
    auth_session_start();
    $expected = (string)($_SESSION['csrf'] ?? '');
    return $expected !== '' && hash_equals($expected, $token);
}

function auth_try_login(string $email, string $password): bool
{
    $cfgEmail = auth_email();
    $cfgPass = auth_password();
    if ($cfgEmail === '' || $cfgPass === '') {
        return false;
    }
    return hash_equals($cfgEmail, $email) && hash_equals($cfgPass, $password);
}

function auth_logout(): void
{
    auth_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
    }
    session_destroy();
}
