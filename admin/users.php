<?php
/**
 * admin/users.php — User management (list, create, toggle active, change role).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

$errors = [];
$success = '';

// ─── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'client';
        $password = $_POST['password'] ?? '';

        if (empty($name)) $errors[] = 'Name is required.';
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!in_array($role, ['client', 'cashier', 'admin'])) $errors[] = 'Invalid role.';

        // Check duplicate email
        if (empty($errors)) {
            $stmt = $mysqli->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Email already exists.';
            }
            $stmt->close();
        }

        if (empty($errors)) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("INSERT INTO users (name, email, phone, role, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('sssss', $name, $email, $phone, $role, $hash);
            $stmt->execute();
            $stmt->close();
            flash("User \"{$name}\" created.", 'success');
            redirect(BASE_URL . 'admin/users.php');
        }
    } elseif ($action === 'toggle_active') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0 && $uid !== (int)current_user()['id']) {
            $stmt = $mysqli->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->bind_param('i', $uid);
            $stmt->execute();
            $stmt->close();
            flash('User status updated.', 'success');
        }
        redirect(BASE_URL . 'admin/users.php');
    } elseif ($action === 'change_role') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        if ($uid > 0 && in_array($new_role, ['client', 'cashier', 'admin']) && $uid !== (int)current_user()['id']) {
            $stmt = $mysqli->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param('si', $new_role, $uid);
            $stmt->execute();
            $stmt->close();
            flash('Role updated.', 'success');
        }
        redirect(BASE_URL . 'admin/users.php');
    }
}

// Load users
$users = $mysqli->query("SELECT id, name, email, phone, role, is_active, created_at FROM users ORDER BY FIELD(role, 'superadmin','admin','cashier','client'), name")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Users';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-users" style="color:var(--brand-primary-light);"></i> Users</h1>
        <p class="page-subtitle"><?= count($users) ?> registered users</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
    <span class="alert-content"><?= implode('<br>', array_map('e', $errors)) ?></span>
</div>
<?php endif; ?>

<div class="grid-2" style="align-items:flex-start;">
    <!-- User List -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-users"></i> All Users</h3></div>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th class="text-center">Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="text-bold"><?= e($u['name']) ?></td>
                    <td class="text-muted"><?= e($u['email']) ?></td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)current_user()['id'] && $u['role'] !== 'superadmin'): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="change_role">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <select name="new_role" class="form-control" style="width:auto;padding:2px 6px;font-size:0.8rem;" onchange="this.form.submit()">
                                <option value="client" <?= $u['role'] === 'client' ? 'selected' : '' ?>>Client</option>
                                <option value="cashier" <?= $u['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </form>
                        <?php else: ?>
                        <span class="badge badge-primary"><?= e(ucfirst($u['role'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($u['is_active']): ?>
                        <span class="badge badge-success">Active</span>
                        <?php else: ?>
                        <span class="badge badge-danger">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ((int)$u['id'] !== (int)current_user()['id'] && $u['role'] !== 'superadmin'): ?>
                        <form method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle_active">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" title="<?= $u['is_active'] ? 'Disable' : 'Enable' ?>">
                                <i class="ph ph-<?= $u['is_active'] ? 'prohibit' : 'check-circle' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Form -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-user-plus"></i> Add User</h3></div>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" placeholder="Optional">
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="role" class="form-control">
                    <option value="client">Client</option>
                    <option value="cashier">Cashier</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Password <span class="required">*</span></label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-user-plus"></i> Create User</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
