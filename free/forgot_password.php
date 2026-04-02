<?php
session_start();
include('includes/db.php');

$success = $error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()");
            $stmt->bind_param("issss", $user['id'], $token, $expiry, $token, $expiry);
            $stmt->execute();
            $stmt->close();
            
            // Create reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
            
            // In production, send email here
            // For now, show the link (for testing purposes)
            $success = "Password reset instructions have been sent to your email. <br><br><strong>For testing:</strong> <a href='$reset_link'>Click here to reset password</a>";
            
            /* 
            // Email sending code (uncomment in production)
            $to = $user['email'];
            $subject = "Password Reset Request - Fertilizer Management System";
            $message = "
                <html>
                <head>
                    <title>Password Reset</title>
                </head>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['full_name']},</p>
                    <p>We received a request to reset your password. Click the link below to reset your password:</p>
                    <p><a href='$reset_link' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p>$reset_link</p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                    <br>
                    <p>Best regards,<br>Fertilizer Management System Team</p>
                </body>
                </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: noreply@fertilizersystem.mw" . "\r\n";
            
            mail($to, $subject, $message, $headers);
            */
            
        } else {
            // Don't reveal if email exists or not (security)
            $success = "If an account exists with this email, password reset instructions have been sent.";
        }
    }
}

// Create password_resets table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    used TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | Fertilizer Management System</title>
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
        .forgot-card {
            max-width: 450px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .forgot-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .forgot-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .forgot-body {
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
        .back-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="forgot-card mx-auto">
            <div class="forgot-header">
                <i class="bi bi-key"></i>
                <h3 class="mb-0">Forgot Password?</h3>
                <p class="mb-0 opacity-75">No worries, we'll send you reset instructions</p>
            </div>
            
            <div class="forgot-body">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <?= $success ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-outline-success">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Enter your email address and we'll send you instructions to reset your password.
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="email" class="form-control" 
                                       placeholder="your.email@example.com" required autofocus>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-reset btn-success w-100">
                            <i class="bi bi-send"></i> Send Reset Link
                        </button>
                    </form>
                    
                    <div class="back-link">
                        <a href="login.php">
                            <i class="bi bi-arrow-left"></i> Back to Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Help Text -->
        <div class="text-center mt-4">
            <p class="text-white">
                <i class="bi bi-shield-check"></i> Your password will remain secure
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>