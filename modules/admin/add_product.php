<?php
// modules/admin/add_product.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../admin-login.php");
    exit();
}

$error = '';

// --- FORM PROCESSING LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Sanitize and collect the inputs
    $sku = trim($_POST['sku']);
    $name = trim($_POST['name']);
    $brand = trim($_POST['brand']);
    $category = trim($_POST['category']);
    $cost_price = floatval($_POST['cost_price']);
    $price = floatval($_POST['price']);
    $discount_price = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : NULL;
    
    // Handle optional split unit measurements
    $unit_value = !empty($_POST['unit_value']) ? floatval($_POST['unit_value']) : NULL;
    $unit_measure = !empty($_POST['unit_measure']) ? $_POST['unit_measure'] : NULL;
    
    $stock = intval($_POST['stock']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    $image_url = trim($_POST['image_url']); // Keeping it simple with URLs for now

    // 2. Basic Validation
    if (empty($sku) || empty($name) || empty($category)) {
        $error = "SKU, Product Name, and Category are required.";
    } else {
        // 3. Check if SKU already exists (SKUs must be unique!)
        $check_stmt = $conn->prepare("SELECT product_id FROM products WHERE sku = ?");
        $check_stmt->bind_param("s", $sku);
        $check_stmt->execute();
        
        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "A product with this SKU already exists! Please use a unique SKU.";
        } else {
            // 4. Insert into Database using Prepared Statements for security
            $insert_query = "INSERT INTO products 
                (sku, name, brand, category, cost_price, price, discount_price, unit_value, unit_measure, stock, low_stock_threshold, status, description, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssddddsiisss", 
                $sku, $name, $brand, $category, $cost_price, $price, $discount_price, $unit_value, $unit_measure, $stock, $low_stock_threshold, $status, $description, $image_url
            );

            if ($stmt->execute()) {
                // Success! Redirect back to the inventory table
                header("Location: products.php");
                exit();
            } else {
                $error = "Error adding product: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

// Include the Header & Sidebar
$page_title = 'Add New Product';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 p-8">
    
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-800">Add New Product</h1>
            <p class="text-gray-500 mt-1">Fill out the details below to add a new item to the inventory.</p>
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

    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden">
        <form method="POST" action="add_product.php" class="p-8">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-blue-900 border-b pb-2">Primary Details</h3>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">SKU (Barcode) *</label>
                        <input type="text" name="sku" required placeholder="e.g. BEAR-MILK-150G" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none uppercase font-mono">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" required placeholder="e.g. Bear Brand Powdered Milk" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Brand</label>
                            <input type="text" name="brand" placeholder="e.g. Nestle" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                            <select name="category" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                                <option value="">Select Category...</option>
                                <option value="Beverages">Beverages</option>
                                <option value="Canned Goods">Canned Goods</option>
                                <option value="Condiments">Condiments</option>
                                <option value="Dairy">Dairy</option>
                                <option value="Noodles">Noodles</option>
                                <option value="Snacks">Snacks</option>
                                <option value="Cooking Essentials">Cooking Essentials</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Size/Weight Value</label>
                            <input type="number" step="0.01" name="unit_value" placeholder="e.g. 150" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Unit of Measure</label>
                            <select name="unit_measure" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                                <option value="">None</option>
                                <option value="g">Grams (g)</option>
                                <option value="kg">Kilograms (kg)</option>
                                <option value="ml">Milliliters (ml)</option>
                                <option value="L">Liters (L)</option>
                                <option value="pc">Piece (pc)</option>
                                <option value="pack">Pack</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-blue-900 border-b pb-2">Pricing & Inventory</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier Cost Price (₱) *</label>
                            <input type="number" step="0.01" min="0" name="cost_price" required placeholder="0.00" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Regular Selling Price (₱)</label>
                            <input type="number" step="0.01" name="price" required class="w-full bg-white border border-gray-300 rounded p-3" placeholder="0.00">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-bold text-red-600 mb-1">Promo/Discount Price (₱) - Optional</label>
                            <input type="number" step="0.01" name="discount_price" class="w-full bg-red-50 border border-red-300 rounded p-3 placeholder-red-300" placeholder="Leave blank if no promo">
                            <p class="text-[10px] text-gray-500 mt-1">If filled, the regular price will be slashed on the shop page.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Initial Stock *</label>
                            <input type="number" name="stock" required value="0" min="0" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Warning At *</label>
                            <input type="number" name="low_stock_threshold" required value="10" min="0" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-red-600 font-semibold">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Status *</label>
                        <select name="status" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                            <option value="active">Active (Visible to Customers)</option>
                            <option value="inactive">Inactive (Hidden)</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Image URL</label>
                        <input type="url" name="image_url" placeholder="https://example.com/image.jpg" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-sm text-gray-500">
                    </div>
                </div>
            </div>

            <div class="mt-8">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Product Description</label>
                <textarea name="description" rows="3" placeholder="Write a short description of the item..." class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
            </div>

            <div class="mt-8 pt-6 border-t flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg shadow-md transition flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Save Product
                </button>
            </div>

        </form>
    </div>

</main>

<?php require_once '../../includes/admin_footer.php'; ?>