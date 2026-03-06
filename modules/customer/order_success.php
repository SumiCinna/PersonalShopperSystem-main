<?php
// modules/customer/order_success.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

// If they somehow get here without a tracking number, send them away
if (!isset($_GET['tracking']) || empty($_GET['tracking'])) {
    header("Location: home.php");
    exit();
}

$tracking_no = $_GET['tracking'];
$user_id = $_SESSION['user_id'];

// --- VERIFY THE ORDER ---
// Make sure this tracking number actually belongs to this user!
$query = "SELECT total_amount, payment_method, created_at FROM orders WHERE tracking_no = ? AND user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("si", $tracking_no, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order not found or doesn't belong to them
    header("Location: home.php");
    exit();
}

$order = $result->fetch_assoc();
$stmt->close();

// Include the Customer Header
$page_title = 'Order Successful';
require_once '../../includes/customer_header.php'; 
?>

<main class="container mx-auto px-6 py-16 flex-grow flex items-center justify-center bg-gray-50">
    <div class="max-w-2xl w-full bg-white rounded-2xl shadow-lg border border-gray-100 p-8 text-center relative overflow-hidden">
        
        <div class="absolute top-0 left-0 w-full h-2 bg-green-500"></div>

        <div class="mx-auto w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mb-6">
            <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
        </div>

        <h1 class="text-3xl font-black text-gray-900 mb-2">Order Confirmed!</h1>
        <p class="text-gray-500 mb-8">Thank you for shopping with PSS. Your order has been securely transmitted to our store cashiers.</p>

        <div class="bg-gray-50 rounded-xl border border-gray-200 p-6 mb-8 text-left">
            <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4 border-b pb-2">Transaction Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Tracking Number</p>
                    <p class="font-mono font-bold text-blue-900 text-lg"><?php echo htmlspecialchars($tracking_no); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Total Amount</p>
                    <p class="font-bold text-gray-900 text-lg">₱<?php echo number_format($order['total_amount'], 2); ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Payment Method</p>
                    <p class="font-semibold text-gray-700 capitalize">
                        <?php echo str_replace('_', ' ', htmlspecialchars($order['payment_method'])); ?>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Date & Time</p>
                    <p class="font-semibold text-gray-700">
                        <?php echo date('M j, Y - g:i A', strtotime($order['created_at'])); ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 text-blue-800 rounded-lg p-4 mb-8 text-sm">
            <p><strong>What happens next?</strong> Our cashiers are preparing your items. You can check the status of your order in the "My Orders" tab. Please head to the pickup counter once your order status changes to <span class="bg-blue-200 px-2 py-0.5 rounded font-bold">Ready</span>.</p>
        </div>

        <div class="flex flex-col sm:flex-row justify-center gap-4">
            <a href="orders.php" class="bg-blue-900 hover:bg-blue-800 text-white font-bold py-3 px-8 rounded-lg transition shadow-md">
                Track My Order
            </a>
            <a href="home.php" class="bg-white hover:bg-gray-50 text-blue-900 font-bold py-3 px-8 rounded-lg transition border border-gray-300 shadow-sm">
                Continue Shopping
            </a>
        </div>

    </div>
</main>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>