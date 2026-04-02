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
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supplier_id = $supplier ? (int) $supplier['id'] : null;

if (!$supplier_id) {
    $error = "Supplier profile not found. Please complete your profile first.";
}

// Handle location actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supplier_id) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // Validate inputs
        if (empty($name) || empty($type) || empty($latitude) || empty($longitude) || empty($address)) {
            $error = "Please fill in all required fields.";
        } elseif (!is_numeric($latitude) || $latitude < -90 || $latitude > 90) {
            $error = "Invalid latitude. Must be between -90 and 90.";
        } elseif (!is_numeric($longitude) || $longitude < -180 || $longitude > 180) {
            $error = "Invalid longitude. Must be between -180 and 180.";
        } else {
            // If setting as primary, unset other primary locations
            if ($is_primary) {
                $conn->query("UPDATE supplier_locations SET is_primary = 0 WHERE supplier_id = $supplier_id");
            }
            
            $stmt = $conn->prepare("INSERT INTO supplier_locations (supplier_id, name, type, latitude, longitude, address, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issddsi", $supplier_id, $name, $type, $latitude, $longitude, $address, $is_primary);
            
            if ($stmt->execute()) {
                $success = "Location added successfully!";
            } else {
                $error = "Failed to add location: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    elseif ($action === 'update') {
        $location_id = (int) $_POST['location_id'];
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $latitude = trim($_POST['latitude'] ?? '');
        $longitude = trim($_POST['longitude'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $is_primary = isset($_POST['is_primary']) ? 1 : 0;
        
        // Verify location belongs to supplier
        $verify = $conn->prepare("SELECT id FROM supplier_locations WHERE id = ? AND supplier_id = ?");
        $verify->bind_param("ii", $location_id, $supplier_id);
        $verify->execute();
        $exists = $verify->get_result()->fetch_assoc();
        $verify->close();
        
        if (!$exists) {
            $error = "Location not found or unauthorized.";
        } elseif (empty($name) || empty($type) || empty($latitude) || empty($longitude) || empty($address)) {
            $error = "Please fill in all required fields.";
        } elseif (!is_numeric($latitude) || $latitude < -90 || $latitude > 90) {
            $error = "Invalid latitude. Must be between -90 and 90.";
        } elseif (!is_numeric($longitude) || $longitude < -180 || $longitude > 180) {
            $error = "Invalid longitude. Must be between -180 and 180.";
        } else {
            // If setting as primary, unset other primary locations
            if ($is_primary) {
                $conn->query("UPDATE supplier_locations SET is_primary = 0 WHERE supplier_id = $supplier_id");
            }
            
            $stmt = $conn->prepare("UPDATE supplier_locations SET name = ?, type = ?, latitude = ?, longitude = ?, address = ?, is_primary = ? WHERE id = ?");
            $stmt->bind_param("ssddsii", $name, $type, $latitude, $longitude, $address, $is_primary, $location_id);
            
            if ($stmt->execute()) {
                $success = "Location updated successfully!";
            } else {
                $error = "Failed to update location: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    elseif ($action === 'delete') {
        $location_id = (int) $_POST['location_id'];
        
        // Verify location belongs to supplier
        $verify = $conn->prepare("SELECT id FROM supplier_locations WHERE id = ? AND supplier_id = ?");
        $verify->bind_param("ii", $location_id, $supplier_id);
        $verify->execute();
        $exists = $verify->get_result()->fetch_assoc();
        $verify->close();
        
        if (!$exists) {
            $error = "Location not found or unauthorized.";
        } else {
            $stmt = $conn->prepare("DELETE FROM supplier_locations WHERE id = ?");
            $stmt->bind_param("i", $location_id);
            
            if ($stmt->execute()) {
                $success = "Location deleted successfully!";
            } else {
                $error = "Failed to delete location: " . $stmt->error;
            }
            $stmt->close();
        }
    }
    
    elseif ($action === 'set_primary') {
        $location_id = (int) $_POST['location_id'];
        
        // Verify location belongs to supplier
        $verify = $conn->prepare("SELECT id FROM supplier_locations WHERE id = ? AND supplier_id = ?");
        $verify->bind_param("ii", $location_id, $supplier_id);
        $verify->execute();
        $exists = $verify->get_result()->fetch_assoc();
        $verify->close();
        
        if (!$exists) {
            $error = "Location not found or unauthorized.";
        } else {
            // Unset all primary locations
            $conn->query("UPDATE supplier_locations SET is_primary = 0 WHERE supplier_id = $supplier_id");
            
            // Set new primary
            $stmt = $conn->prepare("UPDATE supplier_locations SET is_primary = 1 WHERE id = ?");
            $stmt->bind_param("i", $location_id);
            
            if ($stmt->execute()) {
                $success = "Primary location updated successfully!";
            } else {
                $error = "Failed to update primary location: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Fetch all locations
$locations = [];
if ($supplier_id) {
    $stmt = $conn->prepare("SELECT * FROM supplier_locations WHERE supplier_id = ? ORDER BY is_primary DESC, created_at DESC");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $locations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get location for editing
$editLocation = null;
if (isset($_GET['edit']) && $supplier_id) {
    $edit_id = (int) $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM supplier_locations WHERE id = ? AND supplier_id = ?");
    $stmt->bind_param("ii", $edit_id, $supplier_id);
    $stmt->execute();
    $editLocation = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Locations | Fertilizer System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body { background: #f7f9f6; }
        #map { height: 400px; border-radius: 12px; }
        .location-card {
            transition: all 0.2s;
            border-left: 4px solid #dee2e6;
        }
        .location-card:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .location-card.primary {
            border-left-color: #198754;
            background: linear-gradient(to right, #d4edda 0%, #ffffff 5%);
        }
        .location-type-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .coordinate-input {
            font-family: monospace;
            font-size: 0.9rem;
        }
        #searchResults {
            max-height: 200px;
            overflow-y: auto;
            position: absolute;
            z-index: 1000;
            width: calc(100% - 30px);
        }
        .search-result-item {
            cursor: pointer;
            transition: background 0.2s;
        }
        .search-result-item:hover {
            background: #f8f9fa;
        }
        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #198754;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0">
                <i class="bi bi-geo-alt"></i> Manage Locations
            </h3>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                <i class="bi bi-plus-circle"></i> Add Location
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

        <!-- Info Alert -->
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> 
            <strong>Tip:</strong> Add your warehouse, distribution points, or retail locations to help customers and drivers find you easily. Click on the map to get coordinates.
        </div>

        <!-- Map -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <i class="bi bi-map"></i> Your Locations Map
            </div>
            <div class="card-body">
                <div id="map"></div>
            </div>
        </div>

        <!-- Locations List -->
        <?php if (empty($locations)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-geo-alt-fill display-1 text-muted"></i>
                    <h4 class="mt-3 text-muted">No Locations Added Yet</h4>
                    <p class="text-muted">Add your first location to get started</p>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                        <i class="bi bi-plus-circle"></i> Add Location
                    </button>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($locations as $loc): ?>
                <div class="col-md-6">
                    <div class="card location-card <?= $loc['is_primary'] ? 'primary' : '' ?> h-100 position-relative">
                        <span class="location-type-badge badge bg-<?= $loc['type'] === 'warehouse' ? 'primary' : ($loc['type'] === 'distribution_point' ? 'info' : 'warning') ?>">
                            <?= ucfirst(str_replace('_', ' ', $loc['type'])) ?>
                        </span>
                        
                        <?php if ($loc['is_primary']): ?>
                        <span class="position-absolute top-0 start-0 m-2">
                            <span class="badge bg-success">
                                <i class="bi bi-star-fill"></i> Primary
                            </span>
                        </span>
                        <?php endif; ?>
                        
                        <div class="card-body pt-4">
                            <h5 class="card-title mb-3">
                                <i class="bi bi-building"></i> <?= htmlspecialchars($loc['name']) ?>
                            </h5>
                            
                            <div class="mb-2">
                                <small class="text-muted"><i class="bi bi-geo-alt"></i> Address</small>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($loc['address'])) ?></p>
                            </div>
                            
                            <div class="row g-2 mt-2">
                                <div class="col-6">
                                    <small class="text-muted d-block"><i class="bi bi-compass"></i> Latitude</small>
                                    <code><?= number_format($loc['latitude'], 6) ?></code>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block"><i class="bi bi-compass"></i> Longitude</small>
                                    <code><?= number_format($loc['longitude'], 6) ?></code>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> Added <?= date('M d, Y', strtotime($loc['created_at'])) ?>
                                </small>
                            </div>
                            
                            <div class="d-flex gap-2 mt-3">
                                <a href="?edit=<?= $loc['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                                <a href="https://www.google.com/maps?q=<?= $loc['latitude'] ?>,<?= $loc['longitude'] ?>" 
                                   target="_blank" class="btn btn-sm btn-outline-info">
                                    <i class="bi bi-map"></i> View on Map
                                </a>
                                <?php if (!$loc['is_primary']): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="set_primary">
                                    <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-star"></i> Set Primary
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this location?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="location_id" value="<?= $loc['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-geo-alt"></i> <?= $editLocation ? 'Edit Location' : 'Add New Location' ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $editLocation ? 'update' : 'add' ?>">
                <?php if ($editLocation): ?>
                <input type="hidden" name="location_id" value="<?= $editLocation['id'] ?>">
                <?php endif; ?>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Location Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($editLocation['name'] ?? '') ?>" 
                                   placeholder="e.g., Main Warehouse, Lilongwe Branch" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Type <span class="text-danger">*</span></label>
                            <select name="type" class="form-select" required>
                                <option value="warehouse" <?= ($editLocation['type'] ?? '') === 'warehouse' ? 'selected' : '' ?>>Warehouse</option>
                                <option value="distribution_point" <?= ($editLocation['type'] ?? '') === 'distribution_point' ? 'selected' : '' ?>>Distribution Point</option>
                                <option value="retail" <?= ($editLocation['type'] ?? '') === 'retail' ? 'selected' : '' ?>>Retail Store</option>
                            </select>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control" rows="2" required 
                                      placeholder="Full street address"><?= htmlspecialchars($editLocation['address'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <div class="input-group mb-2">
                                <input type="text" id="locationSearch" class="form-control" 
                                       placeholder="Search for a location (e.g., Lilongwe City Mall)">
                                <button type="button" class="btn btn-primary" onclick="searchLocation()">
                                    <i class="bi bi-search"></i> Search
                                </button>
                                <button type="button" class="btn btn-success" onclick="getCurrentLocation()">
                                    <i class="bi bi-crosshair"></i> Use My Location
                                </button>
                            </div>
                            <div id="searchResults" class="list-group mb-2" style="display: none; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Latitude <span class="text-danger">*</span></label>
                            <input type="text" name="latitude" id="latitude" class="form-control coordinate-input" 
                                   value="<?= $editLocation['latitude'] ?? '' ?>" 
                                   placeholder="-13.9626" step="any" required readonly>
                            <small class="text-muted">Auto-filled from map</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Longitude <span class="text-danger">*</span></label>
                            <input type="text" name="longitude" id="longitude" class="form-control coordinate-input" 
                                   value="<?= $editLocation['longitude'] ?? '' ?>" 
                                   placeholder="33.7741" step="any" required readonly>
                            <small class="text-muted">Auto-filled from map</small>
                        </div>
                        
                        <div class="col-12">
                            <div id="miniMap" style="height: 300px; border-radius: 8px;"></div>
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Click on the map to place a marker, or use the search/location buttons above
                            </small>
                        </div>
                        
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" name="is_primary" class="form-check-input" id="isPrimary"
                                       <?= ($editLocation['is_primary'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="isPrimary">
                                    <i class="bi bi-star text-warning"></i> Set as primary location
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-save"></i> <?= $editLocation ? 'Update' : 'Add' ?> Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Initialize main map
const mainMap = L.map('map').setView([-13.9626, 33.7741], 13); // Lilongwe, Malawi
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
}).addTo(mainMap);

// Add markers for existing locations
const locations = <?= json_encode($locations) ?>;
locations.forEach(loc => {
    const markerColor = loc.is_primary ? 'green' : 'blue';
    const icon = L.divIcon({
        html: `<i class="bi bi-geo-alt-fill" style="font-size: 24px; color: ${markerColor};"></i>`,
        className: 'custom-marker',
        iconSize: [24, 24],
        iconAnchor: [12, 24]
    });
    
    L.marker([loc.latitude, loc.longitude], {icon: icon})
        .bindPopup(`<b>${loc.name}</b><br>${loc.type}<br>${loc.address}`)
        .addTo(mainMap);
});

// Center map on locations if any exist
if (locations.length > 0) {
    const bounds = L.latLngBounds(locations.map(l => [l.latitude, l.longitude]));
    mainMap.fitBounds(bounds, {padding: [50, 50]});
}

// Initialize mini map in modal
let miniMap, miniMarker;
const modal = document.getElementById('addLocationModal');
modal.addEventListener('shown.bs.modal', function () {
    if (!miniMap) {
        miniMap = L.map('miniMap').setView([-13.9626, 33.7741], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(miniMap);
        
        miniMap.on('click', function(e) {
            updateMarker(e.latlng.lat, e.latlng.lng);
            
            // Reverse geocode to get address
            reverseGeocode(e.latlng.lat, e.latlng.lng);
        });
    }
    
    setTimeout(() => miniMap.invalidateSize(), 200);
    
    // Set marker if editing
    const lat = document.getElementById('latitude').value;
    const lng = document.getElementById('longitude').value;
    if (lat && lng) {
        const latlng = L.latLng(lat, lng);
        miniMap.setView(latlng, 15);
        updateMarker(lat, lng);
    }
});

// Update marker on map
function updateMarker(lat, lng) {
    document.getElementById('latitude').value = parseFloat(lat).toFixed(6);
    document.getElementById('longitude').value = parseFloat(lng).toFixed(6);
    
    const latlng = L.latLng(lat, lng);
    
    if (miniMarker) {
        miniMarker.setLatLng(latlng);
    } else {
        miniMarker = L.marker(latlng).addTo(miniMap);
    }
    
    miniMap.setView(latlng, 15);
}

// Get current location using GPS
function getCurrentLocation() {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser');
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="loading-spinner"></span> Getting location...';
    btn.disabled = true;
    
    navigator.geolocation.getCurrentPosition(
        function(position) {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            updateMarker(lat, lng);
            reverseGeocode(lat, lng);
            
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        },
        function(error) {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            let message = 'Unable to get your location. ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Please enable location permissions in your browser.';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Location information unavailable.';
                    break;
                case error.TIMEOUT:
                    message += 'Location request timed out.';
                    break;
                default:
                    message += 'An unknown error occurred.';
            }
            alert(message);
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}

// Search for location using Nominatim
function searchLocation() {
    const query = document.getElementById('locationSearch').value.trim();
    
    if (!query) {
        alert('Please enter a location to search');
        return;
    }
    
    const btn = event.target.closest('button');
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<span class="loading-spinner"></span> Searching...';
    btn.disabled = true;
    
    // Use Nominatim for geocoding
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ', Malawi')}&limit=5`)
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            
            if (data.length === 0) {
                alert('No locations found. Try a different search term.');
                return;
            }
            
            displaySearchResults(data);
        })
        .catch(error => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
            console.error('Search error:', error);
            alert('Search failed. Please try again.');
        });
}

// Display search results
function displaySearchResults(results) {
    const resultsDiv = document.getElementById('searchResults');
    resultsDiv.innerHTML = '';
    
    results.forEach(result => {
        const item = document.createElement('a');
        item.href = '#';
        item.className = 'list-group-item list-group-item-action search-result-item';
        item.innerHTML = `
            <div class="d-flex w-100 justify-content-between">
                <h6 class="mb-1">${result.display_name}</h6>
                <small><i class="bi bi-geo-alt"></i></small>
            </div>
            <small class="text-muted">${result.type}</small>
        `;
        
        item.onclick = function(e) {
            e.preventDefault();
            selectSearchResult(result);
        };
        
        resultsDiv.appendChild(item);
    });
    
    resultsDiv.style.display = 'block';
}

// Select a search result
function selectSearchResult(result) {
    updateMarker(result.lat, result.lon);
    
    // Auto-fill address if empty
    const addressField = document.querySelector('textarea[name="address"]');
    if (!addressField.value) {
        addressField.value = result.display_name;
    }
    
    // Auto-fill name if empty
    const nameField = document.querySelector('input[name="name"]');
    if (!nameField.value && result.name) {
        nameField.value = result.name;
    }
    
    document.getElementById('searchResults').style.display = 'none';
    document.getElementById('locationSearch').value = '';
}

// Reverse geocode to get address from coordinates
function reverseGeocode(lat, lng) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(response => response.json())
        .then(data => {
            const addressField = document.querySelector('textarea[name="address"]');
            if (!addressField.value && data.display_name) {
                addressField.value = data.display_name;
            }
        })
        .catch(error => {
            console.error('Reverse geocode error:', error);
        });
}

// Allow Enter key to search
document.getElementById('locationSearch')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        searchLocation();
    }
});

// Hide search results when clicking outside
document.addEventListener('click', function(e) {
    const searchResults = document.getElementById('searchResults');
    const locationSearch = document.getElementById('locationSearch');
    
    if (searchResults && !searchResults.contains(e.target) && e.target !== locationSearch) {
        searchResults.style.display = 'none';
    }
});

// Auto-open modal if editing
<?php if ($editLocation): ?>
const editModal = new bootstrap.Modal(document.getElementById('addLocationModal'));
editModal.show();
<?php endif; ?>
</script>
</body>
</html>