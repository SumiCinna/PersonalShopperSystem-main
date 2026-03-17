<?php
session_start();
require_once '../../config/config.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

$products = [
    ['APPLE-DDE', 'Apple', 'Dole', 'Fresh Produce', 15, 20, NULL, NULL, NULL, 30, 10, 'active', 'Fresh red apples', 'https://images.unsplash.com/photo-1560807707-38cc612d91b3?w=500&q=80'],
    ['BANANA-DDE', 'Banana', 'Dole', 'Fresh Produce', 8, 12, NULL, NULL, NULL, 50, 10, 'active', 'Fresh yellow bananas', 'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=500&q=80'],
    ['ORANGE-SUN', 'Orange', 'Sunkist', 'Fresh Produce', 18, 25, NULL, NULL, NULL, 25, 10, 'active', 'Fresh juicy oranges', 'https://images.unsplash.com/photo-1557803104035-a67b8e063645?w=500&q=80'],
    ['MANGO-GUM', 'Mango', 'Gumaras Fresh', 'Fresh Produce', 35, 45, NULL, NULL, NULL, 20, 5, 'active', 'Premium fresh mangoes', 'https://images.unsplash.com/photo-1585518419759-147417c627a4?w=500&q=80'],
    ['PINEAPPLE-DM', 'Pineapple', 'Del Monte', 'Fresh Produce', 40, 55, NULL, NULL, NULL, 15, 5, 'active', 'Fresh sweet pineapples', 'https://images.unsplash.com/photo-1599599810694-b5ac4dd37e4b?w=500&q=80'],
    ['WATERMELON-LF', 'Watermelon', 'Local Farm', 'Fresh Produce', 60, 80, NULL, NULL, NULL, 10, 3, 'active', 'Fresh sweet watermelons', 'https://images.unsplash.com/photo-1553530666-ba2a8e36cd12?w=500&q=80'],
    ['TOMATO-LF', 'Tomato', 'Local Farm', 'Fresh Produce', 10, 14, NULL, NULL, NULL, 40, 15, 'active', 'Fresh ripe tomatoes', 'https://images.unsplash.com/photo-1592841494240-92586798ee3e?w=500&q=80'],
    ['ONION-LF', 'Onion', 'Local Farm', 'Fresh Produce', 12, 18, NULL, NULL, NULL, 35, 15, 'active', 'Fresh yellow onions', 'https://images.unsplash.com/photo-1585518411506-967409a6a78a?w=500&q=80'],
    ['GARLIC-LF', 'Garlic', 'Local Farm', 'Fresh Produce', 15, 22, NULL, NULL, NULL, 25, 10, 'active', 'Fresh garlic bulbs', 'https://images.unsplash.com/photo-1596040306317-d3c8a8c07bef?w=500&q=80'],
    ['POTATO-BF', 'Potato', 'Baguio Fresh', 'Fresh Produce', 20, 28, NULL, NULL, NULL, 45, 15, 'active', 'Fresh potatoes', 'https://images.unsplash.com/photo-1596040306317-d3c8a8c07bef?w=500&q=80'],
];

$inserted = 0;
$failed = 0;
$errors = [];

foreach ($products as $product) {
    $insert_query = "INSERT INTO products 
        (sku, name, brand, category, cost_price, price, discount_price, unit_value, unit_measure, stock, low_stock_threshold, status, description, image_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
    $stmt = $conn->prepare($insert_query);
    
    if (!$stmt) {
        $failed++;
        $errors[] = "Prepare failed: " . $conn->error;
        continue;
    }
    
    $stmt->bind_param("ssssddddsiisss", 
        $product[0], $product[1], $product[2], $product[3], 
        $product[4], $product[5], $product[6], $product[7], $product[8], 
        $product[9], $product[10], $product[11], $product[12], $product[13]
    );

    if ($stmt->execute()) {
        $inserted++;
    } else {
        $failed++;
        $errors[] = "Failed to insert {$product[1]}: " . $stmt->error;
    }
    $stmt->close();
}

$conn->close();

// Show results
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fresh Produce Insert Results</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold mb-6">Insert Results</h1>
            
            <div class="space-y-4">
                <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded">
                    <p class="font-semibold text-green-900">✓ Successfully Inserted: <span class="text-2xl"><?php echo $inserted; ?></span></p>
                </div>
                
                <?php if ($failed > 0): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded">
                    <p class="font-semibold text-red-900">✗ Failed: <span class="text-2xl"><?php echo $failed; ?></span></p>
                    <?php foreach ($errors as $error): ?>
                    <p class="text-red-700 text-sm mt-2"><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-8">
                <a href="products.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-lg">
                    ← Back to Products
                </a>
            </div>
        </div>
    </div>
</body>
</html>
