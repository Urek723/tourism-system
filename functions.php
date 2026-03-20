<?php

// ── Base URL — set this to match your project folder ──────────────────────
// If running at http://localhost/tourism_system/ → use '/tourism_system/'
// If running at http://localhost/               → use '/'
define('BASE_URL', '/tourism_system/');

/**
 * Safely escape a string for HTML output.
 */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape a value for use inside an HTML attribute.
 */
function eAttr(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Input Sanitisation ─────────────────────────────────────────────────────

function clean_string(string $input, int $maxLen = 255): string
{
    return substr(strip_tags(trim($input)), 0, $maxLen);
}

function clean_email(string $input): string
{
    $email = filter_var(trim($input), FILTER_VALIDATE_EMAIL);
    return $email !== false ? strtolower($email) : '';
}

function clean_int(mixed $input): int
{
    $val = filter_var($input, FILTER_VALIDATE_INT);
    return ($val !== false && $val > 0) ? (int)$val : 0;
}

function validate_username(string $input): bool
{
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,50}$/', $input);
}

function validate_password(string $password): array
{
    $errors = [];
    if (strlen($password) < 8)
        $errors[] = 'Password must be at least 8 characters long.';
    if (!preg_match('/[A-Z]/', $password))
        $errors[] = 'Password must contain at least one uppercase letter.';
    if (!preg_match('/[a-z]/', $password))
        $errors[] = 'Password must contain at least one lowercase letter.';
    if (!preg_match('/[0-9]/', $password))
        $errors[] = 'Password must contain at least one number.';
    return $errors;
}

// ── CSRF Protection ────────────────────────────────────────────────────────

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . eAttr(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';
    if (empty($submitted) || empty($stored)) return false;
    return hash_equals($stored, $submitted);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        die('<p style="color:red;font-family:sans-serif;">
             Invalid request (CSRF token mismatch).
             <a href="javascript:history.back()">Go back</a>.
             </p>');
    }
}

// ── Session Security ───────────────────────────────────────────────────────

function start_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    ini_set('session.use_strict_mode',  '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime',   '1800');

    session_name('TUPI_SESS');
    session_start();

    // Idle timeout — 30 minutes
    if (isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity']) > 1800) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();

    // Periodic session ID regeneration
    if (!isset($_SESSION['__created'])) {
        $_SESSION['__created'] = time();
    } elseif (time() - $_SESSION['__created'] > 300) {
        session_regenerate_id(true);
        $_SESSION['__created'] = time();
    }
}

function destroy_session(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();
}

// ── Authentication Guards ──────────────────────────────────────────────────

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'admin';
}

/**
 * Require the user to be logged in.
 * Always redirects to the absolute login URL so it works from any subfolder.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? BASE_URL;
        // Use absolute path so this works from /admin/ subfolders too
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Require the user to be an admin.
 * Always redirects to the absolute dashboard URL so it works from any subfolder.
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        set_flash('error', 'You do not have permission to access that page.');
        // Use absolute path so this works from /admin/ subfolders too
        header('Location: ' . BASE_URL . 'dashboard.php');
        exit;
    }
}

// ── Flash Messages ─────────────────────────────────────────────────────────

function set_flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function get_flash(string $type): string
{
    $msg = $_SESSION['flash_' . $type] ?? '';
    unset($_SESSION['flash_' . $type]);
    return $msg;
}

function flash_alert(string $type, string $bootstrapClass = ''): string
{
    $msg = get_flash($type);
    if (empty($msg)) return '';
    $class = $bootstrapClass ?: match($type) {
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'info'    => 'alert-info',
        default   => 'alert-warning',
    };
    return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">'
         . e($msg)
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
         . '</div>';
}

// ── Brute-Force Protection ─────────────────────────────────────────────────

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_SECONDS',    900);

function get_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function is_locked_out(): bool
{
    try {
        $db   = get_db();
        $ip   = get_client_ip();
        $stmt = $db->prepare(
            'SELECT attempts, last_attempt FROM login_attempts WHERE ip_address = ?'
        );
        $stmt->execute([$ip]);
        $row = $stmt->fetch();
        if (!$row) return false;
        $elapsed = time() - strtotime($row['last_attempt']);
        if ($elapsed > LOCKOUT_SECONDS) {
            $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
            return false;
        }
        return (int)$row['attempts'] >= MAX_LOGIN_ATTEMPTS;
    } catch (PDOException $e) {
        error_log('[RATE LIMIT CHECK ERROR] ' . $e->getMessage());
        return false;
    }
}

function record_failed_login(): void
{
    try {
        $db   = get_db();
        $ip   = get_client_ip();
        $stmt = $db->prepare(
            'INSERT INTO login_attempts (ip_address, attempts, last_attempt)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = NOW()'
        );
        $stmt->execute([$ip]);
    } catch (PDOException $e) {
        error_log('[RECORD LOGIN ATTEMPT ERROR] ' . $e->getMessage());
    }
}

function clear_login_attempts(): void
{
    try {
        $db   = get_db();
        $ip   = get_client_ip();
        $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
    } catch (PDOException $e) {
        error_log('[CLEAR LOGIN ATTEMPTS ERROR] ' . $e->getMessage());
    }
}

// ── Redirect Helper ────────────────────────────────────────────────────────

/**
 * Redirect to a path relative to BASE_URL.
 *
 * Pass paths WITHOUT a leading slash:
 *   redirect('login.php')                 → /tourism_system/login.php
 *   redirect('admin/admin_panel.php')     → /tourism_system/admin/admin_panel.php
 *   redirect('admin/manage_locations.php')→ /tourism_system/admin/manage_locations.php
 *
 * Paths that already start with / are used as-is (scheme+host stripped
 * to prevent open redirect).
 */
function redirect(string $path): void
{
    // Strip scheme+host if someone passes a full URL (prevents open redirect)
    $path = preg_replace('#^https?://[^/]*#', '', $path);

    // If already absolute (starts with /), use as-is
    // Otherwise prepend BASE_URL so it resolves correctly from any subfolder
    if (!str_starts_with($path, '/')) {
        $path = BASE_URL . $path;
    }

    header('Location: ' . $path);
    exit;
}