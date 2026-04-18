<?php
// modules/customer/checkout.php
session_start();
date_default_timezone_set('Asia/Manila');
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

// 2. Fetch ONLY SELECTED Cart Items (include category)
$cart_query = "SELECT c.quantity, p.product_id, p.name, p.price, p.discount_price, p.image_url, p.category
               FROM cart c
               JOIN products p ON c.product_id = p.product_id
               WHERE c.user_id = ? AND c.cart_id IN ($ids_string)";

$stmt = $conn->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items   = [];
$subtotal_amount = 0;
$vat_rate = 0.12;
$service_fee_rate = 0.10;
$has_meat     = false;
$has_fresh    = false;

while ($row = $result->fetch_assoc()) {
    $final_price = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price'])
        ? $row['discount_price'] : $row['price'];
    $row['final_price'] = $final_price;
    $cart_items[]  = $row;
    $subtotal_amount += ($final_price * $row['quantity']);

    if ($row['category'] === 'Meat & Poultry') $has_meat  = true;
    if ($row['category'] === 'Fresh Produce')  $has_fresh = true;
}
$stmt->close();

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

$subtotal_amount = round($subtotal_amount, 2);
$vat_amount = round($subtotal_amount * $vat_rate, 2);
$service_fee_amount = round($subtotal_amount * $service_fee_rate, 2);
$total_amount = round($subtotal_amount + $vat_amount + $service_fee_amount, 2);

if ($total_amount < 300) {
    $_SESSION['error'] = 'Minimum grand total of ₱300.00 is required before checkout.';
    header("Location: cart.php");
    exit();
}

// --- MEAT & POULTRY: check if 1-2 hr window falls within store hours (10:00-19:00) ---
$meat_blocked = false;
if ($has_meat) {
    $now_minutes    = (int)date('H') * 60 + (int)date('i');
    $min_slot       = $now_minutes + 60;
    $max_slot       = $now_minutes + 120;
    $store_open     = 10 * 60;
    $store_close    = 19 * 60;
    $has_valid_slot = false;
    for ($t = $store_open; $t <= $store_close; $t += 90) {
        if ($t >= $min_slot && $t <= $max_slot) { $has_valid_slot = true; break; }
    }
    if (!$has_valid_slot) $meat_blocked = true;
}

// --- DATE LIMITS based on category ---
if ($has_meat) {
    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d');
} elseif ($has_fresh) {
    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+1 day'));
} else {
    $min_date = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+3 days'));
}

$schedule_notice = '';
if ($has_meat && $has_fresh) {
    $schedule_notice = '🥩🥬 Your order contains <strong>Meat &amp; Poultry</strong> and <strong>Fresh Produce</strong>. Pickup must be <strong>today only</strong>, within <strong>1–2 hours</strong> from now.';
} elseif ($has_meat) {
    $schedule_notice = '🥩 Your order contains <strong>Meat &amp; Poultry</strong>. Pickup must be <strong>today only</strong>, within <strong>1–2 hours</strong> from the current time.';
} elseif ($has_fresh) {
    $schedule_notice = '🥬 Your order contains <strong>Fresh Produce</strong>. Pickup must be scheduled <strong>today or tomorrow</strong>.';
}

$page_title = 'Checkout';
require_once '../../includes/customer_header.php';
?>

<?php if ($meat_blocked): ?>
<main class="container mx-auto px-6 py-12 flex-grow bg-gray-50">
    <div class="max-w-xl mx-auto text-center">
        <div class="bg-white border border-red-200 rounded-2xl shadow-sm p-10">
            <div class="flex justify-center mb-4">
                <div class="bg-red-100 rounded-full p-4">
                    <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
            </div>
            <h2 class="text-2xl font-black text-gray-800 mb-2">Cannot Place Order Right Now</h2>
            <p class="text-gray-500 text-sm mb-4">
                Your cart contains <strong class="text-red-600">Meat &amp; Poultry</strong> which requires pickup within <strong>1&ndash;2 hours</strong> of ordering.
            </p>
            <p class="text-gray-500 text-sm mb-6">
                The current time is <strong><?php echo date('h:i A'); ?></strong>. No available pickup slots within 1&ndash;2 hours during store hours <strong>(10:00 AM &ndash; 7:00 PM)</strong>.
            </p>
            <p class="text-sm text-blue-700 font-semibold mb-6">
                💡 Please come back between <strong>8:00 AM &ndash; 5:00 PM</strong> to ensure a valid pickup slot is available.
            </p>
            <a href="cart.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-xl transition">
                &larr; Back to Cart
            </a>
        </div>
    </div>
</main>
<?php else: ?>
<main class="container mx-auto px-6 py-12 flex-grow bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
            <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Secure Checkout
        </h1>

        <form id="checkoutForm" action="../../core/customer/process_checkout.php" method="POST" class="flex flex-col lg:flex-row gap-8">

            <div class="lg:w-2/3 space-y-6">

                <!-- Schedule Notice -->
                <?php if ($schedule_notice): ?>
                <div class="bg-orange-50 border border-orange-200 rounded-xl p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-orange-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-sm text-orange-800"><?php echo $schedule_notice; ?></p>
                </div>
                <?php endif; ?>

                <!-- Schedule Pick-up -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Schedule Pick-up
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">
                                Date
                                <?php if ($has_meat): ?>
                                    <span class="text-orange-500 font-normal">(today only)</span>
                                <?php elseif ($has_fresh): ?>
                                    <span class="text-orange-500 font-normal">(today or tomorrow only)</span>
                                <?php else: ?>
                                    <span class="text-gray-400 font-normal">(within 3 days)</span>
                                <?php endif; ?>
                            </label>
                            <input type="date" name="pickup_date" id="pickup_date" required
                                min="<?php echo $min_date; ?>"
                                max="<?php echo $max_date; ?>"
                                onchange="filterTimeSlots()"
                                class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">
                                Time
                                <?php if ($has_meat && !$has_fresh): ?>
                                    <span class="text-orange-500 font-normal">(1–2 hrs from now)</span>
                                <?php else: ?>
                                    <span class="text-gray-400 font-normal">(10:00 AM – 7:00 PM)</span>
                                <?php endif; ?>
                            </label>
                            <select name="pickup_time" id="pickup_time" required
                                class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="" disabled selected>Select a time slot</option>
                                <?php
                                $start    = strtotime('10:00');
                                $end      = strtotime('19:00');
                                $interval = 90 * 60;
                                for ($t = $start; $t <= $end; $t += $interval) {
                                    $value   = date('H:i', $t);
                                    $display = date('g:i A', $t);
                                    echo "<option value=\"$value\">$display</option>";
                                }
                                ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Slots available every 1 hr 30 mins</p>
                            <p id="time_warning" class="hidden text-xs text-red-500 mt-1 font-semibold"></p>
                        </div>
                    </div>
                </div>

                <!-- Payment Details — PayMongo -->
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        Payment Details
                    </h3>

                    <!-- PayMongo Branding Notice -->
                    <div class="flex items-center gap-3 bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl p-4 mb-5 text-white">
                        <div class="bg-white bg-opacity-20 rounded-lg p-2 flex-shrink-0">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-black text-sm">Secure Payment via PayMongo</p>
                            <p class="text-blue-100 text-xs mt-0.5">After confirming your order, you'll be redirected to pay securely via <strong>GCash</strong> or <strong>Credit/Debit Card</strong>.</p>
                        </div>
                    </div>

                    <!-- Payment Type Selector -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2">Payment Option</label>
                        <select name="payment_type" id="payment_type"
                            class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 font-bold text-gray-800 outline-none focus:ring-2 focus:ring-blue-500"
                            onchange="updatePaymentBreakdown()">
                            <option value="full">Full Payment (₱<?php echo number_format($total_amount, 2); ?>)</option>
                            <option value="partial_50">50% Downpayment (₱<?php echo number_format($total_amount * 0.5, 2); ?>)</option>
                            <option value="partial_30">30% Downpayment (₱<?php echo number_format($total_amount * 0.3, 2); ?>)</option>
                        </select>
                        <p class="text-xs text-gray-400 mt-2">
                            <svg class="w-3 h-3 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            Remaining balance (if any) will be collected at store pickup.
                        </p>
                    </div>
                </div>

            </div><!-- end left col -->

            <!-- Order Summary -->
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
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Subtotal</span>
                            <span class="font-semibold" id="subtotalDisplay">₱<?php echo number_format($subtotal_amount, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600 mb-3 pb-3 border-b border-dashed border-gray-300">
                            <span>VAT (12%)</span>
                            <span class="font-semibold" id="vatDisplay">₱<?php echo number_format($vat_amount, 2); ?></span>
                        </div>
                        <div class="flex justify-between text-sm text-gray-600 mb-3 pb-3 border-b border-dashed border-gray-300">
                            <span>Service Fee (10%)</span>
                            <span class="font-semibold" id="serviceFeeDisplay">₱<?php echo number_format($service_fee_amount, 2); ?></span>
                        </div>
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

                    <button type="button" onclick="openConfirmModal()"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-4 rounded-lg flex justify-center items-center transition shadow-md text-lg">
                        Confirm Order
                        <svg class="w-6 h-6 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </button>
                    <p class="text-xs text-center text-gray-500 mt-4">By clicking Confirm Order, you agree to our store's pickup policies.</p>
                </div>
            </div>

        </form>
    </div>
</main>

<!-- ===== ORDER REVIEW CONFIRMATION MODAL ===== -->
<div id="confirmOrderModal" class="relative z-50 hidden" role="dialog" aria-modal="true">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity"></div>
    <div class="fixed inset-0 z-10 overflow-y-auto flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg transform transition-all">

            <div class="bg-gradient-to-r from-green-600 to-green-500 rounded-t-2xl px-6 py-5 flex items-center gap-3">
                <div class="bg-white bg-opacity-20 rounded-full p-2">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-white font-black text-lg">Review Your Order</h2>
                    <p class="text-green-100 text-xs">Please double-check everything before placing your order.</p>
                </div>
            </div>

            <div class="px-6 py-5 space-y-4">

                <!-- Items -->
                <div>
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Items Ordered</p>
                    <div class="bg-gray-50 rounded-lg border border-gray-100 divide-y divide-gray-100 max-h-40 overflow-y-auto">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="flex justify-between items-center px-4 py-2 text-sm">
                            <span class="text-gray-700">
                                <span class="font-bold text-gray-900"><?php echo $item['quantity']; ?>x</span>
                                <?php echo htmlspecialchars($item['name']); ?>
                            </span>
                            <span class="font-bold text-gray-800">₱<?php echo number_format($item['final_price'] * $item['quantity'], 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pickup -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                        <p class="text-xs text-blue-400 font-bold uppercase tracking-wide mb-1">Pickup Date</p>
                        <p class="text-sm font-black text-blue-900" id="modal_pickup_date">—</p>
                    </div>
                    <div class="bg-blue-50 border border-blue-100 rounded-lg p-3">
                        <p class="text-xs text-blue-400 font-bold uppercase tracking-wide mb-1">Pickup Time</p>
                        <p class="text-sm font-black text-blue-900" id="modal_pickup_time">—</p>
                    </div>
                </div>

                <!-- Payment Summary -->
                <div class="bg-green-50 border border-green-100 rounded-lg p-4 space-y-2">
                    <p class="text-xs text-green-500 font-bold uppercase tracking-wide mb-1">Payment Summary</p>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>Payment Type</span>
                        <span class="font-bold text-gray-900" id="modal_payment_type">—</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>Subtotal</span>
                        <span class="font-bold text-gray-900" id="modal_subtotal">—</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>VAT (12%)</span>
                        <span class="font-bold text-gray-900" id="modal_vat">—</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>Service Fee (10%)</span>
                        <span class="font-bold text-gray-900" id="modal_service_fee">—</span>
                    </div>
                    <div class="border-t border-green-200 pt-2 flex justify-between text-sm">
                        <span class="font-bold text-green-700">Pay Now (via PayMongo)</span>
                        <span class="font-black text-green-700" id="modal_pay_now">—</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-bold text-red-500">Balance at Store</span>
                        <span class="font-black text-red-500" id="modal_balance">—</span>
                    </div>
                </div>

                <!-- Redirect Notice -->
                <div class="flex items-start gap-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <p class="text-xs text-blue-700 font-semibold">
                        After placing your order, you'll be redirected to <strong>PayMongo's secure payment page</strong> to complete your payment via GCash or Credit/Debit Card.
                    </p>
                </div>

            </div>

            <div class="px-6 pb-6 flex gap-3">
                <button type="button" onclick="closeConfirmModal()"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition text-sm">
                    Go Back &amp; Edit
                </button>
                <button type="button" onclick="submitOrder()"
                    class="flex-1 bg-green-600 hover:bg-green-700 text-white font-black py-3 rounded-xl transition text-sm flex items-center justify-center gap-2 shadow-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Yes, Place Order
                </button>
            </div>

        </div>
    </div>
</div>

<script>
    const hasMeat  = <?php echo $has_meat  ? 'true' : 'false'; ?>;
    const hasFresh = <?php echo $has_fresh ? 'true' : 'false'; ?>;
    const subtotalAmount = <?php echo json_encode($subtotal_amount); ?>;
    const vatAmount = <?php echo json_encode($vat_amount); ?>;
    const serviceFeeAmount = <?php echo json_encode($service_fee_amount); ?>;

    function updatePaymentBreakdown() {
        const total = parseFloat(document.querySelector('input[name="total_amount"]').value);
        const type  = document.getElementById('payment_type').value;
        let upfront = 0, balance = 0;

        if (type === 'full')         { upfront = total; balance = 0; }
        else if (type === 'partial_50') { upfront = total * 0.5; balance = total - upfront; }
        else if (type === 'partial_30') { upfront = total * 0.3; balance = total - upfront; }

        const fmt = v => '₱' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('payNowDisplay').innerText    = fmt(upfront);
        document.getElementById('balanceDueDisplay').innerText = fmt(balance);
        const subtotalEl = document.getElementById('subtotalDisplay');
        const vatEl = document.getElementById('vatDisplay');
        const serviceFeeEl = document.getElementById('serviceFeeDisplay');
        if (subtotalEl) subtotalEl.innerText = fmt(subtotalAmount);
        if (vatEl) vatEl.innerText = fmt(vatAmount);
        if (serviceFeeEl) serviceFeeEl.innerText = fmt(serviceFeeAmount);
    }

    function filterTimeSlots() {
        const dateInput  = document.getElementById('pickup_date');
        const timeSelect = document.getElementById('pickup_time');
        const warningEl  = document.getElementById('time_warning');
        const selectedDate = dateInput.value;

        const now = new Date();
        const todayStr = now.getFullYear() + '-'
            + String(now.getMonth() + 1).padStart(2, '0') + '-'
            + String(now.getDate()).padStart(2, '0');

        const isToday = (selectedDate === todayStr);
        const currentMinutes = now.getHours() * 60 + now.getMinutes();
        let firstAvailable = null;

        Array.from(timeSelect.options).forEach(opt => {
            if (!opt.value) return;
            const [h, m] = opt.value.split(':').map(Number);
            const slotMinutes = h * 60 + m;
            let disabled = false;

            if (isToday) {
                if (hasMeat && !hasFresh) {
                    const minSlot = currentMinutes + 60;
                    const maxSlot = currentMinutes + 120;
                    disabled = slotMinutes < minSlot || slotMinutes > maxSlot;
                } else {
                    disabled = slotMinutes <= currentMinutes;
                }
            }
            opt.disabled = disabled;
            opt.style.color = disabled ? '#9ca3af' : '';
            if (!disabled && !firstAvailable) firstAvailable = opt;
        });

        const currentOpt = timeSelect.options[timeSelect.selectedIndex];
        if (!currentOpt || currentOpt.disabled || !currentOpt.value) {
            timeSelect.value = firstAvailable ? firstAvailable.value : '';
        }

        const allDisabled = Array.from(timeSelect.options).filter(o => o.value).every(o => o.disabled);
        if (allDisabled) {
            timeSelect.value = '';
            warningEl.classList.remove('hidden');
            warningEl.textContent = hasMeat && !hasFresh
                ? '⚠ No available slots for Meat & Poultry within 1–2 hrs from now.'
                : '⚠ No more slots available today. Please select a different date.';
        } else {
            warningEl.classList.add('hidden');
            warningEl.textContent = '';
        }
    }

    function openConfirmModal() {
        const form = document.getElementById('checkoutForm');
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const total   = parseFloat(document.querySelector('input[name="total_amount"]').value);
        const payType = document.getElementById('payment_type').value;
        const dateVal = document.getElementById('pickup_date').value;
        const timeVal = document.getElementById('pickup_time').value;

        const dateObj       = new Date(dateVal + 'T00:00:00');
        const dateFormatted = dateObj.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        const [h, m]    = timeVal.split(':').map(Number);
        const ampm      = h >= 12 ? 'PM' : 'AM';
        const h12       = h % 12 || 12;
        const timeFormatted = h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;

        const payLabels = { full: 'Full Payment', partial_50: '50% Downpayment', partial_30: '30% Downpayment' };
        let upfront = 0, balance = 0;
        if (payType === 'full')         { upfront = total; }
        else if (payType === 'partial_50') { upfront = total * 0.5; balance = total - upfront; }
        else if (payType === 'partial_30') { upfront = total * 0.3; balance = total - upfront; }

        const fmt = v => '₱' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        document.getElementById('modal_pickup_date').textContent  = dateFormatted;
        document.getElementById('modal_pickup_time').textContent  = timeFormatted;
        document.getElementById('modal_payment_type').textContent = payLabels[payType];
        document.getElementById('modal_subtotal').textContent     = fmt(subtotalAmount);
        document.getElementById('modal_vat').textContent          = fmt(vatAmount);
        document.getElementById('modal_service_fee').textContent  = fmt(serviceFeeAmount);
        document.getElementById('modal_pay_now').textContent      = fmt(upfront);
        document.getElementById('modal_balance').textContent      = fmt(balance);

        document.getElementById('confirmOrderModal').classList.remove('hidden');
    }

    function closeConfirmModal() {
        document.getElementById('confirmOrderModal').classList.add('hidden');
    }

    function submitOrder() {
        document.getElementById('checkoutForm').submit();
    }

    // Init
    updatePaymentBreakdown();
    filterTimeSlots();
</script>

<?php endif; ?>
<?php
require_once '../../includes/customer_footer.php';
$conn->close();
?>