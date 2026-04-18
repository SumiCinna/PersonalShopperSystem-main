<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header('Location: ../../inventory-login.php');
    exit();
}

function redirect_with_message(string $path, string $message, string $type = 'ok'): void {
    header('Location: ' . $path . (str_contains($path, '?') ? '&' : '?') . 'msg=' . urlencode($message) . '&type=' . urlencode($type));
    exit();
}

function table_exists(mysqli $conn, string $tableName): bool {
    $safe = $conn->real_escape_string($tableName);
    $res = $conn->query("SHOW TABLES LIKE '{$safe}'");
    return $res && $res->num_rows > 0;
}

$requiredTables = [
    'suppliers',
    'purchase_orders',
    'purchase_order_items',
    'po_receivings',
    'po_receiving_items',
    'supplier_returns'
];

foreach ($requiredTables as $table) {
    if (!table_exists($conn, $table)) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', "Missing table: {$table}. Run database/po_bo_tables.sql first.", 'error');
    }
}

$action = $_POST['action'] ?? '';
$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_message('../../modules/inventory/purchase_orders.php', 'Invalid request method.', 'error');
}

if ($action === 'create_po') {
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $expectedDelivery = trim($_POST['expected_delivery'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (strlen($notes) > 100) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'PO Notes cannot exceed 100 characters.', 'error');
    }

    if ($expectedDelivery !== '') {
        try {
            $today = new DateTime('now', new DateTimeZone('Asia/Manila'));
            $today->setTime(0, 0, 0);
            
            $deliveryDate = new DateTime($expectedDelivery, new DateTimeZone('Asia/Manila'));
            $deliveryDate->setTime(0, 0, 0);

            if ($deliveryDate < $today) {
                redirect_with_message('../../modules/inventory/purchase_orders.php', 'Expected delivery date cannot be in the past.', 'error');
            }
        } catch (Exception $e) {
            redirect_with_message('../../modules/inventory/purchase_orders.php', 'Invalid expected delivery date format.', 'error');
        }
    }

    $productIds = $_POST['product_id'] ?? [];
    $orderTypes = $_POST['order_type'] ?? [];
    $orderedQtys = $_POST['ordered_qty'] ?? [];
    $unitCosts = $_POST['unit_cost'] ?? [];

    if ($supplierId <= 0) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Please select a supplier.', 'error');
    }

    if (!is_array($productIds) || count($productIds) === 0) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Please add at least one item to the PO.', 'error');
    }

    $checkSupplier = $conn->prepare("SELECT supplier_id FROM suppliers WHERE supplier_id = ? AND status = 'active' LIMIT 1");
    $checkSupplier->bind_param('i', $supplierId);
    $checkSupplier->execute();
    $supplierExists = $checkSupplier->get_result()->num_rows > 0;
    $checkSupplier->close();

    if (!$supplierExists) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Selected supplier does not exist or is inactive.', 'error');
    }
    
    // Fetch product details to get pcs_per_box
    $pcsMap = [];
    $productIdsList = array_map('intval', $productIds);
    if (!empty($productIdsList)) {
        $inIds = implode(',', $productIdsList);
        $prodRes = $conn->query("SELECT product_id, pcs_per_box FROM products WHERE product_id IN ($inIds)");
        while ($row = $prodRes->fetch_assoc()) {
            $pcsMap[$row['product_id']] = (int)$row['pcs_per_box'];
        }
    }

    $lines = [];
    $subtotal = 0.0;

    for ($i = 0; $i < count($productIds); $i++) {
        $productId = (int)$productIds[$i];
        $orderType = trim($orderTypes[$i] ?? 'retail');
        $qty = (int)($orderedQtys[$i] ?? 0);
        $cost = (float)($unitCosts[$i] ?? 0);

        if ($productId <= 0 || $qty <= 0 || $cost <= 0) {
            continue;
        }
        
        $pcsPerBox = $pcsMap[$productId] ?? 1;
        $totalPcs = ($orderType === 'wholesale') ? ($qty * $pcsPerBox) : $qty;

        $lineTotal = $qty * $cost;
        $subtotal += $lineTotal;
        $lines[] = [
            'product_id' => $productId,
            'qty' => $qty, // This is boxes if wholesale, pcs if retail
            'total_pcs' => $totalPcs,
            'order_type' => $orderType,
            'cost' => $cost,
            'line_total' => $lineTotal,
        ];
    }

    if (count($lines) === 0) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'No valid line items found. Check quantities and costs.', 'error');
    }

    $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    try {
        $conn->begin_transaction();

        // Check for available credit memo balance for this supplier
        $creditQ = $conn->query("SELECT credit_balance FROM suppliers WHERE supplier_id = {$supplierId}");
        $supplierCredit = $creditQ && $creditQ->num_rows > 0 ? (float)$creditQ->fetch_assoc()['credit_balance'] : 0.00;

        $grandTotal = $subtotal;
        $creditApplied = 0.00;

        // Apply credit safely
        if ($supplierCredit > 0) {
            if ($supplierCredit >= $grandTotal) {
                // Credit memo fully covers this PO
                $creditApplied = $grandTotal;
                $grandTotal = 0;
            } else {
                // Credit memo partially covers this PO
                $creditApplied = $supplierCredit;
                $grandTotal -= $supplierCredit;
            }
        }

        $insertPo = $conn->prepare(
            "INSERT INTO purchase_orders (po_number, supplier_id, status, order_date, expected_delivery, subtotal, tax_amount, grand_total, credit_applied, notes, created_by)  
             VALUES (?, ?, 'pending_approval', CURDATE(), ?, ?, 0.00, ?, ?, ?, ?)" 
        );
        if (!$insertPo) throw new Exception($conn->error);

        $expectedDeliveryValue = $expectedDelivery !== '' ? $expectedDelivery : null;
        
        $insertPo->bind_param('sisdddsi', $poNumber, $supplierId, $expectedDeliveryValue, $subtotal, $grandTotal, $creditApplied, $notes, $userId);
        if (!$insertPo->execute()) throw new Exception($insertPo->error);       

        $poId = (int)$conn->insert_id;
        $insertPo->close();

        // If we applied any credit, deduct it from the supplier's balance and log it
        if ($creditApplied > 0) {
            $conn->query("UPDATE suppliers SET credit_balance = credit_balance - {$creditApplied} WHERE supplier_id = {$supplierId}");
            
            if (table_exists($conn, 'supplier_credit_logs')) {
                $logCredit = $conn->prepare("
                    INSERT INTO supplier_credit_logs (supplier_id, po_id, amount, action, notes) 
                    VALUES (?, ?, ?, 'credit_used', 'Credit applied to new Purchase Order')
                ");
                $logCredit->bind_param('iid', $supplierId, $poId, $creditApplied);
                $logCredit->execute();
                $logCredit->close();
            }
            
            // Add a note to the PO about the credit
            $conn->query("UPDATE purchase_orders SET notes = CONCAT(IFNULL(notes, ''), '\n[Used Credit Memo: PHP " . number_format($creditApplied, 2) . "]') WHERE po_id = {$poId}");
        }        // Use total_pcs mapping in poi.ordered_qty so system tracks physical stock accurately over receiving.
        $insertItem = $conn->prepare(
            "INSERT INTO purchase_order_items (po_id, product_id, ordered_qty, unit_cost, line_total, order_type, box_quantity) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$insertItem) throw new Exception($conn->error);

        foreach ($lines as $line) {
            $ordered_qty = $line['total_pcs']; // Core system uses physical pieces for receiving logic
            $box_qty = $line['order_type'] === 'wholesale' ? $line['qty'] : 0;
            
            $insertItem->bind_param('iiiddsi', $poId, $line['product_id'], $ordered_qty, $line['cost'], $line['line_total'], $line['order_type'], $box_qty);
            if (!$insertItem->execute()) throw new Exception($insertItem->error);
        }
        $insertItem->close();

        $conn->commit();
        redirect_with_message('../../modules/inventory/purchase_orders.php', "Purchase Order {$poNumber} created and sent for admin approval.");
    } catch (Throwable $e) {
        $conn->rollback();
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Failed to create purchase order: ' . $e->getMessage(), 'error');
    }
}

if ($action === 'update_po_status') {
    $poId = (int)($_POST['po_id'] ?? 0);
    $nextStatus = trim($_POST['next_status'] ?? '');

    $allowed = ['ordered', 'shipped', 'delivered'];
    if ($poId <= 0 || !in_array($nextStatus, $allowed, true)) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Invalid PO status update request.', 'error');
    }

    $getPo = $conn->prepare('SELECT status FROM purchase_orders WHERE po_id = ? LIMIT 1');
    $getPo->bind_param('i', $poId);
    $getPo->execute();
    $po = $getPo->get_result()->fetch_assoc();
    $getPo->close();

    if (!$po) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Purchase order not found.', 'error');
    }

    $current = $po['status'];
    if (in_array($current, ['pending_approval', 'rejected', 'cancelled', 'completed'], true)) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Current PO status cannot be moved to logistics state.', 'error');
    }

    $update = $conn->prepare('UPDATE purchase_orders SET status = ? WHERE po_id = ?');
    $update->bind_param('si', $nextStatus, $poId);
    $update->execute();
    $update->close();

    redirect_with_message('../../modules/inventory/purchase_orders.php', 'PO status updated to ' . strtoupper($nextStatus) . '.');
}

if ($action === 'receive_items') {
    $poId = (int)($_POST['po_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    $poItemIds = $_POST['po_item_id'] ?? [];
    $receivedQtys = $_POST['received_qty'] ?? [];
    $acceptedQtys = $_POST['accepted_qty'] ?? [];
    $rejectedQtys = $_POST['rejected_qty'] ?? [];
    $batchNumbers = $_POST['batch_number'] ?? [];
    $mfgDates = $_POST['mfg_date'] ?? [];
    $expiryDates = $_POST['expiry_date'] ?? [];
    $rejectReasons = $_POST['reject_reason'] ?? [];

    if ($poId <= 0 || !is_array($poItemIds) || count($poItemIds) === 0) {
        redirect_with_message('../../modules/inventory/po_receive.php?po_id=' . $poId, 'No receiving items submitted.', 'error');
    }

    $poStmt = $conn->prepare('SELECT po_id, po_number, supplier_id, status FROM purchase_orders WHERE po_id = ? LIMIT 1');
    $poStmt->bind_param('i', $poId);
    $poStmt->execute();
    $po = $poStmt->get_result()->fetch_assoc();
    $poStmt->close();

    if (!$po) {
        redirect_with_message('../../modules/inventory/purchase_orders.php', 'Purchase order not found.', 'error');
    }

    if (!in_array($po['status'], ['approved', 'ordered', 'shipped', 'delivered', 'partially_received'], true)) {
        redirect_with_message('../../modules/inventory/po_receive.php?po_id=' . $poId, 'This PO is not ready for receiving.', 'error');
    }

    try {
        $conn->begin_transaction();

        $insertReceiving = $conn->prepare('INSERT INTO po_receivings (po_id, received_by, received_at, remarks) VALUES (?, ?, NOW(), ?)');
        $insertReceiving->bind_param('iis', $poId, $userId, $remarks);
        $insertReceiving->execute();
        $receivingId = (int)$conn->insert_id;
        $insertReceiving->close();

        $fetchPoItem = $conn->prepare(
            'SELECT poi.po_item_id, poi.product_id, poi.ordered_qty, poi.received_qty, p.name
             FROM purchase_order_items poi
             JOIN products p ON p.product_id = poi.product_id
             WHERE poi.po_item_id = ? AND poi.po_id = ? LIMIT 1'
        );

        $insertReceivingItem = $conn->prepare(
            'INSERT INTO po_receiving_items (receiving_id, po_item_id, product_id, received_qty, accepted_qty, rejected_qty, batch_number, manufacture_date, expiry_date, reject_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $updatePoItem = $conn->prepare(
            'UPDATE purchase_order_items
             SET received_qty = received_qty + ?, rejected_qty = rejected_qty + ?
             WHERE po_item_id = ?'
        );

        $updateStock = $conn->prepare('UPDATE products SET stock = stock + ? WHERE product_id = ?');
        $insertInvLog = $conn->prepare('INSERT INTO inventory_logs (product_id, user_id, quantity_added, remarks) VALUES (?, ?, ?, ?)');
        $insertBatch = $conn->prepare(
            'INSERT INTO product_batches (product_id, po_id, batch_number, manufacture_date, expiry_date, initial_quantity, remaining_quantity, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "Pending")'
        );
        $insertReturn = $conn->prepare(
            'INSERT INTO supplier_returns (po_id, po_item_id, product_id, supplier_id, rejected_qty, reason, reason_notes, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending_return", ?)'
        );

        $processedAtLeastOne = false;

        for ($i = 0; $i < count($poItemIds); $i++) {
            $poItemId = (int)$poItemIds[$i];
            $received = (int)($receivedQtys[$i] ?? 0);
            $accepted = (int)($acceptedQtys[$i] ?? 0);
            $rejected = (int)($rejectedQtys[$i] ?? 0);

            if ($poItemId <= 0 || $received <= 0) {
                continue;
            }

            if ($accepted < 0 || $rejected < 0 || ($accepted + $rejected) > $received) {
                throw new RuntimeException('Invalid accepted/rejected quantity setup for one of the line items.');
            }

            $fetchPoItem->bind_param('ii', $poItemId, $poId);
            $fetchPoItem->execute();
            $item = $fetchPoItem->get_result()->fetch_assoc();

            if (!$item) {
                throw new RuntimeException('One of the submitted PO items was not found in this PO.');
            }

            $productId = (int)$item['product_id'];
            $batch = trim($batchNumbers[$i] ?? '');
            $mfg = trim($mfgDates[$i] ?? '');
            $expiry = trim($expiryDates[$i] ?? '');
            $reason = trim($rejectReasons[$i] ?? 'other');
            $reasonNotes = '';

            $allowedReasons = ['expired', 'damaged_packaging', 'wrong_item', 'near_expiry', 'other'];
            if (!in_array($reason, $allowedReasons, true)) {
                $reasonNotes = $reason;
                $reason = 'other';
            }

            $mfgValue = ($mfg !== '') ? $mfg : null;
            $expiryValue = ($expiry !== '') ? $expiry : null;
            $rejectReasonForReceiving = ($rejected > 0) ? $reason : null;

            $insertReceivingItem->bind_param(
                'iiiiiissss',
                $receivingId,
                $poItemId,
                $productId,
                $received,
                $accepted,
                $rejected,
                $batch,
                $mfgValue,
                $expiryValue,
                $rejectReasonForReceiving
            );
            $insertReceivingItem->execute();

            $updatePoItem->bind_param('iii', $received, $rejected, $poItemId);
            $updatePoItem->execute();

            if ($accepted > 0) {
                // Determine expiry date for FEFO (default to far future if null, though it shouldn't be null for expiry tracked items)
                $batchExpiry = $expiryValue !== null ? $expiryValue : date('Y-m-d', strtotime('+5 years'));

                // Insert into product_batches as Pending
                $insertBatch->bind_param(
                    'iisssii',
                    $productId,
                    $poId,
                    $batch,
                    $mfgValue,
                    $batchExpiry,
                    $accepted,
                    $accepted
                );
                $insertBatch->execute();

                // ─── NOTE: We ONLY insert to product_batches here. ────────
                // We DO NOT update `products.stock` yet! 
                // Stock is updated when the batch is 'Released' via the new Stock Management module.
            }

            if ($rejected > 0) {
                $insertReturn->bind_param(
                    'iiiiissi',
                    $poId,
                    $poItemId,
                    $productId,
                    $po['supplier_id'],
                    $rejected,
                    $reason,
                    $reasonNotes,
                    $userId
                );
                $insertReturn->execute();
            }

            $processedAtLeastOne = true;
        }

        $fetchPoItem->close();
        $insertReceivingItem->close();
        $updatePoItem->close();
        $updateStock->close();
        $insertInvLog->close();
        $insertBatch->close();
        $insertReturn->close();

        if (!$processedAtLeastOne) {
            throw new RuntimeException('No valid receiving quantity was submitted.');
        }

        $completionCheckSql = "
            SELECT
                SUM(CASE WHEN ordered_qty <= received_qty THEN 1 ELSE 0 END) AS lines_done,
                COUNT(*) AS total_lines
            FROM purchase_order_items
            WHERE po_id = ?
        ";
        $completionStmt = $conn->prepare($completionCheckSql);
        $completionStmt->bind_param('i', $poId);
        $completionStmt->execute();
        $completion = $completionStmt->get_result()->fetch_assoc();
        $completionStmt->close();

        $allDone = ((int)$completion['total_lines'] > 0) && ((int)$completion['lines_done'] === (int)$completion['total_lines']);
        $nextPoStatus = $allDone ? 'completed' : 'partially_received';

        $updatePoStatus = $conn->prepare('UPDATE purchase_orders SET status = ? WHERE po_id = ?');
        $updatePoStatus->bind_param('si', $nextPoStatus, $poId);
        $updatePoStatus->execute();
        $updatePoStatus->close();

        $conn->commit();

        redirect_with_message('../../modules/inventory/po_receive.php?po_id=' . $poId, 'Receiving saved. Accepted items were added to stock, rejected items were sent to supplier return queue.');
    } catch (Throwable $e) {
        $conn->rollback();
        redirect_with_message('../../modules/inventory/po_receive.php?po_id=' . $poId, 'Receiving failed: ' . $e->getMessage(), 'error');
    }
}

if ($action === 'update_return_status') {
    $returnId = (int)($_POST['return_id'] ?? 0);
    $nextStatus = trim($_POST['next_status'] ?? '');
    $resolutionType = trim($_POST['resolution_type'] ?? 'pending');

    $allowed = ['pending_return', 'returned_to_supplier', 'resolved'];
    $allowedResolutions = ['replace', 'credit_memo', 'pending'];
    
    if ($returnId <= 0 || !in_array($nextStatus, $allowed, true) || !in_array($resolutionType, $allowedResolutions, true)) {
        redirect_with_message('../../modules/inventory/supplier_returns.php', 'Invalid return status or resolution update.', 'error');
    }

    // Fetch current return with full details to evaluate resolution actions
    $fetchReturn = $conn->prepare('
        SELECT sr.status, sr.resolution_type, sr.supplier_id, sr.product_id, sr.rejected_qty, 
               poi.unit_cost, poi.order_type, poi.box_quantity
        FROM supplier_returns sr 
        LEFT JOIN purchase_order_items poi ON poi.po_item_id = sr.po_item_id
        WHERE sr.return_id = ? LIMIT 1
    ');
    $fetchReturn->bind_param('i', $returnId);
    $fetchReturn->execute();
    $currentReturn = $fetchReturn->get_result()->fetch_assoc();
    $fetchReturn->close();

    if (!$currentReturn) {
        redirect_with_message('../../modules/inventory/supplier_returns.php', 'Return record not found.', 'error');
    }

    // Force resolution to shift status to "resolved" automatically
    if (in_array($resolutionType, ['replace', 'credit_memo'], true)) {
        $nextStatus = 'resolved';
    }

    $oldStatus = $currentReturn['status'];
    $oldResolution = $currentReturn['resolution_type'] ?? 'pending';

    $resolvedAt = null;
    $resolvedByUser = null;
    $sentToSupplierAt = null;
    $sentByUser = null;

    // Set timestamps based on status transitions
    if ($nextStatus === 'resolved' && $oldStatus !== 'resolved') {
        $resolvedAt = date('Y-m-d H:i:s');
        $resolvedByUser = $userId;
    }
    
    if ($nextStatus === 'returned_to_supplier' && $oldStatus !== 'returned_to_supplier') {
        $sentToSupplierAt = date('Y-m-d H:i:s');
        $sentByUser = $userId;
    }

    try {
        $conn->begin_transaction();

        // Update supplier_returns with new status and resolution
        $updateReturn = $conn->prepare('
            UPDATE supplier_returns 
            SET status = ?, 
                resolution_type = ?,
                resolved_at = COALESCE(?, resolved_at),
                resolved_by_user = COALESCE(?, resolved_by_user),
                sent_to_supplier_at = COALESCE(?, sent_to_supplier_at),
                sent_by_user = COALESCE(?, sent_by_user),
                updated_at = NOW()
            WHERE return_id = ?
        ');
        $updateReturn->bind_param(
            'sssisii',
            $nextStatus,
            $resolutionType,
            $resolvedAt,
            $resolvedByUser,
            $sentToSupplierAt,
            $sentByUser,
            $returnId
        );
        $updateReturn->execute();
        $updateReturn->close();

        // Create audit trail entry (if schema exists)
        if (table_exists($conn, 'supplier_return_updates')) {
            $updateType = 'status_change';
            if ($oldResolution !== $resolutionType) {
                $updateType = 'resolution_set';
            }

            $insertUpdate = $conn->prepare('
                INSERT INTO supplier_return_updates (return_id, old_status, new_status, update_type, updated_by)
                VALUES (?, ?, ?, ?, ?)
            ');
            $insertUpdate->bind_param('isssi', $returnId, $oldStatus, $nextStatus, $updateType, $userId);
            $insertUpdate->execute();
            $insertUpdate->close();
        }

        $conn->commit();

        // Additional actions for valid resolution (Replace or Credit Memo) 
        // to prevent double execution, only do this if it wasn't the resolution already
        if ($oldResolution !== $resolutionType && $resolutionType !== 'pending') {
            $conn->begin_transaction();
            try {
                $qty = (int)$currentReturn['rejected_qty'];
                $sId = (int)$currentReturn['supplier_id'];
                $pId = (int)$currentReturn['product_id'];
                
                if ($resolutionType === 'replace') {
                    // Create a new PO for replacement stock
                    $repSuffix = '-REP-' . rand(100, 999);
                    $newPoNum = 'REP' . date('YmdH') . rand(10, 99);
                    
                    // Total cost for replacement is logically 0 since it's a replace,
                    // but for PO structural integrity we can carry over the price.
                    // However, we won't charge them again (so grand_total = 0 to denote free replacement)
                    // Or we just insert the item and ignore the 0 cost for tracking. We'll set lines total = 0.
                    $insertPO = $conn->prepare("
                        INSERT INTO purchase_orders 
                        (po_number, supplier_id, status, order_date, subtotal, tax_amount, grand_total, notes, created_by, approved_by, approved_at) 
                        VALUES (?, ?, 'approved', CURRENT_DATE, 0, 0, 0, 'Auto-generated replacement PO', ?, ?, NOW())
                    ");
                    $insertPO->bind_param('siii', $newPoNum, $sId, $userId, $userId);
                    $insertPO->execute();
                    $newPoId = $insertPO->insert_id;
                    $insertPO->close();
                    
                    // Insert the item
                    $oType = $currentReturn['order_type'] ?? 'retail';
                    $bQty  = (int)($currentReturn['box_quantity'] ?? 0);
                    $uCost = 0; // Free replacement
                    $newLineTotal = 0;
                    
                    $insertItem = $conn->prepare("
                        INSERT INTO purchase_order_items 
                        (po_id, product_id, ordered_qty, order_type, box_quantity, unit_cost, line_total) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertItem->bind_param('iiisidd', $newPoId, $pId, $qty, $oType, $bQty, $uCost, $newLineTotal);
                    $insertItem->execute();
                    $insertItem->close();

                    // Link replacement PO to the return record
                    $conn->query("UPDATE supplier_returns SET replacement_po_id = {$newPoId} WHERE return_id = {$returnId}");
                    
                } elseif ($resolutionType === 'credit_memo') {
                    $uCost = (float)($currentReturn['unit_cost'] ?? 0);
                    $creditAmount = $qty * $uCost;
                    
                    // Check if table column supplier_credit_logs exists, else gracefully skip
                    $conn->query("UPDATE suppliers SET credit_balance = credit_balance + {$creditAmount} WHERE supplier_id = {$sId}");
                    
                    if (table_exists($conn, 'supplier_credit_logs')) {
                        $logCredit = $conn->prepare("
                            INSERT INTO supplier_credit_logs (supplier_id, return_id, amount, action, notes) 
                            VALUES (?, ?, ?, 'credit_added', 'Credit Memo generated from return')
                        ");
                        $logCredit->bind_param('iid', $sId, $returnId, $creditAmount);
                        $logCredit->execute();
                        $logCredit->close();
                    }

                    // Log the credit memo amount in the return
                    $conn->query("UPDATE supplier_returns SET credit_memo_amount = {$creditAmount} WHERE return_id = {$returnId}");
                }
                
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                // We don't abort completely because the main return status was updated
                error_log('Failed applying replacement or credit memo: ' . $e->getMessage());
            }
        }

        $message = "Supplier return updated. Status: {$nextStatus}";
        if ($resolutionType !== 'pending') {
            $formattedResolution = ucwords(str_replace('_', ' ', $resolutionType));
            $message .= ", Resolution: {$formattedResolution}";
        }
        redirect_with_message('../../modules/inventory/supplier_returns.php', $message);
    } catch (Throwable $e) {
        $conn->rollback();
        redirect_with_message('../../modules/inventory/supplier_returns.php', 'Failed to update return: ' . $e->getMessage(), 'error');
    }
}

redirect_with_message('../../modules/inventory/purchase_orders.php', 'Unknown action requested.', 'error');
