<?php
session_start();
include('../includes/db.php');
require_once '../stripe/init.php'; // Stripe PHP SDK

// Stripe Secret Key
\Stripe\Stripe::setApiKey('');

// Ensure logged in as supplier
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supplier') {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Get supplier_id
$stmt = $conn->prepare("SELECT id FROM suppliers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$supplier = $stmt->get_result()->fetch_assoc();
$stmt->close();

$supplier_id = $supplier ? (int) $supplier['id'] : null;

if (!$supplier_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Supplier profile not found']);
    exit;
}

// Get order_id from request
$data = json_decode(file_get_contents("php://input"), true);
$order_id = isset($data['order_id']) ? (int)$data['order_id'] : 0;

if ($order_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

// Fetch order with payment info
$stmt = $conn->prepare("
    SELECT o.id, o.total_price, o.quantity, o.status,
           f.name AS fertilizer_name,
           p.payment_status
    FROM orders o
    JOIN fertilizers f ON o.fertilizer_id = f.id
    LEFT JOIN payments p ON o.id = p.order_id
    WHERE o.id = ? AND o.supplier_id = ?
");
$stmt->bind_param("ii", $order_id, $supplier_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Order not found or unauthorized']);
    exit;
}

// Check if order can be paid
if ($order['payment_status'] === 'Completed') {
    http_response_code(400);
    echo json_encode(['error' => 'Order already paid']);
    exit;
}

if (!in_array($order['status'], ['Approved', 'Dispatched', 'Delivered'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order must be approved before payment']);
    exit;
}

// Calculate amounts with subsidy
$subsidy_percentage = 20;
$subsidy_amount = ($order['total_price'] * $subsidy_percentage) / 100;
$amount_to_pay = $order['total_price'] - $subsidy_amount;

// Convert MWK to USD for Stripe (1700 MWK = 1 USD)
$conversion_rate = 1700;
$price_usd = round($amount_to_pay / $conversion_rate, 2);
$price_cents = intval($price_usd * 100);

// Ensure minimum Stripe amount (50 cents)
if ($price_cents < 50) {
    $price_cents = 50;
}

// Create Stripe checkout session
header('Content-Type: application/json');
try {
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => 'usd',
                'product_data' => [
                    'name' => 'Fertilizer Order #' . $order_id,
                    'description' => $order['fertilizer_name'] . ' (' . $order['quantity'] . ' units)',
                ],
                'unit_amount' => $price_cents,
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => 'http://localhost/Fertilizerapplicationsystem/free/supplier/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => 'http://localhost/Fertilizerapplicationsystem/free/supplier/payment.php?order_id=' . $order_id,
        'metadata' => [
            'order_id' => $order_id,
            'supplier_id' => $supplier_id,
            'supplier_user_id' => $user_id,
            'total_mwk' => $order['total_price'],
            'subsidy_mwk' => $subsidy_amount,
            'amount_paid_mwk' => $amount_to_pay,
        ]
    ]);

    echo json_encode([
        'id' => $checkout_session->id, 
        'url' => $checkout_session->url
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>