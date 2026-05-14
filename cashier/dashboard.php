<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_role('cashier', 'admin', 'superadmin');

// Pending builds queue
$stmt = $mysqli->prepare("SELECT b.*, u.name as client_name, u.phone as client_phone FROM builds b LEFT JOIN users u ON b.user_id = u.id WHERE b.status = 'submitted' ORDER BY b.created_at DESC");
$stmt->execute();
$pending_builds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch build items for all pending builds
$build_items_map = [];
if (!empty($pending_builds)) {
    $build_ids = array_column($pending_builds, 'id');
    $placeholders = implode(',', array_fill(0, count($build_ids), '?'));
    $id_types = str_repeat('i', count($build_ids));
    $stmt = $mysqli->prepare("SELECT bi.*, p.name as product_name, p.sku, p.image_url, c.label as category_label 
        FROM build_items bi 
        JOIN products p ON bi.product_id = p.id 
        JOIN categories c ON p.category_id = c.id 
        WHERE bi.build_id IN ({$placeholders}) 
        ORDER BY c.sort_order");
    $stmt->bind_param($id_types, ...$build_ids);
    $stmt->execute();
    $all_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($all_items as $item) {
        $build_items_map[(int)$item['build_id']][] = $item;
    }
}

// Today's sales count
$stmt = $mysqli->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total),0) as revenue FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->execute();
$today = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'POS Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-cash-register" style="color:var(--brand-primary-light);"></i> POS Dashboard</h1>
        <p class="page-subtitle">Today: <?= (int)$today['cnt'] ?> sales · <?= money((float)$today['revenue']) ?></p>
    </div>
    <div class="page-actions" style="display:flex;gap:10px;align-items:center;">
        <a href="<?= BASE_URL ?>cashier/new_sale.php" class="btn btn-primary"><i class="ph ph-plus"></i> New Sale</a>
        <a href="<?= BASE_URL ?>cashier/lookup_build.php" class="btn btn-secondary"><i class="ph ph-magnifying-glass"></i> Lookup Build</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><h3><i class="ph ph-queue"></i> Pending Build Pickups</h3></div>
    <?php if (empty($pending_builds)): ?>
    <div class="empty-state"><i class="ph ph-check-circle"></i><p>No pending builds in queue.</p></div>
    <?php else: ?>
    <div class="table-wrapper" style="border:none;">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Client</th><th class="text-right">Total</th><th>Submitted</th><th>Expires</th><th class="text-right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($pending_builds as $b): ?>
            <tr>
                <td class="text-mono text-bold"><?= e($b['pickup_code']) ?></td>
                <td><?= e($b['client_name'] ?? 'Unknown') ?></td>
                <td class="text-right text-bold"><?= money((float)$b['total_price']) ?></td>
                <td class="text-muted"><?= date('M j, g:i A', strtotime($b['created_at'])) ?></td>
                <td class="text-muted"><?= $b['reserved_until'] ? date('M j, g:i A', strtotime($b['reserved_until'])) : '—' ?></td>
                <td class="text-right">
                    <div class="d-flex gap-1 align-center" style="justify-content:flex-end;">
                        <button type="button" class="btn btn-sm btn-ghost" title="View Details" onclick="viewBuild(<?= (int)$b['id'] ?>)"><i class="ph ph-eye"></i></button>
                        <a href="<?= BASE_URL ?>cashier/checkout.php?build=<?= $b['id'] ?>" class="btn btn-sm btn-primary"><i class="ph ph-cash-register"></i> Process</a>
                        <form method="POST" action="<?= BASE_URL ?>cashier/cancel_build.php" style="display:inline-flex;" data-confirm="Cancel this build and release stock?" data-confirm-title="Cancel Build" data-confirm-type="danger">
                            <?= csrf_field() ?>
                            <input type="hidden" name="build_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="ph ph-x"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Build Detail Modal -->
<div class="modal-backdrop" id="buildDetailModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 id="buildModalTitle">Build Details</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeBuildModal()" style="font-size:1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" id="buildModalBody" style="padding:24px;"></div>
    </div>
</div>

<script>
var buildsData = <?= json_encode(array_map(function($b) use ($build_items_map) {
    return [
        'id' => (int)$b['id'],
        'pickup_code' => $b['pickup_code'],
        'client_name' => $b['client_name'] ?? 'Unknown',
        'client_phone' => $b['client_phone'] ?? '',
        'total_price' => (float)$b['total_price'],
        'created_at' => date('M j, Y g:i A', strtotime($b['created_at'])),
        'reserved_until' => $b['reserved_until'] ? date('M j, Y g:i A', strtotime($b['reserved_until'])) : null,
        'items' => array_map(function($item) {
            return [
                'category' => $item['category_label'],
                'name' => $item['product_name'],
                'sku' => $item['sku'],
                'price' => (float)$item['price_snapshot'],
                'image' => $item['image_url'] ? BASE_URL . $item['image_url'] : null,
            ];
        }, $build_items_map[(int)$b['id']] ?? [])
    ];
}, $pending_builds), JSON_UNESCAPED_UNICODE) ?>;

function viewBuild(buildId) {
    var build = buildsData.find(function(b) { return b.id === buildId; });
    if (!build) return;

    var modal = document.getElementById('buildDetailModal');
    document.getElementById('buildModalTitle').textContent = 'Build #' + build.pickup_code;

    var html = '';

    // Header with pickup code and client info
    html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding:16px;background:var(--bg-input);border-radius:10px;border:1px solid var(--border-color);">';
    html += '<div>';
    html += '<div style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Pickup Code</div>';
    html += '<div style="font-size:1.4rem;font-weight:800;letter-spacing:3px;color:var(--brand-primary-light);">' + build.pickup_code + '</div>';
    html += '</div>';
    html += '<div style="text-align:right;">';
    html += '<div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">' + build.client_name + '</div>';
    if (build.client_phone) html += '<div style="font-size:0.75rem;color:var(--text-muted);">' + build.client_phone + '</div>';
    html += '</div>';
    html += '</div>';

    // Meta
    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">';
    html += '<div style="background:var(--bg-input);padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);">';
    html += '<span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Submitted</span>';
    html += '<span style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">' + build.created_at + '</span>';
    html += '</div>';
    html += '<div style="background:var(--bg-input);padding:10px 14px;border-radius:8px;border:1px solid var(--border-color);">';
    html += '<span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Expires</span>';
    html += '<span style="font-size:0.82rem;font-weight:600;color:var(--text-primary);">' + (build.reserved_until || '—') + '</span>';
    html += '</div>';
    html += '</div>';

    // Components
    html += '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Components (' + build.items.length + ')</div>';
    html += '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;">';
    build.items.forEach(function(item) {
        html += '<div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg-input);border-radius:8px;border:1px solid var(--border-color);">';
        if (item.image) {
            html += '<img src="' + item.image + '" alt="" style="width:44px;height:44px;object-fit:contain;border-radius:6px;background:var(--bg-card);flex-shrink:0;">';
        } else {
            html += '<div style="width:44px;height:44px;border-radius:6px;background:var(--bg-card);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted);font-size:1.2rem;"><i class="ph ph-package"></i></div>';
        }
        html += '<div style="flex:1;min-width:0;">';
        html += '<div style="font-size:0.6rem;font-weight:700;color:var(--brand-primary-light);text-transform:uppercase;letter-spacing:0.3px;">' + item.category + '</div>';
        html += '<div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + item.name + '</div>';
        html += '<div style="font-size:0.65rem;color:var(--text-muted);font-family:var(--font-mono);">' + item.sku + '</div>';
        html += '</div>';
        html += '<div style="font-size:0.88rem;font-weight:700;color:var(--text-primary);flex-shrink:0;"><?= DEFAULT_CURRENCY ?>' + item.price.toLocaleString(undefined, {minimumFractionDigits:2}) + '</div>';
        html += '</div>';
    });
    html += '</div>';

    // Total
    html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:10px;">';
    html += '<span style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);">Total Build Price</span>';
    html += '<span style="font-size:1.3rem;font-weight:800;color:var(--brand-primary-light);"><?= DEFAULT_CURRENCY ?>' + build.total_price.toLocaleString(undefined, {minimumFractionDigits:2}) + '</span>';
    html += '</div>';

    document.getElementById('buildModalBody').innerHTML = html;
    modal.classList.add('active');
}

function closeBuildModal() {
    document.getElementById('buildDetailModal').classList.remove('active');
}

document.getElementById('buildDetailModal').addEventListener('click', function(e) {
    if (e.target === this) closeBuildModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('buildDetailModal').classList.contains('active')) closeBuildModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
