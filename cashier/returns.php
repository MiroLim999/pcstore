<?php
/**
 * cashier/returns.php — Process returns by looking up a receipt.
 * Lookup receipt → select items to return → refund + restock.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('cashier', 'admin', 'superadmin');

$receipt_query = trim($_GET['receipt'] ?? '');
$order = null;
$order_items = [];
$error = '';
$success = '';

// ─── Lookup receipt ───────────────────────────────────────────
if ($receipt_query !== '') {
    $stmt = $mysqli->prepare("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.cashier_id = u.id WHERE o.receipt_no = ?");
    $stmt->bind_param('s', $receipt_query);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $stmt = $mysqli->prepare("SELECT oi.*, p.name as product_name, p.sku FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->bind_param('i', $order['id']);
        $stmt->execute();
        $order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

// ─── Process return ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $order_id = (int)($_POST['order_id'] ?? 0);
    $return_items = $_POST['return_item'] ?? []; // array of order_item IDs
    $return_qtys = $_POST['return_qty'] ?? [];   // array of quantities
    $reason = trim($_POST['reason'] ?? 'Customer return');

    if ($order_id < 1 || empty($return_items)) {
        $error = 'Select at least one item to return.';
    } else {
        // Load the order
        $stmt = $mysqli->prepare("SELECT * FROM orders WHERE id = ? AND status = 'completed'");
        $stmt->bind_param('i', $order_id);
        $stmt->execute();
        $order_check = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order_check) {
            $error = 'Order not found or already refunded.';
        } else {
            $cashier_id = (int)current_user()['id'];
            $refund_total = 0;

            $mysqli->begin_transaction();
            try {
                foreach ($return_items as $oi_id) {
                    $oi_id = (int)$oi_id;
                    $qty_to_return = (int)($return_qtys[$oi_id] ?? 1);
                    if ($qty_to_return < 1) continue;

                    // Get order item details
                    $stmt = $mysqli->prepare("SELECT * FROM order_items WHERE id = ? AND order_id = ?");
                    $stmt->bind_param('ii', $oi_id, $order_id);
                    $stmt->execute();
                    $oi = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if (!$oi) continue;

                    // Don't return more than was sold
                    $qty_to_return = min($qty_to_return, (int)$oi['qty']);
                    // Proportional discount: if returning partial qty, only subtract proportional discount
                    $proportional_discount = ((float)$oi['discount'] / (int)$oi['qty']) * $qty_to_return;
                    $refund_amount = ($oi['price_snapshot'] * $qty_to_return) - $proportional_discount;
                    $refund_total += $refund_amount;

                    // Restock product
                    $stmt = $mysqli->prepare("UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?");
                    $stmt->bind_param('ii', $qty_to_return, $oi['product_id']);
                    $stmt->execute();
                    $stmt->close();

                    // Log return transaction
                    $type = 'return';
                    $ref_type = 'order';
                    $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, reference_id, created_by, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isisiis', $oi['product_id'], $type, $qty_to_return, $ref_type, $order_id, $cashier_id, $reason);
                    $stmt->execute();
                    $stmt->close();
                }

                // Check if all items have been returned to determine partial vs full refund
                $total_items_in_order = 0;
                $total_items_returned = 0;
                $stmt = $mysqli->prepare("SELECT SUM(qty) as total_qty FROM order_items WHERE order_id = ?");
                $stmt->bind_param('i', $order_id);
                $stmt->execute();
                $total_items_in_order = (int)$stmt->get_result()->fetch_assoc()['total_qty'];
                $stmt->close();

                // Count items being returned now
                foreach ($return_items as $ri_id) {
                    $ri_id = (int)$ri_id;
                    $total_items_returned += (int)($return_qtys[$ri_id] ?? 1);
                }

                // Mark as refunded only if ALL items are returned
                $refund_note = "Refund " . money($refund_total) . " on " . date('M j g:i A');
                if ($total_items_returned >= $total_items_in_order) {
                    $stmt = $mysqli->prepare("UPDATE orders SET status = 'refunded', notes = CONCAT(COALESCE(notes,''), ' | Return: ', ?) WHERE id = ?");
                } else {
                    // Partial return — keep order as completed but add note
                    $stmt = $mysqli->prepare("UPDATE orders SET notes = CONCAT(COALESCE(notes,''), ' | Partial Return: ', ?) WHERE id = ?");
                }
                $stmt->bind_param('si', $refund_note, $order_id);
                $stmt->execute();
                $stmt->close();

                // Log audit
                $stmt = $mysqli->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, new_value) VALUES (?, 'return', 'order', ?, ?)");
                $audit_val = json_encode(['refund_total' => $refund_total, 'reason' => $reason]);
                $stmt->bind_param('iis', $cashier_id, $order_id, $audit_val);
                $stmt->execute();
                $stmt->close();

                $mysqli->commit();
                $success = "Return processed. Refund: " . money($refund_total);

                // Reload order data
                $stmt = $mysqli->prepare("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.cashier_id = u.id WHERE o.id = ?");
                $stmt->bind_param('i', $order_id);
                $stmt->execute();
                $order = $stmt->get_result()->fetch_assoc();
                $stmt->close();

            } catch (Exception $e) {
                $mysqli->rollback();
                error_log('Return failed: ' . $e->getMessage());
                $error = 'Return processing failed.';
            }
        }
    }
}

$page_title = 'Returns';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-arrow-counter-clockwise" style="color:var(--color-warning);"></i> Returns</h1>
        <p class="page-subtitle">Look up a receipt to process a return.</p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><span class="alert-icon"><i class="ph ph-warning-circle"></i></span><span class="alert-content"><?= e($error) ?></span></div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success"><span class="alert-icon"><i class="ph ph-check-circle"></i></span><span class="alert-content"><?= e($success) ?></span></div>
<?php endif; ?>

<!-- Receipt Lookup -->
<div class="card" style="margin-bottom:24px;">
    <form method="GET" action="">
        <div class="d-flex gap-1 align-center">
            <div class="input-group flex-1">
                <i class="ph ph-receipt input-icon"></i>
                <input type="text" name="receipt" class="form-control" placeholder="Enter receipt number (e.g. SALE-20260511-0001)..." autofocus value="<?= e($receipt_query) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="ph ph-magnifying-glass"></i> Lookup</button>
        </div>
    </form>
</div>

<?php if ($receipt_query && !$order): ?>
<div class="card"><div class="empty-state"><i class="ph ph-magnifying-glass"></i><p>No order found for receipt "<?= e($receipt_query) ?>".</p></div></div>
<?php endif; ?>

<?php if ($order): ?>
<div class="grid-2" style="align-items:flex-start;">
    <!-- Order Details -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="ph ph-receipt"></i> <?= e($order['receipt_no']) ?></h3>
                <span class="card-subtitle"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?> · Cashier: <?= e($order['cashier_name'] ?? 'System') ?></span>
            </div>
            <span class="badge <?= $order['status'] === 'completed' ? 'badge-success' : ($order['status'] === 'refunded' ? 'badge-warning' : 'badge-danger') ?>">
                <?= e(ucfirst($order['status'])) ?>
            </span>
        </div>

        <div class="d-flex gap-2 mb-2">
            <div><span class="text-muted" style="font-size:0.75rem;">Total</span><br><strong><?= money((float)$order['total']) ?></strong></div>
            <div><span class="text-muted" style="font-size:0.75rem;">Paid</span><br><strong><?= money((float)$order['paid_amount']) ?></strong></div>
            <div><span class="text-muted" style="font-size:0.75rem;">Method</span><br><strong><?= e(ucfirst($order['payment_method'])) ?></strong></div>
        </div>

        <?php if ($order['status'] === 'completed'): ?>
        <form method="POST" action="" data-confirm="Process this return?" data-confirm-title="Confirm Return" data-confirm-type="warning">
            <?= csrf_field() ?>
            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">

            <div class="table-wrapper" style="border:none;">
                <table class="data-table">
                    <thead><tr><th>Select</th><th>Product</th><th class="text-center">Qty Sold</th><th class="text-center">Return Qty</th><th class="text-right">Unit Price</th></tr></thead>
                    <tbody>
                    <?php foreach ($order_items as $oi): ?>
                    <tr>
                        <td><input type="checkbox" name="return_item[]" value="<?= $oi['id'] ?>"></td>
                        <td>
                            <div class="text-bold"><?= e($oi['product_name']) ?></div>
                            <div class="text-muted text-mono" style="font-size:0.7rem;"><?= e($oi['sku']) ?></div>
                        </td>
                        <td class="text-center"><?= (int)$oi['qty'] ?></td>
                        <td class="text-center">
                            <input type="number" name="return_qty[<?= $oi['id'] ?>]" value="<?= (int)$oi['qty'] ?>" min="1" max="<?= (int)$oi['qty'] ?>"
                                   style="width:50px;text-align:center;padding:4px;border:1px solid var(--border-input);border-radius:4px;background:var(--bg-input);color:var(--text-primary);">
                        </td>
                        <td class="text-right"><?= money((float)$oi['price_snapshot']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="form-group mt-2">
                <label class="form-label">Reason for Return</label>
                <input type="text" name="reason" class="form-control" value="Customer return" required>
            </div>

            <button type="submit" class="btn btn-warning btn-block"><i class="ph ph-arrow-counter-clockwise"></i> Process Return & Restock</button>
        </form>
        <?php else: ?>
        <div class="alert alert-warning" style="margin-top:16px;">
            <span class="alert-icon"><i class="ph ph-info"></i></span>
            <span class="alert-content">This order has already been <?= e($order['status']) ?>. No further returns possible.</span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Return Policy Info -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-info"></i> Return Policy</h3></div>
        <ul style="list-style:disc;padding-left:20px;color:var(--text-secondary);font-size:0.9rem;line-height:1.8;">
            <li>Returns accepted within 7 days of purchase</li>
            <li>Item must be in original condition</li>
            <li>Refund to original payment method</li>
            <li>Stock is automatically restocked on return</li>
            <li>All returns are logged in the audit trail</li>
        </ul>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
