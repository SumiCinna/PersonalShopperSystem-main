<?php
// core/customer/process_checkout.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../modules/customer/cart.php");
    exit();
}

if (!isset($_POST['selected_cart_ids']) || empty($_POST['selected_cart_ids'])) {
    $_SESSION['error'] = "No items were selected for checkout. Please try again.";
    header("Location: ../../modules/customer/cart.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_ids = array_map('intval', $_POST['selected_cart_ids']);
$ids_string = implode(',', $selected_ids);

// 1. Fetch ONLY SELECTED cart items one last time
$cart_query = "SELECT c.quantity, p.product_id, p.price, p.discount_price, p.stock, p.name 
               FROM cart c 
               JOIN products p ON c.product_id = p.product_id 
               WHERE c.user_id = ? AND c.cart_id IN ($ids_string)";
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    // SECURITY: What if someone bought the last item while this user was on the checkout page?
    if ($row['stock'] < $row['quantity']) {
        $_SESSION['error'] = "Sorry, " . $row['name'] . " is no longer in stock in that quantity.";
        header("Location: ../../modules/customer/cart.php");
        exit();
    }
    
    // Use discount price if available
    $final_price = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price']) ? $row['discount_price'] : $row['price'];
    $row['price'] = $final_price; // Overwrite price with final price for order_items table
    $cart_items[] = $row;
    $total_amount += ($final_price * $row['quantity']);
}
$stmt->close();

if (empty($cart_items)) {
    header("Location: ../../modules/customer/home.php");
    exit();
}

// Generate a Unique Tracking Number (e.g., ORD-20260224-4A8F)
$tracking_no = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

if (empty($_POST['pickup_date']) || empty($_POST['pickup_time'])) {
    $_SESSION['error'] = "Please select a pickup date and time.";
    header("Location: ../../modules/customer/cart.php");
    exit();
}

// 1. Gather all inputs
$pickup_datetime = $_POST['pickup_date'] . ' ' . $_POST['pickup_time'] . ':00';
$payment_method = 'gcash'; // Enforce online payment

// 2. The Secure Math Engine
$selected_type = $_POST['payment_type'];
$online_reference = trim($_POST['online_reference']);

if ($selected_type === 'partial_50') {
    $upfront_payment = $total_amount * 0.50; // Server strictly calculates 50%
    $balance_due = $total_amount - $upfront_payment;
    $payment_type = 'partial';
} elseif ($selected_type === 'partial_30') {
    $upfront_payment = $total_amount * 0.30; // Server strictly calculates 30%
    $balance_due = $total_amount - $upfront_payment;
    $payment_type = 'partial';
} else {
    $upfront_payment = $total_amount; // Paid full upfront
    $balance_due = 0.00; // Nothing owed at counter
    $payment_type = 'full';
}

// ==========================================
// START THE DATABASE TRANSACTION
// ==========================================
$conn->begin_transaction();

try {
    // 3. Insert into the upgraded Orders table
    $order_query = "INSERT INTO orders (user_id, tracking_no, total_amount, payment_method, order_status, payment_status, pickup_datetime, payment_type, upfront_payment, balance_due, online_reference) 
                    VALUES (?, ?, ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?)";
                    
    $order_stmt = $conn->prepare($order_query);
    if (!$order_stmt) {
        throw new Exception("Database Error (Orders): " . $conn->error);
    }
    $order_stmt->bind_param("isdsssdds", $user_id, $tracking_no, $total_amount, $payment_method, $pickup_datetime, $payment_type, $upfront_payment, $balance_due, $online_reference);
    
    if (!$order_stmt->execute()) {
        throw new Exception("Order Insert Failed: " . $order_stmt->error);
    }
    
    $order_id = $conn->insert_id;
    $order_stmt->close();

    // STEP 2: Loop through cart items and add them to `order_items`, then deduct stock
    $item_query = "INSERT INTO order_items (order_id, product_id, quantity, price_at_checkout) VALUES (?, ?, ?, ?)";
    $item_stmt = $conn->prepare($item_query);
    if (!$item_stmt) {
        throw new Exception("Database Error (Items): " . $conn->error);
    }
    
    // Bind variables for item insertion (Best Practice: Bind once, update values in loop)
    $p_id = 0; $qty = 0; $price = 0.0;
    $item_stmt->bind_param("iiid", $order_id, $p_id, $qty, $price);
    
    $stock_query = "UPDATE products SET stock = stock - ? WHERE product_id = ?";
    $stock_stmt = $conn->prepare($stock_query);
    if (!$stock_stmt) {
        throw new Exception("Database Error (Stock): " . $conn->error);
    }
    $stock_stmt->bind_param("ii", $qty, $p_id);

    foreach ($cart_items as $item) {
        $p_id = $item['product_id'];
        $qty = $item['quantity'];
        $price = $item['price'];
        
        if (!$item_stmt->execute()) {
            throw new Exception("Item Insert Failed: " . $item_stmt->error);
        }
        
        if (!$stock_stmt->execute()) {
            throw new Exception("Stock Update Failed: " . $stock_stmt->error);
        }
    }
    $item_stmt->close();
    $stock_stmt->close();

    // STEP 3: Remove ONLY the purchased items from the cart
    $clear_cart_query = "DELETE FROM cart WHERE user_id = ? AND cart_id IN ($ids_string)";
    $clear_cart_stmt = $conn->prepare($clear_cart_query);
    if (!$clear_cart_stmt) {
        throw new Exception("Database Error (Cart): " . $conn->error);
    }
    $clear_cart_stmt->bind_param("i", $user_id);
    if (!$clear_cart_stmt->execute()) {
        throw new Exception("Cart Clear Failed: " . $clear_cart_stmt->error);
    }
    $clear_cart_stmt->close();

    // ==========================================
    // EVERYTHING SUCCEEDED! COMMIT THE DATA
    // ==========================================
    $conn->commit();

    // Redirect to a success page with their tracking number
    header("Location: ../../modules/customer/order_success.php?tracking=" . $tracking_no);
    exit();

} catch (Exception $e) {
    // ==========================================
    // SOMETHING FAILED! UNDO EVERYTHING
    // ==========================================
    $conn->rollback();
    
    // Log the error (in a real app) and send the user back with a message
    $_SESSION['error'] = "Order Failed: " . $e->getMessage();
    header("Location: ../../modules/customer/cart.php");
    exit();
}

$conn->close();
?>