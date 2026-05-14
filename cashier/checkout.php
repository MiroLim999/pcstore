<?php
/**
 * cashier/checkout.php — Process payment for a submitted build.
 * ?build=ID — loads the build, shows items, takes cash payment.
 *
 * On payment:
 * 1. DB transaction
 * 2. Update build status → 'paid'
 * 3. Convert reservations → sales in inventory_transactions
 * 4. Decrement products.stock_qty, decrement products.reserved_qty
 * 5. Create orders row + order_items rows (price snapshots)
 * 6. Generate receipt number
 * 7. Redirect to receipt page
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('cashier', 'admin', 'superadmin');

$build_id = (int)($_GET['build'] ?? 0);

if ($build_id < 1) {
    flash('No build specified.', 'danger');
    redirect(BASE_URL . 'cashier/lookup_build.php');
}

// Load build
$stmt = $mysqli->prepare("SELECT b.*, u.name as client_name, u.phone as client_phone, u.email as client_email
    FROM builds b LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ?");
$stmt->bind_param('i', $build_id);
$stmt->execute();
$build = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$build) {
    flash('Build not found.', 'danger');
    redirect(BASE_URL . 'cashier/lookup_build.php');
}

if ($build['status'] !== 'submitted') {
    flash('This build has already been ' . $build['status'] . '.', 'warning');
    redirect(BASE_URL . 'cashier/dashboard.php');
}

// Load build items with product details
$stmt = $mysqli->prepare("SELECT bi.*, p.name as product_name, p.sku, p.cost, p.stock_qty, p.reserved_qty, c.label as category_label
    FROM build_items bi
    JOIN products p ON bi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE bi.build_id = ?
    ORDER BY c.sort_order");
$stmt->bind_param('i', $build_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price_snapshot'] * $item['qty'];
}
$tax = 0;
$total = $subtotal;

// ─── Handle Payment POST ──────────────────────────────────────
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $notes = trim($_POST['notes'] ?? '');

    if ($paid_amount < $total) {
        $errors[] = 'Paid amount (' . money($paid_amount) . ') is less than total (' . money($total) . ').';
    }

    if (empty($errors)) {
        $change = round($paid_amount - $total, 2);
        $cashier_id = (int)current_user()['id'];

        $mysqli->begin_transaction();

        try {
            // 1. Update build status
            $stmt = $mysqli->prepare("UPDATE builds SET status = 'paid', updated_at = NOW() WHERE id = ?");
            $stmt->bind_param('i', $build_id);
            $stmt->execute();
            $stmt->close();

            // 2. Create order
            $receipt_no = generate_receipt_number($mysqli);
            $stmt = $mysqli->prepare("INSERT INTO orders (build_id, cashier_id, receipt_no, subtotal, tax, total, paid_amount, change_amount, payment_method, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?)");
            $stmt->bind_param('iisdddddss', $build_id, $cashier_id, $receipt_no, $subtotal, $tax, $total, $paid_amount, $change, $payment_method, $notes);
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();

            // 3. Create order items + update stock + log transactions
            foreach ($items as $item) {
                $product_id = (int)$item['product_id'];
                $price_snap = (float)$item['price_snapshot'];
                $cost_snap = (float)$item['cost'];
                $qty = (int)$item['qty'];

                // Order item
                $stmt = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, price_snapshot, cost_snapshot, qty) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param('iiddi', $order_id, $product_id, $price_snap, $cost_snap, $qty);
                $stmt->execute();
                $stmt->close();

                // Decrement stock_qty and reserved_qty
                $stmt = $mysqli->prepare("UPDATE products SET stock_qty = stock_qty - ?, reserved_qty = reserved_qty - ? WHERE id = ?");
                $stmt->bind_param('iii', $qty, $qty, $product_id);
                $stmt->execute();
                $stmt->close();

                // Log sale transaction (replaces the reserve)
                $type = 'sale';
                $ref_type = 'order';
                $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, reference_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isisii', $product_id, $type, $qty, $ref_type, $order_id, $cashier_id);
                $stmt->execute();
                $stmt->close();
            }

            $mysqli->commit();

            flash('Payment processed! Receipt: ' . $receipt_no, 'success');
            redirect(BASE_URL . 'cashier/receipt.php?order=' . $order_id);

        } catch (Exception $e) {
            $mysqli->rollback();
            error_log('Checkout failed: ' . $e->getMessage());
            $errors[] = 'Payment processing failed. Please try again.';
        }
    }
}

$page_title = 'Checkout — Build #' . $build['pickup_code'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-cash-register" style="color:var(--color-success);"></i> Checkout</h1>
        <p class="page-subtitle">Build pickup code: <strong style="letter-spacing:2px;font-size:1.1rem;"><?= e($build['pickup_code']) ?></strong></p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>cashier/dashboard.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
    <span class="alert-content"><?= implode('<br>', array_map('e', $errors)) ?></span>
</div>
<?php endif; ?>

<div class="grid-2" style="align-items:flex-start;">
    <!-- Build Details -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3><i class="ph ph-cpu"></i> Build Items</h3>
                <span class="card-subtitle">Client: <?= e($build['client_name'] ?? 'Unknown') ?> <?= $build['client_phone'] ? '(' . e($build['client_phone']) . ')' : '' ?></span>
            </div>
        </div>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Component</th><th>Product</th><th class="text-right">Price</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td><span class="badge badge-primary"><?= e($item['category_label']) ?></span></td>
                    <td>
                        <div class="text-bold"><?= e($item['product_name']) ?></div>
                        <div class="text-muted text-mono" style="font-size:0.7rem;"><?= e($item['sku']) ?></div>
                    </td>
                    <td class="text-right text-bold"><?= money((float)$item['price_snapshot']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-right text-muted">Subtotal</td>
                        <td class="text-right text-bold"><?= money($subtotal) ?></td>
                    </tr>
                    <tr style="border-top:2px solid var(--border-color-light);">
                        <td colspan="2" class="text-right" style="font-size:1.1rem;font-weight:700;">TOTAL</td>
                        <td class="text-right" style="font-size:1.2rem;font-weight:800;color:var(--brand-primary-light);"><?= money($total) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div style="padding:16px 0 0;border-top:1px solid var(--border-color);margin-top:16px;">
            <div class="d-flex gap-2">
                <div>
                    <span class="text-muted" style="font-size:0.75rem;">Submitted</span><br>
                    <span><?= date('M j, Y g:i A', strtotime($build['created_at'])) ?></span>
                </div>
                <div>
                    <span class="text-muted" style="font-size:0.75rem;">Expires</span><br>
                    <span><?= $build['reserved_until'] ? date('M j, Y g:i A', strtotime($build['reserved_until'])) : '—' ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Form -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-money"></i> Take Payment</h3></div>

        <form method="POST" action="" id="payment-form">
            <?= csrf_field() ?>

            <div class="summary-bar mb-2" style="border:none;padding:16px;background:var(--bg-input);border-radius:var(--border-radius);">
                <div class="summary-item">
                    <span class="label">Amount Due</span>
                    <span class="value" style="font-size:1.5rem;color:var(--brand-primary-light);"><?= money($total) ?></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Payment Method</label>
                <select name="payment_method" class="form-control">
                    <option value="cash">Cash</option>
                    <option value="card">Card (manual reference)</option>
                    <option value="split">Split (Cash + Card)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Amount Received (<?= e(DEFAULT_CURRENCY) ?>) <span class="required">*</span></label>
                <input type="number" name="paid_amount" id="paid_amount" class="form-control"
                       step="0.01" min="<?= $total ?>" required
                       placeholder="<?= number_format($total, 2) ?>"
                       style="font-size:1.3rem;font-weight:700;text-align:center;"
                       oninput="calcChange()">
            </div>

            <div class="summary-bar mb-2" style="border:none;padding:12px 16px;background:var(--color-success-bg);border-radius:var(--border-radius);">
                <div class="summary-item">
                    <span class="label">Change Due</span>
                    <span class="value" id="change-display" style="font-size:1.3rem;color:var(--color-success);"><?= money(0) ?></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Notes (optional)</label>
                <input type="text" name="notes" class="form-control" placeholder="e.g. Card ref #1234">
            </div>

            <button type="submit" class="btn btn-success btn-block btn-lg" data-confirm="Confirm payment of <?= money($total) ?>?" data-confirm-title="Confirm Payment" data-confirm-type="info">
                <i class="ph ph-check-circle"></i> Confirm Payment
            </button>
        </form>
    </div>
</div>

<script>
function calcChange() {
    const paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    const total = <?= $total ?>;
    const change = Math.max(0, paid - total);
    document.getElementById('change-display').textContent = '<?= DEFAULT_CURRENCY ?>' + change.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
