<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$admin_name = $_SESSION['full_name'] ?? 'Admin';

// System Statistics
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'supplier' AND status = 'active') as active_suppliers,
        (SELECT COUNT(*) FROM users WHERE role = 'driver' AND status = 'active') as active_drivers,
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT COUNT(*) FROM orders WHERE status = 'Requested') as pending_orders,
        (SELECT COUNT(*) FROM deliveries WHERE status = 'In Transit') as active_deliveries,
        (SELECT COUNT(*) FROM deliveries WHERE status = 'Delivered' AND DATE(delivered_on) = CURDATE()) as today_deliveries,
        (SELECT COUNT(*) FROM certificates WHERE status = 'Approved' AND (expires_on IS NULL OR expires_on >= CURDATE())) as active_certificates,
        (SELECT COUNT(*) FROM certificate_applications WHERE status = 'Pending') as pending_applications,
        (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE MONTH(order_date) = MONTH(CURDATE())) as monthly_revenue,
        (SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'Completed' AND MONTH(payment_date) = MONTH(CURDATE())) as monthly_collected
")->fetch_assoc();

// Recent Orders
$recentOrders = $conn->query("
    SELECT o.*, s.company_name, f.name as fertilizer_name
    FROM orders o
    JOIN suppliers s ON o.supplier_id = s.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    ORDER BY o.order_date DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent Applications
$recentApplications = $conn->query("
    SELECT ca.*, s.company_name, u.full_name
    FROM certificate_applications ca
    JOIN suppliers s ON ca.supplier_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE ca.status = 'Pending'
    ORDER BY ca.submitted_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Active Deliveries
$activeDeliveries = $conn->query("
    SELECT d.*, s.company_name, u.full_name as driver_name, f.name as fertilizer_name
    FROM deliveries d
    JOIN suppliers s ON d.supplier_id = s.id
    LEFT JOIN users u ON d.driver_id = u.id
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    WHERE d.status = 'In Transit'
    ORDER BY d.last_updated DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Low Stock Fertilizers
$lowStock = $conn->query("
    SELECT * FROM fertilizers 
    WHERE stock_remaining <= minimum_stock AND stock_remaining > 0
    ORDER BY stock_remaining ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Monthly Order Trend (6 months)
$monthlyTrend = $conn->query("
    SELECT DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as orders, SUM(total_price) as revenue
    FROM orders
    WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

$trendLabels = array_map(fn($m) => date('M', strtotime($m['month'] . '-01')), $monthlyTrend);
$trendOrders = array_column($monthlyTrend, 'orders');
$trendRevenue = array_column($monthlyTrend, 'revenue');

// Greeting
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .welcome-banner {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card {
            border-radius: 12px;
            border: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .activity-item {
            border-left: 3px solid #dee2e6;
            padding-left: 15px;
            margin-left: 10px;
            transition: border-color 0.2s;
        }
        .activity-item:hover { border-color: #28a745; }
        .alert-card { border-left: 4px solid; }
        .alert-card.warning { border-color: #ffc107; }
        .alert-card.danger { border-color: #dc3545; }
        .quick-action {
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }
        .quick-action:hover { transform: translateY(-3px); }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <!-- Welcome Banner -->
        <div class="welcome-banner p-4 mb-4 shadow">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3 class="mb-1"><?= $greeting ?>, <?= htmlspecialchars($admin_name) ?>!</h3>
                    <p class="mb-0 opacity-75">Here's what's happening with your fertilizer management system today.</p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <i class="bi bi-calendar3"></i> <?= date('l, M d, Y') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Active Suppliers</p>
                                <h3 class="mb-0"><?= $stats['active_suppliers'] ?></h3>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-building"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Active Drivers</p>
                                <h3 class="mb-0"><?= $stats['active_drivers'] ?></h3>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-truck"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Active Certificates</p>
                                <h3 class="mb-0"><?= $stats['active_certificates'] ?></h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-patch-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Monthly Revenue</p>
                                <h3 class="mb-0 text-success">MWK <?= number_format($stats['monthly_revenue'], 0) ?></h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts Row -->
        <div class="row g-3 mb-4">
            <?php if ($stats['pending_applications'] > 0): ?>
            <div class="col-md-6">
                <div class="card alert-card warning shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><i class="bi bi-exclamation-triangle text-warning"></i> Pending Applications</h6>
                            <p class="mb-0 text-muted"><?= $stats['pending_applications'] ?> certificate applications awaiting review</p>
                        </div>
                        <a href="certificates.php" class="btn btn-warning">Review</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($stats['pending_orders'] > 0): ?>
            <div class="col-md-6">
                <div class="card alert-card warning shadow-sm">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1"><i class="bi bi-bag text-warning"></i> Pending Orders</h6>
                            <p class="mb-0 text-muted"><?= $stats['pending_orders'] ?> orders need approval</p>
                        </div>
                        <a href="orders.php" class="btn btn-warning">View</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-graph-up"></i> Order & Revenue Trend
                    </div>
                    <div class="card-body">
                        <canvas id="trendChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="certificates.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-patch-check text-success fs-3"></i>
                                    <div class="small mt-1">Certificates</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="generate_qr.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-qr-code text-primary fs-3"></i>
                                    <div class="small mt-1">Generate QR</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="manage_users.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-people text-info fs-3"></i>
                                    <div class="small mt-1">Users</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="reports.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-bar-chart text-warning fs-3"></i>
                                    <div class="small mt-1">Reports</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="inventory.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-box-seam text-secondary fs-3"></i>
                                    <div class="small mt-1">Inventory</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="audit_logs.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-clipboard-data text-dark fs-3"></i>
                                    <div class="small mt-1">Audit Logs</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Row -->
        <div class="row g-4 mt-2">
            <!-- Recent Orders -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span><i class="bi bi-bag"></i> Recent Orders</span>
                        <a href="orders.php" class="text-decoration-none small">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php foreach ($recentOrders as $order): ?>
                        <div class="activity-item p-3 border-bottom">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong>#<?= $order['id'] ?></strong> - <?= htmlspecialchars($order['fertilizer_name']) ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($order['company_name']) ?></small>
                                </div>
                                <span class="badge bg-<?= $order['status'] === 'Delivered' ? 'success' : ($order['status'] === 'Requested' ? 'warning' : 'primary') ?> h-fit">
                                    <?= $order['status'] ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Pending Applications -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span><i class="bi bi-file-earmark-text"></i> Pending Applications</span>
                        <a href="certificates.php" class="text-decoration-none small">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentApplications)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-check-circle fs-1"></i>
                                <p class="mb-0 mt-2">No pending applications</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentApplications as $app): ?>
                            <div class="activity-item p-3 border-bottom">
                                <strong><?= htmlspecialchars($app['company_name']) ?></strong>
                                <br><small class="text-muted">
                                    <?= htmlspecialchars($app['full_name']) ?> • 
                                    <?= date('M d, H:i', strtotime($app['submitted_at'])) ?>
                                </small>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Deliveries -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span><i class="bi bi-truck"></i> Active Deliveries</span>
                        <span class="badge bg-primary"><?= $stats['active_deliveries'] ?></span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($activeDeliveries)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-truck fs-1"></i>
                                <p class="mb-0 mt-2">No active deliveries</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($activeDeliveries as $del): ?>
                            <div class="activity-item p-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>Delivery #<?= $del['id'] ?></strong>
                                        <br><small class="text-muted">
                                            <?= htmlspecialchars($del['driver_name'] ?? 'No driver') ?> • 
                                            <?= htmlspecialchars($del['company_name']) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-primary h-fit">In Transit</span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <?php if (!empty($lowStock)): ?>
        <div class="card border-0 shadow-sm mt-4 alert-card danger">
            <div class="card-header bg-white">
                <i class="bi bi-exclamation-triangle text-danger"></i> Low Stock Alert
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr><th>Fertilizer</th><th>Type</th><th>Stock</th><th>Minimum</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($lowStock as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['type']) ?></td>
                            <td class="text-danger fw-bold"><?= $item['stock_remaining'] ?></td>
                            <td><?= $item['minimum_stock'] ?></td>
                            <td><a href="inventory.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">Restock</a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'Orders',
                data: <?= json_encode($trendOrders) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                yAxisID: 'y'
            }, {
                label: 'Revenue (MWK)',
                data: <?= json_encode($trendRevenue) ?>,
                type: 'line',
                borderColor: '#0d6efd',
                tension: 0.4,
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
</script>
</body>
</html>