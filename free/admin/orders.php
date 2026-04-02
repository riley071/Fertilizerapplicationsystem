<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = "";

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $order_id = (int) ($_POST['order_id'] ?? 0);
    
    if ($action === 'approve' && $order_id) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'Approved' WHERE id = ? AND status = 'Requested'");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Order #$order_id approved successfully!";
        } else {
            $error = "Failed to approve order or order already processed.";
        }
        $stmt->close();
    }
    
    elseif ($action === 'reject' && $order_id) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ? AND status = 'Requested'");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Order #$order_id rejected successfully!";
        } else {
            $error = "Failed to reject order.";
        }
        $stmt->close();
    }
    
    elseif ($action === 'dispatch' && $order_id) {
        $stmt = $conn->prepare("UPDATE orders SET status = 'Dispatched' WHERE id = ? AND status = 'Approved'");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Order #$order_id dispatched successfully!";
        } else {
            $error = "Failed to dispatch order.";
        }
        $stmt->close();
    }
    
    elseif ($action === 'assign_driver' && $order_id) {
        $driver_id = (int) ($_POST['driver_id'] ?? 0);
        $expected_arrival = $_POST['expected_arrival'] ?? null;
        
        if ($driver_id && $expected_arrival) {
            // Get order and supplier info
            $stmt = $conn->prepare("SELECT supplier_id FROM orders WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($order) {
                // Check if delivery already exists
                $check = $conn->prepare("SELECT id FROM deliveries WHERE order_id = ?");
                $check->bind_param("i", $order_id);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();
                $check->close();
                
                if ($existing) {
                    // Update existing delivery
                    $stmt = $conn->prepare("UPDATE deliveries SET driver_id = ?, expected_arrival = ?, status = 'Pending' WHERE order_id = ?");
                    $stmt->bind_param("isi", $driver_id, $expected_arrival, $order_id);
                } else {
                    // Insert new delivery
                    $admin_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("INSERT INTO deliveries (order_id, admin_id, supplier_id, driver_id, expected_arrival, status) VALUES (?, ?, ?, ?, ?, 'Pending')");
                    $stmt->bind_param("iiiis", $order_id, $admin_id, $order['supplier_id'], $driver_id, $expected_arrival);
                }
                
                if ($stmt->execute()) {
                    // Update order status to Dispatched
                    $conn->query("UPDATE orders SET status = 'Dispatched' WHERE id = $order_id");
                    $success = "Driver assigned successfully to Order #$order_id!";
                } else {
                    $error = "Failed to assign driver.";
                }
                $stmt->close();
            }
        } else {
            $error = "Please select a driver and provide expected arrival time.";
        }
    }
}

// Fetch all orders with related info
$orders = $conn->query("
    SELECT 
        o.id,
        o.quantity,
        o.price_per_unit,
        o.total_price,
        o.status,
        o.order_date,
        s.company_name as supplier_name,
        u.full_name as supplier_contact,
        u.phone as supplier_phone,
        f.name as fertilizer_name,
        f.type as fertilizer_type,
        p.payment_status,
        p.amount_paid,
        d.id as delivery_id,
        d.driver_id,
        d.status as delivery_status,
        d.expected_arrival,
        drv.full_name as driver_name
    FROM orders o
    JOIN suppliers s ON o.supplier_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN payments p ON o.id = p.order_id
    LEFT JOIN deliveries d ON o.id = d.order_id
    LEFT JOIN users drv ON d.driver_id = drv.id
    ORDER BY 
        CASE o.status
            WHEN 'Requested' THEN 1
            WHEN 'Approved' THEN 2
            WHEN 'Dispatched' THEN 3
            WHEN 'Delivered' THEN 4
            ELSE 5
        END,
        o.order_date DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch available drivers
$drivers = $conn->query("
    SELECT id, full_name, phone 
    FROM users 
    WHERE role = 'driver' AND status = 'active'
    ORDER BY full_name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Orders | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .order-card {
            transition: all 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .order-card.status-requested { border-left-color: #ffc107; background: #fff9e6; }
        .order-card.status-approved { border-left-color: #0dcaf0; background: #e6f7ff; }
        .order-card.status-dispatched { border-left-color: #0d6efd; background: #e6f0ff; }
        .order-card.status-delivered { border-left-color: #198754; background: #e6f7ed; }
        .order-card.status-cancelled { border-left-color: #dc3545; background: #ffe6e6; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-primary mb-4"><i class="bi bi-clipboard-check"></i> Manage Orders</h3>

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

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-warning">
                    <div class="card-body">
                        <h6 class="text-muted">Pending Approval</h6>
                        <h3 class="text-warning"><?= count(array_filter($orders, fn($o) => $o['status'] === 'Requested')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info">
                    <div class="card-body">
                        <h6 class="text-muted">Approved</h6>
                        <h3 class="text-info"><?= count(array_filter($orders, fn($o) => $o['status'] === 'Approved')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="text-muted">In Transit</h6>
                        <h3 class="text-primary"><?= count(array_filter($orders, fn($o) => $o['status'] === 'Dispatched')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success">
                    <div class="card-body">
                        <h6 class="text-muted">Delivered</h6>
                        <h3 class="text-success"><?= count(array_filter($orders, fn($o) => $o['status'] === 'Delivered')) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Orders Found</h4>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): 
                $statusClass = 'status-' . strtolower($order['status']);
                $statusBadge = match($order['status']) {
                    'Requested' => 'warning',
                    'Approved' => 'info',
                    'Dispatched' => 'primary',
                    'Delivered' => 'success',
                    'Cancelled' => 'danger',
                    default => 'secondary'
                };
            ?>
            <div class="card order-card mb-3 <?= $statusClass ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        Order #<?= $order['id'] ?> - <?= htmlspecialchars($order['fertilizer_name']) ?>
                                    </h5>
                                    <p class="text-muted mb-0">
                                        <small>
                                            <i class="bi bi-building"></i> <?= htmlspecialchars($order['supplier_name']) ?>
                                            • <i class="bi bi-person"></i> <?= htmlspecialchars($order['supplier_contact']) ?>
                                            • <i class="bi bi-phone"></i> <?= htmlspecialchars($order['supplier_phone']) ?>
                                        </small>
                                    </p>
                                </div>
                                <span class="badge bg-<?= $statusBadge ?> fs-6"><?= $order['status'] ?></span>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-3">
                                    <small class="text-muted">Type</small>
                                    <div><?= htmlspecialchars($order['fertilizer_type']) ?></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Quantity</small>
                                    <div><strong><?= $order['quantity'] ?> units</strong></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Total Price</small>
                                    <div class="text-success"><strong>MWK <?= number_format($order['total_price'], 0) ?></strong></div>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted">Order Date</small>
                                    <div><?= date('M d, Y', strtotime($order['order_date'])) ?></div>
                                </div>
                            </div>

                            <?php if ($order['payment_status']): ?>
                            <div class="mt-3">
                                <span class="badge bg-<?= $order['payment_status'] === 'Completed' ? 'success' : 'warning' ?>">
                                    <i class="bi bi-credit-card"></i> Payment: <?= $order['payment_status'] ?>
                                </span>
                                <?php if ($order['amount_paid']): ?>
                                    <span class="ms-2">MWK <?= number_format($order['amount_paid'], 0) ?> paid</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <?php if ($order['driver_name']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="bi bi-truck"></i> Driver: <strong><?= htmlspecialchars($order['driver_name']) ?></strong>
                                    <?php if ($order['expected_arrival']): ?>
                                        • ETA: <?= date('M d, H:i', strtotime($order['expected_arrival'])) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 border-start">
                            <h6 class="mb-3"><i class="bi bi-gear"></i> Actions</h6>
                            
                            <?php if ($order['status'] === 'Requested'): ?>
                                <form method="POST" class="mb-2">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <div class="d-grid gap-2">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">
                                            <i class="bi bi-check-circle"></i> Approve Order
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('Reject this order?')">
                                            <i class="bi bi-x-circle"></i> Reject Order
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>

                            <?php if ($order['status'] === 'Approved'): ?>
                                <button class="btn btn-primary btn-sm w-100 mb-2" data-bs-toggle="modal" 
                                        data-bs-target="#assignDriverModal<?= $order['id'] ?>">
                                    <i class="bi bi-person-plus"></i> Assign Driver
                                </button>
                                
                                <!-- Assign Driver Modal -->
                                <div class="modal fade" id="assignDriverModal<?= $order['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header bg-primary text-white">
                                                <h5 class="modal-title">Assign Driver to Order #<?= $order['id'] ?></h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="assign_driver">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Select Driver <span class="text-danger">*</span></label>
                                                        <select name="driver_id" class="form-select" required>
                                                            <option value="">Choose a driver...</option>
                                                            <?php foreach ($drivers as $driver): ?>
                                                                <option value="<?= $driver['id'] ?>">
                                                                    <?= htmlspecialchars($driver['full_name']) ?> 
                                                                    (<?= htmlspecialchars($driver['phone']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Expected Arrival <span class="text-danger">*</span></label>
                                                        <input type="datetime-local" name="expected_arrival" class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Assign Driver</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if ($order['status'] === 'Dispatched'): ?>
                                <div class="alert alert-info small mb-0">
                                    <i class="bi bi-info-circle"></i> Order is in transit. Driver will mark as delivered.
                                </div>
                            <?php endif; ?>

                            <?php if ($order['status'] === 'Delivered'): ?>
                                <div class="alert alert-success small mb-0">
                                    <i class="bi bi-check-circle"></i> Order completed successfully!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>