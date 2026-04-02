<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];
$driver_name = $_SESSION['full_name'] ?? 'Driver';

// Get driver stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'Delivered' AND DATE(delivered_on) = CURDATE() THEN 1 ELSE 0 END) as today_delivered
    FROM deliveries WHERE driver_id = {$driver_id}
")->fetch_assoc();

// Get active delivery (In Transit)
$activeDelivery = $conn->query("
    SELECT d.*, o.quantity, o.total_price,
           f.name as fertilizer_name, f.type as fertilizer_type,
           s.company_name, s.phone as supplier_phone, s.address as supplier_address,
           sl.name as destination_name, sl.address as destination_address,
           sl.latitude as dest_lat, sl.longitude as dest_lng
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    LEFT JOIN supplier_locations sl ON sl.supplier_id = s.id AND sl.is_primary = 1
    WHERE d.driver_id = {$driver_id} AND d.status = 'In Transit'
    ORDER BY d.last_updated DESC
    LIMIT 1
")->fetch_assoc();

// Get pending deliveries
$pendingDeliveries = $conn->query("
    SELECT d.*, o.quantity, f.name as fertilizer_name, s.company_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    WHERE d.driver_id = {$driver_id} AND d.status = 'Pending'
    ORDER BY d.expected_arrival ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get recent completed deliveries
$recentDeliveries = $conn->query("
    SELECT d.*, o.quantity, f.name as fertilizer_name, s.company_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    WHERE d.driver_id = {$driver_id} AND d.status = 'Delivered'
    ORDER BY d.delivered_on DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get current time greeting
$hour = date('H');
if ($hour < 12) {
    $greeting = 'Good Morning';
    $icon = 'sunrise';
} elseif ($hour < 17) {
    $greeting = 'Good Afternoon';
    $icon = 'sun';
} else {
    $greeting = 'Good Evening';
    $icon = 'moon';
}
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
        .welcome-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
        }
        .stat-card {
            border-radius: 12px;
            border: none;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .active-delivery-card {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border-radius: 15px;
        }
        .pulse-dot {
            width: 12px; height: 12px;
            background: #fff;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
        }
        .delivery-item {
            border-left: 4px solid #dee2e6;
            transition: all 0.2s;
        }
        .delivery-item:hover {
            border-left-color: #667eea;
            background: #f8f9fa;
        }
        .delivery-item.pending { border-left-color: #ffc107; }
        .delivery-item.completed { border-left-color: #28a745; }
        .quick-action {
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .quick-action i { font-size: 2rem; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <!-- Welcome Card -->
        <div class="welcome-card p-4 mb-4 shadow">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4 class="mb-1">
                        <i class="bi bi-<?= $icon ?>"></i> <?= $greeting ?>, <?= htmlspecialchars($driver_name) ?>!
                    </h4>
                    <p class="mb-0 opacity-75">
                        <?php if ($activeDelivery): ?>
                            You have an active delivery in progress.
                        <?php elseif (count($pendingDeliveries) > 0): ?>
                            You have <?= count($pendingDeliveries) ?> pending delivery(ies) waiting.
                        <?php else: ?>
                            No deliveries assigned. Enjoy your break!
                        <?php endif; ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <span class="badge bg-light text-dark fs-6">
                        <i class="bi bi-calendar3"></i> <?= date('l, M d, Y') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-clock-history text-warning" style="font-size: 1.5rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['pending'] ?? 0 ?></h3>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-truck text-primary" style="font-size: 1.5rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['in_transit'] ?? 0 ?></h3>
                        <small class="text-muted">In Transit</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle text-success" style="font-size: 1.5rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['today_delivered'] ?? 0 ?></h3>
                        <small class="text-muted">Today</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-trophy text-info" style="font-size: 1.5rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['delivered'] ?? 0 ?></h3>
                        <small class="text-muted">Total Completed</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Delivery -->
        <?php if ($activeDelivery): ?>
        <div class="active-delivery-card p-4 mb-4 shadow">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="pulse-dot"></div>
                        <h5 class="mb-0">Active Delivery #<?= $activeDelivery['id'] ?></h5>
                    </div>
                    <small class="opacity-75">Currently in transit</small>
                </div>
                <a href="active_delivery.php" class="btn btn-light btn-sm">
                    <i class="bi bi-geo-alt"></i> Open Navigator
                </a>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-2">
                        <i class="bi bi-box"></i> <strong><?= htmlspecialchars($activeDelivery['fertilizer_name']) ?></strong>
                        (<?= htmlspecialchars($activeDelivery['fertilizer_type']) ?>)
                    </p>
                    <p class="mb-2">
                        <i class="bi bi-123"></i> Quantity: <?= $activeDelivery['quantity'] ?> units
                    </p>
                    <p class="mb-0">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($activeDelivery['company_name']) ?>
                    </p>
                </div>
                <div class="col-md-6">
                    <?php if ($activeDelivery['destination_address']): ?>
                    <p class="mb-2">
                        <i class="bi bi-geo-alt-fill"></i> <strong>Destination:</strong><br>
                        <?= htmlspecialchars($activeDelivery['destination_address']) ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($activeDelivery['expected_arrival']): ?>
                    <p class="mb-0">
                        <i class="bi bi-clock"></i> ETA: <?= date('M d, H:i', strtotime($activeDelivery['expected_arrival'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="mt-3 d-flex gap-2">
                <a href="active_delivery.php" class="btn btn-light flex-fill">
                    <i class="bi bi-navigation"></i> Navigate
                </a>
                <a href="my_deliveries.php?complete=<?= $activeDelivery['id'] ?>" class="btn btn-warning flex-fill"
                   onclick="return confirm('Mark this delivery as completed?')">
                    <i class="bi bi-check-lg"></i> Complete Delivery
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Pending Deliveries -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-hourglass-split text-warning"></i> Pending Deliveries</span>
                        <a href="my_deliveries.php?status=pending" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pendingDeliveries)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mb-0 mt-2">No pending deliveries</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingDeliveries as $del): ?>
                            <div class="delivery-item pending p-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>Delivery #<?= $del['id'] ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($del['fertilizer_name']) ?> - <?= $del['quantity'] ?> units
                                        </small><br>
                                        <small class="text-muted">
                                            <i class="bi bi-building"></i> <?= htmlspecialchars($del['company_name']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($del['expected_arrival']): ?>
                                            <small class="text-muted">ETA</small><br>
                                            <small><?= date('M d, H:i', strtotime($del['expected_arrival'])) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Completed -->
            <div class="col-lg-6 mb-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-check-circle text-success"></i> Recently Completed</span>
                        <a href="delivery_history.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recentDeliveries)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mb-0 mt-2">No completed deliveries yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentDeliveries as $del): ?>
                            <div class="delivery-item completed p-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong>Delivery #<?= $del['id'] ?></strong><br>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($del['fertilizer_name']) ?> - <?= $del['quantity'] ?> units
                                        </small><br>
                                        <small class="text-muted">
                                            <i class="bi bi-building"></i> <?= htmlspecialchars($del['company_name']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-success">Completed</span><br>
                                        <small class="text-muted"><?= date('M d, H:i', strtotime($del['delivered_on'])) ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <a href="my_deliveries.php" class="quick-action card border-0 shadow-sm d-block">
                    <i class="bi bi-list-task text-primary"></i>
                    <div>My Deliveries</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="active_delivery.php" class="quick-action card border-0 shadow-sm d-block">
                    <i class="bi bi-geo-alt text-success"></i>
                    <div>Active Delivery</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="delivery_history.php" class="quick-action card border-0 shadow-sm d-block">
                    <i class="bi bi-clock-history text-info"></i>
                    <div>History</div>
                </a>
            </div>
            <div class="col-6 col-md-3">
                <a href="profile.php" class="quick-action card border-0 shadow-sm d-block">
                    <i class="bi bi-person-circle text-secondary"></i>
                    <div>My Profile</div>
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>