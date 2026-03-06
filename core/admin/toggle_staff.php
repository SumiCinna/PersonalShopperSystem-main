<?php
// core/admin/toggle_staff.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target_user_id = intval($_POST['user_id']);
    $current_status = $_POST['current_status'];

    // Security check: Don't let an admin suspend themselves
    if ($target_user_id === $_SESSION['user_id']) {
        header("Location: ../../modules/admin/staff.php");
        exit();
    }

    $new_status = ($current_status === 'active') ? 'suspended' : 'active';

    $update = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $update->bind_param("si", $new_status, $target_user_id);
    
    if ($update->execute()) {
        $action_text = ($new_status === 'suspended') ? 'suspended' : 'restored';
        $_SESSION['success'] = "Staff member access has been $action_text.";
    }
    $update->close();
    
    header("Location: ../../modules/admin/staff.php");
    exit();
}
?>