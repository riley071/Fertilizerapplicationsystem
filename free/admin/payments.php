<?php
session_start();
include('../includes/db.php');


if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Handle payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $payment_id = (int) $_POST['payment_id'];
    $new_status = $_POST['payment_status'];
    
    $stmt = $conn->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $payment_id);
    
    if ($stmt->execute()) {
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address, affected_record_id, affected_table) VALUES (?, ?, ?, ?, 'payments')");
        $action = "Updated payment status to $new_status";
        $ip = $_SERVER['REMOTE_ADDR'];  
        $log_stmt->bind_param("issi", $user_id, $action, $ip, $payment_id);
        $log_stmt->execute();
        $log_stmt->close();
        
        $success = "Payment status updated successfully!";
    } else {
        $error = "Failed to update payment status.";
    }
    $stmt->close();
}

// Fetch filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT p.*, o.quantity, o.order_date, o.status as order_status,
          f.name as fertilizer_name, u.full_name, s.company_name
          FROM payments p
          LEFT JOIN orders o ON p.order_id = o.id
          LEFT JOIN fertilizers f ON o.fertilizer_id = f.id
          LEFT JOIN suppliers s ON o.supplier_id = s.id
          LEFT JOIN users u ON s.user_id = u.id
          WHERE 1=1";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $query .= " AND p.payment_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND DATE(p.payment_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(p.payment_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (u.full_name LIKE ? OR s.company_name LIKE ? OR p.transaction_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY p.payment_date DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM payments")->fetch_assoc()['count'];
$stats['completed'] = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'Completed'")->fetch_assoc()['count'];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'Pending'")->fetch_assoc()['count'];
$stats['failed'] = $conn->query("SELECT COUNT(*) as count FROM payments WHERE payment_status = 'Failed'")->fetch_assoc()['count'];
$stats['total_revenue'] = $conn->query("SELECT SUM(amount_paid) as total FROM payments WHERE payment_status = 'Completed'")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payments Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.pending { border-color: #ffc107; }
        .stat-card.completed { border-color: #28a745; }
        .stat-card.failed { border-color: #dc3545; }
        .stat-card.revenue { border-color: #0d6efd; }
        .table-hover tbody tr:hover { background-color: #f8f9fa; }
        .status-badge {
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
   <div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary"><i class="bi bi-credit-card"></i> Payments Management</h3>
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

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card pending border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Pending</div>
                                <h4 class="mb-0"><?= number_format($stats['pending']) ?></h4>
                            </div>
                            <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card completed border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Completed</div>
                                <h4 class="mb-0"><?= number_format($stats['completed']) ?></h4>
                            </div>
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card failed border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Failed</div>
                                <h4 class="mb-0"><?= number_format($stats['failed']) ?></h4>
                            </div>
                            <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card revenue border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Revenue</div>
                                <h4 class="mb-0">MWK <?= number_format($stats['total_revenue'], 2) ?></h4>
                            </div>
                            <i class="bi bi-currency-dollar text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="Failed" <?= $status_filter === 'Failed' ? 'selected' : '' ?>>Failed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Supplier, Transaction ID..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                        <a href="payments.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-table"></i> All Payments (<?= count($payments) ?>)</h5>
                <button class="btn btn-sm btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Export Report
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Supplier</th>
                                <th>Fertilizer</th>
                                <th>Total Price</th>
                                <th>Subsidy</th>
                                <th>Amount Paid</th>
                                <th>Payment Method</th>
                                <th>Transaction ID</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                        <p class="mb-0">No payments found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td>#<?= $payment['id'] ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($payment['full_name'] ?? 'N/A') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($payment['company_name'] ?? '') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($payment['fertilizer_name'] ?? 'N/A') ?></td>
                                        <td>MWK <?= number_format($payment['total_price'], 2) ?></td>
                                        <td>
                                            <?php if ($payment['subsidy']): ?>
                                                <span class="text-success">MWK <?= number_format($payment['subsidy'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-semibold">MWK <?= number_format($payment['amount_paid'], 2) ?></td>
                                        <td>
                                            <?php if ($payment['payment_method']): ?>
                                                <span class="badge bg-secondary"><?= htmlspecialchars($payment['payment_method']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($payment['transaction_id']): ?>
                                                <code class="small"><?= htmlspecialchars($payment['transaction_id']) ?></code>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $badge_class = [
                                                'Pending' => 'bg-warning',
                                                'Completed' => 'bg-success',
                                                'Failed' => 'bg-danger'
                                            ][$payment['payment_status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge status-badge <?= $badge_class ?>">
                                                <?= htmlspecialchars($payment['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= date('M d, Y', strtotime($payment['payment_date'])) ?></small><br>
                                            <small class="text-muted"><?= date('H:i', strtotime($payment['payment_date'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $payment['id'] ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#updateModal<?= $payment['id'] ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Modals Section -->
        <?php foreach ($payments as $payment): ?>
            <!-- View Modal -->
            <div class="modal fade" id="viewModal<?= $payment['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Payment Details #<?= $payment['id'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <th width="40%">Order ID</th>
                                                            <td>#<?= $payment['order_id'] ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Supplier</th>
                                                            <td><?= htmlspecialchars($payment['full_name'] ?? 'N/A') ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Company</th>
                                                            <td><?= htmlspecialchars($payment['company_name'] ?? 'N/A') ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Fertilizer</th>
                                                            <td><?= htmlspecialchars($payment['fertilizer_name'] ?? 'N/A') ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Quantity</th>
                                                            <td><?= number_format($payment['quantity']) ?> units</td>
                                                        </tr>
                                                        <tr>
                                                            <th>Total Price</th>
                                                            <td>MWK <?= number_format($payment['total_price'], 2) ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Subsidy</th>
                                                            <td class="text-success">MWK <?= number_format($payment['subsidy'], 2) ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Amount Paid</th>
                                                            <td class="fw-bold">MWK <?= number_format($payment['amount_paid'], 2) ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Payment Method</th>
                                                            <td><?= htmlspecialchars($payment['payment_method'] ?? 'N/A') ?></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Transaction ID</th>
                                                            <td><code><?= htmlspecialchars($payment['transaction_id'] ?? 'N/A') ?></code></td>
                                                        </tr>
                                                        <tr>
                                                            <th>Status</th>
                                                            <td>
                                                                <span class="badge <?= $badge_class ?>">
                                                                    <?= htmlspecialchars($payment['payment_status']) ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>Payment Date</th>
                                                            <td><?= date('M d, Y H:i', strtotime($payment['payment_date'])) ?></td>
                                                        </tr>
                                                        <?php if ($payment['receipt_path']): ?>
                                                        <tr>
                                                            <th>Receipt</th>
                                                            <td><a href="<?= htmlspecialchars($payment['receipt_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View Receipt</a></td>
                                                        </tr>
                                                        <?php endif; ?>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Update Modal -->
                                    <div class="modal fade" id="updateModal<?= $payment['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Payment Status</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                                                        <div class="mb-3">
                                                            <label class="form-label">Payment ID</label>
                                                            <input type="text" class="form-control" value="#<?= $payment['id'] ?>" readonly>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Current Status</label>
                                                            <input type="text" class="form-control" value="<?= $payment['payment_status'] ?>" readonly>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">New Status <span class="text-danger">*</span></label>
                                                            <select name="payment_status" class="form-select" required>
                                                                <option value="Pending" <?= $payment['payment_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                                                <option value="Completed" <?= $payment['payment_status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                                                                <option value="Failed" <?= $payment['payment_status'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="update_payment" class="btn btn-success">
                                                            <i class="bi bi-check-lg"></i> Update Status
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                    </div>
                                    <!-- End Update Modal -->
        <?php endforeach; ?>
        <!-- End Modals Section -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>