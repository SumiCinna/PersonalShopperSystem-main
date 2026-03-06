<?php
// core/cashier/claim_order.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);
    $cashier_id = $_SESSION['user_id'];

    // Lock the order to this cashier and change status to processing
    $query = "UPDATE orders SET order_status = 'processing', processed_by = ? WHERE order_id = ? AND order_status = 'pending'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $cashier_id, $order_id);
    
    if ($stmt->execute()) {
        // Successfully claimed, redirect to POS Terminal
        header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
    } else {
        $_SESSION['error'] = "Could not claim this order. Someone else might be processing it.";
        header("Location: ../../modules/cashier/dashboard.php");
    }
    $stmt->close();
    exit();
} else {
    header("Location: ../../modules/cashier/dashboard.php");
    exit();
}
?>