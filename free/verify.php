<?php
include('includes/db.php');

$certificate = null;
$error = null;
$searched = false;

// Get certificate by number or ID
$cert_number = trim($_GET['cert'] ?? '');
$cert_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($cert_number || $cert_id) {
    $searched = true;
    
    if ($cert_number) {
        $stmt = $conn->prepare("
            SELECT c.*, s.company_name, s.address as company_address, s.phone as company_phone,
                   u.full_name, u.email, ca.details
            FROM certificates c
            LEFT JOIN suppliers s ON c.supplier_id = s.user_id
            LEFT JOIN users u ON c.supplier_id = u.id
            LEFT JOIN certificate_applications ca ON c.application_id = ca.id
            WHERE c.certificate_number = ?
        ");
        $stmt->bind_param("s", $cert_number);
    } else {
        $stmt = $conn->prepare("
            SELECT c.*, s.company_name, s.address as company_address, s.phone as company_phone,
                   u.full_name, u.email, ca.details
            FROM certificates c
            LEFT JOIN suppliers s ON c.supplier_id = s.user_id
            LEFT JOIN users u ON c.supplier_id = u.id
            LEFT JOIN certificate_applications ca ON c.application_id = ca.id
            WHERE c.id = ?
        ");
        $stmt->bind_param("i", $cert_id);
    }
    
    $stmt->execute();
    $certificate = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$certificate) {
        $error = "Certificate not found. Please check the certificate number and try again.";
    }
}

// Handle manual search
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = trim($_POST['certificate_number'] ?? '');
    if ($search) {
        header("Location: verify.php?cert=" . urlencode($search));
        exit();
    }
}

// Determine certificate status
$status = null;
$statusClass = '';
$statusIcon = '';
$isValid = false;

if ($certificate) {
    $today = date('Y-m-d');
    
    if ($certificate['status'] === 'Revoked') {
        $status = 'REVOKED';
        $statusClass = 'danger';
        $statusIcon = 'x-circle-fill';
    } elseif ($certificate['status'] === 'Approved' && $certificate['expires_on'] && $certificate['expires_on'] < $today) {
        $status = 'EXPIRED';
        $statusClass = 'warning';
        $statusIcon = 'exclamation-triangle-fill';
    } elseif ($certificate['status'] === 'Approved') {
        $status = 'VALID';
        $statusClass = 'success';
        $statusIcon = 'check-circle-fill';
        $isValid = true;
    } elseif ($certificate['status'] === 'Pending') {
        $status = 'PENDING';
        $statusClass = 'secondary';
        $statusIcon = 'hourglass-split';
    } else {
        $status = 'UNKNOWN';
        $statusClass = 'secondary';
        $statusIcon = 'question-circle-fill';
    }
    
    // Parse details
    $details = $certificate['details'] ? json_decode($certificate['details'], true) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Certificate - Fertilizer Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
            min-height: 100vh;
        }
        .verify-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 2.5rem;
        }
        .search-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .search-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .result-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-top: 20px;
        }
        .status-banner {
            padding: 30px;
            text-align: center;
            color: white;
        }
        .status-banner.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .status-banner.danger { background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); }
        .status-banner.warning { background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%); color: #333; }
        .status-banner.secondary { background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); }
        .status-icon { font-size: 4rem; margin-bottom: 10px; }
        .status-text { font-size: 1.5rem; font-weight: bold; letter-spacing: 3px; }
        .cert-details { padding: 25px; }
        .detail-row {
            display: flex;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            width: 140px;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .detail-value {
            flex: 1;
            font-weight: 500;
        }
        .qr-section {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
        }
        .qr-section img {
            border: 3px solid #28a745;
            border-radius: 10px;
            padding: 5px;
            background: white;
        }
        .security-notice {
            background: #e8f5e9;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px;
            border-radius: 0 8px 8px 0;
            font-size: 0.85rem;
        }
        .invalid-notice {
            background: #ffebee;
            border-left: 4px solid #dc3545;
        }
        .footer-text {
            text-align: center;
            color: #6c757d;
            font-size: 0.85rem;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <!-- Logo Section -->
        <div class="logo-section">
            <div class="logo-icon">
                <i class="bi bi-patch-check"></i>
            </div>
            <h4 class="text-success">Fertilizer Management System</h4>
            <p class="text-muted">Certificate Verification Portal</p>
        </div>

        <!-- Search Card -->
        <div class="search-card">
            <div class="search-header">
                <i class="bi bi-search"></i>
                <h5 class="mb-0 mt-2">Verify a Certificate</h5>
            </div>
            <div class="p-4">
                <form method="POST" action="verify.php">
                    <div class="input-group">
                        <span class="input-group-text bg-light">
                            <i class="bi bi-upc-scan"></i>
                        </span>
                        <input type="text" name="certificate_number" class="form-control form-control-lg" 
                               placeholder="Enter Certificate Number (e.g., CERT-2025-00001)"
                               value="<?= htmlspecialchars($cert_number) ?>" required>
                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-search"></i> Verify
                        </button>
                    </div>
                    <div class="form-text mt-2">
                        <i class="bi bi-info-circle"></i> 
                        Enter the certificate number or scan the QR code on the certificate.
                    </div>
                </form>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
        <div class="result-card">
            <div class="status-banner danger">
                <div class="status-icon"><i class="bi bi-x-circle-fill"></i></div>
                <div class="status-text">NOT FOUND</div>
            </div>
            <div class="p-4 text-center">
                <p class="text-muted mb-0"><?= htmlspecialchars($error) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Certificate Result -->
        <?php if ($certificate): ?>
        <div class="result-card">
            <!-- Status Banner -->
            <div class="status-banner <?= $statusClass ?>">
                <div class="status-icon"><i class="bi bi-<?= $statusIcon ?>"></i></div>
                <div class="status-text"><?= $status ?></div>
                <?php if ($isValid): ?>
                    <small>This certificate is authentic and currently valid.</small>
                <?php elseif ($status === 'EXPIRED'): ?>
                    <small>This certificate has expired and is no longer valid.</small>
                <?php elseif ($status === 'REVOKED'): ?>
                    <small>This certificate has been revoked by the authority.</small>
                <?php endif; ?>
            </div>

            <!-- Certificate Details -->
            <div class="cert-details">
                <div class="detail-row">
                    <div class="detail-label">Certificate No.</div>
                    <div class="detail-value">
                        <code class="text-success"><?= htmlspecialchars($certificate['certificate_number']) ?></code>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Company Name</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($details['business_name'] ?? $certificate['company_name'] ?? $certificate['full_name']) ?>
                    </div>
                </div>
                <?php if (!empty($details['business_reg_no'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Registration No.</div>
                    <div class="detail-value"><?= htmlspecialchars($details['business_reg_no']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($details['business_address'] ?? $certificate['company_address'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Address</div>
                    <div class="detail-value"><?= htmlspecialchars($details['business_address'] ?? $certificate['company_address']) ?></div>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <div class="detail-label">Date Issued</div>
                    <div class="detail-value">
                        <?= $certificate['issued_on'] ? date('F d, Y', strtotime($certificate['issued_on'])) : 'N/A' ?>
                    </div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Valid Until</div>
                    <div class="detail-value <?= ($status === 'EXPIRED') ? 'text-danger' : '' ?>">
                        <?= $certificate['expires_on'] ? date('F d, Y', strtotime($certificate['expires_on'])) : 'N/A' ?>
                        <?php if ($status === 'EXPIRED'): ?>
                            <span class="badge bg-danger ms-2">Expired</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- QR Code -->
            <?php if ($certificate['qr_code_path'] && $isValid): ?>
            <div class="qr-section">
                <img src="<?= htmlspecialchars($certificate['qr_code_path']) ?>" width="100" height="100" alt="QR Code">
                <div class="text-muted small mt-2">Verification QR Code</div>
            </div>
            <?php endif; ?>

            <!-- Security Notice -->
            <?php if ($isValid): ?>
            <div class="security-notice">
                <i class="bi bi-shield-check text-success"></i>
                <strong>Verified:</strong> This supplier is authorized to trade fertilizers under the Fertilizer Act.
                Always ensure you purchase from certified suppliers only.
            </div>
            <?php else: ?>
            <div class="security-notice invalid-notice">
                <i class="bi bi-exclamation-triangle text-danger"></i>
                <strong>Warning:</strong> This certificate is not currently valid. 
                Do not conduct business based on this certificate. Contact the issuing authority for clarification.
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="footer-text">
            <p>
                <i class="bi bi-building"></i> Ministry of Agriculture<br>
                Republic of Malawi
            </p>
            <p class="small">
                For inquiries, contact: <a href="mailto:certificates@agriculture.gov.mw">certificates@agriculture.gov.mw</a>
            </p>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>