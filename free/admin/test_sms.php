<?php
session_start();
require_once('../includes/db.php');          // adjust path if needed
require_once('../includes/sms_helper.php'); // include your SMS class

// Admin-only access (optional)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = "";
$response_data = "";

// Handle SMS Test Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['test_phone'])) {
        $error = "Please enter a phone number.";
    } else {

        $phone = trim($_POST['test_phone']);
        $sms = new SMSNotification();

        // Send test SMS
        $result = $sms->sendSMS($phone, "Test SMS from FertilizerSys. If you received this, SMS integration works!");

        if ($result['success']) {
            $success = "SMS successfully sent to $phone!";
        } else {
            $error = "Failed to send SMS. HTTP Code: " . $result['http_code'];
        }

        // Capture raw response for debugging
        $response_data = json_encode($result['response'], JSON_PRETTY_PRINT);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Send Test SMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">

    <h3>Test SMS Sending</h3>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Phone Number</label>
            <input type="text" name="test_phone" class="form-control" placeholder="+265999123456" required>
        </div>

        <button type="submit" class="btn btn-primary">Send Test SMS</button>
    </form>

    <?php if ($response_data): ?>
        <hr class="my-4">
        <h5>API Response</h5>
        <pre class="bg-light p-3 border"><?= $response_data ?></pre>
    <?php endif; ?>

</div>
</body>
</html>
