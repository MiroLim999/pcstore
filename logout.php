<?php
/**
 * logout.php — Destroys session and redirects to login.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
flash('You have been logged out.', 'info');
redirect(BASE_URL . 'login.php');
