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
$totalProductsQuery  = "SELECT COUNT(*) as count FROM products";
$totalProducts       = $conn->query($totalProductsQuery)->fetch_assoc()['count'];

$totalStockValueQuery = "SELECT SUM(cost_price * stock) as total_value FROM products WHERE status = 'active'";
$totalStockValue      = $conn->query($totalStockValueQuery)->fetch_assoc()['total_value'] ?? 0;

$lowStockQuery  = "SELECT COUNT(*) as count FROM products WHERE status = 'active' AND stock <= low_stock_threshold AND stock > 0";
$lowStockCount  = $conn->query($lowStockQuery)->fetch_assoc()['count'];

$outOfStockQuery  = "SELECT COUNT(*) as count FROM products WHERE status = 'active' AND stock = 0";
$outOfStockCount  = $conn->query($outOfStockQuery)->fetch_assoc()['count'];

$activeProductsQuery = "SELECT COUNT(*) as count FROM products WHERE status = 'active'";
$activeProducts      = $conn->query($activeProductsQuery)->fetch_assoc()['count'];

$lowStockItemsQuery  = "SELECT product_id, name, sku, stock, low_stock_threshold FROM products WHERE status = 'active' AND stock <= low_stock_threshold AND stock > 0 ORDER BY stock ASC LIMIT 10";
$lowStockItemsResult = $conn->query($lowStockItemsQuery);

$outOfStockItemsQuery  = "SELECT product_id, name, sku FROM products WHERE status = 'active' AND stock = 0 ORDER BY product_id DESC LIMIT 10";
$outOfStockItemsResult = $conn->query($outOfStockItemsQuery);

// --- AUDIT TRAIL ---
// FIX: Product name comes ONLY from LEFT JOIN products — no al.product_name column referenced.
// Deleted products show "Deleted Product #ID".
$auditQuery = "
    SELECT
        al.log_id,
        al.action,
        al.product_id,
        COALESCE(p.name, CONCAT('Deleted Product #', al.product_id)) AS product_name,
        al.field_changed,
        al.old_value,
        al.new_value,
        u.username AS done_by,
        al.created_at
    FROM activity_logs al
    JOIN users u ON al.user_id = u.user_id
    LEFT JOIN products p ON al.product_id = p.product_id
    ORDER BY al.created_at DESC
    LIMIT 3
";
$auditResult = $conn->query($auditQuery);

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

    <!-- Low Stock + Out of Stock Tables -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Low Stock -->
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

        <!-- Out of Stock -->
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

    <!-- AUDIT TRAIL -->
    <div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-50 to-blue-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                </svg>
                Inventory Audit Trail
            </h2>
            <a href="inventory_audit.php"
               class="text-xs text-indigo-600 font-semibold bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-full transition">
                View Full Audit →
            </a>
        </div>
        <div class="overflow-x-auto">
            <?php if ($auditResult && $auditResult->num_rows > 0): ?>
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Field Changed</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Old Value</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">New Value</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Done By</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while ($log = $auditResult->fetch_assoc()):
                            $badge = match($log['action']) {
                                'add'    => ['bg-green-100 text-green-800',   '+ Added'],
                                'update' => ['bg-yellow-100 text-yellow-800', '~ Updated'],
                                'delete' => ['bg-red-100 text-red-800',       '✕ Deleted'],
                                default  => ['bg-gray-100 text-gray-600',     $log['action']],
                            };
                            $is_deleted_product = str_starts_with($log['product_name'], 'Deleted Product #');
                            $field_normalized = strtolower((string)($log['field_changed'] ?? ''));
                            $is_price_field = in_array($field_normalized, ['cost_price', 'selling_price', 'cost price', 'selling price'], true);
                            $new_value_display = $log['new_value'];
                            if ($is_price_field && $log['new_value'] !== null && $log['new_value'] !== '' && is_numeric($log['new_value'])) {
                                $new_value_display = number_format((float)$log['new_value'], 2, '.', '');
                            }
                        ?>
                            <tr class="hover:bg-indigo-50 transition">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $badge[0]; ?>">
                                        <?php echo $badge[1]; ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                    <?php if ($is_deleted_product): ?>
                                        <span class="text-gray-400 italic text-xs"><?php echo htmlspecialchars($log['product_name']); ?></span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($log['product_name']); ?>
                                        <?php if ($log['product_id'] && $log['action'] !== 'delete'): ?>
                                            <a href="edit_product.php?id=<?php echo $log['product_id']; ?>" class="ml-1 text-indigo-400 hover:text-indigo-600 text-xs">#<?php echo $log['product_id']; ?></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 font-medium">
                                    <?php echo $log['field_changed'] ? htmlspecialchars($log['field_changed']) : '<span class="text-gray-300">—</span>'; ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($log['old_value']): ?>
                                        <span class="text-red-500 line-through"><?php echo htmlspecialchars($log['old_value']); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php if ($log['new_value']): ?>
                                        <span class="text-green-600 font-medium"><?php echo htmlspecialchars((string)$new_value_display); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-300">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <div class="flex items-center gap-2">
                                        
                                        </span>
                                        <?php echo htmlspecialchars($log['done_by']); ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span class="font-medium text-gray-700"><?php echo date('M d, Y', strtotime($log['created_at'])); ?></span>
                                        <span class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($log['created_at'])); ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="px-6 py-12 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p class="text-gray-500 text-sm">No activity logged yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
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