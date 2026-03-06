<?php
// core/cashier/update_order.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $order_status = $_POST['order_status'] ?? '';
    $payment_status = $_POST['payment_status'] ?? '';
    $cashier_id = $_SESSION['user_id'];

    // 1. Fetch the exact total amount from the database (Never trust frontend hidden inputs!)
    $stmt = $conn->prepare("SELECT total_amount FROM orders WHERE order_id = ?");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $order_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order_data) {
        $_SESSION['error'] = "Error: Order not found.";
        header("Location: ../../modules/cashier/dashboard.php");
        exit();
    }

    $grand_total = $order_data['total_amount'];

    // ==========================================
    // SCENARIO: VOID TRANSACTION (Reset to Pending)
    // ==========================================
    if (isset($_POST['is_void_action']) && $_POST['is_void_action'] == '1') {
        $void_password = $_POST['void_password'];
        
        // --- MANAGER PASSWORD CHECK ---
        if ($void_password !== 'admin123') { 
            $_SESSION['error'] = "Invalid Manager Password. Transaction void failed.";
            header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
            exit();
        }

        // Reset to pending and release cashier lock (processed_by = NULL)
        $query = "UPDATE orders SET order_status = 'pending', processed_by = NULL WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $order_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Transaction voided. Order returned to pending queue.";
            header("Location: ../../modules/cashier/pos.php");
            exit();
        }
    }

    // ==========================================
    // SCENARIO A: THE POS CHECKOUT (Triple Transaction)
    // ==========================================
    if ($order_status === 'completed' && $payment_status === 'paid') {
        
        $payment_method = $_POST['payment_method'];
        $payment_reference = trim($_POST['payment_reference'] ?? '');
        $amount_tendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? floatval($_POST['amount_tendered']) : NULL;
        $change_amount = isset($_POST['change_amount']) && $_POST['change_amount'] !== '' ? floatval($_POST['change_amount']) : NULL;

        // Security Validation
        if ($payment_method === 'gcash' && empty($payment_reference)) {
            $_SESSION['error'] = "SECURITY BLOCK: GCash transactions require a Reference Number.";
            header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
            exit();
        }

        // Philippine VAT Calculation (Prices are VAT-inclusive)
        // Vatable Sales = Total / 1.12. VAT = Total - Vatable Sales.
        $vatable_sales = $grand_total / 1.12;
        $tax_amount = $grand_total - $vatable_sales;
        $discount_amount = 0.00; // Keeping it 0 for now
        $invoice_no = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

        // Start the SQL Transaction
        $conn->begin_transaction();

        try {
            // STEP 1: Update the Orders Table
            $update_order = $conn->prepare("UPDATE orders SET order_status = 'completed', payment_status = 'paid', processed_by = ? WHERE order_id = ?");
            $update_order->bind_param("ii", $cashier_id, $order_id);
            $update_order->execute();
            $update_order->close();

            // STEP 2: Generate the Official Invoice
            $insert_invoice = $conn->prepare("INSERT INTO invoices (order_id, invoice_no, subtotal, tax_amount, discount_amount, grand_total) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_invoice->bind_param("isdddd", $order_id, $invoice_no, $vatable_sales, $tax_amount, $discount_amount, $grand_total);
            $insert_invoice->execute();
            $invoice_id = $conn->insert_id; // Grab the newly created Invoice ID
            $insert_invoice->close();

            // STEP 3: Record the Cash Flow Transaction
            $insert_trans = $conn->prepare("INSERT INTO transactions (invoice_id, cashier_id, transaction_type, payment_method, amount_paid, cash_tendered, change_given, reference_no) VALUES (?, ?, 'payment', ?, ?, ?, ?, ?)");
            $insert_trans->bind_param("iisddds", $invoice_id, $cashier_id, $payment_method, $grand_total, $amount_tendered, $change_amount, $payment_reference);
            $insert_trans->execute();
            $insert_trans->close();

            // Commit all changes securely
            $conn->commit();
            $_SESSION['success'] = "Transaction Complete! Invoice $invoice_no has been generated.";
            
            // Redirect to the receipt page for printing
            header("Location: ../../modules/cashier/receipt.php?id=" . $invoice_id);
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "System Error: Could not process the transaction.";
            header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
            exit();
        }

    // ==========================================
    // SCENARIO B: JUST UPDATING STATUS (Pending -> Processing -> Ready)
    // ==========================================
    } else {
        $query = "UPDATE orders SET order_status = ?, processed_by = ? WHERE order_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sii", $order_status, $cashier_id, $order_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Order status updated to " . ucfirst($order_status) . ".";
        } else {
            $_SESSION['error'] = "System Error: Could not update the order status.";
        }
        $stmt->close();
        
        // Refresh the POS terminal page
        header("Location: ../../modules/cashier/process_order.php?id=" . $order_id);
        exit();
    }

} else {
    header("Location: ../../modules/cashier/dashboard.php");
    exit();
}
?>