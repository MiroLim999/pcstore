<?php
/**
 * helpers.php — Utility functions used across the app.
 * e(), money(), csrf_field(), csrf_check(), flash(), redirect(), etc.
 */

if (basename($_SERVER['PHP_SELF']) === 'helpers.php') {
    http_response_code(403);
    exit('Access denied.');
}

/**
 * Escape output for HTML. Use this on EVERY user-supplied value you echo.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format a number as currency.
 */
function money(float $amount): string
{
    $symbol = get_setting('currency_symbol') ?? DEFAULT_CURRENCY;
    return $symbol . number_format($amount, 2);
}

/**
 * Generate a CSRF token and store it in the session.
 */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Output a hidden CSRF input field for forms.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . csrf_token() . '">';
}

/**
 * Validate the CSRF token from a POST request.
 * Terminates with 403 if invalid.
 */
function csrf_check(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    $expected = $_SESSION[CSRF_TOKEN_NAME] ?? '';

    if (empty($expected) || !hash_equals($expected, $token)) {
        // Instead of dying, regenerate token and redirect back with error
        unset($_SESSION[CSRF_TOKEN_NAME]);
        flash('Your session expired. Please try again.', 'warning');
        header('Location: ' . $_SERVER['REQUEST_URI']);
        exit;
    }
    // Regenerate token after successful check to prevent reuse
    unset($_SESSION[CSRF_TOKEN_NAME]);
}

/**
 * Set a flash message to display on the next page load.
 */
function flash(string $message, string $type = 'info'): void
{
    $_SESSION['flash_messages'][] = ['message' => $message, 'type' => $type];
}

/**
 * Get and clear all flash messages.
 */
function get_flash_messages(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Render flash messages as HTML alerts.
 */
function render_flash(): string
{
    $messages = get_flash_messages();
    if (empty($messages)) return '';

    $html = '';
    foreach ($messages as $msg) {
        $type = e($msg['type']);
        $text = e($msg['message']);
        $html .= "<div class=\"alert alert-{$type}\">
            <span class=\"alert-icon\"><i class=\"ph ph-info\"></i></span>
            <span class=\"alert-content\">{$text}</span>
            <button class=\"alert-close\" onclick=\"this.parentElement.remove()\">&times;</button>
        </div>";
    }
    return $html;
}

/**
 * Redirect to a URL and exit.
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Get a setting value from the settings table (cached per request).
 */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];
    global $mysqli;

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $mysqli->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $cache[$key] = $row ? $row['setting_value'] : $default;
    return $cache[$key];
}

/**
 * Generate a unique 6-digit pickup code.
 */
function generate_pickup_code(mysqli $mysqli): string
{
    $max_attempts = 10;
    for ($i = 0; $i < $max_attempts; $i++) {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $stmt = $mysqli->prepare("SELECT id FROM builds WHERE pickup_code = ? AND status IN ('submitted','paid')");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            $stmt->close();
            return $code;
        }
        $stmt->close();
    }
    // Fallback — extremely unlikely
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Generate a receipt number: SALE-YYYYMMDD-NNNN
 */
function generate_receipt_number(mysqli $mysqli): string
{
    $date = date('Ymd');
    $prefix = "SALE-{$date}-";

    $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM orders WHERE receipt_no LIKE CONCAT(?, '%')");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next = ($row['cnt'] ?? 0) + 1;
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/**
 * Sanitize a file name for uploads.
 */
function sanitize_filename(string $filename): string
{
    $info = pathinfo($filename);
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $info['filename']);
    $ext = strtolower($info['extension'] ?? 'jpg');
    return $name . '_' . time() . '.' . $ext;
}

/**
 * Check if the current request is AJAX (XHR).
 */
function is_ajax(): bool
{
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
