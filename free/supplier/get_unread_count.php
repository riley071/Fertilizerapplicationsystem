<?php
session_start();
include('../includes/db.php');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['count' => 0]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$count = $conn->query("SELECT COUNT(*) as count FROM messages WHERE recipient_id = $user_id AND is_read = 0")->fetch_assoc()['count'];

echo json_encode(['count' => $count]);
?>