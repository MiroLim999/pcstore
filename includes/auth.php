<?php
/**
 * auth.php — Authentication & authorization helpers.
 * Provides: is_logged_in(), current_user(), require_login(), require_role()
 */

if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * Check if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
}

/**
 * Get the current logged-in user's data from session cache.
 * Returns null if not logged in.
 */
function current_user(): ?array
{
    if (!is_logged_in()) return null;
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user's role.
 */
function current_role(): string
{
    $user = current_user();
    return $user['role'] ?? 'guest';
}

/**
 * Redirect to login if not authenticated.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('Please log in to continue.', 'warning');
        header('Location: ' . BASE_URL . 'login.php');
        exit;
    }
}

/**
 * Require a minimum role level. Roles hierarchy:
 * client < cashier < admin < superadmin
 */
function require_role(string ...$allowed_roles): void
{
    require_login();
    $role = current_role();

    if (!in_array($role, $allowed_roles, true)) {
        http_response_code(403);
        die('Access denied. You do not have permission to view this page.');
    }
}

/**
 * Check if current user has at least the given role.
 */
function has_role(string $minimum_role): bool
{
    $hierarchy = ['client' => 1, 'cashier' => 2, 'admin' => 3, 'superadmin' => 4];
    $current = $hierarchy[current_role()] ?? 0;
    $required = $hierarchy[$minimum_role] ?? 99;
    return $current >= $required;
}

/**
 * Log in a user: store their data in session, regenerate session ID.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user'] = [
        'id'    => (int)$user['id'],
        'name'  => $user['name'],
        'email' => $user['email'],
        'role'  => $user['role'],
    ];
}

/**
 * Log out the current user.
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Check login rate limiting. Returns true if locked out.
 */
function is_login_locked(string $email, mysqli $mysqli): bool
{
    $stmt = $mysqli->prepare(
        "SELECT COUNT(*) as attempts FROM login_attempts 
         WHERE email = ? AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE) AND success = 0"
    );
    $minutes = LOGIN_LOCKOUT_MINUTES;
    $stmt->bind_param('si', $email, $minutes);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return ($result['attempts'] ?? 0) >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Record a login attempt.
 */
function record_login_attempt(string $email, bool $success, mysqli $mysqli): void
{
    $stmt = $mysqli->prepare(
        "INSERT INTO login_attempts (email, ip_address, success, attempted_at) VALUES (?, ?, ?, NOW())"
    );
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $s = $success ? 1 : 0;
    $stmt->bind_param('ssi', $email, $ip, $s);
    $stmt->execute();
    $stmt->close();
}
