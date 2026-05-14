<?php
/**
 * client/catalog.php — Public product catalog with search, filter, and product detail modal.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$search      = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$sort        = $_GET['sort'] ?? 'name';

$where = "WHERE p.is_active = 1 AND p.stock_qty > 0";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}
if ($category_id > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

$order = match ($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    default      => 'p.name ASC',
};

$sql = "SELECT p.*, c.label as category_label FROM products p LEFT JOIN categories c ON p.category_id = c.id {$where} ORDER BY {$order}";
$stmt = $mysqli->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch specs for all products
$specs_map = [];
if (!empty($products)) {
    $pids = array_column($products, 'id');
    $placeholders = implode(',', array_fill(0, count($pids), '?'));
    $id_types = str_repeat('i', count($pids));
    $stmt = $mysqli->prepare("SELECT product_id, spec_key, spec_value FROM product_specs WHERE product_id IN ({$placeholders}) ORDER BY sort_order");
    $stmt->bind_param($id_types, ...$pids);
    $stmt->execute();
    $all_specs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($all_specs as $s) {
        $specs_map[(int)$s['product_id']][] = ['key' => $s['spec_key'], 'value' => $s['spec_value']];
    }
}

$categories = $mysqli->query("SELECT id, label FROM categories ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Catalog';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-storefront" style="color:var(--brand-primary-light);"></i> Product Catalog</h1>
        <p class="page-subtitle"><?= count($products) ?> products available</p>
    </div>
</div>

<form class="filter-bar" method="GET" action="">
    <div class="form-group">
        <label class="form-label">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Search products..." value="<?= e($search) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-control">
            <option value="0">All</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">Sort</label>
        <select name="sort" class="form-control">
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low → High</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High → Low</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-secondary"><i class="ph ph-funnel"></i> Filter</button>
    </div>
</form>

<?php if (empty($products)): ?>
<div class="card"><div class="empty-state"><i class="ph ph-magnifying-glass"></i><p>No products found matching your criteria.</p></div></div>
<?php else: ?>
<div class="grid-3">
    <?php foreach ($products as $p): ?>
    <div class="card card-hover catalog-product-card" style="padding:16px;cursor:pointer;"
         data-id="<?= (int)$p['id'] ?>"
         data-name="<?= e($p['name']) ?>"
         data-price="<?= (float)$p['price'] ?>"
         data-stock="<?= (int)$p['stock_qty'] ?>"
         data-category="<?= e($p['category_label']) ?>"
         data-description="<?= e($p['description'] ?? 'No description available.') ?>"
         data-image="<?= $p['image_url'] ? BASE_URL . e($p['image_url']) : '' ?>"
         data-sku="<?= e($p['sku']) ?>"
         data-specs="<?= e(json_encode($specs_map[(int)$p['id']] ?? [])) ?>">
        <?php if ($p['image_url']): ?>
        <img src="<?= BASE_URL . e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" style="width:100%;height:140px;object-fit:contain;margin-bottom:12px;border-radius:8px;background:var(--bg-input);">
        <?php endif; ?>
        <span class="badge badge-primary" style="margin-bottom:8px;"><?= e($p['category_label']) ?></span>
        <h3 style="font-size:0.95rem;font-weight:600;margin-bottom:4px;"><?= e($p['name']) ?></h3>
        <p class="text-muted" style="font-size:0.8rem;margin-bottom:12px;"><?= e(substr($p['description'] ?? '', 0, 80)) ?></p>
        <div class="d-flex justify-between align-center">
            <span style="font-size:1.1rem;font-weight:700;color:var(--brand-primary-light);"><?= money((float)$p['price']) ?></span>
            <span class="badge badge-success"><?= (int)$p['stock_qty'] ?> in stock</span>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Product Detail Modal -->
<div class="modal-backdrop" id="catalogProductModal">
    <div class="modal" style="max-width:600px;">
        <div class="modal-header">
            <h3 id="modalProductName">Product</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeCatalogModal()" style="font-size:1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" style="padding:24px;">
            <div style="text-align:center;margin-bottom:20px;">
                <img id="modalProductImage" src="" alt="" style="max-height:200px;max-width:100%;object-fit:contain;border-radius:8px;background:var(--bg-input);padding:12px;">
            </div>
            <div class="d-flex justify-between align-center" style="margin-bottom:16px;">
                <span class="badge badge-primary" id="modalProductCategory"></span>
                <span class="badge badge-success" id="modalProductStock"></span>
            </div>
            <div style="margin-bottom:16px;">
                <span style="font-size:0.75rem;color:var(--text-muted);font-family:var(--font-mono);" id="modalProductSku"></span>
            </div>
            <p style="font-size:0.9rem;color:var(--text-secondary);line-height:1.6;margin-bottom:20px;" id="modalProductDescription"></p>
            <div id="modalProductSpecs" style="margin-bottom:20px;"></div>
            <div style="padding-top:16px;border-top:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:1.5rem;font-weight:800;color:var(--brand-primary-light);" id="modalProductPrice"></span>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('catalogProductModal');
    var cards = document.querySelectorAll('.catalog-product-card');

    cards.forEach(function(card) {
        card.addEventListener('click', function() {
            var name = card.dataset.name;
            var price = parseFloat(card.dataset.price);
            var stock = parseInt(card.dataset.stock);
            var category = card.dataset.category;
            var description = card.dataset.description;
            var image = card.dataset.image;
            var sku = card.dataset.sku;
            var specs = JSON.parse(card.dataset.specs);

            document.getElementById('modalProductName').textContent = name;
            document.getElementById('modalProductCategory').textContent = category;
            document.getElementById('modalProductStock').textContent = stock + ' in stock';
            document.getElementById('modalProductSku').textContent = 'SKU: ' + sku;
            document.getElementById('modalProductDescription').textContent = description;
            document.getElementById('modalProductPrice').textContent = '<?= DEFAULT_CURRENCY ?>' + price.toLocaleString(undefined, {minimumFractionDigits:2});

            var imgEl = document.getElementById('modalProductImage');
            if (image) {
                imgEl.src = image;
                imgEl.style.display = 'block';
            } else {
                imgEl.style.display = 'none';
            }

            var specsHtml = '';
            if (specs.length > 0) {
                specsHtml = '<div style="font-size:0.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;">Specifications</div>';
                specsHtml += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
                specs.forEach(function(s) {
                    specsHtml += '<div style="background:var(--bg-input);padding:8px 12px;border-radius:6px;border:1px solid var(--border-color);">';
                    specsHtml += '<span style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">' + s.key.replace(/_/g, ' ') + '</span>';
                    specsHtml += '<span style="font-size:0.85rem;font-weight:600;color:var(--text-primary);">' + s.value + '</span>';
                    specsHtml += '</div>';
                });
                specsHtml += '</div>';
            }
            document.getElementById('modalProductSpecs').innerHTML = specsHtml;

            modal.classList.add('active');
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeCatalogModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('active')) closeCatalogModal();
    });
})();

function closeCatalogModal() {
    document.getElementById('catalogProductModal').classList.remove('active');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
