<?php
/**
 * api/check_compat.php — Live compatibility check endpoint.
 * POST JSON body: { "items": { "cpu": product_id, "mobo": product_id, ... } }
 * Returns compatibility result without creating a build.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/compatibility.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST method required.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$items = $input['items'] ?? [];

if (empty($items) || !is_array($items)) {
    json_response(['success' => false, 'error' => 'No items provided.'], 400);
}

// Filter to valid slugs and cast to int
$valid_slugs = ['cpu', 'cooler', 'mobo', 'ram', 'gpu', 'ssd', 'psu', 'case'];
$clean_items = [];
foreach ($items as $slug => $product_id) {
    if (in_array($slug, $valid_slugs, true) && $product_id) {
        $clean_items[$slug] = (int)$product_id;
    }
}

$result = check_build_compatibility($clean_items, $mysqli);

// Also compute bottleneck if CPU and GPU are present
$bottleneck = null;
if (!empty($clean_items['cpu']) && !empty($clean_items['gpu'])) {
    $cpu_attrs = get_product_compat_attrs($clean_items['cpu'], $mysqli);
    $gpu_attrs = get_product_compat_attrs($clean_items['gpu'], $mysqli);
    $bottleneck = calculate_bottleneck($cpu_attrs, $gpu_attrs);
}

json_response([
    'success'    => true,
    'valid'      => $result['valid'],
    'errors'     => $result['errors'],
    'warnings'   => $result['warnings'],
    'bottleneck' => $bottleneck,
]);
