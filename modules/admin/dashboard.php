<?php
// modules/admin/dashboard.php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../config/config.php';

// --- STRICT SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../index.php");
    exit();
}

// ============================================================
// 1. OVERVIEW METRICS
// ============================================================

// Total Lifetime Revenue
$revenue_query = "SELECT SUM(grand_total) as total_revenue FROM invoices";
$total_revenue = $conn->query($revenue_query)->fetch_assoc()['total_revenue'] ?? 0;

// Total Completed Orders
$orders_query = "SELECT COUNT(*) as total_orders FROM orders WHERE order_status = 'completed'";
$total_orders = $conn->query($orders_query)->fetch_assoc()['total_orders'] ?? 0;

// Total Active Products
$products_query = "SELECT COUNT(*) as total_products FROM products WHERE status = 'active'";
$total_products = $conn->query($products_query)->fetch_assoc()['total_products'] ?? 0;

// Total Active Users (customers only)
$users_query = "SELECT COUNT(*) as total_users FROM users WHERE role = 'customer' AND status = 'active'";
$total_users = $conn->query($users_query)->fetch_assoc()['total_users'] ?? 0;

// Pending Orders Count
$pending_query = "SELECT COUNT(*) as pending FROM orders WHERE order_status IN ('pending','processing')";
$pending_orders = $conn->query($pending_query)->fetch_assoc()['pending'] ?? 0;

// Today's Revenue (from orders created today that are paid)
$today_revenue_query = "SELECT COALESCE(SUM(i.grand_total), 0) as today_revenue 
                         FROM invoices i 
                         JOIN orders o ON i.order_id = o.order_id 
                         WHERE DATE(i.issued_at) = CURDATE()";
$today_revenue = $conn->query($today_revenue_query)->fetch_assoc()['today_revenue'] ?? 0;

// ============================================================
// 2. CHART DATA: Revenue Last 7 Days
// ============================================================
$revenue_7days_query = "
    SELECT DATE(i.issued_at) as sale_date, COALESCE(SUM(i.grand_total), 0) as daily_revenue
    FROM invoices i
    WHERE i.issued_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(i.issued_at)
    ORDER BY sale_date ASC";
$revenue_7days_result = $conn->query($revenue_7days_query);
$revenue_labels = [];
$revenue_data = [];
// Build a full 7-day array (fill missing days with 0)
for ($i = 6; $i >= 0; $i--) {
    $revenue_labels[] = date('D, M j', strtotime("-$i days"));
    $revenue_data[date('Y-m-d', strtotime("-$i days"))] = 0;
}
while ($row = $revenue_7days_result->fetch_assoc()) {
    $revenue_data[$row['sale_date']] = (float)$row['daily_revenue'];
}
$revenue_chart_data = array_values($revenue_data);

// ============================================================
// 3. CHART DATA: Stock by Category
// ============================================================
$category_stock_query = "
    SELECT category, SUM(stock) as total_stock, COUNT(*) as product_count
    FROM products 
    WHERE status = 'active'
    GROUP BY category 
    ORDER BY total_stock DESC";
$category_stock_result = $conn->query($category_stock_query);
$cat_labels = [];
$cat_stock = [];
while ($row = $category_stock_result->fetch_assoc()) {
    $cat_labels[] = $row['category'];
    $cat_stock[] = (int)$row['total_stock'];
}

// ============================================================
// 4. CHART DATA: Orders by Status
// ============================================================
$order_status_query = "
    SELECT order_status, COUNT(*) as count 
    FROM orders 
    GROUP BY order_status";
$order_status_result = $conn->query($order_status_query);
$status_labels = [];
$status_counts = [];
while ($row = $order_status_result->fetch_assoc()) {
    $status_labels[] = ucfirst($row['order_status']);
    $status_counts[] = (int)$row['count'];
}

// ============================================================
// 5. CHART DATA: Payment Method Breakdown
// ============================================================
$payment_query = "
    SELECT payment_method, COUNT(*) as count 
    FROM orders 
    WHERE payment_status = 'paid' 
    GROUP BY payment_method";
$payment_result = $conn->query($payment_query);
$payment_labels = [];
$payment_counts = [];
while ($row = $payment_result->fetch_assoc()) {
    $pm_label = strtoupper($row['payment_method'] ?? 'Unknown');
    if ($pm_label === 'PREPAID') $pm_label = 'GCASH (QRPH)';
    $payment_labels[] = $pm_label;
    $payment_counts[] = (int)$row['count'];
}

// ============================================================
// 6. CHART DATA: Batches Expiring Soon (next 30 days)
// ============================================================
$expiry_query = "
    SELECT pb.expiry_date, p.name, pb.remaining_quantity,
           DATEDIFF(pb.expiry_date, CURDATE()) as days_left
    FROM product_batches pb
    JOIN products p ON pb.product_id = p.product_id
    WHERE pb.status = 'Released' 
      AND pb.remaining_quantity > 0
      AND pb.expiry_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 30 DAY
    ORDER BY pb.expiry_date ASC
    LIMIT 8";
$expiry_result = $conn->query($expiry_query);

// ============================================================
// 7. LOW STOCK ITEMS (existing)
// ============================================================
$low_stock_query = "SELECT product_id, name, sku, stock, low_stock_threshold, category
                    FROM products 
                    WHERE stock <= low_stock_threshold AND status = 'active'
                    ORDER BY stock ASC LIMIT 10";
$low_stock_result = $conn->query($low_stock_query);

// ============================================================
// 8. RECENT ORDERS (new)
// ============================================================
$recent_orders_query = "
    SELECT o.tracking_no, o.order_status, o.payment_status, o.total_amount, 
           o.created_at, o.payment_method,
           CONCAT(up.firstname, ' ', up.surname) as customer_name
    FROM orders o
    LEFT JOIN user_profiles up ON o.user_id = up.user_id
    ORDER BY o.created_at DESC LIMIT 5";
$recent_orders_result = $conn->query($recent_orders_query);

// ============================================================
// 9. TOP PRODUCTS BY STOCK VALUE (cost_price * stock)
// ============================================================
$top_value_query = "
    SELECT name, category, stock, price, (price * stock) as stock_value
    FROM products 
    WHERE status = 'active'
    ORDER BY stock_value DESC 
    LIMIT 5";
$top_value_result = $conn->query($top_value_query);
$top_prod_labels = [];
$top_prod_values = [];
while ($row = $top_value_result->fetch_assoc()) {
    $top_prod_labels[] = strlen($row['name']) > 20 ? substr($row['name'], 0, 18).'...' : $row['name'];
    $top_prod_values[] = (float)$row['stock_value'];
}

// ============================================================
// 10. SUPPLIER RETURNS SUMMARY
// ============================================================
$returns_query = "SELECT status, COUNT(*) as count FROM supplier_returns GROUP BY status";
$returns_result = $conn->query($returns_query);
$returns_pending = 0; $returns_resolved = 0; $returns_sent = 0;
while ($row = $returns_result->fetch_assoc()) {
    if ($row['status'] === 'pending_return') $returns_pending = $row['count'];
    if ($row['status'] === 'resolved') $returns_resolved = $row['count'];
    if ($row['status'] === 'returned_to_supplier') $returns_sent = $row['count'];
}

$page_title = 'Admin Dashboard';
require_once '../../includes/admin_header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
    <style>
        .stat-card { transition: transform 0.15s ease, box-shadow 0.15s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .chart-container { position: relative; width: 100%; }
        .badge-pending    { background:#FEF3C7; color:#92400E; }
        .badge-processing { background:#DBEAFE; color:#1E40AF; }
        .badge-ready      { background:#D1FAE5; color:#065F46; }
        .badge-completed  { background:#D1FAE5; color:#065F46; }
        .badge-cancelled  { background:#FEE2E2; color:#991B1B; }
        .badge-paid       { background:#D1FAE5; color:#065F46; }
        .badge-failed     { background:#FEE2E2; color:#991B1B; }
        .expiry-bar { height: 6px; border-radius: 3px; }
    </style>
</head>

<main class="flex-1 overflow-y-auto p-6 bg-slate-50">

    <!-- PAGE HEADER -->
    <div class="mb-6 border-b border-slate-200 pb-4 flex justify-between items-end">
        <div>
            <h1 class="text-3xl font-black text-slate-900 tracking-tight">System Overview</h1>
            <p class="text-slate-500 mt-1">Monitor your business metrics and inventory health — <?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="flex gap-2">
            <a href="sales_report.php" class="bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-sm font-bold py-2 px-4 rounded-lg transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Sales Report
            </a>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- ROW 1: KPI CARDS (6 cards)                                   -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">

        <div class="stat-card bg-white rounded-xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-1 w-full bg-green-500 rounded-t-xl"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Lifetime Revenue</p>
            <p class="text-2xl font-black text-slate-800">₱<?php echo number_format($total_revenue, 0); ?></p>
            <p class="text-xs text-slate-400 mt-1">All-time from invoices</p>
        </div>

        <div class="stat-card bg-white rounded-xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-1 w-full bg-emerald-400 rounded-t-xl"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Today's Sales</p>
            <p class="text-2xl font-black text-slate-800">₱<?php echo number_format($today_revenue, 0); ?></p>
            <p class="text-xs text-slate-400 mt-1"><?php echo date('M j, Y'); ?></p>
        </div>

        <div class="stat-card bg-white rounded-xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-1 w-full bg-blue-500 rounded-t-xl"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Completed Orders</p>
            <p class="text-2xl font-black text-slate-800"><?php echo number_format($total_orders); ?></p>
            <p class="text-xs text-slate-400 mt-1">All time</p>
        </div>

        <div class="stat-card bg-white rounded-xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-1 w-full bg-amber-400 rounded-t-xl"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Pending Orders</p>
            <p class="text-2xl font-black <?php echo $pending_orders > 0 ? 'text-amber-600' : 'text-slate-800'; ?>">
                <?php echo number_format($pending_orders); ?>
            </p>
            <p class="text-xs text-slate-400 mt-1">Needs attention</p>
        </div>

        <div class="stat-card bg-white rounded-xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-1 w-full bg-purple-500 rounded-t-xl"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Active Products</p>
            <p class="text-2xl font-black text-slate-800"><?php echo number_format($total_products); ?></p>
            <p class="text-xs text-slate-400 mt-1">In catalog</p>
        </div>

        <div class="stat-card bg-white rounded-xl border border-slate-200 p-5 relative overflow-hidden">
            <div class="absolute top-0 left-0 h-1 w-full bg-pink-400 rounded-t-xl"></div>
            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-1">Customers</p>
            <p class="text-2xl font-black text-slate-800"><?php echo number_format($total_users); ?></p>
            <p class="text-xs text-slate-400 mt-1">Active accounts</p>
        </div>

    </div>

    <!-- ============================================================ -->
    <!-- ROW 2: REVENUE CHART (wide) + ORDER STATUS DONUT             -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

        <!-- Revenue Chart (last 7 days) -->
        <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800">Revenue </h2>
                    <p class="text-xs text-slate-400">Daily totals from issued invoices</p>
                </div>
                <span class="text-xs bg-green-50 text-green-700 font-semibold px-2 py-1 rounded-full border border-green-100">Last 7 days</span>
            </div>
            <div class="chart-container" style="height:230px">
                <canvas id="revenueChart" role="img" aria-label="Bar chart showing daily revenue for the last 7 days"></canvas>
            </div>
        </div>

        <!-- Order Status Donut -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-800 mb-1">Orders by Status</h2>
            <p class="text-xs text-slate-400 mb-4">All-time breakdown</p>
            <div class="chart-container" style="height:180px">
                <canvas id="orderStatusChart" role="img" aria-label="Donut chart showing order status distribution"></canvas>
            </div>
            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                <?php
                $status_colors = ['Pending'=>'#F59E0B','Processing'=>'#3B82F6','Ready'=>'#8B5CF6','Completed'=>'#10B981','Cancelled'=>'#EF4444'];
                foreach ($status_labels as $i => $sl):
                    $col = $status_colors[$sl] ?? '#94A3B8';
                ?>
                <span class="flex items-center gap-1">
                    <span style="width:8px;height:8px;border-radius:2px;background:<?php echo $col;?>;display:inline-block"></span>
                    <?php echo $sl; ?> (<?php echo $status_counts[$i]; ?>)
                </span>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- ============================================================ -->
    <!-- ROW 3: STOCK BY CATEGORY + TOP PRODUCTS VALUE + PAYMENT MIX -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">

        <!-- Stock by Category -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-800 mb-1">Inventory by Category</h2>
            <p class="text-xs text-slate-400 mb-4">Total units per category</p>
            <div class="chart-container" style="height:220px">
                <canvas id="categoryStockChart" role="img" aria-label="Horizontal bar chart of inventory stock by category"></canvas>
            </div>
        </div>

        <!-- Top Products by Stock Value -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-800 mb-1">Top Products by Value</h2>
            <p class="text-xs text-slate-400 mb-4">Price × stock on shelf</p>
            <div class="chart-container" style="height:220px">
                <canvas id="topProductsChart" role="img" aria-label="Horizontal bar chart of top 5 products by stock value"></canvas>
            </div>
        </div>

        <!-- Payment Method Mix -->
        <div class="bg-white rounded-xl border border-slate-200 p-6">
            <h2 class="text-base font-bold text-slate-800 mb-1">Payment Methods</h2>
            <p class="text-xs text-slate-400 mb-4">Paid orders breakdown</p>
            <?php if (!empty($payment_labels)): ?>
            <div class="chart-container" style="height:180px">
                <canvas id="paymentChart" role="img" aria-label="Pie chart showing payment method distribution"></canvas>
            </div>
            <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                <?php
                $pay_colors = ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444','#EC4899'];
                foreach ($payment_labels as $pi => $pl):
                ?>
                <span class="flex items-center gap-1">
                    <span style="width:8px;height:8px;border-radius:2px;background:<?php echo $pay_colors[$pi % count($pay_colors)];?>;display:inline-block"></span>
                    <?php echo $pl; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center h-40 text-slate-400">
                <svg class="w-10 h-10 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <p class="text-sm font-medium">No paid orders yet</p>
            </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ============================================================ -->
    <!-- ROW 4: LOW STOCK TABLE + EXPIRING SOON + RECENT ORDERS       -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

        <!-- Low Stock Alerts (wide) -->
        <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="bg-red-50 border-b border-red-100 p-4 flex justify-between items-center">
                <h2 class="text-base font-bold text-red-800 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Critical Inventory Alerts
                </h2>
            </div>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <th class="p-3 font-bold">SKU</th>
                        <th class="p-3 font-bold">Product</th>
                        <th class="p-3 font-bold">Category</th>
                        <th class="p-3 font-bold text-center">Threshold</th>
                        <th class="p-3 font-bold text-center">Stock</th>
                        <th class="p-3 font-bold text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if ($low_stock_result->num_rows > 0): ?>
                        <?php while ($item = $low_stock_result->fetch_assoc()): ?>
                            <tr class="hover:bg-red-50/40 transition">
                                <td class="p-3 font-mono text-xs font-bold text-slate-500"><?php echo htmlspecialchars($item['sku']); ?></td>
                                <td class="p-3 font-semibold text-slate-800"><?php echo htmlspecialchars($item['name']); ?></td>
                                <td class="p-3 text-slate-500 text-xs"><?php echo htmlspecialchars($item['category']); ?></td>
                                <td class="p-3 text-center text-slate-500 text-xs font-bold"><?php echo $item['low_stock_threshold']; ?></td>
                                <td class="p-3 text-center">
                                    <span class="font-black text-lg <?php echo $item['stock'] == 0 ? 'text-red-600' : 'text-orange-500'; ?>">
                                        <?php echo $item['stock']; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
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
                            <td colspan="6" class="p-10 text-center text-slate-500">
                                <svg class="w-10 h-10 mx-auto text-green-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="font-bold text-slate-700">Inventory is healthy!</p>
                                <p class="text-sm">No products below their low stock thresholds.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Expiring Soon -->
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="bg-amber-50 border-b border-amber-100 p-4">
                <h2 class="text-base font-bold text-amber-800 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Expiring Within 30 Days
                </h2>
                <p class="text-xs text-amber-600 mt-0.5">Active batches by expiry date</p>
            </div>
            <div class="p-4 space-y-3 overflow-y-auto" style="max-height: 340px;">
                <?php
                $expiry_rows = [];
                while ($row = $expiry_result->fetch_assoc()) $expiry_rows[] = $row;
                if (!empty($expiry_rows)):
                    foreach ($expiry_rows as $e):
                        $days = (int)$e['days_left'];
                        $urgency_class = $days <= 7 ? 'bg-red-400' : ($days <= 14 ? 'bg-amber-400' : 'bg-yellow-300');
                        $bar_pct = max(5, min(100, round(($days / 30) * 100)));
                ?>
                <div>
                    <div class="flex justify-between items-baseline mb-1">
                        <span class="text-xs font-semibold text-slate-700 truncate max-w-[150px]"><?php echo htmlspecialchars($e['name']); ?></span>
                        <span class="text-xs font-black <?php echo $days <= 7 ? 'text-red-600' : 'text-amber-600'; ?>"><?php echo $days; ?>d left</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5">
                        <div class="expiry-bar <?php echo $urgency_class; ?>" style="width:<?php echo $bar_pct; ?>%"></div>
                    </div>
                    <div class="flex justify-between mt-0.5">
                        <span class="text-[10px] text-slate-400">Qty: <?php echo $e['remaining_quantity']; ?></span>
                        <span class="text-[10px] text-slate-400">Exp: <?php echo date('M j, Y', strtotime($e['expiry_date'])); ?></span>
                    </div>
                </div>
                <?php endforeach; else: ?>
                <div class="flex flex-col items-center justify-center h-40 text-slate-400">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-sm font-medium text-slate-500">No batches expiring soon</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ============================================================ -->
    <!-- ROW 5: RECENT ORDERS + SUPPLIER RETURNS SUMMARY              -->
    <!-- ============================================================ -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-6">

        <!-- Recent Orders -->
        <div class="xl:col-span-2 bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="border-b border-slate-100 p-4 flex justify-between items-center">
                <h2 class="text-base font-bold text-slate-800">Recent Orders</h2>
                <a href="orders.php" class="text-xs text-blue-600 hover:text-blue-800 font-semibold underline">View all →</a>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-100">
                        <th class="p-3 font-bold text-left">Tracking</th>
                        <th class="p-3 font-bold text-left">Customer</th>
                        <th class="p-3 font-bold text-left">Method</th>
                        <th class="p-3 font-bold text-center">Amount</th>
                        <th class="p-3 font-bold text-center">Status</th>
                        <th class="p-3 font-bold text-left">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    <?php if ($recent_orders_result && $recent_orders_result->num_rows > 0): ?>
                        <?php while ($order = $recent_orders_result->fetch_assoc()): ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-3 font-mono text-xs font-bold text-blue-600"><?php echo htmlspecialchars($order['tracking_no']); ?></td>
                            <td class="p-3 text-slate-700 font-medium"><?php echo htmlspecialchars($order['customer_name'] ?? 'Guest'); ?></td>
                            <?php 
                                $pm_display = $order['payment_method'] ?? '—';
                                if (strcasecmp($pm_display, 'prepaid') === 0) $pm_display = 'GCash (QRPH)';
                            ?>
                            <td class="p-3 text-slate-500 text-xs uppercase"><?php echo htmlspecialchars($pm_display); ?></td>
                            <td class="p-3 text-center font-bold text-slate-800">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                            <td class="p-3 text-center">
                                <span class="text-[10px] font-black px-2 py-1 rounded uppercase tracking-wider badge-<?php echo $order['order_status']; ?>">
                                    <?php echo ucfirst($order['order_status']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-xs text-slate-400"><?php echo date('M j, g:i a', strtotime($order['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="p-8 text-center text-slate-400 text-sm">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Supplier Returns + Quick Actions -->
        <div class="space-y-4">

            <!-- Supplier Returns -->
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <h2 class="text-base font-bold text-slate-800 mb-3">Supplier Returns</h2>
                <div class="space-y-2">
                    <div class="flex justify-between items-center p-3 bg-amber-50 border border-amber-100 rounded-lg">
                        <span class="text-sm font-semibold text-amber-800">Pending Return</span>
                        <span class="text-xl font-black text-amber-700"><?php echo $returns_pending; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-blue-50 border border-blue-100 rounded-lg">
                        <span class="text-sm font-semibold text-blue-800">Sent to Supplier</span>
                        <span class="text-xl font-black text-blue-700"><?php echo $returns_sent; ?></span>
                    </div>
                    <div class="flex justify-between items-center p-3 bg-green-50 border border-green-100 rounded-lg">
                        <span class="text-sm font-semibold text-green-800">Resolved</span>
                        <span class="text-xl font-black text-green-700"><?php echo $returns_resolved; ?></span>
                    </div>
                </div>
                <a href="supplier_returns.php" class="mt-3 block text-center text-xs text-slate-500 hover:text-slate-700 underline">Manage returns →</a>
            </div>

            <!-- Quick Actions -->
            <div class="bg-slate-900 rounded-xl p-5 text-white">
                <h3 class="font-black tracking-widest uppercase text-slate-400 text-xs mb-3">Quick Actions</h3>
                <div class="space-y-2">
                    <a href="products.php?action=add" class="w-full bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold py-2.5 px-4 rounded-lg transition flex items-center gap-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                        Add New Product
                    </a>
                    <a href="purchase_orders.php" class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm font-bold py-2.5 px-4 rounded-lg transition flex items-center gap-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        New Purchase Order
                    </a>
                    <a href="sales_report.php" class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm font-bold py-2.5 px-4 rounded-lg transition flex items-center gap-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Sales Report
                    </a>
                    <a href="users.php" class="w-full bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm font-bold py-2.5 px-4 rounded-lg transition flex items-center gap-3">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Manage Users
                    </a>
                </div>
            </div>

        </div>
    </div>

</main>

<!-- ============================================================ -->
<!-- CHART.JS INITIALIZATION                                       -->
<!-- ============================================================ -->
<script>
const gridColor  = 'rgba(0,0,0,0.05)';
const tickColor  = '#94A3B8';
const baseScales = {
    x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } },
    y: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } }
};

// 1. Revenue Bar Chart (last 7 days)
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($revenue_labels); ?>,
        datasets: [{
            label: 'Revenue (₱)',
            data: <?php echo json_encode($revenue_chart_data); ?>,
            backgroundColor: 'rgba(59,130,246,0.8)',
            borderRadius: 6,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { ...baseScales.x },
            y: { ...baseScales.y, ticks: { ...baseScales.y.ticks, callback: v => '₱' + v.toLocaleString() } }
        }
    }
});

// 2. Order Status Donut
new Chart(document.getElementById('orderStatusChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($status_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($status_counts); ?>,
            backgroundColor: ['#F59E0B','#3B82F6','#8B5CF6','#10B981','#EF4444'],
            borderWidth: 0,
            hoverOffset: 6
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '65%',
        plugins: { legend: { display: false } }
    }
});

// 3. Inventory by Category (horizontal bar)
new Chart(document.getElementById('categoryStockChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($cat_labels); ?>,
        datasets: [{
            label: 'Units in Stock',
            data: <?php echo json_encode($cat_stock); ?>,
            backgroundColor: ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444','#EC4899','#14B8A6','#F97316'],
            borderRadius: 4,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { ...baseScales.x },
            y: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } }
        }
    }
});

// 4. Top Products by Value (horizontal bar)
new Chart(document.getElementById('topProductsChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($top_prod_labels); ?>,
        datasets: [{
            label: 'Stock Value (₱)',
            data: <?php echo json_encode($top_prod_values); ?>,
            backgroundColor: 'rgba(139,92,246,0.8)',
            borderRadius: 4,
            borderWidth: 0
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: {
            x: { ...baseScales.x, ticks: { ...baseScales.x.ticks, callback: v => '₱' + v.toLocaleString() } },
            y: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } } }
        }
    }
});

// 5. Payment Method Pie (only if data exists)
<?php if (!empty($payment_labels)): ?>
new Chart(document.getElementById('paymentChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($payment_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($payment_counts); ?>,
            backgroundColor: ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444'],
            borderWidth: 0,
            hoverOffset: 5
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
<?php endif; ?>
</script>

<?php
require_once '../../includes/admin_footer.php';
$conn->close();
?>