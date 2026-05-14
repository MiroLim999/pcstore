<?php
/**
 * config.php — Application constants and database credentials.
 * Include this file at the top of every page.
 */

// Prevent direct access
if (basename($_SERVER['PHP_SELF']) === 'config.php') {
    http_response_code(403);
    exit('Access denied.');
}

// ─── Environment ──────────────────────────────────────────────
define('APP_NAME', 'PC Store');
define('APP_VERSION', '1.0.0');
define('APP_ENV', 'development'); // 'development' or 'production'

// ─── Database ─────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'pcstore');
define('DB_USER', 'root');
define('DB_PASS', 'miro');
define('DB_CHARSET', 'utf8mb4');

// ─── Paths ────────────────────────────────────────────────────
define('BASE_PATH', dirname(__DIR__) . '/');
define('INCLUDES_PATH', BASE_PATH . 'includes/');
define('UPLOADS_PATH', BASE_PATH . 'uploads/');

// Base URL (adjust if not in webroot)
define('BASE_URL', '/pcstore/');

// ─── Business Settings (defaults, overridden by DB settings table) ─
define('DEFAULT_CURRENCY', '₱');
define('DEFAULT_TAX_RATE', 0.12); // 12% VAT
define('DEFAULT_RESERVATION_HOURS', 48);

// ─── Security ─────────────────────────────────────────────────
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 999);
define('LOGIN_LOCKOUT_MINUTES', 15);
define('SESSION_LIFETIME', 3600 * 8); // 8 hours

// ─── File Uploads ─────────────────────────────────────────────
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5 MB

// ─── Error Handling ───────────────────────────────────────────
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', BASE_PATH . 'logs/error.log');
}

// ─── Session ──────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
