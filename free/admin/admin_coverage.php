<?php
session_start();
include('../includes/db.php');

// Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get all supplier locations
$locations = $conn->query("
    SELECT sl.*, u.full_name as supplier_name 
    FROM supplier_locations sl
    JOIN users u ON sl.supplier_id = u.id
    ORDER BY u.full_name, sl.name
");

// Get coverage statistics
$coverageStats = $conn->query("
    SELECT 
        COUNT(DISTINCT district) as districts_covered,
        COUNT(DISTINCT ta) as tas_covered,
        COUNT(DISTINCT supplier_id) as active_suppliers
    FROM coverage_areas
")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Distribution Coverage</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        #coverageMap { height: 600px; width: 100%; }
        .stats-card { transition: all 0.3s; }
        .stats-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h2 class="mb-4">Distribution Coverage</h2>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h3 class="card-title"><?= $coverageStats['districts_covered'] ?></h3>
                        <p class="card-text">Districts Covered</p>
                        <i class="bi bi-map" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h3 class="card-title"><?= $coverageStats['tas_covered'] ?></h3>
                        <p class="card-text">Traditional Authorities Covered</p>
                        <i class="bi bi-geo-alt" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body">
                        <h3 class="card-title"><?= $coverageStats['active_suppliers'] ?></h3>
                        <p class="card-text">Active Suppliers</p>
                        <i class="bi bi-people" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-map"></i> Coverage Map
            </div>
            <div class="card-body">
                <div id="coverageMap"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="bi bi-list-ul"></i> Supplier Locations
            </div>
            <div class="card-body">
                <?php if ($locations->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Supplier</th>
                                    <th>Location Name</th>
                                    <th>Type</th>
                                    <th>Address</th>
                                    <th>Coordinates</th>
                                    <th>Primary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($loc = $locations->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($loc['supplier_name']) ?></td>
                                    <td><?= htmlspecialchars($loc['name']) ?></td>
                                    <td><?= ucfirst($loc['type']) ?></td>
                                    <td><?= htmlspecialchars($loc['address']) ?></td>
                                    <td><?= $loc['latitude'] ?>, <?= $loc['longitude'] ?></td>
                                    <td><?= $loc['is_primary'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '' ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-info-circle" style="font-size: 2rem;"></i>
                        <h4 class="mt-3">No supplier locations found</h4>
                        <p>Suppliers need to add their locations first</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Load Google Maps API -->
<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=visualization&callback=initMap" async defer></script>

<script>
function initMap() {
    const map = new google.maps.Map(document.getElementById("coverageMap"), {
        zoom: 7,
        center: { lat: -13.9626, lng: 33.7741 }, // Default to Malawi center
    });

    // Add heatmap data (this would be more sophisticated in production)
    const heatmapData = [
        <?php 
        $locations->data_seek(0);
        while ($loc = $locations->fetch_assoc()): ?>
            {location: new google.maps.LatLng(<?= $loc['latitude'] ?>, <?= $loc['longitude'] ?>), weight: 2},
        <?php endwhile; ?>
    ];

    const heatmap = new google.maps.visualization.HeatmapLayer({
        data: heatmapData,
        map: map,
        radius: 30,
    });

    // Add markers for each location
    <?php 
    $locations->data_seek(0);
    while ($loc = $locations->fetch_assoc()): ?>
        new google.maps.Marker({
            position: { lat: <?= $loc['latitude'] ?>, lng: <?= $loc['longitude'] ?> },
            map,
            title: "<?= addslashes($loc['supplier_name'] . ' - ' . $loc['name']) ?>",
            icon: {
                url: "https://maps.google.com/mapfiles/ms/icons/<?= $loc['is_primary'] ? 'blue' : 'red' ?>-dot.png",
            },
        });
    <?php endwhile; ?>
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>