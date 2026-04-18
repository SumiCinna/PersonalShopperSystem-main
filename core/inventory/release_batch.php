<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../modules/inventory/stock_management.php");
    exit();
}

$batch_id = (int)($_POST['batch_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$qty = (int)($_POST['qty'] ?? 0);
$userId = (int)$_SESSION['user_id'];

if ($batch_id <= 0 || $product_id <= 0 || $qty <= 0) {
    header("Location: ../../modules/inventory/stock_management.php?error=Invalid+request+data.");
    exit();
}

try {
    $conn->begin_transaction();

    // Verify batch is still pending
    $checkStmt = $conn->prepare("SELECT status, batch_number FROM product_batches WHERE batch_id = ? AND product_id = ?");
    $checkStmt->bind_param("ii", $batch_id, $product_id);
    $checkStmt->execute();
    $batch = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$batch) {
        throw new Exception("Batch not found.");
    }
    if ($batch['status'] !== 'Pending') {
        throw new Exception("This batch has already been " . $batch['status'] . ".");
    }

    // 1. Update batch status to Released
    $updBatch = $conn->prepare("UPDATE product_batches SET status = 'Released' WHERE batch_id = ?");
    $updBatch->bind_param("i", $batch_id);
    $updBatch->execute();
    $updBatch->close();

    // 2. Fetch old stock for activity log
    $oldStockStmt = $conn->prepare("SELECT stock, name FROM products WHERE product_id = ?");
    $oldStockStmt->bind_param('i', $product_id);
    $oldStockStmt->execute();
    $oldStockResult = $oldStockStmt->get_result()->fetch_assoc();
    $oldStock = (int)($oldStockResult['stock'] ?? 0);
    $productName = $oldStockResult['name'] ?? 'Unknown';
    $oldStockStmt->close();

    // 3. Update main products stock
    $updStock = $conn->prepare("UPDATE products SET stock = stock + ? WHERE product_id = ?");
    $updStock->bind_param("ii", $qty, $product_id);
    $updStock->execute();
    $updStock->close();

    // 4. Insert Inventory Log
    $invRemarks = 'Released batch ' . $batch['batch_number'] . ' to store';
    $insertInvLog = $conn->prepare("INSERT INTO inventory_logs (product_id, user_id, quantity_added, remarks) VALUES (?, ?, ?, ?)");
    $insertInvLog->bind_param("iiis", $product_id, $userId, $qty, $invRemarks);
    $insertInvLog->execute();
    $insertInvLog->close();

    // 5. Insert Activity Log manually (if exists)
    $newStock = $oldStock + $qty;
    $auditAction = 'update';
    $auditField = 'stock';
    $insertAuditLog = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, product_name, field_changed, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if ($insertAuditLog) {
        $oldStockStr = (string)$oldStock;
        $newStockStr = (string)$newStock;
        $insertAuditLog->bind_param('isissss', $userId, $auditAction, $product_id, $productName, $auditField, $oldStockStr, $newStockStr);
        $insertAuditLog->execute();
        $insertAuditLog->close();
    }

    $conn->commit();

    header("Location: ../../modules/inventory/stock_management.php?status=Pending&msg=" . urlencode("Successfully released Batch " . $batch['batch_number'] . ". Added $qty units to store."));
    exit();

} catch (Exception $e) {
    $conn->rollback();
    header("Location: ../../modules/inventory/stock_management.php?error=" . urlencode($e->getMessage()));
    exit();
}
