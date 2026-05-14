<?php
/**
 * api/product_search.php — Search products by name, SKU, or barcode.
 * GET ?q=search_term
 * Returns JSON array of matching products (for POS autocomplete).
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Auth required.'], 401);
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    json_response(['success' => true, 'products' => []]);
}

$like = "%{$q}%";
$stmt = $mysqli->prepare("SELECT p.id, p.name, p.sku, p.barcode, p.price, p.stock_qty, p.reserved_qty, p.image_url, c.label as category
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.is_active = 1 AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
    ORDER BY p.name LIMIT 10");
$stmt->bind_param('sss', $like, $like, $like);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$results = array_map(function($p) {
    return [
        'id'       => (int)$p['id'],
        'name'     => $p['name'],
        'sku'      => $p['sku'],
        'barcode'  => $p['barcode'],
        'price'    => (float)$p['price'],
        'stock'    => (int)$p['stock_qty'] - (int)$p['reserved_qty'],
        'category' => $p['category'],
    ];
}, $products);

json_response(['success' => true, 'products' => $results]);
