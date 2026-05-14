<?php
/**
 * cashier/cancel_build.php — Cancel a submitted build and release reserved stock.
 * POST only. Redirects back to dashboard.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('cashier', 'admin', 'superadmin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'cashier/dashboard.php');
}

csrf_check();

$build_id = (int)($_POST['build_id'] ?? 0);

if ($build_id < 1) {
    flash('Invalid build.', 'danger');
    redirect(BASE_URL . 'cashier/dashboard.php');
}

// Load build
$stmt = $mysqli->prepare("SELECT * FROM builds WHERE id = ? AND status = 'submitted'");
$stmt->bind_param('i', $build_id);
$stmt->execute();
$build = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$build) {
    flash('Build not found or already processed.', 'warning');
    redirect(BASE_URL . 'cashier/dashboard.php');
}

// Load build items
$stmt = $mysqli->prepare("SELECT * FROM build_items WHERE build_id = ?");
$stmt->bind_param('i', $build_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$cashier_id = (int)current_user()['id'];

$mysqli->begin_transaction();

try {
    // Update build status
    $stmt = $mysqli->prepare("UPDATE builds SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $build_id);
    $stmt->execute();
    $stmt->close();

    // Release reserved stock for each item
    foreach ($items as $item) {
        $product_id = (int)$item['product_id'];
        $qty = (int)$item['qty'];

        // Decrement reserved_qty (stock_qty stays the same — it was never decremented)
        $stmt = $mysqli->prepare("UPDATE products SET reserved_qty = GREATEST(0, reserved_qty - ?) WHERE id = ?");
        $stmt->bind_param('ii', $qty, $product_id);
        $stmt->execute();
        $stmt->close();

        // Log release transaction
        $type = 'release';
        $ref_type = 'build';
        $reason = 'Build cancelled by cashier';
        $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, reference_id, created_by, reason) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isisiis', $product_id, $type, $qty, $ref_type, $build_id, $cashier_id, $reason);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();
    flash('Build cancelled. Stock released.', 'success');

} catch (Exception $e) {
    $mysqli->rollback();
    error_log('Cancel build failed: ' . $e->getMessage());
    flash('Failed to cancel build. Please try again.', 'danger');
}

redirect(BASE_URL . 'cashier/dashboard.php');
