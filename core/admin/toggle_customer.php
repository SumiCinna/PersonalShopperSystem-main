<?php
// core/admin/toggle_customer.php
session_start();
require_once '../../config/config.php';

// Strict Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = intval($_POST['user_id']);
    $current_status = $_POST['current_status'];

    // Flip the status
    $new_status = ($current_status === 'active') ? 'suspended' : 'active';

    // Make sure we are only affecting customers, just to be safe!
    $update = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ? AND role = 'customer'");
    $update->bind_param("si", $new_status, $target_user_id);
    
    if ($update->execute()) {
        $action_text = ($new_status === 'suspended') ? 'suspended' : 'restored';
        $_SESSION['success'] = "Customer account has been successfully $action_text.";
    } else {
        $_SESSION['error'] = "System Error: Could not update customer status.";
    }
    $update->close();
    
    header("Location: ../../modules/admin/customers.php");
    exit();
}
?>