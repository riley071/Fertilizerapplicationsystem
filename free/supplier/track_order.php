<?php
session_start();
include('../includes/db.php');
include('../includes/functions.php');

if (!is_logged_in()) {
    header('Location: ../login.php');
    exit();
}

$order = null;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cert_id = trim($_POST['cert_id']);

    if (!empty($cert_id)) {
        $stmt = $conn->prepare("
            SELECT o.*, f.name AS fertilizer_name, u.full_name AS farmer_name
            FROM orders o
            JOIN fertilizers f ON o.fertilizer_id = f.id
            JOIN users u ON o.farmer_id = u.id
            WHERE o.certificate_id = ?
        ");
        $stmt->bind_param("s", $cert_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $order = $result->fetch_assoc();

        if (!$order) {
            $error = "No order found for this Certificate ID.";
        }
    } else {
        $error = "Please enter a valid Certificate ID.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Track Order | Fertilizer System</title>
  <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://unpkg.com/html5-qrcode@2.3.8/minified/html5-qrcode.min.js"></script>
</head>
<body>
<div class="container mt-5">
  <h2 class="mb-4 text-success">Track Fertilizer Order</h2>

  <form method="POST" class="mb-4">
    <div class="mb-3">
      <label for="cert_id" class="form-label">Certificate ID</label>
      <input type="text" name="cert_id" id="cert_id" class="form-control" placeholder="Enter or scan Certificate ID" required>
    </div>
    <div id="reader" style="width: 300px;"></div>
    <button type="submit" class="btn btn-success mt-3">Track Order</button>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <?php if ($order): ?>
    <div class="card shadow">
      <div class="card-header bg-success text-white">Order Details</div>
      <div class="card-body">
        <p><strong>Farmer:</strong> <?= htmlspecialchars($order['farmer_name']) ?></p>
        <p><strong>Fertilizer:</strong> <?= htmlspecialchars($order['fertilizer_name']) ?></p>
        <p><strong>Quantity:</strong> <?= htmlspecialchars($order['quantity']) ?></p>
        <p><strong>Status:</strong> <?= htmlspecialchars($order['status']) ?></p>
        <p><strong>Certificate ID:</strong> <?= htmlspecialchars($order['certificate_id']) ?></p>
        <p><strong>Order Date:</strong> <?= htmlspecialchars($order['created_at']) ?></p>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
  const html5QrCode = new Html5Qrcode("reader");
  Html5Qrcode.getCameras().then(devices => {
    if (devices.length) {
      html5QrCode.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: 250
        },
        qrCodeMessage => {
          document.getElementById("cert_id").value = qrCodeMessage;
          html5QrCode.stop(); // Optional: Stop scanning after successful scan
        },
        errorMessage => {
          // console.log(`QR error: ${errorMessage}`);
        }
      );
    }
  });
</script>
</body>
</html>
