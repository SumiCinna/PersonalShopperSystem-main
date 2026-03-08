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
    $final_price = ($row['discount_price'] > 0 && $row['discount_price'] < $row['price']) ? $row['discount_price'] : $row['price'];
    $row['final_price'] = $final_price;
    $cart_items[] = $row;
    $total_amount += ($final_price * $row['quantity']);
}
$stmt->close();

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// --- DATE LIMIT: today + 3 days ---
$min_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+3 days'));

$page_title = 'Checkout';
require_once '../../includes/customer_header.php'; 
?>

<main class="container mx-auto px-6 py-12 flex-grow bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8 flex items-center">
            <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            Secure Checkout
        </h1>

        <form id="checkoutForm" action="../../core/customer/process_checkout.php" method="POST" class="flex flex-col lg:flex-row gap-8">
            
            <div class="lg:w-2/3 space-y-6">
                
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Schedule Pick-up
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Date <span class="text-gray-400 font-normal">(within 3 days)</span></label>
                            <input type="date" name="pickup_date" id="pickup_date" required
                                min="<?php echo $min_date; ?>"
                                max="<?php echo $max_date; ?>"
                                onchange="filterTimeSlots()"
                                class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1">Time <span class="text-gray-400 font-normal">(10:00 AM – 7:00 PM)</span></label>
                            <select name="pickup_time" id="pickup_time" required class="w-full bg-gray-50 border border-gray-300 rounded-lg p-3 outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="" disabled selected>Select a time slot</option>
                                <?php
                                $start = strtotime('10:00');
                                $end   = strtotime('19:00');
                                $interval = 90 * 60;
                                for ($t = $start; $t <= $end; $t += $interval) {
                                    $value   = date('H:i', $t);
                                    $display = date('g:i A', $t);
                                    echo "<option value=\"$value\">$display</option>";
                                }
                                ?>
                            </select>
                            <p class="text-xs text-gray-400 mt-1">Slots available every 1 hr 30 mins</p>
                            <p id="time_warning" class="hidden text-xs text-red-500 mt-1 font-semibold">⚠ No more slots available today. Please select a different date.</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-800 mb-4 border-b pb-2 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        Payment Details
                    </h3>
                    
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
                            <label class="block text-sm font-bold text-blue-900 mb-1">GCash Reference Number <span class="text-blue-500 font-normal">(13 digits)</span></label>
                            <input type="text" name="online_reference" id="online_reference" required
                                placeholder="Enter 13-digit Ref No."
                                maxlength="13"
                                minlength="13"
                                pattern="\d{13}"
                                inputmode="numeric"
                                oninput="this.value = this.value.replace(/\D/g, '').slice(0, 13)"
                                class="w-full bg-white border border-blue-300 rounded-lg p-3 font-mono font-bold tracking-widest text-lg">
                            <p class="text-xs text-blue-400 mt-1"><span id="refCharCount">0</span>/13 digits</p>
                        </div>
                    </div>
                </div>

                <!-- Notifications Info Banner -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-yellow-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <div>
                        <p class="text-sm font-bold text-yellow-800">Order Status Notifications</p>
                        <p class="text-xs text-yellow-700 mt-1">You'll receive updates when your order is <span class="font-semibold">received</span>, <span class="font-semibold">being prepared</span>, and <span class="font-semibold">ready for pickup</span> — via in-app alerts.</p>
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

                    <!-- Triggers modal instead of submitting directly -->
                    <button type="button" onclick="openConfirmModal()" class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-4 px-4 rounded-lg flex justify-center items-center transition shadow-md text-lg">
                        Confirm Order
                        <svg class="w-6 h-6 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
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

            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-green-600 to-green-500 rounded-t-2xl px-6 py-5 flex items-center gap-3">
                <div class="bg-white bg-opacity-20 rounded-full p-2">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                            <span class="text-gray-700"><span class="font-bold text-gray-900"><?php echo $item['quantity']; ?>x</span> <?php echo htmlspecialchars($item['name']); ?></span>
                            <span class="font-bold text-gray-800">₱<?php echo number_format($item['final_price'] * $item['quantity'], 2); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pickup Schedule -->
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

                <!-- Payment -->
                <div class="bg-green-50 border border-green-100 rounded-lg p-4 space-y-2">
                    <p class="text-xs text-green-500 font-bold uppercase tracking-wide mb-1">Payment Summary</p>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>Payment Type</span>
                        <span class="font-bold text-gray-900" id="modal_payment_type">—</span>
                    </div>
                    <div class="flex justify-between text-sm text-gray-700">
                        <span>GCash Ref No.</span>
                        <span class="font-mono font-bold text-gray-900" id="modal_gcash_ref">—</span>
                    </div>
                    <div class="border-t border-green-200 pt-2 flex justify-between text-sm">
                        <span class="font-bold text-green-700">Pay Now (Online)</span>
                        <span class="font-black text-green-700" id="modal_pay_now">—</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-bold text-red-500">Balance at Store</span>
                        <span class="font-black text-red-500" id="modal_balance">—</span>
                    </div>
                </div>

                <!-- Warning note -->
                <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <p class="text-xs text-amber-700 font-semibold">Once confirmed, your GCash payment will be submitted for verification. Make sure the reference number is correct — <span class="font-black">this cannot be changed after placing the order.</span></p>
                </div>

            </div>

            <!-- Modal Actions -->
            <div class="px-6 pb-6 flex gap-3">
                <button type="button" onclick="closeConfirmModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 rounded-xl transition text-sm">
                    Go Back & Edit
                </button>
                <button type="button" onclick="submitOrder()" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-black py-3 rounded-xl transition text-sm flex items-center justify-center gap-2 shadow-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Yes, Place Order
                </button>
            </div>

        </div>
    </div>
</div>

<script>
    function updatePaymentBreakdown() {
        const total = parseFloat(document.querySelector('input[name="total_amount"]').value);
        const type = document.getElementById('payment_type').value;
        let upfront = 0, balance = 0;

        if (type === 'full') {
            upfront = total; balance = 0;
        } else if (type === 'partial_50') {
            upfront = total * 0.5; balance = total - upfront;
        } else if (type === 'partial_30') {
            upfront = total * 0.3; balance = total - upfront;
        }

        document.getElementById('payNowDisplay').innerText = '₱' + upfront.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('balanceDueDisplay').innerText = '₱' + balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    // GCash ref number: live digit counter
    const refInput = document.getElementById('online_reference');
    const refCount = document.getElementById('refCharCount');
    refInput.addEventListener('input', function() {
        refCount.innerText = this.value.length;
        refCount.style.color = this.value.length === 13 ? '#16a34a' : '#60a5fa';
    });

    // --- TIME SLOT VALIDATION ---
    function filterTimeSlots() {
        const dateInput = document.getElementById('pickup_date');
        const timeSelect = document.getElementById('pickup_time');
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
            if (isToday) {
                const [h, m] = opt.value.split(':').map(Number);
                const isPast = (h * 60 + m) <= currentMinutes;
                opt.disabled = isPast;
                opt.style.color = isPast ? '#9ca3af' : '';
                if (!isPast && !firstAvailable) firstAvailable = opt;
            } else {
                opt.disabled = false;
                opt.style.color = '';
                if (!firstAvailable) firstAvailable = opt;
            }
        });

        const currentOpt = timeSelect.options[timeSelect.selectedIndex];
        if (!currentOpt || currentOpt.disabled || !currentOpt.value) {
            timeSelect.value = firstAvailable ? firstAvailable.value : '';
        }

        const allDisabled = isToday && Array.from(timeSelect.options).filter(o => o.value).every(o => o.disabled);
        const warningEl = document.getElementById('time_warning');
        if (allDisabled) { timeSelect.value = ''; warningEl.classList.remove('hidden'); }
        else { warningEl.classList.add('hidden'); }
    }

    // --- CONFIRMATION MODAL ---
    function openConfirmModal() {
        // Trigger native HTML5 validation first
        const form = document.getElementById('checkoutForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const total = parseFloat(document.querySelector('input[name="total_amount"]').value);
        const payType = document.getElementById('payment_type').value;
        const dateVal = document.getElementById('pickup_date').value;
        const timeVal = document.getElementById('pickup_time').value;
        const refVal  = document.getElementById('online_reference').value;

        // Format date nicely
        const dateObj = new Date(dateVal + 'T00:00:00');
        const dateFormatted = dateObj.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

        // Format time nicely
        const [h, m] = timeVal.split(':').map(Number);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h % 12 || 12;
        const timeFormatted = h12 + ':' + String(m).padStart(2, '0') + ' ' + ampm;

        // Payment type label
        const payLabels = { full: 'Full Payment', partial_50: '50% Downpayment', partial_30: '30% Downpayment' };

        // Amounts
        let upfront = 0, balance = 0;
        if (payType === 'full') { upfront = total; }
        else if (payType === 'partial_50') { upfront = total * 0.5; balance = total - upfront; }
        else if (payType === 'partial_30') { upfront = total * 0.3; balance = total - upfront; }

        const fmt = v => '₱' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});

        // Populate modal
        document.getElementById('modal_pickup_date').textContent = dateFormatted;
        document.getElementById('modal_pickup_time').textContent = timeFormatted;
        document.getElementById('modal_payment_type').textContent = payLabels[payType];
        document.getElementById('modal_gcash_ref').textContent = refVal;
        document.getElementById('modal_pay_now').textContent = fmt(upfront);
        document.getElementById('modal_balance').textContent = fmt(balance);

        document.getElementById('confirmOrderModal').classList.remove('hidden');
    }

    function closeConfirmModal() {
        document.getElementById('confirmOrderModal').classList.add('hidden');
    }

    function submitOrder() {
        document.getElementById('checkoutForm').submit();
    }

    updatePaymentBreakdown();
    filterTimeSlots();
</script>

<?php 
require_once '../../includes/customer_footer.php'; 
$conn->close(); 
?>