<?php
session_start();
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {        
    echo json_encode(['success' => false, 'message' => 'Unauthorized Access']); 
    exit();
}

// --- HELPER: Insert a single activity log row ---
function log_activity($conn, $user_id, $action, $product_id, $product_name, $field = null, $old = null, $new = null) {
    // If product_name column doesn't exist, this might fail, let's omit product_name if not needed or handle correctly.
    // In delete_product.php, it uses product_name.
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, product_name, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isissss", $user_id, $action, $product_id, $product_name, $field, $old, $new);
        $stmt->execute();
        $stmt->close();
    } else {
        // Fallback without product_name if schema differs
        $stmt2 = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt2) {
            $stmt2->bind_param("isssss", $user_id, $action, $product_id, $field, $old, $new);
            $stmt2->execute();
            $stmt2->close();
        }
    }
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID is required']);
    exit();
}

$productId = intval($data['id']);

// Fetch old status and name
$old_status = '';
$product_name = '';
$stmt_get = $conn->prepare("SELECT name, status FROM products WHERE product_id = ?");
$stmt_get->bind_param("i", $productId);
$stmt_get->execute();
$res = $stmt_get->get_result();
if ($row = $res->fetch_assoc()) {
    $old_status = $row['status'];
    $product_name = $row['name'];
}
$stmt_get->close();


$stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE product_id = ?");
$stmt->bind_param("i", $productId);

if ($stmt->execute()) {
    log_activity($conn, $_SESSION['user_id'], 'update', $productId, $product_name, 'status', $old_status, 'inactive');
    echo json_encode(['success' => true, 'message' => 'Product status updated to inactive.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update product status: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>