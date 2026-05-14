<?php
/**
 * admin/builds.php — View and manage submitted builds.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// Filters
$status_filter = $_GET['status'] ?? '';

$where = "1=1";
$params = [];
$types = '';

if ($status_filter !== '') {
    $where .= " AND b.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$sql = "SELECT b.*, u.name as client_name, u.email as client_email, u.phone as client_phone,
        (SELECT COUNT(*) FROM build_items WHERE build_id = b.id) as item_count
    FROM builds b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE {$where}
    ORDER BY b.created_at DESC
    LIMIT 100";

$stmt = $mysqli->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$builds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Builds';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-cpu" style="color:var(--brand-primary-light);"></i> Submitted Builds</h1>
        <p class="page-subtitle"><?= count($builds) ?> builds</p>
    </div>
</div>

<!-- Filters -->
<form class="filter-bar" method="GET" action="" style="margin-bottom:20px;">
    <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
            <option value="">All</option>
            <option value="submitted" <?= $status_filter === 'submitted' ? 'selected' : '' ?>>Submitted</option>
            <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary"><i class="ph ph-funnel"></i> Filter</button>
    </div>
</form>

<div class="card">
    <?php if (empty($builds)): ?>
    <div class="empty-state"><i class="ph ph-cpu"></i><p>No builds found.</p></div>
    <?php else: ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pickup Code</th>
                    <th>Client</th>
                    <th class="text-center">Items</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Status</th>
                    <th>Submitted</th>
                    <th>Expires</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($builds as $b): ?>
            <tr>
                <td class="text-mono text-bold"><?= e($b['pickup_code']) ?></td>
                <td>
                    <div class="text-bold"><?= e($b['client_name'] ?? 'Unknown') ?></div>
                    <div class="text-muted" style="font-size:0.75rem;"><?= e($b['client_email'] ?? '') ?></div>
                </td>
                <td class="text-center"><?= (int)$b['item_count'] ?></td>
                <td class="text-right text-bold"><?= money((float)$b['total_price']) ?></td>
                <td class="text-center">
                    <?php
                    $badge = match($b['status']) {
                        'submitted' => 'badge-info',
                        'paid'      => 'badge-success',
                        'expired'   => 'badge-warning',
                        'cancelled' => 'badge-danger',
                        default     => 'badge-secondary',
                    };
                    ?>
                    <span class="badge <?= $badge ?>"><?= e(ucfirst($b['status'])) ?></span>
                </td>
                <td class="text-muted"><?= date('M j, Y g:i A', strtotime($b['created_at'])) ?></td>
                <td class="text-muted"><?= $b['reserved_until'] ? date('M j, g:i A', strtotime($b['reserved_until'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
