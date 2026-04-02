<?php
session_start();
include('db.php');

// Log the logout action
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, 'User logged out', ?)");
    $stmt->bind_param("is", $user_id, $ip);
    $stmt->execute();
    $stmt->close();
}

// Clear all session variables
$_SESSION = [];

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ../login.php');
exit();