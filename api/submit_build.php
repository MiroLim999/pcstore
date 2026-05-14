<?php
/**
 * api/submit_build.php — Submit a PC build for in-store pickup.
 * POST JSON body: { "items": { "cpu": product_id, "cooler": product_id, ... } }
 *
 * Server-side:
 * 1. Validate all product IDs exist and are in stock
 * 2. Run compatibility checks (mirrors JS logic)
 * 3. If valid: create build, reserve stock, generate pickup code
 * 4. Return pickup code + build details
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/compatibility.php';

header('Content-Type: application/json; charset=utf-8');

// Must be logged in
if (!is_logged_in()) {
    json_response(['success' => false, 'error' => 'Authentication required.'], 401);
}

// Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required.'], 405);
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];

if (empty($items) || !is_array($items)) {
    json_response(['success' => false, 'error' => 'No items provided.'], 400);
}

// Valid category slugs
$valid_slugs = ['cpu', 'cooler', 'mobo', 'ram', 'gpu', 'ssd', 'psu', 'case'];
$build_items = [];
$total_price = 0;

// Validate each item
foreach ($items as $slug => $product_id) {
    if (!in_array($slug, $valid_slugs, true)) continue;
    if (!$product_id) continue;

    $product_id = (int)$product_id;
    $stmt = $mysqli->prepare("SELECT p.id, p.price, p.stock_qty, p.reserved_qty, c.slug 
        FROM products p JOIN categories c ON p.category_id = c.id 
        WHERE p.id = ? AND p.is_active = 1");
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$product) {
        json_response(['success' => false, 'error' => "Product ID {$product_id} not found or inactive."], 400);
    }

    if ($product['slug'] !== $slug) {
        json_response(['success' => false, 'error' => "Product ID {$product_id} is not in the '{$slug}' category."], 400);
    }

    $available = $product['stock_qty'] - $product['reserved_qty'];
    if ($available < 1) {
        json_response(['success' => false, 'error' => "Product '{$product_id}' is out of stock."], 400);
    }

    $build_items[$slug] = [
        'product_id' => $product_id,
        'price'      => (float)$product['price'],
    ];
    $total_price += (float)$product['price'];
}

if (empty($build_items)) {
    json_response(['success' => false, 'error' => 'At least one component is required.'], 400);
}

// Run server-side compatibility check
$compat_input = array_map(fn($item) => $item['product_id'], $build_items);
$compat_result = check_build_compatibility($compat_input, $mysqli);

if (!$compat_result['valid']) {
    json_response([
        'success'  => false,
        'error'    => 'Compatibility check failed.',
        'errors'   => $compat_result['errors'],
        'warnings' => $compat_result['warnings'],
    ], 422);
}

// All good — create the build in a transaction
$mysqli->begin_transaction();

try {
    $user_id = (int)current_user()['id'];
    $pickup_code = generate_pickup_code($mysqli);
    $reservation_hours = (int)(get_setting('reservation_hours') ?? DEFAULT_RESERVATION_HOURS);
    $reserved_until = date('Y-m-d H:i:s', strtotime("+{$reservation_hours} hours"));
    $status = 'submitted';

    // Create build
    $stmt = $mysqli->prepare("INSERT INTO builds (user_id, status, pickup_code, total_price, reserved_until) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issds', $user_id, $status, $pickup_code, $total_price, $reserved_until);
    $stmt->execute();
    $build_id = $stmt->insert_id;
    $stmt->close();

    // Create build items + reserve stock
    foreach ($build_items as $slug => $item) {
        $stmt = $mysqli->prepare("INSERT INTO build_items (build_id, product_id, category_slug, price_snapshot, qty) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param('iisd', $build_id, $item['product_id'], $slug, $item['price']);
        $stmt->execute();
        $stmt->close();

        // Reserve stock
        $stmt = $mysqli->prepare("UPDATE products SET reserved_qty = reserved_qty + 1 WHERE id = ?");
        $stmt->bind_param('i', $item['product_id']);
        $stmt->execute();
        $stmt->close();

        // Log inventory transaction
        $type = 'reserve';
        $qty = 1;
        $ref_type = 'build';
        $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, reference_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('isisii', $item['product_id'], $type, $qty, $ref_type, $build_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    $mysqli->commit();

    json_response([
        'success'      => true,
        'build_id'     => $build_id,
        'pickup_code'  => $pickup_code,
        'total_price'  => $total_price,
        'reserved_until' => $reserved_until,
        'warnings'     => $compat_result['warnings'],
        'message'      => "Build submitted! Your pickup code is {$pickup_code}. Present this at the counter within {$reservation_hours} hours.",
    ]);

} catch (Exception $e) {
    $mysqli->rollback();
    error_log('Build submission failed: ' . $e->getMessage());
    json_response(['success' => false, 'error' => 'Failed to submit build. Please try again.'], 500);
}
