<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get supplier
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    header('Location: profile.php');
    exit();
}

$supplier_id = (int) $supplier['id'];

// Date range filter
$dateFrom = $_GET['from'] ?? date('Y-m-01'); // Default: first of current month
$dateTo = $_GET['to'] ?? date('Y-m-d');

// Order Statistics
$orderStats = $conn->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'Dispatched' THEN 1 ELSE 0 END) as dispatched,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Requested' THEN 1 ELSE 0 END) as requested,
        SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(quantity) as total_quantity,
        SUM(total_price) as total_revenue
    FROM orders 
    WHERE supplier_id = {$supplier_id} 
    AND DATE(order_date) BETWEEN '{$dateFrom}' AND '{$dateTo}'
")->fetch_assoc();

// Payment Statistics
$paymentStats = $conn->query("
    SELECT 
        COUNT(CASE WHEN p.payment_status = 'Completed' THEN 1 END) as paid_count,
        SUM(CASE WHEN p.payment_status = 'Completed' THEN p.amount_paid ELSE 0 END) as total_received,
        SUM(CASE WHEN p.payment_status IS NULL OR p.payment_status = 'Pending' THEN o.total_price ELSE 0 END) as total_pending
    FROM orders o
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.supplier_id = {$supplier_id}
    AND DATE(o.order_date) BETWEEN '{$dateFrom}' AND '{$dateTo}'
")->fetch_assoc();

// Delivery Statistics
$deliveryStats = $conn->query("
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        AVG(TIMESTAMPDIFF(HOUR, 
            (SELECT order_date FROM orders WHERE id = deliveries.order_id),
            delivered_on
        )) as avg_delivery_hours
    FROM deliveries 
    WHERE supplier_id = {$supplier_id}
    AND DATE(last_updated) BETWEEN '{$dateFrom}' AND '{$dateTo}'
")->fetch_assoc();

// Monthly Revenue (last 6 months)
$monthlyRevenue = $conn->query("
    SELECT 
        DATE_FORMAT(order_date, '%Y-%m') as month,
        SUM(total_price) as revenue,
        COUNT(*) as orders
    FROM orders 
    WHERE supplier_id = {$supplier_id}
    AND order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

// Top Products
$topProducts = $conn->query("
    SELECT f.name, f.type, SUM(o.quantity) as total_qty, SUM(o.total_price) as total_revenue
    FROM orders o
    JOIN fertilizers f ON o.fertilizer_id = f.id
    WHERE o.supplier_id = {$supplier_id}
    AND DATE(o.order_date) BETWEEN '{$dateFrom}' AND '{$dateTo}'
    GROUP BY o.fertilizer_id
    ORDER BY total_qty DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Order Status Distribution
$statusDistribution = $conn->query("
    SELECT status, COUNT(*) as count
    FROM orders 
    WHERE supplier_id = {$supplier_id}
    AND DATE(order_date) BETWEEN '{$dateFrom}' AND '{$dateTo}'
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);

// Driver Performance
$driverPerformance = $conn->query("
    SELECT u.full_name, 
           COUNT(d.id) as total_deliveries,
           SUM(CASE WHEN d.status = 'Delivered' THEN 1 ELSE 0 END) as completed
    FROM deliveries d
    JOIN users u ON d.driver_id = u.id
    WHERE d.supplier_id = {$supplier_id}
    GROUP BY d.driver_id
    ORDER BY completed DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Prepare chart data
$revenueLabels = array_map(fn($m) => date('M Y', strtotime($m['month'] . '-01')), $monthlyRevenue);
$revenueData = array_map(fn($m) => $m['revenue'], $monthlyRevenue);
$ordersData = array_map(fn($m) => $m['orders'], $monthlyRevenue);

$statusLabels = array_column($statusDistribution, 'status');
$statusData = array_column($statusDistribution, 'count');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f7f9f6; }
        .stat-card {
            border-radius: 12px;
            border: none;
            border-left: 4px solid;
        }
        .stat-card.primary { border-color: #0d6efd; }
        .stat-card.success { border-color: #28a745; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card.info { border-color: #17a2b8; }
        .chart-card { border-radius: 12px; border: none; }
        .metric-value { font-size: 1.8rem; font-weight: 700; }
        .top-product { border-left: 3px solid #28a745; }
        .progress-thin { height: 8px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-bar-chart-line"></i> Reports & Analytics</h3>
            <button class="btn btn-outline-success" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Report
            </button>
        </div>

        <!-- Date Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from" class="form-control" value="<?= $dateFrom ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to" class="form-control" value="<?= $dateTo ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="bi bi-filter"></i> Apply Filter
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card primary shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Total Orders</div>
                        <div class="metric-value text-primary"><?= $orderStats['total_orders'] ?? 0 ?></div>
                        <small class="text-muted"><?= number_format($orderStats['total_quantity'] ?? 0) ?> units</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card success shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Revenue</div>
                        <div class="metric-value text-success">MWK <?= number_format($orderStats['total_revenue'] ?? 0) ?></div>
                        <small class="text-muted"><?= $paymentStats['paid_count'] ?? 0 ?> paid orders</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card warning shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Pending Payment</div>
                        <div class="metric-value text-warning">MWK <?= number_format($paymentStats['total_pending'] ?? 0) ?></div>
                        <small class="text-muted">Awaiting collection</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card info shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small mb-1">Deliveries</div>
                        <div class="metric-value text-info"><?= $deliveryStats['completed'] ?? 0 ?></div>
                        <small class="text-muted">
                            Avg: <?= $deliveryStats['avg_delivery_hours'] ? round($deliveryStats['avg_delivery_hours']) . 'h' : 'N/A' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row g-4 mb-4">
            <!-- Revenue Chart -->
            <div class="col-lg-8">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-graph-up"></i> Revenue Trend (Last 6 Months)
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <!-- Order Status -->
            <div class="col-lg-4">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-pie-chart"></i> Order Status
                    </div>
                    <div class="card-body">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Second Row -->
        <div class="row g-4 mb-4">
            <!-- Top Products -->
            <div class="col-lg-6">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-trophy"></i> Top Products
                    </div>
                    <div class="card-body">
                        <?php if (empty($topProducts)): ?>
                            <div class="text-center text-muted py-4">No data available</div>
                        <?php else: ?>
                            <?php 
                            $maxQty = max(array_column($topProducts, 'total_qty'));
                            foreach ($topProducts as $i => $product): 
                                $percent = $maxQty > 0 ? ($product['total_qty'] / $maxQty) * 100 : 0;
                            ?>
                            <div class="top-product p-3 mb-2 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars($product['type']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <strong><?= number_format($product['total_qty']) ?></strong> units<br>
                                        <small class="text-success">MWK <?= number_format($product['total_revenue']) ?></small>
                                    </div>
                                </div>
                                <div class="progress progress-thin mt-2">
                                    <div class="progress-bar bg-success" style="width: <?= $percent ?>%"></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Driver Performance -->
            <div class="col-lg-6">
                <div class="card chart-card shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-people"></i> Driver Performance
                    </div>
                    <div class="card-body">
                        <?php if (empty($driverPerformance)): ?>
                            <div class="text-center text-muted py-4">No driver data available</div>
                        <?php else: ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th class="text-center">Total</th>
                                        <th class="text-center">Completed</th>
                                        <th class="text-center">Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($driverPerformance as $driver): 
                                    $rate = $driver['total_deliveries'] > 0 
                                        ? ($driver['completed'] / $driver['total_deliveries']) * 100 
                                        : 0;
                                ?>
                                <tr>
                                    <td><i class="bi bi-person-circle"></i> <?= htmlspecialchars($driver['full_name']) ?></td>
                                    <td class="text-center"><?= $driver['total_deliveries'] ?></td>
                                    <td class="text-center text-success"><?= $driver['completed'] ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $rate >= 80 ? 'success' : ($rate >= 50 ? 'warning' : 'danger') ?>">
                                            <?= number_format($rate, 0) ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary Table -->
        <div class="card chart-card shadow-sm">
            <div class="card-header bg-white">
                <i class="bi bi-table"></i> Order Summary
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h4 class="text-warning mb-0"><?= $orderStats['requested'] ?? 0 ?></h4>
                            <small>Requested</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h4 class="text-info mb-0"><?= $orderStats['approved'] ?? 0 ?></h4>
                            <small>Approved</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h4 class="text-primary mb-0"><?= $orderStats['dispatched'] ?? 0 ?></h4>
                            <small>Dispatched</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h4 class="text-success mb-0"><?= $orderStats['delivered'] ?? 0 ?></h4>
                            <small>Delivered</small>
                        </div>
                    </div>
                    <div class="col">
                        <div class="p-3 bg-light rounded">
                            <h4 class="text-danger mb-0"><?= $orderStats['cancelled'] ?? 0 ?></h4>
                            <small>Cancelled</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode($revenueLabels) ?>,
            datasets: [{
                label: 'Revenue (MWK)',
                data: <?= json_encode($revenueData) ?>,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.4
            }, {
                label: 'Orders',
                data: <?= json_encode($ordersData) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'transparent',
                borderDash: [5, 5],
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, position: 'left' },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } }
            }
        }
    });

    // Status Doughnut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusData) ?>,
                backgroundColor: ['#ffc107', '#17a2b8', '#0d6efd', '#28a745', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
</script>
</body>
</html>