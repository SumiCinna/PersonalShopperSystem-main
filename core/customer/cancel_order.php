<?php
// core/customer/cancel_order.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $user_id = $_SESSION['user_id'];

    // 1. Verify order exists, belongs to user, and is pending
    $check_query = "SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? AND order_status = 'pending'";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Order cannot be cancelled (it may have already been processed).";
        header("Location: ../../modules/customer/order_details.php?id=" . $order_id);
        exit();
    }
    $stmt->close();

    // 2. Start Transaction
    $conn->begin_transaction();

    try {
        // 3. Restore Stock (Fetch items and add quantity back to products)
        $items_query = "SELECT product_id, quantity FROM order_items WHERE order_id = ?";
        $items_stmt = $conn->prepare($items_query);
        $items_stmt->bind_param("i", $order_id);
        $items_stmt->execute();
        $items_result = $items_stmt->get_result();

        $update_stock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");

        while ($item = $items_result->fetch_assoc()) {
            $update_stock->bind_param("ii", $item['quantity'], $item['product_id']);
            $update_stock->execute();
        }
        $items_stmt->close();
        $update_stock->close();

        // 4. Update Order Status
        $update_order = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE order_id = ?");
        $update_order->bind_param("i", $order_id);
        $update_order->execute();
        $update_order->close();

        $conn->commit();
        $_SESSION['success'] = "Order has been cancelled successfully.";

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "System error: Could not cancel order.";
    }

    header("Location: ../../modules/customer/order_details.php?id=" . $order_id);
    exit();
} else {
    header("Location: ../../modules/customer/orders.php");
    exit();
}
?>