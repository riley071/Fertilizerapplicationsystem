<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if (!isset($_GET['id'])) {
    die("Certificate ID required");
}

$cert_id = (int)$_GET['id'];

$sql = "SELECT c.*, u.full_name, u.email FROM certificates c JOIN users u ON c.supplier_id = u.id WHERE c.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $cert_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("Certificate not found");
}

$cert = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Certificate #<?= $cert['id'] ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .certificate {
            border: 10px solid #ccc;
            padding: 50px;
            width: 700px;
            margin: auto;
            text-align: center;
        }
        .certificate h1 {
            font-size: 48px;
            margin-bottom: 0;
        }
        .certificate h2 {
            margin-top: 0;
            font-weight: normal;
        }
        .details {
            margin-top: 40px;
            font-size: 18px;
        }
        .qr-code {
            margin-top: 40px;
        }
        @media print {
            body {
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="certificate">
        <h1>Fertilizer Supplier Certificate</h1>
        <h2>Certificate ID: <?= htmlspecialchars($cert['id']) ?></h2>
        <p>This certifies that</p>
        <h2><?= htmlspecialchars($cert['full_name']) ?></h2>
        <p class="details">
            Document: <a href="../<?= htmlspecialchars($cert['document_path']) ?>" target="_blank">View Document</a><br>
            Status: <?= htmlspecialchars($cert['status']) ?><br>
            Issued On: <?= htmlspecialchars($cert['issued_on'] ?? '-') ?><br>
            Expires On: <?= htmlspecialchars($cert['expires_on'] ?? '-') ?>
        </p>
        <?php if (!empty($cert['qr_code_path'])): ?>
            <div class="qr-code">
                <img src="../<?= htmlspecialchars($cert['qr_code_path']) ?>" alt="QR Code" width="150">
                <p>Scan to verify</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</body>
</html>
