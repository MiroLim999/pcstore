<?php
/**
 * login.php — User login page.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Redirect.
if (is_logged_in()) {
    redirect(BASE_URL);
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (is_login_locked($email, $mysqli)) {
        $error = 'Too many failed attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    } else {
        $stmt = $mysqli->prepare("SELECT id, email, password_hash, name, role, is_active FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                $error = 'Your account has been disabled. Contact an administrator.';
                record_login_attempt($email, false, $mysqli);
            } else {
                record_login_attempt($email, true, $mysqli);
                login_user($user);
                redirect(BASE_URL);
            }
        } else {
            record_login_attempt($email, false, $mysqli);
            $error = 'Invalid email or password.';
        }
    }
}

$page_title = 'Login';
$hide_sidebar = true;
$body_class = 'login-page';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — <?= e(APP_NAME) ?></title>
    <script>
        const storedTheme = localStorage.getItem('theme');
        if (storedTheme === 'light' || (!storedTheme && window.matchMedia('(prefers-color-scheme: light)').matches)) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/admin.css">
</head>
<body class="login-page">

<button id="themeToggle" class="btn btn-sm btn-ghost" title="Toggle Theme" style="position:fixed;top:20px;right:20px;font-size:1.3rem;padding:8px;border:1px solid var(--border-color-light);border-radius:50%;width:40px;height:40px;display:flex;align-items:center;justify-content:center;z-index:100;">
    <i class="ph ph-sun"></i>
</button>

<div class="login-container">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon"><i class="ph ph-desktop-tower"></i></div>
            <h1><?= e(APP_NAME) ?></h1>
            <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger">
            <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
            <span class="alert-content"><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <div class="input-group">
                    <i class="ph ph-envelope input-icon"></i>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="you@example.com" required autofocus
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <i class="ph ph-lock input-icon"></i>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Enter your password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:24px;">
                <i class="ph ph-sign-in"></i> Sign In
            </button>
        </form>

        <p style="text-align:center; margin-top:20px; font-size:0.85rem; color:var(--text-muted);">
            Don't have an account ma niga? <a href="<?= BASE_URL ?>register.php">Register here</a>
        </p>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
