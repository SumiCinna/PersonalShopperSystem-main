<?php
// modules/admin/edit_product.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

$error = '';
$success = '';

// 1. Ensure we have a Product ID in the URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

// --- FORM PROCESSING LOGIC (UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku = trim($_POST['sku']);
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $cost_price = floatval($_POST['cost_price']);
    $price = floatval($_POST['price']);
    $discount_price = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : NULL;
    $unit_value = !empty($_POST['unit_value']) ? floatval($_POST['unit_value']) : NULL;
    $unit_measure = !empty($_POST['unit_measure']) ? $_POST['unit_measure'] : NULL;
    $stock = intval($_POST['stock']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']);

    if (empty($sku) || empty($name) || empty($category)) {
        $error = "SKU, Product Name, and Category are required.";
    } else {
        // CRUCIAL: Check if SKU exists, but EXCLUDE the current product's ID
        $check_stmt = $conn->prepare("SELECT product_id FROM products WHERE sku = ? AND product_id != ?");
        $check_stmt->bind_param("si", $sku, $product_id);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Another product is already using this SKU! Please use a unique SKU.";
        } else {
            // Execute the UPDATE query
            $update_query = "UPDATE products SET 
                sku = ?, name = ?, brand = ?, category = ?, cost_price = ?, price = ?, discount_price = ?,
                unit_value = ?, unit_measure = ?, stock = ?, low_stock_threshold = ?, 
                status = ?, description = ?, image_url = ? 
                WHERE product_id = ?";
                
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssddddsiisssi", 
                $sku, $name, $brand, $category, $cost_price, $price, $discount_price,
                $unit_value, $unit_measure, $stock, $low_stock_threshold, 
                $status, $description, $image_url, $product_id
            );

            if ($stmt->execute()) {
                $success = "Product updated successfully!";
            } else {
                $error = "Error updating product: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// --- FETCH CURRENT PRODUCT DATA ---
// We do this AFTER the POST logic so the form shows the freshly updated data!
$fetch_stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$fetch_stmt->bind_param("i", $product_id);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();

if ($result->num_rows === 0) {
    // Product doesn't exist, send them back to the inventory
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();
$fetch_stmt->close();

// Include the Header & Sidebar
$page_title = 'Edit Product: ' . htmlspecialchars($product['name']);
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 p-8">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Edit Product</h1>
            <p class="text-gray-500 mt-1">Editing ID #<?php echo $product_id; ?>: <span class="font-semibold text-blue-800"><?php echo htmlspecialchars($product['name']); ?></span></p>
        </div>
        <a href="products.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold py-2 px-4 rounded-lg transition">
            &larr; Back to Inventory
        </a>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm">
            <p class="font-bold">Error</p>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">
            <p class="font-bold">Success</p>
            <p><?php echo htmlspecialchars($success); ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
        <form method="POST" action="edit_product.php?id=<?php echo $product_id; ?>" class="p-8">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-blue-900 border-b pb-2">Primary Details</h3>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">SKU (Barcode) *</label>
                        <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none uppercase font-mono bg-gray-50">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Brand</label>
                            <input type="text" name="brand" value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                            <select name="category" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                                <?php 
                                $categories = ['Beverages', 'Canned Goods', 'Condiments', 'Dairy', 'Noodles', 'Snacks', 'Cooking Essentials'];
                                foreach ($categories as $cat) {
                                    $selected = ($product['category'] === $cat) ? 'selected' : '';
                                    echo "<option value=\"$cat\" $selected>$cat</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Size/Weight Value</label>
                            <input type="number" step="0.01" name="unit_value" value="<?php echo htmlspecialchars($product['unit_value'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Unit of Measure</label>
                            <select name="unit_measure" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                                <?php 
                                $units = ['' => 'None', 'g' => 'Grams (g)', 'kg' => 'Kilograms (kg)', 'ml' => 'Milliliters (ml)', 'L' => 'Liters (L)', 'pc' => 'Piece (pc)', 'pack' => 'Pack'];
                                foreach ($units as $val => $label) {
                                    $selected = ($product['unit_measure'] === $val) ? 'selected' : '';
                                    echo "<option value=\"$val\" $selected>$label</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-blue-900 border-b pb-2">Pricing & Inventory</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Cost Price (₱) *</label>
                            <input type="number" step="0.01" min="0" name="cost_price" value="<?php echo htmlspecialchars($product['cost_price']); ?>" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Regular Selling Price (₱)</label>
                            <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required class="w-full bg-white border border-gray-300 rounded p-3">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-bold text-red-600 mb-1">Promo/Discount Price (₱) - Optional</label>
                            <input type="number" step="0.01" name="discount_price" value="<?php echo htmlspecialchars($product['discount_price'] ?? ''); ?>" class="w-full bg-red-50 border border-red-300 rounded p-3 placeholder-red-300" placeholder="Leave blank if no promo">
                            <p class="text-[10px] text-gray-500 mt-1">If filled, the regular price will be slashed on the shop page.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Current Stock *</label>
                            <input type="number" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" required min="0" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Warning At *</label>
                            <input type="number" name="low_stock_threshold" value="<?php echo htmlspecialchars($product['low_stock_threshold']); ?>" required min="0" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-red-600 font-semibold">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Status *</label>
                        <select name="status" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                            <option value="active" <?php echo ($product['status'] === 'active') ? 'selected' : ''; ?>>Active (Visible to Customers)</option>
                            <option value="inactive" <?php echo ($product['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive (Hidden)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Image URL</label>
                        <input type="url" name="image_url" value="<?php echo htmlspecialchars($product['image_url'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm text-gray-500">
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Product Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
            </div>

            <div class="mt-8 pt-6 border-t flex justify-end">
                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-3 px-8 rounded-lg shadow-md transition flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Update Product
                </button>
            </div>

        </form>
    </div>

</main>

<?php require_once '../../includes/admin_footer.php'; ?>