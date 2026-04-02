<?php
session_start();
include('../includes/db.php');

$cert_id = '';
$certificate = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cert_id = trim($_POST['cert_id']);
    if ($cert_id === '') {
        $message = "<span class='text-warning'>Please enter or scan a certificate ID.</span>";
    } else {
        // Lookup certificate by ID or QR code path
        $stmt = $conn->prepare("
    SELECT c.*, u.full_name AS supplier_name 
    FROM certificates c 
    JOIN users u ON c.supplier_id = u.id 
    WHERE c.certificate_number = ?
    LIMIT 1
");
$stmt->bind_param('s', $cert_id);  // $cert_id holds the certificate number

        $stmt->execute();
        $result = $stmt->get_result();
        $certificate = $result->fetch_assoc();
        $stmt->close();

        if (!$certificate) {
            $message = "<span class='text-danger'>❌ Certificate not found.</span>";
        } else {
            $message = "<span class='text-success'>✅ Certificate is valid. Status: " . htmlspecialchars($certificate['status']) . "</span>";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Verify Certificate</title>
<style>
    body { background-color: #f7f9f6; }
    .container {
        max-width: 600px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
    .card-header {
        background-color: #e9ecef;
        font-weight: bold;
    }
    .text-success, .text-warning, .text-danger, .text-secondary {
        font-weight: bold;
    }
</style>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>
<body class="bg-light">
<div class="container py-5 my-5">
    <h1 class="mb-4">Certificate Verification</h1>

    <?= $message ?>

    <!-- QR Scanner -->
    <div id="qr-reader" style="width:300px;"></div>

    <!-- Manual input -->
    <form method="POST" class="mt-3">
        <label for="cert_id">Or enter Certificate ID manually:</label>
        <input type="text" id="cert_id" name="cert_id" class="form-control" value="<?= htmlspecialchars($cert_id) ?>" required>
        <button type="submit" class="btn btn-primary mt-2">Verify</button>
    </form>

    <?php if ($certificate): ?>
        <div class="card mt-4">
            <div class="card-header">
                Certificate Details (ID: <?= htmlspecialchars($certificate['id']) ?>)
            </div>
            <div class="card-body">
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
    // Fill the input and submit form automatically
    document.getElementById('cert_id').value = decodedText;
    document.forms[0].submit();
}

function onScanFailure(error) {
    // optional: console.warn(`QR scan failed: ${error}`);
}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "qr-reader",
    { fps: 10, qrbox: 250 }
);
html5QrcodeScanner.render(onScanSuccess, onScanFailure);
</script>
</body>
</html>
