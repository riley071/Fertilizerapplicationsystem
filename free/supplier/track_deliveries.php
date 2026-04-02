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

if (!$supplier) {
    header('Location: profile.php');
    exit();
}

$supplier_id = (int) $supplier['id'];

// Get specific delivery if ID provided
$selected_id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// Fetch active deliveries (Pending or In Transit)
$deliveries = $conn->query("
    SELECT d.*, o.quantity, o.total_price,
           f.name as fertilizer_name,
           u.full_name as driver_name, u.phone as driver_phone,
           sl.name as destination_name, sl.latitude as dest_lat, sl.longitude as dest_lng, sl.address as dest_address
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN users u ON d.driver_id = u.id
    LEFT JOIN supplier_locations sl ON sl.supplier_id = d.supplier_id AND sl.is_primary = 1
    WHERE d.supplier_id = {$supplier_id} AND d.status IN ('Pending', 'In Transit')
    ORDER BY d.status DESC, d.last_updated DESC
")->fetch_all(MYSQLI_ASSOC);

// Convert to JSON for JavaScript
$deliveriesJson = json_encode($deliveries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Track Deliveries - Live Map</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f7f9f6; }
        #map { height: 500px; border-radius: 10px; }
        .delivery-card { 
            cursor: pointer; 
            transition: all 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .delivery-card:hover { background: #f8f9fa; }
        .delivery-card.active { border-left-color: #28a745; background: #e8f5e9; }
        .delivery-card.in-transit { border-left-color: #0d6efd; }
        .delivery-card.pending { border-left-color: #ffc107; }
        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        .status-dot.in-transit { background: #0d6efd; }
        .status-dot.pending { background: #ffc107; }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.2); }
        }
        .driver-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
        }
        .refresh-indicator {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .legend { background: white; padding: 10px; border-radius: 8px; }
        .legend-item { display: flex; align-items: center; gap: 8px; margin: 5px 0; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-geo-alt-fill"></i> Live Delivery Tracking</h3>
            <div>
                <span class="refresh-indicator me-3">
                    <i class="bi bi-arrow-clockwise"></i> Auto-refresh: <span id="countdown">30</span>s
                </span>
                <button class="btn btn-outline-success btn-sm" onclick="refreshData()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh Now
                </button>
            </div>
        </div>

        <div class="row">
            <!-- Map -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div id="map"></div>
                    </div>
                </div>
                
                <!-- Legend -->
                <div class="legend mt-3 shadow-sm">
                    <strong class="small">Map Legend</strong>
                    <div class="d-flex gap-4 mt-2">
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #0d6efd;"></div>
                            <span class="small">In Transit</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #ffc107;"></div>
                            <span class="small">Pending</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-dot" style="background: #28a745;"></div>
                            <span class="small">Destination</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery List -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <i class="bi bi-truck"></i> Active Deliveries
                        <span class="badge bg-primary"><?= count($deliveries) ?></span>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($deliveries)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-truck" style="font-size: 2rem;"></i>
                                <p class="mt-2 mb-0">No active deliveries</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($deliveries as $del): 
                                $isSelected = ($selected_id == $del['id']);
                                $statusClass = strtolower(str_replace(' ', '-', $del['status']));
                            ?>
                            <div class="delivery-card p-3 border-bottom <?= $statusClass ?> <?= $isSelected ? 'active' : '' ?>"
                                 onclick="focusDelivery(<?= $del['id'] ?>)" data-id="<?= $del['id'] ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>Delivery #<?= $del['id'] ?></strong>
                                        <span class="status-dot <?= $statusClass ?> ms-2"></span>
                                        <br>
                                        <small class="text-muted"><?= htmlspecialchars($del['fertilizer_name']) ?></small>
                                        <br>
                                        <small class="text-muted">Qty: <?= $del['quantity'] ?></small>
                                    </div>
                                    <span class="badge bg-<?= $del['status'] === 'In Transit' ? 'primary' : 'warning' ?>">
                                        <?= $del['status'] ?>
                                    </span>
                                </div>
                                <?php if ($del['driver_name']): ?>
                                <div class="mt-2 small">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($del['driver_name']) ?>
                                    <br>
                                    <i class="bi bi-phone"></i> <?= htmlspecialchars($del['driver_phone']) ?>
                                </div>
                                <?php else: ?>
                                <div class="mt-2 small text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> No driver assigned
                                </div>
                                <?php endif; ?>
                                
                                <?php if ($del['current_latitude'] && $del['current_longitude']): ?>
                                <div class="mt-2 small text-success">
                                    <i class="bi bi-broadcast"></i> GPS Active
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Selected Delivery Info -->
                <div id="selectedDeliveryInfo" class="mt-3" style="display: none;">
                    <div class="driver-info">
                        <h6 class="mb-3"><i class="bi bi-truck"></i> Delivery Details</h6>
                        <div id="deliveryDetails"></div>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-primary mb-0"><?= count(array_filter($deliveries, fn($d) => $d['status'] === 'In Transit')) ?></h4>
                                <small class="text-muted">In Transit</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-warning mb-0"><?= count(array_filter($deliveries, fn($d) => $d['status'] === 'Pending')) ?></h4>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Delivery data from PHP
    let deliveries = <?= $deliveriesJson ?>;
    let selectedId = <?= $selected_id ?? 'null' ?>;
    let map, markers = {};
    
    // Initialize map centered on Malawi
    function initMap() {
        map = L.map('map').setView([-13.9626, 33.7741], 7);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        updateMarkers();
        
        if (selectedId) {
            focusDelivery(selectedId);
        }
    }
    
    // Update markers on map
    function updateMarkers() {
        // Clear existing markers
        Object.values(markers).forEach(m => map.removeLayer(m));
        markers = {};
        
        deliveries.forEach(del => {
            // Delivery location (if GPS data exists)
            if (del.current_latitude && del.current_longitude) {
                const color = del.status === 'In Transit' ? '#0d6efd' : '#ffc107';
                const icon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background:${color}; width:20px; height:20px; border-radius:50%; border:3px solid white; box-shadow:0 2px 5px rgba(0,0,0,0.3);"></div>`,
                    iconSize: [20, 20],
                    iconAnchor: [10, 10]
                });
                
                const marker = L.marker([del.current_latitude, del.current_longitude], { icon })
                    .addTo(map)
                    .bindPopup(`
                        <strong>Delivery #${del.id}</strong><br>
                        ${del.fertilizer_name}<br>
                        Driver: ${del.driver_name || 'Not assigned'}<br>
                        Status: ${del.status}
                    `);
                
                marker.on('click', () => focusDelivery(del.id));
                markers['delivery_' + del.id] = marker;
            }
            
            // Destination marker
            if (del.dest_lat && del.dest_lng) {
                const destIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background:#28a745; width:16px; height:16px; border-radius:50%; border:2px solid white;"></div>`,
                    iconSize: [16, 16],
                    iconAnchor: [8, 8]
                });
                
                const destMarker = L.marker([del.dest_lat, del.dest_lng], { icon: destIcon })
                    .addTo(map)
                    .bindPopup(`<strong>Destination</strong><br>${del.destination_name || 'Delivery Point'}`);
                
                markers['dest_' + del.id] = destMarker;
            }
        });
        
        // Fit bounds if markers exist
        const allMarkers = Object.values(markers);
        if (allMarkers.length > 0) {
            const group = L.featureGroup(allMarkers);
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
    
    // Focus on specific delivery
    function focusDelivery(id) {
        selectedId = id;
        const del = deliveries.find(d => d.id == id);
        
        if (!del) return;
        
        // Update UI
        document.querySelectorAll('.delivery-card').forEach(card => {
            card.classList.remove('active');
            if (card.dataset.id == id) card.classList.add('active');
        });
        
        // Show details panel
        const detailsPanel = document.getElementById('selectedDeliveryInfo');
        const detailsContent = document.getElementById('deliveryDetails');
        
        detailsContent.innerHTML = `
            <p class="mb-2"><strong>Order:</strong> #${del.order_id}</p>
            <p class="mb-2"><strong>Product:</strong> ${del.fertilizer_name}</p>
            <p class="mb-2"><strong>Quantity:</strong> ${del.quantity}</p>
            <p class="mb-2"><strong>Driver:</strong> ${del.driver_name || 'Not assigned'}</p>
            ${del.driver_phone ? `<p class="mb-2"><strong>Phone:</strong> ${del.driver_phone}</p>` : ''}
            <p class="mb-2"><strong>Status:</strong> ${del.status}</p>
            ${del.expected_arrival ? `<p class="mb-0"><strong>ETA:</strong> ${new Date(del.expected_arrival).toLocaleString()}</p>` : ''}
        `;
        detailsPanel.style.display = 'block';
        
        // Pan to marker
        if (del.current_latitude && del.current_longitude) {
            map.setView([del.current_latitude, del.current_longitude], 12);
            if (markers['delivery_' + id]) {
                markers['delivery_' + id].openPopup();
            }
        } else if (del.dest_lat && del.dest_lng) {
            map.setView([del.dest_lat, del.dest_lng], 12);
        }
    }
    
    // Refresh data
    function refreshData() {
        location.reload();
    }
    
    // Auto-refresh countdown
    let countdown = 30;
    setInterval(() => {
        countdown--;
        document.getElementById('countdown').textContent = countdown;
        if (countdown <= 0) {
            refreshData();
        }
    }, 1000);
    
    // Initialize
    document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>