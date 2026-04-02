<?php
session_start();
include('../includes/db.php');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Date range filters - PROPERLY SANITIZED
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$report_type = $_GET['report_type'] ?? 'overview';

// Validate and sanitize dates
$date_from = date('Y-m-d', strtotime($date_from));
$date_to = date('Y-m-d', strtotime($date_to));

// Prepare statements to prevent SQL injection
$stats = [];

try {
    // Total Orders
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders");
    $stmt->execute();
    $stats['total_orders'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE DATE(order_date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $stats['orders_period'] = $stmt->get_result()->fetch_assoc()['count'];

    // Revenue Statistics
    $stmt = $conn->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE payment_status = 'Completed'");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    $stmt = $conn->prepare("SELECT SUM(amount_paid) as total FROM payments WHERE payment_status = 'Completed' AND DATE(payment_date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $stats['revenue_period'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Subsidies
    $stmt = $conn->prepare("SELECT SUM(subsidy) as total FROM payments WHERE payment_status = 'Completed'");
    $stmt->execute();
    $stats['total_subsidies'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
    
    $stmt = $conn->prepare("SELECT SUM(subsidy) as total FROM payments WHERE payment_status = 'Completed' AND DATE(payment_date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $stats['subsidies_period'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Fertilizers
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM fertilizers");
    $stmt->execute();
    $stats['total_fertilizers'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM fertilizers WHERE stock_remaining <= minimum_stock");
    $stmt->execute();
    $stats['low_stock'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT SUM(stock_remaining * price_per_unit) as total FROM fertilizers");
    $stmt->execute();
    $stats['total_stock_value'] = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

    // Suppliers
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM suppliers");
    $stmt->execute();
    $stats['total_suppliers'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT s.id) as count FROM suppliers s JOIN orders o ON s.id = o.supplier_id WHERE DATE(o.order_date) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $stats['active_suppliers'] = $stmt->get_result()->fetch_assoc()['count'];

    // Certificates
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates");
    $stmt->execute();
    $stats['total_certificates'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificates WHERE status = 'Approved'");
    $stmt->execute();
    $stats['active_certificates'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM certificate_applications WHERE status = 'Pending'");
    $stmt->execute();
    $stats['pending_applications'] = $stmt->get_result()->fetch_assoc()['count'];

    // Deliveries
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries");
    $stmt->execute();
    $stats['total_deliveries'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries WHERE status = 'Delivered'");
    $stmt->execute();
    $stats['delivered'] = $stmt->get_result()->fetch_assoc()['count'];
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM deliveries WHERE status = 'In Transit'");
    $stmt->execute();
    $stats['in_transit'] = $stmt->get_result()->fetch_assoc()['count'];

    // Order Status Breakdown
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM orders WHERE DATE(order_date) BETWEEN ? AND ? GROUP BY status");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $order_status = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Top Fertilizers
    $stmt = $conn->prepare("
        SELECT f.name, f.type, SUM(o.quantity) as total_ordered, SUM(o.total_price) as total_sales
        FROM orders o
        JOIN fertilizers f ON o.fertilizer_id = f.id
        WHERE DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY f.id
        ORDER BY total_ordered DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $top_fertilizers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Top Suppliers
    $stmt = $conn->prepare("
        SELECT s.company_name, u.full_name, COUNT(o.id) as order_count, SUM(o.total_price) as total_spent
        FROM suppliers s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN orders o ON s.id = o.supplier_id AND DATE(o.order_date) BETWEEN ? AND ?
        GROUP BY s.id
        HAVING order_count > 0
        ORDER BY order_count DESC
        LIMIT 5
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $top_suppliers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Monthly Revenue Trend (Last 6 months)
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(payment_date, '%Y-%m') as month, SUM(amount_paid) as revenue
        FROM payments
        WHERE payment_status = 'Completed' AND payment_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute();
    $monthly_revenue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Payment Methods
    $stmt = $conn->prepare("
        SELECT payment_method, COUNT(*) as count, SUM(amount_paid) as total
        FROM payments
        WHERE payment_status = 'Completed' AND DATE(payment_date) BETWEEN ? AND ?
        GROUP BY payment_method
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $payment_methods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Stock Levels
    $stmt = $conn->prepare("
        SELECT name, stock_remaining, minimum_stock, price_per_unit, (stock_remaining * price_per_unit) as stock_value
        FROM fertilizers
        ORDER BY stock_remaining ASC
        LIMIT 10
    ");
    $stmt->execute();
    $stock_levels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Recent Activity Log
    $stmt = $conn->prepare("
        SELECT l.action, l.timestamp, u.full_name
        FROM logs l
        LEFT JOIN users u ON l.user_id = u.id
        WHERE DATE(l.timestamp) BETWEEN ? AND ?
        ORDER BY l.timestamp DESC
        LIMIT 10
    ");
    $stmt->bind_param("ss", $date_from, $date_to);
    $stmt->execute();
    $recent_logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    die("Error generating report: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        body { background: #f7f9f6; }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.primary { border-color: #0d6efd; }
        .stat-card.success { border-color: #28a745; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card.danger { border-color: #dc3545; }
        .stat-card.info { border-color: #17a2b8; }
        .chart-container {
            position: relative;
            height: 300px;
        }
        @media print {
            .no-print { display: none; }
            body { background: white; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h3 class="text-success"><i class="bi bi-graph-up"></i> Reports & Analytics</h3>
            <button class="btn btn-success" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>

        <!-- Date Filter -->
        <div class="card border-0 shadow-sm mb-4 no-print">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="overview" <?= $report_type === 'overview' ? 'selected' : '' ?>>Overview</option>
                            <option value="sales" <?= $report_type === 'sales' ? 'selected' : '' ?>>Sales Report</option>
                            <option value="inventory" <?= $report_type === 'inventory' ? 'selected' : '' ?>>Inventory Report</option>
                            <option value="suppliers" <?= $report_type === 'suppliers' ? 'selected' : '' ?>>Suppliers Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">From Date</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">To Date</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Report Header -->
        <div class="text-center mb-4 d-print-block d-none">
            <h2>Fertilizer Management System</h2>
            <h4>Report: <?= date('F d, Y', strtotime($date_from)) ?> - <?= date('F d, Y', strtotime($date_to)) ?></h4>
            <p class="text-muted">Generated on <?= date('F d, Y H:i:s') ?></p>
        </div>

        <!-- Key Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card primary border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Orders</div>
                                <h4 class="mb-0"><?= number_format($stats['orders_period']) ?></h4>
                                <small class="text-muted">of <?= number_format($stats['total_orders']) ?> total</small>
                            </div>
                            <i class="bi bi-cart-check text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card success border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Revenue (Period)</div>
                                <h4 class="mb-0">MWK <?= number_format($stats['revenue_period'], 0) ?></h4>
                                <small class="text-muted">of MWK <?= number_format($stats['total_revenue'], 0) ?> total</small>
                            </div>
                            <i class="bi bi-currency-dollar text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card warning border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Subsidies Paid</div>
                                <h4 class="mb-0">MWK <?= number_format($stats['subsidies_period'], 0) ?></h4>
                                <small class="text-muted">of MWK <?= number_format($stats['total_subsidies'], 0) ?> total</small>
                            </div>
                            <i class="bi bi-gift text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card info border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Active Suppliers</div>
                                <h4 class="mb-0"><?= number_format($stats['active_suppliers']) ?></h4>
                                <small class="text-muted">of <?= number_format($stats['total_suppliers']) ?> total</small>
                            </div>
                            <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card danger border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Low Stock Items</div>
                                <h4 class="mb-0"><?= number_format($stats['low_stock']) ?></h4>
                                <small class="text-muted">of <?= number_format($stats['total_fertilizers']) ?> fertilizers</small>
                            </div>
                            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card success border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Stock Value</div>
                                <h4 class="mb-0">MWK <?= number_format($stats['total_stock_value'], 0) ?></h4>
                                <small class="text-muted">Total inventory</small>
                            </div>
                            <i class="bi bi-box-seam text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card primary border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Active Certificates</div>
                                <h4 class="mb-0"><?= number_format($stats['active_certificates']) ?></h4>
                                <small class="text-muted">of <?= number_format($stats['total_certificates']) ?> total</small>
                            </div>
                            <i class="bi bi-award text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card warning border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Deliveries</div>
                                <h4 class="mb-0"><?= number_format($stats['delivered']) ?></h4>
                                <small class="text-muted"><?= $stats['in_transit'] ?> in transit</small>
                            </div>
                            <i class="bi bi-truck text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Charts Column -->
            <div class="col-lg-8 mb-4">
                <!-- Monthly Revenue Chart -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Revenue Trend (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Order Status Chart -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Order Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="orderStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Top Fertilizers -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Top 5 Fertilizers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Fertilizer</th>
                                        <th>Type</th>
                                        <th class="text-end">Quantity Ordered</th>
                                        <th class="text-end">Total Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($top_fertilizers)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No data available</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($top_fertilizers as $fert): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($fert['name']) ?></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($fert['type']) ?></span></td>
                                                <td class="text-end"><?= number_format($fert['total_ordered']) ?> units</td>
                                                <td class="text-end">MWK <?= number_format($fert['total_sales'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Top Suppliers -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-people"></i> Top 5 Suppliers</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Company</th>
                                        <th>Contact Person</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($top_suppliers)): ?>
                                        <tr><td colspan="4" class="text-center text-muted">No data available</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($top_suppliers as $supplier): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($supplier['company_name']) ?></td>
                                                <td><?= htmlspecialchars($supplier['full_name']) ?></td>
                                                <td class="text-end"><?= number_format($supplier['order_count']) ?></td>
                                                <td class="text-end">MWK <?= number_format($supplier['total_spent'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Payment Methods -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-credit-card"></i> Payment Methods</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($payment_methods)): ?>
                            <p class="text-muted small mb-0">No payment data available</p>
                        <?php else: ?>
                            <?php foreach ($payment_methods as $method): ?>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($method['payment_method'] ?? 'N/A') ?></div>
                                        <small class="text-muted"><?= number_format($method['count']) ?> transactions</small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold">MWK <?= number_format($method['total'], 0) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Stock Levels -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-box"></i> Stock Levels</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Fertilizer</th>
                                        <th class="text-end">Stock</th>
                                        <th class="text-end">Min</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($stock_levels)): ?>
                                        <tr><td colspan="3" class="text-center text-muted">No stock data</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($stock_levels as $stock): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($stock['name']) ?></td>
                                                <td class="text-end">
                                                    <?php if ($stock['stock_remaining'] <= $stock['minimum_stock']): ?>
                                                        <span class="text-danger fw-bold"><?= $stock['stock_remaining'] ?></span>
                                                    <?php else: ?>
                                                        <?= $stock['stock_remaining'] ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end"><?= $stock['minimum_stock'] ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>


                <!-- Recent Activity -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_logs)): ?>
                            <p class="text-muted small mb-0">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recent_logs as $log): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="small fw-semibold"><?= htmlspecialchars($log['full_name'] ?? 'System') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($log['action']) ?></div>
                                    <div class="small text-muted"><?= date('M d, H:i', strtotime($log['timestamp'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Revenue Trend Chart
const revenueCtx = document.getElementById('revenueChart').getContext('2d');
const revenueChart = new Chart(revenueCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($monthly_revenue, 'month')) ?>,
        datasets: [{
            label: 'Revenue (MWK)',
            data: <?= json_encode(array_column($monthly_revenue, 'revenue')) ?>,
            borderColor: '#28a745',
            backgroundColor: 'rgba(40, 167, 69, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: true }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Order Status Chart
const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
const orderStatusChart = new Chart(orderStatusCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($order_status, 'status')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($order_status, 'count')) ?>,
            backgroundColor: [
                '#0d6efd',
                '#28a745',
                '#ffc107',
                '#dc3545',
                '#6c757d'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>
</body>
</html>