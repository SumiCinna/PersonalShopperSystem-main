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
    $name_stmt = $conn->prepare("SELECT name, sku, status FROM products WHERE product_id = ?");
        $name_stmt->bind_param("i", $product_id);
        $name_stmt->execute();
        $name_result = $name_stmt->get_result()->fetch_assoc();
        $name_stmt->close();

        $product_name = $name_result ? $name_result['name'] : "Unknown Product (ID #$product_id)";
        $product_sku  = $name_result ? $name_result['sku']  : '—';
    $old_status   = $name_result ? ($name_result['status'] ?? 'unknown') : 'unknown';

        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $deleted = $stmt->execute();
        $delete_error_no = $stmt->errno;
        $delete_error_msg = $stmt->error;
        $stmt->close();

        if ($deleted) {
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
            exit();
        }

        // FK constraint (1451): product already referenced in orders/logs.
        // Fallback to soft delete so product is hidden from customers but history remains intact.
        if ($delete_error_no === 1451) {
            $soft_stmt = $conn->prepare("UPDATE products SET status = 'inactive', stock = 0 WHERE product_id = ? LIMIT 1");
            $soft_stmt->bind_param("i", $product_id);

            if ($soft_stmt->execute()) {
                $affected = $soft_stmt->affected_rows;
                $soft_stmt->close();

                log_activity(
                    $conn,
                    $_SESSION['user_id'],
                    'update',
                    $product_id,
                    $product_name,
                    'Status',
                    $old_status,
                    'inactive (auto due to existing references)'
                );

                if ($affected > 0) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Product is linked to existing records and cannot be permanently deleted. It was set to inactive with zero stock instead.'
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Product is already inactive (or unchanged) and cannot be permanently deleted because it is linked to existing records.'
                    ]);
                }
                exit();
            }

            $soft_error = $soft_stmt->error;
            $soft_stmt->close();
            echo json_encode(['success' => false, 'message' => 'Database error: Could not deactivate linked product. ' . $soft_error]);
            exit();
        }

        echo json_encode(['success' => false, 'message' => 'Database error: Could not delete product. ' . $delete_error_msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No Product ID provided.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}

$conn->close();
?>