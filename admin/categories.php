<?php
/**
 * admin/categories.php — Category management (CRUD).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// ─── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $slug  = trim($_POST['slug'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $sort  = (int)($_POST['sort_order'] ?? 0);

        if ($slug && $label) {
            $stmt = $mysqli->prepare("INSERT INTO categories (slug, label, sort_order) VALUES (?, ?, ?)");
            $stmt->bind_param('ssi', $slug, $label, $sort);
            $stmt->execute();
            $stmt->close();
            flash('Category created.', 'success');
        }
    } elseif ($action === 'update') {
        $cat_id = (int)($_POST['id'] ?? 0);
        $label  = trim($_POST['label'] ?? '');
        $sort   = (int)($_POST['sort_order'] ?? 0);

        if ($cat_id && $label) {
            $stmt = $mysqli->prepare("UPDATE categories SET label = ?, sort_order = ? WHERE id = ?");
            $stmt->bind_param('sii', $label, $sort, $cat_id);
            $stmt->execute();
            $stmt->close();
            flash('Category updated.', 'success');
        }
    } elseif ($action === 'delete') {
        $cat_id = (int)($_POST['id'] ?? 0);
        // Check if products exist in this category
        $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM products WHERE category_id = ?");
        $stmt->bind_param('i', $cat_id);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();

        if ($count > 0) {
            flash("Cannot delete: {$count} products use this category.", 'danger');
        } else {
            $stmt = $mysqli->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->bind_param('i', $cat_id);
            $stmt->execute();
            $stmt->close();
            flash('Category deleted.', 'success');
        }
    }

    redirect(BASE_URL . 'admin/categories.php');
}

// ─── Fetch categories ─────────────────────────────────────────
$categories = $mysqli->query("SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count FROM categories c ORDER BY c.sort_order, c.label")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Categories';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-folders" style="color:var(--brand-primary-light);"></i> Categories</h1>
        <p class="page-subtitle"><?= count($categories) ?> categories</p>
    </div>
</div>

<div class="grid-2" style="align-items:flex-start;">
    <!-- Category List -->
    <div class="card">
        <div class="card-header"><h3>All Categories</h3></div>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Label</th><th>Slug</th><th class="text-center">Products</th><th class="text-center">Order</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($categories as $cat): ?>
                <tr>
                    <td class="text-bold"><?= e($cat['label']) ?></td>
                    <td class="text-mono"><?= e($cat['slug']) ?></td>
                    <td class="text-center"><span class="badge badge-info"><?= (int)$cat['product_count'] ?></span></td>
                    <td class="text-center"><?= (int)$cat['sort_order'] ?></td>
                    <td class="text-right">
                        <form method="POST" style="display:inline;" data-confirm="Delete this category?" data-confirm-title="Delete Category" data-confirm-type="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm text-danger" title="Delete"><i class="ph ph-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Category Form -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-plus"></i> Add Category</h3></div>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label class="form-label">Slug <span class="required">*</span></label>
                <input type="text" name="slug" class="form-control" placeholder="e.g. cpu, gpu, ram" required pattern="[a-z0-9_-]+">
                <p class="form-text">Lowercase, no spaces. Used internally.</p>
            </div>
            <div class="form-group">
                <label class="form-label">Display Label <span class="required">*</span></label>
                <input type="text" name="label" class="form-control" placeholder="e.g. Processor, Graphics Card" required>
            </div>
            <div class="form-group">
                <label class="form-label">Sort Order</label>
                <input type="number" name="sort_order" class="form-control" value="0" min="0">
            </div>
            <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-plus"></i> Create Category</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
