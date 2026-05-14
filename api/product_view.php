<?php
/**
 * api/product_view.php — Returns full product details as JSON for the admin view modal.
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
    json_response(['success' => false, 'error' => 'Invalid product ID']);
}

// Fetch product
$stmt = $mysqli->prepare("SELECT p.*, c.label as category_label FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    json_response(['success' => false, 'error' => 'Product not found']);
}

// Fetch specs
$stmt = $mysqli->prepare("SELECT spec_key, spec_value FROM product_specs WHERE product_id = ? ORDER BY sort_order");
$stmt->bind_param('i', $id);
$stmt->execute();
$specs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$currency = get_setting('currency_symbol') ?? DEFAULT_CURRENCY;

json_response([
    'success' => true,
    'product' => [
        'id'                  => (int)$product['id'],
        'name'                => $product['name'],
        'sku'                 => $product['sku'],
        'barcode'             => $product['barcode'],
        'category'            => $product['category_label'] ?? 'Uncategorized',
        'description'         => $product['description'] ?? '',
        'price'               => $currency . number_format((float)$product['price'], 2),
        'cost'                => $currency . number_format((float)$product['cost'], 2),
        'stock_qty'           => (int)$product['stock_qty'],
        'reserved_qty'        => (int)$product['reserved_qty'],
        'low_stock_threshold' => (int)$product['low_stock_threshold'],
        'image_url'           => $product['image_url'] ? BASE_URL . $product['image_url'] : null,
        'is_active'           => (bool)$product['is_active'],
        'created_at'          => $product['created_at'],
        'specs'               => array_map(function($s) {
            return ['key' => $s['spec_key'], 'value' => $s['spec_value']];
        }, $specs),
    ]
]);
