<?php
/**
 * admin/settings.php — Store settings management.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// ─── Handle save ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $settings_to_save = [
        'store_name'      => trim($_POST['store_name'] ?? ''),
        'store_address'   => trim($_POST['store_address'] ?? ''),
        'currency_symbol' => trim($_POST['currency_symbol'] ?? '₱'),
        'tax_rate'        => (string)(float)($_POST['tax_rate'] ?? '0.12'),
        'reservation_hours' => (string)(int)($_POST['reservation_hours'] ?? '48'),
        'receipt_footer'  => trim($_POST['receipt_footer'] ?? ''),
    ];

    foreach ($settings_to_save as $key => $value) {
        $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->bind_param('sss', $key, $value, $value);
        $stmt->execute();
        $stmt->close();
    }

    flash('Settings saved.', 'success');
    redirect(BASE_URL . 'admin/settings.php');
}

// ─── Load current settings ────────────────────────────────────
$store_name = get_setting('store_name') ?? 'PC Store';
$store_address = get_setting('store_address') ?? '';
$currency_symbol = get_setting('currency_symbol') ?? '₱';
$tax_rate = get_setting('tax_rate') ?? '0.12';
$reservation_hours = get_setting('reservation_hours') ?? '48';
$receipt_footer = get_setting('receipt_footer') ?? '';

$page_title = 'Settings';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-gear" style="color:var(--brand-primary-light);"></i> Settings</h1>
        <p class="page-subtitle">Store configuration</p>
    </div>
</div>

<div class="card" style="max-width:600px;">
    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="form-group">
            <label class="form-label">Store Name</label>
            <input type="text" name="store_name" class="form-control" value="<?= e($store_name) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Store Address</label>
            <input type="text" name="store_address" class="form-control" value="<?= e($store_address) ?>">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Currency Symbol</label>
                <input type="text" name="currency_symbol" class="form-control" value="<?= e($currency_symbol) ?>" maxlength="5" style="width:80px;">
            </div>
            <div class="form-group">
                <label class="form-label">Tax Rate (decimal)</label>
                <input type="number" name="tax_rate" class="form-control" value="<?= e($tax_rate) ?>" step="0.01" min="0" max="1" style="width:120px;">
                <small class="text-muted">e.g. 0.12 = 12%</small>
            </div>
            <div class="form-group">
                <label class="form-label">Reservation Hours</label>
                <input type="number" name="reservation_hours" class="form-control" value="<?= e($reservation_hours) ?>" min="1" max="168" style="width:100px;">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Receipt Footer Message</label>
            <input type="text" name="receipt_footer" class="form-control" value="<?= e($receipt_footer) ?>" placeholder="Thank you for shopping!">
        </div>

        <button type="submit" class="btn btn-primary btn-block"><i class="ph ph-floppy-disk"></i> Save Settings</button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
