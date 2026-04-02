<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get supplier_id
$stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supplier_id = $supplier ? (int) $supplier['id'] : null;

if (!$supplier_id) {
    header('Location: profile.php');
    exit();
}

// Fetch active deliveries with tracking info
$deliveries = $conn->query("
    SELECT 
        d.*,
        o.id as order_id,
        o.quantity,
        o.total_price,
        f.name as fertilizer_name,
        f.type as fertilizer_type,
        f.depot_location,
        u.full_name as driver_name,
        u.phone as driver_phone
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN users u ON d.driver_id = u.id
    WHERE d.supplier_id = $supplier_id AND d.status IN ('Pending', 'In Transit')
    ORDER BY d.expected_arrival ASC
")->fetch_all(MYSQLI_ASSOC);

// Get specific delivery if ID provided
$selected_delivery = null;
if (isset($_GET['id'])) {
    $delivery_id = (int) $_GET['id'];
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            o.id as order_id,
            o.quantity,
            f.name as fertilizer_name,
            u.full_name as driver_name,
            u.phone as driver_phone
        FROM deliveries d
        JOIN orders o ON d.order_id = o.id
        JOIN fertilizers f ON o.fertilizer_id = f.id
        LEFT JOIN users u ON d.driver_id = u.id
        WHERE d.id = ? AND d.supplier_id = ?
    ");
    $stmt->bind_param("ii", $delivery_id, $supplier_id);
    $stmt->execute();
    $selected_delivery = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Deliveries | Supplier</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f7f9f6; }
        #trackingMap { height: 500px; border-radius: 12px; }
        .delivery-card {
            cursor: pointer;
            transition: all 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .delivery-card:hover {
            background: #f8f9fa;
            border-left-color: #0d6efd;
        }
        .delivery-card.active {
            background: #e7f1ff;
            border-left-color: #0d6efd;
        }
        .pulse-marker {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-success mb-4"><i class="bi bi-geo-alt"></i> Track Deliveries</h3>

        <?php if (empty($deliveries)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-truck display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Active Deliveries</h4>
                    <p class="text-muted">You don't have any deliveries in transit at the moment</p>
                    <a href="my_orders.php" class="btn btn-success">View My Orders</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Map -->
                <div class="col-lg-8">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <i class="bi bi-map"></i> Live Tracking Map
                            <button class="btn btn-sm btn-outline-primary float-end" onclick="refreshTracking()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div id="trackingMap"></div>
                        </div>
                    </div>

                    <!-- Delivery Details -->
                    <?php if ($selected_delivery): ?>
                    <div class="card shadow-sm mt-3">
                        <div class="card-header bg-success text-white">
                            <i class="bi bi-info-circle"></i> Delivery Details
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3">Order Information</h6>
                                    <p class="mb-1"><strong>Order ID:</strong> #<?= $selected_delivery['order_id'] ?></p>
                                    <p class="mb-1"><strong>Fertilizer:</strong> <?= htmlspecialchars($selected_delivery['fertilizer_name']) ?></p>
                                    <p class="mb-1"><strong>Quantity:</strong> <?= $selected_delivery['quantity'] ?> units</p>
                                    <p class="mb-1"><strong>Status:</strong> 
                                        <span class="badge bg-<?= $selected_delivery['status'] === 'In Transit' ? 'primary' : 'warning' ?>">
                                            <?= $selected_delivery['status'] ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3">Driver Information</h6>
                                    <?php if ($selected_delivery['driver_name']): ?>
                                        <p class="mb-1"><strong>Driver:</strong> <?= htmlspecialchars($selected_delivery['driver_name']) ?></p>
                                        <p class="mb-1"><strong>Phone:</strong> 
                                            <a href="tel:<?= $selected_delivery['driver_phone'] ?>">
                                                <?= htmlspecialchars($selected_delivery['driver_phone']) ?>
                                            </a>
                                        </p>
                                        <p class="mb-1"><strong>Expected Arrival:</strong> 
                                            <?= date('M d, Y H:i', strtotime($selected_delivery['expected_arrival'])) ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-muted">Driver not assigned yet</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Delivery List -->
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-list"></i> Active Deliveries (<?= count($deliveries) ?>)</h6>
                        </div>
                        <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                            <?php foreach ($deliveries as $delivery): ?>
                            <div class="delivery-card p-3 border-bottom <?= $selected_delivery && $selected_delivery['id'] === $delivery['id'] ? 'active' : '' ?>"
                                 onclick="selectDelivery(<?= $delivery['id'] ?>)">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong><i class="bi bi-box-seam text-success"></i> <?= htmlspecialchars($delivery['fertilizer_name']) ?></strong>
                                        <br><small class="text-muted">Order #<?= $delivery['order_id'] ?></small>
                                    </div>
                                    <span class="badge bg-<?= $delivery['status'] === 'In Transit' ? 'primary' : 'warning' ?>">
                                        <?= $delivery['status'] ?>
                                    </span>
                                </div>
                                
                                <?php if ($delivery['driver_name']): ?>
                                <p class="mb-1 small">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($delivery['driver_name']) ?>
                                </p>
                                <p class="mb-1 small">
                                    <i class="bi bi-phone"></i> <?= htmlspecialchars($delivery['driver_phone']) ?>
                                </p>
                                <?php endif; ?>
                                
                                <p class="mb-0 small text-muted">
                                    <i class="bi bi-clock"></i> ETA: <?= date('M d, H:i', strtotime($delivery['expected_arrival'])) ?>
                                </p>
                                
                                <?php if ($delivery['current_latitude'] && $delivery['current_longitude']): ?>
                                <div class="mt-2">
                                    <span class="badge bg-info">
                                        <i class="bi bi-geo-alt pulse-marker"></i> Live Location Available
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const deliveries = <?= json_encode($deliveries) ?>;
let map, markers = [];

// Initialize map
map = L.map('trackingMap').setView([-13.9626, 33.7741], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

// Add markers for all deliveries
function addMarkers() {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    
    deliveries.forEach((delivery, index) => {
        if (delivery.current_latitude && delivery.current_longitude) {
            // Active delivery with live location
            const marker = L.marker([delivery.current_latitude, delivery.current_longitude], {
                icon: L.divIcon({
                    html: `<i class="bi bi-truck-front" style="font-size: 24px; color: #0d6efd;"></i>`,
                    className: 'pulse-marker',
                    iconSize: [24, 24],
                    iconAnchor: [12, 24]
                })
            }).addTo(map)
              .bindPopup(`
                  <strong>${delivery.fertilizer_name}</strong><br>
                  Order #${delivery.order_id}<br>
                  Driver: ${delivery.driver_name || 'Not assigned'}<br>
                  Status: ${delivery.status}
              `);
            markers.push(marker);
        }
    });
    
    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.2));
    }
}

function selectDelivery(deliveryId) {
    window.location.href = `?id=${deliveryId}`;
}

function refreshTracking() {
    window.location.reload();
}

// Initialize markers
addMarkers();

// Auto-refresh every 30 seconds
setInterval(refreshTracking, 30000);
</script>
</body>
</html>