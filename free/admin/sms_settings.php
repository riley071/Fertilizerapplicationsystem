<?php
session_start();
require_once('../includes/db.php');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$success = $error = "";

// Handle SMS settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = trim($_POST['api_key']);
    $username = trim($_POST['username']);
    $sender_id = trim($_POST['sender_id']);
    $enabled = isset($_POST['sms_enabled']) ? 1 : 0;
    
    // Save to database or config file
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES 
        ('sms_api_key', ?),
        ('sms_username', ?),
        ('sms_sender_id', ?),
        ('sms_enabled', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param("sssi", $api_key, $username, $sender_id, $enabled);
    
    if ($stmt->execute()) {
        $success = "SMS settings saved successfully!";
    } else {
        $error = "Failed to save settings.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SMS Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h3>SMS Notification Settings</h3>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="mb-3">
            <label class="form-label">API Key</label>
            <input type="text" name="api_key" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Sender ID</label>
            <input type="text" name="sender_id" class="form-control" value="FertilizerSys">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="sms_enabled" class="form-check-input" id="smsEnabled" checked>
            <label class="form-check-label" for="smsEnabled">Enable SMS Notifications</label>
        </div>
        <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
    
    <hr class="my-4">
    
    <h5>Test SMS</h5>
    <form method="POST" action="test_sms.php">
        <div class="mb-3">
            <label class="form-label">Test Phone Number</label>
            <input type="text" name="test_phone" class="form-control" placeholder="+265999123456">
        </div>
        <button type="submit" class="btn btn-success">Send Test SMS</button>
    </form>
</div>
</body>
</html>