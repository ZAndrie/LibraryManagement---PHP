<?php
require_once '../auth/check_auth.php';
require_once '../includes/functions.php';
checkRole('admin');

header('Content-Type: application/json');

$patron_id = isset($_GET['patron_id']) ? intval($_GET['patron_id']) : 0;

if ($patron_id > 0) {
    $result = canPatronBorrow($conn, $patron_id);
    echo json_encode($result);
} else {
    echo json_encode([
        'can_borrow' => false,
        'current_count' => 0,
        'max_allowed' => 0,
        'message' => 'Invalid patron'
    ]);
}
?>