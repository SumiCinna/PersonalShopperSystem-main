<?php
// core/customer/add_to_cart.php
session_start();
require_once '../../config/config.php';

// Tell the browser we are sending a JSON response back
header('Content-Type: application/json');

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to your cart.']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Read the JSON sent by the frontend
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['product_id'])) {
        $user_id = $_SESSION['user_id'];
        $product_id = intval($data['product_id']);
        $action = isset($data['action']) ? $data['action'] : 'add';

        // --- UPDATE LOGIC (Set specific quantity) ---
        if ($action === 'update') {
            $new_quantity = intval($data['quantity']);

            // 1. If quantity is 0 or less, remove the item
            if ($new_quantity <= 0) {
                $del_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $del_stmt->bind_param("ii", $user_id, $product_id);
                $del_stmt->execute();
                $del_stmt->close();
            } else {
                // 2. Check Stock Availability
                $stock_stmt = $conn->prepare("SELECT stock, name FROM products WHERE product_id = ?");
                $stock_stmt->bind_param("i", $product_id);
                $stock_stmt->execute();
                $prod_data = $stock_stmt->get_result()->fetch_assoc();
                $stock_stmt->close();

                if (!$prod_data || $prod_data['stock'] < $new_quantity) {
                    echo json_encode(['success' => false, 'message' => 'Sorry, only ' . $prod_data['stock'] . ' units available.']);
                    exit();
                }

                // 3. Update or Insert the specific quantity
                $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
                $stmt->bind_param("iiii", $user_id, $product_id, $new_quantity, $new_quantity);
                $stmt->execute();
                $stmt->close();
            }

            // Get new total cart count for the badge
            $count_stmt = $conn->prepare("SELECT COUNT(*) as total_items FROM cart WHERE user_id = ?");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $new_cart_count = $count_stmt->get_result()->fetch_assoc()['total_items'] ?? 0;
            $count_stmt->close();

            echo json_encode(['success' => true, 'message' => 'Cart updated.', 'cart_count' => $new_cart_count, 'quantity' => $new_quantity]);
            exit();
        }

        // --- TOGGLE LOGIC (Check if exists, then remove or add) ---
        if ($action === 'toggle') {
            $check_stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $check_stmt->bind_param("ii", $user_id, $product_id);
            $check_stmt->execute();
            $exists = $check_stmt->get_result()->num_rows > 0;
            $check_stmt->close();

            if ($exists) {
                // Remove item
                $del_stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $del_stmt->bind_param("ii", $user_id, $product_id);
                $del_stmt->execute();
                $del_stmt->close();

                // Get new count
                $count_stmt = $conn->prepare("SELECT COUNT(*) as total_items FROM cart WHERE user_id = ?");
                $count_stmt->bind_param("i", $user_id);
                $count_stmt->execute();
                $new_cart_count = $count_stmt->get_result()->fetch_assoc()['total_items'] ?? 0;
                $count_stmt->close();

                echo json_encode(['success' => true, 'message' => 'Item removed from cart.', 'cart_count' => $new_cart_count, 'status' => 'removed']);
                exit();
            }
        }
        
        $quantity_to_add = 1; // By default, adding from the dashboard adds 1 at a time

        // 1. FIRST CHECK: Does the product exist and is it in stock?
        $stock_stmt = $conn->prepare("SELECT name, stock, price FROM products WHERE product_id = ? AND status = 'active'");
        $stock_stmt->bind_param("i", $product_id);
        $stock_stmt->execute();
        $stock_result = $stock_stmt->get_result();

        if ($stock_result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Product is unavailable.']);
            exit();
        }

        $product = $stock_result->fetch_assoc();
        $stock_stmt->close();

        if ($product['stock'] < $quantity_to_add) {
            echo json_encode(['success' => false, 'message' => 'Sorry, this item is out of stock!']);
            exit();
        }

        // 2. SECOND CHECK: How many does the user ALREADY have in their cart?
        // We cannot let them put 10 in their cart if the store only has 5!
        $cart_check_stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $cart_check_stmt->bind_param("ii", $user_id, $product_id);
        $cart_check_stmt->execute();
        $cart_check_result = $cart_check_stmt->get_result();
        
        $current_cart_qty = 0;
        if ($cart_check_result->num_rows > 0) {
            $cart_row = $cart_check_result->fetch_assoc();
            $current_cart_qty = $cart_row['quantity'];
        }
        $cart_check_stmt->close();

        // Check if adding one more exceeds store inventory
        if (($current_cart_qty + $quantity_to_add) > $product['stock']) {
            echo json_encode(['success' => false, 'message' => 'You cannot add more of this item. We only have ' . $product['stock'] . ' in stock.']);
            exit();
        }

        // 3. ADD TO CART: Because you built the composite unique key, we can do this brilliantly!
        // This query says: "Try to insert a new row. If it hits our unique key (User + Product already exists), just UPDATE the quantity instead!"
        $insert_cart_query = "INSERT INTO cart (user_id, product_id, quantity) 
                              VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE quantity = quantity + ?";
                              
        $stmt = $conn->prepare($insert_cart_query);
        $stmt->bind_param("iiii", $user_id, $product_id, $quantity_to_add, $quantity_to_add);

        if ($stmt->execute()) {
            
            // Recalculate the TOTAL items in the cart to update the red badge in the header
            $count_stmt = $conn->prepare("SELECT COUNT(*) as total_items FROM cart WHERE user_id = ?");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result()->fetch_assoc();
            $new_cart_count = $count_result['total_items'];
            $count_stmt->close();

            echo json_encode([
                'success' => true, 
                'message' => htmlspecialchars($product['name']) . ' added to cart!',
                'cart_count' => $new_cart_count,
                'status' => 'added'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'System error: Could not update cart.']);
        }
        $stmt->close();
        
    } else {
        echo json_encode(['success' => false, 'message' => 'No product selected.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}

$conn->close();
?>