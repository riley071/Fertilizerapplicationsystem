<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = "";

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $error = "Email already exists.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $full_name, $email, $phone, $hashed, $role, $status);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Create supplier profile if supplier
            if ($role === 'supplier' && !empty($_POST['company_name'])) {
                $company_name = trim($_POST['company_name']);
                $address = trim($_POST['address']);
                $stmt2 = $conn->prepare("INSERT INTO suppliers (user_id, company_name, phone, address) VALUES (?, ?, ?, ?)");
                $stmt2->bind_param("isss", $user_id, $company_name, $phone, $address);
                $stmt2->execute();
                $stmt2->close();
            }
            
            $success = "User added successfully!";
        } else {
            $error = "Failed to add user.";
        }
    }
    $stmt->close();
}

// Handle Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int) $_POST['user_id'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone=?, role=?, status=? WHERE id=?");
    $stmt->bind_param("sssssi", $full_name, $email, $phone, $role, $status, $user_id);
    
    if ($stmt->execute()) {
        $success = "User updated successfully!";
    } else {
        $error = "Failed to update user.";
    }
    $stmt->close();
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int) $_POST['user_id'];
    
    // Check if user has records
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM orders WHERE supplier_id IN (SELECT id FROM suppliers WHERE user_id = ?)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $hasRecords = $stmt->get_result()->fetch_assoc()['cnt'] > 0;
    $stmt->close();
    
    if ($hasRecords) {
        $error = "Cannot delete user with existing orders.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $success = "User deleted successfully!";
        }
        $stmt->close();
    }
}

// Handle Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $user_id = (int) $_POST['user_id'];
    $new_password = $_POST['new_password'];
    
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    
    if ($stmt->execute()) {
        $success = "Password reset successfully!";
    } else {
        $error = "Failed to reset password.";
    }
    $stmt->close();
}

// Filters
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$whereConditions = ["1=1"];
if ($role_filter !== 'all') {
    $whereConditions[] = "u.role = '{$role_filter}'";
}
if ($status_filter !== 'all') {
    $whereConditions[] = "u.status = '{$status_filter}'";
}
if ($search) {
    $searchEsc = $conn->real_escape_string($search);
    $whereConditions[] = "(u.full_name LIKE '%{$searchEsc}%' OR u.email LIKE '%{$searchEsc}%')";
}

$whereClause = implode(' AND ', $whereConditions);

// Fetch users
$users = $conn->query("
    SELECT u.*, s.company_name
    FROM users u
    LEFT JOIN suppliers s ON u.id = s.user_id
    WHERE {$whereClause}
    ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Stats
$stats = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'supplier' THEN 1 ELSE 0 END) as suppliers,
        SUM(CASE WHEN role = 'driver' THEN 1 ELSE 0 END) as drivers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM users
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .stat-card { border-radius: 12px; border: none; }
        .user-row { transition: background 0.2s; }
        .user-row:hover { background: #f8f9fa; }
        .role-badge-admin { background: linear-gradient(135deg, #dc3545, #c82333); }
        .role-badge-supplier { background: linear-gradient(135deg, #28a745, #20c997); }
        .role-badge-driver { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>
    
    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0"><i class="bi bi-people"></i> User Management</h3>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus"></i> Add User
            </button>
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
            <div class="col-md-2">
                <div class="card stat-card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="mb-0"><?= $stats['total'] ?></h3>
                        <small class="text-muted">Total Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-danger"><?= $stats['admins'] ?></h3>
                        <small class="text-muted">Admins</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-success"><?= $stats['suppliers'] ?></h3>
                        <small class="text-muted">Suppliers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-primary"><?= $stats['drivers'] ?></h3>
                        <small class="text-muted">Drivers</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-success"><?= $stats['active'] ?></h3>
                        <small class="text-muted">Active</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stat-card shadow-sm text-center">
                    <div class="card-body">
                        <h3 class="mb-0 text-secondary"><?= $stats['inactive'] ?></h3>
                        <small class="text-muted">Inactive</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name or email..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="all" <?= $role_filter === 'all' ? 'selected' : '' ?>>All Roles</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="supplier" <?= $role_filter === 'supplier' ? 'selected' : '' ?>>Supplier</option>
                            <option value="driver" <?= $role_filter === 'driver' ? 'selected' : '' ?>>Driver</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Status</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Filter</button>
                        <a href="manage_users.php" class="btn btn-outline-secondary">Clear</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($users)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr class="user-row">
                                <td>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                    <?php if ($user['company_name']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($user['company_name']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone'] ?: '-') ?></td>
                                <td>
                                    <span class="badge role-badge-<?= $user['role'] ?> text-white">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($user['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Edit"
                                                onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-warning" title="Reset Password"
                                                onclick="resetPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')">
                                            <i class="bi bi-key"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button type="submit" name="delete_user" class="btn btn-outline-danger" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" minlength="6" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" id="addRole" onchange="toggleCompanyFields()" required>
                                <option value="supplier">Supplier</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div id="companyFields">
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_user" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="editFullName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="editEmail" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" id="editPhone" class="form-control">
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" id="editRole" class="form-select" required>
                                <option value="supplier">Supplier</option>
                                <option value="driver">Driver</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="editStatus" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_user" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="bi bi-key"></i> Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for: <strong id="resetUserName"></strong></p>
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" minlength="6" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="reset_password" class="btn btn-warning w-100">
                        <i class="bi bi-key"></i> Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleCompanyFields() {
        const role = document.getElementById('addRole').value;
        const companyFields = document.getElementById('companyFields');
        companyFields.style.display = role === 'supplier' ? 'block' : 'none';
    }
    
    function editUser(user) {
        document.getElementById('editUserId').value = user.id;
        document.getElementById('editFullName').value = user.full_name;
        document.getElementById('editEmail').value = user.email;
        document.getElementById('editPhone').value = user.phone || '';
        document.getElementById('editRole').value = user.role;
        document.getElementById('editStatus').value = user.status;
        
        new bootstrap.Modal(document.getElementById('editUserModal')).show();
    }
    
    function resetPassword(id, name) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetUserName').textContent = name;
        
        new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
    }
    
    toggleCompanyFields();
</script>
</body>
</html>