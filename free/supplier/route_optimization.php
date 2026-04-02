<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get supplier
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    header('Location: profile.php');
    exit();
}

$supplier_id = (int) $supplier['id'];

// Get supplier's warehouse locations
$warehouses = $conn->query("
    SELECT * FROM supplier_locations 
    WHERE supplier_id = {$supplier_id} 
    ORDER BY is_primary DESC
")->fetch_all(MYSQLI_ASSOC);

// Get pending/in-transit deliveries with destinations
$deliveries = $conn->query("
    SELECT d.*, o.quantity, f.name as fertilizer_name,
           sl.name as dest_name, sl.address as dest_address, 
           sl.latitude as dest_lat, sl.longitude as dest_lng,
           u.full_name as driver_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN supplier_locations sl ON sl.supplier_id = d.supplier_id AND sl.is_primary = 1
    LEFT JOIN users u ON d.driver_id = u.id
    WHERE d.supplier_id = {$supplier_id} AND d.status IN ('Pending', 'In Transit')
    ORDER BY d.expected_arrival ASC
")->fetch_all(MYSQLI_ASSOC);

// Convert to JSON for JavaScript
$warehousesJson = json_encode($warehouses);
$deliveriesJson = json_encode($deliveries);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Route Optimization</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />
    <style>
        body { background: #f7f9f6; }
        #map { height: 500px; border-radius: 10px; }
        .route-card {
            border-left: 4px solid #667eea;
            transition: all 0.2s;
            cursor: pointer;
        }
        .route-card:hover { background: #f8f9fa; }
        .route-card.selected { border-left-color: #28a745; background: #e8f5e9; }
        .stop-number {
            width: 28px; height: 28px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.85rem;
        }
        .route-summary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .optimization-btn {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            color: white;
        }
        .optimization-btn:hover { opacity: 0.9; color: white; }
        .legend { background: white; padding: 10px; border-radius: 8px; }
        .drag-handle { cursor: grab; }
        .drag-handle:active { cursor: grabbing; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-signpost-split"></i> Route Optimization</h3>
            <button class="btn optimization-btn" onclick="optimizeRoute()">
                <i class="bi bi-magic"></i> Auto-Optimize Route
            </button>
        </div>

        <div class="row">
            <!-- Map -->
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div id="map"></div>
                    </div>
                </div>

                <!-- Route Summary -->
                <div class="route-summary p-4 mt-3 shadow" id="routeSummary" style="display:none;">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <h4 class="mb-0" id="totalStops">0</h4>
                            <small>Stops</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="mb-0" id="totalDistance">0 km</h4>
                            <small>Total Distance</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="mb-0" id="totalTime">0 min</h4>
                            <small>Est. Time</small>
                        </div>
                        <div class="col-md-3">
                            <h4 class="mb-0" id="fuelEstimate">0 L</h4>
                            <small>Est. Fuel</small>
                        </div>
                    </div>
                </div>

                <!-- Legend -->
                <div class="legend mt-3 shadow-sm">
                    <div class="d-flex gap-4">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:16px;height:16px;background:#28a745;border-radius:50%;"></div>
                            <small>Warehouse</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:16px;height:16px;background:#dc3545;border-radius:50%;"></div>
                            <small>Delivery Point</small>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:16px;height:16px;background:#667eea;border-radius:50%;"></div>
                            <small>Optimized Route</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delivery Stops -->
            <div class="col-lg-4">
                <!-- Starting Point -->
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-building"></i> Starting Point (Warehouse)
                    </div>
                    <div class="card-body">
                        <select id="startWarehouse" class="form-select" onchange="updateRoute()">
                            <?php if (empty($warehouses)): ?>
                                <option value="">No warehouse locations set</option>
                            <?php else: ?>
                                <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id'] ?>" 
                                        data-lat="<?= $wh['latitude'] ?>" 
                                        data-lng="<?= $wh['longitude'] ?>"
                                        <?= $wh['is_primary'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($wh['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($warehouses)): ?>
                        <div class="mt-2">
                            <a href="supplier_locations.php" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-plus"></i> Add Warehouse Location
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Delivery Stops -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-geo-alt"></i> Delivery Stops</span>
                        <span class="badge bg-primary"><?= count($deliveries) ?></span>
                    </div>
                    <div class="card-body p-0" id="stopsContainer" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($deliveries)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mb-0 mt-2">No pending deliveries</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($deliveries as $i => $del): ?>
                            <div class="route-card p-3 border-bottom" 
                                 data-id="<?= $del['id'] ?>"
                                 data-lat="<?= $del['dest_lat'] ?>"
                                 data-lng="<?= $del['dest_lng'] ?>"
                                 draggable="true">
                                <div class="d-flex align-items-start gap-3">
                                    <div class="stop-number drag-handle"><?= $i + 1 ?></div>
                                    <div class="flex-grow-1">
                                        <strong>Delivery #<?= $del['id'] ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($del['fertilizer_name']) ?></small><br>
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($del['dest_address'] ?? 'No address') ?>
                                        </small>
                                        <?php if ($del['driver_name']): ?>
                                        <br><small class="text-primary">
                                            <i class="bi bi-person"></i> <?= htmlspecialchars($del['driver_name']) ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?= $del['status'] === 'In Transit' ? 'primary' : 'warning' ?> mb-1">
                                            <?= $del['status'] ?>
                                        </span>
                                        <div class="small text-muted" id="distance-<?= $del['id'] ?>">--</div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions -->
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body">
                        <button class="btn btn-primary w-100 mb-2" onclick="exportRoute()">
                            <i class="bi bi-download"></i> Export Route
                        </button>
                        <button class="btn btn-outline-secondary w-100" onclick="shareWithDrivers()">
                            <i class="bi bi-share"></i> Share with Drivers
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const warehouses = <?= $warehousesJson ?>;
    const deliveries = <?= $deliveriesJson ?>;
    
    let map, routingControl;
    let markers = [];
    let currentRoute = [];

    // Initialize map
    function initMap() {
        map = L.map('map').setView([-13.9626, 33.7741], 7);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);

        // Add warehouse markers
        warehouses.forEach(wh => {
            if (wh.latitude && wh.longitude) {
                const marker = L.marker([wh.latitude, wh.longitude], {
                    icon: L.divIcon({
                        className: 'warehouse-marker',
                        html: '<div style="background:#28a745;width:20px;height:20px;border-radius:50%;border:3px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    })
                }).addTo(map).bindPopup(`<strong>${wh.name}</strong><br>Warehouse`);
                markers.push(marker);
            }
        });

        // Add delivery markers
        deliveries.forEach((del, i) => {
            if (del.dest_lat && del.dest_lng) {
                const marker = L.marker([del.dest_lat, del.dest_lng], {
                    icon: L.divIcon({
                        className: 'delivery-marker',
                        html: `<div style="background:#dc3545;width:24px;height:24px;border-radius:50%;border:3px solid white;box-shadow:0 2px 5px rgba(0,0,0,0.3);display:flex;align-items:center;justify-content:center;color:white;font-size:12px;font-weight:bold;">${i+1}</div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    })
                }).addTo(map).bindPopup(`<strong>Delivery #${del.id}</strong><br>${del.fertilizer_name}<br>${del.dest_address || ''}`);
                markers.push(marker);
            }
        });

        // Fit bounds
        if (markers.length > 0) {
            const group = L.featureGroup(markers);
            map.fitBounds(group.getBounds().pad(0.1));
        }

        // Initial route
        updateRoute();
        setupDragAndDrop();
    }

    // Update route based on current order
    function updateRoute() {
        const warehouseSelect = document.getElementById('startWarehouse');
        const startOption = warehouseSelect.options[warehouseSelect.selectedIndex];
        
        if (!startOption || !startOption.dataset.lat) return;

        const waypoints = [];
        
        // Start from warehouse
        waypoints.push(L.latLng(parseFloat(startOption.dataset.lat), parseFloat(startOption.dataset.lng)));

        // Add delivery stops in order
        document.querySelectorAll('.route-card').forEach(card => {
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            if (lat && lng) {
                waypoints.push(L.latLng(lat, lng));
            }
        });

        if (waypoints.length < 2) return;

        // Remove existing route
        if (routingControl) {
            map.removeControl(routingControl);
        }

        // Create new route
        routingControl = L.Routing.control({
            waypoints: waypoints,
            routeWhileDragging: false,
            showAlternatives: false,
            fitSelectedRoutes: true,
            lineOptions: {
                styles: [{ color: '#667eea', weight: 5, opacity: 0.8 }]
            },
            createMarker: function() { return null; } // Don't create default markers
        }).addTo(map);

        routingControl.on('routesfound', function(e) {
            const route = e.routes[0];
            const totalDistance = (route.summary.totalDistance / 1000).toFixed(1);
            const totalTime = Math.round(route.summary.totalTime / 60);
            const fuelEstimate = (totalDistance * 0.12).toFixed(1); // ~12L/100km

            document.getElementById('routeSummary').style.display = 'block';
            document.getElementById('totalStops').textContent = waypoints.length - 1;
            document.getElementById('totalDistance').textContent = totalDistance + ' km';
            document.getElementById('totalTime').textContent = totalTime + ' min';
            document.getElementById('fuelEstimate').textContent = fuelEstimate + ' L';

            currentRoute = route;
        });
    }

    // Optimize route using nearest neighbor algorithm
    function optimizeRoute() {
        const warehouseSelect = document.getElementById('startWarehouse');
        const startOption = warehouseSelect.options[warehouseSelect.selectedIndex];
        
        if (!startOption || !startOption.dataset.lat) {
            alert('Please select a starting warehouse');
            return;
        }

        const start = {
            lat: parseFloat(startOption.dataset.lat),
            lng: parseFloat(startOption.dataset.lng)
        };

        // Get all delivery points
        const stops = [];
        document.querySelectorAll('.route-card').forEach(card => {
            const lat = parseFloat(card.dataset.lat);
            const lng = parseFloat(card.dataset.lng);
            if (lat && lng) {
                stops.push({
                    element: card,
                    lat: lat,
                    lng: lng,
                    id: card.dataset.id
                });
            }
        });

        if (stops.length === 0) return;

        // Nearest neighbor algorithm
        const optimized = [];
        let current = start;
        const remaining = [...stops];

        while (remaining.length > 0) {
            let nearestIdx = 0;
            let nearestDist = Infinity;

            remaining.forEach((stop, idx) => {
                const dist = getDistance(current.lat, current.lng, stop.lat, stop.lng);
                if (dist < nearestDist) {
                    nearestDist = dist;
                    nearestIdx = idx;
                }
            });

            const nearest = remaining.splice(nearestIdx, 1)[0];
            optimized.push(nearest);
            current = { lat: nearest.lat, lng: nearest.lng };
        }

        // Reorder DOM elements
        const container = document.getElementById('stopsContainer');
        optimized.forEach((stop, i) => {
            stop.element.querySelector('.stop-number').textContent = i + 1;
            container.appendChild(stop.element);
        });

        updateRoute();
        
        // Show notification
        alert('Route optimized! The stops have been reordered for the shortest path.');
    }

    // Calculate distance between two points
    function getDistance(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLng/2) * Math.sin(dLng/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    // Setup drag and drop for manual reordering
    function setupDragAndDrop() {
        const container = document.getElementById('stopsContainer');
        let draggedItem = null;

        container.addEventListener('dragstart', (e) => {
            draggedItem = e.target.closest('.route-card');
            e.dataTransfer.effectAllowed = 'move';
            draggedItem.style.opacity = '0.5';
        });

        container.addEventListener('dragend', (e) => {
            draggedItem.style.opacity = '1';
            updateStopNumbers();
            updateRoute();
        });

        container.addEventListener('dragover', (e) => {
            e.preventDefault();
            const afterElement = getDragAfterElement(container, e.clientY);
            if (afterElement == null) {
                container.appendChild(draggedItem);
            } else {
                container.insertBefore(draggedItem, afterElement);
            }
        });
    }

    function getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.route-card:not([style*="opacity: 0.5"])')];
        
        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function updateStopNumbers() {
        document.querySelectorAll('.route-card').forEach((card, i) => {
            card.querySelector('.stop-number').textContent = i + 1;
        });
    }

    // Export route
    function exportRoute() {
        if (!currentRoute) {
            alert('Please generate a route first');
            return;
        }

        let routeText = 'OPTIMIZED DELIVERY ROUTE\n';
        routeText += '========================\n\n';
        routeText += 'Total Distance: ' + document.getElementById('totalDistance').textContent + '\n';
        routeText += 'Estimated Time: ' + document.getElementById('totalTime').textContent + '\n';
        routeText += 'Estimated Fuel: ' + document.getElementById('fuelEstimate').textContent + '\n\n';
        routeText += 'STOPS:\n';

        document.querySelectorAll('.route-card').forEach((card, i) => {
            const deliveryId = card.dataset.id;
            const address = card.querySelector('.text-muted i.bi-geo-alt')?.parentElement?.textContent || 'No address';
            routeText += `${i + 1}. Delivery #${deliveryId}\n   ${address}\n\n`;
        });

        // Download as text file
        const blob = new Blob([routeText], { type: 'text/plain' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'optimized_route_' + new Date().toISOString().split('T')[0] + '.txt';
        a.click();
    }

    // Share with drivers
    function shareWithDrivers() {
        alert('Route details will be sent to assigned drivers via SMS/Email notification.\n\nThis feature requires SMS/Email integration.');
    }

    // Initialize on load
    document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>