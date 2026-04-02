<?php
session_start();
include('../includes/db.php');

// Ensure only logged-in farmers access this page
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = $error = "";

// Get the farmer's actual ID from farmers table
$stmt = $conn->prepare("SELECT id FROM farmers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$farmerData = $result->fetch_assoc();

if (!$farmerData) {
    die("Farmer profile not found. Please contact support.");
}
$farmer_id = $farmerData['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $fertilizer_id = (int)$_POST['fertilizer_id'];
    $quantity = (int)$_POST['quantity'];

    // Get selected fertilizer details
    $stmt = $conn->prepare("SELECT price, stock FROM fertilizers WHERE id = ?");
    $stmt->bind_param("i", $fertilizer_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $fertilizer = $result->fetch_assoc();

    if ($fertilizer && $fertilizer['stock'] >= $quantity) {
        $price_per_unit = $fertilizer['price'];
        $total_price = $price_per_unit * $quantity;
        $status = 'Requested';

        // Start transaction
        $conn->begin_transaction();
        try {
            // Insert order
            $stmt = $conn->prepare("INSERT INTO orders (farmer_id, fertilizer_id, quantity, price_per_unit, total_price, status) 
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidds", $farmer_id, $fertilizer_id, $quantity, $price_per_unit, $total_price, $status);
            $stmt->execute();

            // Update stock
            $stmt = $conn->prepare("UPDATE fertilizers SET stock = stock - ? WHERE id = ?");
            $stmt->bind_param("ii", $quantity, $fertilizer_id);
            $stmt->execute();

            $conn->commit();
            $success = "Order placed successfully!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error placing order: " . $e->getMessage();
        }
    } else {
        $error = "Insufficient stock or invalid fertilizer.";
    }
}

// Get certified fertilizers
$query = "SELECT f.id, f.name, f.price, f.stock, u.full_name AS supplier
          FROM fertilizers f
          JOIN users u ON f.supplier_id = u.id
          WHERE f.certified = 1 AND f.stock > 0";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Place Order | Fertilizer System</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <link href="../assets/css/style.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
</head>
<body>
<div class="d-flex">
  <?php include('../includes/sidebar.php'); ?>

  <div class="flex-grow-1 p-4">
    <h3 class="mb-4 text-success">Place Fertilizer Order</h3>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= $success ?></div>
    <?php elseif ($error): ?>
      <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Order Form -->
    <form method="POST" class="mb-4">
      <div class="row mb-3">
        <div class="col-md-6">
          <label for="fertilizer_id" class="form-label">Select Fertilizer</label>
          <select name="fertilizer_id" id="fertilizer_id" class="form-select" required>
            <option value="" selected disabled>-- Choose Fertilizer --</option>
            <?php if ($result && $result->num_rows > 0): ?>
              <?php while ($row = $result->fetch_assoc()): ?>
                <option value="<?= $row['id'] ?>">
                  <?= htmlspecialchars($row['name']) ?> (MK<?= number_format($row['price']) ?>/kg) | 
                  Stock: <?= $row['stock'] ?> | 
                  <?= htmlspecialchars($row['supplier']) ?>
                </option>
              <?php endwhile; ?>
            <?php else: ?>
              <option disabled>No certified fertilizers available</option>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label for="quantity" class="form-label">Quantity (kg)</label>
          <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button type="submit" name="place_order" class="btn btn-success w-100">Place Order</button>
        </div>
      </div>
    </form>

    <!-- Orders Table -->
    <h5 class="text-secondary mt-5">Your Recent Orders</h5>
    <table class="table table-bordered table-striped mt-2">
      <thead>
        <tr>
          <th>Fertilizer</th>
          <th>Qty (kg)</th>
          <th>Total Price</th>
          <th>Date</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $stmt = $conn->prepare("SELECT o.*, f.name AS fertilizer_name
                                FROM orders o
                                JOIN fertilizers f ON o.fertilizer_id = f.id
                                WHERE o.farmer_id = ?
                                ORDER BY o.order_date DESC");
        $stmt->bind_param("i", $farmer_id);
        $stmt->execute();
        $orders = $stmt->get_result();

        while ($order = $orders->fetch_assoc()):
        ?>
          <tr>
            <td><?= htmlspecialchars($order['fertilizer_name']) ?></td>
            <td><?= $order['quantity'] ?></td>
            <td class="fw-bold">MK<?= number_format($order['total_price'], 2) ?></td>
            <td><?= date('d M Y', strtotime($order['order_date'])) ?></td>
            <td>
              <span class="badge 
                <?php 
                  if ($order['status'] == 'Approved') echo 'bg-primary';
                  elseif ($order['status'] == 'Dispatched') echo 'bg-info';
                  elseif ($order['status'] == 'Delivered') echo 'bg-success';
                  else echo 'bg-warning';
                ?>">
                <?= $order['status'] ?>
              </span>
            </td>
            <td>
              <?php if (in_array($order['status'], ['Requested', 'Pending'])): ?>
                <button class="btn btn-sm btn-primary pay-btn" data-order-id="<?= $order['id'] ?>">Pay</button>
              <?php else: ?>
                <span class="text-muted">-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>
<script src="https://js.stripe.com/v3/"></script>

<script>
const stripe = Stripe("pk_test_51Mw4lyJOqqGZ4qxAgeneCNQzAYdowYThGW2Aje9396l0ajwfqyCpzyfCPehTz4JYK1cZgVMRWkvGDiK58nNdPfKZ00n0Bz8MhR"); // Replace with your real publishable key

document.addEventListener('click', async function(e){
  if (e.target.matches('.pay-btn')) {
    const orderId = e.target.dataset.orderId;
    e.target.disabled = true;

    try {
      const response = await fetch('../farmer/create_checkout_session.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId })
      });

      const session = await response.json();
      if (session.id) {
        stripe.redirectToCheckout({ sessionId: session.id });
      } else {
        alert(session.error || "Failed to start checkout.");
        e.target.disabled = false;
      }
    } catch (err) {
      console.error(err);
      alert("Error creating payment session");
      e.target.disabled = false;
    }
  }
});
</script>

</body>
</html>
