<?php
session_start();
require_once '../../config/config.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

$products = [
    ['APPLE-DDE', 'Apple', 'Dde', 'Fresh Produce', 15, 20, NULL, NULL, NULL, 30, 10, 'active', NULL, NULL],
    ['BANANA-DDE', 'Banana', 'Dde', 'Fresh Produce', 8, 12, NULL, NULL, NULL, 50, 10, 'active', NULL, NULL],
    ['ORANGE-SUN', 'Orange', 'Sunkit', 'Fresh Produce', 18, 25, NULL, NULL, NULL, 25, 10, 'active', NULL, NULL],
    ['MANGO-GUM', 'Mango', 'Gumaras Fresh', 'Fresh Produce', 35, 45, NULL, NULL, NULL, 20, 5, 'active', NULL, NULL],
    ['PINEAPPLE-DM', 'Pineapple', 'Del Monte', 'Fresh Produce', 40, 55, NULL, NULL, NULL, 15, 5, 'active', NULL, NULL],
    ['WATERMELON-LF', 'Watermelon', 'Local Farm', 'Fresh Produce', 60, 80, NULL, NULL, NULL, 10, 3, 'active', NULL, NULL],
    ['TOMATO-LF', 'Tomato', 'Local Farm', 'Fresh Produce', 10, 14, NULL, NULL, NULL, 40, 15, 'active', NULL, NULL],
    ['ONION-LF', 'Onion', 'Local Farm', 'Fresh Produce', 12, 18, NULL, NULL, NULL, 35, 15, 'active', NULL, NULL],
    ['GARLIC-LF', 'Garlic', 'Local Farm', 'Fresh Produce', 15, 22, NULL, NULL, NULL, 25, 10, 'active', NULL, NULL],
    ['POTATO-BF', 'Potato', 'Baguo Fresh', 'Fresh Produce', 20, 28, NULL, NULL, NULL, 45, 15, 'active', NULL, NULL],
];

$inserted = 0;
$failed = 0;

foreach ($products as $product) {
    $insert_query = "INSERT INTO products 
        (sku, name, brand, category, cost_price, price, discount_price, unit_value, unit_measure, stock, low_stock_threshold, status, description, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("ssssddddsiisss", 
        $product[0], $product[1], $product[2], $product[3], 
        $product[4], $product[5], $product[6], $product[7], $product[8], 
        $product[9], $product[10], $product[11], $product[12], $product[13]
    );

    if ($stmt->execute()) {
        $inserted++;
    } else {
        $failed++;
    }
    $stmt->close();
}

$conn->close();

// Redirect back to products page with success message
header("Location: products.php?bulk_insert=success&count=$inserted");
exit();
?>
