<?php
/**
 * cashier/receipt.php — Display and print receipt for a completed order.
 * ?order=ID
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('cashier', 'admin', 'superadmin');

$order_id = (int)($_GET['order'] ?? 0);

if ($order_id < 1) {
    flash('No order specified.', 'danger');
    redirect(BASE_URL . 'cashier/dashboard.php');
}

// Load order
$stmt = $mysqli->prepare("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.cashier_id = u.id WHERE o.id = ?");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    flash('Order not found.', 'danger');
    redirect(BASE_URL . 'cashier/dashboard.php');
}

// Load order items
$stmt = $mysqli->prepare("SELECT oi.*, p.name as product_name, p.sku, c.label as category_label
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    WHERE oi.order_id = ?
    ORDER BY c.sort_order");
$stmt->bind_param('i', $order_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Load build info if linked
$build = null;
$client = null;
if ($order['build_id']) {
    $stmt = $mysqli->prepare("SELECT b.*, u.name as client_name, u.phone as client_phone
        FROM builds b LEFT JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->bind_param('i', $order['build_id']);
    $stmt->execute();
    $build = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$store_name = get_setting('store_name') ?? APP_NAME;
$store_address = get_setting('store_address') ?? '';
$receipt_footer = get_setting('receipt_footer') ?? 'Thank you for your purchase!';

$page_title = 'Receipt — ' . $order['receipt_no'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-receipt" style="color:var(--color-success);"></i> Receipt</h1>
        <p class="page-subtitle"><?= e($order['receipt_no']) ?></p>
    </div>
    <div class="page-actions">
        <button onclick="window.print()" class="btn btn-primary"><i class="ph ph-printer"></i> Print</button>
        <a href="<?= BASE_URL ?>cashier/dashboard.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back to POS</a>
    </div>
</div>

<div class="card" id="receipt-card" style="max-width:500px;margin:0 auto;">
    <!-- Receipt Header -->
    <div style="text-align:center;padding-bottom:16px;border-bottom:2px dashed var(--border-color);margin-bottom:16px;">
        <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:4px;"><?= e($store_name) ?></h2>
        <p class="text-muted" style="font-size:0.8rem;"><?= e($store_address) ?></p>
        <p class="text-muted" style="font-size:0.75rem;margin-top:8px;">
            Receipt #: <strong><?= e($order['receipt_no']) ?></strong><br>
            Date: <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?><br>
            Cashier: <?= e($order['cashier_name'] ?? 'System') ?>
        </p>
        <?php if ($build): ?>
        <p style="font-size:0.8rem;margin-top:8px;">
            Client: <?= e($build['client_name'] ?? 'Walk-in') ?>
            <?php if (!empty($build['client_phone'])): ?> | <?= e($build['client_phone']) ?><?php endif; ?><br>
            Pickup Code: <strong style="letter-spacing:2px;"><?= e($build['pickup_code']) ?></strong>
        </p>
        <?php elseif (!empty($order['notes'])): ?>
        <p style="font-size:0.8rem;margin-top:8px;">
            Customer: <strong><?= e($order['notes']) ?></strong>
        </p>
        <?php else: ?>
        <p style="font-size:0.8rem;margin-top:8px;">
            Customer: <strong>Walk-in</strong>
        </p>
        <?php endif; ?>
    </div>

    <!-- Items -->
    <table style="width:100%;font-size:0.85rem;margin-bottom:16px;">
        <thead>
            <tr style="border-bottom:1px solid var(--border-color);">
                <th style="text-align:left;padding:6px 0;font-weight:600;">Item</th>
                <th style="text-align:center;padding:6px 0;font-weight:600;">Qty</th>
                <th style="text-align:right;padding:6px 0;font-weight:600;">Price</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr style="border-bottom:1px solid var(--border-color);">
                <td style="padding:8px 0;">
                    <div style="font-weight:500;"><?= e($item['product_name']) ?></div>
                    <div style="font-size:0.7rem;color:var(--text-muted);"><?= e($item['category_label']) ?> · <?= e($item['sku']) ?></div>
                </td>
                <td style="text-align:center;padding:8px 0;"><?= (int)$item['qty'] ?></td>
                <td style="text-align:right;padding:8px 0;font-weight:600;"><?= money((float)$item['price_snapshot'] * (int)$item['qty']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div style="border-top:2px dashed var(--border-color);padding-top:12px;">
        <div class="d-flex justify-between" style="margin-bottom:8px;padding-top:4px;font-size:1.2rem;font-weight:800;">
            <span>TOTAL</span>
            <span style="color:var(--brand-primary-light);"><?= money((float)$order['total']) ?></span>
        </div>
        <div class="d-flex justify-between" style="margin-bottom:4px;">
            <span class="text-muted">Paid (<?= e(ucfirst($order['payment_method'])) ?>)</span>
            <span><?= money((float)$order['paid_amount']) ?></span>
        </div>
        <div class="d-flex justify-between" style="margin-bottom:4px;">
            <span class="text-muted">Change</span>
            <span style="color:var(--color-success);font-weight:600;"><?= money((float)$order['change_amount']) ?></span>
        </div>
    </div>

    <!-- Footer -->
    <div style="text-align:center;padding-top:16px;border-top:2px dashed var(--border-color);margin-top:16px;">
        <p style="font-size:0.8rem;color:var(--text-muted);"><?= e($receipt_footer) ?></p>
        <p style="font-size:0.7rem;color:var(--text-muted);margin-top:8px;">
            Status: <span class="badge badge-success"><?= e(ucfirst($order['status'])) ?></span>
        </p>
    </div>
</div>

<style>
@media print {
    .top-navbar, .sidebar, .page-header, .page-actions, .btn { display: none !important; }
    .main-content { margin: 0 !important; padding: 0 !important; }
    #receipt-card { max-width: 100% !important; border: none !important; box-shadow: none !important; }
    body { background: #fff !important; color: #000 !important; }
    * { color: #000 !important; background: transparent !important; }
    .badge { border: 1px solid #000 !important; }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
