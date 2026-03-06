<?php
// core/customer/toggle_wishlist.php
session_start();
require_once '../../config/config.php';

header('Content-Type: application/json');

// Ensure the user is logged in as a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['status' => 'error', 'message' => 'Please log in to save favorites.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Read JSON data sent by JavaScript
    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = intval($data['product_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($product_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product.']);
        exit();
    }

    // Check if the item is already in the wishlist
    $check_stmt = $conn->prepare("SELECT wishlist_id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // It's already favorited! So we REMOVE it (Un-favorite)
        $delete_stmt = $conn->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        echo json_encode(['status' => 'removed', 'message' => 'Removed from Wishlist']);
    } else {
        // Not in wishlist! So we ADD it (Favorite)
        $insert_stmt = $conn->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
        $insert_stmt->bind_param("ii", $user_id, $product_id);
        $insert_stmt->execute();
        $insert_stmt->close();
        
        echo json_encode(['status' => 'added', 'message' => 'Added to Wishlist']);
    }
    
    $check_stmt->close();
    $conn->close();
}
?>