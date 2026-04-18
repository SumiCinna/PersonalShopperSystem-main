<?php
session_start();
require_once '../../config/config.php';
// modules/inventory/edit_product.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

$error   = '';
$success = '';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = intval($_GET['id']);

function log_activity($conn, $user_id, $action, $product_id, $product_name, $field = null, $old = null, $new = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, product_id, product_name, field_changed, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isissss", $user_id, $action, $product_id, $product_name, $field, $old, $new);
    $stmt->execute();
    $stmt->close();
}

function normalize_compare_value(string $field_key, $value): string {
    if ($value === null) return '';
    if (in_array($field_key, ['cost_price', 'price', 'discount_price', 'unit_value'], true)) {
        if ($value === '' || $value === false) return '';
        return number_format((float) $value, 2, '.', '');
    }
    if (in_array($field_key, ['stock', 'low_stock_threshold', 'pcs_per_box'], true)) {
        if ($value === '' || $value === false) return '';
        return (string) ((int) $value);
    }
    return trim((string) $value);
}

function format_audit_value(string $field_key, $value): string {
    if ($value === null || $value === '') return '—';
    if (in_array($field_key, ['cost_price', 'price', 'discount_price', 'unit_value'], true)) {
        return number_format((float) $value, 2, '.', '');
    }
    return (string) $value;
}

$fetch_stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
$fetch_stmt->bind_param("i", $product_id);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();
$fetch_stmt->close();

$batches_stmt = $conn->prepare("SELECT * FROM product_batches WHERE product_id = ? AND status = 'Released' AND remaining_quantity > 0 ORDER BY expiry_date ASC");
$batches_stmt->bind_param("i", $product_id);
$batches_stmt->execute();
$active_batches = $batches_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$batches_stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku                 = trim($_POST['sku']);
    $name                = trim($_POST['name']);
    $brand               = trim($_POST['brand']);
    $category            = trim($_POST['category']);
    $cost_price          = floatval($_POST['cost_price']);
    $price               = floatval($_POST['price']);
    $discount_price      = !empty($_POST['discount_price']) ? floatval($_POST['discount_price']) : NULL;
    $unit_value          = !empty($_POST['unit_value']) ? floatval($_POST['unit_value']) : NULL;
    $unit_measure        = !empty($_POST['unit_measure']) ? $_POST['unit_measure'] : NULL;
    $stock               = intval($_POST['stock']);
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $pcs_per_box         = max(1, intval($_POST['pcs_per_box'] ?? 1));
    $status              = $_POST['status'];
    $description         = trim($_POST['description']);
    $image_url           = trim($_POST['image_url']);

    if (empty($sku) || empty($name) || empty($category)) {
        $error = "SKU, Product Name, and Category are required.";
    } elseif ($cost_price >= $price) {
        $error = "Cost price must be lower than the regular selling price.";
    } else {
        $check_stmt = $conn->prepare("SELECT product_id FROM products WHERE sku = ? AND product_id != ?");
        $check_stmt->bind_param("si", $sku, $product_id);
        $check_stmt->execute();

        if ($check_stmt->get_result()->num_rows > 0) {
            $error = "Another product is already using this SKU! Please use a unique SKU.";
        } else {
            $watched_fields = [
                'name'                => ['label' => 'Product Name',  'old' => $product['name'],                'new' => $name],
                'sku'                 => ['label' => 'SKU',            'old' => $product['sku'],                 'new' => $sku],
                'brand'               => ['label' => 'Brand',          'old' => $product['brand'],               'new' => $brand],
                'category'            => ['label' => 'Category',       'old' => $product['category'],            'new' => $category],
                'cost_price'          => ['label' => 'Cost Price',     'old' => $product['cost_price'],          'new' => $cost_price],
                'price'               => ['label' => 'Selling Price',  'old' => $product['price'],               'new' => $price],
                'discount_price'      => ['label' => 'Discount Price', 'old' => $product['discount_price'],      'new' => $discount_price],
                'stock'               => ['label' => 'Stock',          'old' => $product['stock'],               'new' => $stock],
                'low_stock_threshold' => ['label' => 'Low Stock At',   'old' => $product['low_stock_threshold'], 'new' => $low_stock_threshold],
                'pcs_per_box'         => ['label' => 'Pcs per Box',    'old' => $product['pcs_per_box'] ?? 1,    'new' => $pcs_per_box],
                'status'              => ['label' => 'Status',         'old' => $product['status'],              'new' => $status],
                'unit_value'          => ['label' => 'Unit Value',     'old' => $product['unit_value'],          'new' => $unit_value],
                'unit_measure'        => ['label' => 'Unit Measure',   'old' => $product['unit_measure'],        'new' => $unit_measure],
                'description'         => ['label' => 'Description',    'old' => $product['description'],         'new' => $description],
                'image_url'           => ['label' => 'Image URL',      'old' => $product['image_url'],           'new' => $image_url],
            ];

            $changed_fields = [];
            foreach ($watched_fields as $field_key => $field_data) {
                $old_normalized = normalize_compare_value($field_key, $field_data['old']);
                $new_normalized = normalize_compare_value($field_key, $field_data['new']);
                if ($old_normalized !== $new_normalized) {
                    $changed_fields[$field_key] = $field_data;
                }
            }

            if (empty($changed_fields)) {
                $success = "No changes detected. Nothing was updated.";
                $check_stmt->close();
                goto end_post;
            }

            $update_query = "UPDATE products SET 
                sku = ?, name = ?, brand = ?, category = ?, cost_price = ?, price = ?, discount_price = ?,
                unit_value = ?, unit_measure = ?, stock = ?, low_stock_threshold = ?, pcs_per_box = ?,
                status = ?, description = ?, image_url = ? 
                WHERE product_id = ?";

            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("ssssddddsiiisssi",
                $sku, $name, $brand, $category,
                $cost_price, $price, $discount_price,
                $unit_value, $unit_measure,
                $stock, $low_stock_threshold, $pcs_per_box,
                $status, $description, $image_url,
                $product_id
            );

            if ($stmt->execute()) {
                $success = "Product updated successfully!";

                foreach ($changed_fields as $field_key => $field_data) {
                    log_activity(
                        $conn,
                        $_SESSION['user_id'],
                        'update',
                        $product_id,
                        $name,
                        $field_data['label'],
                        format_audit_value($field_key, $field_data['old']),
                        format_audit_value($field_key, $field_data['new'])
                    );
                }

                $fetch_stmt2 = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
                $fetch_stmt2->bind_param("i", $product_id);
                $fetch_stmt2->execute();
                $product = $fetch_stmt2->get_result()->fetch_assoc();
                $fetch_stmt2->close();

            } else {
                $error = "Error updating product: " . $conn->error;
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

end_post:

$page_title = 'Edit Product: ' . htmlspecialchars($product['name']);
require_once '../../includes/inventory_header.php';
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
                        <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" required maxlength="50" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none uppercase font-mono bg-gray-50">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required maxlength="50" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Brand</label>
                            <input type="text" name="brand" value="<?php echo htmlspecialchars($product['brand'] ?? ''); ?>" maxlength="50" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Category *</label>
                            <select name="category" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                                <?php
                                $categories = ['Beverages','Canned Goods','Condiments','Dairy','Fresh Produce','Noodles','Snacks','Cooking Essentials','Meat & Poultry'];
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
                            <input type="text" inputmode="decimal" name="unit_value" value="<?php echo htmlspecialchars($product['unit_value'] ?? ''); ?>" pattern="^\d{1,5}(\.\d{1,2})?$"
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
                                <?php
                                $units = ['' => 'None','g' => 'Grams (g)','kg' => 'Kilograms (kg)','ml' => 'Milliliters (ml)','L' => 'Liters (L)','pc' => 'Piece (pc)','pack' => 'Pack'];
                                foreach ($units as $val => $label) {
                                    $selected = ($product['unit_measure'] === $val) ? 'selected' : '';
                                    echo "<option value=\"$val\" $selected>$label</option>";
                                }
                                ?>
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
                            <input type="number" name="pcs_per_box" id="pcs_per_box" 
                                value="<?php echo intval($product['pcs_per_box'] ?? 1); ?>" 
                                min="1" max="9999"
                                oninput="if(parseInt(this.value) < 1 || !this.value) this.value = 1; if(this.value.length > 4) this.value = this.value.slice(0,4);"
                                class="w-32 px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold text-center">
                            <span class="text-sm text-gray-500">pcs / box</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Set to 1 if not sold in boxes or N/A.</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-blue-900 border-b pb-2">Pricing & Inventory</h3>

                    <?php if (!empty($active_batches)): ?>
                    <div class="col-span-2 mb-4 p-4 border border-blue-200 bg-blue-50 rounded-xl relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-1 h-full bg-blue-500"></div>
                        <h4 class="text-sm font-black text-blue-900 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 11v9a2 2 0 01-2 2H7a2 2 0 01-2-2v-9m14 0h-4M5 11H1"></path></svg>
                            Active Batches (FEFO Breakdown)
                        </h4>
                        <div class="space-y-2">
                            <?php foreach($active_batches as $b): 
                                $days_to_expire = (strtotime($b['expiry_date']) - time()) / 86400;
                                $warning = '';
                                if ($days_to_expire < 30 && $days_to_expire > 0) $warning = '<span class="text-[10px] bg-amber-200 text-amber-900 px-1.5 py-0.5 rounded font-bold ml-2 uppercase tracking-tight shadow-sm border border-amber-300">Near Expiry</span>';
                                elseif ($days_to_expire <= 0) $warning = '<span class="text-[10px] bg-red-200 text-red-900 px-1.5 py-0.5 rounded font-bold ml-2 uppercase tracking-tight shadow-sm border border-red-300">Expired</span>';
                            ?>
                            <div class="text-[13px] text-slate-700 flex justify-between items-center bg-white px-3 py-2 rounded shadow-sm border border-blue-100">
                                <div>
                                    <div class="mb-0.5">
                                        <span class="font-black text-blue-800 tabular-nums">&#10003; <?php echo $b['remaining_quantity']; ?> pcs</span> 
                                        <?php echo $warning; ?>
                                    </div>
                                    <div class="text-xs text-slate-600 block sm:inline-block">
                                        <span class="font-semibold text-slate-700">Manufacture Date:</span> <?php echo $b['manufacture_date'] ? date('M d, Y', strtotime($b['manufacture_date'])) : 'N/A'; ?>
                                        <span class="text-slate-300 mx-1 hidden sm:inline-block">|</span>
                                    </div>
                                    <div class="text-xs text-slate-600 block sm:inline-block">
                                        <span class="font-semibold text-slate-700">Expiration Date:</span> <?php echo $b['expiry_date'] ? date('M d, Y', strtotime($b['expiry_date'])) : 'N/A'; ?>
                                    </div>
                                </div>
                                <span class="text-[10px] text-slate-400 font-mono tracking-widest hidden sm:inline-block">BATCH #<?php echo $b['batch_number']; ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Cost Price (₱) *</label>
                            <input type="text" inputmode="decimal" name="cost_price" id="cost_price" value="<?php echo htmlspecialchars($product['cost_price']); ?>" required pattern="^\d{1,5}(\.\d{1,2})?$"
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
                            <input type="text" inputmode="decimal" name="price" id="price" value="<?php echo htmlspecialchars($product['price']); ?>" required pattern="^\d{1,5}(\.\d{1,2})?$"
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
                            <input type="number" step="0.01" min="0" max="99999.99" name="discount_price" value="<?php echo htmlspecialchars($product['discount_price'] ?? ''); ?>"
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
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Current Stock *</label>
                            <input type="number" name="stock" value="<?php echo htmlspecialchars($product['stock']); ?>" required min="0" max="99999"
                                oninput="if(this.value.length > 5) this.value = this.value.slice(0,5); if(parseInt(this.value) > 99999) this.value = 99999;"
                                class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none font-bold">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Low Stock Warning At *</label>
                            <input type="number" name="low_stock_threshold" value="<?php echo htmlspecialchars($product['low_stock_threshold']); ?>" required min="0" max="99999"
                                oninput="if(this.value.length > 5) this.value = this.value.slice(0,5); if(parseInt(this.value) > 99999) this.value = 99999;"
                                class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none text-red-600 font-semibold">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Product Status *</label>
                        <select name="status" required class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white">
                            <option value="active"   <?php echo ($product['status'] === 'active')   ? 'selected' : ''; ?>>Active (Visible to Customers)</option>
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
                <textarea name="description" rows="3" maxlength="100" class="w-full px-4 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500 focus:outline-none"><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
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

<script>
(function () {
    const costInput = document.getElementById('cost_price');
    const sellInput = document.getElementById('price');
    const feedback = document.getElementById('price_validation_feedback');
    const form = document.querySelector('form[action^="edit_product.php"]');

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