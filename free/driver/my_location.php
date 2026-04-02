<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Fetch driver's current location settings
$stmt = $conn->prepare("SELECT * FROM driver_locations WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$location = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle location update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
    $latitude = floatval($_POST['latitude']);
    $longitude = floatval($_POST['longitude']);
    $address = trim($_POST['address']);
    $location_type = $_POST['location_type'] ?? 'current';
    
    if (empty($latitude) || empty($longitude)) {
        $error = "Please capture your location first.";
    } else {
        // Check if driver has existing location
        if ($location) {
            // Update existing location
            $stmt = $conn->prepare("UPDATE driver_locations SET latitude = ?, longitude = ?, address = ?, location_type = ?, updated_at = NOW() WHERE driver_id = ?");
            $stmt->bind_param("ddssi", $latitude, $longitude, $address, $location_type, $driver_id);
        } else {
            // Insert new location
            $stmt = $conn->prepare("INSERT INTO driver_locations (driver_id, latitude, longitude, address, location_type, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("iddss", $driver_id, $latitude, $longitude, $address, $location_type);
        }
        
        if ($stmt->execute()) {
            // Log the action
            $log_stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
            $action = "Updated location to: $address";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("iss", $driver_id, $action, $ip);
            $log_stmt->execute();
            $log_stmt->close();
            
            $success = "Location updated successfully!";
            
            // Refresh location data
            $stmt = $conn->prepare("SELECT * FROM driver_locations WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 1");
            $stmt->bind_param("i", $driver_id);
            $stmt->execute();
            $location = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update location.";
        }
        $stmt->close();
    }
}

// Fetch driver info
$stmt = $conn->prepare("SELECT full_name, phone FROM users WHERE id = ?");
$stmt->bind_param("i", $driver_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get location history
$history_stmt = $conn->prepare("SELECT * FROM driver_locations WHERE driver_id = ? ORDER BY updated_at DESC LIMIT 10");
$history_stmt->bind_param("i", $driver_id);
$history_stmt->execute();
$location_history = $history_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Location</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f7f9f6; }
        #map {
            height: 500px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .location-card {
            transition: all 0.3s;
        }
        .location-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .current-location {
            border-left: 4px solid #28a745;
        }
        .info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .history-item {
            border-left: 3px solid #e9ecef;
            padding-left: 1rem;
            margin-bottom: 1rem;
            transition: border-color 0.3s;
        }
        .history-item:hover {
            border-left-color: #28a745;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success"><i class="bi bi-geo-alt"></i> My Location</h3>
            <button class="btn btn-success" onclick="getCurrentLocation()">
                <i class="bi bi-crosshair"></i> Get Current Location
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

        <div class="row g-4">
            <!-- Map Section -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-map"></i> Location Map</h5>
                    </div>
                    <div class="card-body">
                        <div id="map"></div>
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Click on the map or use the button above to set your location
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Update Location Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Update Location</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="locationForm">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Latitude</label>
                                    <input type="text" name="latitude" id="latitude" class="form-control" readonly required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Longitude</label>
                                    <input type="text" name="longitude" id="longitude" class="form-control" readonly required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">Address</label>
                                    <input type="text" name="address" id="address" class="form-control" placeholder="Enter or capture address">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Location Type</label>
                                    <select name="location_type" class="form-select">
                                        <option value="current">Current Location</option>
                                        <option value="home">Home Base</option>
                                        <option value="depot">Depot/Office</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" name="update_location" class="btn btn-success">
                                        <i class="bi bi-check-lg"></i> Update Location
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info & History Section -->
            <div class="col-lg-4">
                <!-- Current Location Card -->
                <div class="card info-card border-0 shadow-sm mb-4">
                    <div class="card-body text-center">
                        <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
                        <h5 class="mt-3"><?= htmlspecialchars($driver['full_name']) ?></h5>
                        <p class="mb-1"><i class="bi bi-telephone"></i> <?= htmlspecialchars($driver['phone']) ?></p>
                        <hr class="my-3 border-light">
                        <?php if ($location): ?>
                            <div class="text-start">
                                <p class="mb-1"><strong>Last Updated:</strong></p>
                                <p class="small"><?= date('M d, Y H:i', strtotime($location['updated_at'])) ?></p>
                                <p class="mb-1"><strong>Location Type:</strong></p>
                                <p class="small"><?= ucfirst($location['location_type']) ?></p>
                            </div>
                        <?php else: ?>
                            <p class="small">No location set yet</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Current Location Details -->
                <?php if ($location): ?>
                <div class="card location-card current-location border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-pin-map"></i> Current Location</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Address:</strong></p>
                        <p class="text-muted"><?= htmlspecialchars($location['address'] ?: 'Not specified') ?></p>
                        
                        <p class="mb-2"><strong>Coordinates:</strong></p>
                        <p class="text-muted small">
                            Lat: <?= number_format($location['latitude'], 6) ?><br>
                            Lng: <?= number_format($location['longitude'], 6) ?>
                        </p>
                        
                        <a href="https://www.google.com/maps?q=<?= $location['latitude'] ?>,<?= $location['longitude'] ?>" 
                           target="_blank" class="btn btn-sm btn-outline-success w-100">
                            <i class="bi bi-map"></i> Open in Google Maps
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Location History -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Location History</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($location_history)): ?>
                            <p class="text-muted small mb-0">No location history available</p>
                        <?php else: ?>
                            <?php foreach ($location_history as $hist): ?>
                                <div class="history-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="small fw-semibold"><?= ucfirst($hist['location_type']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars(substr($hist['address'], 0, 40)) ?><?= strlen($hist['address']) > 40 ? '...' : '' ?></div>
                                        </div>
                                        <span class="badge bg-secondary"><?= date('M d', strtotime($hist['updated_at'])) ?></span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <?= date('H:i', strtotime($hist['updated_at'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Initialize map
let map, marker;
const defaultLat = <?= $location['latitude'] ?? -13.9626 ?>; // Lilongwe default
const defaultLng = <?= $location['longitude'] ?? 33.7741 ?>;

function initMap() {
    map = L.map('map').setView([defaultLat, defaultLng], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);
    
    // Add marker if location exists
    <?php if ($location): ?>
    marker = L.marker([<?= $location['latitude'] ?>, <?= $location['longitude'] ?>]).addTo(map);
    marker.bindPopup("<b>Your Location</b><br><?= htmlspecialchars($location['address']) ?>").openPopup();
    <?php endif; ?>
    
    // Click on map to set location
    map.on('click', function(e) {
        setLocation(e.latlng.lat, e.latlng.lng);
    });
}

function setLocation(lat, lng) {
    // Remove existing marker
    if (marker) {
        map.removeLayer(marker);
    }
    
    // Add new marker
    marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup("<b>Selected Location</b>").openPopup();
    
    // Update form fields
    document.getElementById('latitude').value = lat.toFixed(6);
    document.getElementById('longitude').value = lng.toFixed(6);
    
    // Reverse geocoding (get address from coordinates)
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(response => response.json())
        .then(data => {
            if (data.display_name) {
                document.getElementById('address').value = data.display_name;
            }
        })
        .catch(error => console.error('Geocoding error:', error));
}

function getCurrentLocation() {
    if (navigator.geolocation) {
        // Show loading
        const btn = event.target;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Getting location...';
        btn.disabled = true;
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                
                // Center map and set location
                map.setView([lat, lng], 15);
                setLocation(lat, lng);
                
                // Reset button
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                // Show success message
                showNotification('Location captured successfully!', 'success');
            },
            function(error) {
                btn.innerHTML = originalText;
                btn.disabled = false;
                
                let errorMsg = 'Unable to get location. ';
                switch(error.code) {
                    case error.PERMISSION_DENIED:
                        errorMsg += 'Please allow location access.';
                        break;
                    case error.POSITION_UNAVAILABLE:
                        errorMsg += 'Location information unavailable.';
                        break;
                    case error.TIMEOUT:
                        errorMsg += 'Location request timed out.';
                        break;
                }
                showNotification(errorMsg, 'danger');
            },
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0
            }
        );
    } else {
        showNotification('Geolocation is not supported by your browser.', 'danger');
    }
}

function resetForm() {
    document.getElementById('locationForm').reset();
    <?php if ($location): ?>
    document.getElementById('latitude').value = '<?= $location['latitude'] ?>';
    document.getElementById('longitude').value = '<?= $location['longitude'] ?>';
    document.getElementById('address').value = '<?= htmlspecialchars($location['address']) ?>';
    <?php endif; ?>
}

function showNotification(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => alertDiv.remove(), 5000);
}

// Initialize map on page load
document.addEventListener('DOMContentLoaded', initMap);
</script>
</body>
</html>

<?php
// Create driver_locations table if it doesn't exist
/*
CREATE TABLE IF NOT EXISTS `driver_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `driver_id` INT(11) NOT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `address` TEXT,
  `location_type` ENUM('current', 'home', 'depot') DEFAULT 'current',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `driver_id` (`driver_id`),
  CONSTRAINT `fk_driver_location` FOREIGN KEY (`driver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
?>