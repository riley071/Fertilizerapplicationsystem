<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Fetch user data
$stmt = $conn->prepare("SELECT full_name, email, phone, status, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch admin statistics
$stats = [];

// Total fertilizers managed
$result = $conn->query("SELECT COUNT(*) as total FROM fertilizers WHERE admin_id = $user_id");
$stats['fertilizers'] = $result->fetch_assoc()['total'];

// Total stock value
$result = $conn->query("SELECT SUM(stock_remaining * price_per_unit) as total_value FROM fertilizers WHERE admin_id = $user_id");
$stats['stock_value'] = $result->fetch_assoc()['total_value'] ?? 0;

// Recent activities from logs
$stmt = $conn->prepare("SELECT action, timestamp FROM logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['full_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Validation
    if (empty($new_name) || empty($new_email)) {
        $error = "Name and Email are required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!empty($new_password) && strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists for another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $new_email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "Email is already in use by another account.";
        }
        $stmt->close();
    }

    if (empty($error)) {
        try {
            // Update users table
            if (!empty($new_password)) {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, password=? WHERE id=?");
                $stmt->bind_param("ssssi", $new_name, $new_email, $new_phone, $hashed, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=? WHERE id=?");
                $stmt->bind_param("sssi", $new_name, $new_email, $new_phone, $user_id);
            }
            $stmt->execute();
            $stmt->close();

            // Log the profile update
            $stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, 'Profile updated', ?)");
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt->bind_param("is", $user_id, $ip);
            $stmt->execute();
            $stmt->close();

            $success = "Profile updated successfully!";
            
            // Refresh user data
            $user['full_name'] = $new_name;
            $user['email'] = $new_email;
            $user['phone'] = $new_phone;
            
        } catch (Exception $e) {
            $error = "Failed to update profile. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .profile-header { 
            background: linear-gradient(135deg, #0d6efd 0%, #0dcaf0 100%); 
            color: white; 
            border-radius: 10px 10px 0 0;
        }
        .avatar { 
            width: 80px; height: 80px; 
            background: white; 
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #0d6efd;
        }
        .info-label { font-size: 0.85rem; color: #6c757d; margin-bottom: 2px; }
        .stat-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .activity-item {
            border-left: 3px solid #e9ecef;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .activity-item:last-child {
            margin-complete: 0;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <h3 class="mb-4 text-primary"><i class="bi bi-shield-check"></i> Admin Profile</h3>

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

        <div class="row">
            <!-- Profile Summary Card -->
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="profile-header p-4 text-center">
                        <div class="avatar mx-auto mb-3">
                            <i class="bi bi-person-badge-fill"></i>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <span class="badge bg-light text-primary">Administrator</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="info-label">Email</div>
                            <div><i class="bi bi-envelope text-muted"></i> <?= htmlspecialchars($user['email']) ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Phone</div>
                            <div><i class="bi bi-phone text-muted"></i> <?= htmlspecialchars($user['phone'] ?: 'Not set') ?></div>
                        </div>
                        <div class="mb-3">
                            <div class="info-label">Account Status</div>
                            <div>
                                <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <div class="info-label">Admin Since</div>
                            <div><i class="bi bi-calendar text-muted"></i> <?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activities</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="small text-muted"><?= date('M d, H:i', strtotime($activity['timestamp'])) ?></div>
                                    <div><?= htmlspecialchars($activity['action']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted small mb-0">No recent activities</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <!-- Statistics Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Fertilizers Managed</div>
                                        <h3 class="mb-0"><?= number_format($stats['fertilizers']) ?></h3>
                                    </div>
                                    <div class="text-primary" style="font-size: 2.5rem;">
                                        <i class="bi bi-box-seam"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stat-card border-0 shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="text-muted small">Total Stock Value</div>
                                        <h3 class="mb-0">MWK <?= number_format($stats['stock_value'], 2) ?></h3>
                                    </div>
                                    <div class="text-success" style="font-size: 2.5rem;">
                                        <i class="bi bi-currency-dollar"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <!-- Personal Information -->
                            <h6 class="text-muted mb-3">Personal Information</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?= htmlspecialchars($user['full_name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control" 
                                           value="<?= htmlspecialchars($user['email']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone Number</label>
                                    <input type="text" name="phone" class="form-control" 
                                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                           placeholder="+265...">
                                </div>
                            </div>

                            <!-- Change Password -->
                            <h6 class="text-muted mb-3">Change Password</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" 
                                           placeholder="Leave blank to keep current">
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control">
                                </div>
                            </div>

                            <!-- Admin Preferences (Optional) -->
                            <h6 class="text-muted mb-3">Preferences</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> As an administrator, you have full access to manage fertilizers, orders, deliveries, certificates, and system logs.
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Save Changes
                                </button>
                                <button type="reset" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>