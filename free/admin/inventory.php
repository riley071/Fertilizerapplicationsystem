<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$admin_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Handle Add Fertilizer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_fertilizer'])) {
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $npk_value = trim($_POST['npk_value']);
    $batch_no = trim($_POST['batch_no']);
    $expiry_date = $_POST['expiry_date'] ?: null;
    $stock = (int) $_POST['stock'];
    $minimum_stock = (int) $_POST['minimum_stock'];
    $price_per_unit = (float) $_POST['price_per_unit'];
    $depot_location = trim($_POST['depot_location']);
    
    if (empty($name) || empty($type)) {
        $error = "Name and type are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO fertilizers (admin_id, name, type, npk_value, batch_no, expiry_date, stock, stock_remaining, minimum_stock, price_per_unit, price, depot_location, certified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("isssssiiiids", $admin_id, $name, $type, $npk_value, $batch_no, $expiry_date, $stock, $stock, $minimum_stock, $price_per_unit, $price_per_unit, $depot_location);
        
        if ($stmt->execute()) {
            $success = "Fertilizer added successfully!";
        } else {
            $error = "Failed to add fertilizer.";
        }
        $stmt->close();
    }
}

// Handle Update Fertilizer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fertilizer'])) {
    $fert_id = (int) $_POST['fertilizer_id'];
    $name = trim($_POST['name']);
    $type = trim($_POST['type']);
    $npk_value = trim($_POST['npk_value']);
    $batch_no = trim($_POST['batch_no']);
    $expiry_date = $_POST['expiry_date'] ?: null;
    $stock = (int) $_POST['stock'];
    $stock_remaining = (int) $_POST['stock_remaining'];
    $minimum_stock = (int) $_POST['minimum_stock'];
    $price_per_unit = (float) $_POST['price_per_unit'];
    $depot_location = trim($_POST['depot_location']);
    $certified = isset($_POST['certified']) ? 1 : 0;
    
    $stmt = $conn->prepare("UPDATE fertilizers SET name=?, type=?, npk_value=?, batch_no=?, expiry_date=?, stock=?, stock_remaining=?, minimum_stock=?, price_per_unit=?, price=?, depot_location=?, certified=? WHERE id=?");
    $stmt->bind_param("sssssiiiddsis", $name, $type, $npk_value, $batch_no, $expiry_date, $stock, $stock_remaining, $minimum_stock, $price_per_unit, $price_per_unit, $depot_location, $certified, $fert_id);
    
    if ($stmt->execute()) {
        $success = "Fertilizer updated successfully!";
    } else {
        $error = "Failed to update fertilizer.";
    }
    $stmt->close();
}

// Handle Restock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock'])) {
    $fert_id = (int) $_POST['fertilizer_id'];
    $add_quantity = (int) $_POST['add_quantity'];
    
    if ($add_quantity > 0) {
        $stmt = $conn->prepare("UPDATE fertilizers SET stock = stock + ?, stock_remaining = stock_remaining + ? WHERE id = ?");
        $stmt->bind_param("iii", $add_quantity, $add_quantity, $fert_id);
        
        if ($stmt->execute()) {
            $success = "Stock updated! Added {$add_quantity} units.";
        } else {
            $error = "Failed to update stock.";
        }
        $stmt->close();
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_fertilizer'])) {
    $fert_id = (int) $_POST['fertilizer_id'];
    
    // Check if fertilizer is used in orders
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM orders WHERE fertilizer_id = ?");
    $stmt->bind_param("i", $fert_id);
    $stmt->execute();
    $used = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    
    if ($used > 0) {
        $error = "Cannot delete fertilizer that has been used in orders.";
    } else {
        $stmt = $conn->prepare("DELETE FROM fertilizers WHERE id = ?");
        $stmt->bind_param("i", $fert_id);
        if ($stmt->execute()) {
            $success = "Fertilizer deleted successfully!";
        }
        $stmt->close();
    }
}

// Filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

$whereConditions = ["1=1"];
if ($filter === 'low_stock') {
    $whereConditions[] = "stock_remaining <= minimum_stock";
} elseif ($filter === 'out_of_stock') {
    $whereConditions[] = "stock_remaining = 0";
} elseif ($filter === 'expiring') {
    $whereConditions[] = "expiry_date IS NOT NULL AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

if ($search) {
    $searchEsc = $conn->real_escape_string($search);
    $whereConditions[] = "(name LIKE '%{$searchEsc}%' OR type LIKE '%{$searchEsc}%' OR batch_no LIKE '%{$searchEsc}%')";
}

$whereClause = implode(' AND ', $whereConditions);

// Fetch fertilizers
$fertilizers = $conn->query("
    SELECT * FROM fertilizers 
    WHERE {$whereClause}
    ORDER BY name ASC
")->fetch_all(MYSQLI_ASSOC);

// Statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(stock_remaining) as total_stock,
        SUM(CASE WHEN stock_remaining <= minimum_stock AND stock_remaining > 0 THEN 1 ELSE 0 END) as low_stock,
        SUM(CASE WHEN stock_remaining = 0 THEN 1 ELSE 0 END) as out_of_stock,
        SUM(stock_remaining * price_per_unit) as inventory_value
    FROM fertilizers
")->fetch_assoc();

// Get specific fertilizer for editing
$editFertilizer = null;
if (isset($_GET['id'])) {
    $edit_id = (int) $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM fertilizers WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $editFertilizer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
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
        body { background: #f4f6f9; }
        .stat-card { border-radius: 12px; border: none; }
        .stock-bar { height: 6px; border-radius: 3px; }
        .stock-critical { background: #dc3545; }
        .stock-low { background: #ffc107; }
        .stock-good { background: #28a745; }
        .product-row { transition: background 0.2s; }
        .product-row:hover { background: #f8f9fa; }
        .badge-certified { background: linear-gradient(135deg, #28a745, #20c997); }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-box-seam"></i> Inventory Management</h3>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addModal">
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

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card shadow-sm" style="border-left: 4px solid #0d6efd;">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Total Products</p>
                        <h3 class="text-primary mb-0"><?= $stats['total_products'] ?? 0 ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm" style="border-left: 4px solid #28a745;">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Total Stock</p>
                        <h3 class="text-success mb-0"><?= number_format($stats['total_stock'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm" style="border-left: 4px solid #ffc107;">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Low Stock Items</p>
                        <h3 class="text-warning mb-0"><?= $stats['low_stock'] ?? 0 ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm" style="border-left: 4px solid #17a2b8;">
                    <div class="card-body">
                        <p class="text-muted small mb-1">Inventory Value</p>
                        <h3 class="text-info mb-0">MWK <?= number_format($stats['inventory_value'] ?? 0) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, type, batch..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Filter</label>
                        <select name="filter" class="form-select">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Products</option>
                            <option value="low_stock" <?= $filter === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= $filter === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                            <option value="expiring" <?= $filter === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Search</button>
                        <a href="inventory.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Inventory Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Product</th>
                                <th>Type / NPK</th>
                                <th>Batch</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($fertilizers)): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No fertilizers found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($fertilizers as $fert): 
                                $stockPercent = $fert['stock'] > 0 ? ($fert['stock_remaining'] / $fert['stock']) * 100 : 0;
                                $stockClass = $fert['stock_remaining'] == 0 ? 'stock-critical' : 
                                             ($fert['stock_remaining'] <= $fert['minimum_stock'] ? 'stock-low' : 'stock-good');
                                $isExpiring = $fert['expiry_date'] && strtotime($fert['expiry_date']) <= strtotime('+30 days');
                            ?>
                            <tr class="product-row">
                                <td>
                                    <strong><?= htmlspecialchars($fert['name']) ?></strong>
                                    <?php if ($fert['certified']): ?>
                                        <span class="badge badge-certified text-white ms-1">Certified</span>
                                    <?php endif; ?>
                                    <?php if ($fert['depot_location']): ?>
                                        <br><small class="text-muted"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($fert['depot_location']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($fert['type']) ?>
                                    <?php if ($fert['npk_value']): ?>
                                        <br><small class="text-muted">NPK: <?= htmlspecialchars($fert['npk_value']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= htmlspecialchars($fert['batch_no'] ?? '-') ?></code></td>
                                <td style="min-width: 120px;">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span><?= $fert['stock_remaining'] ?></span>
                                        <span class="text-muted">/ <?= $fert['stock'] ?></span>
                                    </div>
                                    <div class="progress stock-bar">
                                        <div class="progress-bar <?= $stockClass ?>" style="width: <?= $stockPercent ?>%"></div>
                                    </div>
                                    <small class="text-muted">Min: <?= $fert['minimum_stock'] ?></small>
                                </td>
                                <td>MWK <?= number_format($fert['price_per_unit'], 2) ?></td>
                                <td>
                                    <?php if ($fert['expiry_date']): ?>
                                        <span class="<?= $isExpiring ? 'text-danger fw-bold' : '' ?>">
                                            <?= date('M d, Y', strtotime($fert['expiry_date'])) ?>
                                        </span>
                                        <?php if ($isExpiring): ?>
                                            <br><small class="text-danger">Expiring soon!</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($fert['stock_remaining'] == 0): ?>
                                        <span class="badge bg-danger">Out of Stock</span>
                                    <?php elseif ($fert['stock_remaining'] <= $fert['minimum_stock']): ?>
                                        <span class="badge bg-warning text-dark">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-success" title="Restock"
                                                data-bs-toggle="modal" data-bs-target="#restockModal"
                                                onclick="setRestockId(<?= $fert['id'] ?>, '<?= htmlspecialchars($fert['name']) ?>')">
                                            <i class="bi bi-plus-circle"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" title="Edit"
                                                onclick="editFertilizer(<?= htmlspecialchars(json_encode($fert)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this fertilizer?');">
                                            <input type="hidden" name="fertilizer_id" value="<?= $fert['id'] ?>">
                                            <button type="submit" name="delete_fertilizer" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Fertilizer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
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
                                <option value="">Select type</option>
                                <option value="Nitrogen">Nitrogen</option>
                                <option value="Phosphorus">Phosphorus</option>
                                <option value="Potassium">Potassium</option>
                                <option value="NPK Compound">NPK Compound</option>
                                <option value="Organic">Organic</option>
                                <option value="Micronutrient">Micronutrient</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NPK Value</label>
                            <input type="text" name="npk_value" class="form-control" placeholder="e.g., 20-10-10">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Batch No</label>
                            <input type="text" name="batch_no" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Initial Stock <span class="text-danger">*</span></label>
                            <input type="number" name="stock" class="form-control" required min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" name="minimum_stock" class="form-control" value="10" min="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price per Unit (MWK)</label>
                            <input type="number" name="price_per_unit" class="form-control" step="0.01" min="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Depot Location</label>
                            <input type="text" name="depot_location" class="form-control" placeholder="Warehouse location">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fertilizer" class="btn btn-success">
                        <i class="bi bi-plus-lg"></i> Add Fertilizer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Restock Modal -->
<div class="modal fade" id="restockModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="fertilizer_id" id="restockFertId">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Restock</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <p class="mb-3">Adding stock to: <strong id="restockFertName"></strong></p>
                    <label class="form-label">Quantity to Add</label>
                    <input type="number" name="add_quantity" class="form-control form-control-lg text-center" min="1" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="restock" class="btn btn-success w-100">
                        <i class="bi bi-plus-lg"></i> Add Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="fertilizer_id" id="editFertId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Fertilizer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type</label>
                            <select name="type" id="editType" class="form-select" required>
                                <option value="Nitrogen">Nitrogen</option>
                                <option value="Phosphorus">Phosphorus</option>
                                <option value="Potassium">Potassium</option>
                                <option value="NPK Compound">NPK Compound</option>
                                <option value="Organic">Organic</option>
                                <option value="Micronutrient">Micronutrient</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NPK Value</label>
                            <input type="text" name="npk_value" id="editNpk" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Batch No</label>
                            <input type="text" name="batch_no" id="editBatch" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expiry Date</label>
                            <input type="date" name="expiry_date" id="editExpiry" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Total Stock</label>
                            <input type="number" name="stock" id="editStock" class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Remaining</label>
                            <input type="number" name="stock_remaining" id="editRemaining" class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Minimum</label>
                            <input type="number" name="minimum_stock" id="editMinimum" class="form-control" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Price (MWK)</label>
                            <input type="number" name="price_per_unit" id="editPrice" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Depot Location</label>
                            <input type="text" name="depot_location" id="editDepot" class="form-control">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="certified" id="editCertified" class="form-check-input">
                                <label class="form-check-label" for="editCertified">Certified</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_fertilizer" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function setRestockId(id, name) {
        document.getElementById('restockFertId').value = id;
        document.getElementById('restockFertName').textContent = name;
    }
    
    function editFertilizer(fert) {
        document.getElementById('editFertId').value = fert.id;
        document.getElementById('editName').value = fert.name;
        document.getElementById('editType').value = fert.type;
        document.getElementById('editNpk').value = fert.npk_value || '';
        document.getElementById('editBatch').value = fert.batch_no || '';
        document.getElementById('editExpiry').value = fert.expiry_date || '';
        document.getElementById('editStock').value = fert.stock;
        document.getElementById('editRemaining').value = fert.stock_remaining;
        document.getElementById('editMinimum').value = fert.minimum_stock;
        document.getElementById('editPrice').value = fert.price_per_unit;
        document.getElementById('editDepot').value = fert.depot_location || '';
        document.getElementById('editCertified').checked = fert.certified == 1;
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
</script>
</body>
</html>