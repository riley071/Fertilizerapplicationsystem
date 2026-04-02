<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Handle delivery actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $delivery_id = (int) ($_POST['delivery_id'] ?? 0);
    
    if ($action === 'start' && $delivery_id) {
        $stmt = $conn->prepare("UPDATE deliveries SET status = 'In Transit' WHERE id = ? AND driver_id = ? AND status = 'Pending'");
        $stmt->bind_param("ii", $delivery_id, $driver_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $success = "Delivery started! Status updated to In Transit.";
        } else {
            $error = "Failed to start delivery.";
        }
        $stmt->close();
    }
    
    elseif ($action === 'deliver' && $delivery_id) {
        $conn->begin_transaction();
        try {
            // Update delivery status
            $stmt = $conn->prepare("UPDATE deliveries SET status = 'Delivered', delivered_on = NOW() WHERE id = ? AND driver_id = ? AND status = 'In Transit'");
            $stmt->bind_param("ii", $delivery_id, $driver_id);
            $stmt->execute();
            $stmt->close();
            
            // Get order_id from delivery
            $stmt = $conn->prepare("SELECT order_id FROM deliveries WHERE id = ?");
            $stmt->bind_param("i", $delivery_id);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $order_id = $result['order_id'];
            $stmt->close();
            
            // Update order status
            $stmt = $conn->prepare("UPDATE orders SET status = 'Delivered' WHERE id = ?");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            $success = "Delivery completed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Failed to complete delivery: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'update_location' && $delivery_id) {
        $latitude = (float) ($_POST['latitude'] ?? 0);
        $longitude = (float) ($_POST['longitude'] ?? 0);
        
        if ($latitude && $longitude) {
            $stmt = $conn->prepare("UPDATE deliveries SET current_latitude = ?, current_longitude = ? WHERE id = ? AND driver_id = ?");
            $stmt->bind_param("ddii", $latitude, $longitude, $delivery_id, $driver_id);
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
            $stmt->close();
            exit;
        }
    }
}

// Fetch driver's deliveries
$deliveries = $conn->query("
    SELECT 
        d.*,
        o.id as order_id,
        o.quantity,
        o.total_price,
        s.company_name as supplier_name,
        u.full_name as supplier_contact,
        u.phone as supplier_phone,
        f.name as fertilizer_name,
        f.depot_location
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN suppliers s ON d.supplier_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    WHERE d.driver_id = $driver_id
    ORDER BY 
        CASE d.status
            WHEN 'Pending' THEN 1
            WHEN 'In Transit' THEN 2
            WHEN 'Delivered' THEN 3
        END,
        d.expected_arrival ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Deliveries | Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .delivery-card {
            transition: all 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .delivery-card.status-pending { border-left-color: #ffc107; }
        .delivery-card.status-in-transit { border-left-color: #0d6efd; }
        .delivery-card.status-delivered { border-left-color: #198754; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-primary mb-4"><i class="bi bi-truck"></i> My Deliveries</h3>

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
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-body">
                        <h6 class="text-muted">Pending</h6>
                        <h3 class="text-warning"><?= count(array_filter($deliveries, fn($d) => $d['status'] === 'Pending')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-primary">
                    <div class="card-body">
                        <h6 class="text-muted">In Transit</h6>
                        <h3 class="text-primary"><?= count(array_filter($deliveries, fn($d) => $d['status'] === 'In Transit')) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-success">
                    <div class="card-body">
                        <h6 class="text-muted">Delivered Today</h6>
                        <h3 class="text-success">
                            <?= count(array_filter($deliveries, fn($d) => 
                                $d['status'] === 'Delivered' && 
                                date('Y-m-d', strtotime($d['delivered_on'])) === date('Y-m-d')
                            )) ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Deliveries List -->
        <?php if (empty($deliveries)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-truck display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Deliveries Assigned</h4>
                    <p class="text-muted">Check back later for new delivery assignments</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($deliveries as $delivery): 
                $statusClass = 'status-' . strtolower(str_replace(' ', '-', $delivery['status']));
                $statusBadge = match($delivery['status']) {
                    'Pending' => 'warning',
                    'In Transit' => 'primary',
                    'Delivered' => 'success',
                    default => 'secondary'
                };
            ?>
            <div class="card delivery-card mb-3 <?= $statusClass ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="bi bi-box-seam"></i> <?= htmlspecialchars($delivery['fertilizer_name']) ?>
                                    </h5>
                                    <p class="text-muted mb-0">
                                        <small>
                                            Delivery #<?= $delivery['id'] ?> • Order #<?= $delivery['order_id'] ?>
                                        </small>
                                    </p>
                                </div>
                                <span class="badge bg-<?= $statusBadge ?> fs-6"><?= $delivery['status'] ?></span>
                            </div>

                            <div class="mb-3">
                                <h6 class="mb-2"><i class="bi bi-building"></i> Delivery To:</h6>
                                <p class="mb-0">
                                    <strong><?= htmlspecialchars($delivery['supplier_name']) ?></strong><br>
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($delivery['supplier_contact']) ?><br>
                                    <i class="bi bi-phone"></i> <?= htmlspecialchars($delivery['supplier_phone']) ?>
                                </p>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-4">
                                    <small class="text-muted">Quantity</small>
                                    <div><strong><?= $delivery['quantity'] ?> units</strong></div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Pickup Location</small>
                                    <div><?= htmlspecialchars($delivery['depot_location'] ?? 'N/A') ?></div>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted">Expected Arrival</small>
                                    <div><?= date('M d, H:i', strtotime($delivery['expected_arrival'])) ?></div>
                                </div>
                            </div>

                            <?php if ($delivery['delivered_on']): ?>
                            <div class="mt-2">
                                <span class="badge bg-success">
                                    <i class="bi bi-check-circle"></i> Delivered on <?= date('M d, Y H:i', strtotime($delivery['delivered_on'])) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-4 border-start">
                            <h6 class="mb-3"><i class="bi bi-gear"></i> Actions</h6>
                            
                            <?php if ($delivery['status'] === 'Pending'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="start">
                                    <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                                    <button type="submit" class="btn btn-primary w-100 mb-2">
                                        <i class="bi bi-play-circle"></i> Start Delivery
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($delivery['status'] === 'In Transit'): ?>
                                <button class="btn btn-info w-100 mb-2" onclick="updateLocation(<?= $delivery['id'] ?>)">
                                    <i class="bi bi-geo-alt"></i> Update Location
                                </button>
                                <form method="POST">
                                    <input type="hidden" name="action" value="deliver">
                                    <input type="hidden" name="delivery_id" value="<?= $delivery['id'] ?>">
                                    <button type="submit" class="btn btn-success w-100"
                                            onclick="return confirm('Mark this delivery as completed?')">
                                        <i class="bi bi-check-circle"></i> Mark as Delivered
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($delivery['status'] === 'Delivered'): ?>
                                <div class="alert alert-success small mb-0">
                                    <i class="bi bi-check-circle"></i> Delivery completed!
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
<script>
function updateLocation(deliveryId) {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            fetch('my_deliveries.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_location&delivery_id=${deliveryId}&latitude=${position.coords.latitude}&longitude=${position.coords.longitude}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Location updated successfully!');
                } else {
                    alert('Failed to update location');
                }
            });
        },
        function(error) {
            alert('Unable to get your location: ' + error.message);
        }
    );
}
</script>
</body>
</html>