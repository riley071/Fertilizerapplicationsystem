<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Get supplier_id
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    $_SESSION['error'] = "Please complete your supplier profile first.";
    header('Location: profile.php');
    exit();
}

$supplier_id = (int) $supplier['id'];

// Fetch all certificates for this supplier
$stmt = $conn->prepare("
    SELECT c.*, ca.details, ca.submitted_at as application_date
    FROM certificates c
    LEFT JOIN certificate_applications ca ON c.application_id = ca.id
    WHERE c.supplier_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$certificates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate statistics
$stats = ['total' => 0, 'approved' => 0, 'pending' => 0, 'expired' => 0, 'revoked' => 0];
$today = date('Y-m-d');

foreach ($certificates as $cert) {
    $stats['total']++;
    $status = $cert['status'];
    
    // Check if expired
    if ($status === 'Approved' && $cert['expires_on'] && $cert['expires_on'] < $today) {
        $stats['expired']++;
    } elseif ($status === 'Approved') {
        $stats['approved']++;
    } elseif ($status === 'Pending') {
        $stats['pending']++;
    } elseif ($status === 'Revoked') {
        $stats['revoked']++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Certificates</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .stat-card { border-left: 4px solid; border-radius: 8px; }
        .stat-approved { border-color: #28a745; }
        .stat-pending { border-color: #ffc107; }
        .stat-expired { border-color: #dc3545; }
        .stat-total { border-color: #0d6efd; }
        .cert-card { transition: transform 0.2s, box-shadow 0.2s; }
        .cert-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .qr-code { 
            background: white; 
            padding: 10px; 
            border-radius: 8px; 
            border: 2px dashed #dee2e6;
            display: inline-block;
        }
        .status-badge { font-size: 0.75rem; padding: 5px 10px; }
        .cert-number { font-family: monospace; font-size: 1.1rem; letter-spacing: 1px; }
        .days-remaining { font-size: 0.85rem; }
        .expired-overlay {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 8px;
            pointer-events: none;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-patch-check"></i> My Certificates</h3>
            <a href="apply_certificate.php" class="btn btn-success">
                <i class="bi bi-plus-lg"></i> Apply for New Certificate
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card stat-total border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Total Certificates</div>
                                <h3 class="mb-0"><?= $stats['total'] ?></h3>
                            </div>
                            <div class="text-primary opacity-50">
                                <i class="bi bi-file-earmark-text" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card stat-approved border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Active</div>
                                <h3 class="mb-0 text-success"><?= $stats['approved'] ?></h3>
                            </div>
                            <div class="text-success opacity-50">
                                <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card stat-pending border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Pending</div>
                                <h3 class="mb-0 text-warning"><?= $stats['pending'] ?></h3>
                            </div>
                            <div class="text-warning opacity-50">
                                <i class="bi bi-hourglass-split" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card stat-expired border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Expired/Revoked</div>
                                <h3 class="mb-0 text-danger"><?= $stats['expired'] + $stats['revoked'] ?></h3>
                            </div>
                            <div class="text-danger opacity-50">
                                <i class="bi bi-x-circle" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificates List -->
        <?php if (empty($certificates)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-file-earmark-x text-muted" style="font-size: 4rem;"></i>
                    <h5 class="mt-3">No Certificates Yet</h5>
                    <p class="text-muted">You haven't received any certificates. Apply for one to get started.</p>
                    <a href="apply_certificate.php" class="btn btn-success">
                        <i class="bi bi-plus-lg"></i> Apply for Certificate
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($certificates as $cert): 
                    $status = $cert['status'];
                    $isExpired = ($status === 'Approved' && $cert['expires_on'] && $cert['expires_on'] < $today);
                    
                    // Calculate days remaining
                    $daysRemaining = null;
                    if ($status === 'Approved' && $cert['expires_on']) {
                        $daysRemaining = (strtotime($cert['expires_on']) - strtotime($today)) / 86400;
                    }
                    
                    // Determine badge
                    if ($isExpired) {
                        $badgeClass = 'bg-danger';
                        $badgeText = 'Expired';
                    } elseif ($status === 'Approved') {
                        $badgeClass = 'bg-success';
                        $badgeText = 'Active';
                    } elseif ($status === 'Pending') {
                        $badgeClass = 'bg-warning text-dark';
                        $badgeText = 'Pending';
                    } elseif ($status === 'Revoked') {
                        $badgeClass = 'bg-danger';
                        $badgeText = 'Revoked';
                    } else {
                        $badgeClass = 'bg-secondary';
                        $badgeText = $status;
                    }
                    
                    // Parse details JSON
                    $details = $cert['details'] ? json_decode($cert['details'], true) : [];
                ?>
                <div class="col-lg-6">
                    <div class="card cert-card border-0 shadow-sm position-relative">
                        <?php if ($isExpired || $status === 'Revoked'): ?>
                            <div class="expired-overlay"></div>
                        <?php endif; ?>
                        
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge <?= $badgeClass ?> status-badge"><?= $badgeText ?></span>
                                <?php if ($daysRemaining !== null && $daysRemaining > 0 && $daysRemaining <= 30): ?>
                                    <span class="badge bg-warning text-dark status-badge ms-1">
                                        <i class="bi bi-exclamation-triangle"></i> Expires Soon
                                    </span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted">ID: <?= $cert['id'] ?></small>
                        </div>
                        
                        <div class="card-body">
                            <div class="row">
                                <div class="col-8">
                                    <!-- Certificate Number -->
                                    <div class="mb-3">
                                        <label class="text-muted small">Certificate Number</label>
                                        <div class="cert-number text-success fw-bold">
                                            <?= htmlspecialchars($cert['certificate_number'] ?: 'Pending Assignment') ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Company Name -->
                                    <div class="mb-3">
                                        <label class="text-muted small">Company</label>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($details['business_name'] ?? $supplier['company_name'] ?? 'N/A') ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Dates -->
                                    <div class="row">
                                        <div class="col-6">
                                            <label class="text-muted small">Issued On</label>
                                            <div><?= $cert['issued_on'] ? date('M d, Y', strtotime($cert['issued_on'])) : '-' ?></div>
                                        </div>
                                        <div class="col-6">
                                            <label class="text-muted small">Expires On</label>
                                            <div class="<?= ($daysRemaining !== null && $daysRemaining <= 30) ? 'text-danger fw-bold' : '' ?>">
                                                <?= $cert['expires_on'] ? date('M d, Y', strtotime($cert['expires_on'])) : '-' ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($daysRemaining !== null && $daysRemaining > 0): ?>
                                        <div class="days-remaining mt-2">
                                            <i class="bi bi-clock"></i>
                                            <?php if ($daysRemaining > 30): ?>
                                                <span class="text-success"><?= floor($daysRemaining) ?> days remaining</span>
                                            <?php else: ?>
                                                <span class="text-danger fw-bold"><?= floor($daysRemaining) ?> days remaining</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- QR Code -->
                                <div class="col-4 text-center">
                                    <?php if ($cert['qr_code_path'] && $status === 'Approved' && !$isExpired): ?>
                                        <div class="qr-code">
                                            <img src="<?= htmlspecialchars($cert['qr_code_path']) ?>" 
                                                 alt="QR Code" width="100" height="100">
                                        </div>
                                        <small class="text-muted d-block mt-1">Scan to Verify</small>
                                    <?php elseif ($status === 'Pending'): ?>
                                        <div class="qr-code bg-light">
                                            <i class="bi bi-qr-code text-muted" style="font-size: 60px;"></i>
                                        </div>
                                        <small class="text-muted d-block mt-1">Awaiting Approval</small>
                                    <?php else: ?>
                                        <div class="qr-code bg-light">
                                            <i class="bi bi-x-lg text-danger" style="font-size: 60px;"></i>
                                        </div>
                                        <small class="text-muted d-block mt-1">Invalid</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-white border-top">
                            <div class="d-flex gap-2">
                                <?php if ($cert['document_path']): ?>
                                    <a href="<?= htmlspecialchars($cert['document_path']) ?>" 
                                       class="btn btn-sm btn-outline-secondary" target="_blank">
                                        <i class="bi bi-file-earmark"></i> Document
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($status === 'Approved' && !$isExpired): ?>
                                    <a href="print_certificate.php?id=<?= $cert['id'] ?>" 
                                       class="btn btn-sm btn-outline-success" target="_blank">
                                        <i class="bi bi-printer"></i> Print
                                    </a>
                                    <a href="download_certificate.php?id=<?= $cert['id'] ?>" 
                                       class="btn btn-sm btn-success">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                <?php elseif ($isExpired): ?>
                                    <a href="apply_certificate.php" class="btn btn-sm btn-warning">
                                        <i class="bi bi-arrow-repeat"></i> Renew
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Info Box -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h6><i class="bi bi-info-circle text-primary"></i> Certificate Information</h6>
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-check-circle text-success me-2 mt-1"></i>
                            <div>
                                <strong>Active Certificate</strong>
                                <p class="text-muted small mb-0">Your certificate is valid and can be used for fertilizer trading.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-exclamation-triangle text-warning me-2 mt-1"></i>
                            <div>
                                <strong>Expiring Soon</strong>
                                <p class="text-muted small mb-0">Certificates expiring within 30 days. Apply for renewal.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-start">
                            <i class="bi bi-qr-code-scan text-primary me-2 mt-1"></i>
                            <div>
                                <strong>QR Verification</strong>
                                <p class="text-muted small mb-0">Customers can scan the QR code to verify your certificate.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>