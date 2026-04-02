<?php
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

function ensure_role($required_role) {
    if ($_SESSION['role'] !== $required_role) {
        header("Location: ../login.php");
        exit();
    }
}
?>
