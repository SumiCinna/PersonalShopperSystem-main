<?php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header('Location: ../../inventory-login.php');
    exit();
}

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $contact_person = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $categories = $_POST['categories'] ?? []; // Array of categories

    if (empty($name)) {
        $msg = "Supplier name is required.";
        $msgType = "error";
    } else {
        $encodedCategories = json_encode($categories);
        
        $sql = "INSERT INTO suppliers (name, contact_person, phone, email, address, supplied_categories, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssssss', $name, $contact_person, $phone, $email, $address, $encodedCategories);
        
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
                <input type="text" name="name" required class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500 hover:border-blue-400">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-1">Contact Person</label>
                    <input type="text" name="contact_person" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Phone</label>
                    <input type="text" name="phone" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold mb-1">Email</label>
                    <input type="email" name="email" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-semibold mb-1">Address</label>
                    <input type="text" name="address" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-blue-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold mb-1">Supplied Categories</label>
                <p class="text-xs text-slate-500 mb-2">Select the categories that this supplier can provide. They will automatically filter in the PO creation.</p>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-sm mt-3">
                    <?php 
                    $categories = [
                        'Beverages', 'Canned Goods', 'Condiments', 'Dairy', 
                        'Fresh Produce', 'Noodles', 'Snacks', 'Cooking Essentials', 
                        'Meat & Poultry'
                    ];
                    foreach ($categories as $cat) {
                        echo "<label class='flex items-center gap-2'><input type='checkbox' name='categories[]' value='{$cat}' class='rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4'> " . htmlspecialchars($cat) . "</label>";
                    }
                    ?>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2.5 rounded-lg hover:bg-blue-700 transition">Save Supplier</button>
            </div>
        </form>
    </div>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>
