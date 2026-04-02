<?php
session_start();
include('includes/db.php');

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: {$role}/dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = "Your account is inactive. Please contact administrator.";
            } else {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Log the login
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, 'User logged in', ?)");
                $stmt->bind_param("is", $user['id'], $ip);
                $stmt->execute();
                $stmt->close();
                
                // Redirect based on role
                header("Location: {$user['role']}/dashboard.php");
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Fertilizer Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        .login-container {
            display: flex;
            width: 100%;
            max-width: 1000px;
            margin: auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-banner {
            flex: 1;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .login-banner h1 { font-size: 2.5rem; margin-bottom: 20px; }
        .login-banner p { opacity: 0.9; line-height: 1.8; }
        .login-form {
            flex: 1;
            padding: 60px 50px;
        }
        .login-form h2 { color: #28a745; margin-bottom: 10px; }
        .form-floating { margin-bottom: 20px; }
        .btn-login {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px;
            font-size: 1.1rem;
        }
        .btn-login:hover { opacity: 0.9; }
        .feature-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .feature-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        @media (max-width: 768px) {
            .login-banner { display: none; }
            .login-container { max-width: 450px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Banner -->
        <div class="login-banner">
            <div>
                <h1><i class="bi bi-tree"></i> Fertilizer Application System</h1>
                <p>A comprehensive platform for managing fertilizer distribution, supplier certification, and delivery tracking across Malawi.</p>
                
                <div class="mt-5">
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-patch-check"></i></div>
                        <div>
                            <strong>Supplier Certification</strong><br>
                            <small>QR-based certificate application and verification</small>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-truck"></i></div>
                        <div>
                            <strong>Delivery Tracking</strong><br>
                            <small>Real-time GPS tracking for all deliveries</small>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="bi bi-graph-up"></i></div>
                        <div>
                            <strong>Analytics & Reports</strong><br>
                            <small>Comprehensive insights and reporting</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Login Form -->
        <div class="login-form">
            <h2><i class="bi bi-box-arrow-in-right"></i> Welcome Back</h2>
            <p class="text-muted mb-4">Sign in to your account to continue</p>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-floating">
                    <input type="email" name="email" class="form-control" id="email" placeholder="Email" required autofocus>
                    <label for="email"><i class="bi bi-envelope"></i> Email Address</label>
                </div>
                
                <div class="form-floating">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <label for="password"><i class="bi bi-lock"></i> Password</label>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
                    <a href="forgot_password.php" class="text-decoration-none">Forgot password?</a>
                </div>

                <button type="submit" class="btn btn-success btn-login w-100 mb-3">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>

                <p class="text-center text-muted">
                    Don't have an account? <a href="register.php" class="text-success">Register here</a>
                </p>
            </form>

            <hr class="my-4">

            <!-- <div class="text-center text-muted small">
                <p class="mb-2">Demo Accounts:</p>
                <code>admin@example.com</code> | <code>supplier@example.com</code> | <code>driver@example.com</code><br>
                <small>Password: <code>password123</code></small>
            </div> -->
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>