<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    logActivity($conn, $_SESSION['user_id'], 'LOGOUT', 'User logged out');
    deleteUserSession($conn, session_id());
}

session_unset();
session_destroy();
header("Location: ../auth/login.php");
exit();
?>