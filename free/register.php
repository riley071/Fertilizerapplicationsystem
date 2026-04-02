<?php
session_start();
include('includes/db.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: {$role}/dashboard.php");
    exit();
}

$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'supplier';
    
    // Company details for suppliers
    $company_name = trim($_POST['company_name'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');
    
    // Validation
    if (empty($full_name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!in_array($role, ['supplier', 'driver'])) {
        $error = "Invalid role selected.";
    } elseif ($role === 'supplier' && empty($company_name)) {
        $error = "Company name is required for suppliers.";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = "An account with this email already exists.";
        }
        $stmt->close();
    }
    
    if (empty($error)) {
        $conn->begin_transaction();
        
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $full_name, $email, $phone, $hashed_password, $role);
            $stmt->execute();
            $user_id = $stmt->insert_id;
            $stmt->close();
            
            // Create supplier profile if supplier
            if ($role === 'supplier') {
                $stmt = $conn->prepare("INSERT INTO suppliers (user_id, company_name, phone, address) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $user_id, $company_name, $phone, $company_address);
                $stmt->execute();
                $stmt->close();
            }
            
            // Log registration
            $ip = $_SERVER['REMOTE_ADDR'];
            $stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, 'New user registered', ?)");
            $stmt->bind_param("is", $user_id, $ip);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            $success = "Registration successful! You can now login.";
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - Fertilizer Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 20px;
        }
        .register-container {
            display: flex;
            width: 100%;
            max-width: 1100px;
            margin: auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .register-banner {
            flex: 0 0 350px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 50px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .register-banner h1 { font-size: 2rem; margin-bottom: 20px; }
        .register-form {
            flex: 1;
            padding: 40px 50px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .register-form h2 { color: #28a745; margin-bottom: 5px; }
        .role-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        .role-option {
            flex: 1;
            text-align: center;
            padding: 20px;
            border: 2px solid #dee2e6;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .role-option:hover { border-color: #28a745; }
        .role-option.active {
            border-color: #28a745;
            background: #e8f5e9;
        }
        .role-option i { font-size: 2rem; margin-bottom: 10px; }
        .role-option input { display: none; }
        .btn-register {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px;
            font-size: 1.1rem;
        }
        .supplier-fields { display: none; }
        .supplier-fields.show { display: block; }
        @media (max-width: 768px) {
            .register-banner { display: none; }
            .register-container { max-width: 550px; }
            .register-form { padding: 30px; }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <!-- Banner -->
        <div class="register-banner">
            <div>
                <h1><i class="bi bi-tree"></i> Join Our Platform</h1>
                <p>Register to access the Fertilizer Management System and streamline your operations.</p>
                
                <div class="mt-5">
                    <h5><i class="bi bi-building"></i> For Suppliers</h5>
                    <ul class="small">
                        <li>Apply for certification online</li>
                        <li>Manage inventory & orders</li>
                        <li>Track deliveries in real-time</li>
                        <li>Generate invoices & reports</li>
                    </ul>
                    
                    <h5 class="mt-4"><i class="bi bi-truck"></i> For Drivers</h5>
                    <ul class="small">
                        <li>Receive delivery assignments</li>
                        <li>GPS navigation support</li>
                        <li>Update delivery status</li>
                        <li>View delivery history</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Registration Form -->
        <div class="register-form">
            <h2><i class="bi bi-person-plus"></i> Create Account</h2>
            <p class="text-muted mb-4">Fill in your details to get started</p>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <br><a href="login.php" class="alert-link">Click here to login</a>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="registerForm">
                <!-- Role Selection -->
                <label class="form-label">I am a <span class="text-danger">*</span></label>
                <div class="role-selector">
                    <label class="role-option active" id="supplierOption">
                        <input type="radio" name="role" value="supplier" checked>
                        <i class="bi bi-building text-success d-block"></i>
                        <strong>Supplier</strong>
                        <small class="d-block text-muted">Fertilizer distributor</small>
                    </label>
                    <label class="role-option" id="driverOption">
                        <input type="radio" name="role" value="driver">
                        <i class="bi bi-truck text-primary d-block"></i>
                        <strong>Driver</strong>
                        <small class="d-block text-muted">Delivery personnel</small>
                    </label>
                </div>

                <!-- Personal Information -->
                <h6 class="text-muted mb-3"><i class="bi bi-person"></i> Personal Information</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required 
                               value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="+265..."
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <!-- Company Information (Suppliers Only) -->
                <div class="supplier-fields show" id="supplierFields">
                    <h6 class="text-muted mb-3"><i class="bi bi-building"></i> Company Information</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control"
                                   value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Company Address</label>
                            <textarea name="company_address" class="form-control" rows="2"><?= htmlspecialchars($_POST['company_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Password -->
                <h6 class="text-muted mb-3"><i class="bi bi-lock"></i> Security</h6>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="6">
                        <div class="form-text">Minimum 6 characters</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>

                <!-- Terms -->
                <div class="form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" class="text-success">Terms of Service</a> and 
                        <a href="#" class="text-success">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-success btn-register w-100 mb-3">
                    <i class="bi bi-person-plus"></i> Create Account
                </button>

                <p class="text-center text-muted">
                    Already have an account? <a href="login.php" class="text-success">Sign in here</a>
                </p>
            </form>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Role selection toggle
    const supplierOption = document.getElementById('supplierOption');
    const driverOption = document.getElementById('driverOption');
    const supplierFields = document.getElementById('supplierFields');
    
    document.querySelectorAll('.role-option input').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('active'));
            this.closest('.role-option').classList.add('active');
            
            if (this.value === 'supplier') {
                supplierFields.classList.add('show');
            } else {
                supplierFields.classList.remove('show');
            }
        });
    });
    
    // Click handler for role options
    document.querySelectorAll('.role-option').forEach(option => {
        option.addEventListener('click', function() {
            this.querySelector('input').checked = true;
            this.querySelector('input').dispatchEvent(new Event('change'));
        });
    });
</script>
</body>
</html>