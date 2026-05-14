<?php
/**
 * index.php — Landing page. Redirects based on role.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

$role = current_role();

switch ($role) {
    case 'superadmin':
    case 'admin':
        header('Location: ' . BASE_URL . 'admin/dashboard.php');
        break;
    case 'cashier':
        header('Location: ' . BASE_URL . 'cashier/dashboard.php');
        break;
    default:
        header('Location: ' . BASE_URL . 'client/home.php');
        break;
}
exit;
