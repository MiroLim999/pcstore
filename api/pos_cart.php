<?php
/**
 * api/pos_cart.php — AJAX endpoint for POS cart operations.
 * Handles add, remove, update_qty, clear without page refresh.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in() || !has_role('cashier')) {
    json_response(['success' => false, 'error' => 'Unauthorized'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

// Initialize cart
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}
$cart = &$_SESSION['pos_cart'];

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'add') {
    $product_id = (int)($input['product_id'] ?? 0);
    if ($product_id <= 0) {
        json_response(['success' => false, 'error' => 'Invalid product']);
    }

    $stmt = $mysqli->prepare("SELECT id, name, sku, price, cost, stock_qty, reserved_qty, image_url FROM products WHERE id = ? AND is_active = 1");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        json_response(['success' => false, 'error' => 'Product not found']);
    }

    $available = $product['stock_qty'] - $product['reserved_qty'];
    $pid = (int)$product['id'];

    $existing_qty = 0;
    foreach ($cart as $item) {
        if ($item['product_id'] === $pid) $existing_qty += $item['qty'];
    }

    if ($existing_qty >= $available) {
        json_response(['success' => false, 'error' => "Not enough stock (available: {$available})"]);
    }

    $found = false;
    foreach ($cart as &$item) {
        if ($item['product_id'] === $pid) {
            $item['qty']++;
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $cart[] = [
            'product_id' => $pid,
            'name'       => $product['name'],
            'sku'        => $product['sku'],
            'price'      => (float)$product['price'],
            'cost'       => (float)$product['cost'],
            'image_url'  => $product['image_url'],
            'qty'        => 1,
        ];
    }

    json_response(['success' => true, 'message' => "Added: {$product['name']}", 'cart' => buildCartResponse($cart)]);

} elseif ($action === 'remove') {
    $idx = (int)($input['index'] ?? -1);
    if (isset($cart[$idx])) {
        array_splice($cart, $idx, 1);
    }
    json_response(['success' => true, 'cart' => buildCartResponse($cart)]);

} elseif ($action === 'update_qty') {
    $idx = (int)($input['index'] ?? -1);
    $qty = (int)($input['qty'] ?? 1);
    if (isset($cart[$idx])) {
        if ($qty < 1) {
            array_splice($cart, $idx, 1);
        } else {
            $cart[$idx]['qty'] = $qty;
        }
    }
    json_response(['success' => true, 'cart' => buildCartResponse($cart)]);

} elseif ($action === 'clear') {
    $cart = [];
    $_SESSION['pos_cart'] = [];
    json_response(['success' => true, 'cart' => buildCartResponse($cart)]);

} else {
    json_response(['success' => false, 'error' => 'Unknown action']);
}

function buildCartResponse(array &$cart): array
{
    $subtotal = 0;
    $items_count = 0;
    foreach ($cart as $item) {
        $subtotal += ($item['price'] * $item['qty']);
        $items_count += $item['qty'];
    }
    $total = $subtotal;
    $currency = get_setting('currency_symbol') ?? DEFAULT_CURRENCY;

    return [
        'items' => array_values(array_map(function($item) use ($currency) {
            return [
                'name'  => $item['name'],
                'sku'   => $item['sku'],
                'price' => (float)$item['price'],
                'qty'   => (int)$item['qty'],
                'line_total' => (float)($item['price'] * $item['qty']),
                'price_fmt' => $currency . number_format($item['price'], 2),
                'line_total_fmt' => $currency . number_format($item['price'] * $item['qty'], 2),
            ];
        }, $cart)),
        'subtotal' => $subtotal,
        'total' => $total,
        'items_count' => $items_count,
        'subtotal_fmt' => $currency . number_format($subtotal, 2),
        'total_fmt' => $currency . number_format($total, 2),
    ];
}
