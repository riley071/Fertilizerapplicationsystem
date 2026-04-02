<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$driver_id = (int) $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Date filter
$dateFrom = $_GET['from'] ?? '';
$dateTo = $_GET['to'] ?? '';
$dateWhere = '';
$params = [$driver_id];
$types = 'i';

if ($dateFrom) {
    $dateWhere .= " AND DATE(d.delivered_on) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo) {
    $dateWhere .= " AND DATE(d.delivered_on) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

// Get total count
$countSql = "SELECT COUNT(*) as total FROM deliveries d WHERE d.driver_id = ? AND d.status = 'Delivered' {$dateWhere}";
$stmt = $conn->prepare($countSql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalRecords / $perPage);

// Fetch deliveries
$sql = "
    SELECT d.*, o.quantity, o.total_price, o.order_date,
           f.name as fertilizer_name, f.type as fertilizer_type,
           s.company_name, s.address as supplier_address
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    WHERE d.driver_id = ? AND d.status = 'Delivered' {$dateWhere}
    ORDER BY d.delivered_on DESC
    LIMIT {$perPage} OFFSET {$offset}
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_delivered,
        COUNT(DISTINCT DATE(delivered_on)) as active_days,
        SUM(o.quantity) as total_quantity
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    WHERE d.driver_id = {$driver_id} AND d.status = 'Delivered'
")->fetch_assoc();

// Monthly stats for chart
$monthlyStats = $conn->query("
    SELECT 
        DATE_FORMAT(delivered_on, '%Y-%m') as month,
        COUNT(*) as count
    FROM deliveries
    WHERE driver_id = {$driver_id} AND status = 'Delivered'
    AND delivered_on >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(delivered_on, '%Y-%m')
    ORDER BY month ASC
")->fetch_all(MYSQLI_ASSOC);

$chartLabels = array_map(fn($m) => date('M Y', strtotime($m['month'] . '-01')), $monthlyStats);
$chartData = array_map(fn($m) => $m['count'], $monthlyStats);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Delivery History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .stat-card { border-radius: 12px; border: none; }
        .history-item {
            border-left: 4px solid #28a745;
            background: white;
            border-radius: 0 8px 8px 0;
            transition: all 0.2s;
        }
        .history-item:hover { box-shadow: 0 3px 15px rgba(0,0,0,0.1); }
        .date-badge {
            background: #e8f5e9;
            color: #28a745;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <h3 class="text-success mb-4"><i class="bi bi-clock-history"></i> Delivery History</h3>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['total_delivered'] ?? 0 ?></h3>
                        <small class="text-muted">Total Deliveries</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= $stats['active_days'] ?? 0 ?></h3>
                        <small class="text-muted">Active Days</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="bi bi-box-seam text-info" style="font-size: 2rem;"></i>
                        <h3 class="mb-0 mt-2"><?= number_format($stats['total_quantity'] ?? 0) ?></h3>
                        <small class="text-muted">Units Delivered</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <i class="bi bi-graph-up"></i> Deliveries Over Time
            </div>
            <div class="card-body">
                <canvas id="deliveryChart" height="100"></canvas>
            </div>
        </div>

        <!-- Filter -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success me-2">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                        <a href="delivery_history.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- History List -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list"></i> Completed Deliveries</span>
                <span class="badge bg-success"><?= $totalRecords ?> total</span>
            </div>
            <div class="card-body">
                <?php if (empty($deliveries)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">No delivery history found.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $currentDate = '';
                    foreach ($deliveries as $del): 
                        $delDate = date('Y-m-d', strtotime($del['delivered_on']));
                        if ($delDate !== $currentDate):
                            $currentDate = $delDate;
                    ?>
                        <div class="mb-3">
                            <span class="date-badge">
                                <i class="bi bi-calendar3"></i> <?= date('l, F d, Y', strtotime($delDate)) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="history-item p-3 mb-3">
                        <div class="row align-items-center">
                            <div class="col-md-7">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="mb-1">Delivery #<?= $del['id'] ?></h6>
                                        <p class="mb-1 text-muted">
                                            <?= htmlspecialchars($del['fertilizer_name']) ?>
                                            <span class="badge bg-light text-dark"><?= htmlspecialchars($del['fertilizer_type']) ?></span>
                                        </p>
                                        <small class="text-muted">
                                            <i class="bi bi-building"></i> <?= htmlspecialchars($del['company_name']) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 text-center">
                                <div class="text-muted small">Quantity</div>
                                <strong class="fs-5"><?= $del['quantity'] ?></strong>
                                <div class="text-muted small">units</div>
                            </div>
                            <div class="col-md-2 text-end">
                                <span class="badge bg-success">
                                    <i class="bi bi-check"></i> Delivered
                                </span>
                                <div class="small text-muted mt-1">
                                    <?= date('H:i', strtotime($del['delivered_on'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&from=<?= $dateFrom ?>&to=<?= $dateTo ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Delivery Chart
    const ctx = document.getElementById('deliveryChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [{
                label: 'Deliveries',
                data: <?= json_encode($chartData) ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            }
        }
    });
</script>
</body>
</html>