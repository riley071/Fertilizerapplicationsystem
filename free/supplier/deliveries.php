<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Get supplier_id
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    $_SESSION['error'] = "Please complete your supplier profile first.";
    header('Location: profile.php');
    exit();
}

$supplier_id = (int) $supplier['id'];

// Get available drivers
$drivers = $conn->query("SELECT id, full_name, phone FROM users WHERE role = 'driver' AND status = 'active'")->fetch_all(MYSQLI_ASSOC);

// Handle delivery creation from approved order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_delivery'])) {
    $order_id = (int) $_POST['order_id'];
    $driver_id = !empty($_POST['driver_id']) ? (int) $_POST['driver_id'] : null;
    $expected_arrival = !empty($_POST['expected_arrival']) ? $_POST['expected_arrival'] : null;
    
    // Verify order belongs to supplier and is approved/dispatched
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND supplier_id = ? AND status IN ('Approved', 'Dispatched')");
    $stmt->bind_param("ii", $order_id, $supplier_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($order) {
        // Check if delivery already exists
        $stmt = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            $error = "A delivery already exists for this order.";
        } else {
            // Get admin_id (first admin)
            $admin = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
            $admin_id = $admin ? $admin['id'] : 1;
            
            $stmt = $conn->prepare("INSERT INTO deliveries (order_id, admin_id, supplier_id, driver_id, expected_arrival, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
            $stmt->bind_param("iiiis", $order_id, $admin_id, $supplier_id, $driver_id, $expected_arrival);
            
            if ($stmt->execute()) {
                // Update order status to Dispatched
                $conn->query("UPDATE orders SET status = 'Dispatched' WHERE id = {$order_id}");
                $success = "Delivery created successfully!";
            } else {
                $error = "Failed to create delivery.";
            }
            $stmt->close();
        }
    } else {
        $error = "Invalid order or order not ready for delivery.";
    }
}

// Handle driver assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_driver'])) {
    $delivery_id = (int) $_POST['delivery_id'];
    $driver_id = (int) $_POST['driver_id'];
    
    $stmt = $conn->prepare("UPDATE deliveries SET driver_id = ? WHERE id = ? AND supplier_id = ?");
    $stmt->bind_param("iii", $driver_id, $delivery_id, $supplier_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $success = "Driver assigned successfully!";
    } else {
        $error = "Failed to assign driver.";
    }
    $stmt->close();
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $delivery_id = (int) $_POST['delivery_id'];
    $new_status = $_POST['new_status'];
    
    $allowed_statuses = ['Pending', 'In Transit', 'Delivered'];
    if (in_array($new_status, $allowed_statuses)) {
        $delivered_on = ($new_status === 'Delivered') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $conn->prepare("UPDATE deliveries SET status = ?, delivered_on = ? WHERE id = ? AND supplier_id = ?");
        $stmt->bind_param("ssii", $new_status, $delivered_on, $delivery_id, $supplier_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Update order status if delivered
            if ($new_status === 'Delivered') {
                $conn->query("UPDATE orders o JOIN deliveries d ON o.id = d.order_id SET o.status = 'Delivered' WHERE d.id = {$delivery_id}");
            }
            $success = "Delivery status updated!";
        } else {
            $error = "Failed to update status.";
        }
        $stmt->close();
    }
}

// Fetch orders ready for delivery (Approved but no delivery yet)
$pendingOrders = $conn->query("
    SELECT o.*, f.name as fertilizer_name, f.type as fertilizer_type
    FROM orders o
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN deliveries d ON o.id = d.order_id
    WHERE o.supplier_id = {$supplier_id} 
    AND o.status IN ('Approved') 
    AND d.id IS NULL
    ORDER BY o.order_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch all deliveries
$filter = $_GET['status'] ?? 'all';
$statusWhere = match($filter) {
    'pending' => "AND d.status = 'Pending'",
    'transit' => "AND d.status = 'In Transit'",
    'delivered' => "AND d.status = 'Delivered'",
    default => ""
};

$deliveries = $conn->query("
    SELECT d.*, o.quantity, o.total_price, o.order_date,
           f.name as fertilizer_name, f.type as fertilizer_type,
           u.full_name as driver_name, u.phone as driver_phone
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN users u ON d.driver_id = u.id
    WHERE d.supplier_id = {$supplier_id} {$statusWhere}
    ORDER BY d.last_updated DESC
")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as in_transit,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as delivered
    FROM deliveries WHERE supplier_id = {$supplier_id}
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Deliveries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .stat-card { border-radius: 10px; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-3px); }
        .status-pending { border-left: 4px solid #ffc107; }
        .status-transit { border-left: 4px solid #0d6efd; }
        .status-delivered { border-left: 4px solid #28a745; }
        .order-card { border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-truck"></i> Deliveries</h3>
            <a href="track_deliveries.php" class="btn btn-outline-primary">
                <i class="bi bi-geo-alt"></i> Track Live
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Total Deliveries</div>
                                <h3 class="mb-0"><?= $stats['total'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-box-seam text-primary" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Pending</div>
                                <h3 class="mb-0 text-warning"><?= $stats['pending'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">In Transit</div>
                                <h3 class="mb-0 text-primary"><?= $stats['in_transit'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-truck text-primary" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Delivered</div>
                                <h3 class="mb-0 text-success"><?= $stats['delivered'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Ready for Delivery -->
        <?php if (!empty($pendingOrders)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-info text-white">
                <i class="bi bi-box"></i> Orders Ready for Delivery
                <span class="badge bg-light text-dark"><?= count($pendingOrders) ?></span>
            </div>
            <div class="card-body">
                <?php foreach ($pendingOrders as $order): ?>
                <div class="card order-card mb-3">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h6 class="mb-1">Order #<?= $order['id'] ?></h6>
                                <p class="mb-1">
                                    <strong><?= htmlspecialchars($order['fertilizer_name']) ?></strong>
                                    (<?= htmlspecialchars($order['fertilizer_type']) ?>)
                                </p>
                                <small class="text-muted">
                                    Qty: <?= $order['quantity'] ?> | 
                                    Total: MWK <?= number_format($order['total_price'], 2) ?> |
                                    Ordered: <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" class="row g-2">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <div class="col-md-5">
                                        <select name="driver_id" class="form-select form-select-sm">
                                            <option value="">Assign Driver Later</option>
                                            <?php foreach ($drivers as $driver): ?>
                                                <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="datetime-local" name="expected_arrival" class="form-control form-control-sm" placeholder="Expected arrival">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" name="create_delivery" class="btn btn-success btn-sm w-100">
                                            <i class="bi bi-truck"></i> Create
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Deliveries List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list"></i> All Deliveries</span>
                <div class="btn-group btn-group-sm">
                    <a href="?status=all" class="btn btn-outline-secondary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                    <a href="?status=pending" class="btn btn-outline-warning <?= $filter === 'pending' ? 'active' : '' ?>">Pending</a>
                    <a href="?status=transit" class="btn btn-outline-primary <?= $filter === 'transit' ? 'active' : '' ?>">In Transit</a>
                    <a href="?status=delivered" class="btn btn-outline-success <?= $filter === 'delivered' ? 'active' : '' ?>">Delivered</a>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($deliveries)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-truck" style="font-size: 3rem;"></i>
                        <p class="mt-2">No deliveries found.</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Order</th>
                                <th>Fertilizer</th>
                                <th>Driver</th>
                                <th>Status</th>
                                <th>Expected</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($deliveries as $del): 
                            $statusClass = match($del['status']) {
                                'Pending' => 'warning',
                                'In Transit' => 'primary',
                                'Delivered' => 'success',
                                default => 'secondary'
                            };
                        ?>
                        <tr class="status-<?= strtolower(str_replace(' ', '', $del['status'])) ?>">
                            <td><strong>#<?= $del['id'] ?></strong></td>
                            <td>
                                Order #<?= $del['order_id'] ?><br>
                                <small class="text-muted">Qty: <?= $del['quantity'] ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($del['fertilizer_name']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($del['fertilizer_type']) ?></small>
                            </td>
                            <td>
                                <?php if ($del['driver_name']): ?>
                                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($del['driver_name']) ?><br>
                                    <small class="text-muted"><i class="bi bi-phone"></i> <?= htmlspecialchars($del['driver_phone']) ?></small>
                                <?php else: ?>
                                    <form method="POST" class="d-flex gap-1">
                                        <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                                        <select name="driver_id" class="form-select form-select-sm" style="width: 120px;" required>
                                            <option value="">Select</option>
                                            <?php foreach ($drivers as $driver): ?>
                                                <option value="<?= $driver['id'] ?>"><?= htmlspecialchars($driver['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_driver" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusClass ?>"><?= $del['status'] ?></span>
                                <?php if ($del['status'] === 'Delivered' && $del['delivered_on']): ?>
                                    <br><small class="text-muted"><?= date('M d, H:i', strtotime($del['delivered_on'])) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $del['expected_arrival'] ? date('M d, H:i', strtotime($del['expected_arrival'])) : '-' ?>
                            </td>
                            <td>
                                <?php if ($del['status'] !== 'Delivered'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="delivery_id" value="<?= $del['id'] ?>">
                                    <?php if ($del['status'] === 'Pending'): ?>
                                        <input type="hidden" name="new_status" value="In Transit">
                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                            <i class="bi bi-play"></i> Start
                                        </button>
                                    <?php elseif ($del['status'] === 'In Transit'): ?>
                                        <input type="hidden" name="new_status" value="Delivered">
                                        <button type="submit" name="update_status" class="btn btn-sm btn-success">
                                            <i class="bi bi-check-lg"></i> Complete
                                        </button>
                                    <?php endif; ?>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($del['current_latitude'] && $del['current_longitude']): ?>
                                <a href="track_deliveries.php?id=<?= $del['id'] ?>" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-geo-alt"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>