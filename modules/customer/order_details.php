<?php
// modules/customer/order_details.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: orders.php");
    exit();
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// 1. Fetch the Main Order Details (Verifying it belongs to this user!)
$order_query = "SELECT * FROM orders WHERE order_id = ? AND user_id = ?";
$order_stmt = $conn->prepare($order_query);
$order_stmt->bind_param("ii", $order_id, $user_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    // Order doesn't exist or isn't theirs
    header("Location: orders.php");
    exit();
}
$order = $order_result->fetch_assoc();
$order_stmt->close();

// 2. Fetch the Specific Items inside this Order
$items_query = "SELECT oi.quantity, oi.price_at_checkout, p.name, p.image_url, p.unit_value, p.unit_measure 
                FROM order_items oi 
                JOIN products p ON oi.product_id = p.product_id 
                WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

$order_items = [];
while ($row = $items_result->fetch_assoc()) {
    $order_items[] = $row;
}
$items_stmt->close();

// Include the Customer Header
$page_title = 'Order Details - ' . $order['tracking_no'];
require_once '../../includes/customer_header.php'; 

// Helper function for badges
function getStatusBadge($status) {
    switch ($status) {
        case 'pending': return '<span class="bg-yellow-100 text-yellow-800 text-sm font-bold px-4 py-1.5 rounded-full border border-yellow-200">Pending</span>';
        case 'processing': return '<span class="bg-blue-100 text-blue-800 text-sm font-bold px-4 py-1.5 rounded-full border border-blue-200">Processing</span>';
        case 'ready': return '<span class="bg-green-100 text-green-800 text-sm font-bold px-4 py-1.5 rounded-full border border-green-200 animate-pulse">Ready for Pickup</span>';
        case 'completed': return '<span class="bg-gray-100 text-gray-800 text-sm font-bold px-4 py-1.5 rounded-full border border-gray-200">Completed</span>';
        case 'cancelled': return '<span class="bg-red-100 text-red-800 text-sm font-bold px-4 py-1.5 rounded-full border border-red-200">Cancelled</span>';
        default: return '<span class="bg-gray-100 text-gray-800 text-sm font-bold px-4 py-1.5 rounded-full">Unknown</span>';
    }
}
?>

<main class="container mx-auto px-6 py-12 flex-grow bg-gray-50">
    <div class="max-w-5xl mx-auto">
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm font-bold">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded shadow-sm font-bold">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <a href="orders.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 font-semibold mb-6 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to All Orders
        </a>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center">
            <div>
                <h1 class="text-2xl font-black text-gray-900 mb-1">Order <?php echo htmlspecialchars($order['tracking_no']); ?></h1>
                <p class="text-sm text-gray-500 flex items-center">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['created_at'])); ?>
                </p>
            </div>
            <div class="mt-4 md:mt-0 flex flex-col items-end gap-3">
                <?php echo getStatusBadge($order['order_status']); ?>
                
                <?php if($order['order_status'] === 'pending'): ?>
                    <button onclick="openCancelModal()" class="text-sm text-red-500 hover:text-red-700 font-bold underline decoration-red-200 hover:decoration-red-700 transition">
                        Cancel Order
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            
            <div class="lg:w-2/3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 bg-gray-50">
                        <h2 class="text-lg font-bold text-gray-800">Items in this Order (<?php echo count($order_items); ?>)</h2>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($order_items as $item): ?>
                            <li class="p-6 flex flex-col sm:flex-row items-center hover:bg-gray-50 transition">
                                <div class="w-20 h-20 flex-shrink-0 bg-white border border-gray-200 rounded-lg flex items-center justify-center p-2 mb-4 sm:mb-0">
                                    <?php if(!empty($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="max-h-full object-contain">
                                    <?php else: ?>
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    <?php endif; ?>
                                </div>

                                <div class="sm:ml-6 flex-1 text-center sm:text-left">
                                    <h3 class="text-base font-bold text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo floatval($item['unit_value']) . ' ' . htmlspecialchars($item['unit_measure']); ?></p>
                                    <p class="text-sm text-gray-600 mt-2">Qty: <span class="font-bold text-gray-900"><?php echo $item['quantity']; ?></span></p>
                                </div>

                                <div class="mt-4 sm:mt-0 sm:ml-6 text-right">
                                    <p class="text-sm text-gray-500">₱<?php echo number_format($item['price_at_checkout'], 2); ?> each</p>
                                    <p class="text-lg font-black text-blue-900 mt-1">₱<?php echo number_format($item['price_at_checkout'] * $item['quantity'], 2); ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="lg:w-1/3 space-y-6">
                
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2">Payment Summary</h2>
                    
                    <div class="flex justify-between text-gray-600 mb-3">
                        <span>Subtotal</span>
                        <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-gray-600 mb-4 border-b pb-4">
                        <span>Convenience Fee</span>
                        <span>₱0.00</span>
                    </div>
                    
                    <div class="flex justify-between text-xl font-black text-gray-900 mb-4">
                        <span>Grand Total</span>
                        <span class="text-blue-700">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>

                    <div class="bg-gray-50 rounded p-4 border border-gray-200">
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold mb-1">Method</p>
                        <p class="font-semibold text-gray-800 capitalize mb-3">
                            <?php echo str_replace('_', ' ', htmlspecialchars($order['payment_method'])); ?>
                        </p>
                        <p class="text-xs text-gray-500 uppercase tracking-wider font-bold mb-1">Payment Status</p>
                        <?php if($order['payment_status'] === 'paid'): ?>
                            <span class="text-sm text-green-700 font-bold bg-green-100 px-2 py-1 rounded">Paid</span>
                        <?php else: ?>
                            <span class="text-sm text-yellow-700 font-bold bg-yellow-100 px-2 py-1 rounded">Pending Payment</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-blue-50 rounded-xl shadow-sm border border-blue-100 p-6">
                    <h2 class="text-lg font-bold text-blue-900 mb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        In-Store Pickup
                    </h2>
                    <p class="text-sm text-blue-800">Present your Tracking Number <strong>(<?php echo htmlspecialchars($order['tracking_no']); ?>)</strong> at the Customer Service / Online Orders counter when your order is ready.</p>
                </div>

            </div>
        </div>

    </div>
</main>

<!-- Cancel Confirmation Modal -->
<div id="cancelOrderModal" class="relative z-50 hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 transition-opacity backdrop-blur-sm"></div>
    <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-base font-semibold leading-6 text-gray-900" id="modal-title">Cancel Order</h3>
                            <div class="mt-2">
                                <p class="text-sm text-gray-500">Are you sure you want to cancel this order? This action cannot be undone and items will be returned to stock.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <form action="../../core/customer/cancel_order.php" method="POST">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        <button type="submit" class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">Yes, Cancel Order</button>
                    </form>
                    <button type="button" onclick="closeCancelModal()" class="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 sm:mt-0 sm:w-auto">Keep Order</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openCancelModal() { document.getElementById('cancelOrderModal').classList.remove('hidden'); }
    function closeCancelModal() { document.getElementById('cancelOrderModal').classList.add('hidden'); }
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>