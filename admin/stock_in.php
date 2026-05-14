<?php
/**
 * admin/stock_in.php — Restock products (add inventory).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $qty        = (int)($_POST['qty'] ?? 0);
    $unit_cost  = (float)($_POST['unit_cost'] ?? 0);
    $reason     = trim($_POST['reason'] ?? 'Restock');

    if ($product_id < 1) $errors[] = 'Select a product.';
    if ($qty < 1) $errors[] = 'Quantity must be at least 1.';

    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            // Update stock
            $stmt = $mysqli->prepare("UPDATE products SET stock_qty = stock_qty + ?, cost = ? WHERE id = ?");
            $stmt->bind_param('idi', $qty, $unit_cost, $product_id);
            $stmt->execute();
            $stmt->close();

            // Log transaction
            $user_id = (int)current_user()['id'];
            $type = 'restock';
            $ref_type = 'manual';
            $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, created_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isisis', $product_id, $type, $qty, $ref_type, $user_id, $reason);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
            flash("Stock added: +{$qty} units.", 'success');
            redirect(BASE_URL . 'admin/stock_in.php');
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = 'Failed to update stock. Please try again.';
        }
    }
}

// Products for dropdown
$products = $mysqli->query("SELECT id, name, sku, stock_qty FROM products WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Recent stock-in transactions
$stmt = $mysqli->prepare("SELECT it.*, p.name as product_name, p.sku, u.name as user_name 
    FROM inventory_transactions it 
    LEFT JOIN products p ON it.product_id = p.id 
    LEFT JOIN users u ON it.created_by = u.id 
    WHERE it.type = 'restock' 
    ORDER BY it.created_at DESC LIMIT 10");
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Stock In';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-arrow-fat-line-down" style="color:var(--color-success);"></i> Stock In</h1>
        <p class="page-subtitle">Add inventory to existing products.</p>
    </div>
</div>

<div class="grid-2" style="align-items:flex-start;">
    <!-- Stock In Form -->
    <div class="card">
        <div class="card-header"><h3>Add Stock</h3></div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
            <span class="alert-content"><?= implode('<br>', array_map('e', $errors)) ?></span>
        </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>

            <div class="form-group">
                <label class="form-label">Product <span class="required">*</span></label>
                <select name="product_id" class="form-control" required>
                    <option value="">Select product...</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= e($p['sku']) ?>) — Stock: <?= (int)$p['stock_qty'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Quantity <span class="required">*</span></label>
                    <input type="number" name="qty" class="form-control" min="1" required placeholder="e.g. 10">
                </div>
                <div class="form-group">
                    <label class="form-label">Unit Cost (<?= e(DEFAULT_CURRENCY) ?>)</label>
                    <input type="number" name="unit_cost" class="form-control" step="0.01" min="0" placeholder="Cost per unit">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Reason / Supplier</label>
                <input type="text" name="reason" class="form-control" placeholder="e.g. Restock from Supplier X" value="Restock">
            </div>

            <button type="submit" class="btn btn-success btn-block">
                <i class="ph ph-arrow-fat-line-down"></i> Add Stock
            </button>
        </form>
    </div>

    <!-- Recent Stock-In -->
    <div class="card">
        <div class="card-header"><h3>Recent Stock-In</h3></div>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Product</th><th class="text-center">Qty</th><th>By</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td>
                        <div class="text-bold"><?= e($r['product_name']) ?></div>
                        <div class="text-muted text-mono" style="font-size:0.7rem;"><?= e($r['sku']) ?></div>
                    </td>
                    <td class="text-center"><span class="badge badge-success">+<?= (int)$r['qty'] ?></span></td>
                    <td class="text-muted"><?= e($r['user_name'] ?? 'System') ?></td>
                    <td class="text-muted"><?= date('M j, g:i A', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="4" class="text-center text-muted" style="padding:30px;">No recent stock-in records.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
