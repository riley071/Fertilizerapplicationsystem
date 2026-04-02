<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$success = $error = "";

// Get supplier_id from suppliers table
$stmt = $conn->prepare("SELECT id, company_name FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supplier_id = $supplier ? (int) $supplier['id'] : null;

if (!$supplier_id) {
    header('Location: ../login.php');
    exit();
}

// Get order_id from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;

// Fetch order details
$order = null;
if ($order_id) {
    $stmt = $conn->prepare("
        SELECT o.*, f.name as fertilizer_name, f.type as fertilizer_type,
               p.id as payment_id, p.payment_status, p.amount_paid
        FROM orders o
        JOIN fertilizers f ON o.fertilizer_id = f.id
        LEFT JOIN payments p ON o.id = p.order_id
        WHERE o.id = ? AND o.supplier_id = ?
    ");
    $stmt->bind_param("ii", $order_id, $supplier_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$order) {
        $error = "Order not found or unauthorized.";
    } elseif ($order['payment_status'] === 'Completed') {
        $error = "This order has already been paid for.";
    } elseif (!in_array($order['status'], ['Approved', 'Dispatched', 'Delivered'])) {
        $error = "Payment can only be made for approved or dispatched orders.";
    }
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $order_id && $order) {
    $payment_method = $_POST['payment_method'] ?? '';
    $transaction_id = trim($_POST['transaction_id'] ?? '');
    $amount_paid = (float) ($_POST['amount_paid'] ?? 0);
    
    // Calculate subsidy (example: 20% government subsidy)
    $subsidy_percentage = 20;
    $subsidy_amount = ($order['total_price'] * $subsidy_percentage) / 100;
    $amount_to_pay = $order['total_price'] - $subsidy_amount;
    
    if (empty($payment_method)) {
        $error = "Please select a payment method.";
    } elseif (empty($transaction_id)) {
        $error = "Please enter a transaction ID or reference number.";
    } elseif ($amount_paid < $amount_to_pay) {
        $error = "Payment amount is insufficient. Required: MWK " . number_format($amount_to_pay, 0);
    } else {
        // Handle receipt upload
        $receipt_path = null;
        if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === 0) {
            $uploadDir = '../uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            
            $ext = pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION);
            $receipt_filename = 'receipt_' . $order_id . '_' . time() . '.' . $ext;
            $receipt_path = 'uploads/receipts/' . $receipt_filename;
            
            move_uploaded_file($_FILES['receipt']['tmp_name'], '../' . $receipt_path);
        }
        
        // Insert or update payment
        if ($order['payment_id']) {
            // Update existing payment
            $stmt = $conn->prepare("
                UPDATE payments 
                SET total_price = ?, subsidy = ?, amount_paid = ?, 
                    payment_status = 'Completed', payment_method = ?, 
                    transaction_id = ?, receipt_path = ?, payment_date = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("dddsssi", $order['total_price'], $subsidy_amount, $amount_paid, 
                             $payment_method, $transaction_id, $receipt_path, $order['payment_id']);
        } else {
            // Insert new payment
            $stmt = $conn->prepare("
                INSERT INTO payments (order_id, total_price, subsidy, amount_paid, 
                                     payment_status, payment_method, transaction_id, 
                                     receipt_path, payment_date)
                VALUES (?, ?, ?, ?, 'Completed', ?, ?, ?, NOW())
            ");
            $stmt->bind_param("idddsss", $order_id, $order['total_price'], $subsidy_amount, 
                             $amount_paid, $payment_method, $transaction_id, $receipt_path);
        }
        
        if ($stmt->execute()) {
            $success = "Payment successful! Your order will be processed soon.";
            
            // Refresh order data
            $stmt = $conn->prepare("
                SELECT o.*, f.name as fertilizer_name, f.type as fertilizer_type,
                       p.id as payment_id, p.payment_status, p.amount_paid
                FROM orders o
                JOIN fertilizers f ON o.fertilizer_id = f.id
                LEFT JOIN payments p ON o.id = p.order_id
                WHERE o.id = ? AND o.supplier_id = ?
            ");
            $stmt->bind_param("ii", $order_id, $supplier_id);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Payment failed. Please try again.";
        }
        $stmt->close();
    }
}

// Calculate payment details
$subsidy_percentage = 20;
$subsidy_amount = $order ? ($order['total_price'] * $subsidy_percentage) / 100 : 0;
$amount_to_pay = $order ? ($order['total_price'] - $subsidy_amount) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Make Payment | Fertilizer System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f7f9f6; }
        .payment-card { border-radius: 15px; }
        .payment-method-card {
            cursor: pointer;
            transition: all 0.2s;
            border: 2px solid transparent;
        }
        .payment-method-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .payment-method-card.selected {
            border-color: #198754;
            background: #d4edda;
        }
        .payment-method-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .price-breakdown {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
        }
        .subsidy-badge {
            background: #ffc107;
            color: #000;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include('../includes/sidebar.php'); ?>

    <div class="flex-grow-1 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="text-success mb-0">
                <i class="bi bi-credit-card"></i> Make Payment
            </h3>
            <a href="my_orders.php" class="btn btn-outline-success">
                <i class="bi bi-arrow-left"></i> Back to Orders
            </a>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                <a href="my_orders.php" class="alert-link">View your orders</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$order || $order['payment_status'] === 'Completed'): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-exclamation-triangle display-1 text-warning"></i>
                    <h4 class="mt-3">No Payment Required</h4>
                    <p class="text-muted">This order doesn't require payment or has already been paid.</p>
                    <a href="my_orders.php" class="btn btn-success">View Orders</a>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <!-- Order Summary -->
                <div class="col-lg-4">
                    <div class="card payment-card shadow-sm mb-3">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="bi bi-receipt"></i> Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <h6 class="mb-3">Order #<?= $order['id'] ?></h6>
                            
                            <div class="mb-3">
                                <strong><?= htmlspecialchars($order['fertilizer_name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($order['fertilizer_type']) ?></small>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Quantity</small>
                                <div><?= $order['quantity'] ?> units</div>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Unit Price</small>
                                <div>MWK <?= number_format($order['price_per_unit'], 0) ?></div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-2">
                                <small class="text-muted">Subtotal</small>
                                <div><strong>MWK <?= number_format($order['total_price'], 0) ?></strong></div>
                            </div>
                            
                            <div class="mb-2 text-success">
                                <small class="text-muted">Government Subsidy (<?= $subsidy_percentage ?>%)</small>
                                <div><strong>- MWK <?= number_format($subsidy_amount, 0) ?></strong></div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-0">
                                <h5 class="text-success mb-0">Total to Pay</h5>
                                <h3 class="text-success">MWK <?= number_format($amount_to_pay, 0) ?></h3>
                            </div>
                        </div>
                    </div>

                    <!-- Subsidy Info -->
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Good News!</strong> You're eligible for a <?= $subsidy_percentage ?>% government subsidy on this order.
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="col-lg-8">
                    <div class="card payment-card shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="bi bi-credit-card"></i> Payment Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data">
                                <!-- Payment Method Selection -->
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Select Payment Method <span class="text-danger">*</span></label>
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('Stripe')">
                                                <div class="card-body text-center">
                                                    <div class="payment-method-icon text-primary">
                                                        <i class="bi bi-credit-card"></i>
                                                    </div>
                                                    <h6>Credit/Debit Card</h6>
                                                    <small class="text-muted">via Stripe</small>
                                                    <input type="radio" name="payment_method" value="Stripe" class="form-check-input" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('Airtel Money')">
                                                <div class="card-body text-center">
                                                    <div class="payment-method-icon text-danger">
                                                        <i class="bi bi-phone"></i>
                                                    </div>
                                                    <h6>Airtel Money</h6>
                                                    <input type="radio" name="payment_method" value="Airtel Money" class="form-check-input" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('TNM Mpamba')">
                                                <div class="card-body text-center">
                                                    <div class="payment-method-icon text-primary">
                                                        <i class="bi bi-phone"></i>
                                                    </div>
                                                    <h6>TNM Mpamba</h6>
                                                    <input type="radio" name="payment_method" value="TNM Mpamba" class="form-check-input" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card payment-method-card" onclick="selectPaymentMethod('Bank Transfer')">
                                                <div class="card-body text-center">
                                                    <div class="payment-method-icon text-success">
                                                        <i class="bi bi-bank"></i>
                                                    </div>
                                                    <h6>Bank Transfer</h6>
                                                    <input type="radio" name="payment_method" value="Bank Transfer" class="form-check-input" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment Instructions -->
                                <div id="paymentInstructions" class="alert alert-warning" style="display: none;">
                                    <h6 class="alert-heading"><i class="bi bi-info-circle"></i> Payment Instructions</h6>
                                    <div id="instructionsContent"></div>
                                </div>

                                <!-- Transaction Details (hidden for Stripe) -->
                                <div id="manualPaymentFields">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Amount to Pay <span class="text-danger">*</span></label>
                                            <input type="number" name="amount_paid" class="form-control" 
                                                   value="<?= $amount_to_pay ?>" 
                                                   min="<?= $amount_to_pay ?>" 
                                                   step="0.01" required readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Transaction ID / Reference <span class="text-danger">*</span></label>
                                            <input type="text" name="transaction_id" class="form-control" 
                                                   placeholder="e.g., ATM12345678" required>
                                            <small class="text-muted">Enter the transaction ID from your payment</small>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label">Upload Receipt (Optional)</label>
                                            <input type="file" name="receipt" class="form-control" 
                                                   accept=".pdf,.jpg,.jpeg,.png">
                                            <small class="text-muted">Upload a screenshot or PDF of your payment confirmation</small>
                                        </div>
                                    </div>

                                    <!-- Terms and Conditions -->
                                    <div class="form-check mt-4">
                                        <input type="checkbox" class="form-check-input" id="agreeTerms" required>
                                        <label class="form-check-label" for="agreeTerms">
                                            I confirm that I have made the payment and the details provided are correct.
                                        </label>
                                    </div>

                                    <div class="mt-4">
                                        <button type="submit" class="btn btn-success btn-lg w-100">
                                            <i class="bi bi-check-circle"></i> Confirm Payment
                                        </button>
                                    </div>
                                </div>

                                <!-- Stripe Payment Button -->
                                <div id="stripePaymentButton" style="display: none;">
                                    <div class="mt-4">
                                        <button type="button" id="payWithStripe" class="btn btn-primary btn-lg w-100">
                                            <i class="bi bi-credit-card"></i> Pay with Card (Stripe)
                                        </button>
                                        <div id="stripeLoading" class="text-center mt-3" style="display: none;">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Processing...</span>
                                            </div>
                                            <p class="mt-2">Redirecting to secure payment page...</p>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Help Section -->
                    <div class="card mt-3">
                        <div class="card-body">
                            <h6><i class="bi bi-question-circle"></i> Need Help?</h6>
                            <p class="mb-0">If you're having trouble with payment, contact support at <strong>+265-xxx-xxxx</strong> or email <strong>support@fertilizersystem.mw</strong></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const orderId = <?= $order_id ?>;
const amountToPay = <?= $amount_to_pay ?>;

function selectPaymentMethod(method) {
    // Remove selected class from all cards
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selected class to clicked card
    event.currentTarget.classList.add('selected');
    
    // Check the radio button
    event.currentTarget.querySelector('input[type="radio"]').checked = true;
    
    // Show/hide appropriate payment sections
    if (method === 'Stripe') {
        document.getElementById('paymentInstructions').style.display = 'none';
        document.getElementById('manualPaymentFields').style.display = 'none';
        document.getElementById('stripePaymentButton').style.display = 'block';
        showStripeInstructions();
    } else {
        document.getElementById('manualPaymentFields').style.display = 'block';
        document.getElementById('stripePaymentButton').style.display = 'none';
        showPaymentInstructions(method);
    }
}

function showStripeInstructions() {
    const instructionsDiv = document.getElementById('paymentInstructions');
    const contentDiv = document.getElementById('instructionsContent');
    
    contentDiv.innerHTML = `
        <p><strong>Pay securely with your credit or debit card</strong></p>
        <ul>
            <li>Click the "Pay with Card" button below</li>
            <li>You'll be redirected to Stripe's secure payment page</li>
            <li>Enter your card details</li>
            <li>Your payment will be processed instantly</li>
        </ul>
        <p class="mb-0"><i class="bi bi-shield-check"></i> Your payment information is secure and encrypted.</p>
    `;
    
    instructionsDiv.classList.remove('alert-warning');
    instructionsDiv.classList.add('alert-info');
    instructionsDiv.style.display = 'block';
}

function showPaymentInstructions(method) {
    const instructionsDiv = document.getElementById('paymentInstructions');
    const contentDiv = document.getElementById('instructionsContent');
    
    let instructions = '';
    
    switch(method) {
        case 'Airtel Money':
            instructions = `
                <ol>
                    <li>Dial <strong>*115#</strong> on your phone</li>
                    <li>Select <strong>Make Payment</strong></li>
                    <li>Enter Merchant Code: <strong>123456</strong></li>
                    <li>Enter Amount: <strong>MWK ${amountToPay.toLocaleString('en-US', {minimumFractionDigits: 0})}</strong></li>
                    <li>Confirm and enter your PIN</li>
                    <li>Copy the transaction ID from the confirmation message</li>
                </ol>
            `;
            break;
        case 'TNM Mpamba':
            instructions = `
                <ol>
                    <li>Dial <strong>*444#</strong> on your phone</li>
                    <li>Select <strong>Send Money</strong></li>
                    <li>Enter Account: <strong>0888-123-456</strong></li>
                    <li>Enter Amount: <strong>MWK ${amountToPay.toLocaleString('en-US', {minimumFractionDigits: 0})}</strong></li>
                    <li>Confirm and enter your PIN</li>
                    <li>Copy the transaction ID from the confirmation message</li>
                </ol>
            `;
            break;
        case 'Bank Transfer':
            instructions = `
                <p><strong>Bank Account Details:</strong></p>
                <ul>
                    <li>Bank: <strong>National Bank of Malawi</strong></li>
                    <li>Account Name: <strong>Fertilizer Management System</strong></li>
                    <li>Account Number: <strong>1234567890</strong></li>
                    <li>Branch: <strong>Lilongwe</strong></li>
                    <li>Amount: <strong>MWK ${amountToPay.toLocaleString('en-US', {minimumFractionDigits: 0})}</strong></li>
                </ul>
                <p class="mb-0">After transfer, enter your transaction reference number below.</p>
            `;
            break;
    }
    
    contentDiv.innerHTML = instructions;
    instructionsDiv.classList.remove('alert-info');
    instructionsDiv.classList.add('alert-warning');
    instructionsDiv.style.display = 'block';
}

// Stripe Payment Handler
document.getElementById('payWithStripe')?.addEventListener('click', function() {
    const btn = this;
    const loading = document.getElementById('stripeLoading');
    
    btn.disabled = true;
    loading.style.display = 'block';
    
    // Call Stripe checkout creation endpoint
    fetch('create_stripe_checkout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            order_id: orderId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Error: ' + data.error);
            btn.disabled = false;
            loading.style.display = 'none';
        } else {
            // Redirect to Stripe checkout
            window.location.href = data.url;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to initialize payment. Please try again.');
        btn.disabled = false;
        loading.style.display = 'none';
    });
});

// Pre-select payment method if radio is already checked
document.addEventListener('DOMContentLoaded', function() {
    const checkedRadio = document.querySelector('input[name="payment_method"]:checked');
    if (checkedRadio) {
        checkedRadio.closest('.payment-method-card').classList.add('selected');
        const method = checkedRadio.value;
        if (method === 'Stripe') {
            document.getElementById('manualPaymentFields').style.display = 'none';
            document.getElementById('stripePaymentButton').style.display = 'block';
            showStripeInstructions();
        } else {
            showPaymentInstructions(method);
        }
    }
});
</script>
</body>
</html>