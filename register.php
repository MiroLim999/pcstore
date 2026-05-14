<?php
/**
 * register.php — Client registration page.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect(BASE_URL);
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $old = compact('name', 'email', 'phone');

    // Validation
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    // Check duplicate email
    if (empty($errors)) {
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors[] = 'An account with this email already exists.';
        }
        $stmt->close();
    }

    // Create user
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $role = 'client';
        $stmt = $mysqli->prepare("INSERT INTO users (email, password_hash, name, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('sssss', $email, $hash, $name, $phone, $role);
        $stmt->execute();
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Auto-login
        login_user([
            'id'    => $user_id,
            'email' => $email,
            'name'  => $name,
            'role'  => $role,
        ]);

        flash('Account created successfully! Welcome to PC Store.', 'success');
        redirect(BASE_URL . 'client/home.php');
    }
}

$page_title = 'Register';
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

<div class="login-container" style="max-width:480px;">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-icon"><i class="ph ph-desktop-tower"></i></div>
            <h1>Create Account</h1>
            <p>Join <?= e(APP_NAME) ?> to build your dream PC</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
            <span class="alert-content">
                <?php foreach ($errors as $err): ?>
                    <?= e($err) ?><br>
                <?php endforeach; ?>
            </span>
        </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="on">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label" for="name">Full Name <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       placeholder="Juan Dela Cruz" required value="<?= e($old['name']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email Address <span class="required">*</span></label>
                <input type="email" id="email" name="email" class="form-control"
                       placeholder="you@example.com" required value="<?= e($old['email']) ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone (optional)</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       placeholder="09171234567" value="<?= e($old['phone']) ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" class="form-control"
                           placeholder="Min 6 characters" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password_confirm">Confirm <span class="required">*</span></label>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                           placeholder="Repeat password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:24px;">
                <i class="ph ph-user-plus"></i> Create Account
            </button>
        </form>

        <p style="text-align:center; margin-top:20px; font-size:0.85rem; color:var(--text-muted);">
            Already have an account? <a href="<?= BASE_URL ?>login.php">Sign in</a>
        </p>
    </div>
</div>

<script src="<?= BASE_URL ?>assets/js/app.js"></script>
</body>
</html>
