<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$supplier_name = $_SESSION['full_name'] ?? 'Supplier';

// Get supplier_id from suppliers table
$stmt = $conn->prepare("SELECT id, company_name, phone, address FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supplier_id = $supplier ? (int) $supplier['id'] : null;

// Supplier Statistics - Initialize with defaults
$stats = [
    'total_orders' => 0,
    'pending_orders' => 0,
    'delivered_orders' => 0,
    'active_deliveries' => 0,
    'total_spent' => 0,
    'pending_payments' => 0,
    'certificate_status' => 'Not Applied',
    'pending_applications' => 0
];

if ($supplier_id) {
    $result = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM orders WHERE supplier_id = $supplier_id) as total_orders,
            (SELECT COUNT(*) FROM orders WHERE supplier_id = $supplier_id AND status = 'Requested') as pending_orders,
            (SELECT COUNT(*) FROM orders WHERE supplier_id = $supplier_id AND status = 'Delivered') as delivered_orders,
            (SELECT COUNT(*) FROM deliveries WHERE supplier_id = $supplier_id AND status = 'In Transit') as active_deliveries,
            (SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE supplier_id = $supplier_id) as total_spent,
            (SELECT COUNT(*) FROM payments p JOIN orders o ON p.order_id = o.id WHERE o.supplier_id = $supplier_id AND p.payment_status = 'Pending') as pending_payments,
            (SELECT COUNT(*) FROM certificate_applications WHERE supplier_id = $supplier_id AND status = 'Pending') as pending_applications
    ");
    
    if ($result) {
        $fetchedStats = $result->fetch_assoc();
        // Merge fetched stats with defaults, preserving certificate_status
        $stats = array_merge($stats, $fetchedStats);
    }
    
    // Check certificate status
    $cert = $conn->query("SELECT status FROM certificates WHERE supplier_id = $user_id AND status IN ('Approved', 'Pending') ORDER BY issued_on DESC LIMIT 1");
    if ($cert && $cert->num_rows > 0) {
        $certData = $cert->fetch_assoc();
        $stats['certificate_status'] = $certData['status'];
    }
}

// Recent Orders
$recentOrders = [];
if ($supplier_id) {
    $recentOrders = $conn->query("
        SELECT o.*, f.name as fertilizer_name, f.type, p.payment_status
        FROM orders o
        JOIN fertilizers f ON o.fertilizer_id = f.id
        LEFT JOIN payments p ON p.order_id = o.id
        WHERE o.supplier_id = $supplier_id
        ORDER BY o.order_date DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
}

// Active Deliveries
$activeDeliveries = [];
if ($supplier_id) {
    $activeDeliveries = $conn->query("
        SELECT d.*, u.full_name as driver_name, u.phone as driver_phone, f.name as fertilizer_name, o.quantity
        FROM deliveries d
        LEFT JOIN users u ON d.driver_id = u.id
        JOIN orders o ON d.order_id = o.id
        JOIN fertilizers f ON o.fertilizer_id = f.id
        WHERE d.supplier_id = $supplier_id AND d.status = 'In Transit'
        ORDER BY d.last_updated DESC
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
}

// Certificate Applications
$applications = [];
if ($supplier_id) {
    $applications = $conn->query("
        SELECT ca.*, u.full_name as reviewer_name
        FROM certificate_applications ca
        LEFT JOIN users u ON ca.reviewed_by = u.id
        WHERE ca.supplier_id = $supplier_id
        ORDER BY ca.submitted_at DESC
        LIMIT 3
    ")->fetch_all(MYSQLI_ASSOC);
}

// Available Fertilizers
$fertilizers = $conn->query("
    SELECT * FROM fertilizers 
    WHERE stock_remaining > 0 AND certified = 1
    ORDER BY name ASC
    LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Monthly Order Trend (6 months)
$monthlyTrend = [];
if ($supplier_id) {
    $monthlyTrend = $conn->query("
        SELECT DATE_FORMAT(order_date, '%Y-%m') as month, COUNT(*) as orders, SUM(total_price) as spent
        FROM orders
        WHERE supplier_id = $supplier_id AND order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(order_date, '%Y-%m')
        ORDER BY month ASC
    ")->fetch_all(MYSQLI_ASSOC);
}

$trendLabels = array_map(fn($m) => date('M', strtotime($m['month'] . '-01')), $monthlyTrend);
$trendOrders = array_column($monthlyTrend, 'orders');
$trendSpent = array_column($monthlyTrend, 'spent');

// Greeting
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Supplier Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .welcome-banner {
            background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);
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
        .activity-item:hover { border-color: #20c997; }
        .quick-action {
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
        }
        .quick-action:hover { transform: translateY(-3px); }
        .fertilizer-card {
            transition: all 0.2s;
            cursor: pointer;
        }
        .fertilizer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .cert-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
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
                    <h3 class="mb-1"><?= $greeting ?>, <?= htmlspecialchars($supplier_name) ?>!</h3>
                    <p class="mb-0 opacity-75"><?= htmlspecialchars($supplier['company_name'] ?? 'Complete your profile to get started') ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <i class="bi bi-calendar3"></i> <?= date('l, M d, Y') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Certificate Alert -->
        <?php if ($stats['certificate_status'] === 'Not Applied'): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-exclamation-triangle"></i> <strong>Action Required:</strong> Apply for a fertilizer supplier certificate to unlock full access.
            <a href="apply_certificate.php" class="btn btn-sm btn-warning ms-2">Apply Now</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php elseif ($stats['certificate_status'] === 'Pending'): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <i class="bi bi-clock"></i> <strong>Under Review:</strong> Your certificate application is being reviewed by the admin team.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted small mb-1">Total Orders</p>
                                <h3 class="mb-0"><?= $stats['total_orders'] ?></h3>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-bag"></i>
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
                                <p class="text-muted small mb-1">Pending Orders</p>
                                <h3 class="mb-0 text-warning"><?= $stats['pending_orders'] ?></h3>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-clock-history"></i>
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
                                <p class="text-muted small mb-1">Active Deliveries</p>
                                <h3 class="mb-0 text-info"><?= $stats['active_deliveries'] ?></h3>
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
                                <p class="text-muted small mb-1">Total Spent</p>
                                <h3 class="mb-0 text-success">MWK <?= number_format($stats['total_spent'], 0) ?></h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-4">
            <!-- Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <i class="bi bi-graph-up"></i> Your Order & Spending Trend
                    </div>
                    <div class="card-body">
                        <?php if (!empty($monthlyTrend)): ?>
                        <canvas id="trendChart" height="120"></canvas>
                        <?php else: ?>
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-graph-up fs-1"></i>
                            <p class="mb-0 mt-2">No order history yet</p>
                        </div>
                        <?php endif; ?>
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
                                <a href="place_order.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-cart-plus text-success fs-3"></i>
                                    <div class="small mt-1">Place Order</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="my_orders.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-bag-check text-primary fs-3"></i>
                                    <div class="small mt-1">My Orders</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="track_delivery.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-geo-alt text-info fs-3"></i>
                                    <div class="small mt-1">Track Delivery</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="payments.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-credit-card text-warning fs-3"></i>
                                    <div class="small mt-1">Payments</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="apply_certificate.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-patch-check text-success fs-3"></i>
                                    <div class="small mt-1">Certificate</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="profile.php" class="quick-action card bg-light text-center p-3 d-block">
                                    <i class="bi bi-person-circle text-secondary fs-3"></i>
                                    <div class="small mt-1">Profile</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Available Fertilizers -->
        <div class="mt-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-box-seam"></i> Available Fertilizers</h5>
                <a href="place_order.php" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="row g-3">
                <?php if (empty($fertilizers)): ?>
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body text-center text-muted py-5">
                                <i class="bi bi-box-seam fs-1"></i>
                                <p class="mb-0 mt-2">No fertilizers available at the moment</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($fertilizers as $fert): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="card fertilizer-card border-0 shadow-sm h-100 position-relative">
                            <?php if ($fert['certified']): ?>
                            <span class="cert-badge badge bg-success">
                                <i class="bi bi-patch-check"></i> Certified
                            </span>
                            <?php endif; ?>
                            <div class="card-body">
                                <h6 class="card-title"><?= htmlspecialchars($fert['name']) ?></h6>
                                <p class="text-muted small mb-2">
                                    <i class="bi bi-tag"></i> <?= htmlspecialchars($fert['type']) ?>
                                    <?php if ($fert['npk_value']): ?>
                                    • NPK: <?= htmlspecialchars($fert['npk_value']) ?>
                                    <?php endif; ?>
                                </p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong class="text-success">MWK <?= number_format($fert['price_per_unit'], 0) ?></strong>
                                        <br><small class="text-muted">Stock: <?= $fert['stock_remaining'] ?></small>
                                    </div>
                                    <a href="place_order.php?fertilizer_id=<?= $fert['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-cart-plus"></i> Order
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Activity Row -->
        <div class="row g-4 mt-2">
            <!-- Recent Orders -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span><i class="bi bi-bag"></i> Recent Orders</span>
                        <a href="my_orders.php" class="text-decoration-none small">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentOrders)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-bag fs-1"></i>
                                <p class="mb-0 mt-2">No orders yet</p>
                                <a href="place_order.php" class="btn btn-sm btn-success mt-2">Place Your First Order</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentOrders as $order): ?>
                            <div class="activity-item p-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>#<?= $order['id'] ?></strong> - <?= htmlspecialchars($order['fertilizer_name']) ?>
                                        <br><small class="text-muted">
                                            <?= $order['quantity'] ?> units • MWK <?= number_format($order['total_price'], 0) ?>
                                            <?php if ($order['payment_status']): ?>
                                            • <span class="badge bg-<?= $order['payment_status'] === 'Completed' ? 'success' : 'warning' ?>">
                                                <?= $order['payment_status'] ?>
                                            </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= $order['status'] === 'Delivered' ? 'success' : ($order['status'] === 'Requested' ? 'warning' : 'primary') ?> h-fit">
                                        <?= $order['status'] ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Deliveries -->
            <div class="col-lg-6">
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
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?= htmlspecialchars($del['fertilizer_name']) ?></strong>
                                        <br><small class="text-muted">
                                            <i class="bi bi-person"></i> <?= htmlspecialchars($del['driver_name'] ?? 'Pending assignment') ?>
                                            <?php if ($del['driver_phone']): ?>
                                            • <?= htmlspecialchars($del['driver_phone']) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <a href="track_delivery.php?id=<?= $del['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-geo-alt"></i> Track
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Applications -->
        <?php if (!empty($applications)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-white d-flex justify-content-between">
                <span><i class="bi bi-file-earmark-text"></i> Your Certificate Applications</span>
                <a href="apply_certificate.php" class="text-decoration-none small">View All</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Submitted</th>
                                <th>Status</th>
                                <th>Reviewed By</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($applications as $app): 
                            $badge = match($app['status']) {
                                'Approved' => 'success',
                                'Rejected' => 'danger',
                                default => 'warning'
                            };
                        ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= date('M d, Y', strtotime($app['submitted_at'])) ?></td>
                            <td><span class="badge bg-<?= $badge ?>"><?= $app['status'] ?></span></td>
                            <td><?= htmlspecialchars($app['reviewer_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($app['review_notes'] ?? '-') ?></td>
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
<?php if (!empty($monthlyTrend)): ?>
<script>
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($trendLabels) ?>,
            datasets: [{
                label: 'Orders',
                data: <?= json_encode($trendOrders) ?>,
                backgroundColor: 'rgba(32, 201, 151, 0.7)',
                yAxisID: 'y'
            }, {
                label: 'Spending (MWK)',
                data: <?= json_encode($trendSpent) ?>,
                type: 'line',
                borderColor: '#0d6efd',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Orders' } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'MWK' } }
            }
        }
    });
</script>
<?php endif; ?>
</body>
</html>