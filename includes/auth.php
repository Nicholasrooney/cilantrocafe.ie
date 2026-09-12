<?php
/*
 * Staff authentication.
 *
 * One shared café password, by decision — see docs/superpowers/specs.
 * The password is only ever stored as a bcrypt hash, and login attempts are
 * throttled so a shared password cannot be brute forced at leisure.
 *
 * Every action still records who did it via staff_actor(), which returns
 * 'staff' today. When named logins arrive, that function is the only thing
 * that has to change.
 */

require_once __DIR__ . '/config.php';

function staff_session_start(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    session_set_cookie_params([
        'httponly' => true,
        'secure'   => !empty($_SERVER['HTTPS']),
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_name('cilantro_staff');
    session_start();
}

function staff_is_logged_in(): bool
{
    global $staff;
    staff_session_start();

    if (empty($_SESSION['staff_since'])) {
        return false;
    }

    $hours = (int) ($staff['session_hours'] ?? 12);
    if (time() - (int) $_SESSION['staff_since'] > $hours * 3600) {
        staff_logout();
        return false;
    }

    return true;
}

/**
 * Who to record against a change. A single label for now; the column and every
 * caller are already shaped for real names.
 */
function staff_actor(): string
{
    return $_SESSION['staff_name'] ?? 'staff';
}

/**
 * Checks the password, with throttling.
 *
 * @return string  '' on success, otherwise the message to show
 */
function staff_login(string $password): string
{
    global $staff;
    staff_session_start();

    if (empty($staff['password_hash'])) {
        return 'No staff password has been set yet. Open /staff/hash.php to make one.';
    }

    $wait = staff_lockout_remaining();
    if ($wait > 0) {
        return 'Too many attempts. Try again in ' . ceil($wait / 60) . ' minute' . (ceil($wait / 60) === 1.0 ? '' : 's') . '.';
    }

    if (!password_verify($password, $staff['password_hash'])) {
        $_SESSION['staff_attempts'] = (int) ($_SESSION['staff_attempts'] ?? 0) + 1;
        $_SESSION['staff_last_try'] = time();
        return 'That password is not right.';
    }

    // New session id on login, so a stolen pre-login cookie is worthless.
    session_regenerate_id(true);
    $_SESSION['staff_since']    = time();
    $_SESSION['staff_attempts'] = 0;
    unset($_SESSION['staff_last_try']);

    return '';
}

/**
 * Seconds left on the lockout, or 0 if not locked out.
 */
function staff_lockout_remaining(): int
{
    global $staff;

    $attempts = (int) ($_SESSION['staff_attempts'] ?? 0);
    $max      = (int) ($staff['max_attempts'] ?? 5);

    if ($attempts < $max) {
        return 0;
    }

    $elapsed = time() - (int) ($_SESSION['staff_last_try'] ?? 0);
    $lockout = (int) ($staff['lockout_minutes'] ?? 15) * 60;

    if ($elapsed >= $lockout) {
        $_SESSION['staff_attempts'] = 0;
        return 0;
    }

    return $lockout - $elapsed;
}

function staff_logout(): void
{
    staff_session_start();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/**
 * Drop this at the top of any staff page. Sends people to the login screen and
 * remembers where they were trying to go.
 */
function staff_require_login(): void
{
    if (staff_is_logged_in()) {
        return;
    }

    staff_session_start();
    $_SESSION['staff_wanted'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
    header('Location: login.php', true, 302);
    exit;
}

/**
 * CSRF token for the staff forms. Everything that changes data posts this.
 */
function staff_token(): string
{
    staff_session_start();
    if (empty($_SESSION['staff_csrf'])) {
        $_SESSION['staff_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['staff_csrf'];
}

function staff_check_token(?string $token): bool
{
    staff_session_start();
    return !empty($_SESSION['staff_csrf']) && hash_equals($_SESSION['staff_csrf'], (string) $token);
}
