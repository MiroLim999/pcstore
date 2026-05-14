<?php
/**
 * api/order_view.php — Returns order details as JSON for the sales history modal.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    json_response(['success' => false, 'error' => 'Invalid order ID']);
}

// Fetch order
$stmt = $mysqli->prepare("SELECT o.*, u.name as cashier_name FROM orders o LEFT JOIN users u ON o.cashier_id = u.id WHERE o.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    json_response(['success' => false, 'error' => 'Order not found']);
}

// Fetch order items
$stmt = $mysqli->prepare("SELECT oi.*, p.name as product_name, p.sku, p.image_url FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? ORDER BY oi.id");
$stmt->bind_param('i', $id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$currency = get_setting('currency_symbol') ?? DEFAULT_CURRENCY;

json_response([
    'success' => true,
    'order' => [
        'id'             => (int)$order['id'],
        'receipt_no'     => $order['receipt_no'],
        'status'         => $order['status'],
        'date'           => date('M j, Y g:i A', strtotime($order['created_at'])),
        'cashier'        => $order['cashier_name'] ?? 'System',
        'payment_method' => $order['payment_method'],
        'subtotal'       => $currency . number_format((float)$order['subtotal'], 2),
        'total'          => $currency . number_format((float)$order['total'], 2),
        'paid'           => $currency . number_format((float)$order['paid_amount'], 2),
        'change'         => $currency . number_format((float)$order['change_amount'], 2),
        'notes'          => $order['notes'] ?? '',
        'items'          => array_map(function($item) use ($currency) {
            $line = (float)$item['price_snapshot'] * (int)$item['qty'] - (float)$item['discount'];
            return [
                'name'       => $item['product_name'],
                'sku'        => $item['sku'],
                'qty'        => (int)$item['qty'],
                'price'      => $currency . number_format((float)$item['price_snapshot'], 2),
                'line_total' => $currency . number_format($line, 2),
                'image'      => $item['image_url'] ? BASE_URL . $item['image_url'] : null,
            ];
        }, $items),
    ]
]);
