<?php
// modules/customer/checkout.php
session_start();
require_once '../../config/config.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 1. Check if items were selected from cart.php
if (!isset($_POST['selected_cart_ids']) || empty($_POST['selected_cart_ids'])) {
    header("Location: cart.php");
    exit();
}

$selected_ids = array_map('intval', $_POST['selected_cart_ids']);
$ids_string = implode(',', $selected_ids);

// 2. Fetch ONLY SELECTED Cart Items
$cart_query = "SELECT c.quantity, p.product_id, p.name, p.price, p.discount_price, p.image_url 
               FROM cart c
               JOIN products p ON c.product_id = p.product_id
               WHERE c.user_id = ? AND c.cart_id IN ($ids_string)";
               
$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$total_amount = 0;

while ($row = $result->fetch_assoc()) {
    // Use discount price if available
    $final_price = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price']) ? $row['discount_price'] : $row['price'];
    $row['final_price'] = $final_price;
    $cart_items[] = $row;
    $total_amount += ($final_price * $row['quantity']);
}
$stmt->close();

// If the cart is empty, kick them back to the dashboard
if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Include the Customer Header
$page_title = 'Checkout';
require_once '../../includes/customer_header.php'; 
?>

<main class="container mx-auto px-6 py-12 flex-grow bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
            <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Secure Checkout
        </h1>

        <form action="../../core/customer/process_checkout.php" method="POST" class="flex flex-col lg:flex-row gap-8">
            
            <div class="lg:w-2/3 space-y-6">
                
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Schedule Pick-up
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Date</label>
                            <input type="date" name="pickup_date" required min="<?php echo date('Y-m-d'); ?>" class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Time</label>
                            <input type="time" name="pickup_time" required class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Payment Details
                    </h3>
                    
                    <!-- Enforce GCash/Online as the only method -->
                    <input type="hidden" name="payment_method" value="gcash">

                    <div class="bg-blue-50 p-5 rounded-lg border border-blue-200">
                        <p class="text-sm text-blue-800 mb-4 font-semibold bg-blue-100 p-2 rounded">Payment Mode: <span class="font-black">Online Payment Only</span><br>Please send payment to GCash: <span class="font-black">0912-345-6789</span> (PSS Grocery)</p>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-bold text-blue-900 mb-1">Payment Options</label>
                            <select name="payment_type" id="payment_type" class="w-full bg-white border border-blue-300 rounded-lg p-3 font-bold text-blue-900" onchange="updatePaymentBreakdown()">
                                <option value="full">Full Payment (₱<?php echo number_format($total_amount, 2); ?>)</option>
                                <option value="partial_50">50% Downpayment (₱<?php echo number_format($total_amount * 0.5, 2); ?>)</option>
                                <option value="partial_30">30% Downpayment (₱<?php echo number_format($total_amount * 0.3, 2); ?>)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-blue-900 mb-1">GCash Reference Number</label>
                            <input type="text" name="online_reference" id="online_reference" required placeholder="Enter 13-digit Ref No." class="w-full bg-white border border-blue-300 rounded-lg p-3 font-mono font-bold tracking-widest text-lg">
                        </div>
                    </div>
                </div>

            </div>

            <div class="lg:w-1/3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-24">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 border-b pb-2">Order Summary</h2>
                    
                    <div class="max-h-60 overflow-y-auto mb-4 space-y-3 pr-2">
                        <?php foreach ($cart_items as $item): ?>
                            <div class="flex justify-between items-center text-sm">
                                <div class="flex items-center flex-1">
                                    <span class="font-bold text-gray-700 mr-2"><?php echo $item['quantity']; ?>x</span>
                                    <span class="text-gray-600 truncate max-w-[150px]"><?php echo htmlspecialchars($item['name']); ?></span>
                                </div>
                                <span class="font-semibold text-gray-800">₱<?php echo number_format($item['final_price'] * $item['quantity'], 2); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="border-t pt-4 mb-6">
                        <div class="flex justify-between text-2xl font-black text-gray-900">
                            <span>Total</span>
                            <span class="text-blue-700" id="grandTotalDisplay">₱<?php echo number_format($total_amount, 2); ?></span>
                        </div>
                        
                        <div class="mt-4 pt-4 border-t border-dashed border-gray-300 space-y-2">
                            <div class="flex justify-between text-sm font-bold text-green-700">
                                <span>Pay Now (Online):</span>
                                <span id="payNowDisplay">₱<?php echo number_format($total_amount, 2); ?></span>
                            </div>
                            <div class="flex justify-between text-sm font-bold text-red-600">
                                <span>Balance at Store:</span>
                                <span id="balanceDueDisplay">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <input type="hidden" name="total_amount" value="<?php echo $total_amount; ?>">
                    
                    <?php foreach ($selected_ids as $id): ?>
                        <input type="hidden" name="selected_cart_ids[]" value="<?php echo $id; ?>">
                    <?php endforeach; ?>

                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-4 rounded-lg flex justify-center items-center transition shadow-md text-lg">
                        Confirm Order
                        <svg class="w-6 h-6 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </button>
                    
                    <p class="text-xs text-center text-gray-500 mt-4">By clicking Confirm Order, you agree to our store's pickup policies.</p>
                </div>
            </div>
            
        </form>
    </div>
</main>

<script>
    function updatePaymentBreakdown() {
        const total = parseFloat(document.querySelector('input[name="total_amount"]').value);
        const type = document.getElementById('payment_type').value;
        let upfront = 0;
        let balance = 0;

        if (type === 'full') {
            upfront = total;
            balance = 0;
        } else if (type === 'partial_50') {
            upfront = total * 0.5;
            balance = total - upfront;
        } else if (type === 'partial_30') {
            upfront = total * 0.3;
            balance = total - upfront;
        }
        
        document.getElementById('payNowDisplay').innerText = '₱' + upfront.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('balanceDueDisplay').innerText = '₱' + balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
    
    // Run on load to set initial values
    updatePaymentBreakdown();
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>