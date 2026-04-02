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

// Check certificate status
$has_certificate = false;
if ($supplier_id) {
    $cert_check = $conn->query("SELECT id FROM certificates WHERE supplier_id = $user_id AND status = 'Approved' AND (expires_on IS NULL OR expires_on >= CURDATE())");
    $has_certificate = $cert_check && $cert_check->num_rows > 0;
}

// Handle order submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supplier_id) {
    $fertilizer_id = (int) $_POST['fertilizer_id'];
    $quantity = (int) $_POST['quantity'];
    
    if ($quantity <= 0) {
        $error = "Please enter a valid quantity.";
    } else {
        // Get fertilizer details
        $stmt = $conn->prepare("SELECT name, price_per_unit, stock_remaining, certified FROM fertilizers WHERE id = ?");
        $stmt->bind_param("i", $fertilizer_id);
        $stmt->execute();
        $fertilizer = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$fertilizer) {
            $error = "Fertilizer not found.";
        } elseif ($quantity > $fertilizer['stock_remaining']) {
            $error = "Requested quantity exceeds available stock. Available: " . $fertilizer['stock_remaining'] . " units.";
        } elseif ($fertilizer['certified'] && !$has_certificate) {
            $error = "You need an approved certificate to order certified fertilizers. Please apply for a certificate first.";
        } else {
            $total_price = $quantity * $fertilizer['price_per_unit'];
            
            // Insert order
            $stmt = $conn->prepare("INSERT INTO orders (supplier_id, fertilizer_id, quantity, price_per_unit, total_price, status, order_date) VALUES (?, ?, ?, ?, ?, 'Requested', NOW())");
            $stmt->bind_param("iiidd", $supplier_id, $fertilizer_id, $quantity, $fertilizer['price_per_unit'], $total_price);
            
            if ($stmt->execute()) {
                $order_id = $stmt->insert_id;
                $success = "Order placed successfully! Order #$order_id is now pending approval.";
                
                // Update stock_remaining
                $update_stock = $conn->prepare("UPDATE fertilizers SET stock_remaining = stock_remaining - ? WHERE id = ?");
                $update_stock->bind_param("ii", $quantity, $fertilizer_id);
                $update_stock->execute();
                $update_stock->close();
            } else {
                $error = "Failed to place order: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$type_filter = $_GET['type'] ?? '';
$certified_filter = $_GET['certified'] ?? '';

// Fetch available fertilizers with filters
$query = "SELECT * FROM fertilizers WHERE stock_remaining > 0";
$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (name LIKE ? OR type LIKE ? OR npk_value LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

if (!empty($type_filter)) {
    $query .= " AND type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

if ($certified_filter !== '') {
    $query .= " AND certified = ?";
    $params[] = (int)$certified_filter;
    $types .= "i";
}

$query .= " ORDER BY name ASC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$fertilizers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique fertilizer types for filter
$types_result = $conn->query("SELECT DISTINCT type FROM fertilizers WHERE type IS NOT NULL AND type != '' ORDER BY type");
$fertilizer_types = $types_result->fetch_all(MYSQLI_ASSOC);

// Pre-select fertilizer if ID in URL
$selected_fertilizer_id = isset($_GET['fertilizer_id']) ? (int)$_GET['fertilizer_id'] : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Place Order | Fertilizer System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .fertilizer-card {
            transition: all 0.2s;
            cursor: pointer;
            height: 100%;
        }
        .fertilizer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }
        .fertilizer-card.selected {
            border: 2px solid #198754;
            background: linear-gradient(to bottom, #d4edda 0%, #ffffff 10%);
        }
        .stock-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .cert-badge {
            position: absolute;
            top: 10px;
            left: 10px;
        }
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #198754;
        }
        .order-summary {
            position: sticky;
            top: 20px;
        }
        .npk-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0">
                <i class="bi bi-cart-plus"></i> Place New Order
            </h3>
            <a href="my_orders.php" class="btn btn-outline-success">
                <i class="bi bi-bag-check"></i> My Orders
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                <a href="my_orders.php" class="alert-link">View your orders</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <?php if (!$has_certificate): ?>
                    <a href="apply_certificate.php" class="alert-link">Apply for certificate</a>
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Certificate Warning -->
        <?php if (!$has_certificate): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> 
            <strong>Note:</strong> You don't have an approved certificate yet. You can only order non-certified fertilizers. 
            <a href="apply_certificate.php" class="alert-link">Apply for certificate</a> to access certified products.
        </div>
        <?php endif; ?>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-funnel"></i> Filters
                    </div>
                    <div class="card-body">
                        <form method="GET" id="filterForm">
                            <div class="mb-3">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Fertilizer name..." 
                                       value="<?= htmlspecialchars($search) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Type</label>
                                <select name="type" class="form-select">
                                    <option value="">All Types</option>
                                    <?php foreach ($fertilizer_types as $type): ?>
                                        <option value="<?= htmlspecialchars($type['type']) ?>" 
                                                <?= $type_filter === $type['type'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Certification</label>
                                <select name="certified" class="form-select">
                                    <option value="">All Products</option>
                                    <option value="1" <?= $certified_filter === '1' ? 'selected' : '' ?>>Certified Only</option>
                                    <option value="0" <?= $certified_filter === '0' ? 'selected' : '' ?>>Non-Certified Only</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 mb-2">
                                <i class="bi bi-search"></i> Apply Filters
                            </button>
                            <a href="place_order.php" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-x-circle"></i> Clear Filters
                            </a>
                        </form>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-info-circle"></i> Available</h6>
                        <h3 class="text-success mb-0"><?= count($fertilizers) ?></h3>
                        <small class="text-muted">Fertilizer products</small>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="col-lg-9">
                <?php if (empty($fertilizers)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <h4 class="mt-3 text-muted">No Fertilizers Available</h4>
                            <p class="text-muted">No products match your filters or all products are out of stock.</p>
                            <a href="place_order.php" class="btn btn-outline-success">Clear Filters</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($fertilizers as $fert): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card fertilizer-card position-relative" 
                                 data-fertilizer-id="<?= $fert['id'] ?>"
                                 data-fertilizer-name="<?= htmlspecialchars($fert['name']) ?>"
                                 data-fertilizer-price="<?= $fert['price_per_unit'] ?>"
                                 data-fertilizer-stock="<?= $fert['stock_remaining'] ?>"
                                 onclick="selectFertilizer(this)">
                                
                                <?php if ($fert['certified']): ?>
                                <span class="cert-badge badge bg-success">
                                    <i class="bi bi-patch-check"></i> Certified
                                </span>
                                <?php endif; ?>
                                
                                <span class="stock-badge badge bg-<?= $fert['stock_remaining'] > 50 ? 'success' : ($fert['stock_remaining'] > 20 ? 'warning' : 'danger') ?>">
                                    <i class="bi bi-box-seam"></i> <?= $fert['stock_remaining'] ?> units
                                </span>
                                
                                <div class="card-body pt-5">
                                    <h5 class="card-title"><?= htmlspecialchars($fert['name']) ?></h5>
                                    
                                    <div class="mb-2">
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-tag"></i> <?= htmlspecialchars($fert['type']) ?>
                                        </span>
                                        <?php if ($fert['npk_value']): ?>
                                        <span class="npk-badge ms-1">
                                            NPK: <?= htmlspecialchars($fert['npk_value']) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($fert['batch_no']): ?>
                                    <p class="text-muted small mb-1">
                                        <i class="bi bi-upc"></i> Batch: <?= htmlspecialchars($fert['batch_no']) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($fert['expiry_date']): ?>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-calendar-x"></i> Expires: <?= date('M d, Y', strtotime($fert['expiry_date'])) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($fert['depot_location']): ?>
                                    <p class="text-muted small mb-2">
                                        <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($fert['depot_location']) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="price-tag mt-3">
                                        MWK <?= number_format($fert['price_per_unit'], 0) ?>
                                        <small class="text-muted">/unit</small>
                                    </div>
                                    
                                    <button class="btn btn-success w-100 mt-3" 
                                            onclick="event.stopPropagation(); openOrderModal(<?= $fert['id'] ?>)">
                                        <i class="bi bi-cart-plus"></i> Order Now
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Order Modal -->
<div class="modal fade" id="orderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-cart-plus"></i> Place Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="fertilizer_id" id="modal_fertilizer_id">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Fertilizer</label>
                        <p id="modal_fertilizer_name" class="form-control-plaintext"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Price per Unit</label>
                        <p id="modal_fertilizer_price" class="form-control-plaintext text-success"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Available Stock</label>
                        <p id="modal_fertilizer_stock" class="form-control-plaintext"></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Quantity (units) <span class="text-danger">*</span></label>
                        <input type="number" name="quantity" id="order_quantity" 
                               class="form-control" min="1" value="1" required 
                               oninput="calculateTotal()">
                    </div>
                    
                    <div class="alert alert-info">
                        <div class="d-flex justify-content-between">
                            <strong>Total Price:</strong>
                            <strong id="total_price" class="text-success">MWK 0</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-circle"></i> Confirm Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
let currentPrice = 0;
let currentStock = 0;

function selectFertilizer(card) {
    document.querySelectorAll('.fertilizer-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
}

function openOrderModal(fertilizerId) {
    const card = document.querySelector(`[data-fertilizer-id="${fertilizerId}"]`);
    const name = card.dataset.fertilizerName;
    const price = parseFloat(card.dataset.fertilizerPrice);
    const stock = parseInt(card.dataset.fertilizerStock);
    
    currentPrice = price;
    currentStock = stock;
    
    document.getElementById('modal_fertilizer_id').value = fertilizerId;
    document.getElementById('modal_fertilizer_name').textContent = name;
    document.getElementById('modal_fertilizer_price').textContent = 'MWK ' + price.toLocaleString('en-US', {minimumFractionDigits: 0});
    document.getElementById('modal_fertilizer_stock').textContent = stock + ' units';
    document.getElementById('order_quantity').max = stock;
    document.getElementById('order_quantity').value = 1;
    
    calculateTotal();
    
    const modal = new bootstrap.Modal(document.getElementById('orderModal'));
    modal.show();
}

function calculateTotal() {
    const quantity = parseInt(document.getElementById('order_quantity').value) || 0;
    const total = quantity * currentPrice;
    document.getElementById('total_price').textContent = 'MWK ' + total.toLocaleString('en-US', {minimumFractionDigits: 0});
    
    // Validate quantity
    if (quantity > currentStock) {
        document.getElementById('order_quantity').setCustomValidity('Exceeds available stock');
    } else {
        document.getElementById('order_quantity').setCustomValidity('');
    }
}

// Auto-open modal if fertilizer_id in URL
<?php if ($selected_fertilizer_id): ?>
window.addEventListener('DOMContentLoaded', function() {
    const card = document.querySelector(`[data-fertilizer-id="<?= $selected_fertilizer_id ?>"]`);
    if (card) {
        openOrderModal(<?= $selected_fertilizer_id ?>);
    }
});
<?php endif; ?>
</script>
</body>
</html>