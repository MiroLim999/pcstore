<?php
/**
 * admin/stock_adjust.php — Stock adjustment for damage, loss, or corrections.
 * Adjusts stock_qty up or down with a reason logged.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $product_id = (int)($_POST['product_id'] ?? 0);
    $adjustment  = (int)($_POST['adjustment'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if ($product_id < 1) $errors[] = 'Select a product.';
    if ($adjustment === 0) $errors[] = 'Adjustment cannot be zero.';
    if (empty($reason)) $errors[] = 'Reason is required for audit trail.';

    // Check stock won't go negative
    if (empty($errors) && $adjustment < 0) {
        $stmt = $mysqli->prepare("SELECT stock_qty FROM products WHERE id = ?");
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row && ($row['stock_qty'] + $adjustment) < 0) {
            $errors[] = "Cannot reduce below zero. Current stock: {$row['stock_qty']}.";
        }
    }

    if (empty($errors)) {
        $mysqli->begin_transaction();
        try {
            // Update stock
            $stmt = $mysqli->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
            $stmt->bind_param('ii', $adjustment, $product_id);
            $stmt->execute();
            $stmt->close();

            // Log transaction
            $user_id = (int)current_user()['id'];
            $type = 'adjust';
            $ref_type = 'manual';
            $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, created_by, reason) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('isisis', $product_id, $type, $adjustment, $ref_type, $user_id, $reason);
            $stmt->execute();
            $stmt->close();

            // Audit log
            $stmt = $mysqli->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_value) VALUES (?, 'stock_adjust', 'product', ?, ?)");
            $audit_val = json_encode(['adjustment' => $adjustment, 'reason' => $reason]);
            $stmt->bind_param('iis', $user_id, $product_id, $audit_val);
            $stmt->execute();
            $stmt->close();

            $mysqli->commit();
            $sign = $adjustment > 0 ? '+' : '';
            flash("Stock adjusted: {$sign}{$adjustment} units. Reason: {$reason}", 'success');
            redirect(BASE_URL . 'admin/stock_adjust.php');
        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = 'Adjustment failed.';
        }
    }
}

// Products for dropdown
$products = $mysqli->query("SELECT id, name, sku, stock_qty FROM products WHERE is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Recent adjustments
$stmt = $mysqli->prepare("SELECT it.*, p.name as product_name, p.sku, u.name as user_name
    FROM inventory_transactions it
    LEFT JOIN products p ON it.product_id = p.id
    LEFT JOIN users u ON it.created_by = u.id
    WHERE it.type = 'adjust'
    ORDER BY it.created_at DESC LIMIT 15");
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Stock Adjustment';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-wrench" style="color:var(--color-warning);"></i> Stock Adjustment</h1>
        <p class="page-subtitle">Adjust stock for damage, loss, theft, or corrections.</p>
    </div>
</div>

<div class="grid-2" style="align-items:flex-start;">
    <!-- Adjustment Form -->
    <div class="card">
        <div class="card-header"><h3>Make Adjustment</h3></div>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><span class="alert-icon"><i class="ph ph-warning-circle"></i></span><span class="alert-content"><?= implode('<br>', array_map('e', $errors)) ?></span></div>
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

            <div class="form-group">
                <label class="form-label">Adjustment (+ to add, − to remove) <span class="required">*</span></label>
                <input type="number" name="adjustment" class="form-control" required placeholder="e.g. -2 for damage, +5 for found stock"
                       style="font-size:1.1rem;font-weight:600;text-align:center;">
                <p class="form-text">Positive = add stock. Negative = remove stock.</p>
            </div>

            <div class="form-group">
                <label class="form-label">Reason <span class="required">*</span></label>
                <select name="reason" class="form-control" required>
                    <option value="">Select reason...</option>
                    <option value="Damaged goods">Damaged goods</option>
                    <option value="Lost/stolen">Lost / Stolen</option>
                    <option value="Inventory count correction">Inventory count correction</option>
                    <option value="Found stock">Found stock (add)</option>
                    <option value="Supplier return">Supplier return</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <button type="submit" class="btn btn-warning btn-block" data-confirm="Confirm this stock adjustment?" data-confirm-title="Stock Adjustment" data-confirm-type="warning">
                <i class="ph ph-wrench"></i> Apply Adjustment
            </button>
        </form>
    </div>

    <!-- Recent Adjustments -->
    <div class="card">
        <div class="card-header"><h3>Recent Adjustments</h3></div>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Product</th><th class="text-center">Adj</th><th>Reason</th><th>By</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td>
                        <div class="text-bold"><?= e($r['product_name']) ?></div>
                        <div class="text-muted text-mono" style="font-size:0.7rem;"><?= e($r['sku']) ?></div>
                    </td>
                    <td class="text-center">
                        <?php if ((int)$r['qty'] > 0): ?>
                        <span class="badge badge-success">+<?= (int)$r['qty'] ?></span>
                        <?php else: ?>
                        <span class="badge badge-danger"><?= (int)$r['qty'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted" style="max-width:120px;"><?= e($r['reason'] ?? '') ?></td>
                    <td class="text-muted"><?= e($r['user_name'] ?? 'System') ?></td>
                    <td class="text-muted"><?= date('M j, g:i A', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:30px;">No adjustments yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
