<?php
/**
 * admin/product_form.php — Add or Edit a product.
 * ?id=N for edit, blank for add.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

$id = (int)($_GET['id'] ?? 0);
$is_edit = $id > 0;
$errors = [];

// Default values
$product = [
    'name' => '', 'sku' => '', 'barcode' => '', 'category_id' => '',
    'price' => '', 'cost' => '', 'description' => '', 'stock_qty' => 0,
    'low_stock_threshold' => 5, 'is_active' => 1, 'image_url' => '',
];

// Load existing product for edit
if ($is_edit) {
    $stmt = $mysqli->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    $stmt->close();

    if (!$product) {
        flash('Product not found.', 'danger');
        redirect(BASE_URL . 'admin/products.php');
    }
}

// Categories
$categories = $mysqli->query("SELECT id, label FROM categories ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// ─── Handle form submission ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $product['name']        = trim($_POST['name'] ?? '');
    $product['sku']         = trim($_POST['sku'] ?? '');
    $product['barcode']     = trim($_POST['barcode'] ?? '') ?: null;
    $product['category_id'] = (int)($_POST['category_id'] ?? 0);
    $product['price']       = (float)($_POST['price'] ?? 0);
    $product['cost']        = (float)($_POST['cost'] ?? 0);
    $product['description'] = trim($_POST['description'] ?? '');
    $product['low_stock_threshold'] = (int)($_POST['low_stock_threshold'] ?? 5);
    $product['is_active']   = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($product['name'])) $errors[] = 'Product name is required.';
    if (empty($product['sku'])) $errors[] = 'SKU is required.';
    if ($product['category_id'] < 1) $errors[] = 'Category is required.';
    if ($product['price'] < 0) $errors[] = 'Price cannot be negative.';

    // Check SKU uniqueness
    if (empty($errors)) {
        $sku_check = $mysqli->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
        $sku_check->bind_param('si', $product['sku'], $id);
        $sku_check->execute();
        $sku_check->store_result();
        if ($sku_check->num_rows > 0) {
            $errors[] = 'SKU already exists.';
        }
        $sku_check->close();
    }

    // Handle image upload
    if (empty($errors) && !empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, ALLOWED_IMAGE_TYPES)) {
                $errors[] = 'Image must be JPG, PNG, or WebP.';
            } elseif ($file['size'] > MAX_IMAGE_SIZE) {
                $errors[] = 'Image must be under 5MB.';
            } else {
                $filename = sanitize_filename($file['name']);
                $dest = UPLOADS_PATH . $filename;
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $product['image_url'] = 'uploads/' . $filename;
                }
            }
        }
    }

    // Save
    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $mysqli->prepare("UPDATE products SET name=?, sku=?, barcode=?, category_id=?, price=?, cost=?, description=?, low_stock_threshold=?, is_active=?, image_url=COALESCE(?, image_url) WHERE id=?");
            $img = !empty($product['image_url']) ? $product['image_url'] : null;
            $stmt->bind_param('sssiddsiisi',
                $product['name'], $product['sku'], $product['barcode'],
                $product['category_id'], $product['price'], $product['cost'],
                $product['description'], $product['low_stock_threshold'],
                $product['is_active'], $img, $id
            );
            $stmt->execute();
            $stmt->close();

            flash('Product updated successfully.', 'success');
        } else {
            $stmt = $mysqli->prepare("INSERT INTO products (name, sku, barcode, category_id, price, cost, description, low_stock_threshold, is_active, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $img = $product['image_url'] ?: null;
            $stmt->bind_param('sssiddsiis',
                $product['name'], $product['sku'], $product['barcode'],
                $product['category_id'], $product['price'], $product['cost'],
                $product['description'], $product['low_stock_threshold'],
                $product['is_active'], $img
            );
            $stmt->execute();
            $id = $stmt->insert_id;
            $stmt->close();

            flash('Product created successfully.', 'success');
        }
        redirect(BASE_URL . 'admin/products.php');
    }
}

$page_title = $is_edit ? 'Edit Product' : 'Add Product';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-<?= $is_edit ? 'pencil-simple' : 'plus' ?>" style="color:var(--brand-primary-light);"></i> <?= e($page_title) ?></h1>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back</a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <span class="alert-icon"><i class="ph ph-warning-circle"></i></span>
    <span class="alert-content">
        <?php foreach ($errors as $err): ?>
            <?= e($err) ?><br>
        <?php endforeach; ?>
    </span>
</div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Product Name <span class="required">*</span></label>
                <input type="text" name="name" class="form-control" required value="<?= e($product['name']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Category <span class="required">*</span></label>
                <select name="category_id" class="form-control" required>
                    <option value="">Select category...</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $product['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= e($cat['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">SKU <span class="required">*</span></label>
                <input type="text" name="sku" class="form-control" required value="<?= e($product['sku']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Barcode</label>
                <input type="text" name="barcode" class="form-control" value="<?= e($product['barcode'] ?? '') ?>">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Selling Price (<?= e(DEFAULT_CURRENCY) ?>) <span class="required">*</span></label>
                <input type="number" name="price" class="form-control" step="0.01" min="0" required value="<?= e($product['price']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Cost Price (<?= e(DEFAULT_CURRENCY) ?>)</label>
                <input type="number" name="cost" class="form-control" step="0.01" min="0" value="<?= e($product['cost']) ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Low Stock Threshold</label>
                <input type="number" name="low_stock_threshold" class="form-control" min="0" value="<?= (int)$product['low_stock_threshold'] ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="3"><?= e($product['description'] ?? '') ?></textarea>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Product Image</label>
                <input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                <?php if (!empty($product['image_url'])): ?>
                <p class="form-text">Current: <?= e($product['image_url']) ?></p>
                <?php endif; ?>
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:8px;padding-top:24px;">
                <input type="checkbox" name="is_active" id="is_active" value="1" <?= $product['is_active'] ? 'checked' : '' ?>>
                <label for="is_active" class="form-label" style="margin:0;">Active (visible in catalog)</label>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="ph ph-floppy-disk"></i> <?= $is_edit ? 'Update Product' : 'Create Product' ?>
            </button>
            <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
