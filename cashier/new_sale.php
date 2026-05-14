<?php
/**
 * cashier/new_sale.php — Walk-in POS with product catalog cards.
 * Cashier can browse, search, filter products and click to add to cart.
 * Supports walk-in customers buying individual items (CPU, GPU, etc.)
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('cashier', 'admin', 'superadmin');

// Initialize cart in session
if (!isset($_SESSION['pos_cart'])) {
    $_SESSION['pos_cart'] = [];
}

$cart = &$_SESSION['pos_cart'];
$error = '';
$success_msg = '';

// ─── Handle POST actions ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            $stmt = $mysqli->prepare("SELECT id, name, sku, price, cost, stock_qty, reserved_qty, image_url FROM products WHERE id = ? AND is_active = 1");
            $stmt->bind_param('i', $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                $error = 'Product not found or inactive.';
            } else {
                $available = $product['stock_qty'] - $product['reserved_qty'];
                $pid = (int)$product['id'];

                // Check if already in cart
                $existing_qty = 0;
                foreach ($cart as $item) {
                    if ($item['product_id'] === $pid) $existing_qty += $item['qty'];
                }

                if ($existing_qty >= $available) {
                    $error = "Not enough stock for \"{$product['name']}\" (available: {$available}).";
                } else {
                    // Add or increment
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
                    $success_msg = "Added: {$product['name']}";
                }
            }
        }
    } elseif ($action === 'remove') {
        $idx = (int)($_POST['index'] ?? -1);
        if (isset($cart[$idx])) {
            array_splice($cart, $idx, 1);
        }
    } elseif ($action === 'update_qty') {
        $idx = (int)($_POST['index'] ?? -1);
        $qty = (int)($_POST['qty'] ?? 1);
        if (isset($cart[$idx])) {
            if ($qty < 1) {
                array_splice($cart, $idx, 1);
            } else {
                $cart[$idx]['qty'] = $qty;
            }
        }
    } elseif ($action === 'clear') {
        $cart = [];
        $_SESSION['pos_cart'] = [];
    } elseif ($action === 'checkout') {
        if (empty($cart)) {
            $error = 'Cart is empty.';
        } else {
            $paid_amount = (float)($_POST['paid_amount'] ?? 0);
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $notes = trim($_POST['notes'] ?? '');

            // Calculate totals
            $subtotal = 0;
            foreach ($cart as $item) {
                $line_total = $item['price'] * $item['qty'] - ($item['discount'] ?? 0);
                $subtotal += $line_total;
            }
            $total = $subtotal;

            if ($paid_amount < $total) {
                $error = 'Paid amount is less than total.';
            } else {
                $change = round($paid_amount - $total, 2);
                $cashier_id = (int)current_user()['id'];

                $mysqli->begin_transaction();
                try {
                    $receipt_no = generate_receipt_number($mysqli);
                    $status = 'completed';
                    $tax = 0;
                    $stmt = $mysqli->prepare("INSERT INTO orders (build_id, cashier_id, receipt_no, subtotal, tax, total, paid_amount, change_amount, payment_method, status, notes) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isdddddsss', $cashier_id, $receipt_no, $subtotal, $tax, $total, $paid_amount, $change, $payment_method, $status, $notes);
                    $stmt->execute();
                    $order_id = $stmt->insert_id;
                    $stmt->close();

                    foreach ($cart as $item) {
                        $pid = (int)$item['product_id'];
                        $price = (float)$item['price'];
                        $cost = (float)$item['cost'];
                        $qty = (int)$item['qty'];
                        $disc = (float)($item['discount'] ?? 0);

                        $stmt = $mysqli->prepare("INSERT INTO order_items (order_id, product_id, price_snapshot, cost_snapshot, qty, discount) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param('iiddid', $order_id, $pid, $price, $cost, $qty, $disc);
                        $stmt->execute();
                        $stmt->close();

                        $stmt = $mysqli->prepare("UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?");
                        $stmt->bind_param('ii', $qty, $pid);
                        $stmt->execute();
                        $stmt->close();

                        $type = 'sale';
                        $ref_type = 'order';
                        $stmt = $mysqli->prepare("INSERT INTO inventory_transactions (product_id, type, qty, reference_type, reference_id, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param('isisii', $pid, $type, $qty, $ref_type, $order_id, $cashier_id);
                        $stmt->execute();
                        $stmt->close();
                    }

                    $mysqli->commit();
                    $cart = [];
                    $_SESSION['pos_cart'] = [];

                    flash("Sale complete! Receipt: {$receipt_no}", 'success');
                    redirect(BASE_URL . 'cashier/receipt.php?order=' . $order_id);

                } catch (Exception $e) {
                    $mysqli->rollback();
                    error_log('Walk-in sale failed: ' . $e->getMessage());
                    $error = 'Sale processing failed. Please try again.';
                }
            }
        }
    }
}

// ─── Fetch all active products with categories ────────────────
$products_result = $mysqli->query("SELECT p.*, c.label as category_label, c.slug as category_slug FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 ORDER BY c.sort_order, p.name");
$all_products = $products_result->fetch_all(MYSQLI_ASSOC);

// Fetch categories for filter
$categories_result = $mysqli->query("SELECT * FROM categories ORDER BY sort_order");
$categories = $categories_result->fetch_all(MYSQLI_ASSOC);

// Calculate cart totals for display
$cart_subtotal = 0;
$cart_items_count = 0;
foreach ($cart as $item) {
    $cart_subtotal += ($item['price'] * $item['qty']) - ($item['discount'] ?? 0);
    $cart_items_count += $item['qty'];
}
$cart_total = $cart_subtotal;

$page_title = 'Walk-in Sale';
$page_css = [BASE_URL . 'assets/css/pos.css'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="pos-container">
    <!-- LEFT: Product Catalog -->
    <div class="pos-catalog">
        <div class="pos-search-bar">
            <div class="input-group">
                <i class="ph ph-magnifying-glass input-icon"></i>
                <input type="text" id="posProductSearch" class="form-control" placeholder="Search products by name or SKU..." autocomplete="off">
            </div>
            <div class="pos-category-filters" id="posCategoryFilters">
                <button class="pos-filter-btn active" data-filter="all">All</button>
                <?php foreach ($categories as $cat): ?>
                <button class="pos-filter-btn" data-filter="<?= e($cat['slug']) ?>"><?= e($cat['label']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="pos-products-grid" id="posProductsGrid">
            <?php foreach ($all_products as $p):
                $available = $p['stock_qty'] - $p['reserved_qty'];
                $out_of_stock = $available <= 0;
            ?>
            <div class="pos-product-card <?= $out_of_stock ? 'out-of-stock' : '' ?>"
                 data-name="<?= e(strtolower($p['name'])) ?>"
                 data-sku="<?= e(strtolower($p['sku'])) ?>"
                 data-category="<?= e($p['category_slug']) ?>"
                 data-id="<?= (int)$p['id'] ?>"
                 <?php if (!$out_of_stock): ?>onclick="addToCart(<?= (int)$p['id'] ?>)"<?php endif; ?>>
                <div class="pos-product-img">
                    <?php if ($p['image_url']): ?>
                    <img src="<?= BASE_URL . e($p['image_url']) ?>" alt="<?= e($p['name']) ?>">
                    <?php else: ?>
                    <div class="pos-product-img-placeholder"><i class="ph ph-package"></i></div>
                    <?php endif; ?>
                    <span class="pos-product-category-badge"><?= e($p['category_label']) ?></span>
                    <?php if ($out_of_stock): ?>
                    <span class="pos-product-oos-badge">Out of Stock</span>
                    <?php endif; ?>
                </div>
                <div class="pos-product-info">
                    <h4 class="pos-product-name"><?= e($p['name']) ?></h4>
                    <p class="pos-product-sku"><?= e($p['sku']) ?></p>
                    <div class="pos-product-footer">
                        <span class="pos-product-price"><?= money((float)$p['price']) ?></span>
                        <span class="pos-product-stock"><?= $available ?> in stock</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="pos-cart-panel">
        <div class="pos-cart-header">
            <h2><i class="ph ph-receipt"></i> Current Sale</h2>
            <button type="button" class="btn btn-ghost btn-sm" id="posClearBtn" onclick="if(confirm('Clear cart?'))clearCart();" style="<?= empty($cart) ? 'display:none;' : '' ?>"><i class="ph ph-trash"></i> Clear</button>
        </div>

        <div class="pos-cart-items" id="posCartItems">
            <?php if (empty($cart)): ?>
            <div class="pos-cart-empty">
                <i class="ph ph-shopping-cart"></i>
                <p>Cart is empty</p>
                <small>Click products to add them</small>
            </div>
            <?php else: ?>
            <?php foreach ($cart as $idx => $item): ?>
            <div class="pos-cart-item">
                <div class="pos-cart-item-info">
                    <span class="pos-cart-item-name"><?= e($item['name']) ?></span>
                    <span class="pos-cart-item-sku"><?= e($item['sku']) ?></span>
                </div>
                <div class="pos-cart-item-actions">
                    <input type="number" value="<?= (int)$item['qty'] ?>" min="1" max="99"
                           class="pos-qty-input" onchange="updateCartQty(<?= $idx ?>, parseInt(this.value))">
                    <span class="pos-cart-item-price-col">
                        <span class="pos-cart-item-unit"><?= money($item['price']) ?></span>
                        <span class="pos-cart-item-total"><?= money($item['price'] * $item['qty']) ?></span>
                    </span>
                    <button type="button" class="pos-remove-btn" title="Remove" onclick="removeFromCart(<?= $idx ?>)"><i class="ph ph-x"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Totals -->
        <div class="pos-cart-totals" id="posCartTotals">
            <div class="pos-total-row">
                <span>Subtotal</span><span><?= money($cart_subtotal) ?></span>
            </div>
            <div class="pos-total-row pos-total-grand">
                <span>Total Due</span><span><?= money($cart_total) ?></span>
            </div>
        </div>

        <!-- Payment -->
        <div id="posPaymentSection">
        <?php if (!empty($cart)): ?>
        <form method="POST" action="" id="checkout-form" class="pos-checkout-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="checkout">
            <input type="hidden" name="payment_method" value="cash">

            <div class="pos-payment-method-display">
                <i class="ph ph-money"></i>
                <span>Cash Payment</span>
            </div>

            <div class="form-group">
                <label class="form-label">Amount Received</label>
                <input type="number" name="paid_amount" id="pos-paid" class="form-control pos-amount-input"
                       step="0.01" min="<?= $cart_total ?>" required placeholder="0.00"
                       oninput="posCalcChange()">
            </div>

            <div class="pos-change-display">
                <span>Change</span>
                <span id="pos-change" class="pos-change-amount"><?= money(0) ?></span>
            </div>

            <div class="form-group" style="margin-top:12px;">
                <label class="form-label">Customer Name (optional)</label>
                <input type="text" name="notes" class="form-control" placeholder="Walk-in customer name...">
            </div>

            <button type="submit" class="btn btn-success btn-block btn-lg pos-checkout-btn" data-confirm="Confirm payment?" data-confirm-title="Complete Sale" data-confirm-type="info">
                <i class="ph ph-check-circle"></i> Complete Sale
            </button>
        </form>
        <?php else: ?>
        <p class="text-muted text-center" style="padding:20px 0;">Add items to cart to proceed.</p>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($error): ?>
<div class="pos-toast pos-toast-error" id="posToast"><?= e($error) ?></div>
<?php elseif ($success_msg): ?>
<div class="pos-toast pos-toast-success" id="posToast"><?= e($success_msg) ?></div>
<?php endif; ?>

<script>
// ─── AJAX Add to Cart (no page refresh) ───────────────────────
function addToCart(productId) {
    fetch('<?= BASE_URL ?>api/pos_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', product_id: productId })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            renderCart(data.cart);
            showPosToast(data.message, 'success');
        } else {
            showPosToast(data.error, 'error');
        }
    })
    .catch(function() { showPosToast('Network error', 'error'); });
}

function removeFromCart(idx) {
    fetch('<?= BASE_URL ?>api/pos_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'remove', index: idx })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { if (data.success) renderCart(data.cart); });
}

function updateCartQty(idx, qty) {
    fetch('<?= BASE_URL ?>api/pos_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'update_qty', index: idx, qty: qty })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { if (data.success) renderCart(data.cart); });
}

function clearCart() {
    fetch('<?= BASE_URL ?>api/pos_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear' })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) { if (data.success) renderCart(data.cart); });
}

// ─── Render Cart UI ───────────────────────────────────────────
function renderCart(cart) {
    var itemsEl = document.getElementById('posCartItems');
    var totalsEl = document.getElementById('posCartTotals');
    var paymentEl = document.getElementById('posPaymentSection');
    var clearBtn = document.getElementById('posClearBtn');

    if (cart.items.length === 0) {
        itemsEl.innerHTML = '<div class="pos-cart-empty"><i class="ph ph-shopping-cart"></i><p>Cart is empty</p><small>Click products to add them</small></div>';
        totalsEl.innerHTML = '<div class="pos-total-row"><span>Subtotal</span><span>' + cart.subtotal_fmt + '</span></div><div class="pos-total-row pos-total-grand"><span>Total Due</span><span>' + cart.total_fmt + '</span></div>';
        paymentEl.innerHTML = '<p class="text-muted text-center" style="padding:20px 0;">Add items to cart to proceed.</p>';
        if (clearBtn) clearBtn.style.display = 'none';
        return;
    }

    if (clearBtn) clearBtn.style.display = 'inline';

    var html = '';
    cart.items.forEach(function(item, idx) {
        html += '<div class="pos-cart-item">';
        html += '<div class="pos-cart-item-info"><span class="pos-cart-item-name">' + item.name + '</span><span class="pos-cart-item-sku">' + item.sku + '</span></div>';
        html += '<div class="pos-cart-item-actions">';
        html += '<input type="number" value="' + item.qty + '" min="1" max="99" class="pos-qty-input" onchange="updateCartQty(' + idx + ', parseInt(this.value))">';
        html += '<span class="pos-cart-item-price-col"><span class="pos-cart-item-unit">' + item.price_fmt + '</span><span class="pos-cart-item-total">' + item.line_total_fmt + '</span></span>';
        html += '<button type="button" class="pos-remove-btn" title="Remove" onclick="removeFromCart(' + idx + ')"><i class="ph ph-x"></i></button>';
        html += '</div></div>';
    });
    itemsEl.innerHTML = html;

    totalsEl.innerHTML = '<div class="pos-total-row"><span>Subtotal</span><span>' + cart.subtotal_fmt + '</span></div><div class="pos-total-row pos-total-grand"><span>Total Due</span><span>' + cart.total_fmt + '</span></div>';

    // Update payment form
    var payHtml = '';
    payHtml += '<form method="POST" action="" id="checkout-form" class="pos-checkout-form">';
    payHtml += '<?= csrf_field() ?>';
    payHtml += '<input type="hidden" name="action" value="checkout">';
    payHtml += '<input type="hidden" name="payment_method" value="cash">';
    payHtml += '<div class="pos-payment-method-display"><i class="ph ph-money"></i><span>Cash Payment</span></div>';
    payHtml += '<div class="form-group"><label class="form-label">Amount Received</label>';
    payHtml += '<input type="number" name="paid_amount" id="pos-paid" class="form-control pos-amount-input" step="0.01" min="' + cart.total + '" required placeholder="0.00" oninput="posCalcChange()"></div>';
    payHtml += '<div class="pos-change-display"><span>Change</span><span id="pos-change" class="pos-change-amount"><?= DEFAULT_CURRENCY ?>0.00</span></div>';
    payHtml += '<div class="form-group" style="margin-top:12px;"><label class="form-label">Customer Name (optional)</label>';
    payHtml += '<input type="text" name="notes" class="form-control" placeholder="Walk-in customer name..."></div>';
    payHtml += '<button type="submit" class="btn btn-success btn-block btn-lg pos-checkout-btn" data-confirm="Confirm payment?" data-confirm-title="Complete Sale" data-confirm-type="info"><i class="ph ph-check-circle"></i> Complete Sale</button>';
    payHtml += '</form>';
    paymentEl.innerHTML = payHtml;

    // Update the cart total for change calculator
    window._cartTotal = cart.total;
}

// ─── Search & Filter ──────────────────────────────────────────
(function() {
    const searchInput = document.getElementById('posProductSearch');
    const grid = document.getElementById('posProductsGrid');
    const cards = grid.querySelectorAll('.pos-product-card');
    const filterBtns = document.querySelectorAll('.pos-filter-btn');
    let activeFilter = 'all';

    function filterProducts() {
        const query = searchInput.value.toLowerCase().trim();
        cards.forEach(function(card) {
            const name = card.dataset.name;
            const sku = card.dataset.sku;
            const category = card.dataset.category;

            const matchesSearch = !query || name.includes(query) || sku.includes(query);
            const matchesCategory = activeFilter === 'all' || category === activeFilter;

            card.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', filterProducts);

    filterBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            filterBtns.forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            filterProducts();
        });
    });
})();

// ─── Change Calculator ────────────────────────────────────────
window._cartTotal = <?= $cart_total ?>;
function posCalcChange() {
    const paid = parseFloat(document.getElementById('pos-paid').value) || 0;
    const total = window._cartTotal;
    const change = Math.max(0, paid - total);
    document.getElementById('pos-change').textContent = '<?= DEFAULT_CURRENCY ?>' + change.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
}

// ─── Toast ────────────────────────────────────────────────────
function showPosToast(msg, type) {
    var existing = document.getElementById('posToast');
    if (existing) existing.remove();
    var toast = document.createElement('div');
    toast.id = 'posToast';
    toast.className = 'pos-toast pos-toast-' + type;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function() { toast.classList.add('show'); }, 50);
    setTimeout(function() { toast.classList.remove('show'); setTimeout(function() { toast.remove(); }, 400); }, 2500);
}

// ─── Toast auto-dismiss (for page-load toasts) ────────────────
(function() {
    const toast = document.getElementById('posToast');
    if (toast) {
        setTimeout(function() { toast.classList.add('show'); }, 100);
        setTimeout(function() { toast.classList.remove('show'); }, 3500);
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
