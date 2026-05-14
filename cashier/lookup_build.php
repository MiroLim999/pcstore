<?php
/**
 * cashier/lookup_build.php — Search for a submitted build by pickup code, phone, or name.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('cashier', 'admin', 'superadmin');

$query = trim($_GET['q'] ?? '');
$results = [];

if ($query !== '') {
    // Search by pickup code, client name, or phone
    $sql = "SELECT b.*, u.name as client_name, u.phone as client_phone, u.email as client_email
            FROM builds b
            LEFT JOIN users u ON b.user_id = u.id
            WHERE (b.pickup_code = ? OR u.name LIKE ? OR u.phone LIKE ?)
            AND b.status IN ('submitted', 'paid')
            ORDER BY b.created_at DESC
            LIMIT 20";
    $like = "%{$query}%";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('sss', $query, $like, $like);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$page_title = 'Lookup Build';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-magnifying-glass" style="color:var(--brand-primary-light);"></i> Lookup Build</h1>
        <p class="page-subtitle">Search by pickup code, phone, or client name.</p>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <form method="GET" action="">
        <div class="d-flex gap-1 align-center">
            <div class="input-group flex-1">
                <i class="ph ph-magnifying-glass input-icon"></i>
                <input type="text" name="q" class="form-control" placeholder="Enter 6-digit pickup code, phone, or name..." autofocus value="<?= e($query) ?>">
            </div>
            <button type="submit" class="btn btn-primary"><i class="ph ph-magnifying-glass"></i> Search</button>
        </div>
    </form>
</div>

<?php if ($query !== '' && empty($results)): ?>
<div class="card">
    <div class="empty-state">
        <i class="ph ph-magnifying-glass"></i>
        <p>No builds found for "<?= e($query) ?>".</p>
    </div>
</div>
<?php elseif (!empty($results)): ?>
<div class="card">
    <div class="card-header"><h3><?= count($results) ?> result(s) found</h3></div>
    <div class="table-wrapper" style="border:none;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pickup Code</th>
                    <th>Client</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th class="text-right">Total</th>
                    <th>Submitted</th>
                    <th>Expires</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $b): ?>
            <tr>
                <td class="text-mono text-bold" style="font-size:1.1rem;letter-spacing:2px;"><?= e($b['pickup_code']) ?></td>
                <td><?= e($b['client_name'] ?? 'Unknown') ?></td>
                <td class="text-muted"><?= e($b['client_phone'] ?? '—') ?></td>
                <td>
                    <?php $badge = $b['status'] === 'submitted' ? 'badge-info' : 'badge-success'; ?>
                    <span class="badge <?= $badge ?>"><?= e(ucfirst($b['status'])) ?></span>
                </td>
                <td class="text-right text-bold"><?= money((float)$b['total_price']) ?></td>
                <td class="text-muted"><?= date('M j, g:i A', strtotime($b['created_at'])) ?></td>
                <td class="text-muted">
                    <?php if ($b['reserved_until']): ?>
                        <?php $expired = strtotime($b['reserved_until']) < time(); ?>
                        <span class="<?= $expired ? 'text-danger' : '' ?>"><?= date('M j, g:i A', strtotime($b['reserved_until'])) ?></span>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td class="text-right">
                    <?php if ($b['status'] === 'submitted'): ?>
                    <a href="<?= BASE_URL ?>cashier/checkout.php?build=<?= $b['id'] ?>" class="btn btn-sm btn-primary">
                        <i class="ph ph-cash-register"></i> Process
                    </a>
                    <?php else: ?>
                    <span class="badge badge-success">Paid</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
