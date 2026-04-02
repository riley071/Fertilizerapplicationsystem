<?php
session_start();
include('../includes/db.php');

// Auth check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Get supplier_id from suppliers table
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$supplier) {
    $error = "Supplier profile not found. Please complete your profile first.";
    $supplier_id = null;
} else {
    $supplier_id = (int) $supplier['id'];
}

// Check if supplier came via QR code link
$qr_link_id = null;
$qr_valid = false;

if (isset($_GET['qr']) && !empty($_GET['qr'])) {
    $qr_code = $_GET['qr'];
    
    // Validate QR code from qr_links table
    $stmt = $conn->prepare("SELECT id, code, expires_at, active FROM qr_links WHERE code = ? AND purpose = 'certificate_application'");
    $stmt->bind_param("s", $qr_code);
    $stmt->execute();
    $qr_link = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($qr_link) {
        if (!$qr_link['active']) {
            $error = "This QR code is no longer active.";
        } elseif ($qr_link['expires_at'] && strtotime($qr_link['expires_at']) < time()) {
            $error = "This QR code has expired.";
        } else {
            $qr_valid = true;
            $qr_link_id = (int) $qr_link['id'];
        }
    } else {
        $error = "Invalid QR code.";
    }
}

// File upload directory
$projectRoot = realpath(__DIR__ . '/../');
$uploadDir = $projectRoot . '/uploads/applications/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supplier_id) {
    $business_name = trim($_POST['business_name'] ?? '');
    $business_reg_no = trim($_POST['business_reg_no'] ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $fertilizer_types = trim($_POST['fertilizer_types'] ?? '');
    
    // FIX: Properly handle qr_link_id - only use it if it's valid and not empty
    $qr_link_id_post = null;
    if (isset($_POST['qr_link_id']) && !empty($_POST['qr_link_id']) && (int)$_POST['qr_link_id'] > 0) {
        $qr_link_id_post = (int)$_POST['qr_link_id'];
    }
    
    // Validate required fields
    if (empty($business_name) || empty($business_reg_no) || empty($business_address)) {
        $error = "Please fill in all required fields.";
    } elseif (!isset($_FILES['document']) || $_FILES['document']['error'] !== 0) {
        $error = "Please upload a supporting document.";
    } else {
        // Validate file
        $allowed_types = ['application/pdf', 'image/png', 'image/jpeg'];
        $file_mime = mime_content_type($_FILES['document']['tmp_name']);
        
        if (!in_array($file_mime, $allowed_types)) {
            $error = "Only PDF, PNG, and JPG files are allowed.";
        } elseif ($_FILES['document']['size'] > 5 * 1024 * 1024) {
            $error = "File too large. Maximum 5MB allowed.";
        } else {
            // Save file
            $ext = pathinfo($_FILES['document']['name'], PATHINFO_EXTENSION);
            $newFileName = 'app_' . $supplier_id . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFileName;
            $webPath = '../uploads/applications/' . $newFileName;
            
            if (move_uploaded_file($_FILES['document']['tmp_name'], $targetPath)) {
                // Prepare JSON details
                $details = json_encode([
                    'business_name' => $business_name,
                    'business_reg_no' => $business_reg_no,
                    'business_address' => $business_address,
                    'contact_phone' => $contact_phone,
                    'fertilizer_types' => $fertilizer_types
                ]);
                
                // Insert into certificate_applications table with proper NULL handling
                $stmt = $conn->prepare("INSERT INTO certificate_applications (supplier_id, qr_link_id, document_path, details, status, submitted_at) VALUES (?, ?, ?, ?, 'Pending', NOW())");
                $stmt->bind_param("iiss", $supplier_id, $qr_link_id_post, $webPath, $details);
                
                if ($stmt->execute()) {
                    $success = "Your certificate application has been submitted successfully! You will be notified once it's reviewed.";
                } else {
                    $error = "Failed to submit application: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Failed to upload document.";
            }
        }
    }
}

// Fetch existing applications
$applications = [];
if ($supplier_id) {
    $stmt = $conn->prepare("SELECT ca.*, u.full_name as reviewer_name 
                            FROM certificate_applications ca 
                            LEFT JOIN users u ON ca.reviewed_by = u.id 
                            WHERE ca.supplier_id = ? 
                            ORDER BY ca.submitted_at DESC");
    $stmt->bind_param("i", $supplier_id);
    $stmt->execute();
    $applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Apply for Certificate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .qr-notice { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-left: 4px solid #28a745; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-success mb-4"><i class="bi bi-patch-check"></i> Apply for Certificate</h3>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- QR Code Notice -->
        <?php if ($qr_valid): ?>
            <div class="alert qr-notice">
                <i class="bi bi-qr-code-scan"></i> <strong>QR Code Verified!</strong> You can now submit your certificate application.
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> <strong>Tip:</strong> Scan the official QR code provided by the Fertilizer Authority for faster processing. 
                You can still apply without a QR code, but processing may take longer.
            </div>
        <?php endif; ?>

        <!-- Application Form -->
        <?php if ($supplier_id): ?>
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <i class="bi bi-file-earmark-text"></i> Certificate Application Form
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="qr_link_id" value="<?= $qr_link_id ?? '' ?>">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Business/Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="business_name" class="form-control" 
                                   value="<?= htmlspecialchars($supplier['company_name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Business Registration Number <span class="text-danger">*</span></label>
                            <input type="text" name="business_reg_no" class="form-control" 
                                   placeholder="e.g., BRN-12345" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Business Address <span class="text-danger">*</span></label>
                            <textarea name="business_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Phone</label>
                            <input type="text" name="contact_phone" class="form-control" placeholder="+265...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Types of Fertilizers Supplied</label>
                            <input type="text" name="fertilizer_types" class="form-control" 
                                   placeholder="e.g., NPK, Urea, DAP">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Supporting Document <span class="text-danger">*</span></label>
                            <input type="file" name="document" class="form-control" accept=".pdf,.png,.jpg,.jpeg" required>
                            <div class="form-text">Upload business license, tax certificate, or fertilizer quality report (PDF, PNG, JPG - Max 5MB)</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-send"></i> Submit Application
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Previous Applications -->
        <h5 class="mt-4"><i class="bi bi-clock-history"></i> Your Applications</h5>
        <div class="table-responsive">
            <table class="table table-bordered bg-white">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>QR Verified</th>
                        <th>Reviewed By</th>
                        <th>Review Notes</th>
                        <th>Document</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No applications submitted yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): 
                        $badge = match($app['status']) {
                            'Approved' => 'success',
                            'Rejected' => 'danger',
                            default => 'warning'
                        };
                    ?>
                    <tr>
                        <td><?= $app['id'] ?></td>
                        <td><?= date('M d, Y H:i', strtotime($app['submitted_at'])) ?></td>
                        <td><span class="badge bg-<?= $badge ?>"><?= $app['status'] ?></span></td>
                        <td><?= $app['qr_link_id'] ? '<i class="bi bi-check-circle text-success"></i> Yes' : '<i class="bi bi-x-circle text-muted"></i> No' ?></td>
                        <td><?= $app['reviewer_name'] ?? '-' ?></td>
                        <td><?= htmlspecialchars($app['review_notes'] ?? '-') ?></td>
                        <td>
                            <?php if ($app['document_path']): ?>
                                <a href="<?= htmlspecialchars($app['document_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-file-earmark"></i> View
                                </a>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>