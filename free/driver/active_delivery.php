<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Handle delivery status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $delivery_id = (int) $_POST['delivery_id'];
    $new_status = $_POST['status'];
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    
    $stmt = $conn->prepare("UPDATE deliveries SET status = ?, current_latitude = ?, current_longitude = ?, last_updated = NOW() WHERE id = ? AND driver_id = ?");
    $stmt->bind_param("sddi", $new_status, $latitude, $longitude, $delivery_id, $driver_id);
    
    if ($stmt->execute()) {
        // If marked as delivered, update delivered_on timestamp
        if ($new_status === 'Delivered') {
            $update_stmt = $conn->prepare("UPDATE deliveries SET delivered_on = NOW() WHERE id = ?");
            $update_stmt->bind_param("i", $delivery_id);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address, affected_record_id, affected_table) VALUES (?, ?, ?, ?, 'deliveries')");
        $action = "Updated delivery status to $new_status";
        $ip = $_SERVER['REMOTE_ADDR'];
        $log_stmt->bind_param("issi", $driver_id, $action, $ip, $delivery_id);
        $log_stmt->execute();
        $log_stmt->close();
        
        $success = "Delivery status updated successfully!";
    } else {
        $error = "Failed to update delivery status.";
    }
    $stmt->close();
}

// Fetch active deliveries for this driver
$query = "SELECT d.*, o.quantity, o.total_price, o.order_date,
          f.name as fertilizer_name, f.type as fertilizer_type,
          s.company_name, u.full_name as supplier_name, u.phone as supplier_phone
          FROM deliveries d
          LEFT JOIN orders o ON d.order_id = o.id
          LEFT JOIN fertilizers f ON o.fertilizer_id = f.id
          LEFT JOIN suppliers s ON o.supplier_id = s.id
          LEFT JOIN users u ON s.user_id = u.id
          WHERE d.driver_id = ? AND d.status IN ('Pending', 'In Transit')
          ORDER BY d.expected_arrival ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats = [];
$stats['pending'] = $conn->query("SELECT COUNT(*) as count FROM deliveries WHERE driver_id = $driver_id AND status = 'Pending'")->fetch_assoc()['count'];
$stats['in_transit'] = $conn->query("SELECT COUNT(*) as count FROM deliveries WHERE driver_id = $driver_id AND status = 'In Transit'")->fetch_assoc()['count'];
$stats['today_deliveries'] = $conn->query("SELECT COUNT(*) as count FROM deliveries WHERE driver_id = $driver_id AND DATE(expected_arrival) = CURDATE()")->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Active Deliveries</title>
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
        .stat-card.in-transit { border-color: #0d6efd; }
        .stat-card.today { border-color: #28a745; }
        .delivery-card {
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        .delivery-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left-color: #28a745;
        }
        .delivery-card.pending { background: #fff8e1; }
        .delivery-card.in-transit { background: #e3f2fd; }
        .map-container {
            height: 300px;
            border-radius: 10px;
            overflow: hidden;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success"><i class="bi bi-truck"></i> Active Deliveries</h3>
            <button class="btn btn-success" onclick="updateLocation()">
                <i class="bi bi-geo-alt"></i> Update My Location
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
            <div class="col-md-4">
                <div class="card stat-card pending border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Pending</div>
                                <h4 class="mb-0"><?= $stats['pending'] ?></h4>
                            </div>
                            <i class="bi bi-clock-history text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card in-transit border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">In Transit</div>
                                <h4 class="mb-0"><?= $stats['in_transit'] ?></h4>
                            </div>
                            <i class="bi bi-truck text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card today border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small">Today's Deliveries</div>
                                <h4 class="mb-0"><?= $stats['today_deliveries'] ?></h4>
                            </div>
                            <i class="bi bi-calendar-check text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deliveries List -->
        <?php if (empty($deliveries)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3 text-muted">No Active Deliveries</h4>
                    <p class="text-muted">You have no pending or in-transit deliveries at the moment.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($deliveries as $delivery): ?>
                    <div class="col-md-6">
                        <div class="card delivery-card <?= strtolower(str_replace(' ', '-', $delivery['status'])) ?> border-0 shadow-sm">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-box-seam"></i> Delivery #<?= $delivery['id'] ?>
                                </h5>
                                <span class="badge <?= $delivery['status'] === 'Pending' ? 'bg-warning' : 'bg-primary' ?>">
                                    <?= htmlspecialchars($delivery['status']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <!-- Supplier Information -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-person"></i> Supplier Details</h6>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="fw-semibold"><?= htmlspecialchars($delivery['supplier_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($delivery['company_name'] ?? 'N/A') ?></small>
                                        </div>
                                        <a href="tel:<?= htmlspecialchars($delivery['supplier_phone']) ?>" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-telephone"></i> Call
                                        </a>
                                    </div>
                                </div>

                                <!-- Order Details -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-clipboard-check"></i> Order Details</h6>
                                    <table class="table table-sm table-borderless mb-0">
                                        <tr>
                                            <td class="text-muted">Fertilizer:</td>
                                            <td class="text-end fw-semibold"><?= htmlspecialchars($delivery['fertilizer_name']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Type:</td>
                                            <td class="text-end"><span class="badge bg-secondary"><?= htmlspecialchars($delivery['fertilizer_type']) ?></span></td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Quantity:</td>
                                            <td class="text-end fw-semibold"><?= number_format($delivery['quantity']) ?> units</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Order Value:</td>
                                            <td class="text-end fw-semibold">MWK <?= number_format($delivery['total_price'], 2) ?></td>
                                        </tr>
                                    </table>
                                </div>

                                <!-- Delivery Schedule -->
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2"><i class="bi bi-calendar-event"></i> Schedule</h6>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <small class="text-muted">Expected Arrival</small>
                                            <div class="fw-semibold"><?= date('M d, Y - H:i', strtotime($delivery['expected_arrival'])) ?></div>
                                        </div>
                                        <?php
                                        $now = time();
                                        $expected = strtotime($delivery['expected_arrival']);
                                        $diff = $expected - $now;
                                        $hours_left = floor($diff / 3600);
                                        ?>
                                        <?php if ($hours_left > 0): ?>
                                            <div class="text-end">
                                                <small class="text-muted">Time Remaining</small>
                                                <div class="fw-semibold text-primary"><?= $hours_left ?> hours</div>
                                            </div>
                                        <?php elseif ($hours_left > -24): ?>
                                            <div class="text-end">
                                                <small class="text-danger">Overdue</small>
                                                <div class="fw-semibold text-danger"><?= abs($hours_left) ?> hours late</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Location Info -->
                                <?php if ($delivery['current_latitude'] && $delivery['current_longitude']): ?>
                                    <div class="mb-3">
                                        <h6 class="text-muted mb-2"><i class="bi bi-geo-alt"></i> Last Known Location</h6>
                                        <small class="text-muted">
                                            Updated: <?= date('M d, H:i', strtotime($delivery['last_updated'])) ?>
                                        </small>
                                    </div>
                                <?php endif; ?>

                                <!-- Action Buttons -->
                                <div class="d-flex gap-2">
                                    <?php if ($delivery['status'] === 'Pending'): ?>
                                        <button class="btn btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#startModal<?= $delivery['id'] ?>">
                                            <i class="bi bi-play-circle"></i> Start Delivery
                                        </button>
                                    <?php elseif ($delivery['status'] === 'In Transit'): ?>
                                        <button class="btn btn-info flex-fill" data-bs-toggle="modal" data-bs-target="#updateLocationModal<?= $delivery['id'] ?>">
                                            <i class="bi bi-geo-alt"></i> Update Location
                                        </button>
                                        <button class="btn btn-success flex-fill" data-bs-toggle="modal" data-bs-target="#deliveredModal<?= $delivery['id'] ?>">
                                            <i class="bi bi-check-circle"></i> Mark Delivered
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Start Delivery Modal -->
                    <div class="modal fade" id="startModal<?= $delivery['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Start Delivery #<?= $delivery['id'] ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                                        <input type="hidden" name="status" value="In Transit">
                                        <input type="hidden" name="latitude" id="start_lat_<?= $delivery['id'] ?>">
                                        <input type="hidden" name="longitude" id="start_lng_<?= $delivery['id'] ?>">
                                        
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i> Your current location will be recorded when you start this delivery.
                                        </div>
                                        
                                        <p><strong>Supplier:</strong> <?= htmlspecialchars($delivery['supplier_name']) ?></p>
                                        <p><strong>Fertilizer:</strong> <?= htmlspecialchars($delivery['fertilizer_name']) ?></p>
                                        <p><strong>Quantity:</strong> <?= number_format($delivery['quantity']) ?> units</p>
                                        
                                        <p class="text-muted mb-0">Are you ready to start this delivery?</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="update_status" class="btn btn-primary" onclick="getLocation('start_lat_<?= $delivery['id'] ?>', 'start_lng_<?= $delivery['id'] ?>')">
                                            <i class="bi bi-play-circle"></i> Start Delivery
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Update Location Modal -->
                    <div class="modal fade" id="updateLocationModal<?= $delivery['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Update Location</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                                        <input type="hidden" name="status" value="In Transit">
                                        <input type="hidden" name="latitude" id="update_lat_<?= $delivery['id'] ?>">
                                        <input type="hidden" name="longitude" id="update_lng_<?= $delivery['id'] ?>">
                                        
                                        <div class="alert alert-info">
                                            <i class="bi bi-geo-alt"></i> Your current GPS location will be shared with the supplier.
                                        </div>
                                        
                                        <p class="mb-0">Click "Update Location" to share your current position.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="update_status" class="btn btn-info" onclick="getLocation('update_lat_<?= $delivery['id'] ?>', 'update_lng_<?= $delivery['id'] ?>')">
                                            <i class="bi bi-geo-alt"></i> Update Location
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Mark Delivered Modal -->
                    <div class="modal fade" id="deliveredModal<?= $delivery['id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Confirm Delivery</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                                        <input type="hidden" name="status" value="Delivered">
                                        <input type="hidden" name="latitude" id="delivered_lat_<?= $delivery['id'] ?>">
                                        <input type="hidden" name="longitude" id="delivered_lng_<?= $delivery['id'] ?>">
                                        
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> Confirm that the delivery has been completed successfully.
                                        </div>
                                        
                                        <p><strong>Delivery #<?= $delivery['id'] ?></strong></p>
                                        <p><strong>Supplier:</strong> <?= htmlspecialchars($delivery['supplier_name']) ?></p>
                                        <p><strong>Items:</strong> <?= number_format($delivery['quantity']) ?> units of <?= htmlspecialchars($delivery['fertilizer_name']) ?></p>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="confirm_<?= $delivery['id'] ?>" required>
                                            <label class="form-check-label" for="confirm_<?= $delivery['id'] ?>">
                                                I confirm that all items have been delivered to the supplier
                                            </label>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" name="update_status" class="btn btn-success" onclick="getLocation('delivered_lat_<?= $delivery['id'] ?>', 'delivered_lng_<?= $delivery['id'] ?>')">
                                            <i class="bi bi-check-circle"></i> Confirm Delivery
                                        </button>
                                    </div>
                                </form>
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
// Get current location
function getLocation(latFieldId, lngFieldId) {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById(latFieldId).value = position.coords.latitude;
                document.getElementById(lngFieldId).value = position.coords.longitude;
            },
            function(error) {
                console.error('Geolocation error:', error);
                alert('Unable to get location. Please enable GPS and try again.');
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}

// Update driver location
function updateLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                alert('Location captured: ' + position.coords.latitude + ', ' + position.coords.longitude);
                // You can send this to the server via AJAX if needed
            },
            function(error) {
                alert('Unable to get location. Please enable GPS.');
            }
        );
    } else {
        alert('Geolocation is not supported by your browser.');
    }
}
</script>
</body>
</html>