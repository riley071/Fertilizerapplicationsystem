<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];

// Fetch pending and in-transit deliveries with locations
$deliveries = $conn->query("
    SELECT 
        d.id,
        d.expected_arrival,
        d.status,
        o.id as order_id,
        o.quantity,
        f.name as fertilizer_name,
        f.depot_location,
        s.company_name as supplier_name,
        s.address as supplier_address,
        u.phone as supplier_phone,
        sl.latitude,
        sl.longitude,
        sl.address as location_address
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN supplier_locations sl ON d.supplier_id = sl.supplier_id AND sl.is_primary = 1
    WHERE d.driver_id = $driver_id AND d.status IN ('Pending', 'In Transit')
    ORDER BY d.expected_arrival ASC
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Route Optimization | Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <style>
        body { background: #f7f9f6; }
        #routeMap { height: 600px; border-radius: 12px; }
        .delivery-list-item {
            border-left: 4px solid #dee2e6;
            transition: all 0.2s;
        }
        .delivery-list-item:hover {
            background: #f8f9fa;
            border-left-color: #667eea;
        }
        .delivery-list-item.active {
            background: #e7f1ff;
            border-left-color: #0d6efd;
        }
        .route-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-primary mb-4"><i class="bi bi-map"></i> Route Optimization</h3>

        <?php if (empty($deliveries)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-map display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Active Deliveries</h4>
                    <p class="text-muted">You don't have any pending or in-transit deliveries to route</p>
                    <a href="my_deliveries.php" class="btn btn-primary">View All Deliveries</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Map -->
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-3">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-geo-alt"></i> Optimized Route Map</span>
                                <button class="btn btn-sm btn-primary" onclick="optimizeRoute()">
                                    <i class="bi bi-arrow-clockwise"></i> Optimize Route
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div id="routeMap"></div>
                        </div>
                    </div>

                    <!-- Route Summary -->
                    <div class="route-summary shadow">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h4 id="totalStops" class="mb-0"><?= count($deliveries) ?></h4>
                                <small>Total Stops</small>
                            </div>
                            <div class="col-md-4">
                                <h4 id="totalDistance" class="mb-0">-</h4>
                                <small>Total Distance</small>
                            </div>
                            <div class="col-md-4">
                                <h4 id="estimatedTime" class="mb-0">-</h4>
                                <small>Estimated Time</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Delivery List -->
                <div class="col-lg-4">
                    <div class="card shadow-sm">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="bi bi-list-ol"></i> Delivery Stops (<?= count($deliveries) ?>)</h6>
                        </div>
                        <div class="card-body p-0" style="max-height: 700px; overflow-y: auto;">
                            <?php foreach ($deliveries as $index => $delivery): ?>
                            <div class="delivery-list-item p-3 border-bottom" 
                                 data-delivery-id="<?= $delivery['id'] ?>"
                                 data-lat="<?= $delivery['latitude'] ?? '' ?>"
                                 data-lng="<?= $delivery['longitude'] ?? '' ?>">
                                <div class="d-flex">
                                    <div class="me-3">
                                        <div class="badge bg-primary rounded-circle" style="width: 30px; height: 30px; line-height: 20px;">
                                            <?= $index + 1 ?>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($delivery['supplier_name']) ?></h6>
                                        <p class="mb-1 small">
                                            <i class="bi bi-box-seam text-success"></i> 
                                            <?= htmlspecialchars($delivery['fertilizer_name']) ?> 
                                            (<?= $delivery['quantity'] ?> units)
                                        </p>
                                        <p class="mb-1 small text-muted">
                                            <i class="bi bi-geo-alt"></i> 
                                            <?= htmlspecialchars($delivery['location_address'] ?? $delivery['supplier_address']) ?>
                                        </p>
                                        <p class="mb-1 small text-muted">
                                            <i class="bi bi-phone"></i> <?= htmlspecialchars($delivery['supplier_phone']) ?>
                                        </p>
                                        <p class="mb-0 small">
                                            <i class="bi bi-clock"></i> 
                                            ETA: <?= date('M d, H:i', strtotime($delivery['expected_arrival'])) ?>
                                        </p>
                                        <?php if ($delivery['latitude'] && $delivery['longitude']): ?>
                                            <a href="https://www.google.com/maps?q=<?= $delivery['latitude'] ?>,<?= $delivery['longitude'] ?>" 
                                               target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                                <i class="bi bi-map"></i> Open in Maps
                                            </a>
                                        <?php else: ?>
                                            <div class="alert alert-warning alert-sm mt-2 mb-0 small">
                                                <i class="bi bi-exclamation-triangle"></i> No GPS coordinates available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
<script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
<script>
const deliveries = <?= json_encode($deliveries) ?>;

// Initialize map
const map = L.map('routeMap').setView([-13.9626, 33.7741], 12); // Lilongwe, Malawi
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(map);

let routeControl = null;
let markers = [];

// Add markers for all deliveries
function addMarkers() {
    // Clear existing markers
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    
    deliveries.forEach((delivery, index) => {
        if (delivery.latitude && delivery.longitude) {
            const marker = L.marker([delivery.latitude, delivery.longitude])
                .addTo(map)
                .bindPopup(`
                    <strong>${index + 1}. ${delivery.supplier_name}</strong><br>
                    ${delivery.fertilizer_name}<br>
                    ${delivery.quantity} units<br>
                    <small>${delivery.location_address || delivery.supplier_address}</small>
                `);
            markers.push(marker);
        }
    });
    
    // Fit map to markers
    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

// Optimize route
function optimizeRoute() {
    const validDeliveries = deliveries.filter(d => d.latitude && d.longitude);
    
    if (validDeliveries.length === 0) {
        alert('No deliveries with GPS coordinates found');
        return;
    }
    
    // Get current location
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                createRoute(position.coords.latitude, position.coords.longitude, validDeliveries);
            },
            function(error) {
                // Use first delivery location as start if GPS fails
                createRoute(validDeliveries[0].latitude, validDeliveries[0].longitude, validDeliveries);
            }
        );
    } else {
        // Use first delivery location as start
        createRoute(validDeliveries[0].latitude, validDeliveries[0].longitude, validDeliveries);
    }
}

function createRoute(startLat, startLng, validDeliveries) {
    // Remove existing route
    if (routeControl) {
        map.removeControl(routeControl);
    }
    
    // Create waypoints
    const waypoints = [
        L.latLng(startLat, startLng), // Starting point
        ...validDeliveries.map(d => L.latLng(d.latitude, d.longitude))
    ];
    
    // Create route
    routeControl = L.Routing.control({
        waypoints: waypoints,
        routeWhileDragging: false,
        showAlternatives: false,
        addWaypoints: false,
        lineOptions: {
            styles: [{color: '#667eea', opacity: 0.8, weight: 6}]
        },
        createMarker: function(i, waypoint, n) {
            if (i === 0) {
                return L.marker(waypoint.latLng, {
                    icon: L.divIcon({
                        html: '<i class="bi bi-geo-alt-fill" style="font-size: 24px; color: green;"></i>',
                        className: 'custom-marker',
                        iconSize: [24, 24],
                        iconAnchor: [12, 24]
                    })
                }).bindPopup('Start Location');
            } else {
                return L.marker(waypoint.latLng, {
                    icon: L.divIcon({
                        html: `<div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">${i}</div>`,
                        className: 'custom-marker',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15]
                    })
                }).bindPopup(`Stop ${i}: ${validDeliveries[i-1].supplier_name}`);
            }
        }
    }).addTo(map);
    
    // Update summary when route is found
    routeControl.on('routesfound', function(e) {
        const route = e.routes[0];
        const distance = (route.summary.totalDistance / 1000).toFixed(1); // km
        const time = Math.round(route.summary.totalTime / 60); // minutes
        
        document.getElementById('totalDistance').textContent = distance + ' km';
        document.getElementById('estimatedTime').textContent = time + ' min';
    });
}

// Initialize markers on load
addMarkers();

// Auto-optimize on load if deliveries exist
if (deliveries.length > 0) {
    setTimeout(optimizeRoute, 1000);
}

// Click delivery item to highlight on map
document.querySelectorAll('.delivery-list-item').forEach(item => {
    item.addEventListener('click', function() {
        document.querySelectorAll('.delivery-list-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
        
        const lat = parseFloat(this.dataset.lat);
        const lng = parseFloat(this.dataset.lng);
        
        if (lat && lng) {
            map.setView([lat, lng], 15);
            
            // Find and open popup for this marker
            markers.forEach(marker => {
                const markerPos = marker.getLatLng();
                if (Math.abs(markerPos.lat - lat) < 0.0001 && Math.abs(markerPos.lng - lng) < 0.0001) {
                    marker.openPopup();
                }
            });
        }
    });
});
</script>
</body>
</html>