<?php
/**
 * admin/dashboard.php — Admin dashboard with chart visualizations.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin', 'superadmin');

// ─── Fetch KPI data ───────────────────────────────────────────
// Today's sales
$stmt = $mysqli->prepare("SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->execute();
$today_sales = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Pending builds
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM builds WHERE status = 'submitted'");
$stmt->execute();
$pending_builds = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Low stock count
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM products WHERE stock_qty <= low_stock_threshold AND stock_qty > 0 AND is_active = 1");
$stmt->execute();
$low_stock = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Out of stock count
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM products WHERE stock_qty = 0 AND is_active = 1");
$stmt->execute();
$out_of_stock = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total products
$stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM products WHERE is_active = 1");
$stmt->execute();
$total_products = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Well stocked count
$well_stocked = $total_products - $low_stock - $out_of_stock;

// Inventory value
$stmt = $mysqli->prepare("SELECT COALESCE(SUM(cost * stock_qty), 0) as value FROM products WHERE is_active = 1");
$stmt->execute();
$inventory_value = $stmt->get_result()->fetch_assoc()['value'];
$stmt->close();

// Recent orders
$stmt = $mysqli->prepare("SELECT o.receipt_no, o.total, o.created_at, u.name as cashier_name 
    FROM orders o LEFT JOIN users u ON o.cashier_id = u.id 
    WHERE o.status = 'completed' ORDER BY o.created_at DESC LIMIT 5");
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Low stock products
$stmt = $mysqli->prepare("SELECT p.name, p.sku, p.stock_qty, p.low_stock_threshold, c.label as category
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.stock_qty <= p.low_stock_threshold AND p.stock_qty > 0 AND p.is_active = 1 
    ORDER BY p.stock_qty ASC LIMIT 10");
$stmt->execute();
$low_stock_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// This week's revenue
$stmt = $mysqli->prepare("SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as count FROM orders WHERE YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1) AND status = 'completed'");
$stmt->execute();
$week_sales = $stmt->get_result()->fetch_assoc();
$stmt->close();

// This month's revenue
$stmt = $mysqli->prepare("SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as count FROM orders WHERE YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW()) AND status = 'completed'");
$stmt->execute();
$month_sales = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ─── Chart Data: Last 7 days revenue ──────────────────────────
$daily_sales = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $label = date('M j', strtotime("-{$i} days"));
    $stmt = $mysqli->prepare("SELECT COALESCE(SUM(total), 0) as revenue, COUNT(*) as count FROM orders WHERE DATE(created_at) = ? AND status = 'completed'");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $daily_sales[] = ['label' => $label, 'revenue' => (float)$row['revenue'], 'count' => (int)$row['count']];
}

// ─── Chart Data: Inventory by category ────────────────────────
$stmt = $mysqli->prepare("SELECT c.label, SUM(p.stock_qty) as total_stock, COUNT(p.id) as product_count, COALESCE(SUM(p.cost * p.stock_qty), 0) as category_value FROM products p JOIN categories c ON p.category_id = c.id WHERE p.is_active = 1 GROUP BY c.id, c.label ORDER BY c.sort_order");
$stmt->execute();
$inventory_by_category = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Chart Data: Sales by payment method ──────────────────────
$stmt = $mysqli->prepare("SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total), 0) as total FROM orders WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY payment_method");
$stmt->execute();
$sales_by_payment = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── Chart Data: Build statuses ───────────────────────────────
$stmt = $mysqli->prepare("SELECT status, COUNT(*) as count FROM builds GROUP BY status");
$stmt->execute();
$build_statuses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$page_title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="ph ph-squares-four" style="color:var(--brand-primary-light);"></i> Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?= e($user['name']) ?>. Here's your store overview.</p>
    </div>
    <div class="page-actions">
        <a href="<?= BASE_URL ?>cashier/new_sale.php" class="btn btn-primary">
            <i class="ph ph-cash-register"></i> New Sale
        </a>
        <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-secondary">
            <i class="ph ph-package"></i> Products
        </a>
    </div>
</div>

<!-- CHARTS SECTION -->
<div class="dashboard-charts-grid">
    <!-- Revenue Line Chart (7 days) -->
    <div class="chart-card chart-card-wide">
        <div class="chart-card-header">
            <div>
                <h3 class="chart-card-title">Revenue Overview</h3>
                <p class="chart-card-subtitle">Last 7 days performance</p>
            </div>
            <div class="chart-card-kpis">
                <div class="chart-kpi">
                    <span class="chart-kpi-label">Today</span>
                    <span class="chart-kpi-value"><?= money((float)$today_sales['revenue']) ?></span>
                </div>
                <div class="chart-kpi">
                    <span class="chart-kpi-label">This Week</span>
                    <span class="chart-kpi-value"><?= money((float)$week_sales['revenue']) ?></span>
                </div>
                <div class="chart-kpi">
                    <span class="chart-kpi-label">This Month</span>
                    <span class="chart-kpi-value"><?= money((float)$month_sales['revenue']) ?></span>
                </div>
            </div>
        </div>
        <div class="chart-canvas-wrap">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Inventory Donut -->
    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <h3 class="chart-card-title">Inventory by Category</h3>
                <p class="chart-card-subtitle"><?= $total_products ?> active products</p>
            </div>
        </div>
        <div class="chart-canvas-wrap chart-canvas-donut">
            <canvas id="inventoryDonut"></canvas>
        </div>
        <div class="chart-card-footer">
            <span class="chart-footer-stat"><strong><?= money((float)$inventory_value) ?></strong> total value</span>
        </div>
    </div>

    <!-- Stock Health Donut -->
    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <h3 class="chart-card-title">Stock Health</h3>
                <p class="chart-card-subtitle">Product availability status</p>
            </div>
        </div>
        <div class="chart-canvas-wrap chart-canvas-donut">
            <canvas id="stockHealthDonut"></canvas>
        </div>
        <div class="chart-card-footer chart-footer-legend">
            <span class="legend-item"><span class="legend-dot" style="background:#22c55e;"></span> Well Stocked (<?= $well_stocked ?>)</span>
            <span class="legend-item"><span class="legend-dot" style="background:#f59e0b;"></span> Low (<?= $low_stock ?>)</span>
            <span class="legend-item"><span class="legend-dot" style="background:#ef4444;"></span> Out (<?= $out_of_stock ?>)</span>
        </div>
    </div>

    <!-- Payment Methods Donut -->
    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <h3 class="chart-card-title">Payment Methods</h3>
                <p class="chart-card-subtitle">Last 30 days breakdown</p>
            </div>
        </div>
        <div class="chart-canvas-wrap chart-canvas-donut">
            <canvas id="paymentDonut"></canvas>
        </div>
        <div class="chart-card-footer">
            <?php
            $total_payment_count = array_sum(array_column($sales_by_payment, 'count'));
            ?>
            <span class="chart-footer-stat"><strong><?= $total_payment_count ?></strong> total transactions</span>
        </div>
    </div>

    <!-- Builds Status Donut -->
    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <h3 class="chart-card-title">Build Orders</h3>
                <p class="chart-card-subtitle">All-time status breakdown</p>
            </div>
        </div>
        <div class="chart-canvas-wrap chart-canvas-donut">
            <canvas id="buildsDonut"></canvas>
        </div>
        <div class="chart-card-footer">
            <span class="chart-footer-stat"><strong><?= $pending_builds ?></strong> pending pickup</span>
        </div>
    </div>

    <!-- Transactions Bar Chart (7 days) -->
    <div class="chart-card chart-card-wide">
        <div class="chart-card-header">
            <div>
                <h3 class="chart-card-title">Daily Transactions</h3>
                <p class="chart-card-subtitle">Number of sales per day (last 7 days)</p>
            </div>
            <div class="chart-card-kpis">
                <div class="chart-kpi">
                    <span class="chart-kpi-label">Today</span>
                    <span class="chart-kpi-value"><?= (int)$today_sales['count'] ?> sales</span>
                </div>
                <div class="chart-kpi">
                    <span class="chart-kpi-label">Week Total</span>
                    <span class="chart-kpi-value"><?= (int)$week_sales['count'] ?> sales</span>
                </div>
            </div>
        </div>
        <div class="chart-canvas-wrap">
            <canvas id="transactionsChart"></canvas>
        </div>
    </div>
</div>

<!-- CONTENT GRID: Recent Sales & Low Stock (kept as-is) -->
<div class="grid-2 mt-4" style="align-items:flex-start;">
    <!-- Recent Sales -->
    <div class="card">
        <div class="card-header">
            <div><h3><i class="ph ph-receipt"></i> Recent Sales</h3></div>
            <a href="<?= BASE_URL ?>admin/orders.php" class="btn btn-ghost btn-sm">View All →</a>
        </div>
        <?php if (empty($recent_orders)): ?>
        <div class="empty-state">
            <i class="ph ph-receipt"></i>
            <p>No sales yet today.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Receipt</th><th>Cashier</th><th class="text-right">Amount</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($recent_orders as $order): ?>
                <tr>
                    <td class="text-mono"><?= e($order['receipt_no']) ?></td>
                    <td><?= e($order['cashier_name'] ?? 'System') ?></td>
                    <td class="text-right text-bold"><?= money((float)$order['total']) ?></td>
                    <td class="text-muted"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Low Stock -->
    <div class="card">
        <div class="card-header">
            <div><h3><i class="ph ph-warning text-warning"></i> Low Stock Items</h3></div>
            <a href="<?= BASE_URL ?>admin/products.php" class="btn btn-ghost btn-sm">View All →</a>
        </div>
        <?php if (empty($low_stock_items)): ?>
        <div class="empty-state">
            <i class="ph ph-check-circle"></i>
            <p>All products are well stocked.</p>
        </div>
        <?php else: ?>
        <div class="table-wrapper" style="border:none;">
            <table class="data-table">
                <thead><tr><th>Product</th><th class="text-center">Stock</th><th class="text-center">Threshold</th></tr></thead>
                <tbody>
                <?php foreach ($low_stock_items as $item): ?>
                <tr>
                    <td>
                        <div class="text-bold"><?= e($item['name']) ?></div>
                        <div class="text-muted text-mono" style="font-size:0.75rem;"><?= e($item['sku']) ?></div>
                    </td>
                    <td class="text-center"><span class="badge badge-warning"><?= (int)$item['stock_qty'] ?></span></td>
                    <td class="text-center text-muted"><?= (int)$item['low_stock_threshold'] ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    // ─── Theme-aware colors ───────────────────────────────────
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    const gridColor = isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    const textColor = isDark ? '#94a3b8' : '#64748b';
    const tooltipBg = isDark ? '#1a1d27' : '#ffffff';
    const tooltipText = isDark ? '#f1f5f9' : '#0f172a';
    const tooltipBorder = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    // Chart.js global defaults
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.color = textColor;
    Chart.defaults.plugins.tooltip.backgroundColor = tooltipBg;
    Chart.defaults.plugins.tooltip.titleColor = tooltipText;
    Chart.defaults.plugins.tooltip.bodyColor = tooltipText;
    Chart.defaults.plugins.tooltip.borderColor = tooltipBorder;
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
    Chart.defaults.plugins.legend.labels.padding = 16;

    // ─── Revenue Line Chart ───────────────────────────────────
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueGradient = revenueCtx.createLinearGradient(0, 0, 0, 250);
    revenueGradient.addColorStop(0, 'rgba(99, 102, 241, 0.25)');
    revenueGradient.addColorStop(1, 'rgba(99, 102, 241, 0.0)');

    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($daily_sales, 'label')) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_column($daily_sales, 'revenue')) ?>,
                borderColor: '#6366f1',
                backgroundColor: revenueGradient,
                borderWidth: 2.5,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(ctx) { return '<?= DEFAULT_CURRENCY ?>' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2}); }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor } },
                y: {
                    grid: { color: gridColor },
                    ticks: {
                        color: textColor,
                        callback: function(v) { return '<?= DEFAULT_CURRENCY ?>' + (v >= 1000 ? (v/1000).toFixed(0) + 'k' : v); }
                    }
                }
            }
        }
    });

    // ─── Transactions Bar Chart ───────────────────────────────
    const txCtx = document.getElementById('transactionsChart').getContext('2d');
    new Chart(txCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($daily_sales, 'label')) ?>,
            datasets: [{
                label: 'Transactions',
                data: <?= json_encode(array_column($daily_sales, 'count')) ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.7)',
                borderColor: '#6366f1',
                borderWidth: 1,
                borderRadius: 6,
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: textColor } },
                y: {
                    grid: { color: gridColor },
                    ticks: { color: textColor, stepSize: 1 },
                    beginAtZero: true
                }
            }
        }
    });

    // ─── Inventory Donut ──────────────────────────────────────
    const invColors = ['#6366f1','#8b5cf6','#3b82f6','#22c55e','#f59e0b','#ef4444','#14b8a6','#ec4899'];
    new Chart(document.getElementById('inventoryDonut'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_column($inventory_by_category, 'label')) ?>,
            datasets: [{
                data: <?= json_encode(array_map('intval', array_column($inventory_by_category, 'total_stock'))) ?>,
                backgroundColor: invColors.slice(0, <?= count($inventory_by_category) ?>),
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 12 } }
            }
        }
    });

    // ─── Stock Health Donut ───────────────────────────────────
    new Chart(document.getElementById('stockHealthDonut'), {
        type: 'doughnut',
        data: {
            labels: ['Well Stocked', 'Low Stock', 'Out of Stock'],
            datasets: [{
                data: [<?= $well_stocked ?>, <?= $low_stock ?>, <?= $out_of_stock ?>],
                backgroundColor: ['#22c55e', '#f59e0b', '#ef4444'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false }
            }
        }
    });

    // ─── Payment Methods Donut ────────────────────────────────
    const paymentData = <?= json_encode($sales_by_payment) ?>;
    const paymentLabels = paymentData.map(function(d) {
        return d.payment_method.charAt(0).toUpperCase() + d.payment_method.slice(1);
    });
    const paymentValues = paymentData.map(function(d) { return parseInt(d.count); });
    const paymentColors = ['#6366f1', '#22c55e', '#f59e0b', '#3b82f6', '#ec4899'];

    new Chart(document.getElementById('paymentDonut'), {
        type: 'doughnut',
        data: {
            labels: paymentLabels.length ? paymentLabels : ['No Data'],
            datasets: [{
                data: paymentValues.length ? paymentValues : [1],
                backgroundColor: paymentValues.length ? paymentColors.slice(0, paymentValues.length) : ['rgba(100,100,100,0.2)'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 12 } }
            }
        }
    });

    // ─── Builds Status Donut ──────────────────────────────────
    const buildData = <?= json_encode($build_statuses) ?>;
    const buildLabels = buildData.map(function(d) {
        return d.status.charAt(0).toUpperCase() + d.status.slice(1);
    });
    const buildValues = buildData.map(function(d) { return parseInt(d.count); });
    const buildColors = { 'Submitted': '#f59e0b', 'Paid': '#22c55e', 'Expired': '#64748b', 'Cancelled': '#ef4444' };
    const buildBgColors = buildLabels.map(function(l) { return buildColors[l] || '#6366f1'; });

    new Chart(document.getElementById('buildsDonut'), {
        type: 'doughnut',
        data: {
            labels: buildLabels.length ? buildLabels : ['No Builds'],
            datasets: [{
                data: buildValues.length ? buildValues : [1],
                backgroundColor: buildValues.length ? buildBgColors : ['rgba(100,100,100,0.2)'],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 10 }, padding: 12 } }
            }
        }
    });
})();
</script>

<style>
/* ── Dashboard Charts Grid ─────────────────────────────────── */
.dashboard-charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.chart-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius-xl);
    padding: 24px;
    transition: all var(--transition-base);
}

.chart-card:hover {
    border-color: var(--border-color-light);
    box-shadow: var(--shadow-md);
}

.chart-card-wide {
    grid-column: span 2;
}

.chart-card-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 12px;
    flex-wrap: wrap;
}

.chart-card-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 2px;
}

.chart-card-subtitle {
    font-size: 0.78rem;
    color: var(--text-muted);
}

.chart-card-kpis {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.chart-kpi {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.chart-kpi-label {
    font-size: 0.65rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.chart-kpi-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-primary);
}

.chart-canvas-wrap {
    position: relative;
    height: 220px;
}

.chart-canvas-donut {
    height: 200px;
}

.chart-card-footer {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--border-color);
    text-align: center;
}

.chart-footer-stat {
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.chart-footer-stat strong {
    color: var(--text-primary);
}

.chart-footer-legend {
    display: flex;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    color: var(--text-secondary);
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 900px) {
    .dashboard-charts-grid {
        grid-template-columns: 1fr;
    }
    .chart-card-wide {
        grid-column: span 1;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
