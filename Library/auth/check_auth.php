<?php
require_once __DIR__ . '/../config/config.php';

// Allow guest users to access client pages
$is_guest = (isset($_SESSION['role']) && $_SESSION['role'] == 'guest');

// Check if user is logged in (or is guest)
if (!isset($_SESSION['user_id']) && !$is_guest) {
    header("Location: " . dirname($_SERVER['PHP_SELF']) . "/../auth/login.php");
    exit();
}

// Guest users can only access client pages
if ($is_guest) {
    $current_folder = basename(dirname($_SERVER['SCRIPT_FILENAME']));
    if ($current_folder == 'admin') {
        header("Location: " . dirname($_SERVER['PHP_SELF']) . "/../auth/login.php");
        exit();
    }
}

// Check session timeout (30 minutes) - skip for guests
if (!$is_guest && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/functions.php';
    checkSessionTimeout($conn, 30);
}

// Check if specific role is required
function checkRole($required_role) {
    // Allow guest to pass as client
    if ($required_role == 'client' && isset($_SESSION['role']) && $_SESSION['role'] == 'guest') {
        return;
    }
    
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header("Location: " . dirname($_SERVER['PHP_SELF']) . "/../auth/login.php");
        exit();
    }
}

// Get current user info
function getCurrentUser() {
    global $conn;
    
    // Return guest user info
    if (isset($_SESSION['role']) && $_SESSION['role'] == 'guest') {
        return [
            'id' => 0,
            'username' => 'guest',
            'full_name' => 'Guest User',
            'email' => 'guest@library.system',
            'role' => 'guest'
        ];
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?>