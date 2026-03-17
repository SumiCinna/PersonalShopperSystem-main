<?php
// modules/inventory/dashboard.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'inventory') {
    header("Location: ../../inventory-login.php");
    exit();
}

// --- INVENTORY METRICS ---

// 1. Total Products Count
$totalProductsQuery = "SELECT COUNT(*) as count FROM products";
$totalProductsResult = $conn->query($totalProductsQuery);
$totalProducts = $totalProductsResult->fetch_assoc()['count'];

// 2. Total Stock Value (Cost Price * Stock)
$totalStockValueQuery = "SELECT SUM(cost_price * stock) as total_value FROM products WHERE status = 'active'";
$totalStockValueResult = $conn->query($totalStockValueQuery);
$totalStockValue = $totalStockValueResult->fetch_assoc()['total_value'] ?? 0;

// 3. Low Stock Items (stock <= low_stock_threshold)
$lowStockQuery = "SELECT COUNT(*) as count FROM products WHERE status = 'active' AND stock <= low_stock_threshold AND stock > 0";
$lowStockResult = $conn->query($lowStockQuery);
$lowStockCount = $lowStockResult->fetch_assoc()['count'];

// 4. Out of Stock Items
$outOfStockQuery = "SELECT COUNT(*) as count FROM products WHERE status = 'active' AND stock = 0";
$outOfStockResult = $conn->query($outOfStockQuery);
$outOfStockCount = $outOfStockResult->fetch_assoc()['count'];

// 5. Active Products
$activeProductsQuery = "SELECT COUNT(*) as count FROM products WHERE status = 'active'";
$activeProductsResult = $conn->query($activeProductsQuery);
$activeProducts = $activeProductsResult->fetch_assoc()['count'];

// 6. Get Low Stock Items for the table
$lowStockItemsQuery = "SELECT product_id, name, sku, stock, low_stock_threshold FROM products WHERE status = 'active' AND stock <= low_stock_threshold AND stock > 0 ORDER BY stock ASC LIMIT 10";
$lowStockItemsResult = $conn->query($lowStockItemsQuery);

// 7. Get Out of Stock Items for the table
$outOfStockItemsQuery = "SELECT product_id, name, sku FROM products WHERE status = 'active' AND stock = 0 ORDER BY product_id DESC LIMIT 10";
$outOfStockItemsResult = $conn->query($outOfStockItemsQuery);

$page_title = 'Inventory Dashboard';
require_once '../../includes/inventory_header.php';
?>

<main class="flex-1 p-8 overflow-y-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Inventory Dashboard</h1>
        <p class="text-gray-500 text-sm mt-1">Real-time inventory overview and status</p>
    </div>

    <!-- Key Metrics Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <!-- Total Products Card -->
        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl shadow-sm p-6 border border-blue-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-blue-600 text-xs font-semibold uppercase tracking-wide">Total Products</p>
                    <p class="text-3xl font-bold text-blue-900 mt-2"><?php echo $totalProducts; ?></p>
                </div>
                <svg class="w-12 h-12 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                </svg>
            </div>
        </div>

        <!-- Active Products Card -->
        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl shadow-sm p-6 border border-green-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-green-600 text-xs font-semibold uppercase tracking-wide">Active Items</p>
                    <p class="text-3xl font-bold text-green-900 mt-2"><?php echo $activeProducts; ?></p>
                </div>
                <svg class="w-12 h-12 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Low Stock Alert Card -->
        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl shadow-sm p-6 border border-yellow-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-yellow-600 text-xs font-semibold uppercase tracking-wide">Low Stock</p>
                    <p class="text-3xl font-bold text-yellow-900 mt-2"><?php echo $lowStockCount; ?></p>
                </div>
                <svg class="w-12 h-12 text-yellow-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4v2m0 4v2M6.75 15H9m9 0h-2.25M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                </svg>
            </div>
        </div>

        <!-- Out of Stock Card -->
        <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl shadow-sm p-6 border border-red-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-red-600 text-xs font-semibold uppercase tracking-wide">Out of Stock</p>
                    <p class="text-3xl font-bold text-red-900 mt-2"><?php echo $outOfStockCount; ?></p>
                </div>
                <svg class="w-12 h-12 text-red-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>

        <!-- Stock Value Card -->
        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl shadow-sm p-6 border border-purple-200">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-purple-600 text-xs font-semibold uppercase tracking-wide">Stock Value</p>
                    <p class="text-2xl font-bold text-purple-900 mt-2">₱<?php echo number_format($totalStockValue, 2); ?></p>
                </div>
                <svg class="w-12 h-12 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Two Column Layout for Detailed Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Low Stock Items Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-yellow-50 to-orange-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 4v2M6.75 15H9m9 0h-2.25M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                    </svg>
                    Low Stock Alert
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($lowStockItemsResult->num_rows > 0): ?>
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">SKU</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($row = $lowStockItemsResult->fetch_assoc()): ?>
                                <tr class="hover:bg-yellow-50 transition">
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="px-6 py-3 text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($row['sku']); ?></td>
                                    <td class="px-6 py-3 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <?php echo $row['stock']; ?> / <?php echo $row['low_stock_threshold']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-500 text-sm">All products have healthy stock levels!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Out of Stock Items Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gradient-to-r from-red-50 to-pink-50 px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Out of Stock
                </h2>
            </div>
            <div class="overflow-x-auto">
                <?php if ($outOfStockItemsResult->num_rows > 0): ?>
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200">
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Product Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">SKU</th>
                                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php while ($row = $outOfStockItemsResult->fetch_assoc()): ?>
                                <tr class="hover:bg-red-50 transition">
                                    <td class="px-6 py-3 text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td class="px-6 py-3 text-sm text-gray-500 font-mono"><?php echo htmlspecialchars($row['sku']); ?></td>
                                    <td class="px-6 py-3 text-center">
                                        <a href="edit_product.php?id=<?php echo $row['product_id']; ?>" class="inline-flex items-center px-3 py-1 text-xs font-semibold rounded-md bg-blue-100 text-blue-700 hover:bg-blue-200 transition">
                                            Restock
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <p class="text-gray-500 text-sm">No out-of-stock items. Great job!</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="products.php" class="flex items-center justify-center gap-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
            </svg>
            View All Products
        </a>
        <a href="add_product.php" class="flex items-center justify-center gap-3 bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition shadow-sm">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Add New Product
        </a>
    </div>
</main>

<?php require_once '../../includes/inventory_footer.php'; ?>
<?php $conn->close(); ?>
