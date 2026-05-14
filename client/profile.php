<?php
/**
 * client/profile.php — Profile & Change Password (all roles).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$errors = [];
$success = '';

// ─── Handle password change ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password)) $errors[] = 'Current password is required.';
    if (strlen($new_password) < 6) $errors[] = 'New password must be at least 6 characters.';
    if ($new_password !== $confirm_password) $errors[] = 'New passwords do not match.';

    if (empty($errors)) {
        // Verify current password
        $user_id = (int)current_user()['id'];
        $stmt = $mysqli->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current_password, $row['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        } else {
            // Update password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $new_hash, $user_id);
            $stmt->execute();
            $stmt->close();

            flash('Password changed successfully.', 'success');
            redirect(BASE_URL . 'client/profile.php');
        }
    }
}

// Load fresh user data from DB
$user_id = (int)current_user()['id'];
$stmt = $mysqli->prepare("SELECT name, email, phone, role, created_at FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'Profile';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-user-circle" style="color:var(--brand-primary-light);"></i> My Profile</h1>
        <p class="page-subtitle">Account information & security</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
    <span class="alert-content"><?= implode('<br>', array_map('e', $errors)) ?></span>
</div>
<?php endif; ?>

<div class="grid-2" style="align-items:flex-start;">
    <!-- Profile Info -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-identification-card"></i> Account Info</h3></div>
        <div class="form-group">
            <label class="form-label">Name</label>
            <p class="text-bold"><?= e($profile['name']) ?></p>
        </div>
        <div class="form-group">
            <label class="form-label">Email</label>
            <p><?= e($profile['email']) ?></p>
        </div>
        <div class="form-group">
            <label class="form-label">Phone</label>
            <p><?= e($profile['phone'] ?? '—') ?></p>
        </div>
        <div class="form-group">
            <label class="form-label">Role</label>
            <span class="badge badge-primary"><?= e(ucfirst($profile['role'])) ?></span>
        </div>
        <div class="form-group">
            <label class="form-label">Member Since</label>
            <p class="text-muted"><?= date('F j, Y', strtotime($profile['created_at'])) ?></p>
        </div>
    </div>

    <!-- Change Password -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-lock-key"></i> Change Password</h3></div>
        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Current Password <span class="required">*</span></label>
                <input type="password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">New Password <span class="required">*</span></label>
                <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password <span class="required">*</span></label>
                <input type="password" name="confirm_password" class="form-control" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="ph ph-lock-key"></i> Update Password
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
