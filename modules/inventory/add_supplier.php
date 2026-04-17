<?php
session_start();
require_once '../../config/config.php';
// modules/inventory/add_supplier.php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header('Location: ../../inventory-login.php');
    exit();
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $supplier_type = trim($_POST['supplier_type'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $phone = '+63' . trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $categories = $_POST['categories'] ?? []; // Array of categories        
    $new_category = trim($_POST['new_category'] ?? '');

    if (!empty($new_category)) {
        $categories[] = $new_category;
    }

    if (empty($name) || empty($supplier_type) || empty($contact_person) || empty($position)) {
        $msg = "All required fields must be filled.";
        $msgType = "error";
    } elseif (empty($categories)) {
        $msg = "Please select at least one category, or add a new one.";
        $msgType = "error";
    } else {
        $encodedCategories = json_encode($categories);

        $sql = "INSERT INTO suppliers (name, supplier_type, contact_person, position, phone, email, address, supplied_categories, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssssss', $name, $supplier_type, $contact_person, $position, $phone, $email, $address, $encodedCategories);

        if ($stmt->execute()) {
            $msg = "Supplier successfully added.";
            $msgType = "success";
        } else {
            $msg = "Error adding supplier: " . $conn->error;
            $msgType = "error";
        }
        $stmt->close();
    }
}

$page_title = 'Add Supplier';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50 text-slate-800">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center">
        <div>
            <h1 class="text-3xl font-bold">Add Supplier</h1>
            <p class="text-slate-500 mt-1">Register a new supplier and configure their product categories.</p>
        </div>
        <a href="purchase_orders.php" class="mt-4 md:mt-0 px-4 py-2 bg-slate-200 text-slate-800 rounded font-semibold hover:bg-slate-300">Back to PO</a>
    </div>

    <?php if ($msg): ?>
        <div class="mb-4 rounded-lg border px-4 py-3 text-sm <?php echo $msgType === 'error' ? 'border-red-200 bg-red-50 text-red-700' : 'border-green-200 bg-green-50 text-green-700'; ?>">
            <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <div class="w-full bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <form method="POST" class="p-6 space-y-5">
            <div>
                <label class="block text-sm font-semibold mb-1">Company/Supplier Name</label>
                <input type="text" name="name" maxlength="50" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 hover:border-blue-400" placeholder="Enter company name (max 50 chars)">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Supplier Type</label>
                <select name="supplier_type" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                    <option value="">Select type...</option>
                    <option value="Manufacturer">Manufacturer</option>
                    <option value="Wholesaler">Wholesaler</option>
                    <option value="Distributor">Distributor</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-1">Contact Person</label>
                    <input type="text" name="contact_person" maxlength="50" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500" placeholder="Max 50 chars">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Position</label>
                    <input type="text" name="position" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500" placeholder="e.g., Sales Manager">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-1">Phone</label>
                    <div class="flex items-center">
                        <span class="flex-shrink-0 bg-slate-100 border border-r-0 border-slate-300 rounded-l-lg px-3 py-2 text-sm font-semibold text-slate-600">+63</span>
                        <input type="text" name="phone" pattern="^9[0-9]{9}$" title="Must be exactly 10 digits starting with 9" maxlength="10" required class="w-full border border-slate-300 rounded-r-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500" placeholder="9xxxxxxxxx">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" pattern="^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.com$" title="Must be a valid email ending with .com" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500" placeholder="example@domain.com">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Address</label>
                <input type="text" name="address" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Categories (Required)</label>
                <p class="text-xs text-slate-500 mb-2">Select at least one category that this supplier can provide.</p>
                
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-sm mt-3 mb-4 category-group">
                    <?php 
                    $categoriesList = [
                        'Beverages', 'Canned Goods', 'Condiments', 'Dairy', 
                        'Fresh Produce', 'Noodles', 'Snacks', 'Cooking Essentials', 
                        'Meat & Poultry'
                    ];
                    foreach ($categoriesList as $cat) {
                        echo "<label class='flex items-center gap-2'><input type='checkbox' name='categories[]' value='{$cat}' class='category-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4'> " . htmlspecialchars($cat) . "</label>";
                    }
                    ?>
                </div>

                <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg">
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Add Category (Optional)</label>
                    <input type="text" name="new_category" id="new_category" class="w-full border border-slate-300 rounded px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500" placeholder="Type a custom category name...">
                    <p class="text-[10px] text-slate-500 mt-1">If the category isn't in the list above, you can add it here.</p>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-lg hover:bg-blue-700 transition">Save Supplier</button>
            </div>
        </form>
    </div>
</main>

<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.category-checkbox:checked').length;
    const customCategory = document.getElementById('new_category').value.trim();
    
    if (checked === 0 && customCategory === '') {
        e.preventDefault();
        alert('Please select at least one existing category or add a new custom category before saving.');
    }
});
</script>

<?php require_once '../../includes/inventory_footer.php'; ?>
