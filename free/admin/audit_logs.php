<?php
session_start();
include('../includes/db.php');

// Admin-only access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Function to log actions
function logAction($conn, $action, $recordId = null, $tableName = null, $beforeState = null, $afterState = null) {
    $adminId = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Convert states to JSON strings
    $beforeJson = $beforeState ? json_encode($beforeState) : null;
    $afterJson = $afterState ? json_encode($afterState) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO logs 
        (user_id, action, ip_address, affected_record_id, affected_table, before_state, after_state, timestamp)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param(
        "ississs", 
        $adminId,
        $action,
        $ip,
        $recordId,
        $tableName,
        $beforeJson,
        $afterJson
    );
    
    $stmt->execute();
    $stmt->close();
}

$success = $error = '';
$adminId = $_SESSION['user_id'];

// Handle Certificate Status Changes
if (isset($_GET['change_cert_status'])) {
    $certId = (int)$_GET['id'];
    $newStatus = $_GET['change_cert_status'];
    
    // Get current certificate state
    $stmt = $conn->prepare("SELECT * FROM certificates WHERE id = ?");
    $stmt->bind_param("i", $certId);
    $stmt->execute();
    $cert = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($cert) {
        $beforeState = $cert;
        
        // Prepare update
        $updateData = ['status' => $newStatus];
        $updateSql = "UPDATE certificates SET status = ?";
        $params = [$newStatus];
        $types = "s";
        
        if ($newStatus === 'Approved') {
            $updateData['issued_on'] = date('Y-m-d');
            $updateData['expires_on'] = date('Y-m-d', strtotime('+1 year'));
            $updateSql .= ", issued_on = ?, expires_on = ?";
            $params[] = $updateData['issued_on'];
            $params[] = $updateData['expires_on'];
            $types .= "ss";
        } elseif ($newStatus === 'Revoked') {
            $updateData['expires_on'] = date('Y-m-d');
            $updateSql .= ", expires_on = ?";
            $params[] = $updateData['expires_on'];
            $types .= "s";
        }
        
        $updateSql .= " WHERE id = ?";
        $params[] = $certId;
        $types .= "i";
        
        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            $success = "Certificate status updated to $newStatus.";
            
            // Get after state
            $stmt = $conn->prepare("SELECT * FROM certificates WHERE id = ?");
            $stmt->bind_param("i", $certId);
            $stmt->execute();
            $afterState = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Log the action
            logAction($conn, "changed certificate status to $newStatus", $certId, 'certificates', $beforeState, $afterState);
        } else {
            $error = "Failed to update certificate status.";
        }
    }
}

// Handle log deletion
if (isset($_GET['delete_log'])) {
    $logId = (int)$_GET['delete_log'];
    
    // Get log data before deletion (for logging the deletion)
    $stmt = $conn->prepare("SELECT * FROM logs WHERE id = ?");
    $stmt->bind_param("i", $logId);
    $stmt->execute();
    $log = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($log) {
        // Delete the log
        $stmt = $conn->prepare("DELETE FROM logs WHERE id = ?");
        $stmt->bind_param("i", $logId);
        
        if ($stmt->execute()) {
            $success = "Log entry deleted successfully.";
            // Log the deletion of the log
            logAction($conn, "deleted log entry", $logId, 'logs', $log, null);
        } else {
            $error = "Failed to delete log entry.";
        }
        $stmt->close();
    }
}

// Handle delete all logs
if (isset($_GET['delete_all_logs'])) {
    // Get count of logs before deletion
    $result = $conn->query("SELECT COUNT(*) as count FROM logs");
    $count = $result->fetch_assoc()['count'];
    $result->close();
    
    if ($count > 0) {
        // Delete all logs
        if ($conn->query("TRUNCATE TABLE logs")) {
            $success = "All $count log entries deleted successfully.";
            // Log the mass deletion
            logAction($conn, "deleted all log entries", null, 'logs', ['count' => $count], null);
        } else {
            $error = "Failed to delete all log entries.";
        }
    } else {
        $error = "No logs to delete.";
    }
}

// Handle Add User form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Basic validation
    if ($full_name === '' || $email === '' || $role === '' || $password === '') {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $error = "Email is already registered.";
        } else {
            // Hash password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $password_hash, $role);
            
            if ($stmt->execute()) {
                $newUserId = $stmt->insert_id;
                $success = "User added successfully.";
                
                // Prepare after state for logging
                $afterState = [
                    'full_name' => $full_name,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                    'status' => 'active'
                ];
                
                // Log the user creation
                logAction($conn, "created user", $newUserId, 'users', null, $afterState);
            } else {
                $error = "Failed to add user.";
            }
        }
        $stmt->close();
    }
}

// Handle status toggle
if (isset($_GET['toggle_status'])) {
    $id = (int)$_GET['toggle_status'];
    
    // Get current state before update
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $beforeState = $row;
        $new_status = ($row['status'] === 'active') ? 'inactive' : 'active';
        
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $id);
        
        if ($stmt->execute()) {
            $success = "User status updated.";
            
            // Prepare after state for logging
            $afterState = $beforeState;
            $afterState['status'] = $new_status;
            
            // Log the status change
            logAction($conn, "changed user status to $new_status", $id, 'users', $beforeState, $afterState);
        }
        $stmt->close();
    }
}

// Handle delete user
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    
    // Get user data before deletion
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        $beforeState = $row;
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $success = "User deleted.";
            
            // Log the deletion
            logAction($conn, "deleted user", $id, 'users', $beforeState, null);
        }
        $stmt->close();
    }
}

// Fetch all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");

// Fetch all certificates with supplier names
$certificates = $conn->query("
    SELECT c.*, u.full_name as supplier_name 
    FROM certificates c
    JOIN users u ON c.supplier_id = u.id
    ORDER BY c.created_at DESC
");

// Fetch all logs with admin names
$logs = $conn->query("
    SELECT l.*, u.full_name as admin_name 
    FROM logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.timestamp DESC
");

// Pagination setup
$perPage = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Search/filter options
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$actionType = isset($_GET['action_type']) ? $conn->real_escape_string($_GET['action_type']) : '';
$dateFrom = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? $conn->real_escape_string($_GET['date_to']) : '';

$query = "SELECT l.id, l.user_id, l.action, l.timestamp, l.ip_address, 
                 l.affected_table, l.affected_record_id, l.before_state, l.after_state,
                 u.full_name, u.email, u.role 
          FROM logs l 
          JOIN users u ON l.user_id = u.id 
          WHERE 1=1";
$countQuery = "SELECT COUNT(*) as total FROM logs l JOIN users u ON l.user_id = u.id WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (l.action LIKE '%$search%' OR l.ip_address LIKE '%$search%' OR u.full_name LIKE '%$search%')";
    $countQuery .= " AND (l.action LIKE '%$search%' OR l.ip_address LIKE '%$search%')";
}

if ($userId > 0) {
    $query .= " AND l.user_id = $userId";
    $countQuery .= " AND l.user_id = $userId";
}

if (!empty($actionType)) {
    $query .= " AND l.action LIKE '$actionType%'";
    $countQuery .= " AND l.action LIKE '$actionType%'";
}

if (!empty($dateFrom)) {
    $query .= " AND DATE(l.timestamp) >= '$dateFrom'";
    $countQuery .= " AND DATE(l.timestamp) >= '$dateFrom'";
}

if (!empty($dateTo)) {
    $query .= " AND DATE(l.timestamp) <= '$dateTo'";
    $countQuery .= " AND DATE(l.timestamp) <= '$dateTo'";
}

$query .= " ORDER BY l.timestamp DESC LIMIT $offset, $perPage";

// Get logs
$logs = $conn->query($query);
$totalResult = $conn->query($countQuery)->fetch_assoc()['total'];
$totalPages = ceil($totalResult / $perPage);

// Get list of all users for filter dropdown
$users = $conn->query("SELECT id, full_name, role FROM users ORDER BY full_name");

// Get distinct action types for filter dropdown
$actionTypes = $conn->query("SELECT DISTINCT action FROM logs ORDER BY action");

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail | Fertilizer System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"> <!-- Responsive meta -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
   <style>
    body {
        overflow-x: hidden;
    }

    .audit-details pre {
        white-space: pre-wrap;
        word-wrap: break-word;
        overflow-x: auto;
        max-width: 50%;
        max-height: 150px;
        background-color: #f8f9fa;
        padding: 10px;
        border-radius: 4px;
        border: 1px solid #dee2e6;
        margin-bottom: 0;
    }

    .audit-log {
        border-left: 4px solid #388e3c;
        padding: 12px;
        margin-bottom: 12px;
        background-color: #f8f9fa;
    }

    .audit-log-header {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap; /* stack on mobile */
        gap: 8px;
        margin-bottom: 6px;
    }

    .audit-action {
        font-weight: 600;
        color: #388e3c;
    }

    .audit-user {
        font-weight: 500;
        word-break: break-word;
    }

    .audit-meta {
        font-size: 0.85rem;
        color: #6c757d;
        word-break: break-word;
    }

    .badge-admin { background-color: #6f42c1; }
    .badge-supplier { background-color: #fd7e14; }
    .badge-farmer { background-color: #20c997; }

    #auditLogs {
        max-height: 600px;
        overflow-y: auto;
    }

    /* Mobile tweaks */
    @media (max-width: 768px) {
        .card-body form .col-md-3,
        .card-body form .col-md-2,
        .card-body form .col-md-1 {
            flex: 0 0 100%;
            max-width: 100%;
        }

        #auditLogs {
            max-height: unset; /* expand naturally on phones */
        }

        .pagination {
            flex-wrap: wrap;
            justify-content: center;
        }
    }
</style>
</head>
<body>
<div class="d-flex flex-column flex-lg-row">
    <?php include '../includes/sidebar.php'; ?>

    <div class="flex-grow-1 p-3 p-md-4">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
            <h2 class="text-success mb-0">System Audit Trail</h2>
            <div class="text-muted">Total Logs: <?= number_format($totalResult) ?></div>
        </div>
        
        <!-- Filter Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-funnel"></i> Filter Logs</span>
                <a href="?delete_all_logs" class="btn btn-sm btn-danger" 
                   onclick="return confirm('Are you sure you want to delete ALL logs? This action cannot be undone.')">
                   <i class="bi bi-trash"></i> Delete All Logs
                </a>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-12 col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search actions..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <select name="user_id" class="form-select">
                            <option value="0">All Users</option>
                            <?php while ($user = $users->fetch_assoc()): ?>
                                <option value="<?= $user['id'] ?>" <?= $userId == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['full_name']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <select name="action_type" class="form-select">
                            <option value="">All Actions</option>
                            <?php while ($action = $actionTypes->fetch_assoc()): ?>
                                <option value="<?= $action['action'] ?>" <?= $actionType == $action['action'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($action['action']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-12 col-md-2">
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-12 col-md-1">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="bi bi-filter"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Audit Logs -->
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clipboard-data"></i> System Activities</span>
                <?php if ($totalPages > 1): ?>
                    <small class="text-white">Page <?= $page ?> of <?= $totalPages ?></small>
                <?php endif; ?>
            </div>
            <div class="card-body" id="auditLogs">
                <?php if ($logs->num_rows === 0): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x" style="font-size: 3rem;"></i>
                        <h5 class="mt-3">No audit logs found</h5>
                        <p>Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                        <div class="audit-log">
                            <div class="audit-log-header">
                                <div>
                                    <span class="audit-action"><?= htmlspecialchars($log['action']) ?></span>
                                    <span class="badge badge-<?= strtolower($log['role']) ?> ms-2">
                                        <?= ucfirst($log['role']) ?>
                                    </span>
                                </div>
                                <div class="audit-meta">
                                    <?= date('M d, Y H:i:s', strtotime($log['timestamp'])) ?>
                                </div>
                                <a href="?delete_log=<?= $log['id'] ?>" class="btn btn-sm btn-danger" 
                                   onclick="return confirm('Are you sure you want to delete this log entry?')">
                                   <i class="bi bi-trash"></i>
                                </a>
                            </div>
                            
                            <div class="audit-user">
                                <i class="bi bi-person"></i> 
                                <?= htmlspecialchars($log['full_name']) ?> 
                                (<?= htmlspecialchars($log['email']) ?>)
                            </div>
                            
                            <div class="audit-meta mt-1">
                                <i class="bi bi-globe"></i> IP: <?= !empty($log['ip_address']) ? htmlspecialchars($log['ip_address']) : 'N/A' ?>
                                <?php if (!empty($log['affected_table'])): ?>
                                    | <i class="bi bi-table"></i> Table: <?= htmlspecialchars($log['affected_table']) ?>
                                    <?php if (!empty($log['affected_record_id'])): ?>
                                        (ID: <?= htmlspecialchars($log['affected_record_id']) ?>)
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($log['before_state']) || !empty($log['after_state'])): ?>
                                <div class="audit-details mt-3">
                                    <div class="row">
                                        <?php if (!empty($log['before_state'])): ?>
                                            <div class="col-12 col-md-6 mb-3">
                                                <h6><i class="bi bi-arrow-left-circle"></i> Before State</h6>
                                                <pre><?= json_encode(json_decode($log['before_state']), JSON_PRETTY_PRINT) ?></pre>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($log['after_state'])): ?>
                                            <div class="col-12 col-md-6 mb-3">
                                                <h6><i class="bi bi-arrow-right-circle"></i> After State</h6>
                                                <pre><?= json_encode(json_decode($log['after_state']), JSON_PRETTY_PRINT) ?></pre>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center flex-wrap">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" 
                                       href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&user_id=<?= $userId ?>&action_type=<?= urlencode($actionType) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" 
                                       href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&user_id=<?= $userId ?>&action_type=<?= urlencode($actionType) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" 
                                       href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&user_id=<?= $userId ?>&action_type=<?= urlencode($actionType) ?>&date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>