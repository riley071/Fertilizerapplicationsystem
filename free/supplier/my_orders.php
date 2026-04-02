<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Get supplier_id from suppliers table
$stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supplier_id = $supplier ? (int) $supplier['id'] : null;

if (!$supplier_id) {
    $error = "Supplier profile not found. Please complete your profile first.";
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id']) && $supplier_id) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];

    // Verify order belongs to this supplier
    $stmt = $conn->prepare("SELECT id, status FROM orders WHERE id = ? AND supplier_id = ?");
    $stmt->bind_param("ii", $order_id, $supplier_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        if ($action === 'cancel' && $order['status'] === 'Requested') {
            $update = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
            $update->bind_param("i", $order_id);
            
            if ($update->execute()) {
                $success = "Order cancelled successfully.";
            } else {
                $error = "Failed to cancel order.";
            }
            $update->close();
        } else {
            $error = "Cannot cancel order in current status.";
        }
    } else {
        $error = "Order not found or unauthorized.";
    }
}

// Fetch all orders for this supplier with payment info
$orders = [];
if ($supplier_id) {
    $stmt = $conn->prepare("
        SELECT 
            o.id, 
            o.quantity, 
            o.price_per_unit,
            o.total_price,
            o.status, 
            o.order_date,
            f.name AS fertilizer_name,
            f.type AS fertilizer_type,
            f.depot_location,
            p.amount_paid, 
            p.payment_status,
            p.payment_method,
            p.transaction_id,
            d.status AS delivery_status,
            d.expected_arrival,
            d.delivered_on
        FROM orders o
        JOIN fertilizers f ON o.fertilizer_id = f.id
        LEFT JOIN payments p ON o.id = p.order_id
        LEFT JOIN deliveries d ON o.id = d.order_id
        WHERE o.supplier_id = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Orders | Fertilizer System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .order-card {
            transition: all 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .order-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .order-card.status-requested { border-left-color: #ffc107; }
        .order-card.status-approved { border-left-color: #0dcaf0; }
        .order-card.status-dispatched { border-left-color: #0d6efd; }
        .order-card.status-delivered { border-left-color: #198754; }
        .order-card.status-cancelled { border-left-color: #dc3545; }
        .status-timeline {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        .timeline-step {
            text-align: center;
            flex: 1;
            position: relative;
        }
        .timeline-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            z-index: -1;
        }
        .timeline-step.active .timeline-icon {
            background: #198754;
            color: white;
        }
        .timeline-step.current .timeline-icon {
            background: #0d6efd;
            color: white;
        }
        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-bag-check"></i> My Orders</h3>
            <a href="place_order.php" class="btn btn-success">
                <i class="bi bi-plus-circle"></i> Place New Order
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

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="Requested">Requested</option>
                            <option value="Approved">Approved</option>
                            <option value="Dispatched">Dispatched</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterPayment">
                            <option value="">All Payments</option>
                            <option value="Pending">Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Failed">Failed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control" id="searchOrder" placeholder="Search by fertilizer name...">
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-bag-x display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Orders Yet</h4>
                    <p class="text-muted">Start by placing your first order for fertilizers</p>
                    <a href="place_order.php" class="btn btn-success">
                        <i class="bi bi-cart-plus"></i> Place Order
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div id="ordersContainer">
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
                    $paymentBadge = match($order['payment_status'] ?? 'Pending') {
                        'Completed' => 'success',
                        'Failed' => 'danger',
                        default => 'warning'
                    };
                ?>
                <div class="card order-card mb-3 <?= $statusClass ?>" 
                     data-status="<?= $order['status'] ?>" 
                     data-payment="<?= $order['payment_status'] ?? 'Pending' ?>"
                     data-name="<?= strtolower($order['fertilizer_name']) ?>">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="mb-1">
                                            <i class="bi bi-box-seam text-success"></i>
                                            <?= htmlspecialchars($order['fertilizer_name']) ?>
                                        </h5>
                                        <p class="text-muted mb-0">
                                            <small>
                                                <i class="bi bi-tag"></i> <?= htmlspecialchars($order['fertilizer_type']) ?>
                                                • Order #<?= $order['id'] ?>
                                                • <?= date('M d, Y', strtotime($order['order_date'])) ?>
                                            </small>
                                        </p>
                                    </div>
                                    <span class="badge bg-<?= $statusBadge ?> fs-6">
                                        <?= $order['status'] ?>
                                    </span>
                                </div>

                                <div class="row g-2 mt-2">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Quantity</small>
                                        <strong><?= $order['quantity'] ?> units</strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Unit Price</small>
                                        <strong>MWK <?= number_format($order['price_per_unit'], 0) ?></strong>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Total Price</small>
                                        <strong class="text-success">MWK <?= number_format($order['total_price'], 0) ?></strong>
                                    </div>
                                </div>

                                <?php if ($order['depot_location']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt"></i> Depot: <?= htmlspecialchars($order['depot_location']) ?>
                                    </small>
                                </div>
                                <?php endif; ?>

                                <!-- Status Timeline -->
                                <div class="status-timeline">
                                    <div class="timeline-step <?= in_array($order['status'], ['Requested', 'Approved', 'Dispatched', 'Delivered']) ? 'active' : '' ?> <?= $order['status'] === 'Requested' ? 'current' : '' ?>">
                                        <div class="timeline-icon"><i class="bi bi-file-text"></i></div>
                                        <small>Requested</small>
                                    </div>
                                    <div class="timeline-step <?= in_array($order['status'], ['Approved', 'Dispatched', 'Delivered']) ? 'active' : '' ?> <?= $order['status'] === 'Approved' ? 'current' : '' ?>">
                                        <div class="timeline-icon"><i class="bi bi-check-circle"></i></div>
                                        <small>Approved</small>
                                    </div>
                                    <div class="timeline-step <?= in_array($order['status'], ['Dispatched', 'Delivered']) ? 'active' : '' ?> <?= $order['status'] === 'Dispatched' ? 'current' : '' ?>">
                                        <div class="timeline-icon"><i class="bi bi-truck"></i></div>
                                        <small>Dispatched</small>
                                    </div>
                                    <div class="timeline-step <?= $order['status'] === 'Delivered' ? 'active current' : '' ?>">
                                        <div class="timeline-icon"><i class="bi bi-box-seam"></i></div>
                                        <small>Delivered</small>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4 border-start">
                                <h6 class="mb-3"><i class="bi bi-credit-card"></i> Payment Details</h6>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block">Status</small>
                                    <span class="badge bg-<?= $paymentBadge ?>">
                                        <?= htmlspecialchars($order['payment_status'] ?? 'Pending') ?>
                                    </span>
                                </div>

                                <?php if ($order['amount_paid']): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Amount Paid</small>
                                    <strong>MWK <?= number_format($order['amount_paid'], 0) ?></strong>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['payment_method']): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Method</small>
                                    <span><?= htmlspecialchars($order['payment_method']) ?></span>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['transaction_id']): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Transaction ID</small>
                                    <code class="small"><?= htmlspecialchars($order['transaction_id']) ?></code>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['delivery_status']): ?>
                                <hr>
                                <h6 class="mb-3"><i class="bi bi-truck"></i> Delivery</h6>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Status</small>
                                    <span class="badge bg-info"><?= htmlspecialchars($order['delivery_status']) ?></span>
                                </div>
                                <?php if ($order['expected_arrival']): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Expected</small>
                                    <span><?= date('M d, Y H:i', strtotime($order['expected_arrival'])) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($order['delivered_on']): ?>
                                <div class="mb-2">
                                    <small class="text-muted d-block">Delivered On</small>
                                    <span><?= date('M d, Y H:i', strtotime($order['delivered_on'])) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>

                                <hr>
                                <div class="d-grid gap-2">
                                    <?php if ($order['status'] === 'Requested'): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <button type="submit" name="action" value="cancel" class="btn btn-sm btn-outline-danger w-100">
                                                <i class="bi bi-x-circle"></i> Cancel Order
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['delivery_status'] === 'In Transit'): ?>
                                        <a href="track_delivery.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-primary w-100 mb-2">
                                            <i class="bi bi-geo-alt"></i> Track Delivery
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // Show payment button for approved/dispatched orders that haven't been paid
                                    $canPay = in_array($order['status'], ['Approved']) && 
                                             (!$order['payment_status'] || $order['payment_status'] === 'Pending');
                                    ?>
                                    
                                    <?php if ($canPay): ?>
                                        <a href="payments.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-success w-100">
                                            <i class="bi bi-credit-card"></i> Make Payment
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['payment_status'] === 'Completed'): ?>
                                        <button class="btn btn-sm btn-outline-success w-100" disabled>
                                            <i class="bi bi-check-circle"></i> Payment Complete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Filter functionality
document.getElementById('filterStatus').addEventListener('change', filterOrders);
document.getElementById('filterPayment').addEventListener('change', filterOrders);
document.getElementById('searchOrder').addEventListener('input', filterOrders);

function filterOrders() {
    const statusFilter = document.getElementById('filterStatus').value.toLowerCase();
    const paymentFilter = document.getElementById('filterPayment').value.toLowerCase();
    const searchText = document.getElementById('searchOrder').value.toLowerCase();
    
    document.querySelectorAll('.order-card').forEach(card => {
        const status = card.dataset.status.toLowerCase();
        const payment = card.dataset.payment.toLowerCase();
        const name = card.dataset.name;
        
        const statusMatch = !statusFilter || status === statusFilter;
        const paymentMatch = !paymentFilter || payment === paymentFilter;
        const searchMatch = !searchText || name.includes(searchText);
        
        if (statusMatch && paymentMatch && searchMatch) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}
</script>
</body>
</html>