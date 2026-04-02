<?php
session_start();
include('../includes/db.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get the code from POST request
$code = $_POST['code'] ?? '';

if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'No code provided']);
    exit();
}

// Query the database for the QR code
$stmt = $conn->prepare("
    SELECT q.*, u.full_name as created_by_name,
           (SELECT COUNT(*) FROM certificate_applications ca WHERE ca.qr_link_id = q.id) as usage_count
    FROM qr_links q
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.code = ?
    LIMIT 1
");
$stmt->bind_param("s", $code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        'success' => false, 
        'message' => 'QR code not found in system'
    ]);
    exit();
}

$qr = $result->fetch_assoc();
$stmt->close();

// Check validity
$is_valid = true;
$reason = '';

// Check if active
if (!$qr['active']) {
    $is_valid = false;
    $reason = 'This QR code has been deactivated';
}

// Check if expired
if ($qr['expires_at'] && strtotime($qr['expires_at']) < time()) {
    $is_valid = false;
    $reason = 'This QR code has expired';
}

// Format purpose for display
$qr['purpose'] = ucwords(str_replace('_', ' ', $qr['purpose']));

// Prepare response
$response = [
    'success' => true,
    'qr' => [
        'id' => $qr['id'],
        'code' => $qr['code'],
        'target_url' => $qr['target_url'],
        'purpose' => $qr['purpose'],
        'active' => (bool)$qr['active'],
        'created_at' => $qr['created_at'],
        'expires_at' => $qr['expires_at'],
        'created_by_name' => $qr['created_by_name'] ?? 'System',
        'usage_count' => (int)$qr['usage_count'],
        'is_valid' => $is_valid,
        'reason' => $reason
    ]
];

echo json_encode($response);
?>