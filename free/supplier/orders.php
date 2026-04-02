<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$supplier_id = $_SESSION['user_id'];
$success = $error = "";

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['order_id'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("
        SELECT o.quantity, o.status, f.id AS fertilizer_id 
        FROM orders o 
        JOIN fertilizers f ON o.fertilizer_id = f.id 
        WHERE o.id = ? AND f.supplier_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $supplier_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($order) {
        $current_status = $order['status'];

        if ($action === 'accept' && $current_status === 'Requested') {
            $update = $conn->prepare("UPDATE orders SET status = 'Approved' WHERE id = ?");
            $update->bind_param("i", $order_id);
            $update->execute() ? $success = "Order approved successfully." : $error = "Failed to approve order.";
            $update->close();

        } elseif ($action === 'dispatch' && $current_status === 'Approved') {
            $update = $conn->prepare("UPDATE orders SET status = 'Dispatched' WHERE id = ?");
            $update->bind_param("i", $order_id);
            $update->execute() ? $success = "Order dispatched successfully." : $error = "Failed to dispatch order.";
            $update->close();

        } elseif ($action === 'deliver' && $current_status === 'Dispatched') {
            $conn->begin_transaction();
            try {
                $updateOrder = $conn->prepare("UPDATE orders SET status = 'Delivered' WHERE id = ?");
                $updateOrder->bind_param("i", $order_id);
                $updateOrder->execute();
                $updateOrder->close();

                $updateStock = $conn->prepare("UPDATE fertilizers SET stock = stock - ? WHERE id = ?");
                $updateStock->bind_param("ii", $order['quantity'], $order['fertilizer_id']);
                $updateStock->execute();
                $updateStock->close();

                $conn->commit();
                $success = "Order delivered and stock updated.";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Transaction failed: " . $e->getMessage();
            }
        } else {
            $error = "Invalid action or current order status.";
        }
    } else {
        $error = "Order not found or not authorized.";
    }
}

// Fetch all orders with payment info and optional farmer location
$stmt = $conn->prepare("
    SELECT 
        o.id, o.quantity, (o.quantity * f.price) AS total_price,
        o.status, o.order_date,
        u.full_name AS farmer_name, f.name AS fertilizer_name,
        p.amount_paid, p.payment_method,
        fm.latitude AS farmer_lat, fm.longitude AS farmer_lon
    FROM orders o
    JOIN farmers fm ON o.farmer_id = fm.id
    JOIN users u ON fm.user_id = u.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE f.supplier_id = ?
    ORDER BY o.order_date DESC
");
$stmt->bind_param("i", $supplier_id);
$stmt->execute();
$orders = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Orders | Fertilizer System</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-primary mb-4">Manage Orders & Deliveries</h3>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                <tr>
                    <th>Farmer</th>
                    <th>Fertilizer</th>
                    <th>Quantity</th>
                    <th>Total Price</th>
                    <th>Payment</th>
                    <th>Order Date</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($orders->num_rows === 0): ?>
                    <tr><td colspan="9" class="text-center">No orders found.</td></tr>
                <?php else: ?>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($order['farmer_name']) ?></td>
                            <td><?= htmlspecialchars($order['fertilizer_name']) ?></td>
                            <td><?= $order['quantity'] ?> kg</td>
                            <td>MK<?= number_format($order['total_price'], 2) ?></td>
                            <td>
                                <?= $order['amount_paid'] ? 
                                    'MK'.number_format($order['amount_paid'],2).' via '.$order['payment_method'] :
                                    '<span class="text-muted">Not paid</span>' ?>
                            </td>
                            <td><?= date('d M Y', strtotime($order['order_date'])) ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $order['status'] === 'Requested' ? 'secondary' : 
                                    ($order['status'] === 'Approved' ? 'info' : 
                                    ($order['status'] === 'Dispatched' ? 'warning' : 
                                    ($order['status'] === 'Delivered' ? 'success' : 'dark'))) ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($order['farmer_lat'] && $order['farmer_lon']): ?>
                                    <a href="https://www.openstreetmap.org/?mlat=<?= $order['farmer_lat'] ?>&mlon=<?= $order['farmer_lon'] ?>&zoom=15" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-flex gap-1">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <?php if ($order['status'] === 'Requested'): ?>
                                        <button type="submit" name="action" value="accept" class="btn btn-sm btn-success">Approve</button>
                                    <?php elseif ($order['status'] === 'Approved'): ?>
                                        <button type="submit" name="action" value="dispatch" class="btn btn-sm btn-warning">Dispatch</button>
                                    <?php elseif ($order['status'] === 'Dispatched'): ?>
                                        <button type="submit" name="action" value="deliver" class="btn btn-sm btn-primary">Deliver</button>
                                    <?php else: ?>
                                        <span class="text-muted">No actions</span>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
