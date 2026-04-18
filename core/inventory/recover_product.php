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
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, product_name, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("isissss", $user_id, $action, $product_id, $product_name, $field, $old, $new);
        $stmt->execute();
        $stmt->close();
    } else {
        // Fallback without product_name
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

$old_status = '';
$product_name = '';
$stock = 0;

$stmt_get = $conn->prepare("SELECT name, status, stock FROM products WHERE product_id = ?");
$stmt_get->bind_param("i", $productId);
$stmt_get->execute();
$res = $stmt_get->get_result();
if ($row = $res->fetch_assoc()) {
    $old_status = $row['status'];
    $product_name = $row['name'];
    $stock = (int)$row['stock'];
}
$stmt_get->close();

if ($old_status !== 'archived') {
    echo json_encode(['success' => false, 'message' => 'Product is not archived.']);
    exit();
}

$new_status = ($stock > 0) ? 'active' : 'inactive';

$stmt = $conn->prepare("UPDATE products SET status = ? WHERE product_id = ?");
$stmt->bind_param("si", $new_status, $productId);

if ($stmt->execute()) {
    log_activity($conn, $_SESSION['user_id'], 'restore', $productId, $product_name, 'status', $old_status, $new_status);
    echo json_encode(['success' => true, 'message' => 'Product recovered as ' . $new_status, 'new_status' => $new_status]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to recover: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>