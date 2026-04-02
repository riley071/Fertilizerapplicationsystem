<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Fetch driver details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        if (empty($full_name) || empty($phone)) {
            $error = "Please fill in all required fields.";
        } else {
            // Check if email already exists for another user
            if ($email !== $driver['email']) {
                $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check->bind_param("si", $email, $user_id);
                $check->execute();
                if ($check->get_result()->num_rows > 0) {
                    $error = "Email already in use by another user.";
                }
                $check->close();
            }
            
            if (!$error) {
                $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
                $stmt->bind_param("sssi", $full_name, $phone, $email, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['full_name'] = $full_name;
                    $success = "Profile updated successfully!";
                    
                    // Refresh driver data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $driver = $stmt->get_result()->fetch_assoc();
                } else {
                    $error = "Failed to update profile.";
                }
                $stmt->close();
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = "Please fill in all password fields.";
        } elseif (!password_verify($current_password, $driver['password'])) {
            $error = "Current password is incorrect.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } elseif (strlen($new_password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Failed to change password.";
            }
            $stmt->close();
        }
    }
}

// Get delivery statistics
$stats = $conn->query("
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN status = 'Delivered' THEN 1 ELSE 0 END) as completed_deliveries,
        SUM(CASE WHEN status = 'In Transit' THEN 1 ELSE 0 END) as active_deliveries,
        SUM(CASE WHEN status = 'Delivered' AND DATE(delivered_on) = CURDATE() THEN 1 ELSE 0 END) as today_deliveries
    FROM deliveries
    WHERE driver_id = $user_id
")->fetch_assoc();

// Recent deliveries
$recent_deliveries = $conn->query("
    SELECT d.*, o.id as order_id, f.name as fertilizer_name, s.company_name as supplier_name
    FROM deliveries d
    JOIN orders o ON d.order_id = o.id
    JOIN fertilizers f ON o.fertilizer_id = f.id
    JOIN suppliers s ON d.supplier_id = s.id
    WHERE d.driver_id = $user_id
    ORDER BY d.last_updated DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Profile | Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #667eea;
            border: 4px solid rgba(255,255,255,0.3);
        }
        .stat-card {
            border-radius: 12px;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <h3 class="text-primary mb-4"><i class="bi bi-person-circle"></i> My Profile</h3>

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

        <!-- Profile Header -->
        <div class="profile-header mb-4 shadow">
            <div class="row align-items-center">
                <div class="col-auto">
                    <div class="profile-avatar">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>
                <div class="col">
                    <h3 class="mb-1"><?= htmlspecialchars($driver['full_name']) ?></h3>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-truck"></i> Driver
                        • <i class="bi bi-envelope"></i> <?= htmlspecialchars($driver['email']) ?>
                        • <i class="bi bi-phone"></i> <?= htmlspecialchars($driver['phone']) ?>
                    </p>
                </div>
                <div class="col-auto">
                    <span class="badge bg-<?= $driver['status'] === 'active' ? 'success' : 'danger' ?> fs-6">
                        <?= ucfirst($driver['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card border-primary shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Total Deliveries</h6>
                        <h3 class="text-primary"><?= $stats['total_deliveries'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-success shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Completed</h6>
                        <h3 class="text-success"><?= $stats['completed_deliveries'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-info shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Active</h6>
                        <h3 class="text-info"><?= $stats['active_deliveries'] ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card border-warning shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted">Today</h6>
                        <h3 class="text-warning"><?= $stats['today_deliveries'] ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Profile Information -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($driver['full_name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($driver['email']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($driver['phone']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Member Since</label>
                                <input type="text" class="form-control" 
                                       value="<?= date('F d, Y', strtotime($driver['created_at'])) ?>" disabled>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Change Password -->
            <div class="col-lg-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" 
                                       minlength="6" required>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="bi bi-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Deliveries</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_deliveries)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mb-0 mt-2">No recent deliveries</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_deliveries as $delivery): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= htmlspecialchars($delivery['fertilizer_name']) ?></strong>
                                            <br><small class="text-muted">
                                                To: <?= htmlspecialchars($delivery['supplier_name']) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?= 
                                            $delivery['status'] === 'Delivered' ? 'success' : 
                                            ($delivery['status'] === 'In Transit' ? 'primary' : 'warning') 
                                        ?>">
                                            <?= $delivery['status'] ?>
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>