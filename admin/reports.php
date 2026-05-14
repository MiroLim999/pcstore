<?php
/**
 * admin/reports.php — Daily/weekly/monthly sales reports with CSV export.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// ─── Filters ──────────────────────────────────────────────────
$date_from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['to'] ?? date('Y-m-d');

// ─── CSV Export ───────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="sales_report_' . $date_from . '_to_' . $date_to . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Transactions', 'Revenue', 'Tax', 'Profit', 'Avg Sale']);

    $stmt = $mysqli->prepare("SELECT DATE(o.created_at) as sale_date, COUNT(*) as tx_count, SUM(o.subtotal) as revenue, SUM(o.tax) as tax_total,
        SUM(o.subtotal) - COALESCE(SUM(oi_cost.total_cost), 0) as profit
        FROM orders o
        LEFT JOIN (SELECT order_id, SUM(cost_snapshot * qty) as total_cost FROM order_items GROUP BY order_id) oi_cost ON o.id = oi_cost.order_id
        WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY sale_date ORDER BY sale_date DESC");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $avg = $row['tx_count'] > 0 ? $row['revenue'] / $row['tx_count'] : 0;
        fputcsv($output, [$row['sale_date'], $row['tx_count'], number_format($row['revenue'], 2), number_format($row['tax_total'], 2), number_format($row['profit'], 2), number_format($avg, 2)]);
    }
    fclose($output);
    exit;
}

// ─── Daily breakdown ──────────────────────────────────────────
$stmt = $mysqli->prepare("SELECT DATE(o.created_at) as sale_date, COUNT(*) as tx_count, SUM(o.subtotal) as revenue, SUM(o.tax) as tax_total, SUM(o.total) as total_collected,
    SUM(o.subtotal) - COALESCE(SUM(oi_cost.total_cost), 0) as profit
    FROM orders o
    LEFT JOIN (SELECT order_id, SUM(cost_snapshot * qty) as total_cost FROM order_items GROUP BY order_id) oi_cost ON o.id = oi_cost.order_id
    WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY sale_date ORDER BY sale_date DESC");
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$daily_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Summary totals ──────────────────────────────────────────
$total_revenue = 0; $total_tax = 0; $total_profit = 0; $total_tx = 0;
foreach ($daily_data as $d) {
    $total_revenue += (float)$d['revenue'];
    $total_tax += (float)$d['tax_total'];
    $total_profit += (float)$d['profit'];
    $total_tx += (int)$d['tx_count'];
}
$avg_sale = $total_tx > 0 ? $total_revenue / $total_tx : 0;

// ─── Top selling products ─────────────────────────────────────
$stmt = $mysqli->prepare("SELECT p.name, p.sku, c.label as category, SUM(oi.qty) as total_qty, SUM(oi.price_snapshot * oi.qty) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY oi.product_id ORDER BY total_qty DESC LIMIT 10");
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$top_products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Sales by category ────────────────────────────────────────
$stmt = $mysqli->prepare("SELECT c.label, SUM(oi.qty) as total_qty, SUM(oi.price_snapshot * oi.qty) as total_revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status = 'completed' AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY c.id ORDER BY total_revenue DESC");
$stmt->bind_param('ss', $date_from, $date_to);
$stmt->execute();
$by_category = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Sales Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-chart-bar" style="color:var(--brand-primary-light);"></i> Sales Reports</h1>
        <p class="page-subtitle"><?= date('M j, Y', strtotime($date_from)) ?> — <?= date('M j, Y', strtotime($date_to)) ?></p>
    </div>
    <div class="page-actions">
        <a href="?from=<?= e($date_from) ?>&to=<?= e($date_to) ?>&export=csv" class="btn btn-secondary"><i class="ph ph-file-csv"></i> Export CSV</a>
    </div>
</div>

<!-- Date Filter -->
<form class="filter-bar" method="GET" action="">
    <div class="form-group">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($date_from) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($date_to) ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Quick</label>
        <select onchange="applyQuick(this.value)" class="form-control">
            <option value="">Select...</option>
            <option value="today">Today</option>
            <option value="week">This Week</option>
            <option value="month">This Month</option>
            <option value="30days">Last 30 Days</option>
        </select>
    </div>
    <div class="form-group">
        <button type="submit" class="btn btn-primary"><i class="ph ph-funnel"></i> Apply</button>
    </div>
</form>

<!-- KPI Summary -->
<div class="stats-grid">
    <div class="stat-card sales">
        <div class="stat-icon"><i class="ph ph-receipt"></i></div>
        <div class="stat-info">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-value"><?= money($total_revenue) ?></div>
            <div class="stat-change"><?= $total_tx ?> transactions</div>
        </div>
    </div>
    <div class="stat-card profit">
        <div class="stat-icon"><i class="ph ph-trend-up"></i></div>
        <div class="stat-info">
            <div class="stat-label">Gross Profit</div>
            <div class="stat-value"><?= money($total_profit) ?></div>
            <div class="stat-change"><?= $total_revenue > 0 ? round(($total_profit / $total_revenue) * 100, 1) : 0 ?>% margin</div>
        </div>
    </div>
    <div class="stat-card fund">
        <div class="stat-icon"><i class="ph ph-coins"></i></div>
        <div class="stat-info">
            <div class="stat-label">Tax Collected</div>
            <div class="stat-value"><?= money($total_tax) ?></div>
            <div class="stat-change">VAT</div>
        </div>
    </div>
    <div class="stat-card inventory">
        <div class="stat-icon"><i class="ph ph-chart-line-up"></i></div>
        <div class="stat-info">
            <div class="stat-label">Avg. Per Sale</div>
            <div class="stat-value"><?= money($avg_sale) ?></div>
            <div class="stat-change">Per transaction</div>
        </div>
    </div>
</div>

<div class="grid-2" style="align-items:flex-start;">
    <!-- Daily Breakdown -->
    <div class="card">
        <div class="card-header"><h3><i class="ph ph-calendar"></i> Daily Breakdown</h3></div>
        <?php if (empty($daily_data)): ?>
        <div class="empty-state"><i class="ph ph-chart-bar"></i><p>No sales in this period.</p></div>
        <?php else: ?>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Date</th><th class="text-center">Sales</th><th class="text-right">Revenue</th><th class="text-right">Profit</th></tr></thead>
                <tbody>
                <?php foreach ($daily_data as $d): ?>
                <tr>
                    <td class="text-bold"><?= date('M j, Y (D)', strtotime($d['sale_date'])) ?></td>
                    <td class="text-center"><span class="badge badge-info"><?= (int)$d['tx_count'] ?></span></td>
                    <td class="text-right"><?= money((float)$d['revenue']) ?></td>
                    <td class="text-right text-success"><?= money((float)$d['profit']) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Top Products + By Category -->
    <div>
        <div class="card" style="margin-bottom:20px;">
            <div class="card-header"><h3><i class="ph ph-trophy"></i> Top Selling Products</h3></div>
            <?php if (empty($top_products)): ?>
            <div class="empty-state"><p>No data.</p></div>
            <?php else: ?>
            <div class="table-wrapper" style="border:none;">
                <table class="data-table">
                    <thead><tr><th>Product</th><th class="text-center">Qty</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($top_products as $tp): ?>
                    <tr>
                        <td>
                            <div class="text-bold"><?= e($tp['name']) ?></div>
                            <div class="text-muted" style="font-size:0.7rem;"><?= e($tp['category']) ?></div>
                        </td>
                        <td class="text-center"><?= (int)$tp['total_qty'] ?></td>
                        <td class="text-right text-bold"><?= money((float)$tp['total_revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="ph ph-pie-chart"></i> Sales by Category</h3></div>
            <?php if (empty($by_category)): ?>
            <div class="empty-state"><p>No data.</p></div>
            <?php else: ?>
            <div class="table-wrapper" style="border:none;">
                <table class="data-table">
                    <thead><tr><th>Category</th><th class="text-center">Units</th><th class="text-right">Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($by_category as $bc): ?>
                    <tr>
                        <td><span class="badge badge-primary"><?= e($bc['label']) ?></span></td>
                        <td class="text-center"><?= (int)$bc['total_qty'] ?></td>
                        <td class="text-right text-bold"><?= money((float)$bc['total_revenue']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function applyQuick(val) {
    const today = new Date().toISOString().split('T')[0];
    let from = today;
    if (val === 'today') { from = today; }
    else if (val === 'week') { const d = new Date(); d.setDate(d.getDate() - d.getDay()); from = d.toISOString().split('T')[0]; }
    else if (val === 'month') { from = today.substring(0, 8) + '01'; }
    else if (val === '30days') { const d = new Date(); d.setDate(d.getDate() - 30); from = d.toISOString().split('T')[0]; }
    else return;
    window.location.href = '?from=' + from + '&to=' + today;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
