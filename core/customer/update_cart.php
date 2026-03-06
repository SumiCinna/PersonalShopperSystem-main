<?php
// core/customer/update_cart.php
session_start();
require_once '../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $cart_id = intval($data['cart_id']);
    $action = $data['action']; // 'increase' or 'decrease'
    $user_id = $_SESSION['user_id'];

    // Get current cart info and product stock
    $stmt = $conn->prepare("SELECT c.quantity, p.stock FROM cart c JOIN products p ON c.product_id = p.product_id WHERE c.cart_id = ? AND c.user_id = ?");
    $stmt->bind_param("ii", $cart_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $new_qty = $row['quantity'];
        
        if ($action === 'increase' && $new_qty < $row['stock']) {
            $new_qty++;
        } elseif ($action === 'decrease' && $new_qty > 1) {
            $new_qty--;
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot update quantity. Check stock limits.']);
            exit();
        }

        $update = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
        $update->bind_param("ii", $new_qty, $cart_id);
        $update->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
    }
}
?>