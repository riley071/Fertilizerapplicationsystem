<?php
// admin/inventory_management.php
session_start();
include('../includes/db.php');
require_once('../includes/sms_helper.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";
$sms = new SMSNotification();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_fertilizer'])) {
        $name = trim($_POST['name']);
        $type = trim($_POST['type']);
        $npk_value = trim($_POST['npk_value']);
        $batch_no = trim($_POST['batch_no']);
        $price_per_unit = floatval($_POST['price_per_unit']);
        $stock = intval($_POST['stock']);
        $minimum_stock = intval($_POST['minimum_stock']);
        $expiry_date = $_POST['expiry_date'];
        $depot_location = trim($_POST['depot_location']);
        $manufacture_date = $_POST['manufacture_date'];
        $manufacturer = trim($_POST['manufacturer']);
        
        $stmt = $conn->prepare("INSERT INTO fertilizers (admin_id, name, type, npk_value, batch_no, price_per_unit, stock, stock_remaining, minimum_stock, expiry_date, depot_location, manufacture_date, manufacturer, certified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("issssdiiiisss", $user_id, $name, $type, $npk_value, $batch_no, $price_per_unit, $stock, $stock, $minimum_stock, $expiry_date, $depot_location, $manufacture_date, $manufacturer);
        
        if ($stmt->execute()) {
            $success = "Fertilizer added successfully with batch tracking!";
            
            // Log action
            $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address, affected_table) VALUES (?, ?, ?, 'fertilizers')");
            $action = "Added new fertilizer: $name (Batch: $batch_no)";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iss", $user_id, $action, $ip);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $error = "Failed to add fertilizer.";
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_stock'])) {
        $fertilizer_id = intval($_POST['fertilizer_id']);
        $adjustment = intval($_POST['adjustment']);
        $adjustment_type = $_POST['adjustment_type'];
        $reason = trim($_POST['reason']);
        
        if ($adjustment_type === 'add') {
            $sql = "UPDATE fertilizers SET stock_remaining = stock_remaining + ? WHERE id = ?";
        } else {
            $sql = "UPDATE fertilizers SET stock_remaining = stock_remaining - ? WHERE id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $adjustment, $fertilizer_id);
        
        if ($stmt->execute()) {
            // Log stock movement
            $log_stmt = $conn->prepare("INSERT INTO stock_movements (fertilizer_id, quantity, movement_type, reason, performed_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $movement_type = $adjustment_type === 'add' ? 'IN' : 'OUT';
            $log_stmt->bind_param("iissi", $fertilizer_id, $adjustment, $movement_type, $reason, $user_id);
            $log_stmt->execute();
            $log_stmt->close();
            
            $success = "Stock updated successfully!";
        } else {
            $error = "Failed to update stock.";
        }
        $stmt->close();
    }
}

// Fetch all fertilizers with expiry status
$fertilizers = $conn->query("
    SELECT f.*, 
    DATEDIFF(f.expiry_date, CURDATE()) as days_to_expiry,
    CASE 
        WHEN DATEDIFF(f.expiry_date, CURDATE()) < 0 THEN 'expired'
        WHEN DATEDIFF(f.expiry_date, CURDATE()) <= 30 THEN 'critical'
        WHEN DATEDIFF(f.expiry_date, CURDATE()) <= 60 THEN 'warning'
        WHEN DATEDIFF(f.expiry_date, CURDATE()) <= 90 THEN 'notice'
        ELSE 'good'
    END as expiry_status
    FROM fertilizers f
    ORDER BY f.expiry_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Get stock statistics
$stats = [];
$stats['total_items'] = count($fertilizers);
$stats['low_stock'] = $conn->query("SELECT COUNT(*) as count FROM fertilizers WHERE stock_remaining <= minimum_stock")->fetch_assoc()['count'];
$stats['expired'] = $conn->query("SELECT COUNT(*) as count FROM fertilizers WHERE expiry_date < CURDATE()")->fetch_assoc()['count'];
$stats['expiring_soon'] = $conn->query("SELECT COUNT(*) as count FROM fertilizers WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

// Get expiring items for alerts
$expiring_items = $conn->query("
    SELECT * FROM fertilizers 
    WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
    ORDER BY expiry_date ASC
")->fetch_all(MYSQLI_ASSOC);

// Get stock movements history
$stock_movements = $conn->query("
    SELECT sm.*, f.name as fertilizer_name, u.full_name 
    FROM stock_movements sm
    LEFT JOIN fertilizers f ON sm.fertilizer_id = f.id
    LEFT JOIN users u ON sm.performed_by = u.id
    ORDER BY sm.created_at DESC
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .stat-card { border-left: 4px solid; transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card.danger { border-color: #dc3545; }
        .stat-card.warning { border-color: #ffc107; }
        .stat-card.info { border-color: #17a2b8; }
        .stat-card.success { border-color: #28a745; }
        .expiry-badge.expired { background: #dc3545; }
        .expiry-badge.critical { background: #ff6b6b; }
        .expiry-badge.warning { background: #ffc107; color: #000; }
        .expiry-badge.notice { background: #17a2b8; }
        .expiry-badge.good { background: #28a745; }
        .stock-low { background: #fff3cd; }
        .stock-critical { background: #f8d7da; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-primary"><i class="bi bi-boxes"></i> Inventory Management</h3>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addFertilizerModal">
                <i class="bi bi-plus-lg"></i> Add Fertilizer
            </button>
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
                <div class="card stat-card info border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Total Items</div>
                                <h4 class="mb-0"><?= $stats['total_items'] ?></h4>
                            </div>
                            <i class="bi bi-box-seam text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card warning border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Low Stock</div>
                                <h4 class="mb-0"><?= $stats['low_stock'] ?></h4>
                            </div>
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card danger border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Expired</div>
                                <h4 class="mb-0"><?= $stats['expired'] ?></h4>
                            </div>
                            <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card success border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Expiring (30d)</div>
                                <h4 class="mb-0"><?= $stats['expiring_soon'] ?></h4>
                            </div>
                            <i class="bi bi-clock-history text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiry Alerts -->
        <?php if (!empty($expiring_items)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-bell"></i> Expiry Alerts</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Fertilizer</th>
                                <th>Batch No</th>
                                <th>Stock</th>
                                <th>Expiry Date</th>
                                <th>Days Remaining</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring_items as $item): 
                                $days = (int)((strtotime($item['expiry_date']) - time()) / 86400);
                                $status_class = $days < 0 ? 'danger' : ($days <= 30 ? 'danger' : ($days <= 60 ? 'warning' : 'info'));
                            ?>
                                <tr class="<?= $days <= 0 ? 'stock-critical' : ($days <= 30 ? 'stock-low' : '') ?>">
                                    <td><?= htmlspecialchars($item['name']) ?></td>
                                    <td><code><?= htmlspecialchars($item['batch_no']) ?></code></td>
                                    <td><?= $item['stock_remaining'] ?> units</td>
                                    <td><?= date('M d, Y', strtotime($item['expiry_date'])) ?></td>
                                    <td>
                                        <strong class="text-<?= $status_class ?>">
                                            <?= $days < 0 ? 'EXPIRED' : $days . ' days' ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ($days < 0): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php elseif ($days <= 30): ?>
                                            <span class="badge bg-danger">Critical</span>
                                        <?php elseif ($days <= 60): ?>
                                            <span class="badge bg-warning text-dark">Warning</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Notice</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Inventory Table -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-table"></i> All Fertilizers</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Batch No</th>
                                        <th>Type</th>
                                        <th>Stock</th>
                                        <th>Price</th>
                                        <th>Expiry</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fertilizers as $fert): 
                                        $stock_class = $fert['stock_remaining'] <= $fert['minimum_stock'] ? 'stock-critical' : '';
                                    ?>
                                        <tr class="<?= $stock_class ?>">
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($fert['name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($fert['npk_value']) ?></small>
                                            </td>
                                            <td><code><?= htmlspecialchars($fert['batch_no']) ?></code></td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($fert['type']) ?></span></td>
                                            <td>
                                                <div><?= $fert['stock_remaining'] ?> / <?= $fert['stock'] ?></div>
                                                <?php if ($fert['stock_remaining'] <= $fert['minimum_stock']): ?>
                                                    <small class="text-danger">⚠ Low Stock</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>MWK <?= number_format($fert['price_per_unit'], 2) ?></td>
                                            <td>
                                                <div><?= date('M d, Y', strtotime($fert['expiry_date'])) ?></div>
                                                <small class="text-muted"><?= abs($fert['days_to_expiry']) ?> days</small>
                                            </td>
                                            <td>
                                                <span class="badge expiry-badge <?= $fert['expiry_status'] ?>">
                                                    <?= ucfirst($fert['expiry_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?= $fert['id'] ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#stockModal<?= $fert['id'] ?>">
                                                        <i class="bi bi-plus-minus"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- View Details Modal -->
                                        <div class="modal fade" id="viewModal<?= $fert['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Fertilizer Details</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <table class="table table-sm">
                                                            <tr><th>Name</th><td><?= htmlspecialchars($fert['name']) ?></td></tr>
                                                            <tr><th>Batch Number</th><td><code><?= htmlspecialchars($fert['batch_no']) ?></code></td></tr>
                                                            <tr><th>Type</th><td><?= htmlspecialchars($fert['type']) ?></td></tr>
                                                            <tr><th>NPK Value</th><td><?= htmlspecialchars($fert['npk_value']) ?></td></tr>
                                                            <tr><th>Price per Unit</th><td>MWK <?= number_format($fert['price_per_unit'], 2) ?></td></tr>
                                                            <tr><th>Total Stock</th><td><?= $fert['stock'] ?> units</td></tr>
                                                            <tr><th>Remaining Stock</th><td><?= $fert['stock_remaining'] ?> units</td></tr>
                                                            <tr><th>Minimum Stock</th><td><?= $fert['minimum_stock'] ?> units</td></tr>
                                                            <tr><th>Manufacture Date</th><td><?= $fert['manufacture_date'] ? date('M d, Y', strtotime($fert['manufacture_date'])) : 'N/A' ?></td></tr>
                                                            <tr><th>Expiry Date</th><td><?= date('M d, Y', strtotime($fert['expiry_date'])) ?></td></tr>
                                                            <tr><th>Days to Expiry</th><td><?= $fert['days_to_expiry'] ?> days</td></tr>
                                                            <tr><th>Manufacturer</th><td><?= htmlspecialchars($fert['manufacturer'] ?? 'N/A') ?></td></tr>
                                                            <tr><th>Depot Location</th><td><?= htmlspecialchars($fert['depot_location']) ?></td></tr>
                                                            <tr><th>Certified</th><td><?= $fert['certified'] ? 'Yes' : 'No' ?></td></tr>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Stock Adjustment Modal -->
                                        <div class="modal fade" id="stockModal<?= $fert['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Adjust Stock</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="fertilizer_id" value="<?= $fert['id'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Fertilizer</label>
                                                                <input type="text" class="form-control" value="<?= htmlspecialchars($fert['name']) ?>" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Current Stock</label>
                                                                <input type="text" class="form-control" value="<?= $fert['stock_remaining'] ?> units" readonly>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Adjustment Type</label>
                                                                <select name="adjustment_type" class="form-select" required>
                                                                    <option value="add">Add Stock (IN)</option>
                                                                    <option value="remove">Remove Stock (OUT)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Quantity</label>
                                                                <input type="number" name="adjustment" class="form-control" min="1" required>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason</label>
                                                                <textarea name="reason" class="form-control" rows="2" required></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" name="update_stock" class="btn btn-success">Update Stock</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stock Movements History -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Stock Movements</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($stock_movements)): ?>
                            <p class="text-muted small mb-0">No stock movements yet</p>
                        <?php else: ?>
                            <?php foreach ($stock_movements as $movement): ?>
                                <div class="mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($movement['fertilizer_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($movement['full_name']) ?></small>
                                        </div>
                                        <span class="badge <?= $movement['movement_type'] === 'IN' ? 'bg-success' : 'bg-danger' ?>">
                                            <?= $movement['movement_type'] === 'IN' ? '+' : '-' ?><?= $movement['quantity'] ?>
                                        </span>
                                    </div>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($movement['reason']) ?></div>
                                    <div class="small text-muted"><?= date('M d, Y H:i', strtotime($movement['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Fertilizer Modal -->
<div class="modal fade" id="addFertilizerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Fertilizer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="Nitrogen">Nitrogen</option>
                                <option value="Phosphorus">Phosphorus</option>
                                <option value="Potassium">Potassium</option>
                                <option value="NPK">NPK Compound</option>
                                <option value="Organic">Organic</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">NPK Value</label>
                            <input type="text" name="npk_value" class="form-control" placeholder="e.g., 10-10-10">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Batch Number <span class="text-danger">*</span></label>
                            <input type="text" name="batch_no" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" name="manufacturer" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price per Unit (MWK) <span class="text-danger">*</span></label>
                            <input type="number" name="price_per_unit" class="form-control" step="0.01" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Initial Stock <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Stock <span class="text-danger">*</span></label>
                            <input type="number" name="minimum_stock" class="form-control" min="0" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Depot Location</label>
                            <input type="text" name="depot_location" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Manufacture Date</label>
                            <input type="date" name="manufacture_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fertilizer" class="btn btn-success">Add Fertilizer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// ===== ADDITIONAL DATABASE SCHEMA =====
/*
-- Add new columns to fertilizers table
ALTER TABLE `fertilizers` 
ADD COLUMN `manufacture_date` DATE NULL AFTER `expiry_date`,
ADD COLUMN `manufacturer` VARCHAR(255) NULL AFTER `manufacture_date`;

-- Create stock_movements table for tracking
CREATE TABLE `stock_movements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `fertilizer_id` INT(11) NOT NULL,
  `quantity` INT(11) NOT NULL,
  `movement_type` ENUM('IN', 'OUT') NOT NULL,
  `reason` TEXT NOT NULL,
  `performed_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fertilizer_id` (`fertilizer_id`),
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `fk_stock_movement_fertilizer` FOREIGN KEY (`fertilizer_id`) REFERENCES `fertilizers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movement_user` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create settings table for SMS configuration
CREATE TABLE `settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
?>