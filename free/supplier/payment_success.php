<?php
session_start();
include('../includes/db.php');
require_once '../stripe/init.php';

// Stripe Secret Key
\Stripe\Stripe::setApiKey('');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$success = false;
$error = "";
$order = null;

if (isset($_GET['session_id'])) {
    $session_id = $_GET['session_id'];
    
    try {
        // Retrieve the session from Stripe
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        
        if ($session->payment_status === 'paid') {
            $metadata = $session->metadata;
            $order_id = (int) $metadata->order_id;
            $supplier_id = (int) $metadata->supplier_id;
            $total_mwk = (float) $metadata->total_mwk;
            $subsidy_mwk = (float) $metadata->subsidy_mwk;
            $amount_paid_mwk = (float) $metadata->amount_paid_mwk;
            
            // Get order details
            $stmt = $conn->prepare("
                SELECT o.*, f.name as fertilizer_name, f.type as fertilizer_type,
                       p.id as payment_id, p.payment_status
                FROM orders o
                JOIN fertilizers f ON o.fertilizer_id = f.id
                LEFT JOIN payments p ON o.id = p.order_id
                WHERE o.id = ?
            ");
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            // Check if payment already recorded
            if ($order && $order['payment_status'] !== 'Completed') {
                // Record payment
                if ($order['payment_id']) {
                    // Update existing payment
                    $stmt = $conn->prepare("
                        UPDATE payments 
                        SET total_price = ?, subsidy = ?, amount_paid = ?, 
                            payment_status = 'Completed', payment_method = 'Stripe', 
                            transaction_id = ?, payment_date = NOW()
                        WHERE id = ?
                    ");
                    $stmt->bind_param("dddsi", $total_mwk, $subsidy_mwk, $amount_paid_mwk, 
                                     $session->payment_intent, $order['payment_id']);
                } else {
                    // Insert new payment
                    $stmt = $conn->prepare("
                        INSERT INTO payments (order_id, total_price, subsidy, amount_paid, 
                                             payment_status, payment_method, transaction_id, payment_date)
                        VALUES (?, ?, ?, ?, 'Completed', 'Stripe', ?, NOW())
                    ");
                    $stmt->bind_param("iddds", $order_id, $total_mwk, $subsidy_mwk, 
                                     $amount_paid_mwk, $session->payment_intent);
                }
                
                $stmt->execute();
                $stmt->close();
                
                $success = true;
            } else if ($order && $order['payment_status'] === 'Completed') {
                $success = true; // Already processed
            }
        } else {
            $error = "Payment was not completed.";
        }
    } catch (Exception $e) {
        $error = "Error verifying payment: " . $e->getMessage();
    }
} else {
    $error = "Invalid payment session.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Success | Fertilizer System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .success-card {
            max-width: 600px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .success-icon {
            font-size: 5rem;
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: #f0f;
            position: absolute;
            animation: confetti-fall 3s linear;
        }
        @keyframes confetti-fall {
            to {
                transform: translateY(100vh) rotate(360deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card success-card">
            <div class="card-body text-center p-5">
                <?php if ($success): ?>
                    <div class="success-icon text-success mb-4">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h1 class="mb-3">Payment Successful!</h1>
                    <p class="lead text-muted mb-4">
                        Your payment has been processed successfully. Thank you for your order!
                    </p>
                    
                    <?php if ($order): ?>
                    <div class="card bg-light mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Order Details</h5>
                            <hr>
                            <div class="row text-start">
                                <div class="col-6">
                                    <small class="text-muted">Order Number</small>
                                    <p class="mb-2"><strong>#<?= $order['id'] ?></strong></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Fertilizer</small>
                                    <p class="mb-2"><strong><?= htmlspecialchars($order['fertilizer_name']) ?></strong></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Quantity</small>
                                    <p class="mb-2"><strong><?= $order['quantity'] ?> units</strong></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Amount Paid</small>
                                    <p class="mb-2 text-success"><strong>MWK <?= number_format($order['total_price'] * 0.8, 0) ?></strong></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="my_orders.php" class="btn btn-success btn-lg">
                            <i class="bi bi-bag-check"></i> View My Orders
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Go to Dashboard
                        </a>
                    </div>
                    
                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="bi bi-envelope"></i> A confirmation email has been sent to your registered email address.
                        </small>
                    </div>
                    
                <?php else: ?>
                    <div class="success-icon text-danger mb-4">
                        <i class="bi bi-x-circle-fill"></i>
                    </div>
                    <h1 class="mb-3">Payment Failed</h1>
                    <p class="lead text-muted mb-4">
                        <?= htmlspecialchars($error) ?>
                    </p>
                    
                    <div class="d-grid gap-2">
                        <a href="my_orders.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-arrow-left"></i> Back to Orders
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="bi bi-house"></i> Go to Dashboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
   
    <?php endif; ?>
</body>
</html>