<?php
// stripe_webhook.php
require '../vendor/autoload.php';
include('../includes/db.php');

\Stripe\Stripe::setApiKey('sk_test_YOUR_SECRET_KEY');

// Get the raw body and signature header
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$endpoint_secret = 'whsec_YOUR_ENDPOINT_SECRET'; // set from Stripe webhook settings

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
} catch(\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch(\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

if ($event->type === 'checkout.session.completed') {
    $session = $event->data->object;

    // read metadata
    $order_id = (int)($session->metadata->order_id ?? 0);

    // Only proceed if order_id present
    if ($order_id) {
        // retrieve payment intent to get id/amount
        $payment_intent_id = $session->payment_intent ?? null;

        // compute subsidy and save payment as in success handler
        $ordSt = $conn->prepare("SELECT o.*, fert.price AS price_per_unit FROM orders o JOIN fertilizers fert ON o.fertilizer_id = fert.id WHERE o.id = ?");
        $ordSt->bind_param("i", $order_id);
        $ordSt->execute();
        $order = $ordSt->get_result()->fetch_assoc();
        $ordSt->close();
        if ($order) {
            // subsidy
            $sub = $conn->query("SELECT percentage FROM subsidy_policies WHERE active=1 LIMIT 1")->fetch_assoc();
            $subPct = $sub ? (float)$sub['percentage'] : 0.0;
            $total_price = (float)$order['total_price'];
            $subsidy_amount = round($total_price * ($subPct / 100), 2);
            $amount_paid = round($total_price - $subsidy_amount, 2);

            // save payment
            $ins = $conn->prepare("INSERT INTO payments (order_id, total_price, subsidy, amount_paid, payment_method, transaction_id) VALUES (?, ?, ?, ?, ?, ?)");
            $method = 'Stripe';
            $ins->bind_param("idddss", $order_id, $total_price, $subsidy_amount, $amount_paid, $method, $payment_intent_id);
            $ins->execute();
            $ins->close();

            // update order status
            $u = $conn->prepare("UPDATE orders SET status = 'Approved' WHERE id = ?");
            $u->bind_param("i", $order_id);
            $u->execute();
            $u->close();
        }
    }
}

http_response_code(200);
