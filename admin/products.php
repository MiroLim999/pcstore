<?php
/**
 * admin/products.php — Product listing with search, filter, pagination.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// ─── Filters ──────────────────────────────────────────────────
$search      = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$page        = max(1, (int)($_GET['page'] ?? 1));
$per_page    = 15;
$offset      = ($page - 1) * $per_page;

// Build query
$where = "WHERE p.is_active = 1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= " AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}
if ($category_id > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

// Count total
$count_sql = "SELECT COUNT(*) as total FROM products p {$where}";
$stmt = $mysqli->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = max(1, ceil($total / $per_page));

// Fetch products
$sql = "SELECT p.*, c.label as category_label 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        {$where} 
        ORDER BY p.name ASC 
        LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Categories for filter dropdown
$categories = $mysqli->query("SELECT id, label FROM categories ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Products';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-package" style="color:var(--brand-primary-light);"></i> Products</h1>
        <p class="page-subtitle"><?= $total ?> products found</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>admin/product_form.php" class="btn btn-primary">
            <i class="ph ph-plus"></i> Add Product
        </a>
    </div>
</div>

<!-- FILTER BAR -->
<form class="filter-bar" method="GET" action="">
    <div class="form-group">
        <label class="form-label">Search</label>
        <input type="text" name="search" class="form-control" placeholder="Name, SKU, or barcode..." value="<?= e($search) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Category</label>
        <select name="category" class="form-control">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-secondary"><i class="ph ph-funnel"></i> Filter</button>
    </div>
</form>

<!-- PRODUCTS TABLE -->
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-right">Price</th>
                    <th class="text-right">Cost</th>
                    <th class="text-center">Stock</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($products)): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:40px;">No products found.</td></tr>
            <?php else: ?>
                <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div class="text-bold"><?= e($p['name']) ?></div>
                    </td>
                    <td class="text-mono"><?= e($p['sku']) ?></td>
                    <td><span class="badge badge-primary"><?= e($p['category_label'] ?? '') ?></span></td>
                    <td class="text-right text-bold"><?= money((float)$p['price']) ?></td>
                    <td class="text-right text-muted"><?= money((float)$p['cost']) ?></td>
                    <td class="text-center">
                        <?php if ($p['stock_qty'] == 0): ?>
                            <span class="badge badge-danger">Out</span>
                        <?php elseif ($p['stock_qty'] <= $p['low_stock_threshold']): ?>
                            <span class="badge badge-warning"><?= (int)$p['stock_qty'] ?></span>
                        <?php else: ?>
                            <span class="badge badge-success"><?= (int)$p['stock_qty'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <div class="actions">
                            <button type="button" class="btn btn-ghost btn-sm" title="View" onclick="viewProduct(<?= $p['id'] ?>)">
                                <i class="ph ph-eye"></i>
                            </button>
                            <a href="<?= BASE_URL ?>admin/product_form.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" title="Edit">
                                <i class="ph ph-pencil-simple"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_id ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category_id ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category_id ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Product View Modal -->
<div class="modal-backdrop" id="productViewModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 id="pvModalName">Product Details</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeProductView()" style="font-size:1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" id="pvModalBody" style="padding:24px;">
            <div class="text-center text-muted" style="padding:40px;"><div class="spinner" style="margin:0 auto;"></div><p style="margin-top:12px;">Loading...</p></div>
        </div>
    </div>
</div>

<script>
function viewProduct(id) {
    var modal = document.getElementById('productViewModal');
    var body = document.getElementById('pvModalBody');
    document.getElementById('pvModalName').textContent = 'Product Details';
    body.innerHTML = '<div class="text-center text-muted" style="padding:40px;"><div class="spinner" style="margin:0 auto;"></div><p style="margin-top:12px;">Loading...</p></div>';
    modal.classList.add('active');

    fetch('<?= BASE_URL ?>api/product_view.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                body.innerHTML = '<p class="text-danger text-center">Product not found.</p>';
                return;
            }
            var p = data.product;
            document.getElementById('pvModalName').textContent = p.name;

            var html = '';
            if (p.image_url) {
                html += '<div style="text-align:center;margin-bottom:20px;padding:16px;background:var(--bg-input);border-radius:10px;">';
                html += '<img src="' + p.image_url + '" alt="" style="max-height:180px;max-width:100%;object-fit:contain;">';
                html += '</div>';
            }
            html += '<div class="d-flex gap-1 flex-wrap" style="margin-bottom:16px;">';
            html += '<span class="badge badge-primary">' + p.category + '</span>';
            html += '<span class="badge badge-secondary">SKU: ' + p.sku + '</span>';
            if (p.barcode) html += '<span class="badge badge-secondary">Barcode: ' + p.barcode + '</span>';
            html += '</div>';

            html += '<div class="grid-2" style="gap:12px;margin-bottom:16px;">';
            html += '<div style="background:var(--bg-input);padding:12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Selling Price</span><span style="font-size:1.1rem;font-weight:800;color:var(--brand-primary-light);">' + p.price + '</span></div>';
            html += '<div style="background:var(--bg-input);padding:12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Cost</span><span style="font-size:1.1rem;font-weight:700;color:var(--text-secondary);">' + p.cost + '</span></div>';
            html += '<div style="background:var(--bg-input);padding:12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Stock Qty</span><span style="font-size:1.1rem;font-weight:700;color:var(--text-primary);">' + p.stock_qty + '</span></div>';
            html += '<div style="background:var(--bg-input);padding:12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Low Stock Alert</span><span style="font-size:1.1rem;font-weight:700;color:var(--text-primary);">' + p.low_stock_threshold + '</span></div>';
            html += '</div>';

            if (p.description) {
                html += '<div style="margin-bottom:16px;"><span style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;margin-bottom:6px;">Description</span><p style="font-size:0.88rem;color:var(--text-secondary);line-height:1.6;">' + p.description + '</p></div>';
            }

            if (p.specs && p.specs.length > 0) {
                html += '<div style="margin-bottom:12px;"><span style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;margin-bottom:8px;">Specifications</span>';
                html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;">';
                p.specs.forEach(function(s) {
                    html += '<div style="background:var(--bg-input);padding:8px 12px;border-radius:6px;border:1px solid var(--border-color);">';
                    html += '<span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">' + s.key.replace(/_/g, ' ') + '</span>';
                    html += '<span style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">' + s.value + '</span>';
                    html += '</div>';
                });
                html += '</div></div>';
            }

            body.innerHTML = html;
        })
        .catch(function() {
            body.innerHTML = '<p class="text-danger text-center">Failed to load product details.</p>';
        });
}

function closeProductView() {
    document.getElementById('productViewModal').classList.remove('active');
}

document.getElementById('productViewModal').addEventListener('click', function(e) {
    if (e.target === this) closeProductView();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('productViewModal').classList.contains('active')) closeProductView();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
