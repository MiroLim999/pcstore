<?php
/**
 * api/catalog.php — Returns product catalog as JSON for the PC builder.
 * Groups products by category slug, includes specs and compatibility attrs.
 * Shape mirrors the JS CATALOG object so the builder can consume it directly.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Optional category filter
$category = $_GET['category'] ?? '';

$where = "WHERE p.is_active = 1 AND p.stock_qty > 0";
$params = [];
$types = '';

if ($category !== '') {
    $where .= " AND c.slug = ?";
    $params[] = $category;
    $types .= 's';
}

$sql = "SELECT p.id, p.name, p.price, p.image_url, p.stock_qty, c.slug as category_slug, c.label as category_label
        FROM products p
        JOIN categories c ON p.category_id = c.id
        {$where}
        ORDER BY c.sort_order, p.name";

$stmt = $mysqli->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($products)) {
    echo json_encode(['success' => true, 'catalog' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

// Collect all product IDs for batch queries
$product_ids = array_column($products, 'id');
$id_placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$id_types = str_repeat('i', count($product_ids));

// Batch load all specs
$stmt = $mysqli->prepare("SELECT product_id, spec_key, spec_value FROM product_specs WHERE product_id IN ({$id_placeholders}) ORDER BY sort_order");
$stmt->bind_param($id_types, ...$product_ids);
$stmt->execute();
$all_specs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group specs by product_id
$specs_map = [];
foreach ($all_specs as $s) {
    $specs_map[(int)$s['product_id']][] = $s['spec_value'];
}

// Batch load all compatibility attrs
$stmt = $mysqli->prepare("SELECT * FROM compatibility_attrs WHERE product_id IN ({$id_placeholders})");
$stmt->bind_param($id_types, ...$product_ids);
$stmt->execute();
$all_compat = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Group compat by product_id
$compat_map = [];
foreach ($all_compat as $c) {
    $compat_map[(int)$c['product_id']] = array_filter($c, fn($v, $k) => $v !== null && !in_array($k, ['id', 'product_id']), ARRAY_FILTER_USE_BOTH);
}

// Build grouped catalog
$catalog = [];

foreach ($products as $p) {
    $slug = $p['category_slug'];
    $pid = (int)$p['id'];

    if (!isset($catalog[$slug])) {
        $catalog[$slug] = [
            'label'   => $p['category_label'],
            'options' => [],
        ];
    }

    $catalog[$slug]['options'][] = [
        'id'       => $pid,
        'name'     => $p['name'],
        'price'    => (float)$p['price'],
        'image'    => $p['image_url'] ? (BASE_URL . $p['image_url']) : null,
        'stock'    => (int)$p['stock_qty'],
        'specs'    => $specs_map[$pid] ?? [],
        'compat'   => $compat_map[$pid] ?? [],
    ];
}

echo json_encode(['success' => true, 'catalog' => $catalog], JSON_UNESCAPED_UNICODE);
