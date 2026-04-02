<?php
session_start();
include('../includes/db.php');

// Only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = "";

// Fetch drivers for dropdown
$drivers = $conn->query("SELECT id, full_name FROM users WHERE role='driver'");

// Handle Approve / Reject / Assign Driver
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = (int)$_POST['order_id'];

    if (isset($_POST['approve'])) {
        $stmt = $conn->prepare("UPDATE orders SET status='Approved' WHERE id=?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            $success = "Order approved.";
        } else {
            $error = "Failed to approve order: " . $stmt->error;
        }
    }

    if (isset($_POST['reject'])) {
        $stmt = $conn->prepare("UPDATE orders SET status='Rejected' WHERE id=?");
        $stmt->bind_param("i", $order_id);
        if ($stmt->execute()) {
            $success = "Order rejected.";
        } else {
            $error = "Failed to reject order: " . $stmt->error;
        }
    }

    if (isset($_POST['assign_driver'])) {
        $driver_id = (int)$_POST['driver_id'];
        $stmt = $conn->prepare("UPDATE orders SET driver_id=?, status='In Transit' WHERE id=?");
        $stmt->bind_param("ii", $driver_id, $order_id);
        if ($stmt->execute()) {
            $success = "Driver assigned successfully.";
        } else {
            $error = "Failed to assign driver: " . $stmt->error;
        }
    }
}

// Fetch all orders with supplier, fertilizer, and driver info
$sql = "SELECT o.*, u.full_name AS supplier_name, f.name AS fertilizer_name, f.type, f.npk_value, d.full_name AS driver_name
        FROM orders o
        LEFT JOIN users u ON o.supplier_id = u.id
        LEFT JOIN fertilizers f ON o.fertilizer_id = f.id
        LEFT JOIN users d ON o.driver_id = d.id
        ORDER BY o.created_at DESC";
$orders = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Manage Orders</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
<div class="container p-4">
    <h2>Supplier Orders Management</h2>

    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Supplier</th>
                <th>Fertilizer</th>
                <th>Type</th>
                <th>NPK</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Driver</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($order = $orders->fetch_assoc()): ?>
            <tr class="<?= ($order['status'] === 'Rejected') ? 'table-danger' : '' ?>">
                <form method="POST">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <td><?= htmlspecialchars($order['supplier_name']) ?></td>
                    <td><?= htmlspecialchars($order['fertilizer_name']) ?></td>
                    <td><?= htmlspecialchars($order['type']) ?></td>
                    <td><?= htmlspecialchars($order['npk_value']) ?></td>
                    <td><?= (int)$order['quantity'] ?></td>
                    <td><?= htmlspecialchars($order['status']) ?></td>
                    <td>
                        <?php if ($order['status'] === 'Approved' || $order['status'] === 'In Transit'): ?>
                            <select name="driver_id" class="form-control form-control-sm">
                                <option value="">Select Driver</option>
                                <?php
                                $drivers->data_seek(0); // reset pointer
                                while ($driver = $drivers->fetch_assoc()): ?>
                                    <option value="<?= $driver['id'] ?>" <?= ($driver['id'] == $order['driver_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($driver['full_name']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="d-flex gap-1">
                        <?php if ($order['status'] === 'Pending'): ?>
                            <button type="submit" name="approve" class="btn btn-sm btn-success">Approve</button>
                            <button type="submit" name="reject" class="btn btn-sm btn-danger">Reject</button>
                        <?php elseif ($order['status'] === 'Approved'): ?>
                            <button type="submit" name="assign_driver" class="btn btn-sm btn-primary">Assign Driver</button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </form>
            </tr>
        <?php endwhile; ?>
        <?php if ($orders->num_rows === 0): ?>
            <tr><td colspan="8" class="text-center">No orders yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
