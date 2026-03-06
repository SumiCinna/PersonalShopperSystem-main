<?php
// modules/admin/dashboard.php
session_start();
require_once '../../config/config.php';

// --- STRICT SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If a cashier or customer tries to access this, kick them out!
    header("Location: ../../index.php");
    exit();
}

// --- 1. OVERVIEW METRICS ---

// Total Lifetime Revenue (From the new invoices table!)
$revenue_query = "SELECT SUM(grand_total) as total_revenue FROM invoices";
$total_revenue = $conn->query($revenue_query)->fetch_assoc()['total_revenue'] ?? 0;

// Total Orders Processed
$orders_query = "SELECT COUNT(*) as total_orders FROM orders WHERE order_status = 'completed'";
$total_orders = $conn->query($orders_query)->fetch_assoc()['total_orders'] ?? 0;

// Total Active Products in Catalog
$products_query = "SELECT COUNT(*) as total_products FROM products WHERE status = 'active'";
$total_products = $conn->query($products_query)->fetch_assoc()['total_products'] ?? 0;


// --- 2. ACTIONABLE ALERTS: LOW STOCK ITEMS ---
$low_stock_query = "SELECT product_id, name, sku, stock, low_stock_threshold 
                    FROM products 
                    WHERE stock <= low_stock_threshold AND status = 'active'
                    ORDER BY stock ASC LIMIT 10";
$low_stock_result = $conn->query($low_stock_query);


$page_title = 'Admin Dashboard';
require_once '../../includes/admin_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-slate-50">
    
    <div class="mb-8 border-b border-slate-200 pb-4">
        <h1 class="text-3xl font-black text-slate-900 tracking-tight">System Overview</h1>
        <p class="text-slate-500 mt-1">Monitor your business metrics and inventory health.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center relative overflow-hidden">
            <div class="absolute right-0 top-0 h-full w-2 bg-green-500"></div>
            <div class="p-4 bg-green-50 rounded-full mr-5 text-green-600 border border-green-100">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Lifetime Revenue</p>
                <p class="text-3xl font-black text-slate-800 mt-1">₱<?php echo number_format($total_revenue, 2); ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center relative overflow-hidden">
            <div class="absolute right-0 top-0 h-full w-2 bg-blue-500"></div>
            <div class="p-4 bg-blue-50 rounded-full mr-5 text-blue-600 border border-blue-100">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Completed Orders</p>
                <p class="text-3xl font-black text-slate-800 mt-1"><?php echo number_format($total_orders); ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex items-center relative overflow-hidden">
            <div class="absolute right-0 top-0 h-full w-2 bg-purple-500"></div>
            <div class="p-4 bg-purple-50 rounded-full mr-5 text-purple-600 border border-purple-100">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
            </div>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Active Catalog</p>
                <p class="text-3xl font-black text-slate-800 mt-1"><?php echo number_format($total_products); ?> <span class="text-sm text-slate-400 font-normal">Items</span></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <div class="xl:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="bg-red-50 border-b border-red-100 p-5 flex justify-between items-center">
                    <h2 class="text-lg font-bold text-red-800 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        Critical Inventory Alerts
                    </h2>
                    <a href="products.php" class="text-sm text-red-600 hover:text-red-800 font-semibold underline">Manage Inventory &rarr;</a>
                </div>
                
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <th class="p-4 font-bold">SKU</th>
                            <th class="p-4 font-bold">Product Name</th>
                            <th class="p-4 font-bold text-center">Threshold</th>
                            <th class="p-4 font-bold text-center">Current Stock</th>
                            <th class="p-4 font-bold text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if ($low_stock_result->num_rows > 0): ?>
                            <?php while ($item = $low_stock_result->fetch_assoc()): ?>
                                <tr class="hover:bg-red-50/50 transition">
                                    <td class="p-4 font-mono text-xs font-bold text-slate-600">
                                        <?php echo htmlspecialchars($item['sku']); ?>
                                    </td>
                                    <td class="p-4 font-bold text-slate-800">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </td>
                                    <td class="p-4 text-center text-slate-500 text-sm font-semibold">
                                        <?php echo $item['low_stock_threshold']; ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <span class="font-black text-lg <?php echo $item['stock'] == 0 ? 'text-red-600' : 'text-orange-500'; ?>">
                                            <?php echo $item['stock']; ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if ($item['stock'] == 0): ?>
                                            <span class="bg-red-100 text-red-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="bg-orange-100 text-orange-800 text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="p-12 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto text-green-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <p class="font-bold text-lg text-slate-700">Inventory is healthy!</p>
                                    <p class="text-sm">No products are currently below their low stock thresholds.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="xl:col-span-1 space-y-6">
            
            <div class="bg-slate-900 rounded-xl shadow-lg p-6 text-white">
                <h3 class="font-black tracking-widest uppercase text-slate-400 text-xs mb-4">Quick Actions</h3>
                
                <a href="products.php?action=add" class="w-full mb-3 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-4 rounded transition flex items-center shadow">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                    Add New Product
                </a>
                
                <a href="sales_report.php" class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white font-bold py-3 px-4 rounded transition flex items-center shadow">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Generate Sales Report
                </a>
            </div>

        </div>

    </div>

</main>

<?php 
require_once '../../includes/admin_footer.php'; 
$conn->close(); 
?>