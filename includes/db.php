<?php
/**
 * db.php — Opens a MySQLi connection and exposes $mysqli globally.
 * Always included after config.php.
 */

if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    http_response_code(403);
    exit('Access denied.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $mysqli->set_charset(DB_CHARSET);
} catch (mysqli_sql_exception $e) {
    if (APP_ENV === 'development') {
        die('Database connection failed: ' . $e->getMessage());
    }
    error_log('Database connection failed: ' . $e->getMessage());
    die('A database error occurred. Please try again later.');
}
