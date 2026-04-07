<?php
// core/cashier/claim_order.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id   = intval($_POST['order_id']);
    $cashier_id = $_SESSION['user_id'];

    // First, verify the order actually exists and is still pending
    $check = $conn->prepare("SELECT order_id, order_status, processed_by FROM orders WHERE order_id = ?");
    $check->bind_param("i", $order_id);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$row) {
        $_SESSION['error'] = "Order not found.";
        header("Location: ../../modules/cashier/pos.php");
        exit();
    }

    // If already claimed by THIS cashier (e.g. double-click), just redirect them to it
    if ($row['processed_by'] == $cashier_id && in_array($row['order_status'], ['processing', 'ready'])) {
        header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
        exit();
    }

    // If claimed by a DIFFERENT cashier, block
    if (!is_null($row['processed_by']) && $row['processed_by'] != $cashier_id) {
        $_SESSION['error'] = "This order is already being processed by another cashier.";
        header("Location: ../../modules/cashier/pos.php");
        exit();
    }

    // If not pending, block
    if ($row['order_status'] !== 'pending') {
        $_SESSION['error'] = "This order is no longer in the pending queue (status: " . $row['order_status'] . ").";
        header("Location: ../../modules/cashier/pos.php");
        exit();
    }

    // Lock the order to this cashier and change status to processing
    $stmt = $conn->prepare("UPDATE orders SET order_status = 'processing', processed_by = ? WHERE order_id = ? AND order_status = 'pending'");
    $stmt->bind_param("ii", $cashier_id, $order_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        // Successfully claimed
        header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
    } else {
        // Race condition: another cashier claimed it between our check and update
        $_SESSION['error'] = "Could not claim this order. It may have just been taken by another cashier.";
        header("Location: ../../modules/cashier/pos.php");
    }
    exit();

} else {
    header("Location: ../../modules/cashier/pos.php");
    exit();
}
?>