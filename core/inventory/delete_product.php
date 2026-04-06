<?php
// core/inventory/delete_product.php
session_start();
require_once '../../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// --- HELPER: Insert a single activity log row ---
function log_activity($conn, $user_id, $action, $product_id, $product_name, $field = null, $old = null, $new = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, product_name, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $user_id, $action, $product_id, $product_name, $field, $old, $new);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['id'])) {
        $product_id = intval($data['id']);

        // --- Fetch product name BEFORE deleting so we can log it ---
        $name_stmt = $conn->prepare("SELECT name, sku FROM products WHERE product_id = ?");
        $name_stmt->bind_param("i", $product_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result()->fetch_assoc();
        $name_stmt->close();

        $product_name = $name_result ? $name_result['name'] : "Unknown Product (ID #$product_id)";
        $product_sku  = $name_result ? $name_result['sku']  : '—';

        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);

        if ($stmt->execute()) {
            // --- AUDIT LOG: Product deleted ---
            log_activity(
                $conn,
                $_SESSION['user_id'],
                'delete',
                $product_id,
                $product_name,
                null,
                "SKU: $product_sku",
                null
            );

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