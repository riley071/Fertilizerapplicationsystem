<?php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}
?>
