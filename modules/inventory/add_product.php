<?php
session_start();
require_once '../../config/config.php';
// modules/inventory/add_product.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

$error = '';

function log_activity($conn, $user_id, $action, $product_id, $product_name, $field = null, $old = null, $new = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, product_name, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ississs", $user_id, $action, $product_id, $product_name, $field, $old, $new);
    $stmt->execute();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku              = trim($_POST['sku']);
    $name             = trim($_POST['name']);
    $brand            = trim($_POST['brand']);
    $category         = trim($_POST['category']);
    $cost_price       = floatval($_POST['cost_price']);
    $price            = floatval($_POST['price']);
    $discount_price   = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : NULL;
    $unit_value       = !empty($_POST['unit_value']) ? floatval($_POST['unit_value']) : NULL;
    $unit_measure     = !empty($_POST['unit_measure']) ? $_POST['unit_measure'] : NULL;
    $stock            = intval($_POST['stock']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $pcs_per_box      = max(1, intval($_POST['pcs_per_box'] ?? 1));
    $status           = $_POST['status'];
    $description      = trim($_POST['description']);
    $image_url        = trim($_POST['image_url']);

    if (empty($sku) || empty($name) || empty($category)) {
        $error = "SKU, Product Name, and Category are required.";
    } elseif ($cost_price >= $price) {
        $error = "Cost price must be lower than the regular selling price.";
    } else {
        $check_stmt = $conn->prepare("SELECT product_id FROM products WHERE sku = ?");
        $check_stmt->bind_param("s", $sku);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "A product with this SKU already exists! Please use a unique SKU.";
        } else {
            $insert_query = "INSERT INTO products 
                (sku, name, brand, category, cost_price, price, discount_price, unit_value, unit_measure, stock, low_stock_threshold, pcs_per_box, status, description, image_url) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ssssddddsiiisss",
                $sku, $name, $brand, $category,
                $cost_price, $price, $discount_price,
                $unit_value, $unit_measure,
                $stock, $low_stock_threshold, $pcs_per_box,
                $status, $description, $image_url
            );

            if ($stmt->execute()) {
                $new_product_id = $conn->insert_id;

                log_activity(
                    $conn,
                    $_SESSION['user_id'],
                    'add',
                    $new_product_id,
                    $name,
                    null,
                    null,
                    "SKU: $sku | Stock: $stock | Price: ₱$price | Pcs/Box: $pcs_per_box | Status: $status"
                );

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

$page_title = 'Add New Product';
require_once '../../includes/inventory_header.php';
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
                        <input type="text" name="sku" required maxlength="50" placeholder="e.g. BEAR-MILK-150G" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none uppercase font-mono">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" required maxlength="50" placeholder="e.g. Bear Brand Powdered Milk" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Brand</label>
                            <input type="text" name="brand" maxlength="50" placeholder="e.g. Nestle" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                            <select name="category" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                                <option value="">Select Category...</option>
                                <option value="Beverages">Beverages</option>
                                <option value="Canned Goods">Canned Goods</option>
                                <option value="Condiments">Condiments</option>
                                <option value="Dairy">Dairy</option>
                                <option value="Fresh Produce">Fresh Produce</option>
                                <option value="Noodles">Noodles</option>
                                <option value="Snacks">Snacks</option>
                                <option value="Cooking Essentials">Cooking Essentials</option>
                                <option value="Meat &amp; Poultry">Meat &amp; Poultry</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Size/Weight Value</label>
                            <input type="text" inputmode="decimal" name="unit_value" placeholder="e.g. 150.00" pattern="^\d{1,5}(\.\d{1,2})?$"
                                oninput="
                                    var v = this.value;
                                    if (/^\d{0,5}(\.\d{0,2})?$/.test(v)) {
                                        this.dataset.prev = v;
                                    } else {
                                        this.value = this.dataset.prev || '';
                                    }
                                "
                                class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
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

                    <!-- Pieces Per Box -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">
                            Pieces per Box
                            <span class="ml-1 text-xs font-normal text-gray-400">(used for wholesale PO ordering)</span>
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="number" name="pcs_per_box" id="pcs_per_box" value="1" min="1" max="9999"
                                oninput="if(parseInt(this.value) < 1 || !this.value) this.value = 1; if(this.value.length > 4) this.value = this.value.slice(0,4);"
                                class="w-32 px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-center">
                            <span class="text-sm text-gray-500">pcs / box</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Set to 1 if not sold in boxes or N/A.</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-blue-900 border-b pb-2">Pricing & Inventory</h3>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Supplier Cost Price (₱) *</label>
                            <input type="text" inputmode="decimal" name="cost_price" id="cost_price" required placeholder="0.00" pattern="^\d{1,5}(\.\d{1,2})?$"
                                oninput="
                                    var v = this.value;
                                    if (/^\d{0,5}(\.\d{0,2})?$/.test(v)) {
                                        this.dataset.prev = v;
                                    } else {
                                        this.value = this.dataset.prev || '';
                                    }
                                "
                                class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Regular Selling Price (₱)</label>
                            <input type="text" inputmode="decimal" name="price" id="price" required placeholder="0.00" pattern="^\d{1,5}(\.\d{1,2})?$"
                                oninput="
                                    var v = this.value;
                                    if (/^\d{0,5}(\.\d{0,2})?$/.test(v)) {
                                        this.dataset.prev = v;
                                    } else {
                                        this.value = this.dataset.prev || '';
                                    }
                                "
                                class="w-full bg-white border border-gray-300 rounded p-3">
                        </div>
                        <div class="col-span-2">
                            <p id="price_validation_feedback" class="text-xs text-gray-500 min-h-[1rem]"></p>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-bold text-red-600 mb-1">Promo/Discount Price (₱) - Optional</label>
                            <input type="number" step="0.01" min="0" max="99999.99" name="discount_price"
                                oninput="
                                    var parts = this.value.split('.');
                                    if(parts[0].length > 5) parts[0] = parts[0].slice(0,5);
                                    if(parts[1] !== undefined && parts[1].length > 2) parts[1] = parts[1].slice(0,2);
                                    this.value = parts.join('.');
                                    if(parseFloat(this.value) > 99999.99) this.value = 99999.99;
                                "
                                class="w-full bg-red-50 border border-red-300 rounded p-3 placeholder-red-300" placeholder="Leave blank if no promo">
                            <p class="text-[10px] text-gray-500 mt-1">If filled, the regular price will be slashed on the shop page.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Initial Stock *</label>
                            <input type="number" name="stock" required value="0" min="0" max="99999"
                                oninput="if(this.value.length > 5) this.value = this.value.slice(0,5); if(parseInt(this.value) > 99999) this.value = 99999;"
                                class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Warning At *</label>
                            <input type="number" name="low_stock_threshold" required value="10" min="0" max="99999"
                                oninput="if(this.value.length > 5) this.value = this.value.slice(0,5); if(parseInt(this.value) > 99999) this.value = 99999;"
                                class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-red-600 font-semibold">
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
                <textarea name="description" rows="3" maxlength="100" placeholder="Write a short description of the item..." class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"></textarea>
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

<script>
(function () {
    const costInput = document.getElementById('cost_price');
    const sellInput = document.getElementById('price');
    const feedback = document.getElementById('price_validation_feedback');
    const form = document.querySelector('form[action="add_product.php"]');

    if (!costInput || !sellInput || !feedback || !form) return;

    function parsePrice(value) {
        if (!value || value.trim() === '') return null;
        const n = Number.parseFloat(value);
        return Number.isFinite(n) ? n : null;
    }

    function validatePrices() {
        const cost = parsePrice(costInput.value);
        const sell = parsePrice(sellInput.value);

        costInput.setCustomValidity('');
        sellInput.setCustomValidity('');

        if (cost === null || sell === null) {
            feedback.textContent = 'Cost price must be lower than regular selling price.';
            feedback.className = 'text-xs text-gray-500 min-h-[1rem]';
            return true;
        }

        if (cost >= sell) {
            const message = 'Cost price must be lower than regular selling price.';
            costInput.setCustomValidity(message);
            sellInput.setCustomValidity(message);
            feedback.textContent = message;
            feedback.className = 'text-xs text-red-600 min-h-[1rem]';
            return false;
        }

        feedback.textContent = 'Looks good: selling price is higher than cost price.';
        feedback.className = 'text-xs text-green-600 min-h-[1rem]';
        return true;
    }

    costInput.addEventListener('input', validatePrices);
    sellInput.addEventListener('input', validatePrices);

    form.addEventListener('submit', function (event) {
        if (!validatePrices()) {
            event.preventDefault();
            sellInput.reportValidity();
        }
    });

    validatePrices();

    // --- Auto-detect Unit of Measure ---
    const catSelect = document.querySelector('select[name="category"]');
    const umSelect = document.querySelector('select[name="unit_measure"]');
    
    if (catSelect && umSelect) {
        const categoryUnits = {
            'Beverages': ['ml', 'L', 'pc', 'pack'],
            'Canned Goods': ['g', 'kg', 'pc', 'pack'],
            'Condiments': ['g', 'kg', 'ml', 'L', 'pc', 'pack'],
            'Dairy': ['g', 'kg', 'ml', 'L', 'pc', 'pack'],
            'Fresh Produce': ['g', 'kg', 'pc', 'pack'],
            'Noodles': ['g', 'kg', 'pc', 'pack'],
            'Snacks': ['g', 'kg', 'pc', 'pack'],
            'Cooking Essentials': ['g', 'kg', 'ml', 'L', 'pc', 'pack'],
            'Meat & Poultry': ['g', 'kg', 'pack']
        };

        const allUnits = {
            'g': 'Grams (g)',
            'kg': 'Kilograms (kg)',
            'ml': 'Milliliters (ml)',
            'L': 'Liters (L)',
            'pc': 'Piece (pc)',
            'pack': 'Pack'
        };

        let initialUnit = umSelect.value;
        let isInitialLoad = true;

        function updateUnits() {
            const selectedCategory = catSelect.value;
            const currentUnit = isInitialLoad ? initialUnit : umSelect.value;
            
            umSelect.innerHTML = '<option value="">None</option>';
            
            const allowedUnits = categoryUnits[selectedCategory] || Object.keys(allUnits);
            
            allowedUnits.forEach(unit => {
                const opt = document.createElement('option');
                opt.value = unit;
                opt.textContent = allUnits[unit];
                if (unit === currentUnit) opt.selected = true;
                umSelect.appendChild(opt);
            });
            
            isInitialLoad = false;
        }

        catSelect.addEventListener('change', updateUnits);
        updateUnits();
    }
})();
</script>

<?php require_once '../../includes/inventory_footer.php'; ?>