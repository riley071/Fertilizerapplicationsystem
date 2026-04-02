<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch supplier ID for this user
$stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($supplier_id);
$stmt->fetch();
$stmt->close();

if (!$supplier_id) {
    die("supplier profile not found.");
}

// Fetch payments linked to this supplier’s orders
$query = "
    SELECT p.id, p.order_id, p.total_price, p.subsidy, p.amount_paid, 
           p.payment_method, p.transaction_id, p.payment_date,
           o.quantity, o.status
    FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE o.supplier_id = ?
    ORDER BY p.payment_date DESC
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Payments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="mb-4 text-success">💳 My Payments</h3>

        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle">
                    <thead class="table-success">
                        <tr>
                            <th>#</th>
                            <th>Order ID</th>
                            <th>Quantity</th>
                            <th>Total Price (MK)</th>
                            <th>Subsidy (MK)</th>
                            <th>Amount Paid (MK)</th>
                            <th>Method</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td>#<?= $row['order_id'] ?></td>
                            <td><?= $row['quantity'] ?></td>
                            <td><?= number_format($row['total_price'], 2) ?></td>
                            <td><?= number_format($row['subsidy'], 2) ?></td>
                            <td><strong class="text-success"><?= number_format($row['amount_paid'], 2) ?></strong></td>
                            <td><?= htmlspecialchars($row['payment_method']) ?></td>
                            <td><?= htmlspecialchars($row['transaction_id']) ?></td>
                            <td><span class="badge bg-info"><?= $row['status'] ?></span></td>
                            <td><?= date("d M Y, H:i", strtotime($row['payment_date'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No payments found.</div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
