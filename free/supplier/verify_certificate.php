<?php
session_start();
include('../includes/db.php');

$cert_id = '';
$certificate = null;
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cert_id = trim($_POST['cert_id']);
    if ($cert_id === '') {
        $message = "Please enter a certificate ID or scan a QR code.";
    } else {
        // Lookup certificate by certificate_number
        $stmt = $conn->prepare("
            SELECT c.*, u.full_name AS supplier_name 
            FROM certificates c 
            JOIN users u ON c.supplier_id = u.id 
            WHERE c.certificate_number = ? OR c.id = ?
            LIMIT 1
        ");
        $stmt->bind_param('si', $cert_id, $cert_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $certificate = $result->fetch_assoc();
        $stmt->close();

        if (!$certificate) {
            $message = "Certificate not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Verify Certificate</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body class="bg-light">
<div class="container py-5">
    <h1 class="mb-4">Certificate Verification</h1>

    <!-- QR Scanner -->
    <div class="mb-4">
        <h5>Scan QR Code:</h5>
        <div id="qr-reader" style="width:300px;"></div>
    </div>

    <!-- Manual entry -->
    <form method="POST" class="mb-4">
        <label for="cert_id" class="form-label">Or Enter Certificate Number</label>
        <input type="text" name="cert_id" id="cert_id" class="form-control" value="<?= htmlspecialchars($cert_id) ?>" required />
        <button type="submit" class="btn btn-primary mt-3">Verify</button>
    </form>

    <?php if ($message): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($certificate): ?>
        <div class="card">
            <div class="card-header">
                Certificate Details (ID: <?= htmlspecialchars($certificate['id']) ?>)
            </div>
            <div class="card-body">
                <p><strong>Certificate Number:</strong> <?= htmlspecialchars($certificate['certificate_number']) ?></p>
                <p><strong>Supplier:</strong> <?= htmlspecialchars($certificate['supplier_name']) ?></p>
                <p><strong>Status:</strong> 
                    <?php
                    $status_class = match ($certificate['status']) {
                        'Approved' => 'text-success',
                        'Pending' => 'text-warning',
                        'Revoked' => 'text-danger',
                        'Expired' => 'text-secondary',
                        default => '',
                    };
                    ?>
                    <span class="<?= $status_class ?>"><?= htmlspecialchars($certificate['status']) ?></span>
                </p>
                <p><strong>Issued On:</strong> <?= $certificate['issued_on'] ?? '-' ?></p>
                <p><strong>Expires On:</strong> <?= $certificate['expires_on'] ?? '-' ?></p>
                <p><strong>Document:</strong> 
                    <?php if (!empty($certificate['document_path'])): ?>
                        <a href="../<?= htmlspecialchars($certificate['document_path']) ?>" target="_blank">View Document</a>
                    <?php else: ?>
                        N/A
                    <?php endif; ?>
                </p>
                <?php if (!empty($certificate['qr_code_path'])): ?>
                    <p><strong>QR Code:</strong><br />
                        <img src="../<?= htmlspecialchars($certificate['qr_code_path']) ?>" alt="QR Code" width="120" />
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function onScanSuccess(decodedText, decodedResult) {
    // Fill the input and submit form
    document.getElementById('cert_id').value = decodedText;
    document.forms[0].submit();
}

function onScanFailure(error) {
    // Optional: console.warn(`QR scan failed: ${error}`);
}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "qr-reader",
    { fps: 10, qrbox: 250 }
);
html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>
</body>
</html>
