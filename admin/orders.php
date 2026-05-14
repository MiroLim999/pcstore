<?php
/**
 * admin/orders.php — Full order management for admins.
 * All statuses, void capability, detailed view modal.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// Filters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['from'] ?? '';
$date_to = $_GET['to'] ?? '';
$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

// Build query
$where = "WHERE 1=1";
$params = [];
$types = '';

if ($date_from !== '' && $date_to !== '') {
    $where .= " AND DATE(o.created_at) BETWEEN ? AND ?";
    $params[] = $date_from;
    $params[] = $date_to;
    $types .= 'ss';
} elseif ($date_from !== '') {
    $where .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
} elseif ($date_to !== '') {
    $where .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($status_filter !== '') {
    $where .= " AND o.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if ($search !== '') {
    $where .= " AND (o.receipt_no LIKE ? OR o.notes LIKE ? OR u.name LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

// Count
$count_sql = "SELECT COUNT(*) as total FROM orders o LEFT JOIN users u ON o.cashier_id = u.id {$where}";
$stmt = $mysqli->prepare($count_sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$total_pages = max(1, ceil($total / $per_page));

// Fetch orders
$sql = "SELECT o.*, u.name as cashier_name 
    FROM orders o 
    LEFT JOIN users u ON o.cashier_id = u.id 
    {$where} 
    ORDER BY o.created_at DESC 
    LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $mysqli->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Summary stats
$summary_sql = "SELECT COUNT(*) as total_orders, COALESCE(SUM(total),0) as total_revenue FROM orders o LEFT JOIN users u ON o.cashier_id = u.id {$where}";
// Remove the LIMIT/OFFSET params for summary
$summary_params = array_slice($params, 0, -2);
$summary_types = substr($types, 0, -2);
$stmt = $mysqli->prepare($summary_sql);
if ($summary_types) $stmt->bind_param($summary_types, ...$summary_params);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();
$stmt->close();

$page_title = 'Orders';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-receipt" style="color:var(--brand-primary-light);"></i> Orders</h1>
        <p class="page-subtitle"><?= $total ?> orders · <?= money((float)$summary['total_revenue']) ?> total revenue</p>
    </div>
</div>

<!-- Filters -->
<form class="filter-bar" method="GET" action="">
    <div class="form-group">
        <label class="form-label">Search</label>
        <input type="text" name="q" class="form-control" placeholder="Receipt #, customer, or cashier..." value="<?= e($search) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($date_from) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($date_to) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
            <option value="">All</option>
            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="refunded" <?= $status_filter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
            <option value="voided" <?= $status_filter === 'voided' ? 'selected' : '' ?>>Voided</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary"><i class="ph ph-funnel"></i> Filter</button>
        <?php if ($search || $date_from || $date_to || $status_filter): ?>
        <a href="<?= BASE_URL ?>admin/orders.php" class="btn btn-ghost" style="margin-left:4px;">Clear</a>
        <?php endif; ?>
    </div>
</form>

<div class="card">
    <?php if (empty($orders)): ?>
    <div class="empty-state"><i class="ph ph-receipt"></i><p>No orders found.</p></div>
    <?php else: ?>
    <div class="table-wrapper" style="border:none;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Receipt #</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Cashier</th>
                    <th class="text-right">Total</th>
                    <th class="text-center">Status</th>
                    <th>Date</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
                <td class="text-mono text-bold"><?= e($o['receipt_no']) ?></td>
                <td><?= e($o['notes'] ?: 'Walk-in') ?></td>
                <td>
                    <?php if ($o['build_id']): ?>
                    <span class="badge badge-primary">Build</span>
                    <?php else: ?>
                    <span class="badge badge-secondary">Walk-in</span>
                    <?php endif; ?>
                </td>
                <td class="text-muted"><?= e($o['cashier_name'] ?? 'System') ?></td>
                <td class="text-right text-bold"><?= money((float)$o['total']) ?></td>
                <td class="text-center">
                    <?php
                    $badge = match($o['status']) {
                        'completed' => 'badge-success',
                        'refunded'  => 'badge-warning',
                        'voided'    => 'badge-danger',
                        default     => 'badge-secondary',
                    };
                    ?>
                    <span class="badge <?= $badge ?>"><?= e(ucfirst($o['status'])) ?></span>
                </td>
                <td class="text-muted"><?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></td>
                <td class="text-right">
                    <div class="d-flex gap-1" style="justify-content:flex-end;">
                        <button type="button" class="btn btn-ghost btn-sm" title="View Details" onclick="viewOrder(<?= (int)$o['id'] ?>)">
                            <i class="ph ph-eye"></i>
                        </button>
                        <a href="<?= BASE_URL ?>cashier/receipt.php?order=<?= $o['id'] ?>" class="btn btn-ghost btn-sm" title="Print Receipt">
                            <i class="ph ph-printer"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&from=<?= e($date_from) ?>&to=<?= e($date_to) ?>&status=<?= e($status_filter) ?>">← Prev</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&from=<?= e($date_from) ?>&to=<?= e($date_to) ?>&status=<?= e($status_filter) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <a href="?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&from=<?= e($date_from) ?>&to=<?= e($date_to) ?>&status=<?= e($status_filter) ?>">Next →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Order Detail Modal -->
<div class="modal-backdrop" id="orderDetailModal">
    <div class="modal" style="max-width:620px;">
        <div class="modal-header">
            <h3 id="orderModalTitle">Order Details</h3>
            <button class="btn btn-ghost btn-sm" onclick="closeOrderModal()" style="font-size:1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div class="modal-body" id="orderModalBody" style="padding:24px;">
            <div class="text-center text-muted" style="padding:40px;"><div class="spinner" style="margin:0 auto;"></div><p style="margin-top:12px;">Loading...</p></div>
        </div>
    </div>
</div>

<script>
function viewOrder(orderId) {
    var modal = document.getElementById('orderDetailModal');
    var body = document.getElementById('orderModalBody');
    document.getElementById('orderModalTitle').textContent = 'Order Details';
    body.innerHTML = '<div class="text-center text-muted" style="padding:40px;"><div class="spinner" style="margin:0 auto;"></div><p style="margin-top:12px;">Loading...</p></div>';
    modal.classList.add('active');

    fetch('<?= BASE_URL ?>api/order_view.php?id=' + orderId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                body.innerHTML = '<p class="text-danger text-center">Order not found.</p>';
                return;
            }
            var o = data.order;
            document.getElementById('orderModalTitle').textContent = o.receipt_no;

            var statusColors = { completed: '#22c55e', refunded: '#f59e0b', voided: '#ef4444' };
            var statusColor = statusColors[o.status] || '#64748b';

            var html = '';

            // Header
            html += '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding:14px 16px;background:var(--bg-input);border-radius:10px;border:1px solid var(--border-color);">';
            html += '<div>';
            html += '<div style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;">Receipt</div>';
            html += '<div style="font-size:1rem;font-weight:700;color:var(--text-primary);font-family:var(--font-mono);">' + o.receipt_no + '</div>';
            html += '</div>';
            html += '<span style="padding:4px 12px;border-radius:50px;font-size:0.7rem;font-weight:700;text-transform:uppercase;background:' + statusColor + '20;color:' + statusColor + ';">' + o.status.charAt(0).toUpperCase() + o.status.slice(1) + '</span>';
            html += '</div>';

            // Meta
            html += '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:20px;">';
            html += '<div style="background:var(--bg-input);padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Date</span><span style="font-size:0.78rem;font-weight:600;color:var(--text-primary);">' + o.date + '</span></div>';
            html += '<div style="background:var(--bg-input);padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Cashier</span><span style="font-size:0.78rem;font-weight:600;color:var(--text-primary);">' + o.cashier + '</span></div>';
            html += '<div style="background:var(--bg-input);padding:10px 12px;border-radius:8px;border:1px solid var(--border-color);"><span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;">Payment</span><span style="font-size:0.78rem;font-weight:600;color:var(--text-primary);">' + o.payment_method.charAt(0).toUpperCase() + o.payment_method.slice(1) + '</span></div>';
            html += '</div>';

            // Customer
            if (o.notes) {
                html += '<div style="margin-bottom:16px;padding:10px 14px;background:var(--bg-input);border-radius:8px;border:1px solid var(--border-color);font-size:0.82rem;color:var(--text-primary);"><span style="font-size:0.6rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;display:block;margin-bottom:2px;">Customer</span>' + o.notes + '</div>';
            }

            // Items
            html += '<div style="font-size:0.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:10px;">Items (' + o.items.length + ')</div>';
            html += '<div style="display:flex;flex-direction:column;gap:6px;margin-bottom:20px;">';
            o.items.forEach(function(item) {
                html += '<div style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:var(--bg-input);border-radius:8px;border:1px solid var(--border-color);">';
                if (item.image) {
                    html += '<img src="' + item.image + '" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:6px;background:var(--bg-card);flex-shrink:0;">';
                } else {
                    html += '<div style="width:40px;height:40px;border-radius:6px;background:var(--bg-card);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-muted);font-size:1.1rem;"><i class="ph ph-package"></i></div>';
                }
                html += '<div style="flex:1;min-width:0;">';
                html += '<div style="font-size:0.82rem;font-weight:600;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + item.name + '</div>';
                html += '<div style="font-size:0.65rem;color:var(--text-muted);font-family:var(--font-mono);">' + item.sku + ' × ' + item.qty + '</div>';
                html += '</div>';
                html += '<div style="font-size:0.85rem;font-weight:700;color:var(--text-primary);flex-shrink:0;">' + item.line_total + '</div>';
                html += '</div>';
            });
            html += '</div>';

            // Totals
            html += '<div style="padding:14px 16px;background:rgba(99,102,241,0.06);border:1px solid rgba(99,102,241,0.15);border-radius:10px;">';
            html += '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:0.8rem;color:var(--text-secondary);">Subtotal</span><span style="font-size:0.85rem;font-weight:600;color:var(--text-primary);">' + o.subtotal + '</span></div>';
            html += '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:0.8rem;color:var(--text-secondary);">Paid</span><span style="font-size:0.85rem;font-weight:600;color:var(--text-primary);">' + o.paid + '</span></div>';
            html += '<div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:0.8rem;color:var(--text-secondary);">Change</span><span style="font-size:0.85rem;font-weight:600;color:var(--color-success);">' + o.change + '</span></div>';
            html += '<div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px solid var(--border-color);"><span style="font-size:0.9rem;font-weight:700;color:var(--text-primary);">Total</span><span style="font-size:1.1rem;font-weight:800;color:var(--brand-primary-light);">' + o.total + '</span></div>';
            html += '</div>';

            body.innerHTML = html;
        })
        .catch(function() {
            body.innerHTML = '<p class="text-danger text-center">Failed to load order details.</p>';
        });
}

function closeOrderModal() {
    document.getElementById('orderDetailModal').classList.remove('active');
}

document.getElementById('orderDetailModal').addEventListener('click', function(e) {
    if (e.target === this) closeOrderModal();
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && document.getElementById('orderDetailModal').classList.contains('active')) closeOrderModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
