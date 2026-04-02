<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];
$driver_name = $_SESSION['full_name'] ?? 'Driver';

// Driver Statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_deliveries,
        SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as active_deliveries,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as completed_deliveries,
        SUM(CASE WHEN status = 'Delivered' AND DATE(delivered_on) = CURDATE() THEN 1 ELSE 0 END) as today_deliveries
    FROM deliveries
    WHERE driver_id = $driver_id
")->fetch_assoc();

// Upcoming deliveries (pending + in transit)
$upcomingDeliveries = $conn->query("
    SELECT d.*, o.id as order_id, o.quantity, f.name as fertilizer_name, 
           s.company_name as supplier_name, u.phone as supplier_phone
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE d.driver_id = $driver_id AND d.status IN ('Pending', 'In Transit')
    ORDER BY d.expected_arrival ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent completed deliveries
$recentCompleted = $conn->query("
    SELECT d.*, o.id as order_id, f.name as fertilizer_name, s.company_name as supplier_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    WHERE d.driver_id = $driver_id AND d.status = 'Delivered'
    ORDER BY d.delivered_on DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Greeting
$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Driver Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        .delivery-item {
            border-left: 3px solid #dee2e6;
            padding-left: 15px;
            margin-left: 10px;
            transition: border-color 0.2s;
        }
        .delivery-item:hover { border-color: #667eea; }
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
                    <h3 class="mb-1"><?= $greeting ?>, <?= htmlspecialchars($driver_name) ?>!</h3>
                    <p class="mb-0 opacity-75">Ready to deliver today?</p>
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
                                <p class="text-muted small mb-1">Total Deliveries</p>
                                <h3 class="mb-0"><?= $stats['total_deliveries'] ?></h3>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-box-seam"></i>
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
                                <p class="text-muted small mb-1">Pending</p>
                                <h3 class="mb-0 text-warning"><?= $stats['pending_deliveries'] ?></h3>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-clock"></i>
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
                                <p class="text-muted small mb-1">In Transit</p>
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
                                <p class="text-muted small mb-1">Today's Deliveries</p>
                                <h3 class="mb-0 text-success"><?= $stats['today_deliveries'] ?></h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Upcoming Deliveries -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span><i class="bi bi-clock"></i> Upcoming Deliveries</span>
                        <a href="my_deliveries.php" class="text-decoration-none small">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($upcomingDeliveries)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-truck fs-1"></i>
                                <p class="mb-0 mt-2">No upcoming deliveries</p>
                                <small>Check back later for new assignments</small>
                            </div>
                        <?php else: ?>
                            <?php foreach ($upcomingDeliveries as $delivery): ?>
                            <div class="delivery-item p-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><i class="bi bi-box-seam text-primary"></i> <?= htmlspecialchars($delivery['fertilizer_name']) ?></strong>
                                        <br><small class="text-muted">
                                            To: <?= htmlspecialchars($delivery['supplier_name']) ?>
                                            • <?= $delivery['quantity'] ?> units
                                        </small>
                                        <br><small class="text-muted">
                                            <i class="bi bi-clock"></i> ETA: <?= date('M d, H:i', strtotime($delivery['expected_arrival'])) ?>
                                            • <i class="bi bi-phone"></i> <?= htmlspecialchars($delivery['supplier_phone']) ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?= $delivery['status'] === 'In Transit' ? 'primary' : 'warning' ?>">
                                        <?= $delivery['status'] ?>
                                    </span>
                                </div>
                                <div class="mt-2">
                                    <a href="my_deliveries.php" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-right"></i> View Details
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Recent -->
            <div class="col-lg-5">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="my_deliveries.php" class="card bg-light text-center p-3 d-block text-decoration-none">
                                    <i class="bi bi-truck text-primary fs-3"></i>
                                    <div class="small mt-1">My Deliveries</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="route_optimization.php" class="card bg-light text-center p-3 d-block text-decoration-none">
                                    <i class="bi bi-map text-success fs-3"></i>
                                    <div class="small mt-1">Route Plan</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="profile.php" class="card bg-light text-center p-3 d-block text-decoration-none">
                                    <i class="bi bi-person-circle text-info fs-3"></i>
                                    <div class="small mt-1">My Profile</div>
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="../logout.php" class="card bg-light text-center p-3 d-block text-decoration-none">
                                    <i class="bi bi-box-arrow-right text-danger fs-3"></i>
                                    <div class="small mt-1">Logout</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Completed -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <i class="bi bi-check-circle"></i> Recently Completed
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentCompleted)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mb-0 mt-2">No completed deliveries yet</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recentCompleted as $delivery): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($delivery['fertilizer_name']) ?></strong>
                                            <br><small class="text-muted">
                                                <?= htmlspecialchars($delivery['supplier_name']) ?>
                                            </small>
                                            <br><small class="text-success">
                                                <i class="bi bi-check-circle"></i> 
                                                <?= date('M d, H:i', strtotime($delivery['delivered_on'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>