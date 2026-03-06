<?php
// modules/cashier/dashboard.php
session_start();
require_once '../../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'cashier') {
    header("Location: ../../cashier-login.php");
    exit();
}

$cashier_id = $_SESSION['user_id'];

// --- TRANSACTION LOCK SECURITY ---
// Even on the dashboard, we must check if they abandoned a transaction!
$lock_check = $conn->prepare("SELECT order_id FROM orders WHERE processed_by = ? AND order_status IN ('processing', 'ready')");
$lock_check->bind_param("i", $cashier_id);
$lock_check->execute();
$lock_result = $lock_check->get_result();
if ($lock_result->num_rows > 0) {
    $locked_order = $lock_result->fetch_assoc();
    $_SESSION['error'] = "SECURITY LOCK: You must complete or void your active transaction before viewing analytics.";
    header("Location: process_order.php?id=" . $locked_order['order_id']);
    exit();
}
$lock_check->close();

// --- FETCH CASHIER ANALYTICS FOR TODAY ---
$today = date('Y-m-d');

// 1. Total Cash Collected Today by THIS cashier
$sales_query = "SELECT SUM(amount_paid) as total_sales FROM transactions WHERE cashier_id = ? AND DATE(transaction_date) = ?";
$stmt = $conn->prepare($sales_query);
$stmt->bind_param("is", $cashier_id, $today);
$stmt->execute();
$sales_today = $stmt->get_result()->fetch_assoc()['total_sales'] ?? 0;
$stmt->close();

// 2. Customers Served Today by THIS cashier
$served_query = "SELECT COUNT(*) as count FROM orders WHERE processed_by = ? AND order_status = 'completed' AND DATE(created_at) = ?";
$stmt = $conn->prepare($served_query);
$stmt->bind_param("is", $cashier_id, $today);
$stmt->execute();
$customers_served = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// 3. Store-wide Pending Orders (Queue size)
$pending_query = "SELECT COUNT(*) as count FROM orders WHERE order_status = 'pending'";
$pending_queue = $conn->query($pending_query)->fetch_assoc()['count'];

$page_title = 'Shift Overview';
require_once '../../includes/cashier_header.php'; 
?>

<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
    <div class="mb-8 border-b pb-4">
        <h1 class="text-3xl font-bold text-gray-800">Shift Overview</h1>
        <p class="text-gray-500 mt-1">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>. Here is your performance for today.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-green-100 rounded-full mr-4 text-green-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Your Shift Sales</p>
                <p class="text-3xl font-black text-gray-900">₱<?php echo number_format($sales_today, 2); ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-blue-100 rounded-full mr-4 text-blue-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Orders Completed</p>
                <p class="text-3xl font-black text-gray-900"><?php echo $customers_served; ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex items-center">
            <div class="p-4 bg-yellow-100 rounded-full mr-4 text-yellow-600">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm font-bold text-gray-400 uppercase tracking-wider">Pending Orders</p>
                <p class="text-3xl font-black text-gray-900"><?php echo $pending_queue; ?></p>
            </div>
        </div>

    </div>

    <div class="bg-blue-900 rounded-xl shadow-md p-8 text-white flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold mb-1">Ready to serve the next customer?</h2>
            <p class="text-blue-200">There are currently <?php echo $pending_queue; ?> orders waiting in the queue.</p>
        </div>
        <a href="pos.php" class="bg-yellow-400 hover:bg-yellow-500 text-blue-900 font-bold py-3 px-8 rounded-lg shadow transition text-lg">
            Open POS Terminal &rarr;
        </a>
    </div>

</main>

<?php require_once '../../includes/cashier_footer.php'; $conn->close(); ?>