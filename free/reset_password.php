<?php
session_start();
include('includes/db.php');

$success = $error = "";
$token_valid = false;
$token = $_GET['token'] ?? '';

// Verify token
if (!empty($token)) {
    $stmt = $conn->prepare("
        SELECT pr.*, u.email, u.full_name 
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset_request = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($reset_request) {
        $token_valid = true;
    } else {
        $error = "This password reset link is invalid or has expired. Please request a new one.";
    }
} else {
    $error = "Invalid password reset link.";
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $reset_request['user_id']);
        
        if ($stmt->execute()) {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $stmt->bind_param("i", $reset_request['id']);
            $stmt->execute();
            
            $success = "Password reset successfully! You can now login with your new password.";
            $token_valid = false; // Prevent form from showing
        } else {
            $error = "Failed to reset password. Please try again.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password | Fertilizer Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-card {
            max-width: 500px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .reset-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .reset-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .reset-body {
            padding: 2rem;
            background: white;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-reset {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .password-strength {
            height: 5px;
            border-radius: 3px;
            transition: all 0.3s;
        }
        .strength-weak { background: #dc3545; width: 33%; }
        .strength-medium { background: #ffc107; width: 66%; }
        .strength-strong { background: #28a745; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-card mx-auto">
            <div class="reset-header">
                <i class="bi bi-shield-lock"></i>
                <h3 class="mb-0">Reset Password</h3>
                <p class="mb-0 opacity-75">Enter your new password</p>
            </div>
            
            <div class="reset-body">
                <?php if ($success): ?>
                    <div class="alert alert-success text-center">
                        <i class="bi bi-check-circle display-1 text-success"></i>
                        <h4 class="mt-3">Password Reset Successful!</h4>
                        <p><?= $success ?></p>
                    </div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-success btn-lg">
                            <i class="bi bi-box-arrow-in-right"></i> Go to Login
                        </a>
                    </div>
                <?php elseif ($error && !$token_valid): ?>
                    <div class="alert alert-danger text-center">
                        <i class="bi bi-exclamation-triangle display-1 text-danger"></i>
                        <h4 class="mt-3">Invalid or Expired Link</h4>
                        <p><?= htmlspecialchars($error) ?></p>
                    </div>
                    <div class="text-center">
                        <a href="forgot_password.php" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Request New Link
                        </a>
                        <a href="login.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($reset_request): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-person-circle"></i> Resetting password for: <strong><?= htmlspecialchars($reset_request['email']) ?></strong>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="resetForm">
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" 
                                       placeholder="Enter new password" minlength="6" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                            <div class="mt-2">
                                <div class="password-strength" id="strengthBar"></div>
                                <small class="text-muted" id="strengthText">Password strength</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                                       placeholder="Confirm new password" minlength="6" required>
                            </div>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        
                        <div class="alert alert-warning small">
                            <i class="bi bi-info-circle"></i> <strong>Password Tips:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Use at least 6 characters</li>
                                <li>Mix uppercase and lowercase letters</li>
                                <li>Include numbers and special characters</li>
                                <li>Avoid common words or patterns</li>
                            </ul>
                        </div>
                        
                        <button type="submit" class="btn btn-reset btn-success w-100">
                            <i class="bi bi-check-circle"></i> Reset Password
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-muted">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword')?.addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeIcon.classList.remove('bi-eye');
                eyeIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                eyeIcon.classList.remove('bi-eye-slash');
                eyeIcon.classList.add('bi-eye');
            }
        });
        
        // Password strength checker
        document.getElementById('password')?.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthBar.className = 'password-strength';
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
                strengthText.textContent = 'Weak password';
                strengthText.className = 'text-danger';
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
                strengthText.textContent = 'Medium password';
                strengthText.className = 'text-warning';
            } else {
                strengthBar.classList.add('strength-strong');
                strengthText.textContent = 'Strong password';
                strengthText.className = 'text-success';
            }
        });
        
        // Password match validator
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            } else {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
                if (confirmPassword) this.classList.add('is-valid');
            }
        });
    </script>
</body>
</html>