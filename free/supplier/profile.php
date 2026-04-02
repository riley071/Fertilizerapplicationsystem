<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];
$success = $error = "";

// Fetch user data
$stmt = $conn->prepare("SELECT full_name, email, phone, status, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch supplier data if role is supplier
$supplier = null;
if ($role === 'supplier') {
    $stmt = $conn->prepare("SELECT id, company_name, phone as company_phone, address FROM suppliers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $supplier = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name = trim($_POST['full_name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');
    $new_password = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    
    // Supplier fields
    $company_name = trim($_POST['company_name'] ?? '');
    $company_phone = trim($_POST['company_phone'] ?? '');
    $address = trim($_POST['address'] ?? '');

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
        $conn->begin_transaction();
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

            // Update supplier table if supplier
            if ($role === 'supplier') {
                if ($supplier) {
                    $stmt = $conn->prepare("UPDATE suppliers SET company_name=?, phone=?, address=? WHERE user_id=?");
                    $stmt->bind_param("sssi", $company_name, $company_phone, $address, $user_id);
                } else {
                    $stmt = $conn->prepare("INSERT INTO suppliers (user_id, company_name, phone, address) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("isss", $user_id, $company_name, $company_phone, $address);
                }
                $stmt->execute();
                $stmt->close();
                
                // Refresh supplier data
                $supplier = [
                    'company_name' => $company_name,
                    'company_phone' => $company_phone,
                    'address' => $address
                ];
            }

            $conn->commit();
            $success = "Profile updated successfully!";
            
            // Refresh user data
            $user['full_name'] = $new_name;
            $user['email'] = $new_email;
            $user['phone'] = $new_phone;
            
        } catch (Exception $e) {
            $conn->rollback();
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
    <title>My Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .profile-header { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%); 
            color: white; 
            border-radius: 10px 10px 0 0;
        }
        .avatar { 
            width: 80px; height: 80px; 
            background: white; 
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center;
            font-size: 2rem; color: #28a745;
        }
        .info-label { font-size: 0.85rem; color: #6c757d; margin-bottom: 2px; }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <h3 class="mb-4 text-success"><i class="bi bi-person-circle"></i> My Profile</h3>

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
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <h5 class="mb-1"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <span class="badge bg-light text-dark"><?= ucfirst($role) ?></span>
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
                            <div class="info-label">Member Since</div>
                            <div><i class="bi bi-calendar text-muted"></i> <?= date('M d, Y', strtotime($user['created_at'])) ?></div>
                        </div>
                        
                        <?php if ($role === 'supplier' && $supplier): ?>
                        <hr>
                        <div class="mb-3">
                            <div class="info-label">Company</div>
                            <div><i class="bi bi-building text-muted"></i> <?= htmlspecialchars($supplier['company_name'] ?: 'Not set') ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Edit Profile Form -->
            <div class="col-lg-8">
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

                            <?php if ($role === 'supplier'): ?>
                            <!-- Company Information -->
                            <h6 class="text-muted mb-3">Company Information</h6>
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control" 
                                           value="<?= htmlspecialchars($supplier['company_name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company Phone</label>
                                    <input type="text" name="company_phone" class="form-control" 
                                           value="<?= htmlspecialchars($supplier['company_phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Business Address</label>
                                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <?php endif; ?>

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

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success">
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