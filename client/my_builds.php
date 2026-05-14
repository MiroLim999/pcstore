<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user_id = (int)current_user()['id'];
$stmt = $mysqli->prepare("SELECT * FROM builds WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$builds = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch build items for all builds (batch)
$build_items_map = [];
if (!empty($builds)) {
    $build_ids = array_column($builds, 'id');
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

$page_title = 'My Builds';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-list-checks" style="color:var(--brand-primary-light);"></i> My Builds</h1>
        <p class="page-subtitle"><?= count($builds) ?> builds submitted</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>client/builder.php" class="btn btn-primary"><i class="ph ph-plus"></i> New Build</a>
    </div>
</div>

<?php if (empty($builds)): ?>
<div class="card"><div class="empty-state"><i class="ph ph-cpu"></i><p>You haven't submitted any builds yet. <a href="<?= BASE_URL ?>client/builder.php">Start building!</a></p></div></div>
<?php else: ?>
<div class="card">
    <div class="table-wrapper">
        <table class="data-table">
            <thead><tr><th>Pickup Code</th><th>Status</th><th class="text-right">Total</th><th>Submitted</th><th>Expires</th><th class="text-right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($builds as $b): ?>
            <tr>
                <td class="text-mono text-bold"><?= e($b['pickup_code']) ?></td>
                <td>
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
                <td class="text-right text-bold"><?= money((float)$b['total_price']) ?></td>
                <td class="text-muted"><?= date('M j, Y g:i A', strtotime($b['created_at'])) ?></td>
                <td class="text-muted"><?= $b['reserved_until'] ? date('M j, g:i A', strtotime($b['reserved_until'])) : '—' ?></td>
                <td class="text-right">
                    <button type="button" class="btn btn-ghost btn-sm" title="View Build" onclick="viewBuild(<?= (int)$b['id'] ?>)">
                        <i class="ph ph-eye"></i> View
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Build Detail Modal -->
<div class="modal-backdrop" id="buildDetailModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 id="buildModalTitle">Build Details</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeBuildModal()" style="font-size:1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" id="buildModalBody" style="padding:24px;">
        </div>
    </div>
</div>

<script>
// Build data embedded from PHP
var buildsData = <?= json_encode(array_map(function($b) use ($build_items_map) {
    return [
        'id' => (int)$b['id'],
        'pickup_code' => $b['pickup_code'],
        'status' => $b['status'],
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
}, $builds), JSON_UNESCAPED_UNICODE) ?>;

function viewBuild(buildId) {
    var build = buildsData.find(function(b) { return b.id === buildId; });
    if (!build) return;

    var modal = document.getElementById('buildDetailModal');
    document.getElementById('buildModalTitle').textContent = 'Build #' + build.pickup_code;

    var statusColors = { submitted: '#3b82f6', paid: '#22c55e', expired: '#f59e0b', cancelled: '#ef4444' };
    var statusColor = statusColors[build.status] || '#64748b';

    var html = '';

    // Status & Info Header
    html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding:16px;background:var(--bg-input);border-radius:10px;border:1px solid var(--border-color);">';
    html += '<div>';
    html += '<div style="font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;">Pickup Code</div>';
    html += '<div style="font-size:1.4rem;font-weight:800;letter-spacing:3px;color:var(--brand-primary-light);">' + build.pickup_code + '</div>';
    html += '</div>';
    html += '<div style="text-align:right;">';
    html += '<span style="display:inline-block;padding:4px 12px;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;background:' + statusColor + '20;color:' + statusColor + ';">' + build.status.charAt(0).toUpperCase() + build.status.slice(1) + '</span>';
    html += '</div>';
    html += '</div>';

    // Meta info
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

    // Components list
    html += '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Components (' + build.items.length + ')</div>';

    if (build.items.length === 0) {
        html += '<p style="color:var(--text-muted);font-size:0.85rem;">No items found for this build.</p>';
    } else {
        html += '<div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px;">';
        build.items.forEach(function(item) {
            html += '<div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg-input);border-radius:8px;border:1px solid var(--border-color);">';
            if (item.image) {
                html += '<img src="' + item.image + '" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:6px;background:var(--bg-card);flex-shrink:0;">';
            } else {
                html += '<div style="width:40px;height:40px;border-radius:6px;background:var(--bg-card);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted);font-size:1.1rem;"><i class="ph ph-package"></i></div>';
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
    }

    // Total
    html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:10px;">';
    html += '<span style="font-size:0.85rem;font-weight:600;color:var(--text-secondary);">Total Build Price</span>';
    html += '<span style="font-size:1.3rem;font-weight:800;color:var(--brand-primary-light);"><?= DEFAULT_CURRENCY ?>' + build.total_price.toLocaleString(undefined, {minimumFractionDigits:2}) + '</span>';
    html += '</div>';

    // Instructions based on status
    if (build.status === 'submitted') {
        html += '<div style="margin-top:16px;padding:12px 16px;background:var(--color-info-bg);border:1px solid rgba(59,130,246,0.2);border-radius:8px;font-size:0.82rem;color:var(--color-info);">';
        html += '<strong>Next step:</strong> Present your pickup code <strong>' + build.pickup_code + '</strong> at the counter to complete payment.';
        html += '</div>';
    } else if (build.status === 'paid') {
        html += '<div style="margin-top:16px;padding:12px 16px;background:var(--color-success-bg);border:1px solid rgba(34,197,94,0.2);border-radius:8px;font-size:0.82rem;color:var(--color-success);">';
        html += '<strong>Completed!</strong> This build has been paid and collected.';
        html += '</div>';
    } else if (build.status === 'expired') {
        html += '<div style="margin-top:16px;padding:12px 16px;background:var(--color-warning-bg);border:1px solid rgba(245,158,11,0.2);border-radius:8px;font-size:0.82rem;color:var(--color-warning);">';
        html += '<strong>Expired.</strong> The reservation period has passed. You can submit a new build.';
        html += '</div>';
    } else if (build.status === 'cancelled') {
        html += '<div style="margin-top:16px;padding:12px 16px;background:var(--color-danger-bg);border:1px solid rgba(239,68,68,0.2);border-radius:8px;font-size:0.82rem;color:var(--color-danger);">';
        html += '<strong>Cancelled.</strong> This build was cancelled and stock has been released.';
        html += '</div>';
    }

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
