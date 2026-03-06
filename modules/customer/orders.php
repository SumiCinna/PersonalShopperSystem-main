<?php
// modules/customer/orders.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch all orders for this specific customer, newest first
$query = "SELECT order_id, tracking_no, total_amount, payment_method, payment_status, order_status, created_at 
          FROM orders 
          WHERE user_id = ? 
          ORDER BY created_at DESC";
          
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

// Include the Customer Header
$page_title = 'My Orders';
require_once '../../includes/customer_header.php'; 

// Helper function to colorize the order status badges
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200"><svg class="w-2 h-2 mr-1.5 text-yellow-500 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>Pending</span>';
        case 'processing':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 border border-blue-200"><svg class="w-2 h-2 mr-1.5 text-blue-500 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>Processing</span>';
        case 'ready':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200 animate-pulse"><svg class="w-2 h-2 mr-1.5 text-green-500 fill-current" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>Ready for Pickup</span>';
        case 'completed':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">Completed</span>';
        case 'cancelled':
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">Cancelled</span>';
        default:
            return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Unknown</span>';
    }
}
?>

<main class="container mx-auto px-6 py-12 flex-grow bg-gray-50">
    <div class="max-w-6xl mx-auto">
        
        <div class="flex flex-col md:flex-row md:justify-between md:items-end mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Order History</h1>
                <p class="text-gray-500 mt-1">Track your past purchases and check the status of current orders.</p>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg shadow-sm border border-gray-200 text-sm font-medium text-gray-600">
                Total Orders: <span class="text-blue-600 font-bold ml-1"><?php echo count($orders); ?></span>
            </div>
        </div>

        <?php if (empty($orders)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                <h2 class="text-xl font-bold text-gray-700 mb-2">No orders yet</h2>
                <p class="text-gray-500 mb-6">You haven't placed any orders. Start browsing our catalog to fill your cart!</p>
                <a href="home.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                    Browse Products
                </a>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="hidden md:block bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                            <th class="px-6 py-4 font-semibold">Tracking Number</th>
                            <th class="px-6 py-4 font-semibold">Date Placed</th>
                            <th class="px-6 py-4 font-semibold">Payment</th>
                            <th class="px-6 py-4 font-semibold text-center">Status</th>
                            <th class="px-6 py-4 font-semibold text-right">Total Amount</th>
                            <th class="px-6 py-4 font-semibold text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-blue-50/50 transition duration-150">
                                <td class="px-6 py-4">
                                    <span class="font-mono font-bold text-blue-600 text-sm bg-blue-50 px-2 py-1 rounded">
                                        <?php echo htmlspecialchars($order['tracking_no']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <div class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo date('g:i A', strtotime($order['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-sm font-medium text-gray-700 capitalize">
                                            <?php echo str_replace('_', ' ', htmlspecialchars($order['payment_method'])); ?>
                                        </span>
                                        <?php if($order['payment_status'] === 'paid'): ?>
                                            <span class="text-xs text-green-600 font-bold flex items-center mt-0.5"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg> Paid</span>
                                        <?php else: ?>
                                            <span class="text-xs text-yellow-600 font-bold flex items-center mt-0.5"><svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Payment Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php echo getStatusBadge($order['order_status']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <span class="text-base font-bold text-gray-900">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="inline-flex items-center text-blue-600 hover:text-blue-900 text-sm font-semibold transition hover:underline">
                                        View Details
                                        <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Cards View -->
            <div class="md:hidden space-y-4">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <span class="font-mono text-xs font-bold text-gray-400 uppercase">Tracking No.</span>
                                <div class="font-mono font-bold text-blue-600 text-lg"><?php echo htmlspecialchars($order['tracking_no']); ?></div>
                                <div class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y • g:i A', strtotime($order['created_at'])); ?></div>
                            </div>
                            <?php echo getStatusBadge($order['order_status']); ?>
                        </div>
                        
                        <div class="border-t border-gray-100 py-3 flex justify-between items-center">
                            <div class="flex flex-col">
                                <span class="text-xs text-gray-400 uppercase font-bold">Total Amount</span>
                                <span class="text-xl font-black text-gray-900">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-xs text-gray-400 uppercase font-bold block mb-1">Payment</span>
                                <span class="text-sm font-medium text-gray-700 capitalize block"><?php echo str_replace('_', ' ', htmlspecialchars($order['payment_method'])); ?></span>
                                <?php if($order['payment_status'] === 'paid'): ?>
                                    <span class="text-xs text-green-600 font-bold">Paid</span>
                                <?php else: ?>
                                    <span class="text-xs text-yellow-600 font-bold">Pending</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="order_details.php?id=<?php echo $order['order_id']; ?>" class="block text-center w-full mt-2 bg-gray-50 hover:bg-gray-100 text-gray-700 font-semibold py-2 px-4 rounded-lg transition text-sm border border-gray-200">
                            View Order Details
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>