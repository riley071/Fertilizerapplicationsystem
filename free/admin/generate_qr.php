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
$qrDir = $projectRoot . '/uploads/qr_links/';
if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

// Handle QR code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $purpose = $_POST['purpose'] ?? 'certificate_application';
    $expires_days = (int) ($_POST['expires_days'] ?? 30);
    $quantity = min((int) ($_POST['quantity'] ?? 1), 20); // Max 20 at a time
    
    $generated = [];
    
    for ($i = 0; $i < $quantity; $i++) {
        // Generate unique code
        $code = bin2hex(random_bytes(16)); // 32 character hex string
        
        // Calculate expiry
        $expires_at = $expires_days > 0 ? date('Y-m-d H:i:s', strtotime("+{$expires_days} days")) : null;
        
        // Build target URL
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['REQUEST_URI']); // Remove /admin from path
        $basePath = str_replace('/admin', '', $basePath);
        $targetUrl = "{$scheme}://{$host}{$basePath}/supplier/apply_certificate.php?qr={$code}";
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO qr_links (code, target_url, purpose, created_by, expires_at, active) VALUES (?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("sssis", $code, $targetUrl, $purpose, $admin_id, $expires_at);
        
        if ($stmt->execute()) {
            $qr_id = $stmt->insert_id;
            
            // Generate QR image
            $qrFileName = "qrlink_{$qr_id}.png";
            $qrFsPath = $qrDir . $qrFileName;
            $qrWebPath = "../uploads/qr_links/" . $qrFileName;
            
            QRcode::png($targetUrl, $qrFsPath, QR_ECLEVEL_M, 8);
            
            $generated[] = [
                'id' => $qr_id,
                'code' => $code,
                'url' => $targetUrl,
                'qr_path' => $qrWebPath,
                'expires_at' => $expires_at
            ];
        }
        $stmt->close();
    }
    
    if (count($generated) > 0) {
        $success = count($generated) . " QR code(s) generated successfully!";
        $_SESSION['generated_qrs'] = $generated;
    } else {
        $error = "Failed to generate QR codes.";
    }
}

// Handle deactivation
if (isset($_POST['deactivate_id'])) {
    $deactivate_id = (int) $_POST['deactivate_id'];
    $stmt = $conn->prepare("UPDATE qr_links SET active = 0 WHERE id = ?");
    $stmt->bind_param("i", $deactivate_id);
    if ($stmt->execute()) {
        $success = "QR code deactivated successfully.";
    }
    $stmt->close();
}

// Handle activation
if (isset($_POST['activate_id'])) {
    $activate_id = (int) $_POST['activate_id'];
    $stmt = $conn->prepare("UPDATE qr_links SET active = 1 WHERE id = ?");
    $stmt->bind_param("i", $activate_id);
    if ($stmt->execute()) {
        $success = "QR code activated successfully.";
    }
    $stmt->close();
}

// Handle deletion
if (isset($_POST['delete_id'])) {
    $delete_id = (int) $_POST['delete_id'];
    // Check if used
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM certificate_applications WHERE qr_link_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $used = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    
    if ($used > 0) {
        $error = "Cannot delete QR code that has been used for applications.";
    } else {
        $stmt = $conn->prepare("DELETE FROM qr_links WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $success = "QR code deleted successfully.";
        }
        $stmt->close();
    }
}

// Fetch recently generated QRs from session
$generatedQrs = $_SESSION['generated_qrs'] ?? [];
unset($_SESSION['generated_qrs']);

// Fetch all QR links with usage stats
$qrLinks = $conn->query("
    SELECT q.*, u.full_name as created_by_name,
           (SELECT COUNT(*) FROM certificate_applications ca WHERE ca.qr_link_id = q.id) as usage_count
    FROM qr_links q
    LEFT JOIN users u ON q.created_by = u.id
    ORDER BY q.created_at DESC
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN active = 1 AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 ELSE 0 END) as expired
    FROM qr_links
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generate QR Codes - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .qr-preview { 
            border: 2px dashed #dee2e6; 
            border-radius: 10px; 
            padding: 15px; 
            text-align: center;
            background: white;
        }
        .qr-preview img { max-width: 150px; }
        .generated-card {
            border: 2px solid #28a745;
            animation: highlight 2s ease-out;
        }
        @keyframes highlight {
            0% { background-color: #d4edda; }
            100% { background-color: white; }
        }
        .code-text {
            font-family: monospace;
            font-size: 0.75rem;
            word-break: break-all;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-qr-code"></i> QR Code Management</h3>
            <a href="scan_qr.php" class="btn btn-success">
                <i class="bi bi-upc-scan"></i> Scan & Verify QR
            </a>
        </div>

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
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-primary mb-0"><?= $stats['total'] ?? 0 ?></h3>
                        <small class="text-muted">Total QR Codes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-success mb-0"><?= $stats['active'] ?? 0 ?></h3>
                        <small class="text-muted">Active</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-warning mb-0"><?= $stats['expired'] ?? 0 ?></h3>
                        <small class="text-muted">Expired</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-body text-center">
                        <h3 class="text-secondary mb-0"><?= $stats['inactive'] ?? 0 ?></h3>
                        <small class="text-muted">Inactive</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Generate Form -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-plus-circle"></i> Generate New QR Codes
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Purpose</label>
                                <select name="purpose" class="form-select">
                                    <option value="certificate_application">Certificate Application</option>
                                    <option value="info">Information Only</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Validity Period</label>
                                <select name="expires_days" class="form-select">
                                    <option value="7">7 Days</option>
                                    <option value="14">14 Days</option>
                                    <option value="30" selected>30 Days</option>
                                    <option value="60">60 Days</option>
                                    <option value="90">90 Days</option>
                                    <option value="365">1 Year</option>
                                    <option value="0">No Expiry</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" name="quantity" class="form-control" value="1" min="1" max="20">
                                <div class="form-text">Generate up to 20 QR codes at once</div>
                            </div>
                            <button type="submit" name="generate" class="btn btn-success w-100">
                                <i class="bi bi-qr-code"></i> Generate QR Codes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body">
                        <h6><i class="bi bi-info-circle text-primary"></i> How It Works</h6>
                        <ol class="small text-muted ps-3 mb-0">
                            <li class="mb-2">Generate QR codes using the form above</li>
                            <li class="mb-2">Print and distribute to suppliers</li>
                            <li class="mb-2">Suppliers scan QR to access application form</li>
                            <li class="mb-2">Applications are tagged with the QR code used</li>
                            <li>QR-verified applications can be prioritized</li>
                        </ol>
                    </div>
                </div>
            </div>

            <!-- Generated QRs (if any) -->
            <?php if (!empty($generatedQrs)): ?>
            <div class="col-lg-8 mb-4">
                <div class="card border-0 shadow-sm generated-card">
                    <div class="card-header bg-success text-white">
                        <i class="bi bi-check-circle"></i> Newly Generated QR Codes
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($generatedQrs as $qr): ?>
                            <div class="col-md-4">
                                <div class="qr-preview">
                                    <img src="<?= htmlspecialchars($qr['qr_path']) ?>" alt="QR Code">
                                    <div class="mt-2">
                                        <small class="text-muted">ID: <?= $qr['id'] ?></small><br>
                                        <small class="text-muted">
                                            Expires: <?= $qr['expires_at'] ? date('M d, Y', strtotime($qr['expires_at'])) : 'Never' ?>
                                        </small>
                                    </div>
                                    <a href="<?= htmlspecialchars($qr['qr_path']) ?>" download class="btn btn-sm btn-outline-success mt-2">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 text-center">
                            <button onclick="window.print()" class="btn btn-outline-primary">
                                <i class="bi bi-printer"></i> Print All QR Codes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- QR Links List -->
            <div class="col-lg-<?= empty($generatedQrs) ? '8' : '12' ?>">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <i class="bi bi-list"></i> All QR Codes
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>QR Code</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Usage</th>
                                        <th>Expires</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($qrLinks)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No QR codes generated yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($qrLinks as $qr): 
                                        $isExpired = $qr['expires_at'] && strtotime($qr['expires_at']) < time();
                                        $isActive = $qr['active'] && !$isExpired;
                                        
                                        $qrImagePath = "../uploads/qr_links/qrlink_{$qr['id']}.png";
                                    ?>
                                    <tr>
                                        <td>
                                            <?php if (file_exists($qrImagePath)): ?>
                                                <img src="<?= $qrImagePath ?>" width="50" height="50" class="border rounded">
                                            <?php else: ?>
                                                <span class="text-muted">No image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $qr['purpose'] === 'certificate_application' ? 'primary' : 'secondary' ?>">
                                                <?= ucfirst(str_replace('_', ' ', $qr['purpose'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!$qr['active']): ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php elseif ($isExpired): ?>
                                                <span class="badge bg-warning text-dark">Expired</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $qr['usage_count'] ?> uses</span>
                                        </td>
                                        <td>
                                            <?= $qr['expires_at'] ? date('M d, Y', strtotime($qr['expires_at'])) : '<span class="text-muted">Never</span>' ?>
                                        </td>
                                        <td>
                                            <?= date('M d, Y', strtotime($qr['created_at'])) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($qr['created_by_name'] ?? 'System') ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if (file_exists($qrImagePath)): ?>
                                                <a href="<?= $qrImagePath ?>" download class="btn btn-outline-primary" title="Download">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($qr['active']): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="deactivate_id" value="<?= $qr['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-warning" title="Deactivate" 
                                                            onclick="return confirm('Deactivate this QR code?')">
                                                        <i class="bi bi-pause"></i>
                                                    </button>
                                                </form>
                                                <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="activate_id" value="<?= $qr['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-success" title="Activate">
                                                        <i class="bi bi-play"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($qr['usage_count'] == 0): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="delete_id" value="<?= $qr['id'] ?>">
                                                    <button type="submit" class="btn btn-outline-danger" title="Delete"
                                                            onclick="return confirm('Delete this QR code?')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
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
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>