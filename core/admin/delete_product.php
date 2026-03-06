<?php
session_start();
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['id'])) {
        $product_id = intval($data['id']);

        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Product successfully deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: Could not delete product.']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'No Product ID provided.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>