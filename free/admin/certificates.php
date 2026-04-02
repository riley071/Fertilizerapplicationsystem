<?php
session_start();
include('../includes/db.php');
include('../includes/phpqrcode/qrlib.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$admin_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Directory setup
$projectRoot = realpath(__DIR__ . '/../');
$qrDir = $projectRoot . '/uploads/qrcodes/';
$certDir = $projectRoot . '/uploads/certificates/';
if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);
if (!is_dir($certDir)) mkdir($certDir, 0755, true);

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $app_id = (int) ($_POST['application_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $review_notes = trim($_POST['review_notes'] ?? '');
    $validity_years = (int) ($_POST['validity_years'] ?? 1);
    
    if ($app_id && in_array($action, ['approve', 'reject'])) {
        // Fetch application
        $stmt = $conn->prepare("SELECT ca.*, s.company_name, s.user_id as supplier_user_id 
                                FROM certificate_applications ca 
                                JOIN suppliers s ON ca.supplier_id = s.id 
                                WHERE ca.id = ?");
        $stmt->bind_param("i", $app_id);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($application && $application['status'] === 'Pending') {
            $conn->begin_transaction();
            
            try {
                if ($action === 'approve') {
                    // Generate certificate number: CERT-YYYY-XXXXX
                    $year = date('Y');
                    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM certificates WHERE YEAR(created_at) = ?");
                    $stmt->bind_param("i", $year);
                    $stmt->execute();
                    $count = $stmt->get_result()->fetch_assoc()['cnt'] + 1;
                    $stmt->close();
                    
                    $cert_number = sprintf("CERT-%d-%05d", $year, $count);
                    
                    // Calculate dates
                    $issued_on = date('Y-m-d');
                    $expires_on = date('Y-m-d', strtotime("+{$validity_years} years"));
                    
                    // Insert certificate
                    $stmt = $conn->prepare("INSERT INTO certificates 
                        (certificate_number, supplier_id, document_path, status, issued_on, expires_on, application_id, created_at) 
                        VALUES (?, ?, ?, 'Approved', ?, ?, ?, NOW())");
                    $stmt->bind_param("sisssi", $cert_number, $application['supplier_user_id'], 
                                      $application['document_path'], $issued_on, $expires_on, $app_id);
                    $stmt->execute();
                    $cert_id = $stmt->insert_id;
                    $stmt->close();
                    
                    // Generate QR code
                    $qrFileName = "cert_{$cert_id}.png";
                    $qrFsPath = $qrDir . $qrFileName;
                    $qrWebPath = "../uploads/qrcodes/" . $qrFileName;
                    
                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $host = $_SERVER['HTTP_HOST'];
                    $verifyUrl = "{$scheme}://{$host}/verify.php?cert={$cert_number}";
                    
                    QRcode::png($verifyUrl, $qrFsPath, QR_ECLEVEL_L, 6);
                    
                    // Update certificate with QR path
                    $stmt = $conn->prepare("UPDATE certificates SET qr_code_path = ? WHERE id = ?");
                    $stmt->bind_param("si", $qrWebPath, $cert_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    // Update application status
                    $stmt = $conn->prepare("UPDATE certificate_applications 
                                            SET status = 'Approved', reviewed_by = ?, reviewed_at = NOW(), review_notes = ? 
                                            WHERE id = ?");
                    $stmt->bind_param("isi", $admin_id, $review_notes, $app_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success = "Application approved! Certificate {$cert_number} has been issued.";
                    
                } else {
                    // Reject application
                    $stmt = $conn->prepare("UPDATE certificate_applications 
                                            SET status = 'Rejected', reviewed_by = ?, reviewed_at = NOW(), review_notes = ? 
                                            WHERE id = ?");
                    $stmt->bind_param("isi", $admin_id, $review_notes, $app_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    $success = "Application has been rejected.";
                }
                
                $conn->commit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Error processing application: " . $e->getMessage();
            }
        } else {
            $error = "Application not found or already processed.";
        }
    }
}

// Handle certificate revocation
if (isset($_POST['revoke_cert_id'])) {
    $revoke_id = (int) $_POST['revoke_cert_id'];
    $revoke_reason = trim($_POST['revoke_reason'] ?? '');
    
    $stmt = $conn->prepare("UPDATE certificates SET status = 'Revoked' WHERE id = ?");
    $stmt->bind_param("i", $revoke_id);
    if ($stmt->execute()) {
        $success = "Certificate has been revoked.";
    }
    $stmt->close();
}

// Fetch pending applications
$pending = $conn->query("
    SELECT ca.*, s.company_name, u.full_name, u.email, u.phone
    FROM certificate_applications ca
    JOIN suppliers s ON ca.supplier_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE ca.status = 'Pending'
    ORDER BY ca.submitted_at ASC
")->fetch_all(MYSQLI_ASSOC);

// Fetch all certificates
$filter = $_GET['filter'] ?? 'all';
$whereClause = match($filter) {
    'approved' => "WHERE c.status = 'Approved'",
    'expired' => "WHERE c.status = 'Approved' AND c.expires_on < CURDATE()",
    'revoked' => "WHERE c.status = 'Revoked'",
    default => ""
};

$certificates = $conn->query("
    SELECT c.*, s.company_name, u.full_name, u.email
    FROM certificates c
    LEFT JOIN suppliers s ON c.supplier_id = s.user_id
    LEFT JOIN users u ON c.supplier_id = u.id
    {$whereClause}
    ORDER BY c.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Approved' AND (expires_on IS NULL OR expires_on >= CURDATE()) THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'Approved' AND expires_on < CURDATE() THEN 1 ELSE 0 END) as expired,
        SUM(CASE WHEN status = 'Revoked' THEN 1 ELSE 0 END) as revoked
    FROM certificates
")->fetch_assoc();

$pendingCount = count($pending);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Certificates - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .stat-card { border-radius: 10px; border: none; }
        .pending-badge { 
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }
        .app-card { border-left: 4px solid #ffc107; }
        .details-json { font-size: 0.85rem; background: #f8f9fa; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <h3 class="text-success mb-4"><i class="bi bi-patch-check"></i> Certificate Management</h3>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Pending Review</div>
                                <h3 class="text-warning mb-0"><?= $pendingCount ?></h3>
                            </div>
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Active Certificates</div>
                                <h3 class="text-success mb-0"><?= $stats['active'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-check-circle text-success" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Expired</div>
                                <h3 class="text-danger mb-0"><?= $stats['expired'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-calendar-x text-danger" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <div class="text-muted small">Revoked</div>
                                <h3 class="text-secondary mb-0"><?= $stats['revoked'] ?? 0 ?></h3>
                            </div>
                            <i class="bi bi-x-circle text-secondary" style="font-size: 2rem; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Applications -->
        <?php if ($pendingCount > 0): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-bell"></i> Pending Applications 
                <span class="badge bg-dark pending-badge"><?= $pendingCount ?></span>
            </div>
            <div class="card-body">
                <?php foreach ($pending as $app): 
                    $details = $app['details'] ? json_decode($app['details'], true) : [];
                ?>
                <div class="card app-card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="mb-1"><?= htmlspecialchars($details['business_name'] ?? $app['company_name']) ?></h5>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-person"></i> <?= htmlspecialchars($app['full_name']) ?> |
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($app['email']) ?> |
                                    <i class="bi bi-telephone"></i> <?= htmlspecialchars($app['phone'] ?? 'N/A') ?>
                                </p>
                                
                                <div class="details-json">
                                    <strong>Registration:</strong> <?= htmlspecialchars($details['business_reg_no'] ?? 'N/A') ?><br>
                                    <strong>Address:</strong> <?= htmlspecialchars($details['business_address'] ?? 'N/A') ?><br>
                                    <strong>Fertilizer Types:</strong> <?= htmlspecialchars($details['fertilizer_types'] ?? 'N/A') ?>
                                </div>
                                
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> Submitted: <?= date('M d, Y H:i', strtotime($app['submitted_at'])) ?>
                                        <?php if ($app['qr_link_id']): ?>
                                            | <span class="text-success"><i class="bi bi-qr-code"></i> QR Verified</span>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <?php if ($app['document_path']): ?>
                                <a href="<?= htmlspecialchars($app['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                                    <i class="bi bi-file-earmark"></i> View Document
                                </a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4">
                                <form method="POST" class="border-start ps-3">
                                    <input type="hidden" name="application_id" value="<?= $app['id'] ?>">
                                    
                                    <div class="mb-2">
                                        <label class="form-label small">Validity Period</label>
                                        <select name="validity_years" class="form-select form-select-sm">
                                            <option value="1">1 Year</option>
                                            <option value="2" selected>2 Years</option>
                                            <option value="3">3 Years</option>
                                            <option value="5">5 Years</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label class="form-label small">Review Notes</label>
                                        <textarea name="review_notes" class="form-control form-control-sm" rows="2" placeholder="Optional notes..."></textarea>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="action" value="approve" class="btn btn-success btn-sm flex-fill">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm flex-fill"
                                                onclick="return confirm('Reject this application?')">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Certificates List -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list"></i> All Certificates</span>
                <div class="btn-group btn-group-sm">
                    <a href="?filter=all" class="btn btn-outline-secondary <?= $filter === 'all' ? 'active' : '' ?>">All</a>
                    <a href="?filter=approved" class="btn btn-outline-success <?= $filter === 'approved' ? 'active' : '' ?>">Active</a>
                    <a href="?filter=expired" class="btn btn-outline-warning <?= $filter === 'expired' ? 'active' : '' ?>">Expired</a>
                    <a href="?filter=revoked" class="btn btn-outline-danger <?= $filter === 'revoked' ? 'active' : '' ?>">Revoked</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Certificate #</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Issued</th>
                                <th>Expires</th>
                                <th>QR Code</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($certificates)): ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">No certificates found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($certificates as $cert): 
                                $isExpired = ($cert['status'] === 'Approved' && $cert['expires_on'] && $cert['expires_on'] < date('Y-m-d'));
                                $badgeClass = match(true) {
                                    $cert['status'] === 'Revoked' => 'bg-danger',
                                    $isExpired => 'bg-warning text-dark',
                                    $cert['status'] === 'Approved' => 'bg-success',
                                    default => 'bg-secondary'
                                };
                                $badgeText = $isExpired ? 'Expired' : $cert['status'];
                            ?>
                            <tr>
                                <td><code><?= htmlspecialchars($cert['certificate_number']) ?></code></td>
                                <td>
                                    <strong><?= htmlspecialchars($cert['company_name'] ?? $cert['full_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($cert['email']) ?></small>
                                </td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span></td>
                                <td><?= $cert['issued_on'] ? date('M d, Y', strtotime($cert['issued_on'])) : '-' ?></td>
                                <td><?= $cert['expires_on'] ? date('M d, Y', strtotime($cert['expires_on'])) : '-' ?></td>
                                <td>
                                    <?php if ($cert['qr_code_path']): ?>
                                        <img src="<?= htmlspecialchars($cert['qr_code_path']) ?>" width="40" height="40" class="border">
                                    <?php else: ?>-<?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="print_certificate.php?id=<?= $cert['id'] ?>" target="_blank" class="btn btn-outline-primary" title="Print">
                                            <i class="bi bi-printer"></i>
                                        </a>
                                        <?php if ($cert['status'] === 'Approved' && !$isExpired): ?>
                                        <button type="button" class="btn btn-outline-danger" title="Revoke" 
                                                onclick="if(confirm('Revoke this certificate?')) { document.getElementById('revoke-<?= $cert['id'] ?>').submit(); }">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                        <form id="revoke-<?= $cert['id'] ?>" method="POST" style="display:none;">
                                            <input type="hidden" name="revoke_cert_id" value="<?= $cert['id'] ?>">
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>